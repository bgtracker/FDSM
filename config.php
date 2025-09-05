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

// Check if driver is logged in
function isDriverLoggedIn() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'driver';
}

// Get current user data (for management users)
function getCurrentUser() {
    global $pdo;
    if (isLoggedIn() && !isDriverLoggedIn()) {
        $stmt = $pdo->prepare("SELECT u.*, s.station_code, s.station_name FROM users u LEFT JOIN stations s ON u.station_id = s.id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

// Get current driver data (for driver users)
function getCurrentDriver() {
    global $pdo;
    if (isDriverLoggedIn()) {
        $stmt = $pdo->prepare("SELECT d.*, s.station_code, s.station_name FROM drivers d LEFT JOIN stations s ON d.station_id = s.id WHERE d.id = ?");
        $stmt->execute([$_SESSION['driver_id']]);
        return $stmt->fetch();
    }
    return null;
}

// Get current user data (unified function for all user types)
function getCurrentUserData() {
    if (isDriverLoggedIn()) {
        return getCurrentDriver();
    } else {
        return getCurrentUser();
    }
}

// Check if user has permission
function hasPermission($required_role = null) {
    if (isDriverLoggedIn()) {
        return $required_role === 'driver';
    }
    
    $user = getCurrentUser();
    if (!$user) return false;
    
    if ($required_role && $user['user_type'] !== $required_role) {
        return false;
    }
    return true;
}

// Redirect if not logged in (for management users)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
    
    // If a driver tries to access management pages, redirect them
    if (isDriverLoggedIn()) {
        header('Location: driver_dashboard.php');
        exit();
    }
}

// Redirect if not logged in as driver
function requireDriverLogin() {
    if (!isLoggedIn() || !isDriverLoggedIn()) {
        header('Location: index.php');
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