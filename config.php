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

function isSystemActivated() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_activation WHERE status = 'active'");
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getActivationInfo() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM system_activation WHERE status = 'active' ORDER BY activated_at DESC LIMIT 1");
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

function validateProductKey($key) {
    $key = strtoupper(trim($key));
    
    // Validate format (6 segments of 4 characters each)
    if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key)) {
        return ['status' => 'error', 'message' => 'Invalid product key format'];
    }
    
    // Check if key is already used locally
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM system_activation WHERE product_key = ?");
        $stmt->execute([$key]);
        if ($stmt->fetch()) {
            return ['status' => 'error', 'message' => 'This product key has already been used'];
        }
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database error during validation'];
    }
    
    // Validate with remote server
    $postData = json_encode([
        'key' => $key,
        'action' => 'validate'
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $postData,
            'timeout' => 30
        ]
    ]);
    
    $response = @file_get_contents('https://fdms-cms.de/activation.php', false, $context);
    
    if ($response === false) {
        return ['status' => 'error', 'message' => 'Unable to connect to activation server. Please check your internet connection.'];
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        return ['status' => 'error', 'message' => 'Invalid response from activation server'];
    }
    
    return $result;
}

function activateSystem($key, $user_id) {
    global $pdo;
    
    $validation = validateProductKey($key);
    if ($validation['status'] !== 'success') {
        return $validation;
    }
    
    try {
        // Store activation in database
        $system_info = json_encode([
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'php_version' => PHP_VERSION,
            'activation_time' => date('Y-m-d H:i:s')
        ]);
        
        $stmt = $pdo->prepare("INSERT INTO system_activation (product_key, activated_by, system_info) VALUES (?, ?, ?)");
        $stmt->execute([$key, $user_id, $system_info]);
        
        return ['status' => 'success', 'message' => 'System activated successfully!'];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database error during activation'];
    }
}

// Update existing requireLogin() function
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
    
    // Check system activation
    if (!isSystemActivated()) {
        $user = getCurrentUser();
        if ($user && $user['user_type'] === 'station_manager') {
            // Station managers can access activation page
            if (basename($_SERVER['PHP_SELF']) !== 'activate.php') {
                header('Location: activate.php');
                exit();
            }
        } else {
            // Other users get blocked
            header('Location: activation_required.php');
            exit();
        }
    }
    
    // If a driver tries to access management pages, redirect them
    if (isDriverLoggedIn()) {
        header('Location: driver_dashboard.php');
        exit();
    }
}

// Update existing requireDriverLogin() function
function requireDriverLogin() {
    if (!isLoggedIn() || !isDriverLoggedIn()) {
        header('Location: index.php');
        exit();
    }
    
    // Check system activation for drivers
    if (!isSystemActivated()) {
        header('Location: activation_required.php');
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