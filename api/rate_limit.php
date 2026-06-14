<?php
/**
 * Rate Limiting Functions
 * Prevents brute force attacks by limiting requests per IP
 * Uses file-based storage as fallback when APCu is not available
 */

// Rate limiting configuration
define('RATE_LIMIT_WINDOW', 300); // 5 minutes
define('RATE_LIMIT_MAX_ATTEMPTS', 5); // Max attempts per window
define('RATE_LIMIT_BLOCK_DURATION', 900); // 15 minutes block
define('RATE_LIMIT_DIR', __DIR__ . '/../rate_limit_data/');

// Create rate limit directory if it doesn't exist
if (!is_dir(RATE_LIMIT_DIR)) {
    mkdir(RATE_LIMIT_DIR, 0755, true);
}

function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function getRateLimitFile($ip, $action) {
    $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
    return RATE_LIMIT_DIR . "rate_limit_{$action}_{$safeIp}.json";
}

function getBlockFile($ip) {
    $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
    return RATE_LIMIT_DIR . "blocked_{$safeIp}.json";
}

function checkRateLimit($action) {
    $ip = getClientIP();
    
    // Check if IP is blocked
    $blockFile = getBlockFile($ip);
    if (file_exists($blockFile)) {
        $blockData = json_decode(file_get_contents($blockFile), true);
        if ($blockData && $blockData['expires'] > time()) {
            return [
                'allowed' => false,
                'message' => 'Too many failed attempts. Please try again in ' . ceil(($blockData['expires'] - time()) / 60) . ' minutes.',
                'retry_after' => $blockData['expires'] - time()
            ];
        } else {
            unlink($blockFile);
        }
    }
    
    // Check current rate limit
    $rateFile = getRateLimitFile($ip, $action);
    $currentData = null;
    
    if (file_exists($rateFile)) {
        $currentData = json_decode(file_get_contents($rateFile), true);
    }
    
    if (!$currentData) {
        // First request in window
        $currentData = [
            'count' => 1,
            'window_start' => time()
        ];
        file_put_contents($rateFile, json_encode($currentData));
        
        return ['allowed' => true];
    }
    
    // Check if window has expired
    if (time() - $currentData['window_start'] > RATE_LIMIT_WINDOW) {
        // Reset window
        $currentData = [
            'count' => 1,
            'window_start' => time()
        ];
        file_put_contents($rateFile, json_encode($currentData));
        
        return ['allowed' => true];
    }
    
    // Check if limit exceeded
    if ($currentData['count'] >= RATE_LIMIT_MAX_ATTEMPTS) {
        return [
            'allowed' => false,
            'message' => 'Too many requests. Please try again in ' . ceil((RATE_LIMIT_WINDOW - (time() - $currentData['window_start'])) / 60) . ' minutes.',
            'retry_after' => RATE_LIMIT_WINDOW - (time() - $currentData['window_start'])
        ];
    }
    
    // Increment counter
    $currentData['count']++;
    file_put_contents($rateFile, json_encode($currentData));
    
    return ['allowed' => true];
}

function recordFailedAttempt($action) {
    $ip = getClientIP();
    $rateFile = getRateLimitFile($ip, $action);
    
    if (file_exists($rateFile)) {
        $currentData = json_decode(file_get_contents($rateFile), true);
        if ($currentData && $currentData['count'] >= RATE_LIMIT_MAX_ATTEMPTS) {
            // Block the IP
            $blockFile = getBlockFile($ip);
            $blockData = [
                'expires' => time() + RATE_LIMIT_BLOCK_DURATION,
                'reason' => 'too_many_failed_attempts'
            ];
            file_put_contents($blockFile, json_encode($blockData));
        }
    }
}

function recordSuccessfulAttempt($action) {
    $ip = getClientIP();
    $rateFile = getRateLimitFile($ip, $action);
    $blockFile = getBlockFile($ip);
    
    // Remove rate limit file
    if (file_exists($rateFile)) {
        unlink($rateFile);
    }
    
    // Remove block file
    if (file_exists($blockFile)) {
        unlink($blockFile);
    }
}

// Cleanup old files (run occasionally)
function cleanupRateLimitFiles() {
    $files = glob(RATE_LIMIT_DIR . '*.json');
    $now = time();
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['expires']) && $data['expires'] < $now) {
            unlink($file);
        } elseif ($data && isset($data['window_start']) && ($now - $data['window_start']) > RATE_LIMIT_WINDOW) {
            unlink($file);
        }
    }
}

// Run cleanup occasionally (10% chance)
if (rand(1, 10) === 1) {
    cleanupRateLimitFiles();
}
?>
