<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ServerMetricRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entity representing system metrics collected from a monitored Linux server via SSH.
 * Each record contains a snapshot of server metrics at a specific timestamp.
 */
#[ORM\Entity(repositoryClass: ServerMetricRepository::class)]
#[ORM\Table(name: 'server_metrics')]
#[ORM\Index(columns: ['timestamp'], name: 'idx_timestamp')]
class ServerMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Assert\NotNull]
    private \DateTimeInterface $timestamp;

    /**
     * CPU utilization percentage (0.00-100.00)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100)]
    private string $cpuUsage = '0.00';

    /**
     * RAM usage in GB
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    private string $ramUsage = '0.00';

    /**
     * Disk usage in GB
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    private string $diskUsage = '0.00';

    /**
     * Cumulative bytes read from disk
     */
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    private int $ioReadBytes = 0;

    /**
     * Cumulative bytes written to disk
     */
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    private int $ioWriteBytes = 0;

    /**
     * Cumulative bytes sent over network
     */
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    private int $networkSentBytes = 0;

    /**
     * Cumulative bytes received over network
     */
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    private int $networkReceivedBytes = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getCpuUsage(): string
    {
        return $this->cpuUsage;
    }

    public function setCpuUsage(string $cpuUsage): self
    {
        $this->cpuUsage = $cpuUsage;
        return $this;
    }

    public function getRamUsage(): string
    {
        return $this->ramUsage;
    }

    public function setRamUsage(string $ramUsage): self
    {
        $this->ramUsage = $ramUsage;
        return $this;
    }

    public function getDiskUsage(): string
    {
        return $this->diskUsage;
    }

    public function setDiskUsage(string $diskUsage): self
    {
        $this->diskUsage = $diskUsage;
        return $this;
    }

    public function getIoReadBytes(): int
    {
        return $this->ioReadBytes;
    }

    public function setIoReadBytes(int $ioReadBytes): self
    {
        $this->ioReadBytes = $ioReadBytes;
        return $this;
    }

    public function getIoWriteBytes(): int
    {
        return $this->ioWriteBytes;
    }

    public function setIoWriteBytes(int $ioWriteBytes): self
    {
        $this->ioWriteBytes = $ioWriteBytes;
        return $this;
    }

    public function getNetworkSentBytes(): int
    {
        return $this->networkSentBytes;
    }

    public function setNetworkSentBytes(int $networkSentBytes): self
    {
        $this->networkSentBytes = $networkSentBytes;
        return $this;
    }

    public function getNetworkReceivedBytes(): int
    {
        return $this->networkReceivedBytes;
    }

    public function setNetworkReceivedBytes(int $networkReceivedBytes): self
    {
        $this->networkReceivedBytes = $networkReceivedBytes;
        return $this;
    }
}

