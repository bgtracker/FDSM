<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
$user = getCurrentUser();

if (!$image_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid image ID']);
    exit();
}

try {
    // Get image details with permission check
    if ($user['user_type'] === 'dispatcher') {
        $stmt = $pdo->prepare("SELECT vi.*, v.station_id FROM van_images vi 
                              LEFT JOIN vans v ON vi.van_id = v.id 
                              WHERE vi.id = ? AND v.station_id = ?");
        $stmt->execute([$image_id, $user['station_id']]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM van_images WHERE id = ?");
        $stmt->execute([$image_id]);
    }
    
    $image = $stmt->fetch();
    
    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Image not found or access denied']);
        exit();
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM van_images WHERE id = ?");
    $stmt->execute([$image_id]);
    
    // Delete file from filesystem
    if (file_exists($image['image_path'])) {
        unlink($image['image_path']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting image']);
}
?>