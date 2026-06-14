<?php
/**
 * Get Available Versions API
 * Returns list of available application versions for download
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

try {
    $token = $_GET['token'] ?? '';
    
    if (!$token) {
        throw new Exception('Token required');
    }
    
    // Decode token
    $decoded = json_decode(base64_decode($token), true);
    if (!$decoded || $decoded['exp'] < time()) {
        throw new Exception('Invalid or expired token');
    }
    
    // Define downloads directory
    $downloadsDir = '../downloads';
    
    if (!is_dir($downloadsDir)) {
        throw new Exception('Downloads directory not found');
    }
    
    // Get all version folders
    $versions = [];
    $folders = scandir($downloadsDir);
    
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') continue;
        
        $folderPath = $downloadsDir . '/' . $folder;
        if (is_dir($folderPath)) {
            // Check if night.zip exists in this folder
            $zipPath = $folderPath . '/night.zip';
            if (file_exists($zipPath)) {
                $versions[] = [
                    'version' => $folder,
                    'path' => $zipPath,
                    'size' => filesize($zipPath),
                    'modified' => filemtime($zipPath)
                ];
            }
        }
    }
    
    // Sort versions by modification time (newest first)
    usort($versions, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    echo json_encode([
        'success' => true,
        'message' => 'Versions retrieved successfully',
        'data' => [
            'versions' => $versions
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
