<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

const FENNEC_PROBLEM_UNAUTHORIZED = 'https://fennec.sh/problems/unauthorized';
const FENNEC_PROBLEM_INVALID_REQUEST = 'https://fennec.sh/problems/invalid-request';
const FENNEC_PROBLEM_DB_UNAVAILABLE = 'https://fennec.sh/problems/db-unavailable';
const FENNEC_PROBLEM_JOB_CONFLICT = 'https://fennec.sh/problems/job-conflict';

function fennec_route(string $method, string $path, array $options = []): array
{
    $normalizedPath = parse_url($path, PHP_URL_PATH);
    if (!is_string($normalizedPath) || $normalizedPath === '') {
        $normalizedPath = '/';
    }

    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;

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

    if ($method === 'GET' && $normalizedPath === '/readyz') {
        return fennec_readiness_response();
    }

    if ($method === 'POST' && $normalizedPath === '/agent/v1/jobs/claim') {
        return fennec_agent_claim($headers);
    }

    if ($method === 'POST' && preg_match('#^/agent/v1/jobs/(\\d+)/complete$#', $normalizedPath, $matches)) {
        $jobId = (int) $matches[1];
        return fennec_agent_complete($jobId, $headers, $body);
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

function fennec_problem_response(
    int $status,
    string $title,
    string $detail,
    string $type = 'about:blank'
): array
{
    $payload = [
        'type' => $type,
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

function fennec_readiness_response(): array
{
    $guard = fennec_db_guard();
    if (isset($guard['error'])) {
        return $guard['error'];
    }

    $db = $guard['db'];
    if (!$db->ping()) {
        return fennec_problem_response(
            503,
            'Database unavailable',
            'Database ping failed.',
            FENNEC_PROBLEM_DB_UNAVAILABLE
        );
    }

    return fennec_json_response(200, [
        'status' => 'ok',
        'db' => 'ok',
    ]);
}

function fennec_agent_claim(array $headers): array
{
    $guard = fennec_db_guard();
    if (isset($guard['error'])) {
        return $guard['error'];
    }

    $auth = fennec_require_agent($guard['db'], $guard['config'], $headers);
    if (isset($auth['error'])) {
        return $auth['error'];
    }

    try {
        $jobs = new \Fennec\JobRepository($guard['db']);
        $agents = new \Fennec\AgentRepository($guard['db'], $guard['config']);
        $job = $jobs->claimNext($auth['agent']['id'], $agents);
    } catch (Throwable $exception) {
        return fennec_problem_response(
            503,
            'Database unavailable',
            'Job claim failed.',
            FENNEC_PROBLEM_DB_UNAVAILABLE
        );
    }

    if ($job === null) {
        return [
            'status' => 204,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body' => '',
        ];
    }

    return fennec_json_response(200, ['job' => $job]);
}

function fennec_agent_complete(int $jobId, array $headers, ?string $body): array
{
    $guard = fennec_db_guard();
    if (isset($guard['error'])) {
        return $guard['error'];
    }

    $auth = fennec_require_agent($guard['db'], $guard['config'], $headers);
    if (isset($auth['error'])) {
        return $auth['error'];
    }

    $payload = fennec_parse_json($body);
    if ($payload === null) {
        return fennec_problem_response(
            400,
            'Invalid request',
            'Request body must be valid JSON.',
            FENNEC_PROBLEM_INVALID_REQUEST
        );
    }

    $status = $payload['status'] ?? null;
    if (!is_string($status) || !in_array($status, ['succeeded', 'failed'], true)) {
        return fennec_problem_response(
            400,
            'Invalid request',
            'Status must be \"succeeded\" or \"failed\".',
            FENNEC_PROBLEM_INVALID_REQUEST
        );
    }

    $result = $payload['result'] ?? [];
    if (!is_array($result)) {
        return fennec_problem_response(
            400,
            'Invalid request',
            'Result must be a JSON object.',
            FENNEC_PROBLEM_INVALID_REQUEST
        );
    }

    $error = $payload['error'] ?? null;
    if ($error !== null && !is_string($error)) {
        return fennec_problem_response(
            400,
            'Invalid request',
            'Error must be a string.',
            FENNEC_PROBLEM_INVALID_REQUEST
        );
    }

    try {
        $jobs = new \Fennec\JobRepository($guard['db']);
        $job = $jobs->complete($jobId, $auth['agent']['id'], $status, $result, $error);
    } catch (Throwable $exception) {
        return fennec_problem_response(
            503,
            'Database unavailable',
            'Job completion failed.',
            FENNEC_PROBLEM_DB_UNAVAILABLE
        );
    }

    if ($job === null) {
        return fennec_problem_response(
            409,
            'Job conflict',
            'Job is not owned by this agent or is not running.',
            FENNEC_PROBLEM_JOB_CONFLICT
        );
    }

    return fennec_json_response(200, ['job' => $job]);
}

/**
 * @return array{db:\Fennec\Database,config:\Fennec\Config}|array{error:array}
 */
function fennec_db_guard(): array
{
    if (!class_exists(\Fennec\Config::class)) {
        return [
            'error' => fennec_problem_response(
                503,
                'Database unavailable',
                'Database configuration is not loaded.',
                FENNEC_PROBLEM_DB_UNAVAILABLE
            ),
        ];
    }

    $config = \Fennec\Config::fromEnv();
    if (!$config->hasDbConfig()) {
        return [
            'error' => fennec_problem_response(
                503,
                'Database unavailable',
                'Database configuration is missing.',
                FENNEC_PROBLEM_DB_UNAVAILABLE
            ),
        ];
    }

    try {
        $db = \Fennec\Database::connect($config);
    } catch (Throwable $exception) {
        return [
            'error' => fennec_problem_response(
                503,
                'Database unavailable',
                'Database connection failed.',
                FENNEC_PROBLEM_DB_UNAVAILABLE
            ),
        ];
    }

    return [
        'db' => $db,
        'config' => $config,
    ];
}

/**
 * @return array{agent:array{id:int,name:string}}|array{error:array}
 */
function fennec_require_agent(\Fennec\Database $db, \Fennec\Config $config, array $headers): array
{
    $authHeader = fennec_header($headers, 'Authorization');
    if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
        return [
            'error' => fennec_problem_response(
                401,
                'Unauthorized',
                'Missing bearer token.',
                FENNEC_PROBLEM_UNAUTHORIZED
            ),
        ];
    }

    $token = trim(substr($authHeader, 7));
    if ($token === '') {
        return [
            'error' => fennec_problem_response(
                401,
                'Unauthorized',
                'Invalid bearer token.',
                FENNEC_PROBLEM_UNAUTHORIZED
            ),
        ];
    }

    $agents = new \Fennec\AgentRepository($db, $config);
    $agent = $agents->authenticate($token);
    if ($agent === null) {
        return [
            'error' => fennec_problem_response(
                401,
                'Unauthorized',
                'Invalid bearer token.',
                FENNEC_PROBLEM_UNAUTHORIZED
            ),
        ];
    }

    return ['agent' => $agent];
}

function fennec_header(array $headers, string $name): ?string
{
    foreach ($headers as $key => $value) {
        if (is_string($key) && strcasecmp($key, $name) === 0) {
            return is_string($value) ? $value : null;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function fennec_parse_json(?string $body): ?array
{
    if ($body === null || trim($body) === '') {
        return null;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function fennec_openapi_path(): string
{
    return __DIR__ . '/../docs/api/openapi.yaml';
}

function fennec_request_headers(): array
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (!is_array($headers)) {
            $headers = [];
        }
    }

    if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }

    return $headers;
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
    $response = fennec_route(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/',
        [
            'headers' => fennec_request_headers(),
            'body' => file_get_contents('php://input') ?: null,
        ]
    );
    fennec_emit_response($response);
}
