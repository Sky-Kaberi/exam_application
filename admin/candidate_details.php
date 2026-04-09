<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

requireAdminLoginForPage('login.php');
$db = getDb();

$applicantId = (int) ($_GET['id'] ?? 0);
if ($applicantId <= 0) {
    http_response_code(404);
    echo 'Invalid applicant ID.';
    exit;
}

$stmt = $db->prepare('SELECT * FROM applicants WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $applicantId]);
$applicant = $stmt->fetch();
if (!$applicant) {
    http_response_code(404);
    echo 'Candidate not found.';
    exit;
}

function fetchSingleByApplicantId(PDO $db, string $table, int $applicantId): array
{
    $sql = sprintf('SELECT * FROM %s WHERE applicant_id = :applicant_id LIMIT 1', $table);
    $stmt = $db->prepare($sql);
    $stmt->execute(['applicant_id' => $applicantId]);
    return $stmt->fetch() ?: [];
}

$basic = fetchSingleByApplicantId($db, 'applicant_step2_basic', $applicantId);
$address = fetchSingleByApplicantId($db, 'applicant_step2_address', $applicantId);
$courses = fetchSingleByApplicantId($db, 'applicant_step2_courses', $applicantId);
$images = fetchSingleByApplicantId($db, 'applicant_step2_images', $applicantId);
$progress = fetchSingleByApplicantId($db, 'applicant_progress', $applicantId);

function renderField(string $label, $value): string
{
    $text = ($value === null || $value === '') ? '-' : (string) $value;
    return '<div class="col-12 col-md-6 mb-2"><div class="border rounded p-2 h-100"><small class="text-muted d-block">' .
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
        '</small><strong>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</strong></div></div>';
}

function sectionStart(string $title): string
{
    return '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><h2 class="h6 mb-0">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2></div><div class="card-body"><div class="row">';
}

function sectionEnd(): string
{
    return '</div></div></div>';
}

function resolveApplicationStatus(array $progress): string
{
    return !empty($progress['payment_final_submitted_at']) ? 'Submitted' : 'Draft';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Candidate Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">Candidate Details</span>
        <a href="dashboard.php" class="btn btn-sm btn-light">Back to Dashboard</a>
    </div>
</nav>

<div class="container-fluid pb-4">
    <?= sectionStart('Login / Application Info') ?>
    <?= renderField('Application ID', $applicant['application_id'] ?? '') ?>
    <?= renderField('Candidate Name', $applicant['candidate_name'] ?? '') ?>
    <?= renderField('Mobile No.', $applicant['mobile_no'] ?? '') ?>
    <?= renderField('Email', $applicant['email_id'] ?? '') ?>
    <?= renderField('Created Date', $applicant['created_at'] ?? '') ?>
    <?= renderField('Updated Date', $applicant['updated_at'] ?? '') ?>
    <?= sectionEnd() ?>

    <?= sectionStart('Basic Info') ?>
    <?= renderField('Father Name', $applicant['father_name'] ?? '') ?>
    <?= renderField('Mother Name', $applicant['mother_name'] ?? '') ?>
    <?= renderField('Date of Birth', $applicant['date_of_birth'] ?? '') ?>
    <?= renderField('Gender', $applicant['gender'] ?? '') ?>
    <?= renderField('Identification Type', $applicant['identification_type'] ?? '') ?>
    <?= renderField('Identification No.', $applicant['identification_no'] ?? '') ?>
    <?= renderField('Nationality', $basic['nationality'] ?? '') ?>
    <?= renderField('Domicile', $basic['domicile'] ?? '') ?>
    <?= renderField('Religion', $basic['religion'] ?? '') ?>
    <?= renderField('Category', $basic['category'] ?? '') ?>
    <?= renderField('PwD Status', $basic['pwd_status'] ?? '') ?>
    <?= renderField('Disability Type', $basic['disability_type'] ?? '') ?>
    <?= renderField('Disability Percentage', $basic['disability_percentage'] ?? '') ?>
    <?= renderField('Qualifying Examination', $basic['qualifying_examination'] ?? '') ?>
    <?= renderField('Pass Status', $basic['pass_status'] ?? '') ?>
    <?= renderField('Year of Passing', $basic['year_of_passing'] ?? '') ?>
    <?= renderField('Institute Name & Address', $basic['institute_name_address'] ?? '') ?>
    <?= sectionEnd() ?>

    <?= sectionStart('Correspondence Address') ?>
    <?= renderField('Premises', $address['corr_premises'] ?? '') ?>
    <?= renderField('Sub Locality', $address['corr_sub_locality'] ?? '') ?>
    <?= renderField('Locality', $address['corr_locality'] ?? '') ?>
    <?= renderField('District', $address['corr_district'] ?? '') ?>
    <?= renderField('State', $address['corr_state'] ?? '') ?>
    <?= renderField('Country', $address['corr_country'] ?? '') ?>
    <?= renderField('PIN Code', $address['corr_pin_code'] ?? '') ?>
    <?= sectionEnd() ?>

    <?= sectionStart('Permanent Address') ?>
    <?= renderField('Same as Correspondence', isset($address['same_as_correspondence']) ? ((int) $address['same_as_correspondence'] === 1 ? 'Yes' : 'No') : '') ?>
    <?= renderField('Premises', $address['perm_premises'] ?? '') ?>
    <?= renderField('Sub Locality', $address['perm_sub_locality'] ?? '') ?>
    <?= renderField('Locality', $address['perm_locality'] ?? '') ?>
    <?= renderField('District', $address['perm_district'] ?? '') ?>
    <?= renderField('State', $address['perm_state'] ?? '') ?>
    <?= renderField('Country', $address['perm_country'] ?? '') ?>
    <?= renderField('PIN Code', $address['perm_pin_code'] ?? '') ?>
    <?= sectionEnd() ?>

    <?= sectionStart('Course / Paper Selection') ?>
    <?= renderField('Course Group 1', $courses['course_group_1'] ?? '') ?>
    <?= renderField('Course Group 2', $courses['course_group_2'] ?? '') ?>
    <?= renderField('Exam City', $courses['exam_city'] ?? '') ?>
    <?= renderField('Application Fee', $courses['application_fee'] ?? '') ?>
    <?= sectionEnd() ?>

    <?= sectionStart('Uploaded Image Details') ?>
    <div class="col-12 col-md-6 mb-2">
        <div class="border rounded p-2 h-100">
            <small class="text-muted d-block">Photograph</small>
            <?php if (!empty($images['photo_path'])): ?>
                <img src="../public/<?= htmlspecialchars((string) $images['photo_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Photo" class="img-fluid rounded border" style="max-height:180px;">
                <div><small><?= htmlspecialchars((string) $images['photo_path'], ENT_QUOTES, 'UTF-8') ?></small></div>
            <?php else: ?>
                <strong>-</strong>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-md-6 mb-2">
        <div class="border rounded p-2 h-100">
            <small class="text-muted d-block">Signature</small>
            <?php if (!empty($images['signature_path'])): ?>
                <img src="../public/<?= htmlspecialchars((string) $images['signature_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Signature" class="img-fluid rounded border" style="max-height:180px;">
                <div><small><?= htmlspecialchars((string) $images['signature_path'], ENT_QUOTES, 'UTF-8') ?></small></div>
            <?php else: ?>
                <strong>-</strong>
            <?php endif; ?>
        </div>
    </div>
    <?= sectionEnd() ?>

    <?= sectionStart('Payment / Submission Details') ?>
    <?= renderField('Application Status', resolveApplicationStatus($progress)) ?>
    <?= renderField('Payment Status', $applicant['payment_status'] ?? '') ?>
    <?= renderField('Payment Mode', $applicant['payment_mode'] ?? '') ?>
    <?= renderField('Payment Amount', $applicant['payment_amount'] ?? '') ?>
    <?= renderField('Payment DateTime', $applicant['payment_datetime'] ?? '') ?>
    <?= renderField('Transaction Reference', $applicant['transaction_reference'] ?? '') ?>
    <?= renderField('Final Submitted At', $progress['final_submitted_at'] ?? '') ?>
    <?= renderField('Payment Final Submitted At', $progress['payment_final_submitted_at'] ?? '') ?>
    <?= sectionEnd() ?>
</div>
</body>
</html>
