<?php
/**
 * License Key Login Endpoint
 * Handles login using license key
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $licenseKey = $input['license_key'] ?? '';
    
    if (empty($licenseKey)) {
        throw new Exception('License key is required');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if license key exists and is active
    $stmt = $pdo->prepare("
        SELECT 
            lk.id as key_id,
            lk.license_key,
            lk.is_active as key_active,
            lk.expires_at,
            u.id as user_id,
            u.username,
            u.email,
            u.is_active as user_active
        FROM license_keys lk
        LEFT JOIN users u ON lk.user_id = u.id
        WHERE lk.license_key = ? AND lk.is_active = 1
    ");
    $stmt->execute([$licenseKey]);
    $keyData = $stmt->fetch();
    
    if (!$keyData) {
        throw new Exception('Invalid or inactive license key');
    }
    
    // Check if key has expired
    if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
        throw new Exception('License key has expired');
    }
    
    // If key is assigned to a user, check if user is active
    if ($keyData['user_id'] && !$keyData['user_active']) {
        throw new Exception('User account is inactive');
    }
    
    // If no user is assigned, create a temporary user session
    if (!$keyData['user_id']) {
        // Generate temporary username
        $tempUsername = 'user_' . substr($licenseKey, -8);
        $tempEmail = $tempUsername . '@temp.local';
        
        // Create temporary user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, license_key, is_active) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $tempPassword = password_hash(uniqid(), PASSWORD_DEFAULT);
        $stmt->execute([$tempUsername, $tempEmail, $tempPassword, $licenseKey]);
        
        $userId = $pdo->lastInsertId();
        
        // Assign key to user
        $stmt = $pdo->prepare("UPDATE license_keys SET user_id = ? WHERE id = ?");
        $stmt->execute([$userId, $keyData['key_id']]);
        
        $user = [
            'id' => $userId,
            'username' => $tempUsername,
            'email' => $tempEmail,
            'license_key' => $licenseKey,
            'is_active' => true,
            'expires_at' => $keyData['expires_at']
        ];
    } else {
        $user = [
            'id' => $keyData['user_id'],
            'username' => $keyData['username'],
            'email' => $keyData['email'],
            'license_key' => $licenseKey,
            'is_active' => $keyData['user_active'],
            'expires_at' => $keyData['expires_at']
        ];
    }
    
    // Generate JWT token
    $token = generateJWT([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'license_key' => $licenseKey
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'user' => $user
        ]
    ]);
    
} catch (Exception $e) {
    error_log("License key login error: " . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
