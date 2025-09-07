<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// If system is activated, redirect appropriately
if (isSystemActivated()) {
    if (isDriverLoggedIn()) {
        header('Location: driver_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Get user info
$user_data = null;
$user_type = '';
$user_name = '';

if (isDriverLoggedIn()) {
    $user_data = getCurrentDriver();
    $user_type = 'Driver';
    $user_name = $user_data ? $user_data['first_name'] . ' ' . $user_data['last_name'] : 'Driver';
} else {
    $user_data = getCurrentUser();
    $user_type = ucfirst(str_replace('_', ' ', $user_data['user_type'] ?? 'User'));
    $user_name = $user_data ? $user_data['username'] : 'User';
}

$current_page = 'activation_required';
$page_title = 'System Activation Required - FDMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #ffa726 0%, #ff7043 100%);
            min-height: 100vh;
        }
        .blocked-card {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: none;
            border-radius: 15px;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .countdown {
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
            color: #ff5722;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6">
                <div class="card blocked-card">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle fa-5x text-warning pulse mb-3"></i>
                            <h2 class="text-warning">System Not Activated</h2>
                            <p class="text-muted">The Fleet & Driver Management System has not been activated yet</p>
                        </div>

                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-circle fa-2x me-3"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($user_type); ?>:</strong> 
                                    <?php echo htmlspecialchars($user_name); ?>
                                    <?php if ($user_data && isset($user_data['station_code'])): ?>
                                        <br><small class="text-muted">Station: <?php echo htmlspecialchars($user_data['station_code']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <?php if (isDriverLoggedIn()): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-steering-wheel me-2"></i>
                                    <strong>Driver Access Suspended</strong>
                                    <p class="mb-0 mt-2">The system must be activated by a Station Manager before you can access your dashboard. Please contact your station manager to activate the system with a valid product key.</p>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-users-cog me-2"></i>
                                    <strong>Management Access Suspended</strong>
                                    <p class="mb-0 mt-2">The system must be activated by a Station Manager. As a <?php echo htmlspecialchars($user_type); ?>, you cannot access the system until activation is complete.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <i class="fas fa-key fa-2x text-primary mb-2"></i>
                                        <h6>Product Key Required</h6>
                                        <p class="small mb-0">A valid product key must be entered by a Station Manager</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <i class="fas fa-user-shield fa-2x text-success mb-2"></i>
                                        <h6>Station Manager</h6>
                                        <p class="small mb-0">Only Station Managers can activate the system</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-light">
                            <h6><i class="fas fa-lightbulb text-warning me-2"></i>What happens next?</h6>
                            <ul class="text-start mb-0">
                                <li>Contact your Station Manager to obtain a product key</li>
                                <li>Station Manager will enter the key to activate the system</li>
                                <li>Once activated, all users can access their dashboards</li>
                                <li>This is a one-time activation process</li>
                            </ul>
                        </div>

                        <div class="mt-4">
                            <button onclick="location.reload()" class="btn btn-primary me-2">
                                <i class="fas fa-sync-alt me-2"></i>
                                Check Again
                            </button>
                            <a href="logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </div>

                        <div class="mt-4">
                            <div class="countdown" id="autoRefresh">
                                Auto-refresh in: <span id="countdown">30</span> seconds
                            </div>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-headset me-1"></i>
                                Need immediate assistance? Contact support at support@fdms-cms.de
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh countdown
        let countdown = 30;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);

        // Check activation status periodically
        setInterval(function() {
            fetch(window.location.href, {
                method: 'HEAD'
            }).then(response => {
                if (response.redirected) {
                    window.location.reload();
                }
            }).catch(error => {
                console.log('Connection check failed:', error);
            });
        }, 15000); // Check every 15 seconds
    </script>
</body>
</html>