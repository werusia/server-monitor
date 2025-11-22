<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122163254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create server_metrics table for storing system metrics collected from monitored Linux server via SSH';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('server_metrics');
        
        // Primary key
        $table->addColumn('id', 'integer', [
            'unsigned' => true,
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $table->setPrimaryKey(['id']);
        
        // Timestamp
        $table->addColumn('timestamp', 'datetime', [
            'notnull' => true,
        ]);
        
        // CPU usage (0.00-100.00)
        $table->addColumn('cpu_usage', 'decimal', [
            'precision' => 5,
            'scale' => 2,
            'unsigned' => true,
            'notnull' => true,
            'default' => 0,
        ]);
        
        // RAM usage in GB
        $table->addColumn('ram_usage', 'decimal', [
            'precision' => 10,
            'scale' => 2,
            'unsigned' => true,
            'notnull' => true,
            'default' => 0,
        ]);
        
        // Disk usage in GB
        $table->addColumn('disk_usage', 'decimal', [
            'precision' => 10,
            'scale' => 2,
            'unsigned' => true,
            'notnull' => true,
            'default' => 0,
        ]);
        
        // I/O metrics (cumulative bytes)
        $table->addColumn('io_read_bytes', 'bigint', [
            'unsigned' => true,
            'notnull' => true,
            'default' => 0,
        ]);
        
        $table->addColumn('io_write_bytes', 'bigint', [
            'unsigned' => true,
            'notnull' => true,
            'default' => 0,
        ]);
        
        // Network metrics (cumulative bytes)
        $table->addColumn('network_sent_bytes', 'bigint', [
            'unsigned' => true,
            'notnull' => true,
            'default' => 0,
        ]);
        
        $table->addColumn('network_received_bytes', 'bigint', [
            'unsigned' => true,
            'notnull' => true,
            'default' => 0,
        ]);
        
        // Index on timestamp for range queries
        $table->addIndex(['timestamp'], 'idx_timestamp');
        
        // Table options
        $table->addOption('engine', 'InnoDB');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collation', 'utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('server_metrics');
    }
}
