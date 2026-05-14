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
    'SELECT application_id, candidate_name, email_id, payment_status, payment_mode, payment_amount, payment_datetime, transaction_reference, payment_receipt_file, payment_demo_flag, sbi_receipt_path, sbi_reference_no, sbi_payment_date, payment_submitted_at, payment_verified_at, payment_verified_by, payment_admin_note
     FROM applicants
     WHERE id = :id
     LIMIT 1'
);
$applicationStmt->execute(['id' => $applicant['id']]);
$application = $applicationStmt->fetch();
if (!is_array($application)) {
    jsonResponse(['success' => false, 'message' => 'Application not found.'], 404);
}

$coursesStmt = $db->prepare('SELECT course_group_1, course_group_2, application_fee FROM applicant_step2_courses WHERE applicant_id = :id LIMIT 1');
$coursesStmt->execute(['id' => $applicant['id']]);
$courses = $coursesStmt->fetch();

if (!is_array($courses)) {
    jsonResponse(['success' => false, 'message' => 'Course selection not found.'], 422);
}

$applicationFee = isset($courses['application_fee']) && (int) $courses['application_fee'] > 0
    ? (int) $courses['application_fee']
    : calculateApplicationFee((string) ($courses['course_group_1'] ?? ''), (string) ($courses['course_group_2'] ?? ''));
$paymentStatus = (string) ($application['payment_status'] ?? 'not_submitted');
$editablePaymentStatuses = ['not_submitted', 'rejected'];


function validatePaymentReceiptFile(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'SBI Collect Payment Receipt upload is required.';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return 'Payment receipt upload failed. Please retry.';
    }

    $maxSize = 2 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxSize) {
        return 'Payment receipt size must be up to 2MB.';
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($extension, $allowedExtensions, true)) {
        return 'Payment receipt must be a PDF, JPG, JPEG, or PNG file.';
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpName);
    } else {
        $mime = (string) mime_content_type($tmpName);
    }

    $allowedMimeTypes = [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
    ];

    if (!in_array($mime, $allowedMimeTypes[$extension] ?? [], true)) {
        return 'Payment receipt file type does not match the uploaded file.';
    }

    return null;
}

