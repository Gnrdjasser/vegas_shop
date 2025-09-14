<?php

namespace VegasShop\Utils;

/**
 * Migration Class
 * Database migration system for version control
 */
class Migration
{
    private $db;
    private $migrationsTable = 'migrations';

    public function __construct()
    {
        $this->db = \DatabaseConnection::getInstance();
        $this->createMigrationsTable();
    }

    /**
     * Create migrations table if it doesn't exist
     */
    private function createMigrationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->db->executeQuery($sql);
    }

    /**
     * Run all pending migrations
     */
    public function run()
    {
        $migrations = $this->getPendingMigrations();
        
        if (empty($migrations)) {
            echo "No pending migrations.\n";
            return;
        }

        $batch = $this->getNextBatchNumber();
        
        foreach ($migrations as $migration) {
            $this->runMigration($migration, $batch);
        }
        
        echo "Ran " . count($migrations) . " migrations.\n";
    }

    /**
     * Rollback last batch of migrations
     */
    public function rollback()
    {
        $lastBatch = $this->getLastBatchNumber();
        
        if (!$lastBatch) {
            echo "No migrations to rollback.\n";
            return;
        }

        $migrations = $this->getMigrationsByBatch($lastBatch);
        
        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
        }
        
        echo "Rolled back " . count($migrations) . " migrations.\n";
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations()
    {
        $migrationFiles = glob(__DIR__ . '/../../migrations/*.php');
        $executedMigrations = $this->getExecutedMigrations();
        
        $pending = [];
        
        foreach ($migrationFiles as $file) {
            $migration = basename($file, '.php');
            
            if (!in_array($migration, $executedMigrations)) {
                $pending[] = $migration;
            }
        }
        
        sort($pending);
        return $pending;
    }

    /**
     * Get executed migrations
     */
    private function getExecutedMigrations()
    {
        $sql = "SELECT migration FROM {$this->migrationsTable} ORDER BY id";
        $stmt = $this->db->executeQuery($sql);
        $results = $stmt->fetchAll();
        
        return array_column($results, 'migration');
    }

    /**
     * Get next batch number
     */
    private function getNextBatchNumber()
    {
        $sql = "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}";
        $stmt = $this->db->executeQuery($sql);
        $result = $stmt->fetch();
        
        return ($result['max_batch'] ?? 0) + 1;
    }

    /**
     * Get last batch number
     */
    private function getLastBatchNumber()
    {
        $sql = "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}";
        $stmt = $this->db->executeQuery($sql);
        $result = $stmt->fetch();
        
        return $result['max_batch'] ?? null;
    }

    /**
     * Get migrations by batch
     */
    private function getMigrationsByBatch($batch)
    {
        $sql = "SELECT migration FROM {$this->migrationsTable} WHERE batch = :batch ORDER BY id";
        $stmt = $this->db->executeQuery($sql, ['batch' => $batch]);
        $results = $stmt->fetchAll();
        
        return array_column($results, 'migration');
    }

    /**
     * Run single migration
     */
    private function runMigration($migration, $batch)
    {
        echo "Running migration: {$migration}\n";
        
        $file = __DIR__ . '/../../migrations/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: {$file}");
        }
        
        require_once $file;
        
        $class = $this->getMigrationClass($migration);
        $instance = new $class();
        
        try {
            $this->db->beginTransaction();
            
            if (method_exists($instance, 'up')) {
                $instance->up();
            }
            
            $this->recordMigration($migration, $batch);
            $this->db->commit();
            
            echo "✓ {$migration}\n";
            
        } catch (\Exception $e) {
            $this->db->rollback();
            echo "✗ {$migration}: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * Rollback single migration
     */
    private function rollbackMigration($migration)
    {
        echo "Rolling back migration: {$migration}\n";
        
        $file = __DIR__ . '/../../migrations/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: {$file}");
        }
        
        require_once $file;
        
        $class = $this->getMigrationClass($migration);
        $instance = new $class();
        
        try {
            $this->db->beginTransaction();
            
            if (method_exists($instance, 'down')) {
                $instance->down();
            }
            
            $this->removeMigration($migration);
            $this->db->commit();
            
            echo "✓ {$migration}\n";
            
        } catch (\Exception $e) {
            $this->db->rollback();
            echo "✗ {$migration}: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * Get migration class name
     */
    private function getMigrationClass($migration)
    {
        // Remove timestamp prefix (e.g., "2024_01_01_000000_")
        $parts = explode('_', $migration);
        $className = '';
        
        // Skip the first 4 parts (year, month, day, time)
        for ($i = 4; $i < count($parts); $i++) {
            $className .= ucfirst($parts[$i]);
        }
        
        return $className;
    }

    /**
     * Record migration as executed
     */
    private function recordMigration($migration, $batch)
    {
        $sql = "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (:migration, :batch)";
        $this->db->executeQuery($sql, [
            'migration' => $migration,
            'batch' => $batch
        ]);
    }

    /**
     * Remove migration record
     */
    private function removeMigration($migration)
    {
        $sql = "DELETE FROM {$this->migrationsTable} WHERE migration = :migration";
        $this->db->executeQuery($sql, ['migration' => $migration]);
    }

    /**
     * Get migration status
     */
    public function status()
    {
        $migrationFiles = glob(__DIR__ . '/../../migrations/*.php');
        $executedMigrations = $this->getExecutedMigrations();
        
        echo "Migration Status:\n";
        echo "================\n";
        
        foreach ($migrationFiles as $file) {
            $migration = basename($file, '.php');
            $status = in_array($migration, $executedMigrations) ? '✓' : '✗';
            echo "{$status} {$migration}\n";
        }
    }
}
