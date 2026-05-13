ALTER TABLE applicants
    MODIFY COLUMN payment_status ENUM('unpaid', 'payment_submitted', 'paid') NOT NULL DEFAULT 'unpaid',
    ADD COLUMN payment_receipt_file VARCHAR(255) NULL AFTER transaction_reference;
