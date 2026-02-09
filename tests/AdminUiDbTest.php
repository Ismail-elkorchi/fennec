<?php

declare(strict_types=1);

use Fennec\AdminCreator;
use Fennec\AgentRepository;
use Fennec\Config;
use Fennec\Database;
use Fennec\JobRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../public/index.php';

#[Group('db')]
final class AdminUiDbTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testAuthenticatedAdminRendersDashboard(): void
    {
        if (getenv('FENNEC_DB_DSN') === false) {
            $this->fail('Database environment not configured.');
        }

        $config = Config::fromEnv();
        $db = Database::connect($config);

        $db->execute('DELETE FROM jobs');
        $db->execute('DELETE FROM agents');
        $db->execute('DELETE FROM audit_events');
        $db->execute('DELETE FROM users');

        $creator = new AdminCreator($db, $config);
        $adminId = $creator->createAdmin('admin+' . bin2hex(random_bytes(4)) . '@example.test', 'correct-horse-battery-staple');

        $agents = new AgentRepository($db, $config);
        $agent = $agents->create('admin-ui-agent-' . bin2hex(random_bytes(3)));

        $jobs = new JobRepository($db);
        $jobs->enqueue('noop', ['from' => 'admin-ui-test']);
        $jobs->claimNext($agent['agent_id'], $agents, $config->jobLeaseSeconds());

        $_SESSION = [
            'admin_id' => $adminId,
            'csrf_token' => 'csrf-test-token',
        ];

        $response = fennec_route('GET', '/admin');

        $this->assertSame(200, $response['status']);
        $this->assertSame('text/html; charset=utf-8', $response['headers']['Content-Type']);
        $this->assertStringContainsString('Fennec Admin', $response['body']);
        $this->assertStringContainsString('Recent Jobs', $response['body']);
        $this->assertStringContainsString('Agents', $response['body']);
    }
}
