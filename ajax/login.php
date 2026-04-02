<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$payload = decodeJsonRequestBody();
$applicationId = trim((string) ($payload['application_id'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($applicationId === '' || $password === '') {
    jsonResponse(['success' => false, 'message' => 'Application number and password are required.'], 422);
}

$db = getDb();
$stmt = $db->prepare('SELECT id, application_id, candidate_name, password_hash FROM applicants WHERE application_id = :application_id LIMIT 1');
$stmt->execute(['application_id' => $applicationId]);
$applicant = $stmt->fetch();

if (!$applicant || !password_verify($password, (string) $applicant['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid application number or password.'], 401);
}

loginApplicantSession($applicant);
unset($_SESSION['new_application_id']);

jsonResponse([
    'success' => true,
    'message' => 'Login successful.',
    'redirect_to' => '../public/step2.php',
]);
