<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'van_fleet_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT u.*, s.station_code, s.station_name FROM users u LEFT JOIN stations s ON u.station_id = s.id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

// Check if user has permission
function hasPermission($required_role = null) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    if ($required_role && $user['user_type'] !== $required_role) {
        return false;
    }
    return true;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Upload directory configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB for videos
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB for images

// Create upload directories if they don't exist
$directories = [
    UPLOAD_DIR,
    UPLOAD_DIR . 'vans/',
    UPLOAD_DIR . 'vans/images/',
    UPLOAD_DIR . 'vans/videos/',
    UPLOAD_DIR . 'vans/documents/',
    UPLOAD_DIR . 'drivers/',
    UPLOAD_DIR . 'drivers/pictures/',
    UPLOAD_DIR . 'drivers/documents/'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}
?>