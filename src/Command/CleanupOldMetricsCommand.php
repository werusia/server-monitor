<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to delete server metrics older than 90 days.
 * Designed to be run daily by cron (e.g., at 3:00 AM).
 * Deletes records in batches to minimize database locking.
 */
#[AsCommand(
    name: 'app:cleanup-old-metrics',
    description: 'Delete server metrics older than 90 days (runs in batches)'
)]
class CleanupOldMetricsCommand extends Command
{
    private const BATCH_SIZE = 1000;
    private const BATCH_DELAY_MICROSECONDS = 100000; // 0.1 seconds
    private const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'retention-days',
            'r',
            InputOption::VALUE_OPTIONAL,
            'Number of days to retain (default: 90)',
            self::DEFAULT_RETENTION_DAYS
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $retentionDays = (int) $input->getOption('retention-days');

        if ($retentionDays < 1) {
            $io->error('Retention days must be at least 1');
            return Command::FAILURE;
        }

        $cutoffDate = new \DateTime('now', new \DateTimeZone('UTC'));
        $cutoffDate->modify("-{$retentionDays} days");

        $io->info(sprintf(
            'Starting cleanup of metrics older than %d days (before %s)',
            $retentionDays,
            $cutoffDate->format('Y-m-d H:i:s')
        ));

        $this->logger->info('CleanupOldMetricsCommand started', [
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->format('c'),
        ]);

        $totalDeleted = 0;
        $batchCount = 0;

        try {
            do {
                $deletedInBatch = $this->deleteBatch($cutoffDate);

                if ($deletedInBatch > 0) {
                    $totalDeleted += $deletedInBatch;
                    $batchCount++;

                    $io->comment(sprintf(
                        'Batch %d: Deleted %d records (total: %d)',
                        $batchCount,
                        $deletedInBatch,
                        $totalDeleted
                    ));

                    // Small delay between batches to minimize database locking
                    usleep(self::BATCH_DELAY_MICROSECONDS);
                }
            } while ($deletedInBatch > 0);

            if ($totalDeleted > 0) {
                $io->success(sprintf(
                    'Cleanup completed: Deleted %d records in %d batches',
                    $totalDeleted,
                    $batchCount
                ));

                $this->logger->info('CleanupOldMetricsCommand completed successfully', [
                    'total_deleted' => $totalDeleted,
                    'batch_count' => $batchCount,
                    'retention_days' => $retentionDays,
                ]);
            } else {
                $io->info('No records to delete');
                $this->logger->info('CleanupOldMetricsCommand: No records to delete', [
                    'retention_days' => $retentionDays,
                ]);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to cleanup old metrics: ' . $e->getMessage());
            $this->logger->error('CleanupOldMetricsCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'total_deleted' => $totalDeleted,
                'batch_count' => $batchCount,
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Delete a batch of old records.
     *
     * @return int Number of records deleted in this batch
     */
    private function deleteBatch(\DateTime $cutoffDate): int
    {
        $conn = $this->entityManager->getConnection();

        // Use native SQL with LIMIT for efficient batch deletion
        // Note: LIMIT must be a literal value, not a parameter in MySQL
        $sql = sprintf(
            "DELETE FROM server_metrics WHERE timestamp < :cutoff_date LIMIT %d",
            self::BATCH_SIZE
        );

        $deleted = $conn->executeStatement($sql, [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
        ]);

        return $deleted;
    }
}
