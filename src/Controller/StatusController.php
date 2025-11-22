<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ServerMetricRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling system status endpoints.
 */
class StatusController extends AbstractController
{
    private const COLLECTION_INTERVAL_SECONDS = 60; // Expected collection interval (1 minute)
    private const SSH_CONNECTION_TIMEOUT_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly ServerMetricRepository $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Retrieve system status information including last successful metric collection
     * timestamp and SSH connection status.
     */
    #[Route('/api/status', name: 'api_status', methods: ['GET'])]
    public function getStatus(): JsonResponse
    {
        try {
            $timeRangeInfo = $this->repository->getTimeRangeInfo();
            $latest = $this->repository->findLatest();

            $dataAvailable = $timeRangeInfo['total'] > 0;
            $lastCollection = $latest?->getTimestamp();
            $lastCollectionStatus = 'unknown';
            $sshConnected = false;

            if ($lastCollection !== null) {
                $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $secondsSinceLastCollection = $now->getTimestamp() - $lastCollection->getTimestamp();

                // Determine collection status
                if ($secondsSinceLastCollection <= self::COLLECTION_INTERVAL_SECONDS * 2) {
                    $lastCollectionStatus = 'success';
                } elseif ($secondsSinceLastCollection <= self::COLLECTION_INTERVAL_SECONDS * 5) {
                    $lastCollectionStatus = 'delayed';
                } else {
                    $lastCollectionStatus = 'failed';
                }

                // SSH connection status (within last 5 minutes)
                $sshConnected = $secondsSinceLastCollection <= self::SSH_CONNECTION_TIMEOUT_SECONDS;
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'last_collection' => $lastCollection?->format('c'),
                    'last_collection_status' => $lastCollectionStatus,
                    'ssh_connected' => $sshConnected,
                    'data_available' => $dataAvailable,
                    'oldest_record' => $timeRangeInfo['oldest']?->format('c'),
                    'newest_record' => $timeRangeInfo['newest']?->format('c'),
                    'total_records' => $timeRangeInfo['total'],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving system status', [
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

