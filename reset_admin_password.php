<?php
/**
 * Admin Password Reset Utility
 * Run this script to reset the admin password
 * Usage: php reset_admin_password.php [new_password]
 */

require_once 'config/database.php';

$newPassword = $argv[1] ?? ADMIN_PASSWORD;

if (empty($newPassword)) {
    echo "Error: Password cannot be empty\n";
    echo "Usage: php reset_admin_password.php [new_password]\n";
    echo "If no password is provided, will use ADMIN_PASSWORD from config\n";
    exit(1);
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'moozu'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Update existing admin password
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'moozu'");
        $stmt->execute([$hashedPassword]);
        echo "Admin password updated successfully!\n";
        echo "Username: moozu\n";
        echo "New password: " . $newPassword . "\n";
    } else {
        // Create admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, is_active, created_at) 
            VALUES ('moozu', 'admin@moozu.wtf', ?, TRUE, NOW())
        ");
        $stmt->execute([$hashedPassword]);
        echo "Admin user created successfully!\n";
        echo "Username: moozu\n";
        echo "Password: " . $newPassword . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

