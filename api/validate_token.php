<?php
/**
 * Token Validation API
 * Validates tokens for continuous validation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    require_once '../config/database.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $token = $input['token'] ?? '';
    $username = $input['username'] ?? '';
    
    if (empty($token) || empty($username)) {
        throw new Exception('Token and username required');
    }
    
    // Decode token
    $decoded = json_decode(base64_decode($token), true);
    if (!$decoded) {
        throw new Exception('Invalid token format');
    }
    
    // Check if token is expired
    if (isset($decoded['exp']) && $decoded['exp'] < time()) {
        throw new Exception('Token expired');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Verify user exists and token matches
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.hwid, lk.is_active as key_active, lk.expires_at
        FROM users u
        LEFT JOIN license_keys lk ON u.license_key_id = lk.id
        WHERE u.username = ? AND u.id = ?
    ");
    $stmt->execute([$username, $decoded['user_id'] ?? 0]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Check if license key is still active
    if (!$user['key_active']) {
        throw new Exception('License key deactivated');
    }
    
    // Check if license key is expired
    if ($user['expires_at'] && strtotime($user['expires_at']) < time()) {
        throw new Exception('License key expired');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Token valid',
        'data' => [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'license_active' => $user['key_active'],
            'expires_at' => $user['expires_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Token validation error: " . $e->getMessage());
    
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Token validation PHP error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>