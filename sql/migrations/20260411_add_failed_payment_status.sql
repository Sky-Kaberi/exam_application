-- Allow legacy or gateway failed payment submissions to follow the same correction flow as rejected payments.
ALTER TABLE applicants
    MODIFY COLUMN payment_status ENUM('not_submitted', 'pending_verification', 'paid', 'rejected', 'failed') NOT NULL DEFAULT 'not_submitted';
