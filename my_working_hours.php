<?php
require_once 'config.php';
requireDriverLogin();

$current_page = 'my_hours';
$page_title = 'My Working Hours - Van Fleet Management';
$driver = getCurrentDriver();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query conditions
$where_conditions = ["wh.driver_id = ?"];
$params = [$driver['id']];

if ($month_filter) {
    $where_conditions[] = "DATE_FORMAT(wh.work_date, '%Y-%m') = ?";
    $params[] = $month_filter;
}

if ($status_filter) {
    $where_conditions[] = "wh.status = ?";
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM working_hours wh $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get working hours data
$query = "SELECT wh.*, s.station_code, s.station_name, v.license_plate,
                 u.first_name as approved_by_first, u.last_name as approved_by_last
          FROM working_hours wh 
          LEFT JOIN stations s ON wh.station_id = s.id 
          LEFT JOIN vans v ON wh.van_id = v.id
          LEFT JOIN users u ON wh.approved_by = u.id
          $where_clause
          ORDER BY wh.work_date DESC 
          LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$working_hours = $stmt->fetchAll();

// Get monthly statistics
$stats_query = "SELECT 
                    COUNT(*) as total_submissions,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
                    COALESCE(SUM(CASE WHEN status = 'approved' THEN km_total ELSE 0 END), 0) as total_km,
                    COALESCE(SUM(CASE WHEN status = 'approved' THEN total_minutes ELSE 0 END), 0) as total_minutes
                FROM working_hours 
                WHERE driver_id = ? AND DATE_FORMAT(work_date, '%Y-%m') = ?";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([$driver['id'], $month_filter]);
$monthly_stats = $stmt->fetch();

// Helper functions
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

function formatMinutesToHours($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%dh %02dm', $hours, $mins);
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
        }
        .detail-card {
            border-radius: 10px;
            transition: transform 0.2s ease;
        }
        .detail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-history me-2"></i>My Working Hours</h2>
                    <div>
                        <a href="submit_working_hours.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i>Submit Hours
                        </a>
                        <a href="driver_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Monthly Statistics -->
                <div class="card stats-card mb-4">
                    <div class="card-body">
                        <h5 class="text-white mb-4">
                            <i class="fas fa-chart-bar me-2"></i>
                            Statistics for <?php echo date('F Y', strtotime($month_filter . '-01')); ?>
                        </h5>
                        <div class="row text-center">
                            <div class="col-6 col-md-3 mb-3">
                                <h3 class="mb-1"><?php echo $monthly_stats['total_submissions']; ?></h3>
                                <small>Total Submissions</small>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <h3 class="mb-1"><?php echo $monthly_stats['approved_count']; ?></h3>
                                <small>Approved</small>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <h3 class="mb-1"><?php echo number_format($monthly_stats['total_km']); ?></h3>
                                <small>Kilometers (Approved)</small>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <h3 class="mb-1"><?php echo formatMinutesToHours($monthly_stats['total_minutes']); ?></h3>
                                <small>Hours (Approved)</small>
                            </div>
                        </div>
                        
                        <?php if ($monthly_stats['pending_count'] > 0): ?>
                        <div class="alert alert-warning bg-warning bg-opacity-25 border-warning text-white mt-3">
                            <i class="fas fa-clock me-2"></i>
                            You have <?php echo $monthly_stats['pending_count']; ?> submission(s) pending approval.
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($monthly_stats['rejected_count'] > 0): ?>
                        <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You have <?php echo $monthly_stats['rejected_count']; ?> rejected submission(s). Please review and resubmit.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="month" class="form-label">Month</label>
                                <input type="month" class="form-control" id="month" name="month" 
                                       value="<?php echo htmlspecialchars($month_filter); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="my_working_hours.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Working Hours List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Submissions (<?php echo $total_records; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($working_hours)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clock fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Working Hours Found</h5>
                                <p class="text-muted">No working hours match your current filters.</p>
                                <a href="submit_working_hours.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Submit Your First Hours
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($working_hours as $wh): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card detail-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <strong><?php echo date('M d, Y', strtotime($wh['work_date'])); ?></strong>
                                            <?php echo getStatusBadge($wh['status']); ?>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <small class="text-muted">Tour:</small><br>
                                                <strong><?php echo htmlspecialchars($wh['tour_number']); ?></strong>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">Station:</small><br>
                                                <?php echo htmlspecialchars($wh['station_code']); ?>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">Vehicle:</small><br>
                                                <?php echo htmlspecialchars($wh['license_plate']); ?>
                                            </div>
                                            
                                            <div class="row text-center mt-3">
                                                <div class="col-6">
                                                    <div class="border-end">
                                                        <strong class="text-primary"><?php echo $wh['km_total']; ?></strong><br>
                                                        <small class="text-muted">KM</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <strong class="text-success"><?php echo formatMinutesToHours($wh['total_minutes']); ?></strong><br>
                                                    <small class="text-muted">Hours</small>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <button type="button" class="btn btn-sm btn-outline-info w-100" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#detailModal<?php echo $wh['id']; ?>">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <?php if ($wh['status'] === 'approved' && $wh['approved_by']): ?>
                                        <div class="card-footer">
                                            <small class="text-muted">
                                                <i class="fas fa-check me-1"></i>
                                                Approved by <?php echo htmlspecialchars($wh['approved_by_first'] . ' ' . $wh['approved_by_last']); ?>
                                                on <?php echo date('M d, Y', strtotime($wh['approved_at'])); ?>
                                            </small>
                                        </div>
                                        <?php elseif ($wh['status'] === 'rejected'): ?>
                                        <div class="card-footer">
                                            <small class="text-danger">
                                                <i class="fas fa-times me-1"></i>
                                                Rejected - <?php echo htmlspecialchars($wh['rejection_reason'] ?: 'No reason provided'); ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Detail Modal -->
                                <div class="modal fade" id="detailModal<?php echo $wh['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    Working Hours - <?php echo date('M d, Y', strtotime($wh['work_date'])); ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <strong>Status:</strong><br>
                                                        <?php echo getStatusBadge($wh['status']); ?>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <strong>Tour Number:</strong><br>
                                                        <?php echo htmlspecialchars($wh['tour_number']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <strong>Station:</strong><br>
                                                        <?php echo htmlspecialchars($wh['station_code'] . ' - ' . $wh['station_name']); ?>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <strong>Vehicle:</strong><br>
                                                        <?php echo htmlspecialchars($wh['license_plate']); ?>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                <h6>Kilometers</h6>
                                                <div class="row">
                                                    <div class="col-4">
                                                        <strong>Start:</strong> <?php echo number_format($wh['km_start']); ?> km
                                                    </div>
                                                    <div class="col-4">
                                                        <strong>End:</strong> <?php echo number_format($wh['km_end']); ?> km
                                                    </div>
                                                    <div class="col-4">
                                                        <strong>Total:</strong> <?php echo number_format($wh['km_total']); ?> km
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                <h6>Working Times</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-2">
                                                        <strong>Scanner Login:</strong> <?php echo date('H:i', strtotime($wh['scanner_login'])); ?>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <strong>Depot Departure:</strong> <?php echo date('H:i', strtotime($wh['depo_departure'])); ?>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <strong>First Delivery:</strong> <?php echo date('H:i', strtotime($wh['first_delivery'])); ?>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <strong>Last Delivery:</strong> <?php echo date('H:i', strtotime($wh['last_delivery'])); ?>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <strong>Depot Return:</strong> <?php echo date('H:i', strtotime($wh['depo_return'])); ?>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <strong>Break:</strong> <?php echo $wh['break_minutes']; ?> minutes
                                                    </div>
                                                </div>
                                                
                                                <div class="alert alert-info mt-3">
                                                    <strong>Total Working Hours:</strong> <?php echo formatMinutesToHours($wh['total_minutes']); ?>
                                                </div>
                                                
                                                <?php if ($wh['status'] === 'approved'): ?>
                                                <div class="alert alert-success">
                                                    <strong>Approved by:</strong> <?php echo htmlspecialchars($wh['approved_by_first'] . ' ' . $wh['approved_by_last']); ?><br>
                                                    <strong>Approved on:</strong> <?php echo date('M d, Y \a\t g:i A', strtotime($wh['approved_at'])); ?>
                                                </div>
                                                <?php elseif ($wh['status'] === 'rejected'): ?>
                                                <div class="alert alert-danger">
                                                    <strong>Rejection Reason:</strong><br>
                                                    <?php echo htmlspecialchars($wh['rejection_reason'] ?: 'No reason provided'); ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <small class="text-muted">
                                                    Submitted on: <?php echo date('M d, Y \a\t g:i A', strtotime($wh['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php 
                                            $prev_params = array_filter(['month' => $month_filter, 'status' => $status_filter]);
                                            echo $prev_params ? '&' . http_build_query($prev_params) : '';
                                        ?>">Previous</a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                            $page_params = array_filter(['month' => $month_filter, 'status' => $status_filter]);
                                            echo $page_params ? '&' . http_build_query($page_params) : '';
                                        ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php 
                                            $next_params = array_filter(['month' => $month_filter, 'status' => $status_filter]);
                                            echo '&' . http_build_query($next_params);
                                        ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>