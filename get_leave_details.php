<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$date = isset($_GET['date']) ? $_GET['date'] : '';
$station_id = isset($_GET['station']) ? intval($_GET['station']) : 0;

if (!$date || !$station_id) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit;
}

// Verify user has access to this station
if ($user['user_type'] === 'dispatcher' && $station_id !== $user['station_id']) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

// Get leaves for this specific date
$stmt = $pdo->prepare("
    SELECT l.*, d.first_name, d.middle_name, d.last_name, d.driver_id, u.username as created_by_name
    FROM driver_leaves l
    JOIN drivers d ON l.driver_id = d.id
    JOIN users u ON l.created_by = u.id
    WHERE l.station_id = ?
    AND ? BETWEEN l.start_date AND l.end_date
    ORDER BY l.leave_type, d.last_name, d.first_name
");
$stmt->execute([$station_id, $date]);
$leaves = $stmt->fetchAll();

$today = date('Y-m-d');
$is_past = $date < $today;
$can_edit = ($user['user_type'] === 'station_manager') || (!$is_past && $user['user_type'] === 'dispatcher');

if (empty($leaves)) {
    echo '<div class="text-center py-4">';
    echo '<i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>';
    echo '<p class="text-muted">No leaves scheduled for this date.</p>';
    if ($can_edit) {
        echo '<p class="text-muted">Click "Add Leave" to schedule a leave for this date.</p>';
    }
    echo '</div>';
} else {
    // Group by leave type
    $paid_leaves = array_filter($leaves, function($l) { return $l['leave_type'] === 'paid'; });
    $sick_leaves = array_filter($leaves, function($l) { return $l['leave_type'] === 'sick'; });
    
    echo '<div class="leave-summary mb-3">';
    echo '<div class="row">';
    echo '<div class="col-6">';
    echo '<div class="alert alert-success">';
    echo '<strong>Paid Leaves: ' . count($paid_leaves) . '</strong>';
    echo '</div>';
    echo '</div>';
    echo '<div class="col-6">';
    echo '<div class="alert alert-danger">';
    echo '<strong>Sick Leaves: ' . count($sick_leaves) . '</strong>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="leave-list">';
    echo '<h6>Leave Details:</h6>';
    echo '<div class="list-group">';
    
    foreach ($leaves as $leave) {
        $badge_class = $leave['leave_type'] === 'paid' ? 'bg-success' : 'bg-danger';
        $leave_duration = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;
        
        echo '<div class="list-group-item">';
        echo '<div class="d-flex justify-content-between align-items-start">';
        echo '<div class="flex-grow-1">';
        echo '<h6 class="mb-1">';
        echo htmlspecialchars($leave['driver_id']) . ' - ';
        echo htmlspecialchars($leave['first_name'] . ' ' . ($leave['middle_name'] ? $leave['middle_name'] . ' ' : '') . $leave['last_name']);
        echo '</h6>';
        echo '<p class="mb-1">';
        echo '<span class="badge ' . $badge_class . ' me-2">' . ucfirst($leave['leave_type']) . ' Leave</span>';
        echo '<small class="text-muted">';
        echo date('M d', strtotime($leave['start_date'])) . ' to ' . date('M d, Y', strtotime($leave['end_date']));
        echo ' (' . $leave_duration . ' day' . ($leave_duration > 1 ? 's' : '') . ')';
        echo '</small>';
        echo '</p>';
        if ($leave['reason']) {
            echo '<p class="mb-1"><small><strong>Reason:</strong> ' . htmlspecialchars($leave['reason']) . '</small></p>';
        }
        echo '<p class="mb-0"><small class="text-muted">Added by: ' . htmlspecialchars($leave['created_by_name']) . ' on ' . date('M d, Y', strtotime($leave['created_at'])) . '</small></p>';
        echo '</div>';
        if ($can_edit) {
            echo '<div>';
            echo '<button class="btn btn-sm btn-outline-danger" onclick="deleteLeave(' . $leave['id'] . ')">';
            echo '<i class="fas fa-trash"></i>';
            echo '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
}
?>