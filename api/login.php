<?php
/**
 * User Login API
 * Authenticates users and returns JWT token
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
    if (empty($captchaId) || empty($userAnswer)) {
        return false;
    }
    
    // Decode the captcha ID to get the expected answer
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
    $rateLimitCheck = checkRateLimit('login');
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
    $password = $input['password'] ?? '';
    $captchaAnswer = $input['captcha_answer'] ?? '';
    $captchaId = $input['captcha_id'] ?? '';
    $hwid = $input['hwid'] ?? '';
    
    // Validate input (CAPTCHA is optional for C++ program)
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    // Validate CAPTCHA only if provided (web login)
    if (!empty($captchaAnswer) && !empty($captchaId)) {
        if (!validateCaptcha($captchaId, $captchaAnswer)) {
            recordFailedAttempt('login');
            throw new Exception('Invalid CAPTCHA answer');
        }
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Find user by username or email
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.password_hash, u.hwid, u.created_at,
               lk.license_key, lk.expires_at, lk.is_active as key_active
        FROM users u
        LEFT JOIN license_keys lk ON u.license_key_id = lk.id
        WHERE u.username = ? OR u.email = ?
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Invalid credentials');
    }
    
    // HWID validation FIRST (before password check for security)
    if (!empty($hwid)) {
        if (!empty($user['hwid']) && $user['hwid'] !== $hwid) {
            // Account is locked to different computer - fail immediately
            recordFailedAttempt('login');
            throw new Exception('Account is locked to a different computer. Contact admin for HWID reset.');
        }
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        recordFailedAttempt('login');
        throw new Exception('Invalid credentials');
    }
    
    // Set HWID on first successful login (only for C++ program)
    if (!empty($hwid) && empty($user['hwid'])) {
        $stmt = $pdo->prepare("UPDATE users SET hwid = ? WHERE id = ?");
        $stmt->execute([$hwid, $user['id']]);
    }
    // If no HWID provided (web login), allow login without HWID validation
    
    // Record successful login attempt
    recordSuccessfulAttempt('login');
    
    // Check if license key is still active
    if (!$user['key_active']) {
        throw new Exception('Your license key has been deactivated');
    }
    
    // Check if license key is expired
    if ($user['expires_at'] && strtotime($user['expires_at']) < time()) {
        throw new Exception('Your license key has expired');
    }
    
    // Generate JWT token
    $token = base64_encode(json_encode([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'license_key' => $user['license_key'],
        'exp' => time() + (7 * 24 * 60 * 60) // 7 days
    ]));
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'license_key' => $user['license_key'],
                'expires_at' => $user['expires_at']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Login error: " . $e->getMessage());
    
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Log PHP errors
    error_log("Login PHP error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
