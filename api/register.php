<?php
/**
 * User Registration API
 * Registers a new user with a license key
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// CAPTCHA validation function
function validateCaptcha($captchaId, $userAnswer) {
    // Simple server-side CAPTCHA validation
    // In a real implementation, you might store CAPTCHA data in session or database
    
    // For now, we'll use a simple validation based on the captcha ID
    // This is a basic implementation - you can enhance it with proper session management
    
    if (empty($captchaId) || empty($userAnswer)) {
        return false;
    }
    
    // Decode the captcha ID to get the expected answer
    // In a real implementation, you'd store this in a session or database
    $expectedAnswer = base64_decode($captchaId);
    
    if ($expectedAnswer === false) {
        return false;
    }
    
    // Parse the expected answer (format: "num1 operator num2 = answer")
    if (preg_match('/(\d+)\s*([+\-*])\s*(\d+)\s*=\s*(\d+)/', $expectedAnswer, $matches)) {
        $num1 = intval($matches[1]);
        $operator = $matches[2];
        $num2 = intval($matches[3]);
        $expectedResult = intval($matches[4]);
        
        // Calculate the actual result
        $actualResult = 0;
        switch ($operator) {
            case '+':
                $actualResult = $num1 + $num2;
                break;
            case '-':
                $actualResult = $num1 - $num2;
                break;
            case '*':
                $actualResult = $num1 * $num2;
                break;
        }
        
        // Check if the expected result matches the actual result and user answer
        return ($expectedResult === $actualResult) && (intval($userAnswer) === $actualResult);
    }
    
    return false;
}

try {
    require_once '../config/database.php';
    require_once 'rate_limit.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check rate limit before processing
    $rateLimitCheck = checkRateLimit('register');
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => $rateLimitCheck['message'],
            'retry_after' => $rateLimitCheck['retry_after'] ?? 0
        ]);
        exit();
    }
    
    $username = $input['username'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $licenseKey = $input['license_key'] ?? '';
    $captchaAnswer = $input['captcha_answer'] ?? '';
    $captchaId = $input['captcha_id'] ?? '';
    $hwid = $input['hwid'] ?? '';
    
    // Validate input (HWID is optional for web registration)
    if (empty($username) || empty($email) || empty($password) || empty($licenseKey) || empty($captchaAnswer) || empty($captchaId)) {
        throw new Exception('All fields are required');
    }
    
    // Validate CAPTCHA
    if (!validateCaptcha($captchaId, $captchaAnswer)) {
        recordFailedAttempt('register');
        throw new Exception('Invalid CAPTCHA answer');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }
    
    // Check if license key exists and is available
    $stmt = $pdo->prepare("SELECT id, user_id, is_active FROM license_keys WHERE license_key = ?");
    $stmt->execute([$licenseKey]);
    $key = $stmt->fetch();
    
    if (!$key) {
        throw new Exception('Invalid license key');
    }
    
    if ($key['user_id'] !== null) {
        throw new Exception('License key is already assigned');
    }
    
    if (!$key['is_active']) {
        throw new Exception('License key is inactive');
    }
    
    // Get license key details including creation and expiration dates
    $stmt = $pdo->prepare("SELECT expires_at, created_at FROM license_keys WHERE license_key = ?");
    $stmt->execute([$licenseKey]);
    $keyDetails = $stmt->fetch();
    
    if (!$keyDetails) {
        throw new Exception('License key not found');
    }
    
    $originalExpiry = $keyDetails['expires_at'];
    $keyCreatedAt = $keyDetails['created_at'];
    
    // If the key has an expiration date, check if it's expired
    if ($originalExpiry && strtotime($originalExpiry) < time()) {
        throw new Exception('License key has expired');
    }
    
    // Calculate new expiration date based on registration time
    $newExpiryDate = null;
    if ($originalExpiry) {
        // Calculate the duration between key creation and original expiry
        $keyCreatedTimestamp = strtotime($keyCreatedAt);
        $originalExpiryTimestamp = strtotime($originalExpiry);
        $durationSeconds = $originalExpiryTimestamp - $keyCreatedTimestamp;
        
        // Set new expiry date starting from registration time
        $newExpiryDate = date('Y-m-d H:i:s', time() + $durationSeconds);
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Create user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, license_key_id, hwid, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $email, $hashedPassword, $key['id'], $hwid ?: null]);
        $userId = $pdo->lastInsertId();
        
        // Assign license key to user and update expiration date
        $stmt = $pdo->prepare("
            UPDATE license_keys 
            SET user_id = ?, expires_at = ?, updated_at = NOW() 
            WHERE license_key = ?
        ");
        $stmt->execute([$userId, $newExpiryDate, $licenseKey]);
        
        $pdo->commit();
        
        // Record successful registration attempt
        recordSuccessfulAttempt('register');
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user_id' => $userId,
                'username' => $username,
                'email' => $email,
                'license_key' => $licenseKey,
                'expires_at' => $newExpiryDate
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Registration error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Log PHP errors
    error_log("Registration PHP error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>