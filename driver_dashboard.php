<?php
require_once 'config.php';
requireDriverLogin();

$current_page = 'dashboard';
$page_title = 'Driver Dashboard - Van Fleet Management';
$driver = getCurrentDriver();
$message = '';

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $document_type = trim($_POST['document_type']);
    $error_message = '';
    $success_message = '';
    
    if (empty($document_type) || empty($_FILES['document_file']['name'])) {
        $error_message = 'Please select document type and file.';
    } else {
        if ($_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $file_size = $_FILES['document_file']['size'];
            $file_tmp = $_FILES['document_file']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
            $original_name = $_FILES['document_file']['name'];
            
            if ($file_size > MAX_IMAGE_SIZE) {
                $error_message = 'Document file is too large (max 5MB).';
            } elseif (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $error_message = 'Invalid document format. Only JPG, PNG, and PDF are allowed.';
            } else {
                try {
                    $new_filename = $driver['id'] . '_' . strtolower(str_replace(' ', '_', $document_type)) . '_' . time() . '.' . $file_ext;
                    $upload_path = UPLOAD_DIR . 'drivers/documents/' . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $stmt = $pdo->prepare("INSERT INTO driver_documents (driver_id, document_type, document_path, document_name) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$driver['id'], $document_type, $upload_path, $original_name]);
                        
                        $success_message = 'Document uploaded successfully!';
                    } else {
                        $error_message = 'Failed to upload document file.';
                    }
                } catch (Exception $e) {
                    $error_message = 'Error uploading document: ' . $e->getMessage();
                }
            }
        } else {
            $error_message = 'Error uploading file. Please try again.';
        }
    }
    
    if ($success_message) {
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($success_message) . '</div>';
    } elseif ($error_message) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($error_message) . '</div>';
    }
}

// Get assigned van information
$assigned_van = null;
if ($driver['van_id']) {
    $stmt = $pdo->prepare("SELECT v.*, s.station_code, s.station_name FROM vans v LEFT JOIN stations s ON v.station_id = s.id WHERE v.id = ?");
    $stmt->execute([$driver['van_id']]);
    $assigned_van = $stmt->fetch();
}

// Get driver documents count
$stmt = $pdo->prepare("SELECT COUNT(*) as doc_count FROM driver_documents WHERE driver_id = ?");
$stmt->execute([$driver['id']]);
$document_count = $stmt->fetchColumn();

// Get driver documents for display
$stmt = $pdo->prepare("SELECT * FROM driver_documents WHERE driver_id = ? ORDER BY document_type, created_at DESC");
$stmt->execute([$driver['id']]);
$driver_documents = $stmt->fetchAll();

// Get current month working hours statistics (only approved hours)
$current_month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(km_total), 0) as total_km,
        COUNT(*) as total_tours,
        COALESCE(SUM(total_minutes), 0) as total_minutes
    FROM working_hours 
    WHERE driver_id = ? 
    AND DATE_FORMAT(work_date, '%Y-%m') = ? 
    AND status = 'approved'
");
$stmt->execute([$driver['id'], $current_month]);
$monthly_stats = $stmt->fetch();

$total_km = $monthly_stats['total_km'] ?? 0;
$total_tours = $monthly_stats['total_tours'] ?? 0;
$total_hours = round(($monthly_stats['total_minutes'] ?? 0) / 60, 1);

// Check if driver has submitted hours for today
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT id, status FROM working_hours WHERE driver_id = ? AND work_date = ?");
$stmt->execute([$driver['id'], $today]);
$today_submission = $stmt->fetch();

