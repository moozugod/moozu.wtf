<?php
/**
 * SIMPLE CREATE KEYS API
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
    
           // Simple token check - check both header and body
           $token = '';
           
           // Check Authorization header first using $_SERVER
           if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
               $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
               if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                   $token = $matches[1];
               }
           }
           
           // If no token in header, check request body
           if (!$token) {
               $input = json_decode(file_get_contents('php://input'), true);
               $token = $input['token'] ?? '';
           }
    
    if (!$token) {
        throw new Exception('Token required');
    }
    
    $decoded = json_decode(base64_decode($token), true);
    if (!$decoded || $decoded['exp'] < time()) {
        throw new Exception('Invalid or expired token');
    }
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $count = intval($input['count'] ?? 1);
    $prefix = $input['prefix'] ?? 'KEY';
    $expires = $input['expiry_date'] ?? null;
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $createdKeys = [];
    
    for ($i = 0; $i < $count; $i++) {
        $licenseKey = $prefix . '_' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $stmt = $pdo->prepare("
            INSERT INTO license_keys (license_key, created_by_admin, is_active, expires_at) 
            VALUES (?, 1, 1, ?)
        ");
        $stmt->execute([$licenseKey, $expires]);
        
        $createdKeys[] = $licenseKey;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Created $count license key(s)",
        'data' => [
            'keys' => $createdKeys
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>