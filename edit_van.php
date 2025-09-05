<?php
require_once 'config.php';
requireLogin();

$current_page = 'fleet';
$page_title = 'Edit Van - Van Fleet Management';
$user = getCurrentUser();

$van_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

if (!$van_id) {
    header('Location: fleet.php');
    exit();
}

// Get van details with permission check
if ($user['user_type'] === 'dispatcher') {
    $stmt = $pdo->prepare("SELECT * FROM vans WHERE id = ? AND station_id = ?");
    $stmt->execute([$van_id, $user['station_id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM vans WHERE id = ?");
    $stmt->execute([$van_id]);
}

$van = $stmt->fetch();

if (!$van) {
    header('Location: fleet.php');
    exit();
}

// Get stations based on user type
$stations = [];
if ($user['user_type'] === 'station_manager') {
    $stmt = $pdo->query("SELECT * FROM stations ORDER BY station_code");
    $stations = $stmt->fetchAll();
} else {
    // Dispatcher can only edit vans in their own station
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
    $stmt->execute([$user['station_id']]);
    $stations = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_plate = strtoupper(trim($_POST['license_plate']));
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $station_id = intval($_POST['station_id']);
    $status = $_POST['status'];
    
    // Validate input
    if (empty($license_plate) || empty($make) || empty($model) || empty($station_id)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Check if license plate already exists (excluding current van)
        $stmt = $pdo->prepare("SELECT id FROM vans WHERE license_plate = ? AND id != ?");
        $stmt->execute([$license_plate, $van_id]);
        if ($stmt->fetch()) {
            $error_message = 'A van with this license plate already exists.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE vans SET license_plate = ?, make = ?, model = ?, station_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$license_plate, $make, $model, $station_id, $status, $van_id]);
                
                $success_message = 'Van updated successfully!';
                
                // Refresh van data
                $van['license_plate'] = $license_plate;
                $van['make'] = $make;
                $van['model'] = $model;
                $van['station_id'] = $station_id;
                $van['status'] = $status;
                
            } catch (Exception $e) {
                $error_message = 'Error updating van: ' . $e->getMessage();
            }
        }
    }
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
            <div class="col-lg-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-edit me-2"></i>Edit Van: <?php echo htmlspecialchars($van['license_plate']); ?></h2>
                    <a href="fleet.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Fleet
                    </a>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Van Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="license_plate" class="form-label">License Plate *</label>
                                    <input type="text" class="form-control" id="license_plate" name="license_plate" 
                                           value="<?php echo htmlspecialchars($van['license_plate']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="station_id" class="form-label">Station *</label>
                                    <select class="form-select" id="station_id" name="station_id" required>
                                        <option value="">Select Station</option>
                                        <?php foreach ($stations as $station): ?>
                                            <option value="<?php echo $station['id']; ?>" 
                                                    <?php echo $van['station_id'] == $station['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($station['station_code'] . ' - ' . $station['station_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="make" class="form-label">Make *</label>
                                    <input type="text" class="form-control" id="make" name="make" 
                                           value="<?php echo htmlspecialchars($van['make']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo htmlspecialchars($van['model']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="available" <?php echo $van['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="in_use" <?php echo $van['status'] === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                                    <option value="reserve" <?php echo $van['status'] === 'reserve' ? 'selected' : ''; ?>>Reserve</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="fleet.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update Van
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteImage(imageId) {
            if (confirm('Are you sure you want to delete this image?')) {
                fetch('delete_van_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'image_id=' + imageId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting image: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting image');
                });
            }
        }
    </script>
</body>
</html>