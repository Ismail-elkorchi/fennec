<?php

declare(strict_types=1);

use Fennec\AgentRepository;
use Fennec\Config;
use Fennec\Database;
use Fennec\JobRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../public/index.php';

#[Group('db')]
final class JobAgentFlowDbTest extends TestCase
{
    public function testClaimAndCompleteJob(): void
    {
        if (getenv('FENNEC_DB_DSN') === false) {
            $this->fail('Database environment not configured.');
        }

        $config = Config::fromEnv();
        $db = Database::connect($config);

        $db->execute('DELETE FROM jobs');
        $db->execute('DELETE FROM agents');
        $db->execute('DELETE FROM audit_events');

        $agents = new AgentRepository($db, $config);
        $agent = $agents->create('test-agent-' . bin2hex(random_bytes(3)));

        $jobs = new JobRepository($db);
        $job = $jobs->enqueue('noop', ['ping' => 'pong']);

        $claimResponse = fennec_route('POST', '/agent/v1/jobs/claim', [
            'headers' => [
                'Authorization' => 'Bearer ' . $agent['token'],
            ],
        ]);

        $this->assertSame(200, $claimResponse['status']);
        $claimPayload = json_decode($claimResponse['body'], true);
        $this->assertIsArray($claimPayload);
        $this->assertSame($job['id'], $claimPayload['job']['id']);
        $this->assertSame('running', $claimPayload['job']['status']);

        $completeBody = json_encode([
            'status' => 'succeeded',
            'result' => ['ok' => true],
        ], JSON_UNESCAPED_SLASHES);

        $completeResponse = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agent['token'],
                ],
                'body' => $completeBody,
            ]
        );

        $this->assertSame(200, $completeResponse['status']);
        $completePayload = json_decode($completeResponse['body'], true);
        $this->assertIsArray($completePayload);
        $this->assertSame('succeeded', $completePayload['job']['status']);

        $audit = $db->fetchOne(
            'SELECT action FROM audit_events WHERE action = :action ORDER BY id DESC LIMIT 1',
            [':action' => 'agent.create']
        );
        $this->assertNotNull($audit);
        $this->assertSame('agent.create', $audit['action']);
    }
}
