<?php
require_once 'config.php';
requireLogin();

$current_page = 'leaves';
$page_title = 'Manage Leaves - Van Fleet Management';
$user = getCurrentUser();

// Get selected station
$selected_station = isset($_GET['station']) ? intval($_GET['station']) : 
                    ($user['user_type'] === 'dispatcher' ? $user['station_id'] : 0);

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) $current_month = date('n');
if ($current_year < date('Y') || $current_year > date('Y') + 1) $current_year = date('Y');

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

// Get drivers for selected station
$drivers = [];
if ($selected_station) {
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE station_id = ? ORDER BY last_name, first_name");
    $stmt->execute([$selected_station]);
    $drivers = $stmt->fetchAll();
    
    // Get leaves for the selected month
    $start_date = "$current_year-$current_month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $pdo->prepare("
        SELECT l.*, d.first_name, d.last_name, d.driver_id 
        FROM driver_leaves l 
        JOIN drivers d ON l.driver_id = d.id 
        WHERE l.station_id = ? 
        AND ((l.start_date <= ? AND l.end_date >= ?) 
             OR (l.start_date >= ? AND l.start_date <= ?)
             OR (l.end_date >= ? AND l.end_date <= ?))
        ORDER BY l.start_date
    ");
    $stmt->execute([$selected_station, $end_date, $start_date, $start_date, $end_date, $start_date, $end_date]);
    $leaves = $stmt->fetchAll();
    
    // Get yearly statistics
    $year_start = "$current_year-01-01";
    $year_end = "$current_year-12-31";
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN leave_type = 'paid' THEN id END) as paid_count,
            COUNT(DISTINCT CASE WHEN leave_type = 'sick' THEN id END) as sick_count,
            SUM(CASE WHEN leave_type = 'paid' THEN DATEDIFF(LEAST(end_date, ?), GREATEST(start_date, ?)) + 1 ELSE 0 END) as paid_days,
            SUM(CASE WHEN leave_type = 'sick' THEN DATEDIFF(LEAST(end_date, ?), GREATEST(start_date, ?)) + 1 ELSE 0 END) as sick_days
        FROM driver_leaves 
        WHERE station_id = ? 
        AND start_date <= ? AND end_date >= ?
    ");
    $stmt->execute([$year_end, $year_start, $year_end, $year_start, $selected_station, $year_end, $year_start]);
    $yearly_stats = $stmt->fetch();
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

