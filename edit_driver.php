<?php
require_once 'config.php';
requireLogin();

$current_page = 'drivers';
$page_title = 'Edit Driver - Van Fleet Management';
$user = getCurrentUser();

$driver_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

if (!$driver_id) {
    header('Location: drivers.php');
    exit();
}

// Get driver details with permission check
if ($user['user_type'] === 'dispatcher') {
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ? AND station_id = ?");
    $stmt->execute([$driver_id, $user['station_id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->execute([$driver_id]);
}

$driver = $stmt->fetch();

if (!$driver) {
    header('Location: drivers.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_driver_id = strtoupper(trim($_POST['driver_id']));
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $salary_account = trim($_POST['salary_account']);
    
    // Validate input
    if (empty($new_driver_id)) {
        $error_message = 'Driver ID is required.';
    } elseif (strlen($new_driver_id) > 30) {
        $error_message = 'Driver ID must be 30 characters or less.';
    } else {
        // Check if driver ID already exists (excluding current driver)
        $stmt = $pdo->prepare("SELECT id FROM drivers WHERE driver_id = ? AND id != ?");
        $stmt->execute([$new_driver_id, $driver_id]);
        if ($stmt->fetch()) {
            $error_message = 'A driver with this Driver ID already exists.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Handle profile picture upload
                $profile_picture_path = $driver['profile_picture'];
                if (!empty($_FILES['profile_picture']['name'])) {
                    if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                        $file_size = $_FILES['profile_picture']['size'];
                        $file_tmp = $_FILES['profile_picture']['tmp_name'];
                        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                        
                        if ($file_size <= MAX_IMAGE_SIZE && in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                            // Delete old profile picture
                            if ($profile_picture_path && file_exists($profile_picture_path)) {
                                unlink($profile_picture_path);
                            }
                            
                            $new_filename = $new_driver_id . '_profile_' . time() . '.' . $file_ext;
                            $upload_path = UPLOAD_DIR . 'drivers/pictures/' . $new_filename;
                            
                            if (move_uploaded_file($file_tmp, $upload_path)) {
                                $profile_picture_path = $upload_path;
                            }
                        }
                    }
                }
                
                // Update driver
                if ($user['user_type'] === 'station_manager') {
                    // Station manager can edit salary account
                    $stmt = $pdo->prepare("UPDATE drivers SET driver_id = ?, phone_number = ?, address = ?, salary_account = ?, profile_picture = ? WHERE id = ?");
                    $stmt->execute([$new_driver_id, $phone_number, $address, $salary_account, $profile_picture_path, $driver_id]);
                } else {
                    // Dispatcher cannot edit salary account
                    $stmt = $pdo->prepare("UPDATE drivers SET driver_id = ?, phone_number = ?, address = ?, profile_picture = ? WHERE id = ?");
                    $stmt->execute([$new_driver_id, $phone_number, $address, $profile_picture_path, $driver_id]);
                }
                
                $pdo->commit();
                
                $success_message = 'Driver updated successfully!';
                
                // Refresh driver data
                $driver['driver_id'] = $new_driver_id;
                $driver['phone_number'] = $phone_number;
                $driver['address'] = $address;
                if ($user['user_type'] === 'station_manager') {
                    $driver['salary_account'] = $salary_account;
                }
                $driver['profile_picture'] = $profile_picture_path;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = 'Error updating driver: ' . $e->getMessage();
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
    <style>
        .current-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #dee2e6;
        }
        .picture-placeholder {
            width: 150px;
            height: 150px;
            background-color: #f8f9fa;
            border: 3px solid #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-edit me-2"></i>Edit Driver: <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></h2>
                    <a href="view_driver.php?id=<?php echo $driver['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Driver
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
                        <h5 class="mb-0">Editable Driver Information</h5>
                        <small class="text-muted">Note: Only Driver ID, Phone Number, Address, Profile Picture<?php echo $user['user_type'] === 'station_manager' ? ', and Salary Account' : ''; ?> can be edited</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <!-- Current Profile Picture -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label">Current Profile Picture</label>
                                    <div class="d-flex align-items-center">
                                        <?php if ($driver['profile_picture'] && file_exists($driver['profile_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($driver['profile_picture']); ?>" 
                                                 alt="Current Profile Picture" class="current-picture me-3">
                                        <?php else: ?>
                                            <div class="picture-placeholder me-3">
                                                <i class="fas fa-user fa-3x"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6><?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></h6>
                                            <p class="text-muted mb-0">Upload a new picture to replace the current one</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="driver_id" class="form-label">Driver ID *</label>
                                    <input type="text" class="form-control" id="driver_id" name="driver_id" 
                                           value="<?php echo htmlspecialchars($driver['driver_id']); ?>" 
                                           maxlength="30" required>
                                    <div class="form-text">Unique identifier for the driver (max 30 characters)</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="profile_picture" class="form-label">New Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                           accept="image/jpeg,image/jpg,image/png">
                                    <div class="form-text">JPG, JPEG, PNG (max 5MB) - will be resized to 150x150px</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                       value="<?php echo htmlspecialchars($driver['phone_number']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($driver['address']); ?></textarea>
                            </div>

                            <?php if ($user['user_type'] === 'station_manager'): ?>
                            <div class="mb-4">
                                <label for="salary_account" class="form-label">Salary Account (IBAN)</label>
                                <input type="text" class="form-control" id="salary_account" name="salary_account" 
                                       value="<?php echo htmlspecialchars($driver['salary_account']); ?>"
                                       placeholder="e.g., DE89370400440532013000" maxlength="34">
                                <div class="form-text">
                                    <span id="iban-status"></span>
                                    International Bank Account Number (IBAN) for salary payments
                                </div>
                            </div>
                            <?php endif; ?>

                            <hr>

                            <!-- Read-only Information -->
                            <h6 class="text-muted mb-3">Read-Only Information</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted">First Name</label>
                                    <input type="text" class="form-control-plaintext" readonly 
                                           value="<?php echo htmlspecialchars($driver['first_name']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted">Middle Name</label>
                                    <input type="text" class="form-control-plaintext" readonly 
                                           value="<?php echo htmlspecialchars($driver['middle_name'] ?: 'Not specified'); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted">Last Name</label>
                                    <input type="text" class="form-control-plaintext" readonly 
                                           value="<?php echo htmlspecialchars($driver['last_name']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Date of Birth</label>
                                    <input type="text" class="form-control-plaintext" readonly 
                                           value="<?php echo $driver['date_of_birth'] ? date('M d, Y', strtotime($driver['date_of_birth'])) : 'Not specified'; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Hire Date</label>
                                    <input type="text" class="form-control-plaintext" readonly 
                                           value="<?php echo $driver['hire_date'] ? date('M d, Y', strtotime($driver['hire_date'])) : 'Not specified'; ?>">
                                </div>
                            </div>

                            <?php if ($user['user_type'] === 'dispatcher' && $driver['salary_account']): ?>
                            <div class="mb-3">
                                <label class="form-label text-muted">Salary Account (IBAN)</label>
                                <input type="text" class="form-control-plaintext" readonly 
                                       value="<?php echo htmlspecialchars($driver['salary_account'] ?: 'Not specified'); ?>">
                                <div class="form-text text-muted">Contact station manager to modify this field</div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view_driver.php?id=<?php echo $driver['id']; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-save me-1"></i>Update Driver
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

        // IBAN Validation (Station Manager Only)
        let ibanValid = true; // Default to true if no IBAN entered
        
        <?php if ($user['user_type'] === 'station_manager'): ?>
        function validateIBAN(iban) {
            const ibanInput = document.getElementById('salary_account');
            const statusDiv = document.getElementById('iban-status');
            const submitBtn = document.getElementById('submitBtn');
            
            // Clear status
            statusDiv.innerHTML = '';
            
            if (!iban || iban.trim() === '') {
                ibanValid = true;
                submitBtn.disabled = false;
                return;
            }
            
            // Remove spaces and convert to uppercase
            iban = iban.replace(/\s/g, '').toUpperCase();
            
            // Show loading
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin text-primary me-1"></i>Validating IBAN...';
            submitBtn.disabled = true;
            
            // Call free IBAN validation API
            fetch(`https://openiban.com/validate/${iban}`)
                .then(response => response.json())
                .then(data => {
                    if (data.valid === true) {
                        ibanValid = true;
                        statusDiv.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>Valid IBAN';
                        if (data.bankData && data.bankData.name) {
                            statusDiv.innerHTML += ` - ${data.bankData.name}`;
                        }
                        submitBtn.disabled = false;
                    } else {
                        ibanValid = false;
                        statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i>Invalid IBAN';
                        submitBtn.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('IBAN validation error:', error);
                    // If API fails, allow submission but show warning
                    ibanValid = true;
                    statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>Could not validate IBAN (proceeding anyway)';
                    submitBtn.disabled = false;
                });
        }
        
        // Add IBAN validation on input
        document.getElementById('salary_account').addEventListener('input', function() {
            const iban = this.value;
            clearTimeout(this.validationTimeout);
            this.validationTimeout = setTimeout(() => {
                validateIBAN(iban);
            }, 500); // Debounce for 500ms
        });
        
        // Format IBAN as user types (add spaces every 4 characters)
        document.getElementById('salary_account').addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '').toUpperCase();
            let formatted = '';
            for (let i = 0; i < value.length; i += 4) {
                if (i > 0) formatted += ' ';
                formatted += value.substr(i, 4);
            }
            this.value = formatted;
        });
        
        // Validate existing IBAN on page load
        document.addEventListener('DOMContentLoaded', function() {
            const existingIban = document.getElementById('salary_account').value;
            if (existingIban && existingIban.trim() !== '') {
                validateIBAN(existingIban);
            }
        });
        
        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!ibanValid) {
                e.preventDefault();
                alert('Please enter a valid IBAN or leave the field empty.');
                return false;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>