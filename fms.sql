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