// Process leaves for calendar display
$calendar_leaves = [];
if ($selected_station && !empty($leaves)) {
    foreach ($leaves as $leave) {
        $start = strtotime($leave['start_date']);
        $end = strtotime($leave['end_date']);
        $current = strtotime("$current_year-$current_month-01");
        $month_end = strtotime("$current_year-$current_month-$days_in_month");
        
        for ($date = max($start, $current); $date <= min($end, $month_end); $date += 86400) {
            $day = date('j', $date);
            if (!isset($calendar_leaves[$day])) {
                $calendar_leaves[$day] = ['paid' => [], 'sick' => []];
            }
            $calendar_leaves[$day][$leave['leave_type']][] = $leave;
        }
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
            height: 100px;
            vertical-align: top;
            border: 1px solid #dee2e6;
            position: relative;
            cursor: pointer;
            padding: 5px;
        }
        .calendar-table th {
            background: #f8f9fa;
            height: auto;
            text-align: center;
            padding: 10px;
        }
        .calendar-day {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .today {
            background: #fff3cd;
        }
        .past-date {
            background: #f8f9fa;
            color: #6c757d;
        }
        .leave-indicator {
            position: absolute;
            bottom: 5px;
            left: 5px;
            right: 5px;
            height: 30px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }
        .leave-paid {
            background: #28a745;
        }
        .leave-sick {
            background: #dc3545;
        }
        .leave-mixed {
            background: linear-gradient(90deg, #28a745 50%, #dc3545 50%);
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            border-radius: 4px;
        }
        .driver-list {
            max-height: 600px;
            overflow-y: auto;
        }
        .driver-item {
            padding: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 5px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .driver-item:hover {
            background: #f8f9fa;
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
                    <h2><i class="fas fa-calendar-alt me-2"></i>Manage Leaves</h2>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Home
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
                                    <?php if ($next_year <= date('Y') + 1): ?>
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
                                                $has_leaves = isset($calendar_leaves[$day]);
                                                $paid_count = $has_leaves ? count(array_unique(array_column($calendar_leaves[$day]['paid'], 'id'))) : 0;
                                                $sick_count = $has_leaves ? count(array_unique(array_column($calendar_leaves[$day]['sick'], 'id'))) : 0;
                                                $total_count = $paid_count + $sick_count;
                                                
                                                $cell_class = '';
                                                if ($is_today) $cell_class = 'today';
                                                elseif ($is_past) $cell_class = 'past-date';
                                            ?>
                                            <td class="<?php echo $cell_class; ?>" 
                                                onclick="showDayDetails('<?php echo $date_str; ?>', <?php echo $day; ?>)">
                                                <div class="calendar-day"><?php echo $day; ?></div>
                                                <?php if ($total_count > 0): 
                                                    $leave_class = 'leave-indicator ';
                                                    if ($paid_count > 0 && $sick_count > 0) {
                                                        $leave_class .= 'leave-mixed';
                                                    } elseif ($paid_count > 0) {
                                                        $leave_class .= 'leave-paid';
                                                    } else {
                                                        $leave_class .= 'leave-sick';
                                                    }
                                                ?>
                                                <div class="<?php echo $leave_class; ?>">
                                                    <?php echo $total_count; ?> on leave
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
                                        <span class="legend-color leave-paid"></span>
                                        Paid Leave
                                    </span>
                                    <span class="legend-item">
                                        <span class="legend-color leave-sick"></span>
                                        Sick Leave
                                    </span>
                                    <span class="legend-item">
                                        <span class="legend-color leave-mixed"></span>
                                        Mixed (Both Types)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-md-3">
                        <!-- Year Statistics -->
                        <div class="card stats-card mb-3">
                            <div class="card-body">
                                <h5><i class="fas fa-chart-bar me-2"></i>Year <?php echo $current_year; ?> Statistics</h5>
                                <hr class="bg-white">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <h3><?php echo $yearly_stats['paid_days'] ?? 0; ?></h3>
                                        <small>Paid Leave Days</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h3><?php echo $yearly_stats['sick_days'] ?? 0; ?></h3>
                                        <small>Sick Leave Days</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Drivers List -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-users me-1"></i>
                                    Station Drivers (<?php echo count($drivers); ?>)
                                </h6>
                            </div>
                            <div class="card-body driver-list">
                                <?php foreach ($drivers as $driver): ?>
                                <div class="driver-item" data-driver-id="<?php echo $driver['id']; ?>" 
                                     data-driver-name="<?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>">
                                    <strong><?php echo htmlspecialchars($driver['driver_id']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please select a station to view and manage leaves.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Day Details Modal -->
    <div class="modal fade" id="dayDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Details - <span id="modalDate"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="addLeaveBtn" onclick="showAddLeaveForm()">
                        <i class="fas fa-plus me-1"></i>Add Leave
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Leave Modal -->
    <div class="modal fade" id="addLeaveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addLeaveForm" method="POST" action="process_leave.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="station_id" value="<?php echo $selected_station; ?>">
                        
                        <div class="mb-3">
                            <label for="driver_id" class="form-label">Driver *</label>
                            <select class="form-select" id="driver_id" name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>">
                                    <?php echo htmlspecialchars($driver['driver_id'] . ' - ' . $driver['first_name'] . ' ' . $driver['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="leave_type" class="form-label">Leave Type *</label>
                            <select class="form-select" id="leave_type" name="leave_type" required>
                                <option value="">Select Type</option>
                                <option value="paid">Paid Leave</option>
                                <option value="sick">Sick Leave</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date *</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason/Notes</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Leave
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDate = '';
        const userType = '<?php echo $user['user_type']; ?>';
        const selectedStation = <?php echo $selected_station ?: 0; ?>;
        const currentMonth = <?php echo $current_month; ?>;
        const currentYear = <?php echo $current_year; ?>;

        function showDayDetails(date, day) {
            currentDate = date;
            const modal = new bootstrap.Modal(document.getElementById('dayDetailsModal'));
            document.getElementById('modalDate').textContent = formatDate(date);
            
            // Check if past date and user is dispatcher
            const today = new Date().toISOString().split('T')[0];
            const isPast = date < today;
            const addBtn = document.getElementById('addLeaveBtn');
            
            if (isPast && userType === 'dispatcher') {
                addBtn.style.display = 'none';
            } else {
                addBtn.style.display = 'block';
            }
            
            // Load leave details via AJAX
            fetch(`get_leave_details.php?date=${date}&station=${selectedStation}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalBody').innerHTML = html;
                    modal.show();
                });
        }

        function showAddLeaveForm() {
            const addModal = new bootstrap.Modal(document.getElementById('addLeaveModal'));
            document.getElementById('start_date').value = currentDate;
            document.getElementById('end_date').value = currentDate;
            
            // Set min date based on user type
            const today = new Date().toISOString().split('T')[0];
            if (userType === 'dispatcher') {
                document.getElementById('start_date').setAttribute('min', today);
                document.getElementById('end_date').setAttribute('min', today);
            }
            
            // Hide day details modal
            bootstrap.Modal.getInstance(document.getElementById('dayDetailsModal')).hide();
            addModal.show();
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }

        function deleteLeave(leaveId) {
            if (confirm('Are you sure you want to delete this leave record?')) {
                fetch('process_leave.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&leave_id=${leaveId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        // Driver quick selection
        document.querySelectorAll('.driver-item').forEach(item => {
            item.addEventListener('click', function() {
                const driverId = this.dataset.driverId;
                const driverName = this.dataset.driverName;
                
                // If add leave modal is open, select this driver
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addLeaveModal'));
                if (addModal && addModal._isShown) {
                    document.getElementById('driver_id').value = driverId;
                }
            });
        });

        // Form validation
        document.getElementById('addLeaveForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (endDate < startDate) {
                e.preventDefault();
                alert('End date cannot be before start date');
                return false;
            }
            
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            if (diffDays > 30) {
                e.preventDefault();
                alert('Leave duration cannot exceed 30 days');
                return false;
            }
        });
    </script>
</body>
</html>