function paymentDateForResponse(?string $paymentDate, ?string $paymentDatetime): ?string
{
    if ($paymentDate !== null && trim($paymentDate) !== '') {
        return substr($paymentDate, 0, 10);
    }

    if ($paymentDatetime === null || trim($paymentDatetime) === '') {
        return null;
    }

    return substr($paymentDatetime, 0, 10);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isApplicantFinalSubmitted($db, (int) $applicant['id'])) {
        jsonResponse(['success' => false, 'message' => 'Application already submitted. No further changes are allowed.'], 422);
    }

    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    $isJson = stripos($contentType, 'application/json') !== false;
    $payload = $isJson ? decodeJsonRequestBody() : $_POST;
    $action = (string) ($payload['action'] ?? 'submit_payment');

    if ($action === 'final_submit') {
        if ((string) ($application['payment_status'] ?? 'not_submitted') !== 'paid') {
            jsonResponse(['success' => false, 'message' => 'Payment must be verified as paid before final submission.'], 422);
        }

        $submittedAt = date('Y-m-d H:i:s');
        upsertApplicantProgress($db, (int) $applicant['id'], ['payment_final_submitted_at' => $submittedAt]);

        $emailSent = sendFinalSubmissionConfirmationEmail(
            (string) ($application['email_id'] ?? ''),
            (string) ($application['application_id'] ?? ''),
            (string) ($application['candidate_name'] ?? '')
        );

        jsonResponse([
            'success' => true,
            'message' => $emailSent
                ? 'Final submission completed successfully. Confirmation email sent.'
                : 'Final submission completed successfully. Confirmation email could not be sent right now.',
        ]);
    }

    if ($action !== 'submit_payment') {
        jsonResponse(['success' => false, 'message' => 'Invalid payment action.'], 400);
    }

    if ($paymentStatus === 'paid') {
        jsonResponse([
            'success' => true,
            'message' => 'Payment is already verified as paid. Redirecting to confirmation page.',
            'data' => ['payment_status' => 'paid'],
        ]);
    }

    $hasSubmittedSbiDetails = trim((string) ($application['sbi_reference_no'] ?? $application['transaction_reference'] ?? '')) !== ''
        || trim((string) ($application['sbi_payment_date'] ?? $application['payment_datetime'] ?? '')) !== ''
        || trim((string) ($application['sbi_receipt_path'] ?? $application['payment_receipt_file'] ?? '')) !== ''
        || trim((string) ($application['payment_submitted_at'] ?? '')) !== '';
    $canEditPaymentDetails = in_array($paymentStatus, $editablePaymentStatuses, true);
    if ($hasSubmittedSbiDetails && !$canEditPaymentDetails) {
        jsonResponse([
            'success' => true,
            'message' => 'Once your payment is verified you will be able to view & download the confirmation receipt.',
            'data' => [
                'payment_status' => $paymentStatus,
                'payment_amount' => $application['payment_amount'] ?? $applicationFee,
                'payment_date' => paymentDateForResponse($application['sbi_payment_date'] ?? null, $application['payment_datetime'] ?? null),
                'transaction_reference' => $application['sbi_reference_no'] ?? $application['transaction_reference'] ?? null,
                'payment_receipt_file' => $application['sbi_receipt_path'] ?? $application['payment_receipt_file'] ?? null,
                'sbi_receipt_path' => $application['sbi_receipt_path'] ?? null,
                'payment_submitted_at' => $application['payment_submitted_at'] ?? null,
                'payment_admin_note' => $application['payment_admin_note'] ?? null,
                'resubmission_allowed' => false,
            ],
        ]);
    }

    $errors = [];
    $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
    $paymentDate = trim((string) ($payload['payment_date'] ?? ''));
    $declarationA = isset($payload['declaration_a']);
    $declarationB = isset($payload['declaration_b']);

    if ($transactionId === '') {
        $errors['transaction_id'] = 'SBI Collect Reference Number is required.';
    } elseif (mb_strlen($transactionId) > 80) {
        $errors['transaction_id'] = 'SBI Collect Reference Number must be 80 characters or fewer.';
    }

    $paymentDateObject = DateTime::createFromFormat('Y-m-d', $paymentDate);
    $paymentDateErrors = DateTime::getLastErrors();
    if ($paymentDate === '') {
        $errors['payment_date'] = 'Payment Date is required.';
    } elseif ($paymentDateObject === false || ($paymentDateErrors !== false && ((int) $paymentDateErrors['warning_count'] > 0 || (int) $paymentDateErrors['error_count'] > 0))) {
        $errors['payment_date'] = 'Enter a valid payment date.';
    } elseif ($paymentDate > date('Y-m-d')) {
        $errors['payment_date'] = 'Payment date cannot be in the future.';
    }

    if (!$declarationA || !$declarationB) {
        $errors['declaration'] = 'Please accept both declarations before submitting payment details.';
    }

    $receiptError = isset($_FILES['payment_receipt']) ? validatePaymentReceiptFile($_FILES['payment_receipt']) : 'SBI Collect Payment Receipt upload is required.';
    if ($receiptError !== null) {
        $errors['payment_receipt'] = $receiptError;
    }

    if ($errors !== []) {
        jsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
    }

    $safeApplicationId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $applicant['application_id']);
    $uploadDir = __DIR__ . '/../public/uploads/payment_receipts/' . $safeApplicationId;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        jsonResponse(['success' => false, 'message' => 'Unable to create payment receipt upload directory.'], 500);
    }

    $extension = strtolower(pathinfo((string) ($_FILES['payment_receipt']['name'] ?? ''), PATHINFO_EXTENSION));
    $receiptName = $safeApplicationId . '_' . (int) $applicant['id'] . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target = $uploadDir . '/' . $receiptName;

    if (!move_uploaded_file((string) $_FILES['payment_receipt']['tmp_name'], $target)) {
        jsonResponse(['success' => false, 'message' => 'Unable to save payment receipt. Please retry.'], 500);
    }

    $receiptPath = 'uploads/payment_receipts/' . $safeApplicationId . '/' . $receiptName;
    $paymentDatetime = $paymentDate . ' 00:00:00';

    // Candidate submissions only move to pending_verification; only admins can mark records paid.
    $paymentStmt = $db->prepare(
        'UPDATE applicants
         SET payment_status = :payment_status,
             payment_mode = :payment_mode,
             payment_amount = :payment_amount,
             payment_datetime = :payment_datetime,
             transaction_reference = :transaction_reference,
             payment_receipt_file = :payment_receipt_file,
             sbi_receipt_path = :sbi_receipt_path,
             sbi_reference_no = :sbi_reference_no,
             sbi_payment_date = :sbi_payment_date,
             payment_submitted_at = NOW(),
             payment_verified_at = NULL,
             payment_verified_by = NULL,
             payment_admin_note = NULL,
             payment_demo_flag = :payment_demo_flag
         WHERE id = :id'
    );
    $paymentStmt->execute([
        'payment_status' => 'pending_verification',
        'payment_mode' => 'SBI Collect',
        'payment_amount' => $applicationFee,
        'payment_datetime' => $paymentDatetime,
        'transaction_reference' => $transactionId,
        'payment_receipt_file' => $receiptPath,
        'sbi_receipt_path' => $receiptPath,
        'sbi_reference_no' => $transactionId,
        'sbi_payment_date' => $paymentDate,
        'payment_demo_flag' => 0,
        'id' => $applicant['id'],
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Payment details submitted successfully. Your payment will be verified by admin.',
        'data' => [
            'payment_status' => 'pending_verification',
            'payment_amount' => $applicationFee,
            'payment_date' => $paymentDate,
            'transaction_reference' => $transactionId,
            'sbi_reference_no' => $transactionId,
            'payment_receipt_file' => $receiptPath,
            'sbi_receipt_path' => $receiptPath,
        ],
    ]);
}

