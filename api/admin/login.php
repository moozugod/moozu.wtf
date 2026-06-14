<?php
/**
 * Admin Login API
 * Authenticates admin users against database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

try {
    require_once '../../config/database.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // First, try to authenticate using config constants (fallback)
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        // Get or create admin user
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = 'moozu'");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user) {
            // Create admin user if it doesn't exist
            $hashedPassword = password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, is_active, created_at) 
                VALUES ('moozu', 'admin@moozu.wtf', ?, TRUE, NOW())
            ");
            $stmt->execute([$hashedPassword]);
            $userId = $pdo->lastInsertId();
            $user = ['id' => $userId, 'username' => 'moozu', 'email' => 'admin@moozu.wtf'];
        } else {
            // Update admin password hash to match config
            $hashedPassword = password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'moozu'");
            $stmt->execute([$hashedPassword]);
        }
    } else {
        // Check if user exists and is admin (username = 'moozu')
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('Invalid credentials');
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid credentials');
        }
        
        // Check if this is the admin user (username must be 'moozu')
        if ($user['username'] !== 'moozu') {
            throw new Exception('Access denied. Admin privileges required.');
        }
    }
    
    // Generate admin token
    $token = base64_encode(json_encode([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'is_admin' => true,
        'exp' => time() + (24 * 60 * 60) // 24 hours
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
                'is_admin' => true
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Admin login error: " . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Admin login PHP error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>