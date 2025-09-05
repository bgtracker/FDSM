<?php
require_once 'config.php';
requireLogin();

$current_page = 'drivers';
$page_title = 'Add Driver - Van Fleet Management';
$user = getCurrentUser();

$error_message = '';
$success_message = '';

// Get stations based on user type
$stations = [];
if ($user['user_type'] === 'station_manager') {
    $stmt = $pdo->query("SELECT * FROM stations ORDER BY station_code");
    $stations = $stmt->fetchAll();
} else {
    // Dispatcher can only add drivers to their own station
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
    $stmt->execute([$user['station_id']]);
    $stations = $stmt->fetchAll();
}

// Get available vans based on user type and selected station
$available_vans = [];
if (isset($_POST['station_id']) || ($user['user_type'] === 'dispatcher')) {
    $station_id = $user['user_type'] === 'dispatcher' ? $user['station_id'] : intval($_POST['station_id']);
    if ($station_id) {
        $stmt = $pdo->prepare("SELECT * FROM vans WHERE station_id = ? AND status = 'available' ORDER BY license_plate");
        $stmt->execute([$station_id]);
        $available_vans = $stmt->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_driver'])) {
    $driver_id = strtoupper(trim($_POST['driver_id']));
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $hire_date = $_POST['hire_date'];
    $station_id = intval($_POST['station_id']);
    $van_id = intval($_POST['van_id']);
    
    // Validate input
    if (empty($driver_id) || empty($first_name) || empty($last_name) || empty($station_id) || empty($date_of_birth) || empty($hire_date)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (strlen($driver_id) > 30) {
        $error_message = 'Driver ID must be 30 characters or less.';
    } else {
        // Check permissions
        if ($user['user_type'] === 'dispatcher' && $station_id !== $user['station_id']) {
            $error_message = 'You can only add drivers to your own station.';
        } else {
            // Check if driver ID already exists
            $stmt = $pdo->prepare("SELECT id FROM drivers WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            if ($stmt->fetch()) {
                $error_message = 'A driver with this Driver ID already exists.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Handle profile picture upload
                    $profile_picture_path = null;
                    if (!empty($_FILES['profile_picture']['name'])) {
                        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                            $file_size = $_FILES['profile_picture']['size'];
                            $file_tmp = $_FILES['profile_picture']['tmp_name'];
                            $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                            
                            if ($file_size <= MAX_IMAGE_SIZE && in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                                $new_filename = $driver_id . '_profile_' . time() . '.' . $file_ext;
                                $upload_path = UPLOAD_DIR . 'drivers/pictures/' . $new_filename;
                                
                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    // Resize image to 150x150
                                    $profile_picture_path = $upload_path;
                                    // Note: In production, you'd want to use GD or ImageMagick to resize
                                }
                            }
                        }
                    }
                    
                    // Insert driver
                    $stmt = $pdo->prepare("INSERT INTO drivers (driver_id, first_name, middle_name, last_name, date_of_birth, phone_number, address, hire_date, profile_picture, station_id, van_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$driver_id, $first_name, $middle_name, $last_name, $date_of_birth, $phone_number, $address, $hire_date, $profile_picture_path, $station_id, $van_id > 0 ? $van_id : null]);
                    $new_driver_id = $pdo->lastInsertId();
                    
                    // Handle document uploads
                    $upload_errors = [];
                    if (!empty($_FILES['documents']['name'][0])) {
                        foreach ($_FILES['documents']['name'] as $key => $filename) {
                            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                                $file_size = $_FILES['documents']['size'][$key];
                                $file_tmp = $_FILES['documents']['tmp_name'][$key];
                                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                $document_type = $_POST['document_types'][$key] ?? 'Other';
                                
                                if ($file_size > MAX_IMAGE_SIZE) {
                                    $upload_errors[] = "Document $filename is too large (max 5MB)";
                                    continue;
                                }
                                
                                if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                                    $upload_errors[] = "Invalid document format for $filename";
                                    continue;
                                }
                                
                                $new_filename = $new_driver_id . '_doc_' . time() . '_' . $key . '.' . $file_ext;
                                $upload_path = UPLOAD_DIR . 'drivers/documents/' . $new_filename;
                                
                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    $stmt = $pdo->prepare("INSERT INTO driver_documents (driver_id, document_type, document_path, document_name) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([$new_driver_id, $document_type, $upload_path, $filename]);
                                } else {
                                    $upload_errors[] = "Failed to upload document $filename";
                                }
                            }
                        }
                    }
                    
                    // If van assigned, update van status
                    if ($van_id > 0) {
                        $stmt = $pdo->prepare("UPDATE vans SET status = 'in_use' WHERE id = ?");
                        $stmt->execute([$van_id]);
                    }
                    
                    $pdo->commit();
                    
                    $success_message = 'Driver added successfully!';
                    if (!empty($upload_errors)) {
                        $success_message .= ' However, some files had issues: ' . implode(', ', $upload_errors);
                    }
                    
                    // Clear form data
                    $_POST = [];
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = 'Error adding driver: ' . $e->getMessage();
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
            <div class="col-lg-10 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-plus me-2"></i>Add New Driver</h2>
                    <a href="drivers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Drivers
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
                        <h5 class="mb-0">Driver Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="driverForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="driver_id" class="form-label">Driver ID *</label>
                                    <input type="text" class="form-control" id="driver_id" name="driver_id" 
                                           value="<?php echo isset($_POST['driver_id']) ? htmlspecialchars($_POST['driver_id']) : ''; ?>" 
                                           maxlength="30" required>
                                    <div class="form-text">Unique identifier for the driver (max 30 characters)</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="profile_picture" class="form-label">Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                           accept="image/jpeg,image/jpg,image/png">
                                    <div class="form-text">JPG, JPEG, PNG (max 5MB) - will be resized to 150x150px</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="hire_date" class="form-label">Hire Date *</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                           value="<?php echo isset($_POST['hire_date']) ? htmlspecialchars($_POST['hire_date']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="station_id" class="form-label">Station *</label>
                                    <select class="form-select" id="station_id" name="station_id" required <?php echo $user['user_type'] === 'dispatcher' ? 'readonly' : ''; ?>>
                                        <option value="">Select Station</option>
                                        <?php foreach ($stations as $station): ?>
                                            <option value="<?php echo $station['id']; ?>" 
                                                    <?php echo (isset($_POST['station_id']) && $_POST['station_id'] == $station['id']) || 
                                                              ($user['user_type'] === 'dispatcher' && $station['id'] == $user['station_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($station['station_code'] . ' - ' . $station['station_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="van_id" class="form-label">Assign to Van (Optional)</label>
                                <select class="form-select" id="van_id" name="van_id">
                                    <option value="0">No van assignment</option>
                                    <?php foreach ($available_vans as $van): ?>
                                        <option value="<?php echo $van['id']; ?>" 
                                                <?php echo (isset($_POST['van_id']) && $_POST['van_id'] == $van['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($van['license_plate'] . ' (' . $van['make'] . ' ' . $van['model'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Only available vans from the selected station are shown.</div>
                            </div>
                            
                            <hr>
                            
                            <h6>Driver Documents (ID, Driver's License, etc.)</h6>
                            
                            <div id="documentContainer">
                                <div class="document-row row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Document Type</label>
                                        <select class="form-select" name="document_types[]">
                                            <option value="ID">ID</option>
                                            <option value="Drivers License">Driver's License</option>
                                            <option value="Medical Certificate">Medical Certificate</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Document File</label>
                                        <input type="file" class="form-control" name="documents[]" 
                                               accept="image/jpeg,image/jpg,image/png,application/pdf">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-document" disabled>
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addDocument">
                                    <i class="fas fa-plus me-1"></i>Add Another Document
                                </button>
                                <div class="form-text">Supported formats: JPG, PNG, PDF (max 5MB each)</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="drivers.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" name="submit_driver" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Add Driver
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
        // Auto-uppercase driver ID input
        document.getElementById('driver_id').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Auto-update available vans when station changes
        document.getElementById('station_id').addEventListener('change', function() {
            const form = document.getElementById('driverForm');
            const formData = new FormData(form);
            
            // Create a temporary form to submit and reload available vans
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.style.display = 'none';
            
            for (let [key, value] of formData.entries()) {
                if (key !== 'submit_driver' && !key.includes('documents') && key !== 'profile_picture') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    tempForm.appendChild(input);
                }
            }
            
            document.body.appendChild(tempForm);
            tempForm.submit();
        });
        
        // Add/Remove document functionality
        let documentCount = 1;
        
        document.getElementById('addDocument').addEventListener('click', function() {
            if (documentCount < 5) { // Max 5 documents
                const container = document.getElementById('documentContainer');
                const newRow = document.querySelector('.document-row').cloneNode(true);
                
                // Clear values
                newRow.querySelectorAll('input, select').forEach(input => {
                    input.value = '';
                });
                
                // Enable remove button
                newRow.querySelector('.remove-document').disabled = false;
                
                container.appendChild(newRow);
                documentCount++;
                
                if (documentCount >= 5) {
                    this.style.display = 'none';
                }
            }
        });
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-document') || e.target.parentElement.classList.contains('remove-document')) {
                const row = e.target.closest('.document-row');
                if (row && documentCount > 1) {
                    row.remove();
                    documentCount--;
                    document.getElementById('addDocument').style.display = 'inline-block';
                }
            }
        });
    </script>
</body>
</html>