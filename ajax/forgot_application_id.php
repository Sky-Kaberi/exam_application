<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$payload = decodeJsonRequestBody();
$email = trim((string) ($payload['email_id'] ?? ''));
$mobile = preg_replace('/\D+/', '', (string) ($payload['mobile_no'] ?? '')) ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{10}$/', $mobile)) {
    jsonResponse(['success' => false, 'message' => 'Enter the registered email ID and 10 digit mobile number.'], 422);
}

$db = getDb();
$stmt = $db->prepare('SELECT application_id, candidate_name, email_id, mobile_no FROM applicants WHERE email_id = :email_id AND mobile_no = :mobile_no LIMIT 1');
$stmt->execute([
    'email_id' => $email,
    'mobile_no' => $mobile,
]);
$applicant = $stmt->fetch();

if (!$applicant) {
    jsonResponse(['success' => false, 'message' => 'No application found for the supplied email ID and mobile number.'], 404);
}

$emailSent = sendForgotApplicationIdEmail(
    (string) $applicant['email_id'],
    (string) $applicant['application_id'],
    (string) $applicant['candidate_name']
);
$smsSent = sendForgotApplicationIdSms((string) $applicant['mobile_no'], (string) $applicant['application_id']);

jsonResponse([
    'success' => true,
    'message' => 'Application ID recovery request processed.',
    'email_sent' => $emailSent,
    'sms_sent' => $smsSent,
]);
