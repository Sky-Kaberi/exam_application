<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function generateOtp(): string
{
    return (string) random_int(100000, 999999);
}

function generateApplicationId(PDO $db): string
{
    do {
        $candidate = APPLICATION_PREFIX . date('Ymd') . random_int(1000, 9999);
        $stmt = $db->prepare('SELECT COUNT(*) FROM applicants WHERE application_id = :application_id');
        $stmt->execute(['application_id' => $candidate]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $candidate;
}

function validateRegistrationInput(array $data): array
{
    $errors = [];

    if (trim($data['candidate_name'] ?? '') === '') {
        $errors['candidate_name'] = 'Candidate name is required.';
    }

    if (trim($data['father_name'] ?? '') === '') {
        $errors['father_name'] = 'Father name is required.';
    }

    if (trim($data['mother_name'] ?? '') === '') {
        $errors['mother_name'] = 'Mother name is required.';
    }

    if (!preg_match('/^[0-9]{10}$/', $data['mobile_no'] ?? '')) {
        $errors['mobile_no'] = 'Enter a valid 10 digit mobile number.';
    }

    if (!filter_var($data['email_id'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors['email_id'] = 'Enter a valid email address.';
    }

    if (!in_array($data['gender'] ?? '', ['Male', 'Female', 'Other'], true)) {
        $errors['gender'] = 'Select a valid gender.';
    }

    if (($data['password'] ?? '') === '' || strlen((string) $data['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (trim($data['identification_no'] ?? '') === '') {
        $errors['identification_no'] = 'Identification number is required.';
    }

    if (trim($data['identification_type'] ?? '') === '') {
        $errors['identification_type'] = 'Identification type is required.';
    }

    return $errors;
}

function createOtpRecord(PDO $db, string $channel, string $recipient): array
{
    $stmt = $db->prepare('SELECT * FROM otp_verifications WHERE channel = :channel AND recipient = :recipient ORDER BY id DESC LIMIT 1');
    $stmt->execute(['channel' => $channel, 'recipient' => $recipient]);
    $latest = $stmt->fetch();

    if ($latest && strtotime((string) $latest['created_at']) > time() - OTP_RESEND_LIMIT_SECONDS) {
        return [
            'success' => false,
            'message' => 'Please wait before requesting another OTP.',
        ];
    }

    $otp = generateOtp();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $stmt = $db->prepare('INSERT INTO otp_verifications (channel, recipient, otp_code, expires_at) VALUES (:channel, :recipient, :otp_code, :expires_at)');
    $stmt->execute([
        'channel' => $channel,
        'recipient' => $recipient,
        'otp_code' => password_hash($otp, PASSWORD_DEFAULT),
        'expires_at' => $expiresAt,
    ]);

    $logLine = sprintf("[%s] %s OTP for %s is %s\n", date('c'), strtoupper($channel), $recipient, $otp);
    file_put_contents(__DIR__ . '/../logs/otp.log', $logLine, FILE_APPEND);

    return [
        'success' => true,
        'message' => 'OTP generated successfully. Check configured gateway/log.',
        'debug_otp' => $otp,
    ];
}

function verifyOtpRecord(PDO $db, string $channel, string $recipient, string $otp): bool
{
    $stmt = $db->prepare('SELECT * FROM otp_verifications WHERE channel = :channel AND recipient = :recipient AND verified_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute(['channel' => $channel, 'recipient' => $recipient]);
    $record = $stmt->fetch();

    if (!$record) {
        return false;
    }

    if (strtotime((string) $record['expires_at']) < time()) {
        return false;
    }

    if (!password_verify($otp, (string) $record['otp_code'])) {
        return false;
    }

    $update = $db->prepare('UPDATE otp_verifications SET verified_at = NOW() WHERE id = :id');
    $update->execute(['id' => $record['id']]);

    return true;
}
