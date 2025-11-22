<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ServerMetric;
use App\Repository\ServerMetricRepository;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * Service handling business logic for server metrics retrieval and aggregation.
 */
class MetricsService
{
    private const VALID_RANGES = ['1h', '6h', '24h', '7d', '30d'];
    private const AGGREGATION_BUCKET_SECONDS = 600; // 10 minutes

    public function __construct(
        private readonly ServerMetricRepository $repository
    ) {
    }

    /**
     * Calculate start and end time based on range parameter.
     *
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
     * @throws \InvalidArgumentException if range is invalid
     */
    public function calculateTimeRange(string $range): array
    {
        if (!in_array($range, self::VALID_RANGES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid range. Must be one of: %s', implode(', ', self::VALID_RANGES))
            );
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $start = match ($range) {
            '1h' => $now->modify('-1 hour'),
            '6h' => $now->modify('-6 hours'),
            '24h' => $now->modify('-24 hours'),
            '7d' => $now->modify('-7 days'),
            '30d' => $now->modify('-30 days'),
            default => throw new \InvalidArgumentException('Invalid range'),
        };

        return [
            'start' => $start,
            'end' => $now,
        ];
    }

    /**
     * Calculate time range from custom start and end parameters.
     *
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
     * @throws \InvalidArgumentException if dates are invalid or range is too large
     */
    public function calculateCustomTimeRange(string $start, string $end): array
    {
        try {
            $startTime = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
            $endTime = new \DateTimeImmutable($end, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                'Invalid datetime format. Use ISO 8601 format (e.g., 2024-01-15T14:30:00Z)'
            );
        }

        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time');
        }

        $diff = $endTime->getTimestamp() - $startTime->getTimestamp();
        $maxRange = 30 * 24 * 60 * 60; // 30 days in seconds

        if ($diff > $maxRange) {
            throw new \InvalidArgumentException('Maximum range is 30 days');
        }

        return [
            'start' => $startTime,
            'end' => $endTime,
        ];
    }

    /**
     * Get metrics for a time range with automatic aggregation for long ranges.
     *
     * @return array{data: array, meta: array}
     */
    public function getMetrics(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime, ?string $range = null): array
    {
        $diff = $endTime->getTimestamp() - $startTime->getTimestamp();
        $shouldAggregate = $diff >= (7 * 24 * 60 * 60); // 7 days or more

        if ($shouldAggregate) {
            return $this->getAggregatedMetrics($startTime, $endTime, $range);
        }

        return $this->getRawMetrics($startTime, $endTime, $range);
    }

    /**
     * Get raw metrics without aggregation.
     *
     * @return array{data: array, meta: array}
     */
    private function getRawMetrics(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime, ?string $range): array
    {
        $metrics = $this->repository->findByTimeRange($startTime, $endTime);

        $data = array_map(function (ServerMetric $metric) {
            return [
                'id' => $metric->getId(),
                'timestamp' => $metric->getTimestamp()->format('c'),
                'cpu_usage' => (float) $metric->getCpuUsage(),
                'ram_usage' => (float) $metric->getRamUsage(),
                'disk_usage' => (float) $metric->getDiskUsage(),
                'io_read_bytes' => $metric->getIoReadBytes(),
                'io_write_bytes' => $metric->getIoWriteBytes(),
                'network_sent_bytes' => $metric->getNetworkSentBytes(),
                'network_received_bytes' => $metric->getNetworkReceivedBytes(),
            ];
        }, $metrics);

        return [
            'data' => $data,
            'meta' => [
                'range' => $range ?? 'custom',
                'count' => count($data),
                'aggregated' => false,
                'start_time' => $startTime->format('c'),
                'end_time' => $endTime->format('c'),
            ],
        ];
    }

