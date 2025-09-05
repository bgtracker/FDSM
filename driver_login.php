<?php
require_once 'config.php';

$current_page = 'login';
$page_title = 'Driver Login - Van Fleet Management';
$error_message = '';
$success_message = '';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isDriverLoggedIn()) {
        header('Location: driver_dashboard.php');
        exit();
    } else {
        header('Location: dashboard.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = strtoupper(trim($_POST['driver_id']));
    $password = $_POST['password'];
    
    if (empty($driver_id) || empty($password)) {
        $error_message = 'Please enter both Driver ID and password.';
    } else {
        try {
            // Check if driver exists and driver_id matches password
            $stmt = $pdo->prepare("SELECT d.*, s.station_code, s.station_name FROM drivers d LEFT JOIN stations s ON d.station_id = s.id WHERE d.driver_id = ?");
            $stmt->execute([$driver_id]);
            $driver = $stmt->fetch();
            
            if ($driver && $driver_id === strtoupper($password)) {
                // Create driver session
                $_SESSION['user_id'] = 'driver_' . $driver['id'];
                $_SESSION['driver_id'] = $driver['id'];
                $_SESSION['username'] = $driver['driver_id'];
                $_SESSION['user_type'] = 'driver';
                $_SESSION['station_id'] = $driver['station_id'];
                
                header('Location: driver_dashboard.php');
                exit();
            } else {
                $error_message = 'Invalid Driver ID or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again.';
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
        body {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
        }
        .login-card {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-tie fa-3x text-success mb-3"></i>
                            <h3>Driver Login</h3>
                            <p class="text-muted">Enter your Driver ID to access your dashboard</p>
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
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="driver_id" class="form-label">Driver ID</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-id-card"></i>
                                    </span>
                                    <input type="text" class="form-control" id="driver_id" name="driver_id" 
                                           value="<?php echo isset($_POST['driver_id']) ? htmlspecialchars($_POST['driver_id']) : ''; ?>" 
                                           required maxlength="30" style="text-transform: uppercase;">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="form-text">Your password is the same as your Driver ID</div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>

                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login Selection
                            </a>
                        </div>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Having trouble logging in? Contact your station manager.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-uppercase driver ID input
        document.getElementById('driver_id').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>