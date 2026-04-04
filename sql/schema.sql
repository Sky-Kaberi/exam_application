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
    registrant_ip_address VARCHAR(45) NULL,
    email_verified_at DATETIME NULL,
    mobile_verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_candidate_name (candidate_name),
    INDEX idx_identification (identification_type, identification_no)
);

CREATE TABLE IF NOT EXISTS applicant_step2_basic (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    nationality VARCHAR(30) NOT NULL DEFAULT 'Indian',
    domicile VARCHAR(30) NOT NULL,
    religion VARCHAR(30) NOT NULL,
    category VARCHAR(30) NOT NULL,
    sub_category_details TEXT NULL,
    pwd_status ENUM('Yes', 'No') NOT NULL,
    disability_type VARCHAR(100) NULL,
    disability_percentage DECIMAL(5,2) NULL,
    qualifying_examination VARCHAR(255) NOT NULL,
    pass_status VARCHAR(30) NOT NULL,
    year_of_passing VARCHAR(10) NOT NULL,
    institute_name_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_step2_basic_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    INDEX idx_step2_basic_lookup (applicant_id, domicile, category)
);

CREATE TABLE IF NOT EXISTS applicant_step2_address (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    corr_premises VARCHAR(255) NOT NULL,
    corr_sub_locality VARCHAR(255) NULL,
    corr_locality VARCHAR(255) NOT NULL,
    corr_country VARCHAR(100) NOT NULL,
    corr_state VARCHAR(100) NOT NULL,
    corr_district VARCHAR(100) NOT NULL,
    corr_pin_code VARCHAR(10) NOT NULL,
    same_as_correspondence TINYINT(1) NOT NULL DEFAULT 0,
    perm_premises VARCHAR(255) NOT NULL,
    perm_sub_locality VARCHAR(255) NULL,
    perm_locality VARCHAR(255) NOT NULL,
    perm_country VARCHAR(100) NOT NULL,
    perm_state VARCHAR(100) NOT NULL,
    perm_district VARCHAR(100) NOT NULL,
    perm_pin_code VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_step2_address_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applicant_step2_courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    course_group_1 VARCHAR(100) NOT NULL DEFAULT '',
    course_group_2 VARCHAR(100) NOT NULL DEFAULT '',
    exam_city VARCHAR(150) NOT NULL,
    application_fee INT UNSIGNED NOT NULL DEFAULT 3000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_step2_courses_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applicant_step2_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    photo_path VARCHAR(255) NULL,
    signature_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_step2_images_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applicant_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    step2_basic_completed TINYINT(1) NOT NULL DEFAULT 0,
    step2_address_completed TINYINT(1) NOT NULL DEFAULT 0,
    step2_courses_completed TINYINT(1) NOT NULL DEFAULT 0,
    step2_images_completed TINYINT(1) NOT NULL DEFAULT 0,
    final_submitted_at DATETIME NULL,
    last_tab VARCHAR(30) NOT NULL DEFAULT 'basic',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_progress_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
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
