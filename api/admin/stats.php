<?php
/**
 * ADMIN STATS API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

try {
    require_once '../../config/database.php';
    
    // Simple token check - check query parameter first, then header
    $token = $_GET['token'] ?? '';
    
    // If no token in query, check Authorization header
    if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
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
    
    // Assigned keys
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM license_keys WHERE user_id IS NOT NULL");
    $stats['assigned_keys'] = $stmt->fetch()['count'];
    
    // Unassigned keys
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM license_keys WHERE user_id IS NULL");
    $stats['unassigned_keys'] = $stmt->fetch()['count'];
    
    // Total downloads (if app_downloads table exists)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM app_downloads");
        $stats['total_downloads'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['total_downloads'] = 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>