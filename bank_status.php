<?php
header('Content-Type: application/json; charset=utf-8');
$dir = __DIR__ . '/Bank';
$files = [];
if (is_dir($dir)) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        if (is_file($path)) {
            $files[] = ['name' => $f, 'bytes' => filesize($path)];
        }
    }
}
echo json_encode([
    'ok' => true,
    'bankDirExists' => is_dir($dir),
    'count' => count($files),
    'sample' => array_slice($files, 0, 5),
    'commitHint' => trim(@file_get_contents(__DIR__ . '/BUILD_ID') ?: ''),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
