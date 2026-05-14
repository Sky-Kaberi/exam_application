-- Collapse any legacy gateway failure state into the existing rejected correction flow.
UPDATE applicants
SET payment_status = 'rejected'
WHERE payment_status = 'failed';

ALTER TABLE applicants
    MODIFY COLUMN payment_status ENUM('not_submitted', 'pending_verification', 'paid', 'rejected') NOT NULL DEFAULT 'not_submitted';
