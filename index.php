<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isDriverLoggedIn()) {
        header('Location: driver_dashboard.php');
        exit();
    } else {
        header('Location: dashboard.php');
        exit();
    }
}

$current_page = 'home';
$page_title = 'Login - Van Fleet Management';
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-card {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
        .role-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            height: 100%;
        }
        .role-card:hover {
            transform: translateY(-5px);
            border-color: #007bff;
            box-shadow: 0 10px 20px rgba(0, 123, 255, 0.2);
        }
        .role-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .hero-section {
            padding: 50px 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card login-card">
                        <div class="card-body p-5">
                            <div class="text-center mb-5">
                                <i class="fas fa-truck fa-4x text-primary mb-3"></i>
                                <h2 class="display-6 mb-3">Fleet & Driver Management System</h2>
                                <p class="lead text-muted">Choose your login type to access the system</p>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="card role-card h-100" onclick="location.href='driver_login.php'">
                                        <div class="card-body text-center p-4">
                                            <i class="fas fa-user-tie role-icon text-success"></i>
                                            <h4 class="card-title mb-3">Driver Login</h4>
                                            <p class="card-text text-muted">
                                                Access your driver dashboard using your Driver ID
                                            </p>
                                            <div class="mt-3">
                                                <span class="badge bg-success fs-6 px-3 py-2">
                                                    <i class="fas fa-id-card me-1"></i>
                                                    Use Driver ID
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card role-card h-100" onclick="location.href='login.php'">
                                        <div class="card-body text-center p-4">
                                            <i class="fas fa-users-cog role-icon text-primary"></i>
                                            <h4 class="card-title mb-3">Management Login</h4>
                                            <p class="card-text text-muted">
                                                Dispatcher or Station Manager access to fleet management tools
                                            </p>
                                            <div class="mt-3">
                                                <span class="badge bg-primary fs-6 px-3 py-2">
                                                    <i class="fas fa-key me-1"></i>
                                                    Username & Password
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <a href="about.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-info-circle me-1"></i>Learn More About Our System
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>