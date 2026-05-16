<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

$step1Stmt = $db->prepare('SELECT application_id, candidate_name, father_name, mother_name, date_of_birth, gender, identification_type, identification_no, mobile_no, email_id, payment_status, payment_mode, payment_amount, payment_datetime, transaction_reference, sbi_reference_no, sbi_payment_date, payment_verified_at, payment_demo_flag FROM applicants WHERE id = :id LIMIT 1');
$step1Stmt->execute(['id' => $applicant['id']]);
$step1 = $step1Stmt->fetch();

if (!is_array($step1) || (string) ($step1['payment_status'] ?? 'not_submitted') !== 'paid') {
    jsonResponse(['success' => false, 'message' => 'Once your payment is verified you will be able to view & download the confirmation receipt.'], 403);
}

// Prefer the verified SBI Collect fields on the receipt, while keeping legacy keys for existing templates.
$step1['transaction_reference'] = $step1['sbi_reference_no'] ?: $step1['transaction_reference'];
$step1['payment_datetime'] = $step1['sbi_payment_date'] ?: $step1['payment_datetime'];


$basicStmt = $db->prepare('SELECT nationality, domicile, religion, category, pwd_status, disability_type, disability_percentage, qualifying_examination, pass_status, year_of_passing, institute_name_address FROM applicant_step2_basic WHERE applicant_id = :id LIMIT 1');
$basicStmt->execute(['id' => $applicant['id']]);
$basic = $basicStmt->fetch();

$addressStmt = $db->prepare('SELECT * FROM applicant_step2_address WHERE applicant_id = :id LIMIT 1');
$addressStmt->execute(['id' => $applicant['id']]);
$address = $addressStmt->fetch();

$coursesStmt = $db->prepare('SELECT course_group_1, course_group_2, exam_city, application_fee FROM applicant_step2_courses WHERE applicant_id = :id LIMIT 1');
$coursesStmt->execute(['id' => $applicant['id']]);
$courses = $coursesStmt->fetch();

if (is_array($courses)) {
    $courses['application_fee'] = isset($courses['application_fee']) && (int) $courses['application_fee'] > 0
        ? (int) $courses['application_fee']
        : calculateApplicationFee((string) ($courses['course_group_1'] ?? ''), (string) ($courses['course_group_2'] ?? ''));
}

$imagesStmt = $db->prepare('SELECT photo_path, signature_path FROM applicant_step2_images WHERE applicant_id = :id LIMIT 1');
$imagesStmt->execute(['id' => $applicant['id']]);
$images = $imagesStmt->fetch();

jsonResponse([
    'success' => true,
    'data' => [
        'step1' => $step1,
        'basic' => $basic,
        'address' => $address,
        'courses' => $courses,
        'images' => $images,
        'confirmation_datetime' => date('Y-m-d H:i:s'),
    ],
]);
