<?php
/**
 * Cleanup Expired Keys API
 * Automatically deletes expired license keys from the database
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
    if (!$decoded || $decoded['exp'] < time() || !isset($decoded['is_admin']) || !$decoded['is_admin']) {
        throw new Exception('Invalid or expired token');
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Delete expired keys
    $stmt = $pdo->prepare("DELETE FROM license_keys WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "Cleaned up $deletedCount expired key(s)",
        'data' => [
            'deleted_count' => $deletedCount
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
