<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

$progress = getApplicantProgress($db, (int) $applicant['id']);
$step2Completed = (int) $progress['step2_basic_completed'] === 1
    && (int) $progress['step2_address_completed'] === 1
    && (int) $progress['step2_courses_completed'] === 1
    && (int) $progress['step2_images_completed'] === 1;

if (!$step2Completed) {
    jsonResponse(['success' => false, 'message' => 'Please complete all Step 2 tabs first.'], 422);
}

if ($progress['final_submitted_at'] === null) {
    jsonResponse(['success' => false, 'message' => 'Please complete Step 3 Preview first.'], 422);
}

$applicationStmt = $db->prepare(
    'SELECT application_id, candidate_name, payment_status, payment_mode, payment_amount, payment_datetime, transaction_reference, payment_demo_flag
     FROM applicants
     WHERE id = :id
     LIMIT 1'
);
$applicationStmt->execute(['id' => $applicant['id']]);
$application = $applicationStmt->fetch();

$coursesStmt = $db->prepare('SELECT course_group_1, course_group_2, application_fee FROM applicant_step2_courses WHERE applicant_id = :id LIMIT 1');
$coursesStmt->execute(['id' => $applicant['id']]);
$courses = $coursesStmt->fetch();

if (!is_array($courses)) {
    jsonResponse(['success' => false, 'message' => 'Course selection not found.'], 422);
}

$applicationFee = isset($courses['application_fee']) && (int) $courses['application_fee'] > 0
    ? (int) $courses['application_fee']
    : calculateApplicationFee((string) ($courses['course_group_1'] ?? ''), (string) ($courses['course_group_2'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = decodeJsonRequestBody();
    $action = (string) ($payload['action'] ?? 'pay');

    if ($action === 'final_submit') {
        if ((string) ($application['payment_status'] ?? 'unpaid') !== 'paid') {
            jsonResponse(['success' => false, 'message' => 'Please complete payment first.'], 422);
        }

        upsertApplicantProgress($db, (int) $applicant['id'], ['payment_final_submitted_at' => date('Y-m-d H:i:s')]);
        jsonResponse(['success' => true, 'message' => 'Final submission completed successfully.']);
    }

    if ((string) ($application['payment_status'] ?? 'unpaid') === 'paid') {
        jsonResponse(['success' => true, 'message' => 'Payment already completed.', 'data' => ['payment_status' => 'paid']]);
    }

    $declarationA = (bool) ($payload['declaration_a'] ?? false);
    $declarationB = (bool) ($payload['declaration_b'] ?? false);
    if (!$declarationA || !$declarationB) {
        jsonResponse(['success' => false, 'message' => 'Please accept both declarations before payment.'], 422);
    }

    $transactionReference = generateDemoTransactionReference();
    $paymentDatetime = date('Y-m-d H:i:s');

    $paymentStmt = $db->prepare(
        'UPDATE applicants
         SET payment_status = :payment_status,
             payment_mode = :payment_mode,
             payment_amount = :payment_amount,
             payment_datetime = :payment_datetime,
             transaction_reference = :transaction_reference,
             payment_demo_flag = :payment_demo_flag
         WHERE id = :id'
    );
    $paymentStmt->execute([
        'payment_status' => 'paid',
        'payment_mode' => 'demo',
        'payment_amount' => $applicationFee,
        'payment_datetime' => $paymentDatetime,
        'transaction_reference' => $transactionReference,
        'payment_demo_flag' => 1,
        'id' => $applicant['id'],
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Demo payment successful.',
        'data' => [
            'payment_status' => 'paid',
            'payment_amount' => $applicationFee,
            'payment_datetime' => $paymentDatetime,
            'transaction_reference' => $transactionReference,
        ],
    ]);
}

jsonResponse([
    'success' => true,
    'data' => [
        'application_id' => $application['application_id'] ?? '',
        'candidate_name' => $application['candidate_name'] ?? '',
        'payment_status' => $application['payment_status'] ?? 'unpaid',
        'payment_mode' => $application['payment_mode'] ?? null,
        'payment_amount' => $application['payment_amount'] ?? null,
        'payment_datetime' => $application['payment_datetime'] ?? null,
        'transaction_reference' => $application['transaction_reference'] ?? null,
        'payment_demo_flag' => $application['payment_demo_flag'] ?? 0,
        'payment_final_submitted_at' => $progress['payment_final_submitted_at'] ?? null,
        'payable_amount' => $applicationFee,
        'course_group_1' => $courses['course_group_1'] ?? '',
        'course_group_2' => $courses['course_group_2'] ?? '',
    ],
]);
