CREATE DATABASE IF NOT EXISTS exam_application CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE exam_application;

CREATE TABLE IF NOT EXISTS applicants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id VARCHAR(30) NOT NULL UNIQUE,
    candidate_name VARCHAR(150) NOT NULL,
    father_name VARCHAR(150) NOT NULL,
    mother_name VARCHAR(150) NOT NULL,
    date_of_birth DATE NULL,
    gender ENUM('Male', 'Female', 'Third Gender') NOT NULL,
    identification_type VARCHAR(50) NOT NULL,
    identification_no VARCHAR(100) NOT NULL,
    mobile_no VARCHAR(15) NOT NULL UNIQUE,
    email_id VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    mobile_verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_candidate_name (candidate_name),
    INDEX idx_identification (identification_type, identification_no)
);

CREATE TABLE IF NOT EXISTS otp_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('email', 'mobile') NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    otp_code VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel_recipient (channel, recipient),
    INDEX idx_expiry (expires_at)
);
