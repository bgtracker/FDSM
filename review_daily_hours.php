<?php
require_once 'config.php';
requireLogin();

$current_page = 'working_hours';
$page_title = 'Review Daily Hours - Van Fleet Management';
$user = getCurrentUser();

$review_date = isset($_GET['date']) ? $_GET['date'] : '';
$station_id = isset($_GET['station']) ? intval($_GET['station']) : 0;
$message = '';

if (!$review_date || !$station_id) {
    header('Location: manage_working_hours.php');
    exit();
}

// Verify user has access to this station
if ($user['user_type'] === 'dispatcher' && $station_id !== $user['station_id']) {
    header('Location: manage_working_hours.php');
    exit();
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_submission'])) {
        $submission_id = intval($_POST['submission_id']);
        
        try {
            $stmt = $pdo->prepare("UPDATE working_hours SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND station_id = ?");
            $stmt->execute([$user['id'], $submission_id, $station_id]);
            
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Working hours approved successfully.</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error approving submission.</div>';
        }
    } elseif (isset($_POST['reject_submission'])) {
        $submission_id = intval($_POST['submission_id']);
        $rejection_reason = trim($_POST['rejection_reason']);
        
        if (empty($rejection_reason)) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Please provide a reason for rejection.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE working_hours SET status = 'rejected', rejection_reason = ? WHERE id = ? AND station_id = ?");
                $stmt->execute([$rejection_reason, $submission_id, $station_id]);
                
                $message = '<div class="alert alert-warning"><i class="fas fa-times-circle me-2"></i>Working hours rejected.</div>';
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error rejecting submission.</div>';
            }
        }
    } elseif (isset($_POST['edit_submission'])) {
        $submission_id = intval($_POST['submission_id']);
        $edit_reason = trim($_POST['edit_reason']);
        
        // Get original values for logging
        $stmt = $pdo->prepare("SELECT * FROM working_hours WHERE id = ? AND station_id = ?");
        $stmt->execute([$submission_id, $station_id]);
        $original = $stmt->fetch();
        
        if (!$original) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Submission not found.</div>';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Collect edited fields
                $updates = [];
                $params = [];
                $edits = [];
                
                $editable_fields = [
                    'tour_number' => 'Tour Number',
                    'km_start' => 'Start KM',
                    'km_end' => 'End KM', 
                    'scanner_login' => 'Scanner Login',
                    'depo_departure' => 'Depot Departure',
                    'first_delivery' => 'First Delivery',
                    'last_delivery' => 'Last Delivery',
                    'depo_return' => 'Depot Return'
                ];
                
                foreach ($editable_fields as $field => $label) {
                    $new_value = trim($_POST[$field]);
                    $old_value = $original[$field];
                    
                    if ($new_value !== $old_value) {
                        $updates[] = "$field = ?";
                        $params[] = $new_value;
                        $edits[] = [
                            'field' => $field,
                            'label' => $label,
                            'old_value' => $old_value,
                            'new_value' => $new_value
                        ];
                    }
                }
                
                if (!empty($updates)) {
                    // Recalculate working hours if times were changed
                    $time_fields = ['scanner_login', 'depo_departure', 'first_delivery', 'last_delivery', 'depo_return'];
                    $times_changed = false;
                    
                    foreach ($time_fields as $field) {
                        if (trim($_POST[$field]) !== $original[$field]) {
                            $times_changed = true;
                            break;
                        }
                    }
                    
                    if ($times_changed) {
                        $login_time = new DateTime($review_date . ' ' . $_POST['scanner_login']);
                        $return_time = new DateTime($review_date . ' ' . $_POST['depo_return']);
                        
                        // Handle overnight shifts
                        if ($return_time < $login_time) {
                            $return_time->add(new DateInterval('P1D'));
                        }
                        
                        $total_minutes = ($return_time->getTimestamp() - $login_time->getTimestamp()) / 60;
                        $break_minutes = $total_minutes > (9 * 60) ? 45 : 30;
                        $working_minutes = $total_minutes - $break_minutes;
                        
                        $updates[] = "break_minutes = ?";
                        $updates[] = "total_minutes = ?";
                        $params[] = $break_minutes;
                        $params[] = $working_minutes;
                    }
                    
                    // Update submission
                    $sql = "UPDATE working_hours SET " . implode(', ', $updates) . ", status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
                    $params[] = $user['id'];
                    $params[] = $submission_id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    // Log edits
                    foreach ($edits as $edit) {
                        $stmt = $pdo->prepare("INSERT INTO working_hours_edits (working_hours_id, edited_by, field_name, old_value, new_value, edit_reason) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$submission_id, $user['id'], $edit['field'], $edit['old_value'], $edit['new_value'], $edit_reason]);
                    }
                    
                    $pdo->commit();
                    $message = '<div class="alert alert-success"><i class="fas fa-edit me-2"></i>Working hours updated and approved successfully.</div>';
                } else {
                    // No changes, just approve
                    $stmt = $pdo->prepare("UPDATE working_hours SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                    $stmt->execute([$user['id'], $submission_id]);
                    
                    $pdo->commit();
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Working hours approved successfully.</div>';
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error updating submission: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Get station info
$stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
$stmt->execute([$station_id]);
$station = $stmt->fetch();

// Get submissions for this date and station
$stmt = $pdo->prepare("
    SELECT wh.*, d.driver_id, d.first_name, d.middle_name, d.last_name, d.profile_picture,
           v.license_plate, v.make, v.model,
           u.first_name as approved_by_first, u.last_name as approved_by_last
    FROM working_hours wh 
    JOIN drivers d ON wh.driver_id = d.id 
    LEFT JOIN vans v ON wh.van_id = v.id
    LEFT JOIN users u ON wh.approved_by = u.id
    WHERE wh.station_id = ? AND wh.work_date = ?
    ORDER BY wh.status ASC, d.last_name, d.first_name
");
$stmt->execute([$station_id, $review_date]);
$submissions = $stmt->fetchAll();

// Get drivers who haven't submitted (only check if not on leave)
$stmt = $pdo->prepare("SELECT id, driver_id, first_name, middle_name, last_name FROM drivers WHERE station_id = ? ORDER BY last_name, first_name");
$stmt->execute([$station_id]);
$all_drivers = $stmt->fetchAll();

$submitted_driver_ids = array_column($submissions, 'driver_id');

// Check for leaves on this date
$stmt = $pdo->prepare("
    SELECT DISTINCT dl.driver_id, dl.leave_type, d.driver_id as driver_code, d.first_name, d.last_name
    FROM driver_leaves dl
    JOIN drivers d ON dl.driver_id = d.id
    WHERE dl.station_id = ? AND ? BETWEEN dl.start_date AND dl.end_date
");
$stmt->execute([$station_id, $review_date]);
$drivers_on_leave = $stmt->fetchAll();
$leave_driver_ids = array_column($drivers_on_leave, 'driver_id');

// Find missing drivers
$missing_drivers = [];
foreach ($all_drivers as $driver) {
    if (!in_array($driver['id'], $submitted_driver_ids) && !in_array($driver['id'], $leave_driver_ids)) {
        $missing_drivers[] = $driver;
    }
}

// Helper functions
function formatMinutesToHours($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%dh %02dm', $hours, $mins);
}

function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending Review</span>';
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
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
        .submission-card {
            border-radius: 10px;
            transition: transform 0.2s ease;
            margin-bottom: 1rem;
        }
        .submission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .submission-pending {
            border-left: 4px solid #ffc107;
        }
        .submission-approved {
            border-left: 4px solid #198754;
        }
        .submission-rejected {
            border-left: 4px solid #dc3545;
        }
        .driver-photo {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }
        .time-input {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .edit-mode {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
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
                        <i class="fas fa-calendar-day me-2"></i>
                        Review Hours - <?php echo date('l, F d, Y', strtotime($review_date)); ?>
                    </h2>
                    <a href="manage_working_hours.php?station=<?php echo $station_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Calendar
                    </a>
                </div>

                <div class="alert alert-info">
                    <h5><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($station['station_code'] . ' - ' . $station['station_name']); ?></h5>
                    <p class="mb-0">Reviewing working hours submissions for this station on the selected date.</p>
                </div>

                <?php echo $message; ?>

                <!-- Summary Card -->
                <div class="card summary-card mb-4">
                    <div class="card-body">
                        <h5>Daily Summary</h5>
                        <div class="row text-center">
                            <div class="col-6 col-md-3 mb-2">
                                <h4><?php echo count($submissions); ?></h4>
                                <small>Submissions</small>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <h4><?php echo count(array_filter($submissions, function($s) { return $s['status'] === 'pending'; })); ?></h4>
                                <small>Pending</small>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <h4><?php echo count($missing_drivers); ?></h4>
                                <small>Missing</small>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <h4><?php echo count($drivers_on_leave); ?></h4>
                                <small>On Leave</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Missing Drivers Warning -->
                <?php if (!empty($missing_drivers)): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Missing Submissions</h5>
                    <p>The following drivers have not submitted working hours for this date and are not on leave:</p>
                    <ul class="mb-0">
                        <?php foreach ($missing_drivers as $driver): ?>
                        <li>
                            <?php echo htmlspecialchars($driver['driver_id'] . ' - ' . $driver['first_name'] . ' ' . $driver['last_name']); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn btn-sm btn-warning mt-2" onclick="dismissMissingWarning()">
                        <i class="fas fa-times me-1"></i>Dismiss Warning
                    </button>
                </div>
                <?php endif; ?>

                <!-- Drivers on Leave -->
                <?php if (!empty($drivers_on_leave)): ?>
                <div class="alert alert-info">
                    <h6><i class="fas fa-calendar-times me-2"></i>Drivers on Leave</h6>
                    <ul class="mb-0">
                        <?php foreach ($drivers_on_leave as $leave): ?>
                        <li>
                            <?php echo htmlspecialchars($leave['driver_code'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?>
                            <span class="badge bg-<?php echo $leave['leave_type'] === 'paid' ? 'primary' : 'danger'; ?> ms-2">
                                <?php echo ucfirst($leave['leave_type']); ?> Leave
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Submissions -->
                <?php if (empty($submissions)): ?>
                <div class="alert alert-secondary text-center">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <h5>No Submissions</h5>
                    <p class="mb-0">No working hours have been submitted for this date.</p>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($submissions as $submission): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card submission-card submission-<?php echo $submission['status']; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <?php if ($submission['profile_picture'] && file_exists($submission['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($submission['profile_picture']); ?>" 
                                             alt="Driver Photo" class="driver-photo me-2">
                                    <?php else: ?>
                                        <div class="driver-photo me-2 bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($submission['driver_id']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></small>
                                    </div>
                                </div>
                                <?php echo getStatusBadge($submission['status']); ?>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Tour:</small><br>
                                        <strong><?php echo htmlspecialchars($submission['tour_number']); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Vehicle:</small><br>
                                        <?php echo htmlspecialchars($submission['license_plate']); ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-4 text-center">
                                        <h6 class="text-primary mb-0"><?php echo number_format($submission['km_total']); ?></h6>
                                        <small class="text-muted">KM</small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <h6 class="text-success mb-0"><?php echo formatMinutesToHours($submission['total_minutes']); ?></h6>
                                        <small class="text-muted">Hours</small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <h6 class="text-info mb-0"><?php echo $submission['break_minutes']; ?>m</h6>
                                        <small class="text-muted">Break</small>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <?php if ($submission['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal<?php echo $submission['id']; ?>">
                                            <i class="fas fa-eye me-1"></i>Review & Approve
                                        </button>
                                    <?php elseif ($submission['status'] === 'approved'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal<?php echo $submission['id']; ?>">
                                            <i class="fas fa-check me-1"></i>View Approved
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal<?php echo $submission['id']; ?>">
                                            <i class="fas fa-times me-1"></i>View Rejected
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($submission['status'] === 'approved'): ?>
                            <div class="card-footer">
                                <small class="text-muted">
                                    <i class="fas fa-check me-1"></i>
                                    Approved by <?php echo htmlspecialchars($submission['approved_by_first'] . ' ' . $submission['approved_by_last']); ?>
                                    on <?php echo date('M d, Y g:i A', strtotime($submission['approved_at'])); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Detail Modal -->
                    <div class="modal fade" id="detailModal<?php echo $submission['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        Review Hours - <?php echo htmlspecialchars($submission['driver_id'] . ' - ' . $submission['first_name'] . ' ' . $submission['last_name']); ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                
                                <?php if ($submission['status'] === 'pending'): ?>
                                <!-- Edit Form for Pending Submissions -->
                                <form method="POST" id="editForm<?php echo $submission['id']; ?>">
                                    <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Tour Details</h6>
                                                <div class="mb-3">
                                                    <label class="form-label">Tour Number</label>
                                                    <input type="text" class="form-control" name="tour_number" 
                                                           value="<?php echo htmlspecialchars($submission['tour_number']); ?>" maxlength="7">
                                                </div>
                                                
                                                <h6>Kilometers</h6>
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Start KM</label>
                                                        <input type="number" class="form-control km-input" name="km_start" 
                                                               value="<?php echo $submission['km_start']; ?>" min="0">
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">End KM</label>
                                                        <input type="number" class="form-control km-input" name="km_end" 
                                                               value="<?php echo $submission['km_end']; ?>" min="0">
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Total KM</label>
                                                    <input type="number" class="form-control" id="km_total<?php echo $submission['id']; ?>" readonly>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <h6>Working Times</h6>
                                                <div class="mb-3">
                                                    <label class="form-label">Scanner Login</label>
                                                    <input type="time" class="form-control time-input time-calc" name="scanner_login" 
                                                           value="<?php echo date('H:i', strtotime($submission['scanner_login'])); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Depot Departure</label>
                                                    <input type="time" class="form-control time-input" name="depo_departure" 
                                                           value="<?php echo date('H:i', strtotime($submission['depo_departure'])); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">First Delivery</label>
                                                    <input type="time" class="form-control time-input" name="first_delivery" 
                                                           value="<?php echo date('H:i', strtotime($submission['first_delivery'])); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Last Delivery</label>
                                                    <input type="time" class="form-control time-input" name="last_delivery" 
                                                           value="<?php echo date('H:i', strtotime($submission['last_delivery'])); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Depot Return</label>
                                                    <input type="time" class="form-control time-input time-calc" name="depo_return" 
                                                           value="<?php echo date('H:i', strtotime($submission['depo_return'])); ?>">
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Break</label>
                                                        <input type="text" class="form-control" id="break_display<?php echo $submission['id']; ?>" readonly>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Total Hours</label>
                                                        <input type="text" class="form-control" id="total_hours_display<?php echo $submission['id']; ?>" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="edit_reason" class="form-label">Edit Reason (if making changes)</label>
                                            <textarea class="form-control" name="edit_reason" rows="2" 
                                                      placeholder="Explain why you are making changes..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-danger me-auto" onclick="showRejectForm(<?php echo $submission['id']; ?>)">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                        <button type="submit" name="edit_submission" class="btn btn-success">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <!-- View Only for Approved/Rejected -->
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Tour Details</h6>
                                            <p><strong>Tour Number:</strong> <?php echo htmlspecialchars($submission['tour_number']); ?></p>
                                            <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($submission['license_plate'] . ' (' . $submission['make'] . ' ' . $submission['model'] . ')'); ?></p>
                                            
                                            <h6>Kilometers</h6>
                                            <p><strong>Start:</strong> <?php echo number_format($submission['km_start']); ?> km</p>
                                            <p><strong>End:</strong> <?php echo number_format($submission['km_end']); ?> km</p>
                                            <p><strong>Total:</strong> <?php echo number_format($submission['km_total']); ?> km</p>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6>Working Times</h6>
                                            <p><strong>Scanner Login:</strong> <?php echo date('H:i', strtotime($submission['scanner_login'])); ?></p>
                                            <p><strong>Depot Departure:</strong> <?php echo date('H:i', strtotime($submission['depo_departure'])); ?></p>
                                            <p><strong>First Delivery:</strong> <?php echo date('H:i', strtotime($submission['first_delivery'])); ?></p>
                                            <p><strong>Last Delivery:</strong> <?php echo date('H:i', strtotime($submission['last_delivery'])); ?></p>
                                            <p><strong>Depot Return:</strong> <?php echo date('H:i', strtotime($submission['depo_return'])); ?></p>
                                            <p><strong>Break:</strong> <?php echo $submission['break_minutes']; ?> minutes</p>
                                            <p><strong>Total Hours:</strong> <?php echo formatMinutesToHours($submission['total_minutes']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($submission['status'] === 'approved'): ?>
                                    <div class="alert alert-success">
                                        <strong>Approved by:</strong> <?php echo htmlspecialchars($submission['approved_by_first'] . ' ' . $submission['approved_by_last']); ?><br>
                                        <strong>Approved on:</strong> <?php echo date('M d, Y \a\t g:i A', strtotime($submission['approved_at'])); ?>
                                    </div>
                                    <?php elseif ($submission['status'] === 'rejected'): ?>
                                    <div class="alert alert-danger">
                                        <strong>Rejection Reason:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($submission['rejection_reason'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Reject Form Modal -->
                    <div class="modal fade" id="rejectModal<?php echo $submission['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reject Submission</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                    <div class="modal-body">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            You are about to reject the working hours submission for 
                                            <strong><?php echo htmlspecialchars($submission['driver_id']); ?></strong>.
                                        </div>
                                        <div class="mb-3">
                                            <label for="rejection_reason" class="form-label">Reason for Rejection *</label>
                                            <textarea class="form-control" name="rejection_reason" rows="4" required
                                                      placeholder="Please provide a clear reason for rejecting this submission..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="reject_submission" class="btn btn-danger">
                                            <i class="fas fa-times me-1"></i>Reject Submission
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function dismissMissingWarning() {
            document.querySelector('.alert-warning').style.display = 'none';
        }

        function showRejectForm(submissionId) {
            // Hide detail modal
            const detailModal = bootstrap.Modal.getInstance(document.getElementById('detailModal' + submissionId));
            detailModal.hide();
            
            // Show reject modal
            setTimeout(() => {
                const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal' + submissionId));
                rejectModal.show();
            }, 300);
        }

        // Initialize calculations for each form
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($submissions as $submission): ?>
            <?php if ($submission['status'] === 'pending'): ?>
            setupCalculations(<?php echo $submission['id']; ?>);
            <?php endif; ?>
            <?php endforeach; ?>
        });

        function setupCalculations(submissionId) {
            const form = document.getElementById('editForm' + submissionId);
            
            // KM calculation
            function calculateKM() {
                const start = parseInt(form.querySelector('[name="km_start"]').value) || 0;
                const end = parseInt(form.querySelector('[name="km_end"]').value) || 0;
                const total = Math.max(0, end - start);
                document.getElementById('km_total' + submissionId).value = total;
            }
            
            // Hours calculation
            function calculateHours() {
                const scannerLogin = form.querySelector('[name="scanner_login"]').value;
                const depoReturn = form.querySelector('[name="depo_return"]').value;
                
                if (scannerLogin && depoReturn) {
                    try {
                        const workDate = '<?php echo $review_date; ?>';
                        let loginTime = new Date(workDate + 'T' + scannerLogin);
                        let returnTime = new Date(workDate + 'T' + depoReturn);
                        
                        // Handle overnight shifts
                        if (returnTime < loginTime) {
                            returnTime.setDate(returnTime.getDate() + 1);
                        }
                        
                        const totalMinutes = (returnTime - loginTime) / (1000 * 60);
                        const breakMinutes = totalMinutes > (9 * 60) ? 45 : 30;
                        const workingMinutes = totalMinutes - breakMinutes;
                        
                        document.getElementById('break_display' + submissionId).value = breakMinutes + ' minutes';
                        
                        if (workingMinutes > 0) {
                            const hours = Math.floor(workingMinutes / 60);
                            const minutes = Math.round(workingMinutes % 60);
                            document.getElementById('total_hours_display' + submissionId).value = hours + 'h ' + minutes + 'm';
                        } else {
                            document.getElementById('total_hours_display' + submissionId).value = 'Invalid times';
                        }
                    } catch (error) {
                        document.getElementById('total_hours_display' + submissionId).value = 'Error calculating';
                    }
                }
            }
            
            // Add event listeners
            form.querySelectorAll('.km-input').forEach(input => {
                input.addEventListener('input', calculateKM);
            });
            
            form.querySelectorAll('.time-calc').forEach(input => {
                input.addEventListener('input', calculateHours);
            });
            
            // Initial calculations
            calculateKM();
            calculateHours();
        }
    </script>
</body>
</html>