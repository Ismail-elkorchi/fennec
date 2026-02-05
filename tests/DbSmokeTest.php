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
            $this->markTestSkipped('Database environment not configured.');
        }

        $config = Config::fromEnv();
        $db = Database::connect($config);

        $version = $db->fetchColumn(
            'SELECT version FROM schema_migrations WHERE version = :version',
            [':version' => '001_init.sql']
        );

        $this->assertSame('001_init.sql', $version);

        $email = 'admin+' . bin2hex(random_bytes(4)) . '@example.test';
        $creator = new AdminCreator($db, $config);
        $userId = $creator->createAdmin($email, 'correct-horse-battery-staple');

        $row = $db->fetchOne('SELECT email, role FROM users WHERE id = :id', [':id' => $userId]);
        $this->assertNotNull($row);
        $this->assertSame($email, $row['email']);
        $this->assertSame('admin', $row['role']);
    }
}
