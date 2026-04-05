ALTER TABLE applicant_progress
    ADD COLUMN payment_final_submitted_at DATETIME NULL AFTER final_submitted_at;
