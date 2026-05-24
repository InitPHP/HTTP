<?php
declare(strict_types=1);

/**
 * PSR-18 entegrasyon test fixture'ı.
 * Route'lar:
 *   /echo      → method, body ve isteğin header'larını JSON olarak yansıtır
 *   /status    → ?code=NNN ile arbitrary status döner
 *   /cookies   → iki Set-Cookie header'ı verir
 *   /redirect  → 302 ile /echo'a yönlendirir
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($path === '/echo') {
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
        }
    }
    header('Content-Type: application/json');
    echo json_encode([
        'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'body'    => file_get_contents('php://input') ?: '',
        'headers' => $headers,
        'path'    => $path,
    ]);
    return true;
}

if ($path === '/status') {
    $code = (int) ($_GET['code'] ?? 200);
    http_response_code($code);
    echo 'status ' . $code;
    return true;
}

if ($path === '/cookies') {
    header('Set-Cookie: a=1');
    header('Set-Cookie: b=2', false);
    echo 'cookies';
    return true;
}

if ($path === '/redirect') {
    header('Location: /echo', true, 302);
    echo 'redirecting';
    return true;
}

http_response_code(404);
echo 'not found';
return true;
