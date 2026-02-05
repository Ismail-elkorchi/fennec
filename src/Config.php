<?php

declare(strict_types=1);

namespace Fennec;

final class Config
{
    private string $dbDsn;
    private string $dbUser;
    private string $dbPassword;
    private int $passwordMemoryCost;
    private int $passwordTimeCost;
    private int $passwordThreads;

    private function __construct(
        string $dbDsn,
        string $dbUser,
        string $dbPassword,
        int $passwordMemoryCost,
        int $passwordTimeCost,
        int $passwordThreads
    ) {
        $this->dbDsn = $dbDsn;
        $this->dbUser = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->passwordMemoryCost = $passwordMemoryCost;
        $this->passwordTimeCost = $passwordTimeCost;
        $this->passwordThreads = $passwordThreads;
    }

    public static function fromEnv(): self
    {
        $dsn = self::env('FENNEC_DB_DSN', 'pgsql:host=db;port=5432;dbname=fennec');
        $user = self::env('FENNEC_DB_USER', 'fennec');
        $password = self::env('FENNEC_DB_PASSWORD', 'fennec-dev');

        $memoryDefault = defined('PASSWORD_ARGON2_DEFAULT_MEMORY_COST')
            ? PASSWORD_ARGON2_DEFAULT_MEMORY_COST
            : 65536;
        $timeDefault = defined('PASSWORD_ARGON2_DEFAULT_TIME_COST')
            ? PASSWORD_ARGON2_DEFAULT_TIME_COST
            : 4;
        $threadsDefault = defined('PASSWORD_ARGON2_DEFAULT_THREADS')
            ? PASSWORD_ARGON2_DEFAULT_THREADS
            : 1;

        $memory = self::envInt('FENNEC_PASSWORD_MEMORY_COST', $memoryDefault);
        $time = self::envInt('FENNEC_PASSWORD_TIME_COST', $timeDefault);
        $threads = self::envInt('FENNEC_PASSWORD_THREADS', $threadsDefault);

        return new self($dsn, $user, $password, $memory, $time, $threads);
    }

    public function dbDsn(): string
    {
        return $this->dbDsn;
    }

    public function dbUser(): string
    {
        return $this->dbUser;
    }

    public function dbPassword(): string
    {
        return $this->dbPassword;
    }

    public function passwordOptions(): array
    {
        return [
            'memory_cost' => $this->passwordMemoryCost,
            'time_cost' => $this->passwordTimeCost,
            'threads' => $this->passwordThreads,
        ];
    }

    public function hasDbConfig(): bool
    {
        return $this->dbDsn !== '' && $this->dbUser !== '';
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        $value = trim($value);
        return $value === '' ? '' : $value;
    }

    private static function envInt(string $key, int $default): int
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return $default;
        }

        return (int) $value;
    }
}
