<?php
/**
 * SIMPLE ADMIN DASHBOARD API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

try {
    require_once '../../config/database.php';
    
    // Simple token check
    $token = $_GET['token'] ?? '';
    if (!$token) {
        throw new Exception('Token required');
    }
    
    $decoded = json_decode(base64_decode($token), true);
    if (!$decoded || $decoded['exp'] < time()) {
        throw new Exception('Invalid or expired token');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get stats
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Total license keys
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM license_keys");
    $stats['total_keys'] = $stmt->fetch()['count'];
    
    // Active keys
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM license_keys WHERE is_active = 1");
    $stats['active_keys'] = $stmt->fetch()['count'];
    
    // Get all license keys
    $stmt = $pdo->query("
        SELECT lk.*, u.username as assigned_user 
        FROM license_keys lk 
        LEFT JOIN users u ON lk.user_id = u.id 
        ORDER BY lk.created_at DESC
    ");
    $keys = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'keys' => $keys
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
