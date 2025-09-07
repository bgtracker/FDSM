<?php
require_once 'config.php';
requireLogin();

// Ensure only management users can access this dashboard
if (isDriverLoggedIn()) {
    header('Location: driver_dashboard.php');
    exit();
}

$user = getCurrentUser();
if (!$user) {
    header('Location: index.php');
    exit();
}

$current_page = 'dashboard';
$page_title = 'Management Dashboard - Van Fleet Management';
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
            padding: 80px 0;
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
                    <h1 class="display-4 mb-4">Fleet & Driver Management Dashboard</h1>
                    <p class="lead mb-4">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 
                    Manage your fleet operations efficiently from this central dashboard.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-primary">
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
            
            <div class="col-md-4 mb-4">
                <div class="card feature-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-3x text-info mb-3"></i>
                        <h5 class="card-title">Working Hours</h5>
                        <p class="card-text">Review and approve driver working hour submissions.</p>
                        <a href="manage_working_hours.php" class="btn btn-info">Review Hours</a>
                    </div>
                </div>
            </div>
            
            <?php if ($user['user_type'] === 'station_manager'): ?>
            <div class="col-md-4 mb-4">
                <div class="card feature-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-3x text-secondary mb-3"></i>
                        <h5 class="card-title">Manage Stations</h5>
                        <p class="card-text">Create and manage stations across your network.</p>
                        <a href="stations.php" class="btn btn-secondary">Manage Stations</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>