    /**
     * Get aggregated metrics using SQL GROUP BY with 10-minute buckets.
     *
     * @return array{data: array, meta: array}
     */
    private function getAggregatedMetrics(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime, ?string $range): array
    {
        $em = $this->repository->getEntityManager();
        $conn = $em->getConnection();

        $sql = "
            SELECT 
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / :bucket) * :bucket) AS timestamp,
                AVG(cpu_usage) AS cpu_usage,
                AVG(ram_usage) AS ram_usage,
                AVG(disk_usage) AS disk_usage,
                MAX(io_read_bytes) AS io_read_bytes,
                MAX(io_write_bytes) AS io_write_bytes,
                MAX(network_sent_bytes) AS network_sent_bytes,
                MAX(network_received_bytes) AS network_received_bytes
            FROM server_metrics
            WHERE timestamp >= :start_time AND timestamp <= :end_time
            GROUP BY FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / :bucket) * :bucket)
            ORDER BY timestamp ASC
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'bucket' => self::AGGREGATION_BUCKET_SECONDS,
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'end_time' => $endTime->format('Y-m-d H:i:s'),
        ]);

        $data = [];
        while ($row = $result->fetchAssociative()) {
            $data[] = [
                'id' => null,
                'timestamp' => (new \DateTimeImmutable($row['timestamp']))->format('c'),
                'cpu_usage' => (float) $row['cpu_usage'],
                'ram_usage' => (float) $row['ram_usage'],
                'disk_usage' => (float) $row['disk_usage'],
                'io_read_bytes' => (int) $row['io_read_bytes'],
                'io_write_bytes' => (int) $row['io_write_bytes'],
                'network_sent_bytes' => (int) $row['network_sent_bytes'],
                'network_received_bytes' => (int) $row['network_received_bytes'],
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'range' => $range ?? 'custom',
                'count' => count($data),
                'aggregated' => true,
                'bucket_size_minutes' => self::AGGREGATION_BUCKET_SECONDS / 60,
                'start_time' => $startTime->format('c'),
                'end_time' => $endTime->format('c'),
            ],
        ];
    }

    /**
     * Get the latest metric record.
     */
    public function getLatest(): ?array
    {
        $metric = $this->repository->findLatest();

        if ($metric === null) {
            return null;
        }

        return [
            'id' => $metric->getId(),
            'timestamp' => $metric->getTimestamp()->format('c'),
            'cpu_usage' => (float) $metric->getCpuUsage(),
            'ram_usage' => (float) $metric->getRamUsage(),
            'disk_usage' => (float) $metric->getDiskUsage(),
            'io_read_bytes' => $metric->getIoReadBytes(),
            'io_write_bytes' => $metric->getIoWriteBytes(),
            'network_sent_bytes' => $metric->getNetworkSentBytes(),
            'network_received_bytes' => $metric->getNetworkReceivedBytes(),
        ];
    }

    /**
     * Get aggregated statistics for a time range.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime): array
    {
        $stats = $this->repository->getStatistics($startTime, $endTime);
        $latest = $this->repository->findLatest();

        if ($stats === null) {
            // Return empty stats structure
            return [
                'cpu' => ['min' => null, 'max' => null, 'avg' => null, 'current' => null],
                'ram' => ['min' => null, 'max' => null, 'avg' => null, 'current' => null],
                'disk' => ['min' => null, 'max' => null, 'avg' => null, 'current' => null],
                'io' => [
                    'read_total' => null,
                    'write_total' => null,
                    'read_avg_per_minute' => null,
                    'write_avg_per_minute' => null,
                ],
                'network' => [
                    'sent_total' => null,
                    'received_total' => null,
                    'sent_avg_per_minute' => null,
                    'received_avg_per_minute' => null,
                ],
                'last_update' => $latest?->getTimestamp()->format('c'),
                'record_count' => 0,
            ];
        }

        // Get first and last records for I/O and Network calculations
        $firstMetric = $this->getFirstMetricInRange($startTime, $endTime);
        $lastMetric = $this->getLastMetricInRange($startTime, $endTime);

        $timeSpanMinutes = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;
        if ($timeSpanMinutes <= 0) {
            $timeSpanMinutes = 1;
        }

        $ioReadAvg = 0;
        $ioWriteAvg = 0;
        $networkSentAvg = 0;
        $networkReceivedAvg = 0;

        if ($firstMetric !== null && $lastMetric !== null) {
            $ioReadDiff = $lastMetric->getIoReadBytes() - $firstMetric->getIoReadBytes();
            $ioWriteDiff = $lastMetric->getIoWriteBytes() - $firstMetric->getIoWriteBytes();
            $networkSentDiff = $lastMetric->getNetworkSentBytes() - $firstMetric->getNetworkSentBytes();
            $networkReceivedDiff = $lastMetric->getNetworkReceivedBytes() - $firstMetric->getNetworkReceivedBytes();

            $ioReadAvg = max(0, (int) ($ioReadDiff / $timeSpanMinutes));
            $ioWriteAvg = max(0, (int) ($ioWriteDiff / $timeSpanMinutes));
            $networkSentAvg = max(0, (int) ($networkSentDiff / $timeSpanMinutes));
            $networkReceivedAvg = max(0, (int) ($networkReceivedDiff / $timeSpanMinutes));
        }

        $currentCpu = $latest ? (float) $latest->getCpuUsage() : null;
        $currentRam = $latest ? (float) $latest->getRamUsage() : null;
        $currentDisk = $latest ? (float) $latest->getDiskUsage() : null;

        return [
            'cpu' => [
                'min' => (float) $stats['cpu_min'],
                'max' => (float) $stats['cpu_max'],
                'avg' => (float) $stats['cpu_avg'],
                'current' => $currentCpu,
            ],
            'ram' => [
                'min' => (float) $stats['ram_min'],
                'max' => (float) $stats['ram_max'],
                'avg' => (float) $stats['ram_avg'],
                'current' => $currentRam,
            ],
            'disk' => [
                'min' => (float) $stats['disk_min'],
                'max' => (float) $stats['disk_max'],
                'avg' => (float) $stats['disk_avg'],
                'current' => $currentDisk,
            ],
            'io' => [
                'read_total' => $lastMetric?->getIoReadBytes() ?? 0,
                'write_total' => $lastMetric?->getIoWriteBytes() ?? 0,
                'read_avg_per_minute' => $ioReadAvg,
                'write_avg_per_minute' => $ioWriteAvg,
            ],
            'network' => [
                'sent_total' => $lastMetric?->getNetworkSentBytes() ?? 0,
                'received_total' => $lastMetric?->getNetworkReceivedBytes() ?? 0,
                'sent_avg_per_minute' => $networkSentAvg,
                'received_avg_per_minute' => $networkReceivedAvg,
            ],
            'last_update' => $latest?->getTimestamp()->format('c'),
            'record_count' => (int) $stats['record_count'],
        ];
    }

    /**
     * Get first metric in time range.
     */
    private function getFirstMetricInRange(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime): ?ServerMetric
    {
        $metrics = $this->repository->findByTimeRange($startTime, $endTime);
        return $metrics[0] ?? null;
    }

    /**
     * Get last metric in time range.
     */
    private function getLastMetricInRange(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime): ?ServerMetric
    {
        $metrics = $this->repository->findByTimeRange($startTime, $endTime);
        return $metrics[count($metrics) - 1] ?? null;
    }
}

