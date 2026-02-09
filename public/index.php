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

    if ($method === 'GET' && $normalizedPath === '/login') {
        return fennec_login_page();
    }

    if ($method === 'POST' && $normalizedPath === '/login') {
        return fennec_login_submit($body);
    }

    if ($method === 'POST' && $normalizedPath === '/logout') {
        return fennec_logout_submit($body);
    }

    if ($method === 'GET' && $normalizedPath === '/admin') {
        return fennec_admin_dashboard();
    }

    if ($method === 'POST' && $normalizedPath === '/agent/v1/jobs/claim') {
        return fennec_agent_claim($headers);
    }

    if ($method === 'POST' && preg_match('#^/agent/v1/jobs/(\\d+)/heartbeat$#', $normalizedPath, $matches)) {
        $jobId = (int) $matches[1];
        return fennec_agent_heartbeat($jobId, $headers);
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

function fennec_html_response(int $status, string $body): array
{
    $headers = array_merge(
        [
            'Content-Type' => 'text/html; charset=utf-8',
        ],
        fennec_html_security_headers()
    );

    return [
        'status' => $status,
        'headers' => $headers,
        'body' => $body,
    ];
}

function fennec_redirect_response(string $location, int $status = 302): array
{
    $headers = array_merge(
        [
            'Content-Type' => 'text/html; charset=utf-8',
            'Location' => $location,
        ],
        fennec_html_security_headers()
    );

    return [
        'status' => $status,
        'headers' => $headers,
        'body' => '',
    ];
}

/**
 * @return array<string, string>
 */
function fennec_html_security_headers(): array
{
    return [
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'X-Frame-Options' => 'DENY',
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
        'instance' => 'urn:fennec:problem',
    ];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $body = '{"type":"about:blank","title":"Serialization error","status":500,"instance":"urn:fennec:problem"}';
    }

    return [
        'status' => $status,
        'headers' => [
            'Content-Type' => 'application/problem+json; charset=utf-8',
        ],
        'body' => $body,
    ];
}

function fennec_login_page(?string $error = null, int $status = 200): array
{
    $csrfToken = fennec_ensure_csrf_token();
    $errorHtml = '';
    if ($error !== null && trim($error) !== '') {
        $errorHtml = '<p style="color:#b00020;">' . fennec_escape_html($error) . '</p>';
    }

    $body = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fennec Login</title>
</head>
<body>
  <main style="max-width: 420px; margin: 4rem auto; font-family: sans-serif;">
    <h1>Fennec Login</h1>
    {$errorHtml}
    <form method="post" action="/login">
      <input type="hidden" name="csrf_token" value="{$csrfToken}">
      <div style="margin-bottom: 0.8rem;">
        <label for="email">Email</label><br>
        <input id="email" name="email" type="email" required autocomplete="username" style="width: 100%;">
      </div>
      <div style="margin-bottom: 0.8rem;">
        <label for="password">Password</label><br>
        <input id="password" name="password" type="password" required autocomplete="current-password" style="width: 100%;">
      </div>
      <button type="submit">Sign in</button>
    </form>
  </main>
</body>
</html>
HTML;

    return fennec_html_response($status, $body);
}

function fennec_login_submit(?string $body): array
{
    $form = fennec_parse_form($body);
    if (!fennec_csrf_valid($form['csrf_token'] ?? null)) {
        return fennec_login_page('Invalid CSRF token.', 400);
    }

    $email = trim((string) ($form['email'] ?? ''));
    $password = (string) ($form['password'] ?? '');
    if ($email === '' || $password === '') {
        return fennec_login_page('Email and password are required.', 400);
    }

    $guard = fennec_db_guard();
    if (isset($guard['error'])) {
        return $guard['error'];
    }

    try {
        $admin = $guard['db']->fetchOne(
            "SELECT id, password_hash\n" .
            "FROM users\n" .
            "WHERE email = :email AND role = 'admin' AND disabled = false\n" .
            "LIMIT 1",
            [':email' => $email]
        );
    } catch (Throwable $exception) {
        return fennec_problem_response(
            503,
            'Database unavailable',
            'Admin login failed.',
            FENNEC_PROBLEM_DB_UNAVAILABLE
        );
    }

    if ($admin === null || !password_verify($password, (string) $admin['password_hash'])) {
        return fennec_login_page('Invalid credentials.', 401);
    }

    fennec_session_regenerate();

    fennec_session_set('admin_id', (int) $admin['id']);
    fennec_session_set('csrf_token', fennec_generate_csrf_token());

    return fennec_redirect_response('/admin');
}

