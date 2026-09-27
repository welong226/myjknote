<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$filename = __DIR__ . '/data/calendar_data.json';

$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = [];

    if (file_exists($filename) && ($content = file_get_contents($filename))) {
        $all = json_decode($content, true) ?: [];
    }

    if ($month >= 1 && $month <= 12 && $year > 0) {
        $key = 'cal_' . $year . '_' . $month;
        echo json_encode($all[$key] ?? []);
    } else {
        echo json_encode($all);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $monthData = json_decode($input, true);

    if ($monthData === null || $month < 1 || $month > 12 || $year <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '無效的 JSON 資料']);
        exit;
    }

    $all = [];

    if (file_exists($filename) && ($content = file_get_contents($filename))) {
        $all = json_decode($content, true) ?: [];
    }

    $key = 'cal_' . $year . '_' . $month;
    $all[$key] = $monthData;

    if (file_put_contents($filename, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '無法寫入檔案']);
    }
}
?>
