<?php
require_once 'config.php';
requireLogin();

$current_page = 'fleet';
$page_title = 'My Fleet - Van Fleet Management';
$user = getCurrentUser();

$message = '';

// Handle van deletion (Station Manager only)
if (isset($_POST['delete_van']) && $user['user_type'] === 'station_manager') {
    $van_id = intval($_POST['van_id']);
    
    try {
        $pdo->beginTransaction();
        
        // Get van details for confirmation
        $stmt = $pdo->prepare("SELECT license_plate FROM vans WHERE id = ?");
        $stmt->execute([$van_id]);
        $van = $stmt->fetch();
        
        if ($van) {
            // Get van images, videos, and documents to delete files
            $stmt = $pdo->prepare("SELECT image_path FROM van_images WHERE van_id = ?");
            $stmt->execute([$van_id]);
            $images = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT video_path FROM van_videos WHERE van_id = ?");
            $stmt->execute([$van_id]);
            $videos = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT document_path FROM van_documents WHERE van_id = ?");
            $stmt->execute([$van_id]);
            $documents = $stmt->fetchAll();
            
            // Delete the van (cascade will handle related records)
            $stmt = $pdo->prepare("DELETE FROM vans WHERE id = ?");
            $stmt->execute([$van_id]);
            
            // Delete physical files
            foreach ($images as $image) {
                if (file_exists($image['image_path'])) {
                    unlink($image['image_path']);
                }
            }
            foreach ($videos as $video) {
                if (file_exists($video['video_path'])) {
                    unlink($video['video_path']);
                }
            }
            foreach ($documents as $document) {
                if (file_exists($document['document_path'])) {
                    unlink($document['document_path']);
                }
            }
            
            $pdo->commit();
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Van "' . htmlspecialchars($van['license_plate']) . '" deleted successfully.</div>';
        } else {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Van not found.</div>';
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error deleting van: ' . $e->getMessage() . '</div>';
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$license_filter = isset($_GET['license']) ? trim($_GET['license']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$driver_filter = isset($_GET['driver']) ? trim($_GET['driver']) : '';
$station_filter = isset($_GET['station']) ? trim($_GET['station']) : '';

// Build query based on user type
$where_conditions = [];
$params = [];

if ($user['user_type'] === 'dispatcher') {
    $where_conditions[] = "v.station_id = ?";
    $params[] = $user['station_id'];
} elseif ($user['user_type'] === 'station_manager' && $station_filter) {
    $where_conditions[] = "v.station_id = ?";
    $params[] = $station_filter;
}

if ($license_filter) {
    $where_conditions[] = "v.license_plate LIKE ?";
    $params[] = "%$license_filter%";
}

if ($status_filter) {
    $where_conditions[] = "v.status = ?";
    $params[] = $status_filter;
}

if ($driver_filter) {
    $where_conditions[] = "(d.first_name LIKE ? OR d.last_name LIKE ?)";
    $params[] = "%$driver_filter%";
    $params[] = "%$driver_filter%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) FROM vans v 
                LEFT JOIN drivers d ON v.id = d.van_id 
                LEFT JOIN stations s ON v.station_id = s.id 
                $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get vans data
$query = "SELECT v.*, s.station_code, s.station_name,
                 d.first_name as driver_first_name, d.middle_name as driver_middle_name, d.last_name as driver_last_name
          FROM vans v 
          LEFT JOIN drivers d ON v.id = d.van_id 
          LEFT JOIN stations s ON v.station_id = s.id 
          $where_clause
          ORDER BY v.license_plate 
          LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vans = $stmt->fetchAll();

// Get stations for filter (station managers only)
$stations = [];
if ($user['user_type'] === 'station_manager') {
    $stmt = $pdo->query("SELECT * FROM stations ORDER BY station_code");
    $stations = $stmt->fetchAll();
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-truck me-2"></i>My Fleet</h2>
                    <a href="add_van.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Van
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
                            <div class="col-md-3">
                                <label for="license" class="form-label">License Plate</label>
                                <input type="text" class="form-control" id="license" name="license" 
                                       value="<?php echo htmlspecialchars($license_filter); ?>" placeholder="Enter license plate">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="in_use" <?php echo $status_filter === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                                    <option value="reserve" <?php echo $status_filter === 'reserve' ? 'selected' : ''; ?>>Reserve</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="driver" class="form-label">Driver</label>
                                <input type="text" class="form-control" id="driver" name="driver" 
                                       value="<?php echo htmlspecialchars($driver_filter); ?>" placeholder="Driver name">
                            </div>
                            
                            <?php if ($user['user_type'] === 'station_manager'): ?>
                            <div class="col-md-3">
                                <label for="station" class="form-label">Station</label>
                                <select class="form-select" id="station" name="station">
                                    <option value="">All Stations</option>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo $station['id']; ?>" <?php echo $station_filter == $station['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['station_code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="fleet.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Fleet Vehicles (<?php echo $total_records; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($vans)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No vehicles found</h5>
                                <p class="text-muted">Try adjusting your filters or add a new van to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>License Plate</th>
                                            <th>Make & Model</th>
                                            <th>Station</th>
                                            <th>Status</th>
                                            <th>Driver</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vans as $van): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($van['license_plate']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($van['make'] . ' ' . $van['model']); ?></td>
                                            <td><?php echo htmlspecialchars($van['station_code']); ?></td>
                                            <td>
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
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($van['driver_first_name']): ?>
                                                    <?php echo htmlspecialchars($van['driver_first_name'] . ' ' . $van['driver_last_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No driver assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit_van.php?id=<?php echo $van['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view_van.php?id=<?php echo $van['id']; ?>" class="btn btn-outline-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($user['user_type'] === 'station_manager'): ?>
                                                        <button type="button" class="btn btn-outline-danger delete-van-btn" 
                                                                data-van-id="<?php echo $van['id']; ?>"
                                                                data-van-license="<?php echo htmlspecialchars($van['license_plate']); ?>"
                                                                data-van-info="<?php echo htmlspecialchars($van['make'] . ' ' . $van['model']); ?>"
                                                                title="Delete Van">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
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
                                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">Previous</a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">Next</a>
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

    <!-- Delete Van Confirmation Modal -->
    <div class="modal fade" id="deleteVanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                        Confirm Van Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <p class="mb-2"><strong>Are you sure you want to delete van:</strong></p>
                        <p class="h6 text-center" id="vanInfo"></p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>This will permanently delete:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Van details and status information</li>
                                <li>All uploaded images and videos</li>
                                <li>Registration documents</li>
                                <li>All maintenance records</li>
                                <li>Driver assignments (drivers will be unassigned)</li>
                            </ul>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmText" class="form-label">
                            <strong>Type "DELETE" to confirm:</strong>
                        </label>
                        <input type="text" class="form-control" id="confirmText" placeholder="Type DELETE here">
                    </div>
                    
                    <p class="text-muted text-center mb-0"><strong>This action cannot be undone!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <form method="POST" id="deleteVanForm">
                        <input type="hidden" name="van_id" id="vanIdToDelete">
                        <button type="submit" name="delete_van" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                            <i class="fas fa-trash me-1"></i>Delete Van Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteVanModal'));
            const confirmText = document.getElementById('confirmText');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const vanInfo = document.getElementById('vanInfo');
            const vanIdToDelete = document.getElementById('vanIdToDelete');
            
            // Handle delete button clicks
            document.querySelectorAll('.delete-van-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const vanId = this.getAttribute('data-van-id');
                    const vanLicense = this.getAttribute('data-van-license');
                    const vanInfoText = this.getAttribute('data-van-info');
                    
                    // Set modal content
                    vanInfo.textContent = vanLicense + ' (' + vanInfoText + ')';
                    vanIdToDelete.value = vanId;
                    
                    // Reset form
                    confirmText.value = '';
                    confirmBtn.disabled = true;
                    
                    // Show modal
                    deleteModal.show();
                });
            });
            
            // Enable/disable delete button based on confirmation text
            confirmText.addEventListener('input', function() {
                if (this.value === 'DELETE') {
                    confirmBtn.disabled = false;
                } else {
                    confirmBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>