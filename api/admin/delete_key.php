<?php
/**
 * Delete Key API
 * Deletes a license key from the database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

try {
    require_once '../../config/database.php';
    
    // Read request body once
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Simple token check - get token from request body
    $token = $input['token'] ?? '';
    
    if (!$token) {
        throw new Exception('Token required');
    }
    
    $decoded = json_decode(base64_decode($token), true);
    if (!$decoded || $decoded['exp'] < time()) {
        throw new Exception('Invalid or expired token');
    }

    // Get input data
    $licenseKey = $input['license_key'] ?? '';
    
    if (!$licenseKey) {
        throw new Exception('License key required');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Delete the key
    $stmt = $pdo->prepare("DELETE FROM license_keys WHERE license_key = ?");
    $result = $stmt->execute([$licenseKey]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Key not found');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Key deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>