function fennec_logout_submit(?string $body): array
{
    $form = fennec_parse_form($body);
    if (!fennec_csrf_valid($form['csrf_token'] ?? null)) {
        return fennec_login_page('Invalid CSRF token.', 400);
    }

    fennec_session_clear();

    fennec_session_regenerate();

    return fennec_redirect_response('/login');
}

function fennec_admin_dashboard(): array
{
    $adminId = fennec_session_admin_id();
    if ($adminId === null) {
        return fennec_redirect_response('/login');
    }

    $guard = fennec_db_guard();
    if (isset($guard['error'])) {
        return $guard['error'];
    }

    try {
        $admin = $guard['db']->fetchOne(
            "SELECT id, email\n" .
            "FROM users\n" .
            "WHERE id = :id AND role = 'admin' AND disabled = false\n" .
            "LIMIT 1",
            [':id' => $adminId]
        );

        if ($admin === null) {
            fennec_session_clear();
            return fennec_redirect_response('/login');
        }

        $jobs = $guard['db']->fetchAll(
            "SELECT id, type, status, attempt, created_at, started_at, finished_at, heartbeat_at, lease_expires_at\n" .
            "FROM jobs\n" .
            "ORDER BY id DESC\n" .
            "LIMIT 10"
        );

        $agents = $guard['db']->fetchAll(
            "SELECT id, name, created_at, last_seen_at\n" .
            "FROM agents\n" .
            "ORDER BY id DESC"
        );
    } catch (Throwable $exception) {
        return fennec_problem_response(
            503,
            'Database unavailable',
            'Admin dashboard failed.',
            FENNEC_PROBLEM_DB_UNAVAILABLE
        );
    }

    $csrfToken = fennec_ensure_csrf_token();
    $jobsRows = '';
    foreach ($jobs as $job) {
        $jobsRows .= '<tr>';
        $jobsRows .= '<td>' . fennec_escape_html((string) $job['id']) . '</td>';
        $jobsRows .= '<td>' . fennec_escape_html((string) $job['type']) . '</td>';
        $jobsRows .= '<td>' . fennec_escape_html((string) $job['status']) . '</td>';
        $jobsRows .= '<td>' . fennec_escape_html((string) $job['attempt']) . '</td>';
        $jobsRows .= '<td>' . fennec_escape_html(fennec_nullable_string($job['created_at'])) . '</td>';
        $jobsRows .= '<td>' . fennec_escape_html(fennec_nullable_string($job['started_at'])) . '</td>';
        $jobsRows .= '<td>' . fennec_escape_html(fennec_nullable_string($job['finished_at'])) . '</td>';
        $jobsRows .= '</tr>';
    }
    if ($jobsRows === '') {
        $jobsRows = '<tr><td colspan="7">No jobs yet.</td></tr>';
    }

    $agentRows = '';
    foreach ($agents as $agent) {
        $agentRows .= '<tr>';
        $agentRows .= '<td>' . fennec_escape_html((string) $agent['id']) . '</td>';
        $agentRows .= '<td>' . fennec_escape_html((string) $agent['name']) . '</td>';
        $agentRows .= '<td>' . fennec_escape_html(fennec_nullable_string($agent['created_at'])) . '</td>';
        $agentRows .= '<td>' . fennec_escape_html(fennec_nullable_string($agent['last_seen_at'] ?? null)) . '</td>';
        $agentRows .= '</tr>';
    }
    if ($agentRows === '') {
        $agentRows = '<tr><td colspan="4">No agents yet.</td></tr>';
    }

    $adminEmail = fennec_escape_html((string) $admin['email']);
    $body = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fennec Admin</title>
</head>
<body>
  <main style="max-width: 980px; margin: 2rem auto; font-family: sans-serif;">
    <h1>Fennec Admin</h1>
    <p>Signed in as {$adminEmail}</p>
    <form method="post" action="/logout" style="margin-bottom: 1rem;">
      <input type="hidden" name="csrf_token" value="{$csrfToken}">
      <button type="submit">Sign out</button>
    </form>
    <p>
      <a href="/openapi.yaml">OpenAPI</a> |
      <a href="/healthz">Healthz</a> |
      <a href="/readyz">Readyz</a>
    </p>

    <h2>Recent Jobs</h2>
    <table border="1" cellpadding="6" cellspacing="0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Status</th>
          <th>Attempt</th>
          <th>Created</th>
          <th>Started</th>
          <th>Finished</th>
        </tr>
      </thead>
      <tbody>
        {$jobsRows}
      </tbody>
    </table>

    <h2>Agents</h2>
    <table border="1" cellpadding="6" cellspacing="0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Created</th>
          <th>Last Seen</th>
        </tr>
      </thead>
      <tbody>
        {$agentRows}
      </tbody>
    </table>
  </main>
</body>
</html>
HTML;

    return fennec_html_response(200, $body);
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
        $leaseSeconds = $guard['config']->jobLeaseSeconds();
        $job = $jobs->claimNext($auth['agent']['id'], $agents, $leaseSeconds);
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

function fennec_agent_heartbeat(int $jobId, array $headers): array
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
        $leaseSeconds = $guard['config']->jobLeaseSeconds();
        $job = $jobs->heartbeat($jobId, $auth['agent']['id'], $leaseSeconds);
    } catch (Throwable $exception) {
        return fennec_problem_response(
            503,
            'Database unavailable',
            'Job heartbeat failed.',
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
        try {
            $existing = $jobs->findById($jobId);
        } catch (Throwable $exception) {
            return fennec_problem_response(
                503,
                'Database unavailable',
                'Job completion failed.',
                FENNEC_PROBLEM_DB_UNAVAILABLE
            );
        }

        if (
            $existing !== null
            && $existing['locked_by_agent_id'] === $auth['agent']['id']
            && fennec_is_terminal_job_status($existing['status'])
            && fennec_json_values_equal($existing['result'], $result)
            && $existing['last_error'] === $error
            && $existing['status'] === $status
        ) {
            return fennec_json_response(200, ['job' => $existing]);
        }

        return fennec_problem_response(
            409,
            'Job conflict',
            'Job is not owned by this agent, is not running, or has a conflicting terminal state.',
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

/**
 * @return array<string, string>
 */
function fennec_parse_form(?string $body): array
{
    if ($body === null || trim($body) === '') {
        return [];
    }

    $parsed = [];
    parse_str($body, $parsed);
    if (!is_array($parsed)) {
        return [];
    }

    $clean = [];
    foreach ($parsed as $key => $value) {
        if (is_string($key) && is_scalar($value)) {
            $clean[$key] = trim((string) $value);
        }
    }

    return $clean;
}

function fennec_session_bootstrap(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    session_name('fennec_session');
    session_set_cookie_params(fennec_session_cookie_options($_SERVER));
    session_start();
}

/**
 * @param array<string, mixed> $server
 * @return array{lifetime:int,path:string,secure:bool,httponly:bool,samesite:string}
 */
function fennec_session_cookie_options(array $server): array
{
    return [
        'lifetime' => 0,
        'path' => '/',
        'secure' => fennec_request_is_https($server),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/**
 * @param array<string, mixed> $server
 */
function fennec_request_is_https(array $server): bool
{
    $https = $server['HTTPS'] ?? '';
    if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
        return true;
    }

    $forwarded = $server['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwarded) && strtolower($forwarded) === 'https') {
        return true;
    }

    return false;
}

function fennec_session_regenerate(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return false;
    }

    return session_regenerate_id(true);
}

function fennec_session_set(string $key, mixed $value): void
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    $_SESSION[$key] = $value;
}

function fennec_session_get(string $key): mixed
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return null;
    }

    return $_SESSION[$key] ?? null;
}

