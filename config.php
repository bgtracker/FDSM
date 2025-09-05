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
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'vans/')) {
    mkdir(UPLOAD_DIR . 'vans/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'vans/images/')) {
    mkdir(UPLOAD_DIR . 'vans/images/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'vans/videos/')) {
    mkdir(UPLOAD_DIR . 'vans/videos/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'vans/documents/')) {
    mkdir(UPLOAD_DIR . 'vans/documents/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'drivers/')) {
    mkdir(UPLOAD_DIR . 'drivers/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'drivers/pictures/')) {
    mkdir(UPLOAD_DIR . 'drivers/pictures/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'drivers/documents/')) {
    mkdir(UPLOAD_DIR . 'drivers/documents/', 0777, true);
}
?>