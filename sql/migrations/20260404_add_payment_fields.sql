ALTER TABLE applicants
    ADD COLUMN payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid' AFTER mobile_verified_at,
    ADD COLUMN payment_mode VARCHAR(30) NULL AFTER payment_status,
    ADD COLUMN payment_amount INT UNSIGNED NULL AFTER payment_mode,
    ADD COLUMN payment_datetime DATETIME NULL AFTER payment_amount,
    ADD COLUMN transaction_reference VARCHAR(80) NULL AFTER payment_datetime,
    ADD COLUMN payment_demo_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER transaction_reference;
