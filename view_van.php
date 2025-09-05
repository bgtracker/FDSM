<?php
require_once 'config.php';
requireLogin();

$current_page = 'fleet';
$page_title = 'Van Details - Van Fleet Management';
$user = getCurrentUser();

$van_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$van_id) {
    header('Location: fleet.php');
    exit();
}

// Get van details with permission check
if ($user['user_type'] === 'dispatcher') {
    $stmt = $pdo->prepare("SELECT v.*, s.station_code, s.station_name 
                          FROM vans v 
                          LEFT JOIN stations s ON v.station_id = s.id 
                          WHERE v.id = ? AND v.station_id = ?");
    $stmt->execute([$van_id, $user['station_id']]);
} else {
    $stmt = $pdo->prepare("SELECT v.*, s.station_code, s.station_name 
                          FROM vans v 
                          LEFT JOIN stations s ON v.station_id = s.id 
                          WHERE v.id = ?");
    $stmt->execute([$van_id]);
}

$van = $stmt->fetch();

if (!$van) {
    header('Location: fleet.php');
    exit();
}

// Get assigned driver
$stmt = $pdo->prepare("SELECT * FROM drivers WHERE van_id = ?");
$stmt->execute([$van_id]);
$assigned_driver = $stmt->fetch();

// Get van images
$stmt = $pdo->prepare("SELECT * FROM van_images WHERE van_id = ? ORDER BY created_at");
$stmt->execute([$van_id]);
$van_images = $stmt->fetchAll();

// Get van videos
$stmt = $pdo->prepare("SELECT * FROM van_videos WHERE van_id = ? ORDER BY created_at");
$stmt->execute([$van_id]);
$van_videos = $stmt->fetchAll();

// Get van documents
$stmt = $pdo->prepare("SELECT * FROM van_documents WHERE van_id = ? ORDER BY created_at");
$stmt->execute([$van_id]);
$van_documents = $stmt->fetchAll();

// Get recent maintenance records
$stmt = $pdo->prepare("SELECT vm.*, u.username, u.first_name, u.last_name 
                      FROM van_maintenance vm 
                      LEFT JOIN users u ON vm.user_id = u.id 
                      WHERE vm.van_id = ? 
                      ORDER BY vm.created_at DESC 
                      LIMIT 5");
$stmt->execute([$van_id]);
$recent_maintenance = $stmt->fetchAll();
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
        .van-image {
            max-height: 200px;
            object-fit: cover;
            cursor: pointer;
        }
        .van-video {
            max-height: 300px;
            width: 100%;
        }
        .media-thumbnail {
            height: 120px;
            object-fit: cover;
            cursor: pointer;
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
                        <i class="fas fa-truck me-2"></i>
                        Van Details: <?php echo htmlspecialchars($van['license_plate']); ?>
                    </h2>
                    <div>
                        <a href="edit_van.php?id=<?php echo $van['id']; ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <a href="fleet.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Fleet
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Van Information -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Vehicle Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>License Plate:</strong><br>
                                        <span class="h5 text-primary"><?php echo htmlspecialchars($van['license_plate']); ?></span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Station:</strong><br>
                                        <?php echo htmlspecialchars($van['station_code'] . ' - ' . $van['station_name']); ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Status:</strong><br>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch($van['status']) {
                                            case 'available':
                                                $status_class = 'success';
                                                $status_text = 'Available';
                                                break;
                                            case 'in_use':
                                                $status_class = 'primary';
                                                $status_text = 'In Use';
                                                break;
                                            case 'reserve':
                                                $status_class = 'warning';
                                                $status_text = 'Reserve';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?> fs-6"><?php echo $status_text; ?></span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Added:</strong><br>
                                        <small class="text-muted"><?php echo date('M d, Y \a\t g:i A', strtotime($van['created_at'])); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Last Updated:</strong><br>
                                        <small class="text-muted"><?php echo date('M d, Y \a\t g:i A', strtotime($van['updated_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Van Media Preview -->
                        <?php if (!empty($van_images) || !empty($van_videos)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Van Media Preview</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($van_images)): ?>
                                <h6>Images (<?php echo count($van_images); ?>)</h6>
                                <div class="row mb-3">
                                    <?php foreach (array_slice($van_images, 0, 4) as $image): ?>
                                    <div class="col-md-3 mb-2">
                                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                             class="img-fluid rounded van-image" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#imageModal<?php echo $image['id']; ?>"
                                             alt="Van Image">
                                        
                                        <!-- Image Modal -->
                                        <div class="modal fade" id="imageModal<?php echo $image['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Van Image</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-center">
                                                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                             class="img-fluid" alt="Van Image">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($van_images) > 4): ?>
                                    <p class="text-muted">... and <?php echo count($van_images) - 4; ?> more images</p>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($van_videos)): ?>
                                <h6>Videos (<?php echo count($van_videos); ?>)</h6>
                                <div class="row">
                                    <?php foreach (array_slice($van_videos, 0, 2) as $video): ?>
                                    <div class="col-md-6 mb-2">
                                        <video controls class="van-video rounded">
                                            <source src="<?php echo htmlspecialchars($video['video_path']); ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($van_videos) > 2): ?>
                                    <p class="text-muted">... and <?php echo count($van_videos) - 2; ?> more videos</p>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Maintenance -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Maintenance</h5>
                                <a href="van_maintenance_detail.php?id=<?php echo $van['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-tools me-1"></i>View All
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_maintenance)): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-tools fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No maintenance records yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_maintenance as $record): ?>
                                    <div class="border-start border-3 border-primary ps-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                            </small>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($record['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars(substr($record['maintenance_record'], 0, 150))); ?><?php echo strlen($record['maintenance_record']) > 150 ? '...' : ''; ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Assigned Driver -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Assigned Driver</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($assigned_driver): ?>
                                    <div class="text-center">
                                        <i class="fas fa-user-circle fa-3x text-primary mb-2"></i>
                                        <h6>
                                            <a href="view_driver.php?id=<?php echo $assigned_driver['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($assigned_driver['first_name'] . ' ' . ($assigned_driver['middle_name'] ? $assigned_driver['middle_name'] . ' ' : '') . $assigned_driver['last_name']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">Driver (Click to view profile)</small>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-user-slash fa-3x mb-2"></i>
                                        <p class="mb-0">No driver assigned</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="edit_van.php?id=<?php echo $van['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-1"></i>Edit Van
                                    </a>
                                    <a href="van_maintenance_detail.php?id=<?php echo $van['id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-tools me-1"></i>Maintenance
                                    </a>
                                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#mediaModal">
                                        <i class="fas fa-images me-1"></i>View Pics and Vids
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#documentsModal">
                                        <i class="fas fa-file-alt me-1"></i>View Van Docs
                                    </button>
                                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#qrModal">
                                        <i class="fas fa-qrcode me-1"></i>QR Generator
                                    </button>
                                    <a href="drivers.php" class="btn btn-success">
                                        <i class="fas fa-users me-1"></i>Manage Drivers
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Generator Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">VIN QR Code - <?php echo htmlspecialchars($van['license_plate']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <strong>VIN:</strong> <span class="text-info"><?php echo htmlspecialchars($van['vin_number']); ?></span>
                    </div>
                    <div id="qrcode" class="mb-3"></div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Right-click on the QR code and select "Save image as..." to download.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="downloadQR()">
                        <i class="fas fa-download me-1"></i>Download QR Code
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Media Modal -->
    <div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Van Media - <?php echo htmlspecialchars($van['license_plate']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($van_images) || !empty($van_videos)): ?>
                        <!-- Images Section -->
                        <?php if (!empty($van_images)): ?>
                        <h6><i class="fas fa-images me-1"></i>Images (<?php echo count($van_images); ?>)</h6>
                        <div class="row mb-4">
                            <?php foreach ($van_images as $image): ?>
                            <div class="col-md-3 mb-3">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     class="img-fluid rounded media-thumbnail" 
                                     data-bs-toggle="modal" 
                                     data-bs-target="#imageViewModal<?php echo $image['id']; ?>"
                                     alt="Van Image">
                                
                                <!-- Image View Modal -->
                                <div class="modal fade" id="imageViewModal<?php echo $image['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Van Image</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                     class="img-fluid" alt="Van Image">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Videos Section -->
                        <?php if (!empty($van_videos)): ?>
                        <h6><i class="fas fa-video me-1"></i>Videos (<?php echo count($van_videos); ?>)</h6>
                        <div class="row">
                            <?php foreach ($van_videos as $video): ?>
                            <div class="col-md-6 mb-3">
                                <video controls class="w-100 rounded" style="max-height: 250px;">
                                    <source src="<?php echo htmlspecialchars($video['video_path']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-images fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Media Files</h5>
                            <p class="text-muted">No images or videos have been uploaded for this van yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents Modal -->
    <div class="modal fade" id="documentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Van Registration Documents - <?php echo htmlspecialchars($van['license_plate']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($van_documents)): ?>
                        <div class="list-group">
                            <?php foreach ($van_documents as $document): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file-alt me-2"></i>
                                    <strong><?php echo htmlspecialchars($document['document_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        Uploaded: <?php echo date('M d, Y \a\t g:i A', strtotime($document['created_at'])); ?>
                                    </small>
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
                            <p class="text-muted">No registration documents have been uploaded for this van.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, setting up QR functionality');
            
            // Test if modal exists
            const qrModal = document.getElementById('qrModal');
            const qrButton = document.querySelector('[data-bs-target="#qrModal"]');
            
            if (!qrModal) {
                console.error('QR Modal not found!');
                return;
            }
            
            if (!qrButton) {
                console.error('QR Button not found!');
                return;
            }
            
            console.log('QR Modal and Button found');
            
            let qrCodeGenerated = false;
            
            // Generate QR code when modal is shown
            qrModal.addEventListener('shown.bs.modal', function() {
                console.log('QR Modal opened');
                
                if (!qrCodeGenerated) {
                    const qrContainer = document.getElementById('qrcode');
                    const vinNumber = '<?php echo htmlspecialchars($van['vin_number']); ?>';
                    
                    console.log('Generating QR for VIN:', vinNumber);
                    
                    // Show loading
                    qrContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Generating QR Code...</div>';
                    
                    // Use Google QR API (more reliable)
                    setTimeout(function() {
                        const qrSize = 300;
                        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(vinNumber)}&margin=10`;
                        
                        qrContainer.innerHTML = `
                            <div class="text-center">
                                <img src="${qrUrl}" alt="QR Code for VIN: ${vinNumber}" class="img-fluid border rounded" style="max-width: ${qrSize}px; background: white; padding: 10px;">
                            </div>
                        `;
                        
                        qrCodeGenerated = true;
                        console.log('QR Code generated successfully');
                    }, 500);
                }
            });
            
            // Test button click
            qrButton.addEventListener('click', function(e) {
                console.log('QR Button clicked!');
            });
        });
        
        // Download QR code function
        function downloadQR() {
            console.log('Download QR called');
            
            const img = document.querySelector('#qrcode img');
            const vinNumber = '<?php echo htmlspecialchars($van['vin_number']); ?>';
            const licensePlate = '<?php echo htmlspecialchars($van['license_plate']); ?>';
            
            if (img && img.src) {
                // Create a temporary canvas to convert the image
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                const tempImg = new Image();
                tempImg.crossOrigin = 'anonymous';
                tempImg.onload = function() {
                    canvas.width = tempImg.width;
                    canvas.height = tempImg.height;
                    ctx.drawImage(tempImg, 0, 0);
                    
                    // Download the canvas as PNG
                    const link = document.createElement('a');
                    link.download = `VIN_QR_${licensePlate}_${vinNumber}.png`;
                    link.href = canvas.toDataURL('image/png');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    console.log('QR Code downloaded');
                };
                tempImg.src = img.src;
            } else {
                // Direct link download as fallback
                const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(vinNumber)}&margin=10`;
                const link = document.createElement('a');
                link.download = `VIN_QR_${licensePlate}_${vinNumber}.png`;
                link.href = qrUrl;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                console.log('QR Code downloaded via direct link');
            }
        }
    </script>
</body>
</html>