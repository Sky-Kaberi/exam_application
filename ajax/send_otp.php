<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$payload = decodeJsonRequestBody();
$channel = $payload['channel'] ?? '';
$recipient = trim((string) ($payload['recipient'] ?? ''));

if (!in_array($channel, ['mobile', 'email'], true) || $recipient === '') {
    jsonResponse(['success' => false, 'message' => 'Channel and recipient are required.'], 422);
}

if ($channel === 'mobile' && !preg_match('/^[0-9]{10}$/', $recipient)) {
    jsonResponse(['success' => false, 'message' => 'Enter a valid mobile number.'], 422);
}

if ($channel === 'email' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Enter a valid email address.'], 422);
}


$existingApplicantStmt = null;
if ($channel === 'mobile') {
    $existingApplicantStmt = getDb()->prepare('SELECT id FROM applicants WHERE mobile_no = :recipient LIMIT 1');
} else {
    $existingApplicantStmt = getDb()->prepare('SELECT id FROM applicants WHERE LOWER(email_id) = LOWER(:recipient) LIMIT 1');
}
$existingApplicantStmt->execute(['recipient' => $recipient]);
if ($existingApplicantStmt->fetch()) {
    $fieldLabel = $channel === 'mobile' ? 'Mobile number' : 'Email ID';
    jsonResponse([
        'success' => false,
        'message' => $fieldLabel . ' already exists. Please use a new ' . strtolower($fieldLabel) . '.',
    ], 409);
}

$result = createOtpRecord(getDb(), $channel, $recipient);
jsonResponse($result, $result['success'] ? 200 : 429);
