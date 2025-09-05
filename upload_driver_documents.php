<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: drivers.php');
    exit();
}

$driver_id = intval($_POST['driver_id']);
$document_type = trim($_POST['document_type']);
$error_message = '';
$success_message = '';

// Verify user has permission to upload documents for this driver
if ($user['user_type'] === 'dispatcher') {
    $stmt = $pdo->prepare("SELECT id FROM drivers WHERE id = ? AND station_id = ?");
    $stmt->execute([$driver_id, $user['station_id']]);
} else {
    $stmt = $pdo->prepare("SELECT id FROM drivers WHERE id = ?");
    $stmt->execute([$driver_id]);
}

if (!$stmt->fetch()) {
    header('Location: drivers.php');
    exit();
}

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
                $new_filename = $driver_id . '_' . strtolower(str_replace(' ', '_', $document_type)) . '_' . time() . '.' . $file_ext;
                $upload_path = UPLOAD_DIR . 'drivers/documents/' . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $stmt = $pdo->prepare("INSERT INTO driver_documents (driver_id, document_type, document_path, document_name) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$driver_id, $document_type, $upload_path, $original_name]);
                    
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

// Redirect back to driver view with message
$redirect_url = 'view_driver.php?id=' . $driver_id;
if ($success_message) {
    $redirect_url .= '&success=' . urlencode($success_message);
} elseif ($error_message) {
    $redirect_url .= '&error=' . urlencode($error_message);
}

header('Location: ' . $redirect_url);
exit();
?>