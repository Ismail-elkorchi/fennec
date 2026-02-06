<?php

declare(strict_types=1);

namespace Fennec;

use RuntimeException;
use Throwable;

final class JobRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function enqueue(string $type, array $payload, ?string $scheduledAt = null): array
    {
        $type = trim($type);
        if ($type === '') {
            throw new RuntimeException('Job type is required.');
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new RuntimeException('Failed to encode job payload.');
        }

        $row = $this->db->fetchOne(
            'INSERT INTO jobs (type, payload, scheduled_at) VALUES (:type, :payload, COALESCE(:scheduled_at, now())) RETURNING *',
            [
                ':type' => $type,
                ':payload' => $payloadJson,
                ':scheduled_at' => $scheduledAt,
            ]
        );

        if ($row === null) {
            throw new RuntimeException('Failed to enqueue job.');
        }

        return $this->normalizeJob($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimNext(int $agentId, AgentRepository $agents, int $leaseSeconds): ?array
    {
        $this->db->begin();
        try {
            $agents->touch($agentId);

            $row = $this->db->fetchOne(
                "WITH next_job AS (\n" .
                "  SELECT id\n" .
                "  FROM jobs\n" .
                "  WHERE status = 'queued' AND scheduled_at <= now()\n" .
                "  ORDER BY scheduled_at, id\n" .
                "  FOR UPDATE SKIP LOCKED\n" .
                "  LIMIT 1\n" .
                ")\n" .
                "UPDATE jobs\n" .
                "SET status = 'running',\n" .
                "    locked_at = now(),\n" .
                "    started_at = COALESCE(started_at, now()),\n" .
                "    heartbeat_at = now(),\n" .
                "    lease_expires_at = now() + (:lease_seconds || ' seconds')::interval,\n" .
                "    attempt = attempt + 1,\n" .
                "    locked_by_agent_id = :agent_id\n" .
                "WHERE id IN (SELECT id FROM next_job)\n" .
                "RETURNING *",
                [
                    ':agent_id' => $agentId,
                    ':lease_seconds' => $leaseSeconds,
                ]
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        if ($row === null) {
            return null;
        }

        return $this->normalizeJob($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function heartbeat(int $jobId, int $agentId, int $leaseSeconds): ?array
    {
        $row = $this->db->fetchOne(
            "UPDATE jobs\n" .
            "SET heartbeat_at = now(),\n" .
            "    lease_expires_at = now() + (:lease_seconds || ' seconds')::interval\n" .
            "WHERE id = :id AND locked_by_agent_id = :agent_id AND status = 'running'\n" .
            "RETURNING *",
            [
                ':id' => $jobId,
                ':agent_id' => $agentId,
                ':lease_seconds' => $leaseSeconds,
            ]
        );

        if ($row === null) {
            return null;
        }

        return $this->normalizeJob($row);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    public function complete(
        int $jobId,
        int $agentId,
        string $status,
        array $result,
        ?string $error
    ): ?array {
        $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES);
        if ($resultJson === false) {
            throw new RuntimeException('Failed to encode job result.');
        }

        $row = $this->db->fetchOne(
            "UPDATE jobs\n" .
            "SET status = :status,\n" .
            "    finished_at = now(),\n" .
            "    result = :result,\n" .
            "    last_error = :error,\n" .
            "    heartbeat_at = NULL,\n" .
            "    lease_expires_at = NULL\n" .
            "WHERE id = :id AND locked_by_agent_id = :agent_id AND status = 'running'\n" .
            "RETURNING *",
            [
                ':status' => $status,
                ':result' => $resultJson,
                ':error' => $error,
                ':id' => $jobId,
                ':agent_id' => $agentId,
            ]
        );

        if ($row === null) {
            return null;
        }

        return $this->normalizeJob($row);
    }

    /**
     * @return array<int>
     */
    public function requeueExpired(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, attempt, max_attempts, last_error\n" .
            "FROM jobs\n" .
            "WHERE status = 'running' AND lease_expires_at IS NOT NULL AND lease_expires_at < now()"
        );

        if ($rows === []) {
            return [];
        }

        $requeuedIds = [];
        $this->db->begin();
        try {
            foreach ($rows as $row) {
                $jobId = (int) $row['id'];
                $attempt = (int) $row['attempt'];
                $maxAttempts = (int) $row['max_attempts'];
                $lastError = $this->appendLeaseError($row['last_error']);

                if ($attempt >= $maxAttempts) {
                    $this->db->execute(
                        "UPDATE jobs\n" .
                        "SET status = 'failed',\n" .
                        "    finished_at = now(),\n" .
                        "    last_error = :last_error,\n" .
                        "    locked_by_agent_id = NULL,\n" .
                        "    locked_at = NULL,\n" .
                        "    lease_expires_at = NULL,\n" .
                        "    heartbeat_at = NULL\n" .
                        "WHERE id = :id",
                        [
                            ':last_error' => $lastError,
                            ':id' => $jobId,
                        ]
                    );
                } else {
                    $delaySeconds = min(300, 5 * (2 ** $attempt));
                    $this->db->execute(
                        "UPDATE jobs\n" .
                        "SET status = 'queued',\n" .
                        "    scheduled_at = now() + (:delay_seconds || ' seconds')::interval,\n" .
                        "    last_error = :last_error,\n" .
                        "    locked_by_agent_id = NULL,\n" .
                        "    locked_at = NULL,\n" .
                        "    lease_expires_at = NULL,\n" .
                        "    heartbeat_at = NULL\n" .
                        "WHERE id = :id",
                        [
                            ':delay_seconds' => $delaySeconds,
                            ':last_error' => $lastError,
                            ':id' => $jobId,
                        ]
                    );

                    $requeuedIds[] = $jobId;
                }
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        sort($requeuedIds, SORT_NUMERIC);

        return $requeuedIds;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeJob(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type' => (string) $row['type'],
            'payload' => $this->decodeJsonField($row['payload'] ?? '{}'),
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'scheduled_at' => (string) $row['scheduled_at'],
            'locked_at' => $row['locked_at'] === null ? null : (string) $row['locked_at'],
            'started_at' => $row['started_at'] === null ? null : (string) $row['started_at'],
            'finished_at' => $row['finished_at'] === null ? null : (string) $row['finished_at'],
            'heartbeat_at' => $row['heartbeat_at'] === null ? null : (string) $row['heartbeat_at'],
            'lease_expires_at' => $row['lease_expires_at'] === null ? null : (string) $row['lease_expires_at'],
            'attempt' => (int) $row['attempt'],
            'max_attempts' => (int) $row['max_attempts'],
            'locked_by_agent_id' => $row['locked_by_agent_id'] === null ? null : (int) $row['locked_by_agent_id'],
            'result' => $this->decodeJsonField($row['result'] ?? '{}'),
            'last_error' => $row['last_error'] === null ? null : (string) $row['last_error'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonField(string $value): array
    {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function appendLeaseError(mixed $value): string
    {
        $existing = is_string($value) ? trim($value) : '';
        if ($existing === '') {
            return 'lease expired';
        }

        return $existing . '; lease expired';
    }
}
