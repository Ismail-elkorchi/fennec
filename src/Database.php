<?php

declare(strict_types=1);

namespace Fennec;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function connect(Config $config): self
    {
        $pdo = new PDO(
            $config->dbDsn(),
            $config->dbUser(),
            $config->dbPassword(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return new self($pdo);
    }

    public function ping(): bool
    {
        try {
            $statement = $this->pdo->prepare('SELECT 1');
            $statement->execute();
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function execute(string $sql, array $params = []): void
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
