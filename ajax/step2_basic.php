<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

if (isApplicantFinalSubmitted($db, (int) $applicant['id']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Application already submitted. No further changes are allowed.'], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT nationality, domicile, religion, category, sub_category_details, pwd_status, disability_type, disability_percentage, qualifying_examination, pass_status, year_of_passing, institute_name_address FROM applicant_step2_basic WHERE applicant_id = :applicant_id LIMIT 1');
    $stmt->execute(['applicant_id' => $applicant['id']]);
    $row = $stmt->fetch();

    jsonResponse([
        'success' => true,
        'data' => $row ?: [
            'nationality' => 'Indian',
            'domicile' => '',
            'religion' => '',
            'category' => '',
            'sub_category_details' => '',
            'pwd_status' => 'No',
            'disability_type' => '',
            'disability_percentage' => '',
            'qualifying_examination' => '',
            'pass_status' => 'Passed',
            'year_of_passing' => '',
            'institute_name_address' => '',
        ],
    ]);
}

$payload = decodeJsonRequestBody();
$errors = validateStep2BasicInput($payload);

if ($errors !== []) {
    jsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
}

$saveData = [
    'applicant_id' => $applicant['id'],
    'nationality' => 'Indian',
    'domicile' => $payload['domicile'],
    'religion' => $payload['religion'],
    'category' => $payload['category'],
    'sub_category_details' => trim((string) ($payload['sub_category_details'] ?? '')),
    'pwd_status' => $payload['pwd_status'],
    'disability_type' => $payload['pwd_status'] === 'Yes' ? $payload['disability_type'] : null,
    'disability_percentage' => $payload['pwd_status'] === 'Yes' ? (float) $payload['disability_percentage'] : null,
    'qualifying_examination' => trim((string) $payload['qualifying_examination']),
    'pass_status' => 'Passed',
    'year_of_passing' => trim((string) $payload['year_of_passing']),
    'institute_name_address' => trim((string) $payload['institute_name_address']),
];

$stmt = $db->prepare(
    'INSERT INTO applicant_step2_basic (
        applicant_id, nationality, domicile, religion, category, sub_category_details, pwd_status,
        disability_type, disability_percentage, qualifying_examination, pass_status, year_of_passing,
        institute_name_address
    ) VALUES (
        :applicant_id, :nationality, :domicile, :religion, :category, :sub_category_details, :pwd_status,
        :disability_type, :disability_percentage, :qualifying_examination, :pass_status, :year_of_passing,
        :institute_name_address
    ) ON DUPLICATE KEY UPDATE
        nationality = VALUES(nationality),
        domicile = VALUES(domicile),
        religion = VALUES(religion),
        category = VALUES(category),
        sub_category_details = VALUES(sub_category_details),
        pwd_status = VALUES(pwd_status),
        disability_type = VALUES(disability_type),
        disability_percentage = VALUES(disability_percentage),
        qualifying_examination = VALUES(qualifying_examination),
        pass_status = VALUES(pass_status),
        year_of_passing = VALUES(year_of_passing),
        institute_name_address = VALUES(institute_name_address)'
);
$stmt->execute($saveData);
upsertApplicantProgress($db, (int) $applicant['id'], ['step2_basic_completed' => 1, 'last_tab' => 'basic']);

jsonResponse(['success' => true, 'message' => 'Basic Info saved successfully.']);
