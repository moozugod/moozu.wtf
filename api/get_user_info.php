<?php
/**
 * Get User Information API
 * Returns current user information including license details
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

try {
    require_once '../config/database.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    
    if (empty($token)) {
        throw new Exception('Token required');
    }
    
    // Decode token
    $tokenData = json_decode(base64_decode($token), true);
    if (!$tokenData || !isset($tokenData['user_id'])) {
        throw new Exception('Invalid token');
    }
    
    // Check if token is expired
    if (isset($tokenData['exp']) && $tokenData['exp'] < time()) {
        throw new Exception('Token expired');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get user information with license details
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at, u.last_login,
               lk.license_key, lk.expires_at, lk.is_active as key_active
        FROM users u
        LEFT JOIN license_keys lk ON u.license_key_id = lk.id
        WHERE u.id = ?
    ");
    $stmt->execute([$tokenData['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Check if license key is still active
    $isActive = $user['key_active'] && (!$user['expires_at'] || strtotime($user['expires_at']) > time());
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'license_key' => $user['license_key'],
                'expires_at' => $user['expires_at'],
                'license_active' => $isActive,
                'created_at' => $user['created_at'],
                'last_login' => $user['last_login']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Get user info error: " . $e->getMessage());
    
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Log PHP errors
    error_log("Get user info PHP error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