function fennec_session_clear(): void
{
    $_SESSION = [];
}

function fennec_session_admin_id(): ?int
{
    $raw = fennec_session_get('admin_id');
    if (is_int($raw) && $raw > 0) {
        return $raw;
    }

    if (is_string($raw) && ctype_digit($raw)) {
        $value = (int) $raw;
        return $value > 0 ? $value : null;
    }

    return null;
}

function fennec_generate_csrf_token(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $exception) {
        return bin2hex((string) microtime(true));
    }
}

function fennec_ensure_csrf_token(): string
{
    $token = fennec_session_get('csrf_token');
    if (is_string($token) && $token !== '') {
        return $token;
    }

    $token = fennec_generate_csrf_token();
    fennec_session_set('csrf_token', $token);

    return $token;
}

function fennec_csrf_valid(mixed $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $known = fennec_session_get('csrf_token');
    if (!is_string($known) || $known === '') {
        return false;
    }

    return hash_equals($known, $token);
}

function fennec_escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fennec_nullable_string(mixed $value): string
{
    if ($value === null) {
        return '-';
    }

    return (string) $value;
}

function fennec_is_terminal_job_status(mixed $status): bool
{
    return is_string($status) && in_array($status, ['succeeded', 'failed'], true);
}

function fennec_json_values_equal(mixed $left, mixed $right): bool
{
    if (!is_array($left) || !is_array($right)) {
        return false;
    }

    return fennec_normalize_json_value($left) === fennec_normalize_json_value($right);
}

function fennec_normalize_json_value(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        foreach ($value as $index => $item) {
            $value[$index] = fennec_normalize_json_value($item);
        }
        return $value;
    }

    ksort($value);
    foreach ($value as $key => $item) {
        $value[$key] = fennec_normalize_json_value($item);
    }

    return $value;
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
    fennec_session_bootstrap();
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
