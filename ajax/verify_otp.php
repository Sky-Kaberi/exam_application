<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$payload = decodeJsonRequestBody();

$verified = verifyOtpRecord(
    getDb(),
    (string) ($payload['channel'] ?? ''),
    trim((string) ($payload['recipient'] ?? '')),
    trim((string) ($payload['otp'] ?? ''))
);

$channel = (string) ($payload['channel'] ?? '');
$successMessage = 'OTP verified successfully.';
if ($channel === 'mobile') {
    $successMessage = 'Mobile No Verified Successfully';
} elseif ($channel === 'email') {
    $successMessage = 'Email Id Verified Successfully';
}

jsonResponse([
    'success' => $verified,
    'message' => $verified ? $successMessage : 'Invalid or expired OTP.',
], $verified ? 200 : 422);
