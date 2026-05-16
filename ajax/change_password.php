<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicantSession = requireApplicantLoginForJson();
$payload = decodeJsonRequestBody();
$currentPassword = (string) ($payload['current_password'] ?? '');
$newPassword = (string) ($payload['new_password'] ?? '');
$confirmPassword = (string) ($payload['confirm_password'] ?? '');
$errors = [];

if ($currentPassword === '') {
    $errors['current_password'] = 'Current password is required.';
}

$newPasswordError = validateApplicantPassword($newPassword);
if ($newPasswordError !== null) {
    $errors['new_password'] = $newPasswordError;
}

if ($newPassword !== $confirmPassword) {
    $errors['confirm_password'] = 'Confirm password must match new password.';
}

if ($currentPassword !== '' && $newPassword !== '' && hash_equals($currentPassword, $newPassword)) {
    $errors['new_password'] = 'New password must be different from current password.';
}

if ($errors !== []) {
    jsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
}

$db = getDb();
$stmt = $db->prepare('SELECT id, password_hash FROM applicants WHERE id = :id LIMIT 1');
$stmt->execute(['id' => (int) $applicantSession['id']]);
$applicant = $stmt->fetch();

if (!$applicant || !password_verify($currentPassword, (string) $applicant['password_hash'])) {
    jsonResponse([
        'success' => false,
        'message' => 'Current password is incorrect.',
        'errors' => ['current_password' => 'Current password is incorrect.'],
    ], 401);
}

$updateStmt = $db->prepare('UPDATE applicants SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
$updateStmt->execute([
    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
    'id' => (int) $applicant['id'],
]);

jsonResponse([
    'success' => true,
    'message' => 'Password changed successfully.',
]);
