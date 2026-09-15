<?php

declare(strict_types=1);

/**
 * Shared security helpers for the SmartGate application.
 */

function smartgate_start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    smartgate_security_headers();

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function smartgate_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function smartgate_csrf_token(): string
{
    smartgate_start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function smartgate_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(smartgate_csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function smartgate_require_csrf(): void
{
    smartgate_start_session();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }

    $submitted =
        isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
            ? $_POST['csrf_token']
            : '';
    $expected = (string)($_SESSION['csrf_token'] ?? '');

    if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please reload the page and try again.');
    }
}

function smartgate_require_device_auth(): void
{
    smartgate_security_headers();

    // SmartGate ESP32 device key
    $configuredKey =
        'Y-q42R_nty9coWVlPO0gU03GKTW5tIJFtw73BacFI7NCX8EjNSOjeA-TYp1ygYQS';

    // Key received from ESP32
    $providedKey =
        (string)($_SERVER['HTTP_X_DEVICE_KEY'] ?? '');

    if (
        $providedKey === '' ||
        !hash_equals($configuredKey, $providedKey)
    ) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized device.'
        ]);

        exit;
    }
}

function smartgate_allowed_role(string $role): bool
{
    return in_array($role, [
        'super_admin',
        'MIS',
        'Security',
        'CCDU',
        'Guidance',
        'Library',
        'IGP'
    ], true);
}
