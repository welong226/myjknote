<?php
/*
  我的班表 - 班表資料 API（按月分檔）
  GET  /api.php?ym=2026-09  -> 該月 JSON（沒有就回 {}）
  POST /api.php?ym=2026-09  -> 覆寫該月 JSON（body 為該月日期物件）

  檔案：shift_data/YYYY-MM.json
  若月檔不存在，會嘗試從舊的 shift_data.json 抽該月（相容遷移）
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataDir = __DIR__ . '/data/shift_data';
$legacyFile = __DIR__ . '/data/shift_data.json';

function valid_ym($ym) {
    return is_string($ym) && preg_match('/^\d{4}-\d{2}$/', $ym);
}

function month_file($dir, $ym) {
    return $dir . '/' . $ym . '.json';
}

function read_json_file($file) {
    if (!file_exists($file)) return array();
    $txt = file_get_contents($file);
    if ($txt === false || $txt === '') return array();
    $obj = json_decode($txt, true);
    return is_array($obj) ? $obj : array();
}

function write_json_file($file, $obj) {
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;
    $tmp = $file . '.tmp';
    $fp = fopen($tmp, 'c+');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    ftruncate($fp, 0);
    fseek($fp, 0);
    fwrite($fp, json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return rename($tmp, $file);
}

function filter_month($all, $ym) {
    $out = array();
    foreach ($all as $k => $v) {
        if (is_string($k) && strpos($k, $ym) === 0) $out[$k] = $v;
    }
    return $out;
}

function read_month($dir, $legacy, $ym) {
    $file = month_file($dir, $ym);
    if (file_exists($file)) return read_json_file($file);
    // 相容：從舊單一檔抽出該月
    if (file_exists($legacy)) {
        return filter_month(read_json_file($legacy), $ym);
    }
    return array();
}

$ym = isset($_GET['ym']) ? $_GET['ym'] : '';
if (!valid_ym($ym)) {
    http_response_code(400);
    echo json_encode(array('error' => 'ym required, format YYYY-MM'), JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(read_month($dataDir, $legacyFile, $ym), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $obj = json_decode($raw, true);
    if (!is_array($obj)) {
        http_response_code(400);
        echo json_encode(array('error' => 'invalid json'));
        exit;
    }
    // 只留下該月的日期鍵，避免寫錯月
    $clean = filter_month($obj, $ym);
    $file = month_file($dataDir, $ym);
    if (write_json_file($file, $clean)) {
        echo json_encode(array('ok' => true, 'ym' => $ym, 'count' => count($clean)));
    } else {
        http_response_code(500);
        echo json_encode(array('error' => 'write failed'));
    }
    exit;
}

http_response_code(405);
echo json_encode(array('error' => 'method not allowed'));
