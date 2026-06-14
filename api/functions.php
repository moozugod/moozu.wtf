<?php
require_once '../config/database.php';

/**
 * Utility Functions for Key-Based System
 */

/**
 * Generate JWT token
 */
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload['iat'] = time();
    $payload['exp'] = time() + (24 * 60 * 60); // 24 hours
    $payloadJson = json_encode($payload);

    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));

    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

/**
 * Verify JWT token
 */
function verifyJWT($token) {
    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

        if (!$header || !$payload) {
            return false;
        }

        $signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], JWT_SECRET, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if (!hash_equals($expectedSignature, $parts[2])) {
            return false;
        }

        if ($payload['exp'] < time()) {
            return false;
        }

        return $payload;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Validate input data
 */
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        $ruleParts = explode('|', $rule);
        
        foreach ($ruleParts as $rulePart) {
            if (strpos($rulePart, ':') !== false) {
                list($ruleName, $ruleValue) = explode(':', $rulePart, 2);
            } else {
                $ruleName = $rulePart;
                $ruleValue = null;
            }
            
            switch ($ruleName) {
                case 'required':
                    if (empty($value)) {
                        $errors[] = "$field is required";
                    }
                    break;
                    
                case 'email':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "$field must be a valid email";
                    }
                    break;
                    
                case 'min':
                    if (!empty($value) && strlen($value) < intval($ruleValue)) {
                        $errors[] = "$field must be at least $ruleValue characters";
                    }
                    break;
                    
                case 'max':
                    if (!empty($value) && strlen($value) > intval($ruleValue)) {
                        $errors[] = "$field must be at most $ruleValue characters";
                    }
                    break;
            }
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Send JSON response
 */
function sendResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
 * Get user by username or email
 */
function getUserByUsernameOrEmail($identifier) {
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$identifier, $identifier]);
    return $stmt->fetch();
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Authenticate user from token
 */
function authenticateUser() {
    $token = null;
    
    // Check Authorization header
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    // Check query parameters
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    // Check request body
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['token'])) {
            $token = $input['token'];
        }
    }
    
    if (!$token) {
        return false;
    }
    
    // Verify JWT token
    $payload = verifyJWT($token);
    if (!$payload) {
        return false;
    }
    
    // Get user from database
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    return $user;
}
?>