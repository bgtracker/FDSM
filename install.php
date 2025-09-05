<?php
// Fleet & Driver Management System (FDMS) Installation Script
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
        'File Uploads Enabled' => ini_get('file_uploads'),
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
        
        // Create config file
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
        // Read and execute SQL schema
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
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

        CREATE TABLE IF NOT EXISTS stations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            station_code VARCHAR(10) UNIQUE NOT NULL,
            station_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS vans (
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

        CREATE TABLE IF NOT EXISTS van_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS van_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            video_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS van_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            document_path VARCHAR(255) NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id VARCHAR(30) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100),
            last_name VARCHAR(100) NOT NULL,
            date_of_birth DATE,
            phone_number VARCHAR(20),
            address TEXT,
            salary_account VARCHAR(34) NULL,
            hire_date DATE,
            profile_picture VARCHAR(255),
            station_id INT NOT NULL,
            van_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS driver_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            document_path VARCHAR(255) NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS van_maintenance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            van_id INT NOT NULL,
            user_id INT NOT NULL,
            maintenance_record TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS driver_leaves (
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
        ";
        
        $pdo->exec($sql);
        
        // Insert initial data
        $pdo->exec("INSERT IGNORE INTO stations (station_code, station_name) VALUES
            ('DRP4', 'DRP4 Station'),
            ('DHE1', 'DHE1 Station'),
            ('DHE4', 'DHE4 Station'),
            ('DHE6', 'DHE6 Station'),
            ('DBW1', 'DBW1 Station')");
        
        $password_hash = password_hash('Amazon2018!', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT IGNORE INTO users (username, password, first_name, middle_name, last_name, user_type, station_id) VALUES
            ('pnozharov', ?, 'Pavel', 'Boitchev', 'Nozharov', 'station_manager', 1),
            ('vmarkov', ?, 'Vasko', 'Kalinov', 'Markov', 'dispatcher', 1)")->execute([$password_hash, $password_hash]);
        
        // Add foreign key constraint for users table
        try {
            $pdo->exec("ALTER TABLE users ADD FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Ignore if constraint already exists
        }
        
        $step = 4;
        $message = 'Database tables created successfully! Initial data inserted.';
        
    } catch (Exception $e) {
        $error = 'Error creating database tables: ' . $e->getMessage();
    }
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
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 25px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 0;
        }
        .step.active:not(:last-child)::after,
        .step.completed:not(:last-child)::after {
            background: #28a745;
        }
        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dee2e6;
            color: #6c757d;
            font-weight: bold;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }
        .step.active .step-circle {
            background: #007bff;
            color: white;
            transform: scale(1.1);
        }
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .feature-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: none;
            transition: transform 0.3s ease;
            color: #212529 !important; /* Ensure text is dark */
        }
        .feature-card h5, .feature-card p, .feature-card small, .feature-card strong, .feature-card div {
            color: #212529 !important; /* Force dark text for all elements */
        }
        .feature-card .text-muted {
            color: #6c757d !important; /* Proper muted color but still visible */
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .system-showcase {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        .role-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .role-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        .role-icon {
            font-size: 2rem;
            margin-right: 1rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .management-icon {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        .driver-icon {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid transparent;
        }
        .requirement-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .requirement-item.danger {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .btn-custom {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .success-animation {
            animation: slideInUp 0.6s ease-out;
        }
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="card install-card">
                        <!-- Logo Section -->
                        <div class="logo-section">
                            <i class="fas fa-truck logo-icon"></i>
                            <h1 class="display-5 mb-2">FDMS</h1>
                            <h4 class="mb-3">Fleet & Driver Management System</h4>
                            <p class="lead mb-0">Professional fleet management solution for modern transportation companies</p>
                        </div>

                        <div class="card-body p-5">
                            <!-- Progress Indicator -->
                            <div class="step-indicator">
                                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                                    <div class="step-circle">
                                        <?php if ($step > 1): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            1
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2"><small>Requirements</small></div>
                                </div>
                                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">
                                    <div class="step-circle">
                                        <?php if ($step > 2): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            2
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2"><small>Database</small></div>
                                </div>
                                <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">
                                    <div class="step-circle">
                                        <?php if ($step > 3): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            3
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2"><small>Installation</small></div>
                                </div>
                                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">
                                    <div class="step-circle">
                                        <?php if ($step >= 4): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            4
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2"><small>Complete</small></div>
                                </div>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($message): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Success:</strong> <?php echo htmlspecialchars($message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($step === 1): ?>
                                <!-- Welcome & System Overview -->
                                <div class="text-center mb-5">
                                    <h2 class="mb-4">Welcome to FDMS Installation</h2>
                                    <p class="lead text-muted">Let's set up your comprehensive fleet and driver management solution</p>
                                </div>

                                <!-- System Features Overview -->
                                <div class="system-showcase mb-4">
                                    <h3 class="text-center mb-4">
                                        <i class="fas fa-star me-2"></i>
                                        System Features Overview
                                    </h3>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="role-section">
                                                <div class="role-header">
                                                    <div class="role-icon management-icon">
                                                        <i class="fas fa-users-cog"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-1 text-muted">Management Portal</h5>
                                                        <small class="text-muted">Station Managers & Dispatchers</small>
                                                    </div>
                                                </div>
                                                <ul class="list-unstyled mb-0 text-muted">
                                                    <li><i class="fas fa-check text-success me-2"></i>Complete fleet management</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Driver assignment & tracking</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Vehicle maintenance records</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Leave management system</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Multi-station support</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Document management</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Role-based permissions</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-4">
                                            <div class="role-section">
                                                <div class="role-header">
                                                    <div class="role-icon driver-icon">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-1 text-muted">Driver Portal</h5>
                                                        <small class="text-muted">Self-Service Dashboard</small>
                                                    </div>
                                                </div>
                                                <ul class="list-unstyled mb-0 text-muted">
                                                    <li><i class="fas fa-check text-success me-2"></i>Personal dashboard</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Assigned vehicle info</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Document upload</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Profile management</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Work statistics</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Simple ID-based login</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Mobile-friendly design</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Technical Features -->
                                <div class="feature-grid">
                                    <div class="feature-card">
                                        <i class="fas fa-shield-alt feature-icon text-primary"></i>
                                        <h5>Secure & Reliable</h5>
                                        <p class="text-muted mb-0">Role-based access control, secure file uploads, and comprehensive validation</p>
                                    </div>
                                    <div class="feature-card">
                                        <i class="fas fa-mobile-alt feature-icon text-success"></i>
                                        <h5>Mobile Responsive</h5>
                                        <p class="text-muted mb-0">Works perfectly on desktop, tablet, and mobile devices</p>
                                    </div>
                                    <div class="feature-card">
                                        <i class="fas fa-chart-line feature-icon text-warning"></i>
                                        <h5>Analytics Ready</h5>
                                        <p class="text-muted mb-0">Built-in reporting and statistics with calendar-based leave management</p>
                                    </div>
                                    <div class="feature-card">
                                        <i class="fas fa-cloud-upload-alt feature-icon text-info"></i>
                                        <h5>Document Management</h5>
                                        <p class="text-muted mb-0">Comprehensive file upload system for vehicles and drivers</p>
                                    </div>
                                </div>

                                <!-- System Requirements -->
                                <h3 class="mb-4">
                                    <i class="fas fa-server me-2"></i>
                                    System Requirements Check
                                </h3>
                                
                                <div class="requirements-container">
                                    <?php foreach ($requirements as $requirement => $met): ?>
                                    <div class="requirement-item <?php echo $met ? 'success' : 'danger'; ?>">
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
                                            <p class="mb-0">Some requirements are not satisfied. Please contact your hosting provider or system administrator to resolve these issues before proceeding.</p>
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
                                
                                <div class="system-showcase">
                                    <h4 class="text-center mb-4">
                                        <i class="fas fa-list-check me-2"></i>
                                        What will be installed:
                                    </h4>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-table me-2 text-info"></i>Database Tables</h6>
                                            <ul class="list-unstyled ms-3">
                                                <li><i class="fas fa-check me-1 text-success"></i>Users & Authentication</li>
                                                <li><i class="fas fa-check me-1 text-success"></i>Stations Management</li>
                                                <li><i class="fas fa-check me-1 text-success"></i>Vehicle Fleet</li>
                                                <li><i class="fas fa-check me-1 text-success"></i>Driver Profiles</li>
                                                <li><i class="fas fa-check me-1 text-success"></i>Maintenance Records</li>
                                                <li><i class="fas fa-check me-1 text-success"></i>Leave Management</li>
                                                <li><i class="fas fa-check me-1 text-success"></i>Document Storage</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-data me-2 text-warning"></i>Initial Data</h6>
                                            <ul class="list-unstyled ms-3">
                                                <li><i class="fas fa-building me-1 text-primary"></i>5 Sample Stations (DRP4, DHE1, DHE4, DHE6, DBW1)</li>
                                                <li><i class="fas fa-folder me-1 text-secondary"></i>Upload Directory Structure</li>
                                                <li><i class="fas fa-shield-alt me-1 text-success"></i>Security Constraints</li>
                                                <li><i class="fas fa-cog me-1 text-info"></i>System Configuration</li>
                                            </ul>
                                            
                                            <div class="mt-3 p-3 bg-dark rounded">
                                                <h6 class="text-light mb-2">
                                                    <i class="fas fa-info-circle me-2"></i>Next Step
                                                </h6>
                                                <small class="text-light">
                                                    After database installation, you'll create<br>
                                                    your administrator account to manage FDMS
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <a href="?step=3&install_db=1" class="btn btn-success btn-lg btn-custom">
                                        <i class="fas fa-rocket me-2"></i>Install FDMS Database
                                    </a>
                                </div>

                            <?php elseif ($step === 4): ?>
                                <div class="text-center success-animation">
                                    <div class="mb-4">
                                        <i class="fas fa-check-circle" style="font-size: 5rem; color: #28a745;"></i>
                                    </div>
                                    <h2 class="text-success mb-4">🎉 Installation Complete!</h2>
                                    <p class="lead text-muted mb-4">FDMS has been successfully installed and is ready to use.</p>
                                </div>

                                <div class="system-showcase">
                                    <h4 class="text-center mb-4">
                                        <i class="fas fa-rocket me-2"></i>
                                        Your System is Ready!
                                    </h4>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="role-section">
                                                <div class="role-header">
                                                    <div class="role-icon management-icon">
                                                        <i class="fas fa-users-cog"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-1">Management Access</h5>
                                                        <small class="text-muted">Station Managers & Dispatchers</small>
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <strong>Usernames:</strong> pnozharov / vmarkov<br>
                                                    <strong>Password:</strong> Amazon2018!
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="role-section">
                                                <div class="role-header">
                                                    <div class="role-icon driver-icon">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-1">Driver Access</h5>
                                                        <small class="text-muted">Add drivers to enable login</small>
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <small class="text-muted">
                                                        Drivers login with their Driver ID<br>
                                                        (Password = Driver ID)
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-shield-alt me-2"></i>Important Security Note</h5>
                                    <p class="mb-2"><strong>Please delete this installation file (install.php) immediately for security reasons.</strong></p>
                                    <p class="mb-0">This file contains sensitive setup information and should not be accessible after installation.</p>
                                </div>
                                
                                <div class="text-center">
                                    <a href="index.php" class="btn btn-success btn-lg btn-custom me-3">
                                        <i class="fas fa-home me-2"></i>Launch FDMS
                                    </a>
                                    <a href="login.php" class="btn btn-primary btn-lg btn-custom">
                                        <i class="fas fa-sign-in-alt me-2"></i>Management Login
                                    </a>
                                </div>

                                <div class="text-center mt-4">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        For support and documentation, please refer to the system documentation or contact your administrator.
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactive enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Add loading state to form submissions
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                    }
                });
            });
            
            // Add smooth scroll behavior for single-page navigation
            document.documentElement.style.scrollBehavior = 'smooth';
        });
    </script>
</body>
</html>