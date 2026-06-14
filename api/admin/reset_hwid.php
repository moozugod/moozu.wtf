<?php
/**
 * Admin HWID Reset API
 * Allows admin to reset a user's HWID
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
    require_once '../../config/database.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Debug: Log the input
    error_log("HWID Reset Input: " . json_encode($input));
    
    // Get token from request body
    $token = $input['token'] ?? '';
    $userId = $input['user_id'] ?? '';
    $resetReason = $input['reset_reason'] ?? 'Admin reset';
    
    if (empty($token)) {
        throw new Exception('Admin token required');
    }
    
    if (empty($userId)) {
        throw new Exception('User ID required');
    }
    
    // Simple admin token validation (same as other admin endpoints)
    $decodedToken = base64_decode($token);
    $tokenData = json_decode($decodedToken, true);
    
    if (!$tokenData || !isset($tokenData['is_admin']) || !$tokenData['is_admin']) {
        throw new Exception('Invalid admin token');
    }
    
    $adminId = $tokenData['user_id'] ?? 0;
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    error_log("Database connection successful");
    
    // Get user info (check if hwid column exists first)
    try {
        $stmt = $pdo->prepare("SELECT id, username, hwid FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // HWID column doesn't exist, just get basic user info
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user['hwid'] = null; // Set hwid to null if column doesn't exist
    }
    
    error_log("User query result: " . json_encode($user));
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $oldHwid = $user['hwid'] ?? null;
    
    // Reset HWID (set to NULL) - handle missing columns gracefully
    try {
        $stmt = $pdo->prepare("UPDATE users SET hwid = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        error_log("HWID reset successful");
    } catch (Exception $e) {
        error_log("HWID reset failed: " . $e->getMessage());
        throw new Exception('Failed to reset HWID: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'HWID reset successfully',
        'data' => [
            'user_id' => $userId,
            'username' => $user['username'],
            'old_hwid' => $oldHwid
        ]
    ]);
    
} catch (Exception $e) {
    error_log("HWID reset error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("HWID reset PHP error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
