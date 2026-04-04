<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

$stmt = $db->prepare(
    'SELECT a.application_id, a.candidate_name, a.payment_status, a.payment_mode, a.payment_amount, a.payment_datetime, a.transaction_reference, a.payment_demo_flag,
            c.course_group_1, c.course_group_2, c.application_fee
     FROM applicants a
     LEFT JOIN applicant_step2_courses c ON c.applicant_id = a.id
     WHERE a.id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $applicant['id']]);
$row = $stmt->fetch();

if (!is_array($row) || (string) ($row['payment_status'] ?? 'unpaid') !== 'paid') {
    jsonResponse(['success' => false, 'message' => 'Payment pending. Confirmation is available only after successful payment.'], 403);
}

$payableAmount = isset($row['application_fee']) && (int) $row['application_fee'] > 0
    ? (int) $row['application_fee']
    : calculateApplicationFee((string) ($row['course_group_1'] ?? ''), (string) ($row['course_group_2'] ?? ''));

jsonResponse([
    'success' => true,
    'data' => [
        'application_id' => $row['application_id'] ?? '',
        'candidate_name' => $row['candidate_name'] ?? '',
        'course_group_1' => $row['course_group_1'] ?? '',
        'course_group_2' => $row['course_group_2'] ?? '',
        'payable_amount' => $payableAmount,
        'payment_status' => $row['payment_status'] ?? 'unpaid',
        'payment_mode' => $row['payment_mode'] ?? '',
        'payment_amount' => $row['payment_amount'] ?? null,
        'payment_datetime' => $row['payment_datetime'] ?? null,
        'transaction_reference' => $row['transaction_reference'] ?? '',
        'payment_demo_flag' => $row['payment_demo_flag'] ?? 0,
        'confirmation_datetime' => date('Y-m-d H:i:s'),
    ],
]);
