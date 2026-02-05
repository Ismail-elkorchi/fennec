<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../public/index.php';

final class BootstrapTest extends TestCase
{
    public function testHealthzRoute(): void
    {
        $response = fennec_route('GET', '/healthz');

        $this->assertSame(200, $response['status']);
        $this->assertSame('application/json; charset=utf-8', $response['headers']['Content-Type']);

        $payload = json_decode($response['body'], true);
        $this->assertSame(['status' => 'ok'], $payload);
    }

    public function testVersionRoute(): void
    {
        $response = fennec_route('GET', '/version');

        $this->assertSame(200, $response['status']);
        $this->assertSame('application/json; charset=utf-8', $response['headers']['Content-Type']);

        $payload = json_decode($response['body'], true);
        $this->assertSame('fennec', $payload['name']);
        $this->assertArrayHasKey('version', $payload);
        $this->assertArrayHasKey('commit', $payload);
        $this->assertArrayHasKey('build_time', $payload);
    }

    public function testOpenApiYamlRoute(): void
    {
        $response = fennec_route('GET', '/openapi.yaml');

        $this->assertSame(200, $response['status']);
        $this->assertSame('application/yaml; charset=utf-8', $response['headers']['Content-Type']);
        $this->assertStringContainsString('openapi: 3.1.0', $response['body']);
    }

    public function testDefaultRoute(): void
    {
        $response = fennec_route('GET', '/');

        $this->assertSame(200, $response['status']);
        $this->assertSame('text/plain; charset=utf-8', $response['headers']['Content-Type']);
        $this->assertSame('Fennec bootstrap', $response['body']);
    }
}
