<?php

declare(strict_types=1);

namespace Fennec;

use InvalidArgumentException;
use RuntimeException;

final class AgentRepository
{
    private Database $db;
    private Config $config;

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * @return array{agent_id:int,name:string,token:string}
     */
    public function create(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Agent name is required.');
        }

        $secret = $this->generateSecret();
        $hash = password_hash($secret, PASSWORD_ARGON2ID, $this->config->passwordOptions());
        if ($hash === false) {
            throw new RuntimeException('Failed to hash agent secret.');
        }

        $row = $this->db->fetchOne(
            'INSERT INTO agents (name, token_hash) VALUES (:name, :token_hash) RETURNING id',
            [
                ':name' => $name,
                ':token_hash' => $hash,
            ]
        );

        if ($row === null || !isset($row['id'])) {
            throw new RuntimeException('Failed to create agent.');
        }

        $agentId = (int) $row['id'];
        $token = $agentId . '.' . $secret;

        $payload = json_encode([
            'agent_id' => $agentId,
            'name' => $name,
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $payload = '{}';
        }

        $this->db->execute(
            'INSERT INTO audit_events (actor_user_id, action, payload) VALUES (:actor_user_id, :action, :payload)',
            [
                ':actor_user_id' => null,
                ':action' => 'agent.create',
                ':payload' => $payload,
            ]
        );

        return [
            'agent_id' => $agentId,
            'name' => $name,
            'token' => $token,
        ];
    }

    public function authenticate(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$idPart, $secret] = $parts;
        if ($idPart === '' || $secret === '' || !ctype_digit($idPart)) {
            return null;
        }

        $agentId = (int) $idPart;
        $row = $this->db->fetchOne(
            'SELECT id, name, token_hash, disabled FROM agents WHERE id = :id',
            [':id' => $agentId]
        );

        if ($row === null) {
            return null;
        }

        if (!empty($row['disabled'])) {
            return null;
        }

        if (!password_verify($secret, (string) $row['token_hash'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ];
    }

    public function touch(int $agentId): void
    {
        $this->db->execute(
            'UPDATE agents SET last_seen_at = now() WHERE id = :id',
            [':id' => $agentId]
        );
    }

    private function generateSecret(): string
    {
        $bytes = random_bytes(32);
        $encoded = base64_encode($bytes);
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }
}
