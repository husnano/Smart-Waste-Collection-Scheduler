-- Smart Waste Collection Scheduler
-- Import in phpMyAdmin: Import tab → choose this file → Go
-- This DROPS the old database first so re-import does not fail with "Table already exists".

DROP DATABASE IF EXISTS smartwaste_db;
CREATE DATABASE smartwaste_db;
USE smartwaste_db;

CREATE TABLE neighbourhoods (
    neighbourhood_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    area_code VARCHAR(20) DEFAULT NULL
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('resident', 'admin') NOT NULL DEFAULT 'resident',
    neighbourhood_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (neighbourhood_id) REFERENCES neighbourhoods(neighbourhood_id) ON DELETE SET NULL
);

CREATE TABLE collection_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    neighbourhood_id INT NOT NULL,
    waste_type ENUM('General Waste', 'Recycling', 'Garden Waste') NOT NULL,
    collection_day VARCHAR(20) NOT NULL,
    next_collection_date DATE NOT NULL,
    collection_time VARCHAR(40) DEFAULT '7:00 AM',
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (neighbourhood_id) REFERENCES neighbourhoods(neighbourhood_id) ON DELETE CASCADE
);

CREATE TABLE pickup_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_date DATE NOT NULL,
    pickup_time VARCHAR(40) DEFAULT NULL,
    waste_type VARCHAR(50) NOT NULL,
    description TEXT,
    status ENUM('Pending', 'Approved', 'Completed', 'Rejected') DEFAULT 'Pending',
    fee DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    payment_status ENUM('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_reference VARCHAR(40) DEFAULT NULL,
    payment_details VARCHAR(150) DEFAULT NULL,
    admin_note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE complaints (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(150) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    status ENUM('Submitted', 'Under Review', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Submitted',
    action_taken TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    type ENUM('Announcement', 'Campaign') NOT NULL DEFAULT 'Announcement',
    posted_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE reminders (
    reminder_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    remind_on DATE NOT NULL,
    is_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES collection_schedules(schedule_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_schedule (user_id, schedule_id)
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(30) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    link VARCHAR(255) DEFAULT NULL,
    related_id INT DEFAULT 0,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_type_related (user_id, type, related_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Sample neighbourhoods
INSERT INTO neighbourhoods (name, area_code) VALUES
('Taman Melati', 'TM01'),
('Taman Desa', 'TD02'),
('Bandar Baru', 'BB03'),
('Kampung Sungai', 'KS04');

-- Sample collection schedules (adjust dates as needed)
INSERT INTO collection_schedules (neighbourhood_id, waste_type, collection_day, next_collection_date, collection_time, notes) VALUES
(1, 'General Waste', 'Monday', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '7:00 AM', 'Place bins by 7am'),
(1, 'Recycling', 'Thursday', DATE_ADD(CURDATE(), INTERVAL 6 DAY), '8:00 AM', 'Separate paper, plastic, glass'),
(2, 'General Waste', 'Tuesday', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '7:00 AM', 'Place bins by 7am'),
(2, 'Recycling', 'Friday', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '8:00 AM', 'Rinse containers before recycling'),
(3, 'General Waste', 'Wednesday', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '7:00 AM', NULL),
(3, 'Recycling', 'Saturday', DATE_ADD(CURDATE(), INTERVAL 8 DAY), '9:00 AM', NULL),
(4, 'General Waste', 'Monday', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '7:00 AM', NULL),
(4, 'Recycling', 'Thursday', DATE_ADD(CURDATE(), INTERVAL 6 DAY), '8:00 AM', NULL);

-- After import, open setup/create_admin.php in browser to create admin account.

INSERT INTO announcements (title, content, type, posted_by) VALUES
('Welcome to SmartWaste', 'Register an account, choose your neighbourhood, and view your collection calendar.', 'Announcement', NULL),
('Recycle Right Campaign', 'Join our community campaign: reduce single-use plastic and sort recyclables correctly. Every small action counts!', 'Campaign', NULL);
