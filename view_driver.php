<?php
require_once 'config.php';
requireLogin();

$current_page = 'drivers';
$page_title = 'Driver Details - Van Fleet Management';
$user = getCurrentUser();

$driver_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$driver_id) {
    header('Location: drivers.php');
    exit();
}

// Get driver details with permission check
if ($user['user_type'] === 'dispatcher') {
    $stmt = $pdo->prepare("SELECT d.*, s.station_code, s.station_name, v.license_plate as van_license, v.make as van_make, v.model as van_model 
                          FROM drivers d 
                          LEFT JOIN stations s ON d.station_id = s.id 
                          LEFT JOIN vans v ON d.van_id = v.id
                          WHERE d.id = ? AND d.station_id = ?");
    $stmt->execute([$driver_id, $user['station_id']]);
} else {
    $stmt = $pdo->prepare("SELECT d.*, s.station_code, s.station_name, v.license_plate as van_license, v.make as van_make, v.model as van_model 
                          FROM drivers d 
                          LEFT JOIN stations s ON d.station_id = s.id 
                          LEFT JOIN vans v ON d.van_id = v.id
                          WHERE d.id = ?");
    $stmt->execute([$driver_id]);
}

$driver = $stmt->fetch();

if (!$driver || !is_array($driver)) {
    header('Location: drivers.php');
    exit();
}

// Helper functions for safe data access
function safeGet($array, $key, $default = '') {
    return (is_array($array) && isset($array[$key]) && $array[$key] !== null) ? $array[$key] : $default;
}

function formatDate($date_string, $format = 'M d, Y') {
    if (empty($date_string) || $date_string === '0000-00-00') {
        return 'Not specified';
    }
    return date($format, strtotime($date_string));
}

function formatDateTime($datetime_string, $format = 'M d, Y \a\t g:i A') {
    if (empty($datetime_string)) {
        return 'Not specified';
    }
    return date($format, strtotime($datetime_string));
}

function getFullName($driver) {
    if (!is_array($driver)) {
        return 'Unknown Driver';
    }
    
    $name = '';
    $first_name = safeGet($driver, 'first_name');
    $middle_name = safeGet($driver, 'middle_name');
    $last_name = safeGet($driver, 'last_name');
    
    if ($first_name) {
        $name .= $first_name;
    }
    if ($middle_name) {
        $name .= ' ' . $middle_name;
    }
    if ($last_name) {
        $name .= ' ' . $last_name;
    }
    
    return trim($name) ?: 'Unknown Driver';
}

function getVanInfo($driver) {
    if (!is_array($driver)) {
        return null;
    }
    
    $van_license = safeGet($driver, 'van_license');
    $van_make = safeGet($driver, 'van_make');
    $van_model = safeGet($driver, 'van_model');
    
    if (!$van_license) {
        return null;
    }
    
    return [
        'license' => $van_license,
        'make_model' => trim($van_make . ' ' . $van_model)
    ];
}

// Extract ALL safe values at once to avoid any direct array access
$driver_db_id = intval(safeGet($driver, 'id', 0));
$driver_full_name = getFullName($driver);
$driver_id_safe = safeGet($driver, 'driver_id', 'N/A');
$station_code = safeGet($driver, 'station_code', 'N/A');
$station_name = safeGet($driver, 'station_name', 'Unknown Station');
$profile_picture = safeGet($driver, 'profile_picture');
$date_of_birth = safeGet($driver, 'date_of_birth');
$hire_date = safeGet($driver, 'hire_date');
$phone_number = safeGet($driver, 'phone_number');
$address = safeGet($driver, 'address');
$salary_account = safeGet($driver, 'salary_account');
$created_at = safeGet($driver, 'created_at');
$updated_at = safeGet($driver, 'updated_at');

// Ensure we have a valid driver ID
if ($driver_db_id <= 0) {
    header('Location: drivers.php');
    exit();
}

// Get van information
$van_info = getVanInfo($driver);

// Get driver documents using the safe driver ID
$stmt = $pdo->prepare("SELECT * FROM driver_documents WHERE driver_id = ? ORDER BY document_type, created_at");
$stmt->execute([$driver_db_id]);
$driver_documents = $stmt->fetchAll();

// Handle success/error messages from redirects
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';
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
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #dee2e6;
        }
        .profile-placeholder {
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
        .iban-display {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-user me-2"></i>
                        Driver Details: <?php echo htmlspecialchars($driver_full_name); ?>
                    </h2>
                    <div>
                        <a href="edit_driver.php?id=<?php echo $driver_db_id; ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <a href="drivers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Drivers
                        </a>
                    </div>
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

                <div class="row">
                    <!-- Driver Information -->
                    <div class="col-lg-8">
                        <!-- Profile Header -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <?php if ($profile_picture && file_exists($profile_picture)): ?>
                                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                                                 alt="Profile Picture" class="profile-picture">
                                        <?php else: ?>
                                            <div class="profile-placeholder">
                                                <i class="fas fa-user fa-3x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col">
                                        <h3 class="mb-1"><?php echo htmlspecialchars($driver_full_name); ?></h3>
                                        <p class="text-muted mb-2">Driver ID: <strong><?php echo htmlspecialchars($driver_id_safe); ?></strong></p>
                                        <p class="text-muted mb-0">Station: <strong><?php echo htmlspecialchars($station_code); ?></strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Driver Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Full Name:</strong><br>
                                        <?php echo htmlspecialchars($driver_full_name); ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Driver ID:</strong><br>
                                        <span class="text-primary"><?php echo htmlspecialchars($driver_id_safe); ?></span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Date of Birth:</strong><br>
                                        <?php echo formatDate($date_of_birth); ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Hire Date:</strong><br>
                                        <?php echo formatDate($hire_date); ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Phone Number:</strong><br>
                                        <?php echo $phone_number ? htmlspecialchars($phone_number) : 'Not specified'; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Station:</strong><br>
                                        <?php echo htmlspecialchars($station_code . ' - ' . $station_name); ?>
                                    </div>
                                    <?php if ($address): ?>
                                    <div class="col-12 mb-3">
                                        <strong>Address:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($address)); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Salary Account - Visible to both Station Managers and Dispatchers -->
                                    <?php if ($salary_account): ?>
                                    <div class="col-12 mb-3">
                                        <strong>Salary Account (IBAN):</strong>
                                        <?php if ($user['user_type'] === 'dispatcher'): ?>
                                            <small class="text-muted">(View Only)</small>
                                        <?php endif; ?>
                                        <br>
                                        <span class="iban-display"><?php echo htmlspecialchars($salary_account); ?></span>
                                        <div class="mt-1">
                                            <small class="text-success">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Valid IBAN for salary payments
                                            </small>
                                        </div>
                                    </div>
                                    <?php elseif ($user['user_type'] === 'station_manager'): ?>
                                    <div class="col-12 mb-3">
                                        <strong>Salary Account (IBAN):</strong><br>
                                        <span class="text-muted">Not specified</span>
                                        <div class="mt-1">
                                            <small class="text-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Consider adding an IBAN for salary payments
                                            </small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-6 mb-3">
                                        <strong>Added to System:</strong><br>
                                        <small class="text-muted"><?php echo formatDateTime($created_at); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Last Updated:</strong><br>
                                        <small class="text-muted"><?php echo formatDateTime($updated_at); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Van -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Vehicle Assignment</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($van_info): ?>
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h6 class="mb-1">
                                                <i class="fas fa-truck me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($van_info['license']); ?>
                                            </h6>
                                            <?php if ($van_info['make_model']): ?>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($van_info['make_model']); ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-primary fs-6">Currently Assigned</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-truck-slash fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No vehicle currently assigned</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="edit_driver.php?id=<?php echo $driver_db_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-1"></i>Edit Driver
                                    </a>
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                                        <i class="fas fa-upload me-1"></i>Upload Documents
                                    </button>
                                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#documentsModal">
                                        <i class="fas fa-file-alt me-1"></i>View Driver Documents
                                    </button>
                                    <a href="drivers.php" class="btn btn-success">
                                        <i class="fas fa-users me-1"></i>Back to Drivers
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Driver Statistics -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Driver Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border-end">
                                            <h4 class="text-primary mb-0"><?php echo count($driver_documents); ?></h4>
                                            <small class="text-muted">Documents</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h4 class="text-success mb-0">
                                            <?php 
                                            $service_days = 0;
                                            if ($hire_date && $hire_date !== '0000-00-00') {
                                                $service_days = floor((time() - strtotime($hire_date)) / (60 * 60 * 24));
                                            }
                                            echo max(0, $service_days); 
                                            ?>
                                        </h4>
                                        <small class="text-muted">Days of Service</small>
                                    </div>
                                </div>
                                
                                <?php if ($van_info): ?>
                                <div class="alert alert-success">
                                    <small>
                                        <i class="fas fa-check-circle me-1"></i>
                                        Currently assigned to vehicle
                                    </small>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <small>
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        No vehicle assigned
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($salary_account): ?>
                                <div class="alert alert-info">
                                    <small>
                                        <i class="fas fa-university me-1"></i>
                                        Salary account configured
                                    </small>
                                </div>
                                <?php elseif ($user['user_type'] === 'station_manager'): ?>
                                <div class="alert alert-secondary">
                                    <small>
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        No salary account set
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Documents Modal -->
    <div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Driver Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="upload_driver_documents.php">
                    <div class="modal-body">
                        <input type="hidden" name="driver_id" value="<?php echo $driver_db_id; ?>">
                        
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Select Document Type</option>
                                <option value="ID">ID</option>
                                <option value="Drivers License">Driver's License</option>
                                <option value="Medical Certificate">Medical Certificate</option>
                                <option value="Background Check">Background Check</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="document_file" class="form-label">Document File</label>
                            <input type="file" class="form-control" id="document_file" name="document_file" 
                                   accept="image/jpeg,image/jpg,image/png,application/pdf" required>
                            <div class="form-text">Supported formats: JPG, PNG, PDF (max 5MB)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Documents Modal -->
    <div class="modal fade" id="documentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Driver Documents - <?php echo htmlspecialchars($driver_id_safe); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($driver_documents)): ?>
                        <div class="list-group">
                            <?php foreach ($driver_documents as $document): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-alt me-2 text-primary"></i>
                                        <div>
                                            <strong><?php echo htmlspecialchars(safeGet($document, 'document_type', 'Unknown Type')); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars(safeGet($document, 'document_name', 'Unknown Document')); ?></small>
                                            <br>
                                            <small class="text-muted">
                                                Uploaded: <?php echo formatDateTime(safeGet($document, 'created_at')); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <?php $doc_path = safeGet($document, 'document_path'); ?>
                                    <?php if ($doc_path): ?>
                                    <a href="<?php echo htmlspecialchars($doc_path); ?>" 
                                       class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <a href="<?php echo htmlspecialchars($doc_path); ?>" 
                                       class="btn btn-sm btn-secondary" download>
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">File not available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Documents</h5>
                            <p class="text-muted">No documents have been uploaded for this driver yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>