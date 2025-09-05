<?php
require_once 'config.php';
requireLogin();

$current_page = 'fleet';
$page_title = 'Add Van - Van Fleet Management';
$user = getCurrentUser();

$error_message = '';
$success_message = '';

// Get stations based on user type
$stations = [];
if ($user['user_type'] === 'station_manager') {
    $stmt = $pdo->query("SELECT * FROM stations ORDER BY station_code");
    $stations = $stmt->fetchAll();
} else {
    // Dispatcher can only add vans to their own station
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
    $stmt->execute([$user['station_id']]);
    $stations = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_plate = strtoupper(trim($_POST['license_plate']));
    $vin_number = strtoupper(trim($_POST['vin_number']));
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $station_id = intval($_POST['station_id']);
    $status = $_POST['status'];
    
    // Validate input
    if (empty($license_plate) || empty($vin_number) || empty($make) || empty($model) || empty($station_id)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (strlen($vin_number) !== 17) {
        $error_message = 'VIN number must be exactly 17 characters long.';
    } else {
        // Check if license plate already exists
        $stmt = $pdo->prepare("SELECT id FROM vans WHERE license_plate = ?");
        $stmt->execute([$license_plate]);
        if ($stmt->fetch()) {
            $error_message = 'A van with this license plate already exists.';
        } else {
            // Check if VIN already exists
            $stmt = $pdo->prepare("SELECT id FROM vans WHERE vin_number = ?");
            $stmt->execute([$vin_number]);
            if ($stmt->fetch()) {
                $error_message = 'A van with this VIN number already exists.';
            } else {
                // Check if documents are uploaded
                if (empty($_FILES['documents']['name'][0])) {
                    $error_message = 'Registration documents are required when adding a van.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // Insert van
                        $stmt = $pdo->prepare("INSERT INTO vans (license_plate, vin_number, make, model, station_id, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$license_plate, $vin_number, $make, $model, $station_id, $status]);
                        $van_id = $pdo->lastInsertId();
                        
                        // Handle file uploads
                        $upload_errors = [];
                        
                        // Handle registration documents (REQUIRED)
                        $documents_uploaded = false;
                        if (!empty($_FILES['documents']['name'][0])) {
                            foreach ($_FILES['documents']['name'] as $key => $filename) {
                                if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                                    $file_size = $_FILES['documents']['size'][$key];
                                    $file_tmp = $_FILES['documents']['tmp_name'][$key];
                                    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                    
                                    if ($file_size > MAX_IMAGE_SIZE) {
                                        $upload_errors[] = "Document $filename is too large (max 5MB)";
                                        continue;
                                    }
                                    
                                    if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                                        $upload_errors[] = "Invalid document format for $filename";
                                        continue;
                                    }
                                    
                                    $new_filename = $van_id . '_doc_' . time() . '_' . $key . '.' . $file_ext;
                                    $upload_path = UPLOAD_DIR . 'vans/documents/' . $new_filename;
                                    
                                    if (move_uploaded_file($file_tmp, $upload_path)) {
                                        $stmt = $pdo->prepare("INSERT INTO van_documents (van_id, document_path, document_name) VALUES (?, ?, ?)");
                                        $stmt->execute([$van_id, $upload_path, $filename]);
                                        $documents_uploaded = true;
                                    } else {
                                        $upload_errors[] = "Failed to upload document $filename";
                                    }
                                }
                            }
                        }
                        
                        if (!$documents_uploaded) {
                            $pdo->rollBack();
                            $error_message = 'Registration documents are required and must be successfully uploaded.';
                        } else {
                            // Handle images
                            if (!empty($_FILES['images']['name'][0])) {
                                $image_count = 0;
                                foreach ($_FILES['images']['name'] as $key => $filename) {
                                    if ($image_count >= 10) break; // Max 10 images
                                    
                                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                                        $file_size = $_FILES['images']['size'][$key];
                                        $file_tmp = $_FILES['images']['tmp_name'][$key];
                                        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                        
                                        if ($file_size > MAX_IMAGE_SIZE) {
                                            $upload_errors[] = "Image $filename is too large (max 5MB)";
                                            continue;
                                        }
                                        
                                        if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                            $upload_errors[] = "Invalid image format for $filename";
                                            continue;
                                        }
                                        
                                        $new_filename = $van_id . '_' . time() . '_' . $key . '.' . $file_ext;
                                        $upload_path = UPLOAD_DIR . 'vans/images/' . $new_filename;
                                        
                                        if (move_uploaded_file($file_tmp, $upload_path)) {
                                            $stmt = $pdo->prepare("INSERT INTO van_images (van_id, image_path) VALUES (?, ?)");
                                            $stmt->execute([$van_id, $upload_path]);
                                            $image_count++;
                                        } else {
                                            $upload_errors[] = "Failed to upload image $filename";
                                        }
                                    }
                                }
                            }
                            
                            // Handle video
                            if (!empty($_FILES['video']['name'])) {
                                if ($_FILES['video']['error'] === UPLOAD_ERR_OK) {
                                    $file_size = $_FILES['video']['size'];
                                    $file_tmp = $_FILES['video']['tmp_name'];
                                    $file_ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
                                    
                                    if ($file_size > MAX_FILE_SIZE) {
                                        $upload_errors[] = "Video is too large (max 50MB)";
                                    } elseif (!in_array($file_ext, ['mp4', 'avi', 'mov', 'wmv'])) {
                                        $upload_errors[] = "Invalid video format";
                                    } else {
                                        $new_filename = $van_id . '_' . time() . '.' . $file_ext;
                                        $upload_path = UPLOAD_DIR . 'vans/videos/' . $new_filename;
                                        
                                        if (move_uploaded_file($file_tmp, $upload_path)) {
                                            $stmt = $pdo->prepare("INSERT INTO van_videos (van_id, video_path) VALUES (?, ?)");
                                            $stmt->execute([$van_id, $upload_path]);
                                        } else {
                                            $upload_errors[] = "Failed to upload video";
                                        }
                                    }
                                }
                            }
                            
                            $pdo->commit();
                            
                            $success_message = 'Van added successfully!';
                            if (!empty($upload_errors)) {
                                $success_message .= ' However, some files had issues: ' . implode(', ', $upload_errors);
                            }
                            
                            // Clear form data
                            $_POST = [];
                        }
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = 'Error adding van: ' . $e->getMessage();
                    }
                }
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
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-plus me-2"></i>Add New Van</h2>
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
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="license_plate" class="form-label">License Plate *</label>
                                    <input type="text" class="form-control" id="license_plate" name="license_plate" 
                                           value="<?php echo isset($_POST['license_plate']) ? htmlspecialchars($_POST['license_plate']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="vin_number" class="form-label">VIN Number *</label>
                                    <input type="text" class="form-control" id="vin_number" name="vin_number" 
                                           value="<?php echo isset($_POST['vin_number']) ? htmlspecialchars($_POST['vin_number']) : ''; ?>" 
                                           maxlength="17" minlength="17" required>
                                    <div class="form-text">Vehicle Identification Number (17 characters)</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="make" class="form-label">Make *</label>
                                    <input type="text" class="form-control" id="make" name="make" 
                                           value="<?php echo isset($_POST['make']) ? htmlspecialchars($_POST['make']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="station_id" class="form-label">Station *</label>
                                    <select class="form-select" id="station_id" name="station_id" required>
                                        <option value="">Select Station</option>
                                        <?php foreach ($stations as $station): ?>
                                            <option value="<?php echo $station['id']; ?>" 
                                                    <?php echo (isset($_POST['station_id']) && $_POST['station_id'] == $station['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($station['station_code'] . ' - ' . $station['station_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="available" <?php echo (isset($_POST['status']) && $_POST['status'] === 'available') ? 'selected' : 'selected'; ?>>Available</option>
                                        <option value="in_use" <?php echo (isset($_POST['status']) && $_POST['status'] === 'in_use') ? 'selected' : ''; ?>>In Use</option>
                                        <option value="reserve" <?php echo (isset($_POST['status']) && $_POST['status'] === 'reserve') ? 'selected' : ''; ?>>Reserve</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6>Registration Documents (Required) *</h6>
                            
                            <div class="mb-3">
                                <label for="documents" class="form-label">Registration Documents *</label>
                                <input type="file" class="form-control" id="documents" name="documents[]" 
                                       accept="image/jpeg,image/jpg,image/png,application/pdf" multiple required>
                                <div class="form-text">Upload registration documents. Supported formats: JPG, PNG, PDF (max 5MB each)</div>
                            </div>
                            
                            <hr>
                            
                            <h6>Media Files (Optional)</h6>
                            
                            <div class="mb-3">
                                <label for="images" class="form-label">Van Images (Max 10 images, 5MB each)</label>
                                <input type="file" class="form-control" id="images" name="images[]" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif" multiple>
                                <div class="form-text">Supported formats: JPG, PNG, GIF</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="video" class="form-label">Van Video (Max 50MB)</label>
                                <input type="file" class="form-control" id="video" name="video" 
                                       accept="video/mp4,video/avi,video/mov,video/wmv">
                                <div class="form-text">Supported formats: MP4, AVI, MOV, WMV</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="fleet.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Add Van
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
        // Auto-uppercase VIN input
        document.getElementById('vin_number').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Auto-uppercase license plate input
        document.getElementById('license_plate').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>