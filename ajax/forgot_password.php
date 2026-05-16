<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$payload = decodeJsonRequestBody();
$applicationId = trim((string) ($payload['application_id'] ?? ''));
$email = trim((string) ($payload['email_id'] ?? ''));
$mobile = preg_replace('/\D+/', '', (string) ($payload['mobile_no'] ?? '')) ?? '';

if ($applicationId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{10}$/', $mobile)) {
    jsonResponse(['success' => false, 'message' => 'Enter the Application ID, registered email ID and 10 digit mobile number.'], 422);
}

$db = getDb();
$stmt = $db->prepare('SELECT id, application_id, candidate_name, email_id, mobile_no FROM applicants WHERE application_id = :application_id AND email_id = :email_id AND mobile_no = :mobile_no LIMIT 1');
$stmt->execute([
    'application_id' => $applicationId,
    'email_id' => $email,
    'mobile_no' => $mobile,
]);
$applicant = $stmt->fetch();

if (!$applicant) {
    jsonResponse(['success' => false, 'message' => 'No application found for the supplied details.'], 404);
}

$newPassword = generateTemporaryPassword();
$updateStmt = $db->prepare('UPDATE applicants SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
$updateStmt->execute([
    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
    'id' => $applicant['id'],
]);

$emailSent = sendForgotPasswordEmail(
    (string) $applicant['email_id'],
    $newPassword,
    (string) $applicant['candidate_name']
);
$smsSent = sendForgotPasswordSms((string) $applicant['mobile_no'], $newPassword);

jsonResponse([
    'success' => true,
    'message' => 'Password recovery request processed. Use the password sent by SMS/email to login.',
    'email_sent' => $emailSent,
    'sms_sent' => $smsSent,
]);
