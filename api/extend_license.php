<?php
/**
 * License Extension API
 * Allows users to extend their license with a new license key
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
    $newLicenseKey = $input['new_license_key'] ?? '';
    
    if (!$token) {
        throw new Exception('Token required');
    }
    
    if (!$newLicenseKey) {
        throw new Exception('New license key required');
    }
    
    // Decode token
    $decoded = json_decode(base64_decode($token), true);
    if (!$decoded || $decoded['exp'] < time()) {
        throw new Exception('Invalid or expired token');
    }
    
    $userId = $decoded['user_id'];
    
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Get current user info
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.license_key_id,
                   lk.license_key as current_key, lk.expires_at as current_expires
            FROM users u
            LEFT JOIN license_keys lk ON u.license_key_id = lk.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Check if new license key exists and is unassigned and active
        $stmt = $pdo->prepare("
            SELECT id, license_key, expires_at 
            FROM license_keys 
            WHERE license_key = ? AND user_id IS NULL AND is_active = TRUE
        ");
        $stmt->execute([$newLicenseKey]);
        $newKey = $stmt->fetch();
        
        if (!$newKey) {
            throw new Exception('Invalid, used, or inactive license key');
        }
        
        // Check if new key has expired
        if ($newKey['expires_at'] && strtotime($newKey['expires_at']) < time()) {
            throw new Exception('New license key has expired');
        }
        
        // Calculate new expiry date
        $newExpiresAt = null;
        if ($newKey['expires_at']) {
            // If current license has expiry, extend from current expiry
            if ($user['current_expires']) {
                $currentExpiry = strtotime($user['current_expires']);
                $newKeyDuration = strtotime($newKey['expires_at']) - time();
                $newExpiresAt = date('Y-m-d H:i:s', $currentExpiry + $newKeyDuration);
            } else {
                // If current license is permanent, use new key's expiry
                $newExpiresAt = $newKey['expires_at'];
            }
        }
        
        // Update user's license key
        $stmt = $pdo->prepare("
            UPDATE users 
            SET license_key_id = ? 
            WHERE id = ?
        ");
        $stmt->execute([$newKey['id'], $userId]);
        
        // Assign new license key to user
        $stmt = $pdo->prepare("
            UPDATE license_keys 
            SET user_id = ?, expires_at = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$userId, $newExpiresAt, $newKey['id']]);
        
        // Deactivate old license key if it exists
        if ($user['license_key_id']) {
            $stmt = $pdo->prepare("
                UPDATE license_keys 
                SET is_active = FALSE, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$user['license_key_id']]);
        }
        
        $pdo->commit();
        
        // Get updated user info
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email,
                   lk.license_key, lk.expires_at, lk.is_active as license_active
            FROM users u
            LEFT JOIN license_keys lk ON u.license_key_id = lk.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'License extended successfully',
            'data' => [
                'user' => $updatedUser,
                'new_expires_at' => $newExpiresAt
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
