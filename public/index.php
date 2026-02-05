<?php

declare(strict_types=1);

function fennec_route(string $method, string $path): array
{
    $normalizedPath = parse_url($path, PHP_URL_PATH);
    if (!is_string($normalizedPath) || $normalizedPath === '') {
        $normalizedPath = '/';
    }

    if ($method === 'GET' && $normalizedPath === '/healthz') {
        return fennec_json_response(200, ['status' => 'ok']);
    }

    if ($method === 'GET' && $normalizedPath === '/version') {
        return fennec_json_response(200, [
            'name' => 'fennec',
            'version' => getenv('FENNEC_VERSION') ?: '0.1.0',
            'commit' => getenv('FENNEC_COMMIT') ?: 'unknown',
            'build_time' => getenv('FENNEC_BUILD_TIME') ?: 'unknown',
        ]);
    }

    if ($method === 'GET' && $normalizedPath === '/openapi.yaml') {
        $specPath = fennec_openapi_path();
        if (!is_file($specPath)) {
            return fennec_problem_response(500, 'Spec unavailable', 'OpenAPI spec file not found.');
        }

        $specBody = file_get_contents($specPath);
        if ($specBody === false) {
            return fennec_problem_response(500, 'Spec unavailable', 'Unable to read OpenAPI spec file.');
        }

        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'application/yaml; charset=utf-8',
            ],
            'body' => $specBody,
        ];
    }

    return [
        'status' => 200,
        'headers' => [
            'Content-Type' => 'text/plain; charset=utf-8',
        ],
        'body' => 'Fennec bootstrap',
    ];
}

function fennec_json_response(int $status, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return fennec_problem_response(500, 'Serialization error', 'Unable to encode response JSON.');
    }

    return [
        'status' => $status,
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
        ],
        'body' => $body,
    ];
}

function fennec_problem_response(int $status, string $title, string $detail): array
{
    $payload = [
        'type' => 'about:blank',
        'title' => $title,
        'status' => $status,
        'detail' => $detail,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $body = '{"type":"about:blank","title":"Serialization error","status":500}';
    }

    return [
        'status' => $status,
        'headers' => [
            'Content-Type' => 'application/problem+json; charset=utf-8',
        ],
        'body' => $body,
    ];
}

function fennec_openapi_path(): string
{
    return __DIR__ . '/../docs/api/openapi.yaml';
}

function fennec_emit_response(array $response): void
{
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $value) {
        header($name . ': ' . $value);
    }
    echo $response['body'];
}

if (PHP_SAPI !== 'cli') {
    $response = fennec_route($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    fennec_emit_response($response);
}
