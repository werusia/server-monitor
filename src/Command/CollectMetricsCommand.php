<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ServerMetric;
use App\Service\SshMetricsCollector;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to collect server metrics via SSH and store them in the database.
 * Designed to be run by cron every minute.
 */
#[AsCommand(
    name: 'app:collect-metrics',
    description: 'Collect server metrics via SSH and store them in the database'
)]
class CollectMetricsCommand extends Command
{
    public function __construct(
        private readonly SshMetricsCollector $sshMetricsCollector,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get SSH configuration from environment
        $host = $_ENV['SSH_HOST'] ?? null;
        $port = (int) ($_ENV['SSH_PORT'] ?? 22);
        $username = $_ENV['SSH_USERNAME'] ?? null;
        $privateKeyBase64 = $_ENV['SSH_PRIVATE_KEY'] ?? null;

        // Validate required parameters
        if (empty($host) || empty($username) || empty($privateKeyBase64)) {
            $io->error('Missing required SSH configuration. Please set SSH_HOST, SSH_USERNAME, and SSH_PRIVATE_KEY in .env');
            $this->logger->error('CollectMetricsCommand: Missing required SSH configuration');
            return Command::FAILURE;
        }

        try {
            // Collect metrics via SSH
            $io->info('Connecting to server and collecting metrics...');
            $metrics = $this->sshMetricsCollector->collectMetrics($host, $port, $username, $privateKeyBase64);

            // Create and persist metric entity
            $serverMetric = new ServerMetric();
            $serverMetric->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
            $serverMetric->setCpuUsage(number_format($metrics['cpu_usage'], 2, '.', ''));
            $serverMetric->setRamUsage(number_format($metrics['ram_usage'], 2, '.', ''));
            $serverMetric->setDiskUsage(number_format($metrics['disk_usage'], 2, '.', ''));
            $serverMetric->setIoReadBytes($metrics['io_read_bytes']);
            $serverMetric->setIoWriteBytes($metrics['io_write_bytes']);
            $serverMetric->setNetworkSentBytes($metrics['network_sent_bytes']);
            $serverMetric->setNetworkReceivedBytes($metrics['network_received_bytes']);

            $this->entityManager->persist($serverMetric);
            $this->entityManager->flush();

            $io->success(sprintf(
                'Metrics collected successfully: CPU=%.2f%%, RAM=%.2fGB, Disk=%.2fGB',
                $metrics['cpu_usage'],
                $metrics['ram_usage'],
                $metrics['disk_usage']
            ));

            $this->logger->info('Metrics collected successfully', [
                'cpu_usage' => $metrics['cpu_usage'],
                'ram_usage' => $metrics['ram_usage'],
                'disk_usage' => $metrics['disk_usage'],
                'timestamp' => $serverMetric->getTimestamp()->format('c'),
            ]);

            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $io->error('Failed to collect metrics: ' . $e->getMessage());
            $this->logger->error('CollectMetricsCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Unexpected error: ' . $e->getMessage());
            $this->logger->error('CollectMetricsCommand unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}

