<?php
require_once 'config.php';
requireLogin();

$current_page = 'stations';
$page_title = 'Manage Stations - Van Fleet Management';
$user = getCurrentUser();

// Check if user is station manager
if ($user['user_type'] !== 'station_manager') {
    header('Location: index.php');
    exit();
}

$message = '';

// Handle station deletion
if (isset($_POST['delete_station'])) {
    $station_id = intval($_POST['station_id']);
    
    try {
        // Check if station has vans or users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vans WHERE station_id = ?");
        $stmt->execute([$station_id]);
        $van_count = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE station_id = ?");
        $stmt->execute([$station_id]);
        $user_count = $stmt->fetchColumn();
        
        if ($van_count > 0 || $user_count > 0) {
            $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Cannot delete station. It has ' . $van_count . ' vans and ' . $user_count . ' users assigned.</div>';
        } else {
            $stmt = $pdo->prepare("DELETE FROM stations WHERE id = ?");
            $stmt->execute([$station_id]);
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Station deleted successfully.</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error deleting station.</div>';
    }
}

// Handle adding new station
if (isset($_POST['add_station'])) {
    $station_code = strtoupper(trim($_POST['station_code']));
    $station_name = trim($_POST['station_name']);
    
    if (empty($station_code) || empty($station_name)) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Please fill in all fields.</div>';
    } else {
        try {
            // Check if station code already exists
            $stmt = $pdo->prepare("SELECT id FROM stations WHERE station_code = ?");
            $stmt->execute([$station_code]);
            if ($stmt->fetch()) {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Station code already exists.</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO stations (station_code, station_name) VALUES (?, ?)");
                $stmt->execute([$station_code, $station_name]);
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Station added successfully.</div>';
                
                // Clear form
                $_POST = [];
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error adding station.</div>';
        }
    }
}

// Get all stations with counts
$query = "SELECT s.*, 
                 (SELECT COUNT(*) FROM vans WHERE station_id = s.id) as van_count,
                 (SELECT COUNT(*) FROM users WHERE station_id = s.id) as user_count
          FROM stations s 
          ORDER BY s.station_code";
$stmt = $pdo->prepare($query);
$stmt->execute();
$stations = $stmt->fetchAll();
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
                    <h2><i class="fas fa-building me-2"></i>Manage Stations</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStationModal">
                        <i class="fas fa-plus me-1"></i>Add Station
                    </button>
                </div>

                <?php echo $message; ?>

                <!-- Stations List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Stations (<?php echo count($stations); ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($stations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No stations found</h5>
                                <p class="text-muted">Add your first station to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Station Code</th>
                                            <th>Station Name</th>
                                            <th>Vans</th>
                                            <th>Users</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stations as $station): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($station['station_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($station['station_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $station['van_count']; ?> vans</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $station['user_count']; ?> users</span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($station['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($station['van_count'] == 0 && $station['user_count'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $station['id']; ?>" 
                                                            title="Delete Station">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Cannot delete - has vans or users">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $station['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete station <strong><?php echo htmlspecialchars($station['station_code']); ?></strong>?</p>
                                                                <p class="text-muted">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="station_id" value="<?php echo $station['id']; ?>">
                                                                    <button type="submit" name="delete_station" class="btn btn-danger">Delete Station</button>
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
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add Station Modal -->
                <div class="modal fade" id="addStationModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add New Station</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="station_code" class="form-label">Station Code *</label>
                                        <input type="text" class="form-control" id="station_code" name="station_code" 
                                               value="<?php echo isset($_POST['station_code']) ? htmlspecialchars($_POST['station_code']) : ''; ?>" 
                                               required maxlength="10" style="text-transform: uppercase;">
                                        <div class="form-text">Short identifier for the station (e.g., DRP4, DHE1)</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="station_name" class="form-label">Station Name *</label>
                                        <input type="text" class="form-control" id="station_name" name="station_name" 
                                               value="<?php echo isset($_POST['station_name']) ? htmlspecialchars($_POST['station_name']) : ''; ?>" 
                                               required maxlength="100">
                                        <div class="form-text">Full name of the station</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_station" class="btn btn-primary">Add Station</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-uppercase station code input
        document.getElementById('station_code').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>