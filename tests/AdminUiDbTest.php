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
    protected function setUp(): void
    {
        $this->stopSession();
        $_SESSION = [];
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    protected function tearDown(): void
    {
        $this->stopSession();
        $_SESSION = [];
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
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

    public function testAuthenticatedAdminCanViewAgentsPages(): void
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
        $agentId = (int) $agent['agent_id'];

        $_SESSION = [
            'admin_id' => $adminId,
            'csrf_token' => 'csrf-test-token',
        ];

        $index = fennec_route('GET', '/admin/agents');
        $this->assertSame(200, $index['status']);
        $this->assertStringContainsString('Agents List', $index['body']);
        $this->assertStringContainsString('agent-row-' . $agentId, $index['body']);
        $this->assertStringContainsString((string) $agentId, $index['body']);
        $this->assertNoSecretsInHtml($index['body']);

        $detail = fennec_route('GET', '/admin/agents/' . $agentId);
        $this->assertSame(200, $detail['status']);
        $this->assertStringContainsString('Agent Details', $detail['body']);
        $this->assertStringContainsString('data-agent-id="' . $agentId . '"', $detail['body']);
        $this->assertNoSecretsInHtml($detail['body']);
    }

    public function testAuthenticatedAdminCanViewJobsPages(): void
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
        $job = $jobs->enqueue('noop', ['from' => 'admin-ui-test']);
        $jobs->claimNext($agent['agent_id'], $agents, $config->jobLeaseSeconds());
        $jobId = (int) $job['id'];

        $_SESSION = [
            'admin_id' => $adminId,
            'csrf_token' => 'csrf-test-token',
        ];

        $index = fennec_route('GET', '/admin/jobs');
        $this->assertSame(200, $index['status']);
        $this->assertStringContainsString('Jobs List', $index['body']);
        $this->assertStringContainsString('job-row-' . $jobId, $index['body']);
        $this->assertNoSecretsInHtml($index['body']);
        $this->assertStringNotContainsString('payload', strtolower($index['body']));
        $this->assertStringNotContainsString('result', strtolower($index['body']));

        $detail = fennec_route('GET', '/admin/jobs/' . $jobId);
        $this->assertSame(200, $detail['status']);
        $this->assertStringContainsString('Job Details', $detail['body']);
        $this->assertStringContainsString('data-job-id="' . $jobId . '"', $detail['body']);
        $this->assertNoSecretsInHtml($detail['body']);
        $this->assertStringNotContainsString('payload', strtolower($detail['body']));
        $this->assertStringNotContainsString('result', strtolower($detail['body']));
    }

    public function testLoginRegeneratesSessionIdAndRedirectsToAdmin(): void
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

        $email = 'admin+' . bin2hex(random_bytes(4)) . '@example.test';
        $password = 'correct-horse-battery-staple';
        $creator = new AdminCreator($db, $config);
        $creator->createAdmin($email, $password);

        session_name('fennec_session');
        session_set_cookie_params(fennec_session_cookie_options([]));
        session_start();
        $_SESSION = [
            'csrf_token' => 'login-csrf-token',
        ];
        $before = session_id();

        $response = fennec_route('POST', '/login', [
            'body' => http_build_query([
                'csrf_token' => 'login-csrf-token',
                'email' => $email,
                'password' => $password,
            ]),
        ]);

        $after = session_id();

        $this->assertSame(302, $response['status']);
        $this->assertSame('/admin', $response['headers']['Location']);
        $this->assertNotSame($before, $after);
        $this->assertIsInt($_SESSION['admin_id'] ?? null);
        $this->assertNotSame('login-csrf-token', $_SESSION['csrf_token'] ?? null);
    }

    private function stopSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_unset();
        session_destroy();
    }

    private function assertNoSecretsInHtml(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/\\b\\d+\\.[A-Za-z0-9_-]{10,}\\b/', $html);
        $this->assertStringNotContainsString('token_hash', $html);
        $this->assertStringNotContainsString('password_hash', $html);
        $this->assertStringNotContainsString('FENNEC_DB_PASSWORD', $html);
        $this->assertStringNotContainsString('Bearer ', $html);
    }
}
