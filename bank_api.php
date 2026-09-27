<?php
/**
 * Bank Quota API
 */
$data_file = __DIR__ . '/data/banks_quota.json';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    if ($json !== false) {
        file_put_contents($data_file, $json);
        echo json_encode(["status" => "success"]);
    }
} else {
    if (file_exists($data_file)) {
        echo file_get_contents($data_file);
    } else {
        echo json_encode([]);
    }
}
?>
