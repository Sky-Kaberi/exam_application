<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
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

$result = createOtpRecord(getDb(), $channel, $recipient);
jsonResponse($result, $result['success'] ? 200 : 429);
