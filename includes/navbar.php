<?php
$user = getCurrentUser();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <div class="d-flex align-items-center">
            <button class="navbar-toggler me-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-text text-white-50">
                FDMS v1.132
            </span>
        </div>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'home') ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'fleet') ? 'active' : ''; ?>" href="fleet.php">
                            <i class="fas fa-truck me-1"></i>My Fleet
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'maintenance') ? 'active' : ''; ?>" href="maintenance.php">
                            <i class="fas fa-tools me-1"></i>Van Maintenance
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'drivers') ? 'active' : ''; ?>" href="drivers.php">
                            <i class="fas fa-users me-1"></i>My Drivers
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'leaves') ? 'active' : ''; ?>" href="manage_leaves.php">
                            <i class="fas fa-calendar-alt me-1"></i>Manage Leaves
                        </a>
                    </li>
                    
                    <?php if ($user && $user['user_type'] === 'station_manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page === 'stations') ? 'active' : ''; ?>" href="stations.php">
                                <i class="fas fa-building me-1"></i>Manage Stations
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'about') ? 'active' : ''; ?>" href="about.php">
                        <i class="fas fa-info-circle me-1"></i>About
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>