-- MCSM AuthMe Self-Register Database Schema

-- Registration requests table
CREATE TABLE IF NOT EXISTS `registration_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(16) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `password_payload` LONGTEXT NOT NULL,
    `note` TEXT,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `ip_address` VARCHAR(45),
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME,
    `processed_by` VARCHAR(255),
    `mcsm_daemon_id` VARCHAR(255),
    `mcsm_instance_id` VARCHAR(255),
    `admin_notes` TEXT,
    `rejection_reason` TEXT,
    UNIQUE KEY `uk_username_pending` (`username`, `status`),
    INDEX `idx_status` (`status`),
    INDEX `idx_requested_at` (`requested_at`),
    INDEX `idx_processed_at` (`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

