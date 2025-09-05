<?php
require_once 'config.php';
requireLogin();

$current_page = 'maintenance';
$page_title = 'Van Maintenance - Van Fleet Management';
$user = getCurrentUser();

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$vans = [];

if ($search_term) {
    // Search for vans by license plate
    if ($user['user_type'] === 'dispatcher') {
        $query = "SELECT v.*, s.station_code, s.station_name 
                  FROM vans v 
                  LEFT JOIN stations s ON v.station_id = s.id 
                  WHERE v.license_plate LIKE ? AND v.station_id = ?
                  ORDER BY v.license_plate";
        $stmt = $pdo->prepare($query);
        $stmt->execute(["%$search_term%", $user['station_id']]);
    } else {
        $query = "SELECT v.*, s.station_code, s.station_name 
                  FROM vans v 
                  LEFT JOIN stations s ON v.station_id = s.id 
                  WHERE v.license_plate LIKE ?
                  ORDER BY v.license_plate";
        $stmt = $pdo->prepare($query);
        $stmt->execute(["%$search_term%"]);
    }
    $vans = $stmt->fetchAll();
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
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-tools me-2"></i>Van Maintenance</h2>
                <p class="text-muted mb-4">Search for a van to view and manage maintenance records.</p>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Search Van</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <label for="search" class="form-label">License Plate</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search_term); ?>" 
                                       placeholder="Enter license plate number">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                                <a href="maintenance.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Search Results -->
                <?php if ($search_term): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Search Results</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($vans)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No vans found</h5>
                                    <p class="text-muted">Try searching with a different license plate number.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($vans as $van): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-truck me-1"></i>
                                                    <?php echo htmlspecialchars($van['license_plate']); ?>
                                                </h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($van['make'] . ' ' . $van['model']); ?><br>
                                                        Station: <?php echo htmlspecialchars($van['station_code']); ?><br>
                                                        Status: 
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        switch($van['status']) {
                                                            case 'available':
                                                                $status_class = 'success';
                                                                $status_text = 'Available';
                                                                break;
                                                            case 'in_use':
                                                                $status_class = 'primary';
                                                                $status_text = 'In Use';
                                                                break;
                                                            case 'reserve':
                                                                $status_class = 'warning';
                                                                $status_text = 'Reserve';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    </small>
                                                </p>
                                                <a href="van_maintenance_detail.php?id=<?php echo $van['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-tools me-1"></i>View Maintenance
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Instructions -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">Search for a Van</h4>
                            <p class="text-muted">Enter a license plate number above to find and manage van maintenance records.</p>
                            <div class="row mt-4">
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <i class="fas fa-search fa-2x text-primary mb-2"></i>
                                            <h6>Search Vans</h6>
                                            <small class="text-muted">Find vans by license plate number</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <i class="fas fa-history fa-2x text-success mb-2"></i>
                                            <h6>View History</h6>
                                            <small class="text-muted">Access complete maintenance history</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <i class="fas fa-plus fa-2x text-warning mb-2"></i>
                                            <h6>Add Records</h6>
                                            <small class="text-muted">Create new maintenance entries</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>