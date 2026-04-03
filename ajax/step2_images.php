<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

$photoRules = ['min' => 10 * 1024, 'max' => 200 * 1024];
$signRules = ['min' => 4 * 1024, 'max' => 30 * 1024];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT photo_path, signature_path FROM applicant_step2_images WHERE applicant_id = :applicant_id LIMIT 1');
    $stmt->execute(['applicant_id' => $applicant['id']]);
    $row = $stmt->fetch() ?: ['photo_path' => null, 'signature_path' => null];

    jsonResponse(['success' => true, 'data' => $row]);
}

function validateImageFile(array $file, array $sizeRules, string $label): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return $label . ' upload failed. Please retry.';
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg'], true)) {
        return $label . ' must be JPG/JPEG format.';
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size < $sizeRules['min'] || $size > $sizeRules['max']) {
        return sprintf('%s size must be between %dKB and %dKB.', $label, (int) ($sizeRules['min'] / 1024), (int) ($sizeRules['max'] / 1024));
    }

    $mime = mime_content_type((string) ($file['tmp_name'] ?? ''));
    if (!in_array($mime, ['image/jpeg', 'image/pjpeg'], true)) {
        return $label . ' must be a valid JPEG image.';
    }

    return null;
}

$photoErr = isset($_FILES['photo']) ? validateImageFile($_FILES['photo'], $photoRules, 'Photograph') : null;
$signErr = isset($_FILES['signature']) ? validateImageFile($_FILES['signature'], $signRules, 'Signature') : null;
$errors = [];
if ($photoErr !== null) {
    $errors['photo'] = $photoErr;
}
if ($signErr !== null) {
    $errors['signature'] = $signErr;
}

$stmt = $db->prepare('SELECT photo_path, signature_path FROM applicant_step2_images WHERE applicant_id = :applicant_id LIMIT 1');
$stmt->execute(['applicant_id' => $applicant['id']]);
$existing = $stmt->fetch() ?: ['photo_path' => null, 'signature_path' => null];

if ($errors !== []) {
    jsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
}

$uploadDir = __DIR__ . '/../public/uploads/applicants/' . $applicant['application_id'];
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    jsonResponse(['success' => false, 'message' => 'Unable to create upload directory.'], 500);
}

$photoPath = $existing['photo_path'];
$signaturePath = $existing['signature_path'];

if (isset($_FILES['photo']) && (int) $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $photoName = 'photo_' . bin2hex(random_bytes(8)) . '.jpg';
    $target = $uploadDir . '/' . $photoName;
    if (!move_uploaded_file((string) $_FILES['photo']['tmp_name'], $target)) {
        jsonResponse(['success' => false, 'message' => 'Unable to save photograph.'], 500);
    }
    $photoPath = 'uploads/applicants/' . $applicant['application_id'] . '/' . $photoName;
}

if (isset($_FILES['signature']) && (int) $_FILES['signature']['error'] !== UPLOAD_ERR_NO_FILE) {
    $signName = 'signature_' . bin2hex(random_bytes(8)) . '.jpg';
    $target = $uploadDir . '/' . $signName;
    if (!move_uploaded_file((string) $_FILES['signature']['tmp_name'], $target)) {
        jsonResponse(['success' => false, 'message' => 'Unable to save signature.'], 500);
    }
    $signaturePath = 'uploads/applicants/' . $applicant['application_id'] . '/' . $signName;
}

if ($photoPath === null || $signaturePath === null) {
    jsonResponse(['success' => false, 'message' => 'Both photograph and signature are required at least once.'], 422);
}

$saveStmt = $db->prepare('INSERT INTO applicant_step2_images (applicant_id, photo_path, signature_path) VALUES (:applicant_id, :photo_path, :signature_path)
    ON DUPLICATE KEY UPDATE photo_path = VALUES(photo_path), signature_path = VALUES(signature_path)');
$saveStmt->execute([
    'applicant_id' => $applicant['id'],
    'photo_path' => $photoPath,
    'signature_path' => $signaturePath,
]);

upsertApplicantProgress($db, (int) $applicant['id'], ['step2_images_completed' => 1, 'last_tab' => 'image']);

jsonResponse(['success' => true, 'message' => 'Images saved successfully.', 'data' => ['photo_path' => $photoPath, 'signature_path' => $signaturePath]]);
