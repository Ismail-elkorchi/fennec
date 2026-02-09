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
    public function testCompleteIsIdempotentForSameTerminalPayload(): void
    {
        [$config, $db] = $this->db();
        $this->resetDb($db);
        $agent = $this->createAgent($db, $config, 'idempotent-agent');
        $job = (new JobRepository($db))->enqueue('noop', ['ping' => 'pong']);
        $this->claimJob($job['id'], $agent['token']);

        $completeBody = json_encode([
            'status' => 'succeeded',
            'result' => ['ok' => true],
        ], JSON_UNESCAPED_SLASHES);
        $this->assertIsString($completeBody);

        $firstComplete = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agent['token'],
                ],
                'body' => $completeBody,
            ]
        );
        $this->assertSame(200, $firstComplete['status']);
        $firstPayload = json_decode($firstComplete['body'], true);
        $this->assertIsArray($firstPayload);

        $retryComplete = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agent['token'],
                ],
                'body' => $completeBody,
            ]
        );
        $this->assertSame(200, $retryComplete['status']);
        $retryPayload = json_decode($retryComplete['body'], true);
        $this->assertIsArray($retryPayload);

        $this->assertSame('succeeded', $retryPayload['job']['status']);
        $this->assertSame($firstPayload['job']['attempt'], $retryPayload['job']['attempt']);
        $this->assertSame($firstPayload['job']['finished_at'], $retryPayload['job']['finished_at']);
        $this->assertSame($agent['agent_id'], $retryPayload['job']['locked_by_agent_id']);

        $stored = (new JobRepository($db))->findById($job['id']);
        $this->assertNotNull($stored);
        $this->assertSame('succeeded', $stored['status']);
        $this->assertSame($retryPayload['job']['attempt'], $stored['attempt']);
        $this->assertSame($retryPayload['job']['result'], $stored['result']);
    }

    public function testConflictingTerminalCompletionReturnsProblem(): void
    {
        [$config, $db] = $this->db();
        $this->resetDb($db);
        $agent = $this->createAgent($db, $config, 'conflict-agent');
        $job = (new JobRepository($db))->enqueue('noop', ['ping' => 'pong']);
        $this->claimJob($job['id'], $agent['token']);

        $firstBody = json_encode([
            'status' => 'succeeded',
            'result' => ['ok' => true],
        ], JSON_UNESCAPED_SLASHES);
        $this->assertIsString($firstBody);

        $firstComplete = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agent['token'],
                ],
                'body' => $firstBody,
            ]
        );
        $this->assertSame(200, $firstComplete['status']);

        $conflictingBody = json_encode([
            'status' => 'failed',
            'result' => ['ok' => false],
            'error' => 'failed on replay',
        ], JSON_UNESCAPED_SLASHES);
        $this->assertIsString($conflictingBody);

        $conflict = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agent['token'],
                ],
                'body' => $conflictingBody,
            ]
        );
        $this->assertSame(409, $conflict['status']);
        $this->assertSame('application/problem+json; charset=utf-8', $conflict['headers']['Content-Type']);

        $problem = json_decode($conflict['body'], true);
        $this->assertIsArray($problem);
        $this->assertSame(409, $problem['status']);
        $this->assertSame(FENNEC_PROBLEM_JOB_CONFLICT, $problem['type']);

        $stored = (new JobRepository($db))->findById($job['id']);
        $this->assertNotNull($stored);
        $this->assertSame('succeeded', $stored['status']);
        $this->assertNull($stored['last_error']);
    }

    public function testStaleAgentCompletionIsRejected(): void
    {
        [$config, $db] = $this->db();
        $this->resetDb($db);

        $agents = new AgentRepository($db, $config);
        $agentA = $agents->create('stale-a-' . bin2hex(random_bytes(3)));
        $agentB = $agents->create('stale-b-' . bin2hex(random_bytes(3)));

        $jobs = new JobRepository($db);
        $job = $jobs->enqueue('noop', ['ping' => 'pong']);
        $this->claimJob($job['id'], $agentA['token']);

        $db->execute(
            "UPDATE jobs SET lease_expires_at = now() - INTERVAL '5 seconds' WHERE id = :id",
            [':id' => $job['id']]
        );
        $requeued = $jobs->requeueExpired();
        $this->assertSame([$job['id']], $requeued);

        $db->execute(
            "UPDATE jobs SET scheduled_at = now() WHERE id = :id",
            [':id' => $job['id']]
        );
        $this->claimJob($job['id'], $agentB['token']);

        $completeByBBody = json_encode([
            'status' => 'succeeded',
            'result' => ['owner' => 'agent-b'],
        ], JSON_UNESCAPED_SLASHES);
        $this->assertIsString($completeByBBody);

        $completeByB = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agentB['token'],
                ],
                'body' => $completeByBBody,
            ]
        );
        $this->assertSame(200, $completeByB['status']);

        $staleComplete = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agentA['token'],
                ],
                'body' => $completeByBBody,
            ]
        );
        $this->assertSame(409, $staleComplete['status']);
        $this->assertSame('application/problem+json; charset=utf-8', $staleComplete['headers']['Content-Type']);

        $problem = json_decode($staleComplete['body'], true);
        $this->assertIsArray($problem);
        $this->assertSame(409, $problem['status']);
        $this->assertSame(FENNEC_PROBLEM_JOB_CONFLICT, $problem['type']);
    }

    public function testHeartbeatAfterCompletionIsRejected(): void
    {
        [$config, $db] = $this->db();
        $this->resetDb($db);

        $agent = $this->createAgent($db, $config, 'heartbeat-agent');
        $job = (new JobRepository($db))->enqueue('noop', ['ping' => 'pong']);
        $this->claimJob($job['id'], $agent['token']);

        $completeBody = json_encode([
            'status' => 'succeeded',
            'result' => ['ok' => true],
        ], JSON_UNESCAPED_SLASHES);
        $this->assertIsString($completeBody);

        $complete = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/complete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agent['token'],
                ],
                'body' => $completeBody,
            ]
        );
        $this->assertSame(200, $complete['status']);

        $heartbeat = fennec_route(
            'POST',
            '/agent/v1/jobs/' . $job['id'] . '/heartbeat',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $agent['token'],
                ],
            ]
        );
        $this->assertSame(409, $heartbeat['status']);
        $this->assertSame('application/problem+json; charset=utf-8', $heartbeat['headers']['Content-Type']);

        $problem = json_decode($heartbeat['body'], true);
        $this->assertIsArray($problem);
        $this->assertSame(409, $problem['status']);
        $this->assertSame(FENNEC_PROBLEM_JOB_CONFLICT, $problem['type']);
    }

    /**
     * @return array{Config, Database}
     */
    private function db(): array
    {
        if (getenv('FENNEC_DB_DSN') === false) {
            $this->fail('Database environment not configured.');
        }

        $config = Config::fromEnv();
        $db = Database::connect($config);

        return [$config, $db];
    }

    private function resetDb(Database $db): void
    {
        $db->execute('DELETE FROM jobs');
        $db->execute('DELETE FROM agents');
        $db->execute('DELETE FROM audit_events');
    }

    /**
     * @return array{name:string,agent_id:int,token:string}
     */
    private function createAgent(Database $db, Config $config, string $prefix): array
    {
        $agents = new AgentRepository($db, $config);

        return $agents->create($prefix . '-' . bin2hex(random_bytes(3)));
    }

    private function claimJob(int $jobId, string $token): void
    {
        $claim = fennec_route('POST', '/agent/v1/jobs/claim', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
        $this->assertSame(200, $claim['status']);

        $payload = json_decode($claim['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame($jobId, $payload['job']['id']);
        $this->assertSame('running', $payload['job']['status']);
    }
}
