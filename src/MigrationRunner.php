<?php

declare(strict_types=1);

namespace Fennec;

use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private Database $db;
    private string $path;

    public function __construct(Database $db, string $path)
    {
        $this->db = $db;
        $this->path = rtrim($path, '/');
    }

    /**
     * @return list<string>
     */
    public function migrate(): array
    {
        $files = glob($this->path . '/*.sql');
        if ($files === false) {
            throw new RuntimeException('Failed to read migrations directory.');
        }

        sort($files);
        $applied = $this->appliedVersions();
        $appliedSet = array_fill_keys($applied, true);
        $ran = [];

        foreach ($files as $file) {
            $version = basename($file);
            if (isset($appliedSet[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Failed to read migration: ' . $file);
            }

            $this->applyMigration($version, $sql);
            $ran[] = $version;
        }

        return $ran;
    }

    /**
     * @return list<string>
     */
    private function appliedVersions(): array
    {
        try {
            $rows = $this->db->fetchOne(
                'SELECT array_agg(version ORDER BY version) AS versions FROM schema_migrations'
            );
        } catch (Throwable $exception) {
            return [];
        }

        if ($rows === null || $rows['versions'] === null) {
            return [];
        }

        $versions = trim((string) $rows['versions'], '{}');
        if ($versions === '') {
            return [];
        }

        return array_map('trim', str_getcsv($versions, ',', '"', '\\'));
    }

    private function applyMigration(string $version, string $sql): void
    {
        $statements = $this->splitStatements($sql);

        $this->db->begin();
        try {
            foreach ($statements as $statement) {
                $this->db->execute($statement);
            }

            $this->db->execute(
                'INSERT INTO schema_migrations (version) VALUES (:version)',
                [':version' => $version]
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $parts = array_map('trim', explode(';', $sql));
        $statements = [];

        foreach ($parts as $part) {
            if ($part !== '') {
                $statements[] = $part;
            }
        }

        return $statements;
    }
}
