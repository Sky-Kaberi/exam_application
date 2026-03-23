<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

$verified = verifyOtpRecord(
    getDb(),
    (string) ($payload['channel'] ?? ''),
    trim((string) ($payload['recipient'] ?? '')),
    trim((string) ($payload['otp'] ?? ''))
);

jsonResponse([
    'success' => $verified,
    'message' => $verified ? 'OTP verified successfully.' : 'Invalid or expired OTP.',
], $verified ? 200 : 422);
