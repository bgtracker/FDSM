<?php
require_once 'config.php';
requireLogin();

$current_page = 'drivers';
$page_title = 'My Drivers - Van Fleet Management';
$user = getCurrentUser();

$message = '';

// Handle driver deletion
if (isset($_POST['delete_driver'])) {
    $driver_id = intval($_POST['driver_id']);
    
    try {
        // Check if user has permission to delete this driver
        if ($user['user_type'] === 'dispatcher') {
            $stmt = $pdo->prepare("SELECT id FROM drivers WHERE id = ? AND station_id = ?");
            $stmt->execute([$driver_id, $user['station_id']]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM drivers WHERE id = ?");
            $stmt->execute([$driver_id]);
        }
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM drivers WHERE id = ?");
            $stmt->execute([$driver_id]);
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Driver deleted successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Driver not found or access denied.</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error deleting driver.</div>';
    }
}

// Handle driver assignment
if (isset($_POST['assign_driver'])) {
    $driver_id = intval($_POST['driver_id']);
    $van_id = intval($_POST['van_id']);
    
    try {
        $pdo->beginTransaction();
        
        // Get current van assignment for this driver
        $stmt = $pdo->prepare("SELECT van_id FROM drivers WHERE id = ?");
        $stmt->execute([$driver_id]);
        $current_assignment = $stmt->fetch();
        $old_van_id = $current_assignment['van_id'];
        
        // If removing van assignment or changing van
        if ($old_van_id && ($van_id == 0 || $van_id != $old_van_id)) {
            // Set old van to available
            $stmt = $pdo->prepare("UPDATE vans SET status = 'available' WHERE id = ?");
            $stmt->execute([$old_van_id]);
            
            // Remove any other driver assignments from this van
            $stmt = $pdo->prepare("UPDATE drivers SET van_id = NULL WHERE van_id = ?");
            $stmt->execute([$old_van_id]);
        }
        
        // If assigning to a new van
        if ($van_id > 0) {
            // Remove any other drivers from the new van
            $stmt = $pdo->prepare("UPDATE drivers SET van_id = NULL WHERE van_id = ?");
            $stmt->execute([$van_id]);
            
            // Assign driver to new van
            $stmt = $pdo->prepare("UPDATE drivers SET van_id = ? WHERE id = ?");
            $stmt->execute([$van_id, $driver_id]);
            
            // Update van status to in_use
            $stmt = $pdo->prepare("UPDATE vans SET status = 'in_use' WHERE id = ?");
            $stmt->execute([$van_id]);
        } else {
            // Just remove assignment (van already set to available above)
            $stmt = $pdo->prepare("UPDATE drivers SET van_id = NULL WHERE id = ?");
            $stmt->execute([$driver_id]);
        }
        
        $pdo->commit();
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Driver assignment updated successfully.</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error updating driver assignment.</div>';
    }
}

// Filters
$driver_id_filter = isset($_GET['driver_id']) ? trim($_GET['driver_id']) : '';
$driver_name_filter = isset($_GET['driver_name']) ? trim($_GET['driver_name']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_conditions = [];
$params = [];

// Get drivers based on user type
if ($user['user_type'] === 'dispatcher') {
    $where_conditions[] = "d.station_id = ?";
    $params[] = $user['station_id'];
} 

// Add driver ID filter
if ($driver_id_filter) {
    $where_conditions[] = "d.driver_id LIKE ?";
    $params[] = "%$driver_id_filter%";
}

// Add driver name filter
if ($driver_name_filter) {
    $where_conditions[] = "(d.first_name LIKE ? OR d.middle_name LIKE ? OR d.last_name LIKE ? OR CONCAT(d.first_name, ' ', d.last_name) LIKE ?)";
    $params[] = "%$driver_name_filter%";
    $params[] = "%$driver_name_filter%";
    $params[] = "%$driver_name_filter%";
    $params[] = "%$driver_name_filter%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get count
$count_query = "SELECT COUNT(*) FROM drivers d $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();

$query = "SELECT d.*, s.station_code, s.station_name, v.license_plate as van_license
          FROM drivers d 
          LEFT JOIN stations s ON d.station_id = s.id 
          LEFT JOIN vans v ON d.van_id = v.id 
          $where_clause
          ORDER BY d.last_name, d.first_name 
          LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$drivers = $stmt->fetchAll();
$total_pages = ceil($total_records / $limit);

// Get available vans for assignment
if ($user['user_type'] === 'dispatcher') {
    $vans_query = "SELECT v.* FROM vans v WHERE v.station_id = ? AND (v.status = 'available' OR v.id IN (SELECT van_id FROM drivers WHERE station_id = ?))";
    $stmt = $pdo->prepare($vans_query);
    $stmt->execute([$user['station_id'], $user['station_id']]);
} else {
    $vans_query = "SELECT v.*, s.station_code FROM vans v LEFT JOIN stations s ON v.station_id = s.id WHERE v.status = 'available' OR v.id IN (SELECT van_id FROM drivers WHERE van_id IS NOT NULL)";
    $stmt = $pdo->prepare($vans_query);
    $stmt->execute();
}
$available_vans = $stmt->fetchAll();
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i>My Drivers</h2>
                    <a href="add_driver.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Driver
                    </a>
                </div>

                <?php echo $message; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="driver_id" class="form-label">Driver ID</label>
                                <input type="text" class="form-control" id="driver_id" name="driver_id" 
                                       value="<?php echo htmlspecialchars($driver_id_filter); ?>" placeholder="Enter driver ID">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="driver_name" class="form-label">Driver Name</label>
                                <input type="text" class="form-control" id="driver_name" name="driver_name" 
                                       value="<?php echo htmlspecialchars($driver_name_filter); ?>" placeholder="Enter driver name">
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="drivers.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Drivers List (<?php echo $total_records; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($drivers)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No drivers found</h5>
                                <p class="text-muted">Add your first driver to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Driver ID</th>
                                            <th>Name</th>
                                            <th>Station</th>
                                            <th>Assigned Van</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($drivers as $driver): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($driver['driver_id']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($driver['first_name'] . ' ' . ($driver['middle_name'] ? $driver['middle_name'] . ' ' : '') . $driver['last_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($driver['station_code']); ?></td>
                                            <td>
                                                <?php if ($driver['van_license']): ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($driver['van_license']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">No van assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <!-- View Driver Button -->
                                                    <a href="view_driver.php?id=<?php echo $driver['id']; ?>" class="btn btn-outline-info" title="View Driver">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <!-- Assign Van Button -->
                                                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#assignModal<?php echo $driver['id']; ?>" title="Assign Van">
                                                        <i class="fas fa-truck"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Button -->
                                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $driver['id']; ?>" title="Delete Driver">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>

                                                <!-- Assign Van Modal -->
                                                <div class="modal fade" id="assignModal<?php echo $driver['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Assign Van to <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label for="van_id<?php echo $driver['id']; ?>" class="form-label">Select Van</label>
                                                                        <select class="form-select" id="van_id<?php echo $driver['id']; ?>" name="van_id">
                                                                            <option value="0">Remove assignment</option>
                                                                            <?php foreach ($available_vans as $van): ?>
                                                                                <option value="<?php echo $van['id']; ?>" <?php echo $driver['van_id'] == $van['id'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($van['license_plate'] . ' (' . $van['make'] . ' ' . $van['model'] . ')'); ?>
                                                                                    <?php if (isset($van['station_code'])): ?>
                                                                                        - <?php echo htmlspecialchars($van['station_code']); ?>
                                                                                    <?php endif; ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="assign_driver" class="btn btn-success">Assign Van</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $driver['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete driver <strong><?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></strong>?</p>
                                                                <p class="text-muted">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                                                    <button type="submit" name="delete_driver" class="btn btn-danger">Delete Driver</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="drivers.php<?php 
                                            $prev_params = array_filter(['driver_id' => $driver_id_filter, 'driver_name' => $driver_name_filter]);
                                            if ($page > 2) $prev_params['page'] = $page - 1;
                                            echo $prev_params ? '?' . http_build_query($prev_params) : '';
                                        ?>">Previous</a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="drivers.php<?php 
                                            $page_params = array_filter(['driver_id' => $driver_id_filter, 'driver_name' => $driver_name_filter]);
                                            if ($i > 1) $page_params['page'] = $i;
                                            echo $page_params ? '?' . http_build_query($page_params) : '';
                                        ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="drivers.php<?php 
                                            $next_params = array_filter(['driver_id' => $driver_id_filter, 'driver_name' => $driver_name_filter, 'page' => $page + 1]);
                                            echo '?' . http_build_query($next_params);
                                        ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>