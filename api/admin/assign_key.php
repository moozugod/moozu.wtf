<?php
/**
 * Assign Key API
 * Assigns a license key to a user
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
    $username = $input['username'] ?? '';
    $licenseKey = $input['license_key'] ?? '';
    
    if (!$username || !$licenseKey) {
        throw new Exception('Username and license key required');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if user exists, if not create them
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$username, $username . '@example.com']);
        $userId = $pdo->lastInsertId();
    } else {
        $userId = $user['id'];
    }
    
    // Assign the key to the user
    $stmt = $pdo->prepare("UPDATE license_keys SET user_id = ?, updated_at = NOW() WHERE license_key = ? AND user_id IS NULL");
    $result = $stmt->execute([$userId, $licenseKey]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Key not found or already assigned');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Key assigned successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>