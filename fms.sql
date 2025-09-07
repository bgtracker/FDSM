-- Van Fleet Management Database Schema
CREATE DATABASE van_fleet_management;
USE van_fleet_management;

-- Users table (Dispatchers and Station Managers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    user_type ENUM('dispatcher', 'station_manager') NOT NULL,
    station_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Stations table
CREATE TABLE stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_code VARCHAR(10) UNIQUE NOT NULL,
    station_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vans table
CREATE TABLE vans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    vin_number VARCHAR(17) UNIQUE NOT NULL,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    station_id INT NOT NULL,
    status ENUM('in_use', 'available', 'reserve') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
);

-- Van images table
CREATE TABLE van_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    van_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
);

-- Van videos table
CREATE TABLE van_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    van_id INT NOT NULL,
    video_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
);

-- Van documents table (registration documents)
CREATE TABLE van_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    van_id INT NOT NULL,
    document_path VARCHAR(255) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE
);

-- Drivers table
CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id VARCHAR(30) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE,
    phone_number VARCHAR(20),
    address TEXT,
    hire_date DATE,
    profile_picture VARCHAR(255),
    station_id INT NOT NULL,
    van_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE SET NULL
);

-- Driver documents table
CREATE TABLE driver_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    document_path VARCHAR(255) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

-- Van maintenance records table
CREATE TABLE van_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    van_id INT NOT NULL,
    user_id INT NOT NULL,
    maintenance_record TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Driver leaves table
CREATE TABLE driver_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    station_id INT NOT NULL,
    leave_type ENUM('paid', 'sick') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_station_date (station_id, start_date, end_date)
);

-- Add salary_account field to drivers table
ALTER TABLE drivers ADD COLUMN salary_account VARCHAR(34) NULL AFTER address;

-- Add index for salary_account if needed for searches
CREATE INDEX idx_salary_account ON drivers(salary_account);

-- Working Hours Database Schema
-- Add this to your existing database

-- Working hours submissions table
CREATE TABLE IF NOT EXISTS working_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    station_id INT NOT NULL,
    work_date DATE NOT NULL,
    tour_number VARCHAR(7) NOT NULL,
    van_id INT NOT NULL,
    
    -- Kilometers
    km_start INT NOT NULL,
    km_end INT NOT NULL,
    km_total INT GENERATED ALWAYS AS (km_end - km_start) STORED,
    
    -- Times (stored as TIME type for easier calculations)
    scanner_login TIME NOT NULL,
    depo_departure TIME NOT NULL,
    first_delivery TIME NOT NULL,
    last_delivery TIME NOT NULL,
    depo_return TIME NOT NULL,
    break_minutes INT NOT NULL,
    total_minutes INT NOT NULL,
    
    -- Status and approval
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    FOREIGN KEY (van_id) REFERENCES vans(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_driver_date (driver_id, work_date),
    INDEX idx_station_date (station_id, work_date),
    INDEX idx_status (status),
    INDEX idx_work_date (work_date),
    
    -- Unique constraint to prevent duplicate submissions
    UNIQUE KEY unique_driver_date (driver_id, work_date)
);

-- Working hours edits log (to track changes made by management)
CREATE TABLE IF NOT EXISTS working_hours_edits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    working_hours_id INT NOT NULL,
    edited_by INT NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value VARCHAR(100) NOT NULL,
    new_value VARCHAR(100) NOT NULL,
    edit_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (working_hours_id) REFERENCES working_hours(id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_working_hours_id (working_hours_id),
    INDEX idx_edited_by (edited_by)
);

CREATE TABLE IF NOT EXISTS `system_activation` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_key` varchar(255) NOT NULL,
    `activated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `activated_by` int(11) DEFAULT NULL,
    `system_info` text DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    PRIMARY KEY (`id`),
    UNIQUE KEY `product_key` (`product_key`),
    KEY `activated_by` (`activated_by`),
    FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
);

-- Insert initial stations
INSERT INTO stations (station_code, station_name) VALUES
('DRP4', 'DRP4 Station'),
('DHE1', 'DHE1 Station'),
('DHE4', 'DHE4 Station'),
('DHE6', 'DHE6 Station'),
('DBW1', 'DBW1 Station');

-- Insert initial users with hashed passwords (Amazon2018!)
-- Password hash generated with: password_hash('Amazon2018!', PASSWORD_DEFAULT)
INSERT INTO users (username, password, first_name, middle_name, last_name, user_type, station_id) VALUES
('pnozharov', '$2y$10$uzFygt6MzJMVvZmJyxcRkO5ZTCA2xjpn4K9l.1I6tCXNXNWgfGQ/y', 'Pavel', 'Boitchev', 'Nozharov', 'station_manager', 1),
('vmarkov', '$2y$10$uzFygt6MzJMVvZmJyxcRkO5ZTCA2xjpn4K9l.1I6tCXNXNWgfGQ/y', 'Vasko', 'Kalinov', 'Markov', 'dispatcher', 1);

-- Add foreign key constraint for users table
ALTER TABLE users ADD FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL;