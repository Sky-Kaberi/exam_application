<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

$step1Stmt = $db->prepare('SELECT application_id, candidate_name, father_name, mother_name, date_of_birth, gender, identification_type, identification_no, mobile_no, email_id, payment_status, payment_mode, payment_amount, payment_datetime, transaction_reference, payment_demo_flag FROM applicants WHERE id = :id LIMIT 1');
$step1Stmt->execute(['id' => $applicant['id']]);
$step1 = $step1Stmt->fetch();

if (!is_array($step1) || (string) ($step1['payment_status'] ?? 'unpaid') !== 'paid') {
    jsonResponse(['success' => false, 'message' => 'Payment pending. Confirmation is available only after successful payment.'], 403);
}

$progress = getApplicantProgress($db, (int) $applicant['id']);
if ($progress['payment_final_submitted_at'] === null) {
    jsonResponse(['success' => false, 'message' => 'Please complete final submission after payment to view confirmation.'], 403);
}

$basicStmt = $db->prepare('SELECT nationality, domicile, religion, category, sub_category_details, pwd_status, disability_type, disability_percentage, qualifying_examination, pass_status, year_of_passing, institute_name_address FROM applicant_step2_basic WHERE applicant_id = :id LIMIT 1');
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
