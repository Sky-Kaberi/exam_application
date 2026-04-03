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
    course_group_1 VARCHAR(100) NOT NULL,
    course_group_2 VARCHAR(100) NOT NULL,
    exam_city VARCHAR(150) NOT NULL,
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
