<?php

declare(strict_types=1);

namespace App\Service;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;

/**
 * Service for collecting system metrics from a Linux server via SSH.
 * Reads metrics from /proc filesystem and returns structured data.
 */
class SshMetricsCollector
{
    private const SSH_TIMEOUT = 30; // seconds
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_BASE = 2; // seconds for exponential backoff

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Collect all metrics from the server via SSH.
     *
     * @param string $host Server address (IP or hostname)
     * @param int $port SSH port (default: 22)
     * @param string $username SSH username
     * @param string $privateKey Base64 encoded private key
     * @return array{cpu_usage: float, ram_usage: float, disk_usage: float, io_read_bytes: int, io_write_bytes: int, network_sent_bytes: int, network_received_bytes: int}
     * @throws \RuntimeException if connection fails after all retries
     */
    public function collectMetrics(string $host, int $port, string $username, string $privateKey): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                return $this->executeCollection($host, $port, $username, $privateKey);
            } catch (\Exception $e) {
                $attempt++;
                $lastException = $e;

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_BASE ** $attempt;
                    $this->logger->warning(
                        sprintf(
                            'SSH connection attempt %d/%d failed: %s. Retrying in %d seconds...',
                            $attempt,
                            self::MAX_RETRIES,
                            $e->getMessage(),
                            $delay
                        ),
                        [
                            'host' => $host,
                            'port' => $port,
                            'attempt' => $attempt,
                        ]
                    );
                    sleep($delay);
                }
            }
        }

        // All retries exhausted
        $this->logger->error(
            sprintf(
                'Failed to collect metrics after %d attempts: %s',
                self::MAX_RETRIES,
                $lastException?->getMessage() ?? 'Unknown error'
            ),
            [
                'host' => $host,
                'port' => $port,
                'exception' => $lastException,
            ]
        );

        throw new \RuntimeException(
            sprintf(
                'Failed to collect metrics from %s:%d after %d attempts: %s',
                $host,
                $port,
                self::MAX_RETRIES,
                $lastException?->getMessage() ?? 'Unknown error'
            ),
            0,
            $lastException
        );
    }

    /**
     * Execute the actual metric collection via SSH.
     *
     * @return array{cpu_usage: float, ram_usage: float, disk_usage: float, io_read_bytes: int, io_write_bytes: int, network_sent_bytes: int, network_received_bytes: int}
     * @throws \RuntimeException if SSH connection or command execution fails
     */
    private function executeCollection(string $host, int $port, string $username, string $privateKey): array
    {
        // Decode base64 private key
        $decodedKey = base64_decode($privateKey, true);
        if ($decodedKey === false) {
            throw new \RuntimeException('Failed to decode base64 private key');
        }

        // Load private key
        try {
            $key = PublicKeyLoader::load($decodedKey);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to load private key: ' . $e->getMessage(), 0, $e);
        }

        // Connect via SSH
        $ssh = new SSH2($host, $port, self::SSH_TIMEOUT);
        if (!$ssh->login($username, $key)) {
            throw new \RuntimeException('SSH login failed');
        }

        try {
            // Collect all metrics
            $cpuUsage = $this->collectCpuUsage($ssh);
            $ramUsage = $this->collectRamUsage($ssh);
            $diskUsage = $this->collectDiskUsage($ssh);
            $ioMetrics = $this->collectIoMetrics($ssh);
            $networkMetrics = $this->collectNetworkMetrics($ssh);

            return [
                'cpu_usage' => $cpuUsage,
                'ram_usage' => $ramUsage,
                'disk_usage' => $diskUsage,
                'io_read_bytes' => $ioMetrics['read'],
                'io_write_bytes' => $ioMetrics['write'],
                'network_sent_bytes' => $networkMetrics['sent'],
                'network_received_bytes' => $networkMetrics['received'],
            ];
        } finally {
            $ssh->disconnect();
        }
    }

    /**
     * Collect CPU usage from /proc/loadavg.
     * Returns average CPU usage percentage (0-100).
     */
    private function collectCpuUsage(SSH2 $ssh): float
    {
        $output = $ssh->exec('cat /proc/loadavg');
        if ($output === false) {
            throw new \RuntimeException('Failed to read /proc/loadavg');
        }

        $parts = explode(' ', trim($output));
        if (count($parts) < 1) {
            throw new \RuntimeException('Invalid format in /proc/loadavg');
        }

        // Load average for 1 minute (first value)
        // Convert to percentage: load_avg * 100 / number_of_cores
        // For simplicity, we'll use load_avg * 100 (assuming single core or normalized)
        // In production, you might want to get actual CPU count
        $loadAvg = (float) $parts[0];

        // Get number of CPU cores
        $cpuInfo = $ssh->exec('nproc');
        $cores = $cpuInfo !== false ? (int) trim($cpuInfo) : 1;
        if ($cores < 1) {
            $cores = 1;
        }

        // Calculate CPU usage percentage: (load_avg / cores) * 100
        // Cap at 100%
        $cpuUsage = min(100.0, ($loadAvg / $cores) * 100.0);

        return round($cpuUsage, 2);
    }

    /**
     * Collect RAM usage from /proc/meminfo.
     * Returns RAM usage in GB.
     */
    private function collectRamUsage(SSH2 $ssh): float
    {
        $output = $ssh->exec('cat /proc/meminfo');
        if ($output === false) {
            throw new \RuntimeException('Failed to read /proc/meminfo');
        }

        $memTotal = null;
        $memAvailable = null;

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^MemTotal:\s+(\d+)\s+kB$/', $line, $matches)) {
                $memTotal = (int) $matches[1] * 1024; // Convert kB to bytes
            } elseif (preg_match('/^MemAvailable:\s+(\d+)\s+kB$/', $line, $matches)) {
                $memAvailable = (int) $matches[1] * 1024; // Convert kB to bytes
            }
        }

        if ($memTotal === null || $memAvailable === null) {
            throw new \RuntimeException('Failed to parse /proc/meminfo (missing MemTotal or MemAvailable)');
        }

        // Calculate used RAM: total - available
        $memUsed = $memTotal - $memAvailable;

        // Convert to GB
        $ramUsageGb = $memUsed / (1024 * 1024 * 1024);

        return round($ramUsageGb, 2);
    }

    /**
     * Collect disk usage using df command.
     * Returns disk usage in GB for root filesystem.
     */
    private function collectDiskUsage(SSH2 $ssh): float
    {
        // Get root filesystem usage in bytes using awk for reliable parsing
        $output = $ssh->exec("df -B1 / 2>/dev/null | awk 'NR==2 {print \$3}'");
        if ($output === false || !is_numeric(trim($output))) {
            // Fallback: try without awk
            $output = $ssh->exec("df -B1 / 2>/dev/null | tail -n 1");
            if ($output === false) {
                throw new \RuntimeException('Failed to execute df command');
            }

            $parts = preg_split('/\s+/', trim($output));
            if (count($parts) < 4) {
                throw new \RuntimeException('Failed to parse df output');
            }

            // Format: Filesystem 1B-blocks Used Available Use% Mounted
            // Used is at index 2 (Filesystem, 1B-blocks, Used, Available, ...)
            $usedBytes = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : null;
        } else {
            $usedBytes = (int) trim($output);
        }

        if ($usedBytes === null || $usedBytes < 0) {
            throw new \RuntimeException('Failed to parse disk usage from df output');
        }

        // Convert bytes to GB
        $diskUsageGb = $usedBytes / (1024 * 1024 * 1024);

        return round($diskUsageGb, 2);
    }

    /**
     * Collect I/O metrics from /proc/diskstats.
     * Returns cumulative read and write bytes.
     */
    private function collectIoMetrics(SSH2 $ssh): array
    {
        $output = $ssh->exec('cat /proc/diskstats');
        if ($output === false) {
            throw new \RuntimeException('Failed to read /proc/diskstats');
        }

        $totalReadBytes = 0;
        $totalWriteBytes = 0;

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Format: major minor name rio rmerge rsect ruse wio wmerge wsect wuse ...
            // rio = read I/Os, rsect = sectors read (multiply by 512 for bytes)
            // wio = write I/Os, wsect = sectors written (multiply by 512 for bytes)
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 14) {
                continue;
            }

            // Skip loop devices and other virtual devices
            $deviceName = $parts[2];
            if (str_starts_with($deviceName, 'loop') || str_starts_with($deviceName, 'ram')) {
                continue;
            }

            // Read sectors (index 5) and write sectors (index 9)
            // Each sector is 512 bytes
            $readSectors = isset($parts[5]) ? (int) $parts[5] : 0;
            $writeSectors = isset($parts[9]) ? (int) $parts[9] : 0;

            $totalReadBytes += $readSectors * 512;
            $totalWriteBytes += $writeSectors * 512;
        }

        return [
            'read' => $totalReadBytes,
            'write' => $totalWriteBytes,
        ];
    }

    /**
     * Collect network metrics from /proc/net/dev.
     * Returns cumulative sent and received bytes.
     */
    private function collectNetworkMetrics(SSH2 $ssh): array
    {
        $output = $ssh->exec('cat /proc/net/dev');
        if ($output === false) {
            throw new \RuntimeException('Failed to read /proc/net/dev');
        }

        $totalSentBytes = 0;
        $totalReceivedBytes = 0;

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, 'Inter-') || str_starts_with($line, ' face')) {
                continue;
            }

            // Format: interface: bytes packets errs drop fifo frame compressed multicast bytes packets ...
            // Split by colon first to separate interface name
            if (!str_contains($line, ':')) {
                continue;
            }

            [$interfaceName, $data] = explode(':', $line, 2);
            $interfaceName = trim($interfaceName);
            $data = trim($data);

            // Skip loopback interface
            if ($interfaceName === 'lo') {
                continue;
            }

            // Parse data part: received_bytes received_packets ... sent_bytes sent_packets ...
            $parts = preg_split('/\s+/', $data);
            if (count($parts) < 16) {
                continue;
            }

            // Received bytes (index 0), sent bytes (index 8)
            $receivedBytes = isset($parts[0]) && is_numeric($parts[0]) ? (int) $parts[0] : 0;
            $sentBytes = isset($parts[8]) && is_numeric($parts[8]) ? (int) $parts[8] : 0;

            $totalReceivedBytes += $receivedBytes;
            $totalSentBytes += $sentBytes;
        }

        return [
            'sent' => $totalSentBytes,
            'received' => $totalReceivedBytes,
        ];
    }
}
