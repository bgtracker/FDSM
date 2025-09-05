<?php
require_once 'config.php';

$current_page = 'home';
$page_title = 'Home - Van Fleet Management';
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
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 mb-4">Fleet & Driver Management System</h1>
                    <p class="lead mb-4">Efficiently manage your van fleet operations and drivers with our comprehensive CMS dashboard.</p>
                    <?php if (!isLoggedIn()): ?>
                        <a href="login.php" class="btn btn-light btn-lg me-3">Get Started</a>
                        <a href="about.php" class="btn btn-outline-light btn-lg">Learn More</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <?php if (isLoggedIn()): ?>
            <?php $user = getCurrentUser(); ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-success">
                        <h4>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h4>
                        <p class="mb-0">You are logged in as a <?php echo ucwords(str_replace('_', ' ', $user['user_type'])); ?>
                        <?php if ($user['station_code']): ?>
                            at station <?php echo htmlspecialchars($user['station_code']); ?>
                        <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">My Fleet</h5>
                            <p class="card-text">Manage your van fleet and track vehicle status.</p>
                            <a href="fleet.php" class="btn btn-primary">View Fleet</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                            <h5 class="card-title">Van Maintenance</h5>
                            <p class="card-text">Track and manage vehicle maintenance records.</p>
                            <a href="maintenance.php" class="btn btn-warning">View Maintenance</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-success mb-3"></i>
                            <h5 class="card-title">My Drivers</h5>
                            <p class="card-text">Manage driver assignments and information.</p>
                            <a href="drivers.php" class="btn btn-success">View Drivers</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-alt fa-3x text-danger mb-3"></i>
                            <h5 class="card-title">Manage Leaves</h5>
                            <p class="card-text">Track and manage driver paid and sick leaves.</p>
                            <a href="manage_leaves.php" class="btn btn-danger">Manage Leaves</a>
                        </div>
                    </div>
                </div>
                
                <?php if ($user['user_type'] === 'station_manager'): ?>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-building fa-3x text-info mb-3"></i>
                            <h5 class="card-title">Manage Stations</h5>
                            <p class="card-text">Create and manage stations across your network.</p>
                            <a href="stations.php" class="btn btn-info">Manage Stations</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h2 class="text-center mb-5">Key Features</h2>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Fleet Management</h5>
                            <p class="card-text">Track and manage your entire van fleet with real-time status updates.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Driver Management</h5>
                            <p class="card-text">Assign drivers to vehicles and manage driver information efficiently.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                            <h5 class="card-title">Maintenance Tracking</h5>
                            <p class="card-text">Keep detailed maintenance records for all vehicles in your fleet.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-alt fa-3x text-danger mb-3"></i>
                            <h5 class="card-title">Leave Management</h5>
                            <p class="card-text">Track paid and sick leaves for all drivers with calendar view.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-building fa-3x text-info mb-3"></i>
                            <h5 class="card-title">Multi-Station Support</h5>
                            <p class="card-text">Manage multiple stations with role-based access control.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>