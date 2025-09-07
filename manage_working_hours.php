<?php
require_once 'config.php';
requireLogin();

$current_page = 'working_hours';
$page_title = 'Manage Working Hours - Van Fleet Management';
$user = getCurrentUser();

// Get selected station
$selected_station = isset($_GET['station']) ? intval($_GET['station']) : 
                    ($user['user_type'] === 'dispatcher' ? $user['station_id'] : 0);

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) $current_month = date('n');
if ($current_year < date('Y') - 1 || $current_year > date('Y')) $current_year = date('Y');

// Get stations based on user type
$stations = [];
if ($user['user_type'] === 'station_manager') {
    $stmt = $pdo->query("SELECT * FROM stations ORDER BY station_code");
    $stations = $stmt->fetchAll();
} else {
    // Dispatcher can only see their own station
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
    $stmt->execute([$user['station_id']]);
    $stations = $stmt->fetchAll();
    $selected_station = $user['station_id'];
}

// Get working hours submissions for the selected month and station
$submissions_by_date = [];
$drivers_by_date = [];
$missing_drivers_by_date = [];

if ($selected_station) {
    $start_date = "$current_year-$current_month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Get all submissions for the month
    $stmt = $pdo->prepare("
        SELECT wh.*, d.driver_id, d.first_name, d.last_name
        FROM working_hours wh 
        JOIN drivers d ON wh.driver_id = d.id 
        WHERE wh.station_id = ? 
        AND wh.work_date BETWEEN ? AND ?
        ORDER BY wh.work_date, d.last_name, d.first_name
    ");
    $stmt->execute([$selected_station, $start_date, $end_date]);
    $submissions = $stmt->fetchAll();
    
    // Group submissions by date
    foreach ($submissions as $submission) {
        $date = $submission['work_date'];
        if (!isset($submissions_by_date[$date])) {
            $submissions_by_date[$date] = [];
        }
        $submissions_by_date[$date][] = $submission;
    }
    
    // Get all drivers for this station
    $stmt = $pdo->prepare("SELECT id, driver_id, first_name, last_name FROM drivers WHERE station_id = ? ORDER BY last_name, first_name");
    $stmt->execute([$selected_station]);
    $all_drivers = $stmt->fetchAll();
    
    // Check for missing submissions (only for past dates)
    $today = date('Y-m-d');
    for ($day = 1; $day <= date('t', strtotime($start_date)); $day++) {
        $check_date = sprintf("%04d-%02d-%02d", $current_year, $current_month, $day);
        
        // Only check past dates and today
        if ($check_date <= $today) {
            // Get drivers who submitted for this date
            $submitted_driver_ids = [];
            if (isset($submissions_by_date[$check_date])) {
                foreach ($submissions_by_date[$check_date] as $sub) {
                    $submitted_driver_ids[] = $sub['driver_id'];
                }
            }
            
            // Check for leaves on this date
            $stmt = $pdo->prepare("
                SELECT DISTINCT dl.driver_id
                FROM driver_leaves dl
                WHERE dl.station_id = ?
                AND ? BETWEEN dl.start_date AND dl.end_date
            ");
            $stmt->execute([$selected_station, $check_date]);
            $drivers_on_leave = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Find missing drivers (not submitted and not on leave)
            $missing_drivers = [];
            foreach ($all_drivers as $driver) {
                if (!in_array($driver['id'], $submitted_driver_ids) && !in_array($driver['id'], $drivers_on_leave)) {
                    $missing_drivers[] = $driver;
                }
            }
            
            if (!empty($missing_drivers)) {
                $missing_drivers_by_date[$check_date] = $missing_drivers;
            }
        }
    }
}

// Calculate calendar data
$first_day = date('w', strtotime("$current_year-$current_month-01"));
$days_in_month = date('t', strtotime("$current_year-$current_month-01"));
$prev_month = $current_month - 1;
$prev_year = $current_year;
$next_month = $current_month + 1;
$next_year = $current_year;

if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Helper function to get day status
function getDayStatus($date, $submissions_by_date, $missing_drivers_by_date) {
    $has_pending = false;
    $has_submissions = false;
    $has_missing = isset($missing_drivers_by_date[$date]);
    
    if (isset($submissions_by_date[$date])) {
        $has_submissions = true;
        foreach ($submissions_by_date[$date] as $sub) {
            if ($sub['status'] === 'pending') {
                $has_pending = true;
                break;
            }
        }
    }
    
    if ($has_missing) {
        return 'missing'; // Orange - has missing submissions
    } elseif ($has_pending) {
        return 'pending'; // Red - has pending submissions
    } elseif ($has_submissions) {
        return 'approved'; // Green - all approved
    } else {
        return 'empty'; // Default - no submissions
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
        .calendar-table {
            width: 100%;
            table-layout: fixed;
        }
        .calendar-table th, .calendar-table td {
            height: 120px;
            vertical-align: top;
            border: 1px solid #dee2e6;
            position: relative;
            cursor: pointer;
            padding: 8px;
        }
        .calendar-table th {
            background: #f8f9fa;
            height: auto;
            text-align: center;
            padding: 15px;
            cursor: default;
        }
        .calendar-day {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        .today {
            background: #fff3cd;
            border: 2px solid #ffc107;
        }
        .past-date {
            background: #f8f9fa;
        }
        .future-date {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        .day-pending {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .day-approved {
            background: #d1e7dd;
            border-color: #198754;
        }
        .day-missing {
            background: #fff3cd;
            border-color: #fd7e14;
        }
        .day-status {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-pending { background: #dc3545; }
        .status-approved { background: #198754; }
        .status-missing { background: #fd7e14; }
        .submission-count {
            font-size: 0.75rem;
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 2px;
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            margin-bottom: 10px;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid my-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clock me-2"></i>Manage Working Hours</h2>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Station Selection -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row align-items-end">
                            <div class="col-md-4">
                                <label for="station" class="form-label">Select Station</label>
                                <select class="form-select" id="station" name="station" onchange="this.form.submit()" 
                                        <?php echo $user['user_type'] === 'dispatcher' ? 'disabled' : ''; ?>>
                                    <?php if ($user['user_type'] === 'station_manager'): ?>
                                        <option value="">-- Select a Station --</option>
                                    <?php endif; ?>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo $station['id']; ?>" 
                                                <?php echo $selected_station == $station['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['station_code'] . ' - ' . $station['station_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                            <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                        </form>
                    </div>
                </div>

                <?php if ($selected_station): ?>
                <div class="row">
                    <!-- Calendar Section -->
                    <div class="col-md-9">
                        <!-- Calendar Navigation -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="?station=<?php echo $selected_station; ?>&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                    <h4 class="mb-0">
                                        <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>
                                    </h4>
                                    <?php if ($next_year <= date('Y') || ($next_year == date('Y') && $next_month <= date('n'))): ?>
                                    <a href="?station=<?php echo $selected_station; ?>&month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" 
                                       class="btn btn-outline-primary">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-outline-secondary" disabled>
                                        Next <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Calendar -->
                        <div class="card">
                            <div class="card-body p-0">
                                <table class="calendar-table">
                                    <thead>
                                        <tr>
                                            <th>Sunday</th>
                                            <th>Monday</th>
                                            <th>Tuesday</th>
                                            <th>Wednesday</th>
                                            <th>Thursday</th>
                                            <th>Friday</th>
                                            <th>Saturday</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $day = 1;
                                        $today = date('Y-m-d');
                                        for ($week = 0; $week < 6; $week++):
                                            if ($day > $days_in_month) break;
                                        ?>
                                        <tr>
                                            <?php for ($dow = 0; $dow < 7; $dow++): ?>
                                            <?php if (($week == 0 && $dow < $first_day) || $day > $days_in_month): ?>
                                            <td class="text-muted"></td>
                                            <?php else: 
                                                $date_str = sprintf("%04d-%02d-%02d", $current_year, $current_month, $day);
                                                $is_today = ($date_str == $today);
                                                $is_past = ($date_str < $today);
                                                $is_future = ($date_str > $today);
                                                $day_status = getDayStatus($date_str, $submissions_by_date, $missing_drivers_by_date);
                                                
                                                $cell_class = '';
                                                if ($is_today) $cell_class .= 'today ';
                                                elseif ($is_past) $cell_class .= 'past-date ';
                                                elseif ($is_future) $cell_class .= 'future-date ';
                                                
                                                if ($day_status === 'pending') $cell_class .= 'day-pending ';
                                                elseif ($day_status === 'approved') $cell_class .= 'day-approved ';
                                                elseif ($day_status === 'missing') $cell_class .= 'day-missing ';
                                                
                                                $submission_count = isset($submissions_by_date[$date_str]) ? count($submissions_by_date[$date_str]) : 0;
                                                $missing_count = isset($missing_drivers_by_date[$date_str]) ? count($missing_drivers_by_date[$date_str]) : 0;
                                            ?>
                                            <td class="<?php echo trim($cell_class); ?>" 
                                                <?php if (!$is_future): ?>
                                                onclick="viewDayDetails('<?php echo $date_str; ?>', <?php echo $day; ?>)"
                                                <?php endif; ?>>
                                                <div class="calendar-day"><?php echo $day; ?></div>
                                                
                                                <?php if ($day_status !== 'empty'): ?>
                                                <div class="day-status status-<?php echo $day_status; ?>"></div>
                                                <?php endif; ?>
                                                
                                                <?php if ($submission_count > 0): ?>
                                                <div class="submission-count">
                                                    <?php echo $submission_count; ?> submission<?php echo $submission_count > 1 ? 's' : ''; ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($missing_count > 0): ?>
                                                <div class="submission-count" style="background: rgba(253,126,20,0.2);">
                                                    <?php echo $missing_count; ?> missing
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <?php $day++; endif; ?>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h6>Legend</h6>
                                <div>
                                    <span class="legend-item">
                                        <span class="legend-color" style="background: #f8d7da; border-color: #dc3545;"></span>
                                        Pending Submissions
                                    </span>
                                    <span class="legend-item">
                                        <span class="legend-color" style="background: #d1e7dd; border-color: #198754;"></span>
                                        All Approved
                                    </span>
                                    <span class="legend-item">
                                        <span class="legend-color" style="background: #fff3cd; border-color: #fd7e14;"></span>
                                        Missing Submissions
                                    </span>
                                    <span class="legend-item">
                                        <span class="legend-color" style="background: #fff3cd; border-color: #ffc107;"></span>
                                        Today
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-md-3">
                        <!-- Monthly Statistics -->
                        <?php 
                        $total_submissions = count($submissions);
                        $pending_submissions = array_filter($submissions, function($s) { return $s['status'] === 'pending'; });
                        $approved_submissions = array_filter($submissions, function($s) { return $s['status'] === 'approved'; });
                        $total_missing = array_sum(array_map('count', $missing_drivers_by_date));
                        ?>
                        <div class="card stats-card mb-3">
                            <div class="card-body">
                                <h5><i class="fas fa-chart-bar me-2"></i><?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?> Statistics</h5>
                                <hr class="bg-white">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <h3><?php echo $total_submissions; ?></h3>
                                        <small>Total Submissions</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h3><?php echo count($pending_submissions); ?></h3>
                                        <small>Pending Review</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h3><?php echo count($approved_submissions); ?></h3>
                                        <small>Approved</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h3><?php echo $total_missing; ?></h3>
                                        <small>Missing</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-bolt me-1"></i>
                                    Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="showPendingOnly()">
                                        <i class="fas fa-clock me-1"></i>Show Pending Only
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showMissingOnly()">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Show Missing Only
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="showAllDays()">
                                        <i class="fas fa-eye me-1"></i>Show All Days
                                    </button>
                                </div>
                                
                                <?php if (count($pending_submissions) > 0): ?>
                                <div class="alert alert-warning mt-3 p-2">
                                    <small>
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo count($pending_submissions); ?> submissions need your review
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($total_missing > 0): ?>
                                <div class="alert alert-danger mt-3 p-2">
                                    <small>
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php echo $total_missing; ?> drivers haven't submitted hours
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please select a station to view and manage working hours.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const selectedStation = <?php echo $selected_station ?: 0; ?>;
        const currentMonth = <?php echo $current_month; ?>;
        const currentYear = <?php echo $current_year; ?>;

        function viewDayDetails(date, day) {
            if (selectedStation === 0) {
                alert('Please select a station first.');
                return;
            }
            
            const url = `review_daily_hours.php?date=${date}&station=${selectedStation}`;
            window.location.href = url;
        }

        function showPendingOnly() {
            const pendingCells = document.querySelectorAll('.day-pending');
            const allCells = document.querySelectorAll('.calendar-table td');
            
            // Hide all cells first
            allCells.forEach(cell => {
                if (!cell.classList.contains('day-pending') && cell.querySelector('.calendar-day')) {
                    cell.style.opacity = '0.3';
                }
            });
            
            // Highlight pending cells
            pendingCells.forEach(cell => {
                cell.style.opacity = '1';
                cell.style.transform = 'scale(1.05)';
                cell.style.zIndex = '10';
            });
        }

        function showMissingOnly() {
            const missingCells = document.querySelectorAll('.day-missing');
            const allCells = document.querySelectorAll('.calendar-table td');
            
            // Hide all cells first
            allCells.forEach(cell => {
                if (!cell.classList.contains('day-missing') && cell.querySelector('.calendar-day')) {
                    cell.style.opacity = '0.3';
                }
            });
            
            // Highlight missing cells
            missingCells.forEach(cell => {
                cell.style.opacity = '1';
                cell.style.transform = 'scale(1.05)';
                cell.style.zIndex = '10';
            });
        }

        function showAllDays() {
            const allCells = document.querySelectorAll('.calendar-table td');
            
            // Reset all cells
            allCells.forEach(cell => {
                cell.style.opacity = '1';
                cell.style.transform = 'scale(1)';
                cell.style.zIndex = 'auto';
            });
        }
    </script>
</body>
</html>