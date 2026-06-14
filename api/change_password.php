<?php
require_once '../config/database.php';
require_once 'functions.php';

/**
 * Change Password API
 * POST /api/change_password.php
 * Headers: Authorization: Bearer <token>
 * Body: { "currentPassword": "string", "newPassword": "string", "confirmPassword": "string" }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Method not allowed', 405);
}

try {
    // Get and verify JWT token
    $token = getBearerToken();

    if (!$token) {
        sendResponse(false, null, 'Authorization token required', 401);
    }

    $payload = verifyJWT($token);

    if (!$payload) {
        sendResponse(false, null, 'Invalid or expired token', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse(false, null, 'Invalid JSON input', 400);
    }

    // Validate required fields
    $validationRules = [
        'currentPassword' => 'required|min:6',
        'newPassword' => 'required|min:6|max:255',
        'confirmPassword' => 'required|min:6|max:255'
    ];

    $errors = validateInput($input, $validationRules);

    if (!empty($errors)) {
        sendResponse(false, null, 'Validation failed: ' . implode(', ', $errors), 400);
    }

    $currentPassword = $input['currentPassword'];
    $newPassword = $input['newPassword'];
    $confirmPassword = $input['confirmPassword'];

    // Check if new passwords match
    if ($newPassword !== $confirmPassword) {
        sendResponse(false, null, 'New passwords do not match', 400);
    }

    // Get user data
    $user = getUserById($payload['user_id']);

    if (!$user) {
        sendResponse(false, null, 'User not found', 404);
    }

    // Verify current password
    if (!verifyPassword($currentPassword, $user['password_hash'])) {
        sendResponse(false, null, 'Current password is incorrect', 400);
    }

    // Hash new password
    $newPasswordHash = hashPassword($newPassword);

    // Update password in database
    $pdo = getDBConnection();
    if (!$pdo) {
        sendResponse(false, null, 'Database connection failed', 500);
    }

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$newPasswordHash, $user['id']]);

    if ($result) {
        // Log API access
        logApiAccess($user['api_key'], $_SERVER['REQUEST_URI'], 200);

        sendResponse(true, null, 'Password updated successfully');
    } else {
        sendResponse(false, null, 'Failed to update password', 500);
    }

} catch (Exception $e) {
    error_log('Change password error: ' . $e->getMessage());

    if (isset($user['api_key'])) {
        logApiAccess($user['api_key'], $_SERVER['REQUEST_URI'], 500);
    }

    sendResponse(false, null, 'Internal server error', 500);
}
?>
