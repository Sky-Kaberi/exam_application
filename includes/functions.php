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
    $namePattern = '/^[A-Za-z ]+$/';
    $salutationPattern = '/\b(?:late|mr|ms|mrs|dr|prof)\b\.?/i';
    $allowedGenders = ['Male', 'Female', 'Third Gender'];
    $allowedIdentificationTypes = [
        'School ID card',
        'Voter ID',
        'Passport',
        'Ration Card with Photograph',
        'Class 10 admit card with Photograph',
        'Any other Valid Govt. Identity card With Photograph',
    ];

    $candidateName = trim((string) ($data['candidate_name'] ?? ''));
    if ($candidateName === '') {
        $errors['candidate_name'] = 'Candidate name is required.';
    } elseif (strlen($candidateName) > 46) {
        $errors['candidate_name'] = 'Candidate name must be maximum 46 characters.';
    } elseif (!preg_match($namePattern, $candidateName)) {
        $errors['candidate_name'] = 'Candidate name can only contain letters and spaces.';
    }

    $fatherName = trim((string) ($data['father_name'] ?? ''));
    if ($fatherName === '') {
        $errors['father_name'] = 'Father name is required.';
    } elseif (strlen($fatherName) > 46) {
        $errors['father_name'] = 'Father name must be maximum 46 characters.';
    } elseif (!preg_match($namePattern, $fatherName)) {
        $errors['father_name'] = 'Father name can only contain letters and spaces.';
    } elseif (preg_match($salutationPattern, $fatherName)) {
        $errors['father_name'] = 'Father name must not include salutations such as Late, Mr., Ms., Mrs., Dr., Prof.';
    }

    $motherName = trim((string) ($data['mother_name'] ?? ''));
    if ($motherName === '') {
        $errors['mother_name'] = 'Mother name is required.';
    } elseif (strlen($motherName) > 46) {
        $errors['mother_name'] = 'Mother name must be maximum 46 characters.';
    } elseif (!preg_match($namePattern, $motherName)) {
        $errors['mother_name'] = 'Mother name can only contain letters and spaces.';
    } elseif (preg_match($salutationPattern, $motherName)) {
        $errors['mother_name'] = 'Mother name must not include salutations such as Late, Mr., Ms., Mrs., Dr., Prof.';
    }

    if (!preg_match('/^[0-9]{10}$/', $data['mobile_no'] ?? '')) {
        $errors['mobile_no'] = 'Enter a valid 10 digit mobile number.';
    }

    if (!filter_var($data['email_id'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors['email_id'] = 'Enter a valid email address.';
    }

    if (!in_array($data['gender'] ?? '', $allowedGenders, true)) {
        $errors['gender'] = 'Select a valid gender.';
    }

    if (trim((string) ($data['date_of_birth'] ?? '')) === '') {
        $errors['date_of_birth'] = 'Date of birth is required.';
    }

    $password = (string) ($data['password'] ?? '');
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*\-]).{8,13}$/', $password)) {
        $errors['password'] = 'Password must be 8-13 chars and include uppercase, lowercase, number and special character.';
    }

    if ($password !== (string) ($data['confirm_password'] ?? '')) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (trim($data['identification_no'] ?? '') === '') {
        $errors['identification_no'] = 'Identification number is required.';
    }

    if (!in_array($data['identification_type'] ?? '', $allowedIdentificationTypes, true)) {
        $errors['identification_type'] = 'Identification type is invalid.';
    }

    if (trim((string) ($data['captcha_token'] ?? '')) === '') {
        $errors['captcha_token'] = 'CAPTCHA verification is required.';
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

    if ($channel === 'email') {
        $emailSent = sendEmailOtp($recipient, $otp);

        return [
            'success' => $emailSent,
            'message' => $emailSent
                ? 'OTP sent to email successfully.'
                : 'Unable to send OTP email right now. Please retry.',
        ];
    }

    return [
        'success' => true,
        'message' => 'OTP generated successfully.',
        'display_otp' => $otp,
    ];
}

function sendEmailOtp(string $recipient, string $otp): bool
{
    $subject = 'Your Email OTP for Exam Application';
    $message = "Your OTP for exam application registration is: {$otp}. It is valid for " . OTP_EXPIRY_MINUTES . ' minutes.';
    $headers = 'From: noreply@exam-application.local' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8';

    return mail($recipient, $subject, $message, $headers);
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

function verifyTurnstileToken(string $token, ?string $remoteIp = null): array
{
    if (TURNSTILE_SECRET_KEY === '') {
        return [
            'success' => false,
            'message' => 'CAPTCHA secret is not configured.',
        ];
    }

    if (trim($token) === '') {
        return [
            'success' => false,
            'message' => 'CAPTCHA token is missing.',
        ];
    }

    $payload = http_build_query([
        'secret' => TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $remoteIp ?? '',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 8,
        ],
    ]);

    $response = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($response === false) {
        return [
            'success' => false,
            'message' => 'Unable to reach CAPTCHA verification service.',
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || !($decoded['success'] ?? false)) {
        return [
            'success' => false,
            'message' => 'CAPTCHA verification failed. Please try again.',
        ];
    }

    return ['success' => true, 'message' => 'CAPTCHA verified.'];
}
