<?php
/**
 * NoteTick API forward proxy (Simperium / TickTick).
 * POST JSON: { "url", "method", "headers", "body" }
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Expose-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'POST only']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data) || empty($data['url']) || !is_string($data['url'])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'bad request']);
    exit;
}

$url = $data['url'];
$parts = parse_url($url);
$host = $parts['host'] ?? '';
$scheme = $parts['scheme'] ?? '';
$allowed = [
    'auth.simperium.com',
    'api.simperium.com',
    'ticktick.com',
    'api.ticktick.com',
];
if ($scheme !== 'https' || !in_array($host, $allowed, true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'host not allowed']);
    exit;
}

$method = strtoupper((string)($data['method'] ?? 'GET'));
$headersIn = is_array($data['headers'] ?? null) ? $data['headers'] : [];
$body = $data['body'] ?? null;

$headerLines = [];
foreach ($headersIn as $k => $v) {
    if (!is_string($k)) {
        continue;
    }
    $headerLines[] = $k . ': ' . (string)$v;
}

$opts = [
    'http' => [
        'method' => $method,
        'header' => implode("\r\n", $headerLines),
        'timeout' => 90,
        'ignore_errors' => true,
        'follow_location' => 0,
    ],
];
if ($body !== null && $body !== '' && $method !== 'GET' && $method !== 'HEAD') {
    $opts['http']['content'] = is_string($body) ? $body : json_encode($body);
}

$ctx = stream_context_create($opts);
$respBody = @file_get_contents($url, false, $ctx);
if ($respBody === false) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    $err = error_get_last();
    echo json_encode(['error' => $err['message'] ?? 'upstream failed']);
    exit;
}

$status = 502;
$contentType = '';
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = (int)$m[1];
        } elseif (stripos($line, 'Content-Type:') === 0) {
            $contentType = trim(substr($line, strlen('Content-Type:')));
        }
    }
}

http_response_code($status);
if ($contentType !== '') {
    header('Content-Type: ' . $contentType);
}
echo $respBody;
