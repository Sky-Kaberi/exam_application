-- Manual SBI Collect payment verification workflow.
-- Run after the existing payment migrations.

ALTER TABLE applicants
    MODIFY COLUMN payment_status ENUM('unpaid', 'payment_submitted', 'not_submitted', 'pending_verification', 'paid', 'rejected') NOT NULL DEFAULT 'not_submitted',
    ADD COLUMN sbi_receipt_path VARCHAR(255) NULL AFTER payment_receipt_file,
    ADD COLUMN sbi_reference_no VARCHAR(80) NULL AFTER sbi_receipt_path,
    ADD COLUMN sbi_payment_date DATE NULL AFTER sbi_reference_no,
    ADD COLUMN payment_submitted_at DATETIME NULL AFTER sbi_payment_date,
    ADD COLUMN payment_verified_at DATETIME NULL AFTER payment_submitted_at,
    ADD COLUMN payment_verified_by BIGINT UNSIGNED NULL AFTER payment_verified_at,
    ADD COLUMN payment_admin_note TEXT NULL AFTER payment_verified_by,
    ADD INDEX idx_payment_status (payment_status),
    ADD INDEX idx_payment_verified_by (payment_verified_by);

UPDATE applicants
SET sbi_receipt_path = COALESCE(sbi_receipt_path, payment_receipt_file),
    sbi_reference_no = COALESCE(sbi_reference_no, transaction_reference),
    sbi_payment_date = COALESCE(sbi_payment_date, DATE(payment_datetime)),
    payment_submitted_at = CASE
        WHEN payment_status IN ('payment_submitted', 'pending_verification', 'paid') AND payment_submitted_at IS NULL THEN COALESCE(payment_datetime, updated_at)
        ELSE payment_submitted_at
    END,
    payment_verified_at = CASE
        WHEN payment_status = 'paid' AND payment_verified_at IS NULL THEN COALESCE(payment_datetime, updated_at)
        ELSE payment_verified_at
    END;

UPDATE applicants
SET payment_status = CASE
        WHEN payment_status = 'unpaid' THEN 'not_submitted'
        WHEN payment_status = 'payment_submitted' THEN 'pending_verification'
        ELSE payment_status
    END;

ALTER TABLE applicants
    MODIFY COLUMN payment_status ENUM('not_submitted', 'pending_verification', 'paid', 'rejected') NOT NULL DEFAULT 'not_submitted',
    ADD CONSTRAINT fk_applicants_payment_verified_by
        FOREIGN KEY (payment_verified_by) REFERENCES admin_users(id) ON DELETE SET NULL;
