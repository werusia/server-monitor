<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MetricsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling metrics endpoints.
 */
class MetricsController extends AbstractController
{
    public function __construct(
        private readonly MetricsService $metricsService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Retrieve server metrics for a specified time range.
     * Automatically aggregates data for long ranges (7d, 30d) using 10-minute buckets.
     *
     * Query parameters:
     * - range: 1h, 6h, 24h, 7d, 30d (default: 24h)
     * - start: Custom start time in ISO 8601 format (UTC) - overrides range
     * - end: Custom end time in ISO 8601 format (UTC) - overrides range
     */
    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $range = $request->query->get('range', '24h');
            $start = $request->query->get('start');
            $end = $request->query->get('end');

            // If custom start/end provided, use them
            if ($start !== null || $end !== null) {
                if ($start === null || $end === null) {
                    return new JsonResponse(
                        [
                            'success' => false,
                            'error' => 'Both start and end parameters are required when using custom range',
                        ],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                try {
                    $timeRange = $this->metricsService->calculateCustomTimeRange($start, $end);
                    $result = $this->metricsService->getMetrics($timeRange['start'], $timeRange['end'], null);
                } catch (\InvalidArgumentException $e) {
                    return new JsonResponse(
                        [
                            'success' => false,
                            'error' => $e->getMessage(),
                        ],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            } else {
                // Use range parameter
                try {
                    $timeRange = $this->metricsService->calculateTimeRange($range);
                    $result = $this->metricsService->getMetrics($timeRange['start'], $timeRange['end'], $range);
                } catch (\InvalidArgumentException $e) {
                    return new JsonResponse(
                        [
                            'success' => false,
                            'error' => $e->getMessage(),
                        ],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            return new JsonResponse([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving metrics', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Internal server error',
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Retrieve the most recent server metrics record.
     * Used for displaying last known values when SSH collection fails.
     */
    #[Route('/api/metrics/latest', name: 'api_metrics_latest', methods: ['GET'])]
    public function getLatest(): JsonResponse
    {
        try {
            $data = $this->metricsService->getLatest();

            return new JsonResponse([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving latest metrics', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Internal server error',
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Retrieve aggregated statistics for a specified time range.
     * Provides summary information for dashboard overview.
     *
     * Query parameters:
     * - range: 1h, 6h, 24h, 7d, 30d (default: 24h)
     */
    #[Route('/api/metrics/stats', name: 'api_metrics_stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        try {
            $range = $request->query->get('range', '24h');

            try {
                $timeRange = $this->metricsService->calculateTimeRange($range);
            } catch (\InvalidArgumentException $e) {
                return new JsonResponse(
                    [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $stats = $this->metricsService->getStatistics($timeRange['start'], $timeRange['end']);

            return new JsonResponse([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving metrics statistics', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Internal server error',
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

