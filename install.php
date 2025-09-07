<?php
// Fleet & Driver Management System (FDMS) Installation Script with Activation
// Run this file once to set up the system

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$message = '';
$error = '';

// Step 1: Check requirements
if ($step === 1) {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'GD Extension (for image handling)' => extension_loaded('gd'),
        'OpenSSL Extension (for encryption)' => extension_loaded('openssl'),
        'cURL Extension (for activation)' => extension_loaded('curl'),
        'File Uploads Enabled' => ini_get('file_uploads'),
        'allow_url_fopen Enabled' => ini_get('allow_url_fopen'),
        'Uploads Directory Writable' => is_writable('.') || mkdir('uploads', 0777, true)
    ];
}

// Step 2: Database configuration
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    
    try {
        // Test connection
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
        $pdo->exec("USE `$db_name`");
        
        // Create config file with activation functions
        $config_content = "<?php
// Database configuration
define('DB_HOST', '$db_host');
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');

// Create PDO connection
try {
    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8\", DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException \$e) {
    die(\"Database connection failed: \" . \$e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset(\$_SESSION['user_id']);
}

// Check if driver is logged in
function isDriverLoggedIn() {
    return isset(\$_SESSION['user_type']) && \$_SESSION['user_type'] === 'driver';
}

// Get current user data (for management users)
function getCurrentUser() {
    global \$pdo;
    if (isLoggedIn() && !isDriverLoggedIn()) {
        \$stmt = \$pdo->prepare(\"SELECT u.*, s.station_code, s.station_name FROM users u LEFT JOIN stations s ON u.station_id = s.id WHERE u.id = ?\");
        \$stmt->execute([\$_SESSION['user_id']]);
        return \$stmt->fetch();
    }
    return null;
}

// Get current driver data (for driver users)
function getCurrentDriver() {
    global \$pdo;
    if (isDriverLoggedIn()) {
        \$stmt = \$pdo->prepare(\"SELECT d.*, s.station_code, s.station_name FROM drivers d LEFT JOIN stations s ON d.station_id = s.id WHERE d.id = ?\");
        \$stmt->execute([\$_SESSION['driver_id']]);
        return \$stmt->fetch();
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
function hasPermission(\$required_role = null) {
    if (isDriverLoggedIn()) {
        return \$required_role === 'driver';
    }
    
    \$user = getCurrentUser();
    if (!\$user) return false;
    
    if (\$required_role && \$user['user_type'] !== \$required_role) {
        return false;
    }
    return true;
}

// System activation functions
function isSystemActivated() {
    global \$pdo;
    try {
        \$stmt = \$pdo->query(\"SELECT COUNT(*) FROM system_activation WHERE status = 'active'\");
        return \$stmt->fetchColumn() > 0;
    } catch (PDOException \$e) {
        return false;
    }
}

function getActivationInfo() {
    global \$pdo;
    try {
        \$stmt = \$pdo->query(\"SELECT * FROM system_activation WHERE status = 'active' ORDER BY activated_at DESC LIMIT 1\");
        return \$stmt->fetch();
    } catch (PDOException \$e) {
        return null;
    }
}

function validateProductKey(\$key) {
    \$key = strtoupper(trim(\$key));
    
    // Validate format (6 segments of 4 characters each)
    if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}\$/', \$key)) {
        return ['status' => 'error', 'message' => 'Invalid product key format'];
    }
    
    // Check if key is already used locally
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare(\"SELECT * FROM system_activation WHERE product_key = ?\");
        \$stmt->execute([\$key]);
        if (\$stmt->fetch()) {
            return ['status' => 'error', 'message' => 'This product key has already been used'];
        }
    } catch (PDOException \$e) {
        return ['status' => 'error', 'message' => 'Database error during validation'];
    }
    
    // Validate with remote server
    \$postData = json_encode([
        'key' => \$key,
        'action' => 'validate'
    ]);
    
    \$context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => \$postData,
            'timeout' => 30
        ]
    ]);
    
    \$response = @file_get_contents('https://fdms-cms.de/activation.php', false, \$context);
    
    if (\$response === false) {
        // Try cURL fallback
        if (function_exists('curl_init')) {
            \$ch = curl_init();
            curl_setopt(\$ch, CURLOPT_URL, 'https://fdms-cms.de/activation.php');
            curl_setopt(\$ch, CURLOPT_POST, true);
            curl_setopt(\$ch, CURLOPT_POSTFIELDS, \$postData);
            curl_setopt(\$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(\$ch, CURLOPT_TIMEOUT, 30);
            curl_setopt(\$ch, CURLOPT_SSL_VERIFYPEER, true);
            
            \$response = curl_exec(\$ch);
            \$curl_error = curl_error(\$ch);
            curl_close(\$ch);
            
            if (\$response === false) {
                return ['status' => 'error', 'message' => 'Unable to connect to activation server: ' . \$curl_error];
            }
        } else {
            return ['status' => 'error', 'message' => 'Unable to connect to activation server. Please check your internet connection.'];
        }
    }
    
    \$result = json_decode(\$response, true);
    
    if (!\$result) {
        return ['status' => 'error', 'message' => 'Invalid response from activation server'];
    }
    
    return \$result;
}

function activateSystem(\$key, \$user_id) {
    global \$pdo;
    
    \$validation = validateProductKey(\$key);
    if (\$validation['status'] !== 'success') {
        return \$validation;
    }
    
    try {
        // Store activation in database
        \$system_info = json_encode([
            'server_name' => \$_SERVER['SERVER_NAME'] ?? 'unknown',
            'user_agent' => \$_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => \$_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'php_version' => PHP_VERSION,
            'activation_time' => date('Y-m-d H:i:s'),
            'installed_via' => 'installer'
        ]);
        
        \$stmt = \$pdo->prepare(\"INSERT INTO system_activation (product_key, activated_by, system_info) VALUES (?, ?, ?)\");
        \$stmt->execute([\$key, \$user_id, \$system_info]);
        
        return ['status' => 'success', 'message' => 'System activated successfully!'];
    } catch (PDOException \$e) {
        return ['status' => 'error', 'message' => 'Database error during activation'];
    }
}

// Redirect if not logged in (for management users)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
    
    // Check system activation
    if (!isSystemActivated()) {
        \$user = getCurrentUser();
        if (\$user && \$user['user_type'] === 'station_manager') {
            // Station managers can access activation page
            if (basename(\$_SERVER['PHP_SELF']) !== 'activate.php') {
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

// Redirect if not logged in as driver
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
\$directories = [
    UPLOAD_DIR,
    UPLOAD_DIR . 'vans/',
    UPLOAD_DIR . 'vans/images/',
    UPLOAD_DIR . 'vans/videos/',
    UPLOAD_DIR . 'vans/documents/',
    UPLOAD_DIR . 'drivers/',
    UPLOAD_DIR . 'drivers/pictures/',
    UPLOAD_DIR . 'drivers/documents/'
];

foreach (\$directories as \$dir) {
    if (!file_exists(\$dir)) {
        mkdir(\$dir, 0777, true);
    }
}
?>";
        
        file_put_contents('config.php', $config_content);
        $step = 3;
        $message = 'Database connection successful! Configuration file created.';
        
    } catch (PDOException $e) {
        $error = 'Database connection failed: ' . $e->getMessage();
    }
}

// Step 3: Install database schema
if ($step === 3 && isset($_GET['install_db'])) {
    require_once 'config.php';
    
    try {
        // Read and execute SQL schema (including system_activation table)
        $sql = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100),
            last_name VARCHAR(100) NOT NULL,
            user_type ENUM('dispatcher', 'station_manager') NOT NULL,
            station_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        -- Stations table
        CREATE TABLE stations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            station_code VARCHAR(10) UNIQUE NOT NULL,
            station_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        -- Vans table
        CREATE TABLE vans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_plate VARCHAR(20) UNIQUE NOT NULL,
            vin_number VARCHAR(17) UNIQUE NOT NULL,
            make VARCHAR(50) NOT NULL,
            model VARCHAR(50) NOT NULL,
            station_id INT NOT NULL,
            status ENUM('in_use', 'available', 'reserve') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
        );

        -- Van images table
        CREATE TABLE van_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
        );

        -- Van videos table
        CREATE TABLE van_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            video_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
        );

        -- Van documents table (registration documents)
        CREATE TABLE van_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            document_path VARCHAR(255) NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
        );

        -- Drivers table
        CREATE TABLE drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id VARCHAR(30) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100),
            last_name VARCHAR(100) NOT NULL,
            date_of_birth DATE,
            phone_number VARCHAR(20),
            address TEXT,
            hire_date DATE,
            profile_picture VARCHAR(255),
            station_id INT NOT NULL,
            van_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE SET NULL
        );

        -- Driver documents table
        CREATE TABLE driver_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            document_path VARCHAR(255) NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
        );

        -- Van maintenance records table
        CREATE TABLE van_maintenance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            user_id INT NOT NULL,
            maintenance_record TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Driver leaves table
        CREATE TABLE driver_leaves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            station_id INT NOT NULL,
            leave_type ENUM('paid', 'sick') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
            FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id),
            INDEX idx_dates (start_date, end_date),
            INDEX idx_station_date (station_id, start_date, end_date)
        );

        -- Add salary_account field to drivers table
        ALTER TABLE drivers ADD COLUMN salary_account VARCHAR(34) NULL AFTER address;

        -- Add index for salary_account if needed for searches
        CREATE INDEX idx_salary_account ON drivers(salary_account);

        -- Working Hours Database Schema
        -- Add this to your existing database

        -- Working hours submissions table
        CREATE TABLE IF NOT EXISTS working_hours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            station_id INT NOT NULL,
            work_date DATE NOT NULL,
            tour_number VARCHAR(7) NOT NULL,
            van_id INT NOT NULL,
            
            -- Kilometers
            km_start INT NOT NULL,
            km_end INT NOT NULL,
            km_total INT GENERATED ALWAYS AS (km_end - km_start) STORED,
            
            -- Times (stored as TIME type for easier calculations)
            scanner_login TIME NOT NULL,
            depo_departure TIME NOT NULL,
            first_delivery TIME NOT NULL,
            last_delivery TIME NOT NULL,
            depo_return TIME NOT NULL,
            break_minutes INT NOT NULL,
            total_minutes INT NOT NULL,
            
            -- Status and approval
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            rejection_reason TEXT NULL,
            
            -- Metadata
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Foreign key constraints
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
            FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            
            -- Indexes for performance
            INDEX idx_driver_date (driver_id, work_date),
            INDEX idx_station_date (station_id, work_date),
            INDEX idx_status (status),
            INDEX idx_work_date (work_date),
            
            -- Unique constraint to prevent duplicate submissions
            UNIQUE KEY unique_driver_date (driver_id, work_date)
        );

        -- Working hours edits log (to track changes made by management)
        CREATE TABLE IF NOT EXISTS working_hours_edits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            working_hours_id INT NOT NULL,
            edited_by INT NOT NULL,
            field_name VARCHAR(50) NOT NULL,
            old_value VARCHAR(100) NOT NULL,
            new_value VARCHAR(100) NOT NULL,
            edit_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (working_hours_id) REFERENCES working_hours(id) ON DELETE CASCADE,
            FOREIGN KEY (edited_by) REFERENCES users(id) ON DELETE CASCADE,
            
            INDEX idx_working_hours_id (working_hours_id),
            INDEX idx_edited_by (edited_by)
        );

        CREATE TABLE IF NOT EXISTS `system_activation` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `product_key` varchar(255) NOT NULL,
            `activated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `activated_by` int(11) DEFAULT NULL,
            `system_info` text DEFAULT NULL,
            `status` enum('active','inactive') DEFAULT 'active',
            PRIMARY KEY (`id`),
            UNIQUE KEY `product_key` (`product_key`),
            KEY `activated_by` (`activated_by`),
            FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        );
        ";
        
        $pdo->exec($sql);
        
        // Insert initial data
        $pdo->exec("INSERT IGNORE INTO stations (station_code, station_name) VALUES
            ('DRP4', 'DRP4 Station'),
            ('DHE1', 'DHE1 Station'),
            ('DHE4', 'DHE4 Station'),
            ('DHE6', 'DHE6 Station'),
            ('DBW1', 'DBW1 Station')");
        
        // Add foreign key constraint for users table
        try {
            $pdo->exec("ALTER TABLE users ADD FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Ignore if constraint already exists
        }
        
        $step = 4;
        $message = 'Database tables created successfully! System ready for activation.';
        
    } catch (Exception $e) {
        $error = 'Error creating database tables: ' . $e->getMessage();
    }
}

// Step 4: System Activation
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_system'])) {
    require_once 'config.php';
    
    $product_key = strtoupper(trim($_POST['product_key']));
    
    if (empty($product_key)) {
        $error = 'Please enter a product key.';
    } else {
        // Use the station manager ID from the inserted user
        $stmt = $pdo->query("SELECT id FROM users WHERE user_type = 'station_manager' LIMIT 1");
        $station_manager = $stmt->fetch();
        
        if ($station_manager) {
            $result = activateSystem($product_key, $station_manager['id']);
            
            if ($result['status'] === 'success') {
                $step = 5;
                $message = 'System activated successfully! Installation complete.';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'No station manager found for activation.';
        }
    }
}

// Step 5: Complete installation
if ($step === 5 && isset($_GET['finish'])) {
    // Delete install.php file
    if (file_exists(__FILE__)) {
        unlink(__FILE__);
    }
    
    // Redirect to index.php
    header('Location: index.php?installed=1');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FDMS Installation - Fleet & Driver Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .install-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .install-card { 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15); 
            border: none; 
            border-radius: 20px; 
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            max-width: 800px;
            width: 100%;
        }
        .logo-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .logo-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .progress-container {
            margin: 2rem 0;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            height: 4px;
            background: #e9ecef;
            z-index: 0;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: bold;
            border: 3px solid #fff;
        }
        .step.active .step-circle {
            background: #28a745;
            color: white;
        }
        .step.completed .step-circle {
            background: #20c997;
            color: white;
        }
        .btn-custom {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .key-input {
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
            text-align: center;
            letter-spacing: 2px;
        }
        .activation-section {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="logo-section">
                <i class="fas fa-truck logo-icon"></i>
                <h1 class="mb-0">FDMS Installation</h1>
                <p class="mb-0">Fleet & Driver Management System</p>
            </div>
            
            <div class="card-body p-5">
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle">1</div>
                        <small>Requirements</small>
                    </div>
                    <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle">2</div>
                        <small>Database</small>
                    </div>
                    <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle">3</div>
                        <small>Installation</small>
                    </div>
                    <div class="step <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle">4</div>
                        <small>Activation</small>
                    </div>
                    <div class="step <?php echo $step >= 5 ? 'active' : ''; ?>">
                        <div class="step-circle">5</div>
                        <small>Complete</small>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="content-section">
                    <?php if ($step === 1): ?>
                        <div class="text-center mb-4">
                            <h2><i class="fas fa-clipboard-check me-2"></i>System Requirements</h2>
                            <p class="text-muted">Checking if your server meets the requirements</p>
                        </div>
                        
                        <div class="requirements-list">
                            <?php foreach ($requirements as $requirement => $met): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 mb-2 border rounded bg-<?php echo $met ? 'success' : 'danger'; ?> bg-opacity-10">
                                    <span>
                                        <i class="fas fa-<?php echo $met ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                        <?php echo $requirement; ?>
                                    </span>
                                    <span class="badge bg-<?php echo $met ? 'success' : 'danger'; ?>">
                                        <?php echo $met ? 'OK' : 'Failed'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <?php if (array_product($requirements)): ?>
                                <a href="?step=2" class="btn btn-primary btn-lg btn-custom">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    Continue to Database Setup
                                </a>
                                <p class="text-success mt-3">
                                    <i class="fas fa-check-circle me-1"></i>
                                    All requirements met! Ready to proceed.
                                </p>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Requirements Not Met</h5>
                                    <p class="mb-0">Some requirements are not satisfied. Please contact your hosting provider to resolve these issues.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($step === 2): ?>
                        <div class="text-center mb-4">
                            <h2><i class="fas fa-database me-2"></i>Database Configuration</h2>
                            <p class="text-muted">Configure your database connection settings</p>
                        </div>
                        
                        <form method="POST" class="row g-4">
                            <div class="col-md-6">
                                <label for="db_host" class="form-label">
                                    <i class="fas fa-server me-1"></i>Database Host
                                </label>
                                <input type="text" class="form-control form-control-lg" id="db_host" name="db_host" value="localhost" required>
                                <div class="form-text">Usually 'localhost' for most hosting providers</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="db_name" class="form-label">
                                    <i class="fas fa-database me-1"></i>Database Name
                                </label>
                                <input type="text" class="form-control form-control-lg" id="db_name" name="db_name" value="van_fleet_management" required>
                                <div class="form-text">Will be created if it doesn't exist</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="db_user" class="form-label">
                                    <i class="fas fa-user me-1"></i>Database Username
                                </label>
                                <input type="text" class="form-control form-control-lg" id="db_user" name="db_user" value="root" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="db_pass" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Database Password
                                </label>
                                <input type="password" class="form-control form-control-lg" id="db_pass" name="db_pass">
                                <div class="form-text">Leave empty if no password is required</div>
                            </div>
                            
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary btn-lg btn-custom">
                                    <i class="fas fa-plug me-2"></i>Test Database Connection
                                </button>
                            </div>
                        </form>

                    <?php elseif ($step === 3): ?>
                        <div class="text-center mb-4">
                            <h2><i class="fas fa-cogs me-2"></i>Ready for Installation</h2>
                            <p class="text-muted">Everything is configured. Let's install the database schema and initial data.</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>What will be installed:</h5>
                            <ul class="mb-0">
                                <li>Database tables for users, stations, vehicles, and drivers</li>
                                <li>Working hours and leave management system</li>
                                <li>System activation table</li>
                                <li>Initial station data and default admin user</li>
                                <li>Upload directories for documents and images</li>
                            </ul>
                        </div>
                        
                        <div class="text-center">
                            <a href="?step=3&install_db=1" class="btn btn-success btn-lg btn-custom">
                                <i class="fas fa-download me-2"></i>Install Database
                            </a>
                        </div>

                    <?php elseif ($step === 4): ?>
                        <div class="text-center mb-4">
                            <h2><i class="fas fa-shield-alt me-2"></i>System Activation</h2>
                            <p class="text-muted">Enter your product key to activate the FDMS</p>
                        </div>
                        
                        <div class="activation-section">
                            <div class="text-center mb-4">
                                <i class="fas fa-key fa-3x mb-3"></i>
                                <h4>Product Key Required</h4>
                                <p class="mb-0">Your FDMS installation requires a valid product key to proceed.</p>
                            </div>
                            
                            <form method="POST">
                                <div class="mb-4">
                                    <label for="product_key" class="form-label text-white">Product Key</label>
                                    <input type="text" class="form-control form-control-lg key-input" 
                                           id="product_key" name="product_key" 
                                           placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"
                                           maxlength="29" required>
                                    <div class="form-text text-white-50">
                                        Format: XXXX-XXXX-XXXX-XXXX-XXXX-XXXX
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="activate_system" class="btn btn-light btn-lg btn-custom">
                                        <i class="fas fa-unlock me-2"></i>Activate System
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Default Login Credentials:</strong><br>
                            Username: <code>pnozharov</code><br>
                            Password: <code>Amazon2018!</code><br>
                            Role: Station Manager
                        </div>

                    <?php elseif ($step === 5): ?>
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                                <h2 class="text-success mt-3">Installation Complete!</h2>
                                <p class="text-muted">Your FDMS has been successfully installed and activated.</p>
                            </div>
                            
                            <div class="alert alert-success">
                                <h5><i class="fas fa-rocket me-2"></i>What's Next?</h5>
                                <ul class="text-start mb-0">
                                    <li>The install.php file will be automatically deleted</li>
                                    <li>You can log in with the station manager account</li>
                                    <li>Start adding stations, vehicles, and drivers</li>
                                    <li>Configure your fleet management settings</li>
                                </ul>
                            </div>
                            
                            <a href="?step=5&finish=1" class="btn btn-success btn-lg btn-custom">
                                <i class="fas fa-home me-2"></i>Launch FDMS
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format product key input
        const productKeyInput = document.getElementById('product_key');
        if (productKeyInput) {
            productKeyInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^A-Z0-9]/g, '');
                let formatted = value.match(/.{1,4}/g)?.join('-') || value;
                if (formatted.length <= 29) {
                    e.target.value = formatted.toUpperCase();
                }
            });
            
            productKeyInput.focus();
        }
    </script>
</body>
</html>