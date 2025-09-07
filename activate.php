<?php
require_once 'config.php';

// Only station managers can access this page
if (!isLoggedIn() || isDriverLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
if (!$user || $user['user_type'] !== 'station_manager') {
    header('Location: dashboard.php');
    exit();
}

// If system is already activated, redirect to dashboard
if (isSystemActivated()) {
    header('Location: dashboard.php');
    exit();
}

$current_page = 'activate';
$page_title = 'Activate FDMS - Fleet & Driver Management System';
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate'])) {
    $product_key = strtoupper(trim($_POST['product_key']));
    
    if (empty($product_key)) {
        $error_message = 'Please enter a product key.';
    } else {
        $result = activateSystem($product_key, $user['id']);
        
        if ($result['status'] === 'success') {
            $success_message = $result['message'];
            // Redirect to dashboard after 2 seconds
            header("refresh:2;url=dashboard.php");
        } else {
            $error_message = $result['message'];
        }
    }
}
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            min-height: 100vh;
        }
        .activation-card {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: none;
            border-radius: 15px;
        }
        .key-input {
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
            text-align: center;
            letter-spacing: 2px;
        }
        .key-format {
            color: #6c757d;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .info-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6">
                <div class="card activation-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-shield-alt fa-4x text-danger mb-3"></i>
                            <h2 class="text-danger">System Activation Required</h2>
                            <p class="text-muted">Please enter your product key to activate the Fleet & Driver Management System</p>
                            <div class="badge bg-primary">
                                <i class="fas fa-user-shield me-1"></i>
                                Station Manager: <?php echo htmlspecialchars($user['username']); ?>
                            </div>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                                <div class="mt-2">
                                    <div class="spinner-border spinner-border-sm me-2" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    Redirecting to dashboard...
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="activationForm">
                                <div class="mb-4">
                                    <label for="product_key" class="form-label">Product Key</label>
                                    <input type="text" class="form-control form-control-lg key-input" 
                                           id="product_key" name="product_key" 
                                           placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"
                                           maxlength="29" required>
                                    <div class="form-text key-format">
                                        Format: XXXX-XXXX-XXXX-XXXX-XXXX-XXXX
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="activate" class="btn btn-danger btn-lg" id="activateBtn">
                                        <i class="fas fa-unlock me-2"></i>
                                        Activate System
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="info-section">
                            <h6><i class="fas fa-info-circle text-primary me-2"></i>Important Information</h6>
                            <ul class="mb-0 small">
                                <li>Each product key can only be used once</li>
                                <li>Internet connection is required for activation</li>
                                <li>Only Station Managers can activate the system</li>
                                <li>All users will gain access once activation is complete</li>
                            </ul>
                        </div>

                        <div class="mt-4 text-center">
                            <a href="logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-1"></i>
                                Logout
                            </a>
                        </div>

                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-headset me-1"></i>
                                Need help? Contact support at support@fdms-cms.de
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format product key input
        document.getElementById('product_key').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^A-Z0-9]/g, '');
            let formatted = value.match(/.{1,4}/g)?.join('-') || value;
            if (formatted.length <= 29) {
                e.target.value = formatted.toUpperCase();
            }
        });

        // Handle form submission
        document.getElementById('activationForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('activateBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Activating...';
            btn.disabled = true;
        });

        // Auto-focus on product key input
        document.getElementById('product_key').focus();
    </script>
</body>
</html>