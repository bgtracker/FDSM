<?php
// Van Fleet Management Installation Script
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

// Get current user data
function getCurrentUser() {
    global \$pdo;
    if (isLoggedIn()) {
        \$stmt = \$pdo->prepare(\"SELECT u.*, s.station_code, s.station_name FROM users u LEFT JOIN stations s ON u.station_id = s.id WHERE u.id = ?\");
        \$stmt->execute([\$_SESSION['user_id']]);
        return \$stmt->fetch();
    }
    return null;
}

// Check if user has permission
function hasPermission(\$required_role = null) {
    \$user = getCurrentUser();
    if (!\$user) return false;
    
    if (\$required_role && \$user['user_type'] !== \$required_role) {
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

        CREATE TABLE IF NOT EXISTS drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100),
            last_name VARCHAR(100) NOT NULL,
            station_id INT NOT NULL,
            van_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
            FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE SET NULL
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
    <title>Van Fleet Management - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-card { box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); border: none; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6">
                <div class="card install-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                            <h3>Van Fleet Management</h3>
                            <p class="text-muted">Installation Wizard</p>
                        </div>

                        <!-- Progress indicator -->
                        <div class="progress mb-4" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo ($step / 4) * 100; ?>%"></div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($step === 1): ?>
                            <h5>Step 1: System Requirements</h5>
                            <p class="text-muted">Checking your server configuration...</p>
                            
                            <div class="list-group mb-4">
                                <?php foreach ($requirements as $requirement => $met): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo $requirement; ?>
                                    <?php if ($met): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times"></i> Failed</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (array_product($requirements)): ?>
                                <a href="?step=2" class="btn btn-primary btn-lg w-100">
                                    Continue to Database Setup <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <strong>Some requirements are not met.</strong> Please contact your hosting provider or system administrator.
                                </div>
                            <?php endif; ?>

                        <?php elseif ($step === 2): ?>
                            <h5>Step 2: Database Configuration</h5>
                            <p class="text-muted">Enter your database connection details.</p>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Database Host</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Database Name</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" value="van_fleet_management" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Database Username</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Database Password</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass">
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    Test Connection <i class="fas fa-database ms-1"></i>
                                </button>
                            </form>

                        <?php elseif ($step === 3): ?>
                            <h5>Step 3: Database Installation</h5>
                            <p class="text-muted">Ready to create database tables and insert initial data.</p>
                            
                            <div class="alert alert-info">
                                <strong>What will be created:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Database tables (users, stations, vans, drivers, maintenance)</li>
                                    <li>5 initial stations (DRP4, DHE1, DHE4, DHE6, DBW1)</li>
                                    <li>2 test user accounts</li>
                                    <li>Upload directories</li>
                                </ul>
                            </div>
                            
                            <a href="?step=3&install_db=1" class="btn btn-primary btn-lg w-100">
                                Install Database <i class="fas fa-cog ms-1"></i>
                            </a>

                        <?php elseif ($step === 4): ?>
                            <h5>Step 4: Installation Complete!</h5>
                            <p class="text-muted">Van Fleet Management has been successfully installed.</p>
                            
                            <div class="alert alert-success">
                                <strong>Test Accounts Created:</strong>
                                <ul class="mb-0 mt-2">
                                    <li><strong>Station Manager:</strong> pnozharov / Amazon2018!</li>
                                    <li><strong>Dispatcher:</strong> vmarkov / Amazon2018!</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-warning">
                                <strong>Important:</strong> Please delete this installation file (install.php) for security reasons.
                            </div>
                            
                            <a href="index.php" class="btn btn-success btn-lg w-100">
                                Launch Van Fleet Management <i class="fas fa-rocket ms-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>