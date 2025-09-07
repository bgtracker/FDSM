<?php
require_once 'config.php';
requireDriverLogin();

$current_page = 'submit_hours';
$page_title = 'Submit Working Hours - Van Fleet Management';
$driver = getCurrentDriver();
$error_message = '';
$success_message = '';

// Get available stations
$stmt = $pdo->query("SELECT * FROM stations ORDER BY station_code");
$stations = $stmt->fetchAll();

// Get assigned van
$assigned_van = null;
if ($driver['van_id']) {
    $stmt = $pdo->prepare("SELECT * FROM vans WHERE id = ?");
    $stmt->execute([$driver['van_id']]);
    $assigned_van = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $work_date = $_POST['work_date'];
    $station_id = intval($_POST['station_id']);
    $tour_number = strtoupper(trim($_POST['tour_number']));
    $van_id = intval($_POST['van_id']);
    
    $km_start = intval($_POST['km_start']);
    $km_end = intval($_POST['km_end']);
    
    $scanner_login = $_POST['scanner_login'];
    $depo_departure = $_POST['depo_departure'];
    $first_delivery = $_POST['first_delivery'];
    $last_delivery = $_POST['last_delivery'];
    $depo_return = $_POST['depo_return'];
    
    // Validate inputs
    if (empty($work_date) || empty($station_id) || empty($tour_number) || empty($van_id) ||
        empty($scanner_login) || empty($depo_departure) || empty($first_delivery) || 
        empty($last_delivery) || empty($depo_return) || $km_start <= 0 || $km_end <= 0) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($km_end <= $km_start) {
        $error_message = 'End kilometers must be greater than start kilometers.';
    } elseif (strlen($tour_number) > 7) {
        $error_message = 'Tour number cannot exceed 7 characters.';
    } elseif ($work_date > date('Y-m-d')) {
        $error_message = 'Cannot submit hours for future dates.';
    } else {
        // Calculate working hours
        try {
            $login_time = new DateTime($work_date . ' ' . $scanner_login);
            $return_time = new DateTime($work_date . ' ' . $depo_return);
            
            // Handle overnight shifts (if return time is before login time, assume next day)
            if ($return_time < $login_time) {
                $return_time->add(new DateInterval('P1D'));
            }
            
            $total_minutes = ($return_time->getTimestamp() - $login_time->getTimestamp()) / 60;
            
            // Calculate break time based on working hours
            $break_minutes = 30; // Default for up to 9 hours
            if ($total_minutes > (9 * 60)) {
                $break_minutes = 45; // 45 minutes for over 9 hours
            }
            
            // Subtract break from total
            $total_minutes -= $break_minutes;
            
            if ($total_minutes <= 0) {
                $error_message = 'Invalid time entries. Please check your times.';
            } else {
                // Check if submission already exists for this date
                $stmt = $pdo->prepare("SELECT id FROM working_hours WHERE driver_id = ? AND work_date = ?");
                $stmt->execute([$driver['id'], $work_date]);
                
                if ($stmt->fetch()) {
                    $error_message = 'You have already submitted working hours for this date.';
                } else {
                    // Insert working hours record
                    $stmt = $pdo->prepare("
                        INSERT INTO working_hours 
                        (driver_id, station_id, work_date, tour_number, van_id, 
                         km_start, km_end, scanner_login, depo_departure, first_delivery, 
                         last_delivery, depo_return, break_minutes, total_minutes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $driver['id'], $station_id, $work_date, $tour_number, $van_id,
                        $km_start, $km_end, $scanner_login, $depo_departure, $first_delivery,
                        $last_delivery, $depo_return, $break_minutes, $total_minutes
                    ]);
                    
                    $success_message = 'Working hours submitted successfully! Your submission is now pending approval.';
                    
                    // Clear form data
                    $_POST = [];
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error processing time calculations. Please check your time entries.';
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
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            margin-bottom: 2rem;
        }
        .form-section-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0;
            margin: -1px -1px 0 -1px;
        }
        .calculated-field {
            background-color: #e9ecef;
            color: #495057;
        }
        .help-icon {
            cursor: help;
            color: #6c757d;
        }
        .time-input {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clock me-2"></i>Submit Working Hours</h2>
                    <div>
                        <a href="my_working_hours.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-history me-1"></i>My Submissions
                        </a>
                        <a href="driver_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <div class="mt-2">
                            <a href="my_working_hours.php" class="btn btn-sm btn-outline-success">View My Submissions</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$assigned_van): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No Vehicle Assigned:</strong> You don't have a vehicle assigned. Please contact your dispatcher before submitting working hours.
                    </div>
                <?php endif; ?>

                <form method="POST" id="workingHoursForm">
                    <!-- Tour Details Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-route me-2"></i>
                                Tour Details
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="work_date" class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="work_date" name="work_date" 
                                           value="<?php echo isset($_POST['work_date']) ? htmlspecialchars($_POST['work_date']) : date('Y-m-d'); ?>"
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="form-text">Select the date you worked</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="station_id" class="form-label">Station *</label>
                                    <select class="form-select" id="station_id" name="station_id" required>
                                        <?php foreach ($stations as $station): ?>
                                            <option value="<?php echo $station['id']; ?>" 
                                                    <?php echo (isset($_POST['station_id']) ? ($_POST['station_id'] == $station['id']) : ($station['id'] == $driver['station_id'])) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($station['station_code'] . ' - ' . $station['station_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Your assigned station is pre-selected</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tour_number" class="form-label">Tour Number *</label>
                                    <input type="text" class="form-control" id="tour_number" name="tour_number" 
                                           value="<?php echo isset($_POST['tour_number']) ? htmlspecialchars($_POST['tour_number']) : ''; ?>"
                                           maxlength="7" placeholder="e.g., CA_A213" required style="text-transform: uppercase;">
                                    <div class="form-text">Maximum 7 characters (e.g., CA_A213)</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="van_id" class="form-label">Vehicle *</label>
                                    <input type="hidden" name="van_id" value="<?php echo $assigned_van ? $assigned_van['id'] : ''; ?>">
                                    <input type="text" class="form-control calculated-field" readonly
                                           value="<?php echo $assigned_van ? htmlspecialchars($assigned_van['license_plate'] . ' (' . $assigned_van['make'] . ' ' . $assigned_van['model'] . ')') : 'No vehicle assigned'; ?>">
                                    <div class="form-text">Your assigned vehicle (cannot be changed)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kilometers Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-road me-2"></i>
                                Kilometers
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="km_start" class="form-label">Start KM *</label>
                                    <input type="number" class="form-control" id="km_start" name="km_start" 
                                           value="<?php echo isset($_POST['km_start']) ? htmlspecialchars($_POST['km_start']) : ''; ?>"
                                           min="0" step="1" required>
                                    <div class="form-text">Odometer reading at start</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="km_end" class="form-label">End KM *</label>
                                    <input type="number" class="form-control" id="km_end" name="km_end" 
                                           value="<?php echo isset($_POST['km_end']) ? htmlspecialchars($_POST['km_end']) : ''; ?>"
                                           min="0" step="1" required>
                                    <div class="form-text">Odometer reading at end</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="km_total" class="form-label">Total KM</label>
                                    <input type="number" class="form-control calculated-field" id="km_total" readonly>
                                    <div class="form-text">Automatically calculated</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Times Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>
                                Working Times
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="scanner_login" class="form-label">Scanner Login *</label>
                                    <input type="time" class="form-control time-input" id="scanner_login" name="scanner_login" 
                                           value="<?php echo isset($_POST['scanner_login']) ? htmlspecialchars($_POST['scanner_login']) : ''; ?>" required>
                                    <div class="form-text">Time you logged into your scanner</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="depo_departure" class="form-label">Depot Departure *</label>
                                    <input type="time" class="form-control time-input" id="depo_departure" name="depo_departure" 
                                           value="<?php echo isset($_POST['depo_departure']) ? htmlspecialchars($_POST['depo_departure']) : ''; ?>" required>
                                    <div class="form-text">Time you left the depot</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_delivery" class="form-label">First Delivery *</label>
                                    <input type="time" class="form-control time-input" id="first_delivery" name="first_delivery" 
                                           value="<?php echo isset($_POST['first_delivery']) ? htmlspecialchars($_POST['first_delivery']) : ''; ?>" required>
                                    <div class="form-text">Time of your first delivery</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_delivery" class="form-label">Last Delivery *</label>
                                    <input type="time" class="form-control time-input" id="last_delivery" name="last_delivery" 
                                           value="<?php echo isset($_POST['last_delivery']) ? htmlspecialchars($_POST['last_delivery']) : ''; ?>" required>
                                    <div class="form-text">Time of your last delivery</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="depo_return" class="form-label">
                                        Depot Return *
                                        <i class="fas fa-question-circle help-icon ms-1" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top"
                                           title="This is the last field by which your working hours are calculated. Enter either the exact time you returned to the station or add 30-40 minutes on top of your last delivery if you're going straight home with no packages to return."></i>
                                    </label>
                                    <input type="time" class="form-control time-input" id="depo_return" name="depo_return" 
                                           value="<?php echo isset($_POST['depo_return']) ? htmlspecialchars($_POST['depo_return']) : ''; ?>" required>
                                    <div class="form-text">Return time or last delivery + 30-40min</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="break_display" class="form-label">Break Time</label>
                                    <input type="text" class="form-control calculated-field" id="break_display" readonly>
                                    <div class="form-text">30min (≤9h) or 45min (>9h)</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="total_hours_display" class="form-label">Total Hours</label>
                                    <input type="text" class="form-control calculated-field" id="total_hours_display" readonly>
                                    <div class="form-text">Automatically calculated</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                        <div>
                            <a href="driver_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Cancel
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary btn-lg" <?php echo !$assigned_van ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane me-1"></i>Submit Working Hours
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-uppercase tour number
            document.getElementById('tour_number').addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
            
            // Calculate total kilometers
            function calculateKilometers() {
                const start = parseInt(document.getElementById('km_start').value) || 0;
                const end = parseInt(document.getElementById('km_end').value) || 0;
                const total = Math.max(0, end - start);
                document.getElementById('km_total').value = total;
            }
            
            // Calculate working hours
            function calculateHours() {
                const scannerLogin = document.getElementById('scanner_login').value;
                const depoReturn = document.getElementById('depo_return').value;
                
                if (scannerLogin && depoReturn) {
                    try {
                        const workDate = document.getElementById('work_date').value;
                        let loginTime = new Date(workDate + 'T' + scannerLogin);
                        let returnTime = new Date(workDate + 'T' + depoReturn);
                        
                        // Handle overnight shifts
                        if (returnTime < loginTime) {
                            returnTime.setDate(returnTime.getDate() + 1);
                        }
                        
                        const totalMinutes = (returnTime - loginTime) / (1000 * 60);
                        
                        // Calculate break
                        const breakMinutes = totalMinutes > (9 * 60) ? 45 : 30;
                        document.getElementById('break_display').value = breakMinutes + ' minutes';
                        
                        // Calculate total hours
                        const workingMinutes = totalMinutes - breakMinutes;
                        const hours = Math.floor(workingMinutes / 60);
                        const minutes = Math.round(workingMinutes % 60);
                        
                        if (workingMinutes > 0) {
                            document.getElementById('total_hours_display').value = hours + 'h ' + minutes + 'm';
                        } else {
                            document.getElementById('total_hours_display').value = 'Invalid times';
                        }
                    } catch (error) {
                        document.getElementById('total_hours_display').value = 'Error calculating';
                    }
                } else {
                    document.getElementById('break_display').value = '';
                    document.getElementById('total_hours_display').value = '';
                }
            }
            
            // Add event listeners
            document.getElementById('km_start').addEventListener('input', calculateKilometers);
            document.getElementById('km_end').addEventListener('input', calculateKilometers);
            document.getElementById('scanner_login').addEventListener('input', calculateHours);
            document.getElementById('depo_return').addEventListener('input', calculateHours);
            document.getElementById('work_date').addEventListener('change', calculateHours);
            
            // Initial calculations
            calculateKilometers();
            calculateHours();
            
            // Form validation
            document.getElementById('workingHoursForm').addEventListener('submit', function(e) {
                const kmStart = parseInt(document.getElementById('km_start').value) || 0;
                const kmEnd = parseInt(document.getElementById('km_end').value) || 0;
                
                if (kmEnd <= kmStart) {
                    e.preventDefault();
                    alert('End kilometers must be greater than start kilometers.');
                    return false;
                }
                
                const totalHours = document.getElementById('total_hours_display').value;
                if (totalHours === 'Invalid times' || totalHours === 'Error calculating' || totalHours === '') {
                    e.preventDefault();
                    alert('Please check your time entries. There seems to be an error in the calculation.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>