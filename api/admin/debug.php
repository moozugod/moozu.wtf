<?php
/**
 * Debug API - Test what headers and data we receive
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

echo json_encode([
    'success' => true,
    'debug' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders(),
        'http_auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET',
        'input' => $input,
        'all_server' => $_SERVER
    ]
]);
?>
