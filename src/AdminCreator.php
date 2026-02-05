<?php

declare(strict_types=1);

namespace Fennec;

use InvalidArgumentException;
use RuntimeException;

final class AdminCreator
{
    private Database $db;
    private Config $config;

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function createAdmin(string $email, string $password): int
    {
        $email = trim($email);
        if ($email === '') {
            throw new InvalidArgumentException('Email is required.');
        }

        if ($password === '') {
            throw new InvalidArgumentException('Password is required.');
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID, $this->config->passwordOptions());
        if ($hash === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        $row = $this->db->fetchOne(
            'INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role) RETURNING id',
            [
                ':email' => $email,
                ':password_hash' => $hash,
                ':role' => 'admin',
            ]
        );

        if ($row === null || !isset($row['id'])) {
            throw new RuntimeException('Failed to create admin user.');
        }

        $userId = (int) $row['id'];

        $payload = json_encode([
            'email' => $email,
            'bootstrap' => true,
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $payload = '{}';
        }

        $this->db->execute(
            'INSERT INTO audit_events (actor_user_id, action, payload) VALUES (:actor_user_id, :action, :payload)',
            [
                ':actor_user_id' => $userId,
                ':action' => 'user.create_admin',
                ':payload' => $payload,
            ]
        );

        return $userId;
    }
}
