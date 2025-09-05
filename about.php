<?php
require_once 'config.php';

$current_page = 'about';
$page_title = 'About - Van Fleet Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="display-4 text-center mb-5">About Van Fleet Management</h1>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="fas fa-truck fa-2x text-primary mb-3"></i>
                                <h5>Fleet Management</h5>
                                <p>Our comprehensive system allows you to track and manage your entire van fleet with real-time status updates, detailed records, and efficient operations.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-success mb-3"></i>
                                <h5>Driver Management</h5>
                                <p>Easily manage driver assignments, track their information, and ensure optimal allocation of human resources across your fleet operations.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="fas fa-tools fa-2x text-warning mb-3"></i>
                                <h5>Maintenance Tracking</h5>
                                <p>Keep detailed maintenance records for all vehicles, schedule services, and maintain a complete history of repairs and upkeep for each van.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="fas fa-building fa-2x text-info mb-3"></i>
                                <h5>Multi-Station Support</h5>
                                <p>Manage multiple stations with role-based access control. Station managers can oversee all locations while dispatchers focus on their assigned stations.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-5">
                    <div class="card-body">
                        <h3>System Features</h3>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Real-time van status tracking (In Use, Available, Reserve)</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Comprehensive driver assignment system</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Detailed maintenance record keeping</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Multi-station management capabilities</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Role-based access control (Dispatchers & Station Managers)</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Image and video upload support for van documentation</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Responsive design for mobile and desktop use</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Advanced filtering and search capabilities</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body text-center">
                        <h4>Contact Information</h4>
                        <p class="text-muted">Developed by Pavel Nozharov - ByTrends Ltd</p>
                        <p>For support and inquiries, please contact your system administrator.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>