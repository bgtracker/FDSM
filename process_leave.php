<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'add') {
    $station_id = intval($_POST['station_id']);
    $driver_id = intval($_POST['driver_id']);
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    // Verify user has access to this station
    if ($user['user_type'] === 'dispatcher' && $station_id !== $user['station_id']) {
        header('Location: manage_leaves.php?error=access_denied');
        exit;
    }
    
    // Check if dispatcher is trying to add past dates
    $today = date('Y-m-d');
    if ($user['user_type'] === 'dispatcher' && $start_date < $today) {
        header('Location: manage_leaves.php?error=past_date');
        exit;
    }
    
    // Validate dates
    if ($end_date < $start_date) {
        header('Location: manage_leaves.php?error=invalid_dates');
        exit;
    }
    
    // Check duration (max 30 days)
    $diff = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
    if ($diff > 30) {
        header('Location: manage_leaves.php?error=duration_exceeded');
        exit;
    }
    
    // Check for overlapping leaves
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM driver_leaves 
        WHERE driver_id = ? 
        AND ((start_date <= ? AND end_date >= ?) 
             OR (start_date <= ? AND end_date >= ?)
             OR (start_date >= ? AND end_date <= ?))
    ");
    $stmt->execute([$driver_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date]);
    $overlap_count = $stmt->fetchColumn();
    
    if ($overlap_count > 0) {
        header('Location: manage_leaves.php?station=' . $station_id . '&error=overlap');
        exit;
    }
    
    // Insert leave
    try {
        $stmt = $pdo->prepare("
            INSERT INTO driver_leaves (driver_id, station_id, leave_type, start_date, end_date, reason, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$driver_id, $station_id, $leave_type, $start_date, $end_date, $reason, $user['id']]);
        
        // Get month and year for redirect
        $month = date('n', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        
        header('Location: manage_leaves.php?station=' . $station_id . '&month=' . $month . '&year=' . $year . '&success=added');
        exit;
        
    } catch (Exception $e) {
        header('Location: manage_leaves.php?station=' . $station_id . '&error=database');
        exit;
    }
    
} elseif ($action === 'delete') {
    header('Content-Type: application/json');
    
    $leave_id = intval($_POST['leave_id']);
    
    // Get leave details
    $stmt = $pdo->prepare("SELECT l.*, d.station_id FROM driver_leaves l JOIN drivers d ON l.driver_id = d.id WHERE l.id = ?");
    $stmt->execute([$leave_id]);
    $leave = $stmt->fetch();
    
    if (!$leave) {
        echo json_encode(['success' => false, 'message' => 'Leave not found']);
        exit;
    }
    
    // Verify permissions
    if ($user['user_type'] === 'dispatcher') {
        if ($leave['station_id'] !== $user['station_id']) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Check if trying to delete past leave
        $today = date('Y-m-d');
        if ($leave['start_date'] < $today) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete past leaves']);
            exit;
        }
    }
    
    // Delete leave
    try {
        $stmt = $pdo->prepare("DELETE FROM driver_leaves WHERE id = ?");
        $stmt->execute([$leave_id]);
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
    
} else {
    header('Location: manage_leaves.php');
    exit;
}
?>