jsonResponse([
    'success' => true,
    'data' => [
        'application_id' => $application['application_id'] ?? '',
        'candidate_name' => $application['candidate_name'] ?? '',
        'payment_status' => $paymentStatus,
        'payment_mode' => $application['payment_mode'] ?? null,
        'payment_amount' => $application['payment_amount'] ?? null,
        'payment_datetime' => $application['payment_datetime'] ?? null,
        'payment_date' => paymentDateForResponse($application['sbi_payment_date'] ?? null, $application['payment_datetime'] ?? null),
        'transaction_reference' => $application['sbi_reference_no'] ?? $application['transaction_reference'] ?? null,
        'payment_receipt_file' => $application['sbi_receipt_path'] ?? $application['payment_receipt_file'] ?? null,
        'sbi_reference_no' => $application['sbi_reference_no'] ?? null,
        'sbi_receipt_path' => $application['sbi_receipt_path'] ?? null,
        'payment_submitted_at' => $application['payment_submitted_at'] ?? null,
        'payment_verified_at' => $application['payment_verified_at'] ?? null,
        'payment_admin_note' => $application['payment_admin_note'] ?? null,
        'payment_demo_flag' => $application['payment_demo_flag'] ?? 0,
        'payment_final_submitted_at' => $progress['payment_final_submitted_at'] ?? null,
        'resubmission_allowed' => in_array($paymentStatus, $editablePaymentStatuses, true),
        'payable_amount' => $applicationFee,
        'course_group_1' => $courses['course_group_1'] ?? '',
        'course_group_2' => $courses['course_group_2'] ?? '',
    ],
]);
