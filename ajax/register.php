<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$errors = validateRegistrationInput($payload);

if ($errors !== []) {
    jsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
}

$db = getDb();

$emailVerifiedStmt = $db->prepare('SELECT verified_at FROM otp_verifications WHERE channel = :channel AND recipient = :recipient ORDER BY id DESC LIMIT 1');
$emailVerifiedStmt->execute(['channel' => 'email', 'recipient' => $payload['email_id']]);
$emailRecord = $emailVerifiedStmt->fetch();
$emailOk = $emailRecord && $emailRecord['verified_at'] !== null;

$mobileVerifiedStmt = $db->prepare('SELECT verified_at FROM otp_verifications WHERE channel = :channel AND recipient = :recipient ORDER BY id DESC LIMIT 1');
$mobileVerifiedStmt->execute(['channel' => 'mobile', 'recipient' => $payload['mobile_no']]);
$mobileRecord = $mobileVerifiedStmt->fetch();
$mobileOk = $mobileRecord && $mobileRecord['verified_at'] !== null;

if (!$emailOk || !$mobileOk) {
    jsonResponse(['success' => false, 'message' => 'Email and mobile OTP verification are mandatory.'], 422);
}

$check = $db->prepare('SELECT COUNT(*) FROM applicants WHERE email_id = :email_id OR mobile_no = :mobile_no');
$check->execute(['email_id' => $payload['email_id'], 'mobile_no' => $payload['mobile_no']]);
if ((int) $check->fetchColumn() > 0) {
    jsonResponse(['success' => false, 'message' => 'Email ID or mobile number already registered.'], 409);
}

$applicationId = generateApplicationId($db);
$stmt = $db->prepare('INSERT INTO applicants (
    application_id, candidate_name, father_name, mother_name, date_of_birth, gender,
    identification_type, identification_no, mobile_no, email_id, password_hash, email_verified_at, mobile_verified_at
) VALUES (
    :application_id, :candidate_name, :father_name, :mother_name, :date_of_birth, :gender,
    :identification_type, :identification_no, :mobile_no, :email_id, :password_hash, NOW(), NOW()
)');
$stmt->execute([
    'application_id' => $applicationId,
    'candidate_name' => $payload['candidate_name'],
    'father_name' => $payload['father_name'],
    'mother_name' => $payload['mother_name'],
    'date_of_birth' => $payload['date_of_birth'] ?: null,
    'gender' => $payload['gender'],
    'identification_type' => $payload['identification_type'],
    'identification_no' => $payload['identification_no'],
    'mobile_no' => $payload['mobile_no'],
    'email_id' => $payload['email_id'],
    'password_hash' => password_hash((string) $payload['password'], PASSWORD_DEFAULT),
]);

jsonResponse(['success' => true, 'application_id' => $applicationId, 'message' => 'Step 1 registration completed.']);
