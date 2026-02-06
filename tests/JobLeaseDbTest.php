<?php

declare(strict_types=1);

use Fennec\AgentRepository;
use Fennec\Config;
use Fennec\Database;
use Fennec\JobRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('db')]
final class JobLeaseDbTest extends TestCase
{
    public function testExpiredLeaseIsRequeued(): void
    {
        if (getenv('FENNEC_DB_DSN') === false) {
            $this->fail('Database environment not configured.');
        }

        $config = Config::fromEnv();
        $db = Database::connect($config);

        $db->execute('DELETE FROM jobs');
        $db->execute('DELETE FROM agents');

        $agents = new AgentRepository($db, $config);
        $agent = $agents->create('lease-agent-' . bin2hex(random_bytes(3)));

        $jobs = new JobRepository($db);
        $job = $jobs->enqueue('noop', ['lease' => true]);

        $claimed = $jobs->claimNext($agent['agent_id'], $agents, $config->jobLeaseSeconds());
        $this->assertNotNull($claimed);
        $this->assertSame('running', $claimed['status']);

        $db->execute(
            "UPDATE jobs SET lease_expires_at = now() - INTERVAL '5 seconds' WHERE id = :id",
            [':id' => $job['id']]
        );

        $requeuedIds = $jobs->requeueExpired();
        $this->assertSame([$job['id']], $requeuedIds);

        $row = $db->fetchOne('SELECT status FROM jobs WHERE id = :id', [':id' => $job['id']]);
        $this->assertNotNull($row);
        $this->assertSame('queued', $row['status']);

        $db->execute(
            "UPDATE jobs SET scheduled_at = now() WHERE id = :id",
            [':id' => $job['id']]
        );

        $claimedAgain = $jobs->claimNext($agent['agent_id'], $agents, $config->jobLeaseSeconds());
        $this->assertNotNull($claimedAgain);
        $this->assertSame($job['id'], $claimedAgain['id']);
    }
}
