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

$curlHeaders = [];
foreach ($headersIn as $k => $v) {
    if (!is_string($k)) {
        continue;
    }
    $curlHeaders[] = $k . ': ' . (string)$v;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER => $curlHeaders,
]);
if ($body !== null && $body !== '' && $method !== 'GET' && $method !== 'HEAD') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
}

$resp = curl_exec($ch);
if ($resp === false) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}

$headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$respBody = substr($resp, $headerSize);
curl_close($ch);

http_response_code($status > 0 ? $status : 502);
if ($contentType !== '') {
    header('Content-Type: ' . $contentType);
}
echo $respBody;
