ALTER TABLE applicant_step2_courses
    MODIFY course_group_1 VARCHAR(100) NOT NULL DEFAULT '',
    MODIFY course_group_2 VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN application_fee INT UNSIGNED NOT NULL DEFAULT 3000 AFTER exam_city;
