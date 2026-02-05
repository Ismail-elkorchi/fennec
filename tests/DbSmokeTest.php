<?php

declare(strict_types=1);

use Fennec\AdminCreator;
use Fennec\Config;
use Fennec\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('db')]
final class DbSmokeTest extends TestCase
{
    public function testMigrationsAndAdminBootstrap(): void
    {
        if (getenv('FENNEC_DB_DSN') === false) {
            $this->fail('Database environment not configured.');
        }

        $config = Config::fromEnv();
        $db = Database::connect($config);

        $count = $db->fetchColumn(
            'SELECT COUNT(*) FROM schema_migrations WHERE version IN (:v1, :v2)',
            [':v1' => '001_init.sql', ':v2' => '002_agents_jobs.sql']
        );

        $this->assertSame('2', (string) $count);

        $email = 'admin+' . bin2hex(random_bytes(4)) . '@example.test';
        $creator = new AdminCreator($db, $config);
        $userId = $creator->createAdmin($email, 'correct-horse-battery-staple');

        $row = $db->fetchOne('SELECT email, role FROM users WHERE id = :id', [':id' => $userId]);
        $this->assertNotNull($row);
        $this->assertSame($email, $row['email']);
        $this->assertSame('admin', $row['role']);
    }
}
