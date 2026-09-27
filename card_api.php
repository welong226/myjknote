<?php
/**
 * Card Manager API
 * Handles saving and loading card data from a JSON file.
 */

$data_file = __DIR__ . '/data/cards_data.json';

// Handle CORS if needed (though on same domain here)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save data
    $json = file_get_contents('php://input');
    if ($json !== false) {
        if (file_put_contents($data_file, $json) !== false) {
            echo json_encode(["status" => "success"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to write file"]);
        }
    }
} else {
    // Load data
    if (file_exists($data_file)) {
        echo file_get_contents($data_file);
    } else {
        echo json_encode([]);
    }
}
?>
