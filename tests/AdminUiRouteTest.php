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

    public function testLoginPostRejectsMissingCsrfToken(): void
    {
        $response = fennec_route('POST', '/login', [
            'body' => http_build_query([
                'email' => 'admin@example.test',
                'password' => 'bad-password',
            ]),
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Invalid CSRF token.', $response['body']);
    }

    public function testHtmlResponsesIncludeSecurityHeaders(): void
    {
        $response = fennec_route('GET', '/login');

        $this->assertSame(200, $response['status']);
        $this->assertSame('text/html; charset=utf-8', $response['headers']['Content-Type']);
        $this->assertArrayHasKey('Content-Security-Policy', $response['headers']);
        $this->assertArrayHasKey('X-Content-Type-Options', $response['headers']);
        $this->assertArrayHasKey('Referrer-Policy', $response['headers']);
        $this->assertArrayHasKey('X-Frame-Options', $response['headers']);
        $this->assertStringContainsString("frame-ancestors 'none'", $response['headers']['Content-Security-Policy']);
    }

    public function testSessionCookiePolicyIncludesSecurityFlags(): void
    {
        $http = fennec_session_cookie_options([]);
        $this->assertSame(0, $http['lifetime']);
        $this->assertSame('/', $http['path']);
        $this->assertFalse($http['secure']);
        $this->assertTrue($http['httponly']);
        $this->assertSame('Lax', $http['samesite']);

        $https = fennec_session_cookie_options(['HTTPS' => 'on']);
        $this->assertTrue($https['secure']);
    }
}
