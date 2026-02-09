<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../public/index.php';

final class AdminUiRouteTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testUnauthenticatedAdminRedirectsToLogin(): void
    {
        $response = fennec_route('GET', '/admin');

        $this->assertSame(302, $response['status']);
        $this->assertSame('/login', $response['headers']['Location']);
    }
}
