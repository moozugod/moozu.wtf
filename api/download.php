<?php
/**
 * Download API
 * Serves application files for download
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

try {
    $token = $_GET['token'] ?? '';
    $version = $_GET['version'] ?? '';
    
    if (!$token) {
        throw new Exception('Token required');
    }
    
    if (!$version) {
        throw new Exception('Version required');
    }
    
    // Decode token
    $decoded = json_decode(base64_decode($token), true);
    if (!$decoded || $decoded['exp'] < time()) {
        throw new Exception('Invalid or expired token');
    }
    
    // Define file path
    $filePath = '../downloads/' . $version . '/night.zip';
    
    if (!file_exists($filePath)) {
        throw new Exception('File not found');
    }
    
    // Set headers for file download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="night-' . $version . '.zip"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Output file
    readfile($filePath);
    exit();
    
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>