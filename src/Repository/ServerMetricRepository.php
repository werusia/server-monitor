<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ServerMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServerMetric>
 */
class ServerMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerMetric::class);
    }

    /**
     * Find metrics within a time range, ordered by timestamp ascending.
     *
     * @return ServerMetric[]
     */
    public function findByTimeRange(\DateTimeInterface $startTime, \DateTimeInterface $endTime): array
    {
        return $this->createQueryBuilder('sm')
            ->where('sm.timestamp >= :startTime')
            ->andWhere('sm.timestamp <= :endTime')
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->orderBy('sm.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the most recent metric record.
     */
    public function findLatest(): ?ServerMetric
    {
        return $this->createQueryBuilder('sm')
            ->orderBy('sm.timestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get aggregated statistics for a time range.
     * Returns array with min, max, avg, and current values for CPU, RAM, and Disk.
     *
     * @return array<string, mixed>|null
     */
    public function getStatistics(\DateTimeInterface $startTime, \DateTimeInterface $endTime): ?array
    {
        $result = $this->createQueryBuilder('sm')
            ->select([
                'MIN(sm.cpuUsage) as cpu_min',
                'MAX(sm.cpuUsage) as cpu_max',
                'AVG(sm.cpuUsage) as cpu_avg',
                'MIN(sm.ramUsage) as ram_min',
                'MAX(sm.ramUsage) as ram_max',
                'AVG(sm.ramUsage) as ram_avg',
                'MIN(sm.diskUsage) as disk_min',
                'MAX(sm.diskUsage) as disk_max',
                'AVG(sm.diskUsage) as disk_avg',
                'COUNT(sm.id) as record_count',
            ])
            ->where('sm.timestamp >= :startTime')
            ->andWhere('sm.timestamp <= :endTime')
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getSingleResult();

        if (!$result || (int) $result['record_count'] === 0) {
            return null;
        }

        return $result;
    }

    /**
     * Get oldest and newest timestamps, total record count.
     *
     * @return array{oldest: \DateTimeImmutable|null, newest: \DateTimeImmutable|null, total: int}
     */
    public function getTimeRangeInfo(): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select([
                'MIN(sm.timestamp) as oldest',
                'MAX(sm.timestamp) as newest',
                'COUNT(sm.id) as total',
            ])
            ->getQuery()
            ->getSingleResult();

        return [
            'oldest' => $result['oldest'] ? new \DateTimeImmutable($result['oldest']) : null,
            'newest' => $result['newest'] ? new \DateTimeImmutable($result['newest']) : null,
            'total' => (int) $result['total'],
        ];
    }
}

