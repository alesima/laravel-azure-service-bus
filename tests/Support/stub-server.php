<?php

// Router stub for CurlHttpTransportTest (run with: php -S 127.0.0.1:PORT this-file).
// Responds based on the request path; no external network involved.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/echo') {
    header('X-Echo-Method: ' . $method);
    header('X-Echo-Has-Auth: ' . (isset($_SERVER['HTTP_AUTHORIZATION']) ? 'yes' : 'no'));
    header('Content-Type: text/plain');
    echo 'method=' . $method . ';body=' . file_get_contents('php://input');

    return;
}

if ($path === '/created') {
    http_response_code(201);
    header('Content-Type: text/plain');
    echo 'made';

    return;
}

if ($path === '/missing') {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'nope';

    return;
}

header('Content-Type: text/plain');
echo 'ok';