// Helper function to get van status badge
function getVanStatusBadge($status) {
    switch($status) {
        case 'available':
            return '<span class="badge bg-success">Available</span>';
        case 'in_use':
            return '<span class="badge bg-primary">In Use</span>';
        case 'reserve':
            return '<span class="badge bg-warning">Reserve</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
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
        .hero-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 60px 0;
        }
        .feature-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .van-info-card {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
            border: none;
        }
        .hours-card {
            background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%);
            color: white;
            border: none;
        }
        .profile-picture {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
        }
        .profile-placeholder {
            width: 80px;
            height: 80px;
            background-color: rgba(255,255,255,0.2);
            border: 3px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .stat-card {
            border-radius: 15px;
            border: none;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 mb-3">Welcome, <?php echo htmlspecialchars($driver['first_name']); ?>!</h1>
                    <p class="lead mb-2">Driver ID: <?php echo htmlspecialchars($driver['driver_id']); ?></p>
                    <p class="mb-0">Station: <?php echo htmlspecialchars($driver['station_code']); ?> - <?php echo htmlspecialchars($driver['station_name']); ?></p>
                </div>
                <div class="col-lg-4 text-center">
                    <?php if ($driver['profile_picture'] && file_exists($driver['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($driver['profile_picture']); ?>" 
                             alt="Profile Picture" class="profile-picture">
                    <?php else: ?>
                        <div class="profile-placeholder mx-auto">
                            <i class="fas fa-user fa-2x"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <?php echo $message; ?>
        
        <!-- Monthly Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3">
                    <i class="fas fa-chart-line me-2"></i>
                    This Month Statistics (<?php echo date('F Y'); ?>)
                    <small class="text-muted">- Approved Hours Only</small>
                </h4>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-primary mx-auto">
                            <i class="fas fa-road"></i>
                        </div>
                        <h3 class="text-primary mb-1"><?php echo number_format($total_km); ?></h3>
                        <h6 class="text-muted mb-0">Kilometers Driven</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-success mx-auto">
                            <i class="fas fa-route"></i>
                        </div>
                        <h3 class="text-success mb-1"><?php echo $total_tours; ?></h3>
                        <h6 class="text-muted mb-0">Tours Completed</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-warning mx-auto">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="text-warning mb-1"><?php echo $total_hours; ?>h</h3>
                        <h6 class="text-muted mb-0">Working Hours</h6>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Working Hours Section -->
            <div class="col-lg-8 mb-4">
                <div class="card hours-card">
                    <div class="card-header border-0">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-clock me-2"></i>Working Hours Submission
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="text-white mb-2">Daily Hours Tracking</h6>
                                <?php if ($today_submission): ?>
                                    <?php if ($today_submission['status'] === 'pending'): ?>
                                        <p class="mb-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <strong>Today's Status:</strong> 
                                            <span class="badge bg-warning">Pending Review</span>
                                        </p>
                                        <p class="mb-0 small">Your hours for today have been submitted and are awaiting approval.</p>
                                    <?php elseif ($today_submission['status'] === 'approved'): ?>
                                        <p class="mb-2">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <strong>Today's Status:</strong> 
                                            <span class="badge bg-success">Approved</span>
                                        </p>
                                        <p class="mb-0 small">Your hours for today have been approved.</p>
                                    <?php elseif ($today_submission['status'] === 'rejected'): ?>
                                        <p class="mb-2">
                                            <i class="fas fa-times-circle me-1"></i>
                                            <strong>Today's Status:</strong> 
                                            <span class="badge bg-danger">Rejected</span>
                                        </p>
                                        <p class="mb-0 small">Your hours were rejected. Please resubmit with corrections.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="mb-2">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <strong>Today's Status:</strong> 
                                        <span class="badge bg-secondary">Not Submitted</span>
                                    </p>
                                    <p class="mb-0 small">Remember to submit your working hours for today.</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-clock fa-4x text-white opacity-75"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <?php if (!$today_submission || $today_submission['status'] === 'rejected'): ?>
                                <a href="submit_working_hours.php" class="btn btn-light btn-lg">
                                    <i class="fas fa-plus me-2"></i>Submit Daily Hours
                                </a>
                            <?php else: ?>
                                <a href="my_working_hours.php" class="btn btn-light btn-lg">
                                    <i class="fas fa-eye me-2"></i>View My Submissions
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Assigned Vehicle Information -->
                <?php if ($assigned_van): ?>
                <div class="card van-info-card mt-4">
                    <div class="card-header border-0">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-truck me-2"></i>Your Assigned Vehicle
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="text-white mb-2"><?php echo htmlspecialchars($assigned_van['license_plate']); ?></h3>
                                <p class="mb-1"><strong>Make & Model:</strong> <?php echo htmlspecialchars($assigned_van['make'] . ' ' . $assigned_van['model']); ?></p>
                                <p class="mb-1"><strong>Station:</strong> <?php echo htmlspecialchars($assigned_van['station_code']); ?></p>
                                <p class="mb-1"><strong>Status:</strong> <?php echo getVanStatusBadge($assigned_van['status']); ?></p>
                                <?php if ($assigned_van['vin_number']): ?>
                                <p class="mb-0"><strong>VIN:</strong> <code class="text-light"><?php echo htmlspecialchars($assigned_van['vin_number']); ?></code></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-truck fa-5x text-white opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mt-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-truck-slash fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Vehicle Assigned</h5>
                        <p class="text-muted mb-0">You don't have a vehicle assigned at the moment. Please contact your dispatcher.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-md-4 mb-3">
                        <div class="card feature-card text-center">
                            <div class="card-body">
                                <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                                <h6>Work Days</h6>
                                <h4 class="text-success">
                                    <?php 
                                    $work_days = 0;
                                    if ($driver['hire_date'] && $driver['hire_date'] !== '0000-00-00') {
                                        $work_days = floor((time() - strtotime($driver['hire_date'])) / (60 * 60 * 24));
                                    }
                                    echo max(0, $work_days); 
                                    ?>
                                </h4>
                                <small class="text-muted">Days of Service</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card feature-card text-center">
                            <div class="card-body">
                                <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                                <h6>Documents</h6>
                                <h4 class="text-info"><?php echo $document_count; ?></h4>
                                <small class="text-muted">Uploaded Documents</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card feature-card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-check fa-2x text-primary mb-2"></i>
                                <h6>Status</h6>
                                <h4 class="text-primary">Active</h4>
                                <small class="text-muted">Driver Status</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Profile Documents -->
                <div class="card feature-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>Profile Documents
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Upload and manage your personal documents</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                <i class="fas fa-upload me-1"></i>Upload Document
                            </button>
                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewDocumentsModal">
                                <i class="fas fa-eye me-1"></i>View My Documents
                            </button>
                        </div>
                        
                        <?php if ($document_count > 0): ?>
                        <div class="mt-3">
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i>
                                You have <?php echo $document_count; ?> document(s) uploaded
                            </small>
                        </div>
                        <?php else: ?>
                        <div class="mt-3">
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                No documents uploaded yet
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card feature-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="submit_working_hours.php" class="btn btn-warning">
                                <i class="fas fa-clock me-1"></i>Submit Hours
                            </a>
                            
                            <a href="my_working_hours.php" class="btn btn-outline-warning">
                                <i class="fas fa-history me-1"></i>View Submissions
                            </a>
                            
                            <?php if ($assigned_van): ?>
                            <button type="button" class="btn btn-success" disabled>
                                <i class="fas fa-truck me-1"></i>Vehicle Assigned
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-outline-warning" disabled>
                                <i class="fas fa-truck-slash me-1"></i>No Vehicle
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#profileModal">
                                <i class="fas fa-user me-1"></i>View Profile
                            </button>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Track your daily working hours!
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type *</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Select Document Type</option>
                                <option value="ID">Personal ID</option>
                                <option value="Drivers License">Driver's License</option>
                                <option value="Medical Certificate">Medical Certificate</option>
                                <option value="Background Check">Background Check</option>
                                <option value="Bank Account Confirmation">Bank Account Confirmation</option>
                                <option value="Insurance">Insurance Documents</option>
                                <option value="Training Certificate">Training Certificate</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="document_file" class="form-label">Document File *</label>
                            <input type="file" class="form-control" id="document_file" name="document_file" 
                                   accept="image/jpeg,image/jpg,image/png,application/pdf" required>
                            <div class="form-text">Supported formats: JPG, PNG, PDF (max 5MB)</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Note:</strong> All uploaded documents will be reviewed by your station manager. 
                                Make sure your documents are clear and valid.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_document" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Documents Modal -->
    <div class="modal fade" id="viewDocumentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">My Documents</h5>
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
                            <p class="text-muted">You haven't uploaded any documents yet. Click "Upload Document" to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="openUploadFromView()">
                        <i class="fas fa-plus me-1"></i>Upload New Document
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">My Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <?php if ($driver['profile_picture'] && file_exists($driver['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($driver['profile_picture']); ?>" 
                                 alt="Profile Picture" class="profile-picture">
                        <?php else: ?>
                            <div class="profile-placeholder mx-auto">
                                <i class="fas fa-user fa-2x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <strong>Name:</strong><br>
                            <?php echo htmlspecialchars(trim($driver['first_name'] . ' ' . ($driver['middle_name'] ? $driver['middle_name'] . ' ' : '') . $driver['last_name'])); ?>
                        </div>
                        <div class="col-6 mb-3">
                            <strong>Driver ID:</strong><br>
                            <?php echo htmlspecialchars($driver['driver_id']); ?>
                        </div>
                        <div class="col-6 mb-3">
                            <strong>Station:</strong><br>
                            <?php echo htmlspecialchars($driver['station_code']); ?>
                        </div>
                        <div class="col-6 mb-3">
                            <strong>Hire Date:</strong><br>
                            <?php echo $driver['hire_date'] ? date('M d, Y', strtotime($driver['hire_date'])) : 'Not specified'; ?>
                        </div>
                        <?php if ($driver['phone_number']): ?>
                        <div class="col-12 mb-3">
                            <strong>Phone:</strong><br>
                            <?php echo htmlspecialchars($driver['phone_number']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($driver['address']): ?>
                        <div class="col-12 mb-3">
                            <strong>Address:</strong><br>
                            <?php echo nl2br(htmlspecialchars($driver['address'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            To update your profile information, please contact your station manager.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to open upload modal from view documents modal
        function openUploadFromView() {
            // Close view documents modal
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewDocumentsModal'));
            viewModal.hide();
            
            // Wait for close animation, then open upload modal
            setTimeout(() => {
                const uploadModal = new bootstrap.Modal(document.getElementById('uploadDocumentModal'));
                uploadModal.show();
            }, 300);
        }
    </script>
</body>
</html>