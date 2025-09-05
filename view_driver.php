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

if (!$driver) {
    header('Location: drivers.php');
    exit();
}

// Get driver documents
$stmt = $pdo->prepare("SELECT * FROM driver_documents WHERE driver_id = ? ORDER BY document_type, created_at");
$stmt->execute([$driver_id]);
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
                        Driver Details: <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>
                    </h2>
                    <div>
                        <a href="edit_driver.php?id=<?php echo $driver['id']; ?>" class="btn btn-primary me-2">
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
                                        <?php if ($driver['profile_picture'] && file_exists($driver['profile_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($driver['profile_picture']); ?>" 
                                                 alt="Profile Picture" class="profile-picture">
                                        <?php else: ?>
                                            <div class="profile-placeholder">
                                                <i class="fas fa-user fa-3x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col">
                                        <h3 class="mb-1"><?php echo htmlspecialchars($driver['first_name'] . ' ' . ($driver['middle_name'] ? $driver['middle_name'] . ' ' : '') . $driver['last_name']); ?></h3>
                                        <p class="text-muted mb-2">Driver ID: <strong><?php echo htmlspecialchars($driver['driver_id']); ?></strong></p>
                                        <p class="text-muted mb-0">Station: <strong><?php echo htmlspecialchars($driver['station_code']); ?></strong></p>
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
                                        <?php echo htmlspecialchars($driver['first_name'] . ' ' . ($driver['middle_name'] ? $driver['middle_name'] . ' ' : '') . $driver['last_name']); ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Driver ID:</strong><br>
                                        <span class="text-primary"><?php echo htmlspecialchars($driver['driver_id']); ?></span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Date of Birth:</strong><br>
                                        <?php echo $driver['date_of_birth'] ? date('M d, Y', strtotime($driver['date_of_birth'])) : 'Not specified'; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Hire Date:</strong><br>
                                        <?php echo $driver['hire_date'] ? date('M d, Y', strtotime($driver['hire_date'])) : 'Not specified'; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Phone Number:</strong><br>
                                        <?php echo $driver['phone_number'] ? htmlspecialchars($driver['phone_number']) : 'Not specified'; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Station:</strong><br>
                                        <?php echo htmlspecialchars($driver['station_code'] . ' - ' . $driver['station_name']); ?>
                                    </div>
                                    <?php if ($driver['address']): ?>
                                    <div class="col-12 mb-3">
                                        <strong>Address:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($driver['address'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-6 mb-3">
                                        <strong>Added to System:</strong><br>
                                        <small class="text-muted"><?php echo date('M d, Y \a\t g:i A', strtotime($driver['created_at'])); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Last Updated:</strong><br>
                                        <small class="text-muted"><?php echo date('M d, Y \a\t g:i A', strtotime($driver['updated_at'])); ?></small>
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
                                <?php if ($driver['van_license']): ?>
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h6 class="mb-1">
                                                <i class="fas fa-truck me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($driver['van_license']); ?>
                                            </h6>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($driver['van_make'] . ' ' . $driver['van_model']); ?>
                                            </p>
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
                                    <a href="edit_driver.php?id=<?php echo $driver['id']; ?>" class="btn btn-primary">
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
                                            $service_days = $driver['hire_date'] ? floor((time() - strtotime($driver['hire_date'])) / (60 * 60 * 24)) : 0;
                                            echo $service_days; 
                                            ?>
                                        </h4>
                                        <small class="text-muted">Days of Service</small>
                                    </div>
                                </div>
                                <?php if ($driver['van_license']): ?>
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
                        <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                        
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
                    <h5 class="modal-title">Driver Documents - <?php echo htmlspecialchars($driver['driver_id']); ?></h5>
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
                                            <strong><?php echo htmlspecialchars($document['document_type']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($document['document_name']); ?></small>
                                            <br>
                                            <small class="text-muted">
                                                Uploaded: <?php echo date('M d, Y \a\t g:i A', strtotime($document['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <a href="<?php echo htmlspecialchars($document['document_path']); ?>" 
                                       class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <a href="<?php echo htmlspecialchars($document['document_path']); ?>" 
                                       class="btn btn-sm btn-secondary" download>
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
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