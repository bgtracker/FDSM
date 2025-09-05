<?php
require_once 'config.php';
requireDriverLogin();

$current_page = 'dashboard';
$page_title = 'Driver Dashboard - Van Fleet Management';
$driver = getCurrentDriver();
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 60px 0;
        }
        .welcome-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-5 mb-3">Welcome, <?php echo htmlspecialchars($driver['first_name']); ?>!</h1>
                    <p class="lead mb-4">Driver Dashboard - <?php echo htmlspecialchars($driver['driver_id']); ?></p>
                    <p class="mb-0">Station: <?php echo htmlspecialchars($driver['station_code']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card welcome-card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-truck fa-4x text-success mb-4"></i>
                        <h3 class="mb-3">Driver Portal</h3>
                        <p class="text-muted mb-4">
                            Welcome to your driver dashboard. This portal is currently being developed 
                            and will soon include features like:
                        </p>
                        
                        <div class="row mt-4">
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                                        <h6>Schedule & Leaves</h6>
                                        <small class="text-muted">View your schedule and request leaves</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <i class="fas fa-truck fa-2x text-warning mb-2"></i>
                                        <h6>Vehicle Information</h6>
                                        <small class="text-muted">View details about your assigned van</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <i class="fas fa-user fa-2x text-success mb-2"></i>
                                        <h6>Profile & Documents</h6>
                                        <small class="text-muted">Manage your profile and documents</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Coming Soon!</strong> More features will be available in future updates.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>