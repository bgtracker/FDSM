<?php
require_once 'config.php';
requireLogin();

$current_page = 'maintenance';
$page_title = 'Van Maintenance Detail - Van Fleet Management';
$user = getCurrentUser();

$van_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

if (!$van_id) {
    header('Location: maintenance.php');
    exit();
}

// Get van details with permission check
if ($user['user_type'] === 'dispatcher') {
    $stmt = $pdo->prepare("SELECT v.*, s.station_code, s.station_name 
                          FROM vans v 
                          LEFT JOIN stations s ON v.station_id = s.id 
                          WHERE v.id = ? AND v.station_id = ?");
    $stmt->execute([$van_id, $user['station_id']]);
} else {
    $stmt = $pdo->prepare("SELECT v.*, s.station_code, s.station_name 
                          FROM vans v 
                          LEFT JOIN stations s ON v.station_id = s.id 
                          WHERE v.id = ?");
    $stmt->execute([$van_id]);
}

$van = $stmt->fetch();

if (!$van) {
    header('Location: maintenance.php');
    exit();
}

// Handle new maintenance record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record'])) {
    $maintenance_record = trim($_POST['maintenance_record']);
    
    if (empty($maintenance_record)) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Please enter maintenance details.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO van_maintenance (van_id, user_id, maintenance_record) VALUES (?, ?, ?)");
            $stmt->execute([$van_id, $user['id'], $maintenance_record]);
            
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Maintenance record added successfully.</div>';
            
            // Clear the form
            $_POST['maintenance_record'] = '';
            
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error adding maintenance record.</div>';
        }
    }
}

// Get maintenance records
$stmt = $pdo->prepare("SELECT vm.*, u.username, u.first_name, u.last_name 
                      FROM van_maintenance vm 
                      LEFT JOIN users u ON vm.user_id = u.id 
                      WHERE vm.van_id = ? 
                      ORDER BY vm.created_at DESC");
$stmt->execute([$van_id]);
$maintenance_records = $stmt->fetchAll();
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
                    <h2>
                        <i class="fas fa-tools me-2"></i>
                        Maintenance: <?php echo htmlspecialchars($van['license_plate']); ?>
                    </h2>
                    <a href="maintenance.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Search
                    </a>
                </div>

                <?php echo $message; ?>

                <!-- Van Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Vehicle Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>License Plate:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($van['license_plate']); ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Make & Model:</strong><br>
                                <?php echo htmlspecialchars($van['make'] . ' ' . $van['model']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Station:</strong><br>
                                <?php echo htmlspecialchars($van['station_code']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Status:</strong><br>
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
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add New Maintenance Record -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Add Maintenance Record</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="maintenance_record" class="form-label">Maintenance Details</label>
                                <textarea class="form-control" id="maintenance_record" name="maintenance_record" 
                                          rows="4" placeholder="Enter what maintenance work was performed..."
                                          ><?php echo isset($_POST['maintenance_record']) ? htmlspecialchars($_POST['maintenance_record']) : ''; ?></textarea>
                            </div>
                            <button type="submit" name="add_record" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Add Record
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Maintenance History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Maintenance History (<?php echo count($maintenance_records); ?> records)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($maintenance_records)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No maintenance records</h5>
                                <p class="text-muted">This van has no maintenance history yet. Add the first record above.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($maintenance_records as $record): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-0">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                    <small class="text-muted">(@<?php echo htmlspecialchars($record['username']); ?>)</small>
                                                </h6>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M d, Y \a\t g:i A', strtotime($record['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="maintenance-content">
                                            <?php echo nl2br(htmlspecialchars($record['maintenance_record'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
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