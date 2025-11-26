<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MetricsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MetricsService.
 * Tests business logic in isolation without database dependencies.
 */
class MetricsServiceTest extends TestCase
{
    private MetricsService $service;
    private $repositoryMock;

    protected function setUp(): void
    {
        // Mock the repository dependency
        $this->repositoryMock = $this->createMock(\App\Repository\ServerMetricRepository::class);
        $this->service = new MetricsService($this->repositoryMock);
    }

    #[Test]
    public function calculateTimeRangeWithValidRangeReturnsCorrectTimeRange(): void
    {
        // Arrange
        $range = '1h';

        // Act
        $result = $this->service->calculateTimeRange($range);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('start', $result);
        $this->assertArrayHasKey('end', $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['start']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['end']);

        // Verify that start is before end
        $this->assertLessThan($result['end']->getTimestamp(), $result['start']->getTimestamp());

        // Verify that the difference is approximately 1 hour (with 5 second tolerance)
        $diff = $result['end']->getTimestamp() - $result['start']->getTimestamp();
        $this->assertEqualsWithDelta(3600, $diff, 5);
    }

    #[Test]
    #[DataProvider('validRangesProvider')]
    public function calculateTimeRangeWithValidRangesReturnsCorrectTimeRange(string $range, int $expectedSeconds): void
    {
        // Act
        $result = $this->service->calculateTimeRange($range);

        // Assert
        $diff = $result['end']->getTimestamp() - $result['start']->getTimestamp();
        $this->assertEqualsWithDelta($expectedSeconds, $diff, 5);
    }

    #[Test]
    public function calculateTimeRangeWithInvalidRangeThrowsException(): void
    {
        // Arrange
        $invalidRange = 'invalid';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid range');

        // Act
        $this->service->calculateTimeRange($invalidRange);
    }

    #[Test]
    public function calculateCustomTimeRangeWithValidDatesReturnsCorrectRange(): void
    {
        // Arrange
        $start = '2024-01-15T10:00:00Z';
        $end = '2024-01-15T12:00:00Z';

        // Act
        $result = $this->service->calculateCustomTimeRange($start, $end);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('start', $result);
        $this->assertArrayHasKey('end', $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['start']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['end']);
        $this->assertLessThan($result['end']->getTimestamp(), $result['start']->getTimestamp());
    }

    #[Test]
    public function calculateCustomTimeRangeWithStartAfterEndThrowsException(): void
    {
        // Arrange
        $start = '2024-01-15T12:00:00Z';
        $end = '2024-01-15T10:00:00Z';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start time must be before end time');

        // Act
        $this->service->calculateCustomTimeRange($start, $end);
    }

    #[Test]
    public function calculateCustomTimeRangeWithRangeExceeding30DaysThrowsException(): void
    {
        // Arrange
        $start = '2024-01-01T00:00:00Z';
        $end = '2024-02-15T00:00:00Z'; // More than 30 days

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum range is 30 days');

        // Act
        $this->service->calculateCustomTimeRange($start, $end);
    }

    #[Test]
    public function calculateCustomTimeRangeWithInvalidDateFormatThrowsException(): void
    {
        // Arrange
        $start = 'invalid-date';
        $end = '2024-01-15T12:00:00Z';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid datetime format');

        // Act
        $this->service->calculateCustomTimeRange($start, $end);
    }

    /**
     * Data provider for valid ranges.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function validRangesProvider(): array
    {
        return [
            '1 hour' => ['1h', 3600],
            '6 hours' => ['6h', 21600],
            '24 hours' => ['24h', 86400],
            '7 days' => ['7d', 604800],
            '30 days' => ['30d', 2592000],
        ];
    }
}

