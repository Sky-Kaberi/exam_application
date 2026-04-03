<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT corr_premises, corr_sub_locality, corr_locality, corr_country, corr_state, corr_district, corr_pin_code, same_as_correspondence, perm_premises, perm_sub_locality, perm_locality, perm_country, perm_state, perm_district, perm_pin_code FROM applicant_step2_address WHERE applicant_id = :applicant_id LIMIT 1');
    $stmt->execute(['applicant_id' => $applicant['id']]);
    $row = $stmt->fetch();

    jsonResponse([
        'success' => true,
        'data' => $row ?: [
            'corr_premises' => '', 'corr_sub_locality' => '', 'corr_locality' => '', 'corr_country' => '', 'corr_state' => '', 'corr_district' => '', 'corr_pin_code' => '',
            'same_as_correspondence' => 0,
            'perm_premises' => '', 'perm_sub_locality' => '', 'perm_locality' => '', 'perm_country' => '', 'perm_state' => '', 'perm_district' => '', 'perm_pin_code' => '',
        ],
        'reference' => getAddressReferenceData(),
    ]);
}

$payload = decodeJsonRequestBody();
$errors = validateStep2AddressInput($payload);

if ($errors !== []) {
    jsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
}

$saveData = [
    'applicant_id' => $applicant['id'],
    'corr_premises' => trim((string) $payload['corr_premises']),
    'corr_sub_locality' => trim((string) ($payload['corr_sub_locality'] ?? '')),
    'corr_locality' => trim((string) $payload['corr_locality']),
    'corr_country' => trim((string) $payload['corr_country']),
    'corr_state' => trim((string) $payload['corr_state']),
    'corr_district' => trim((string) $payload['corr_district']),
    'corr_pin_code' => trim((string) $payload['corr_pin_code']),
    'same_as_correspondence' => !empty($payload['same_as_correspondence']) ? 1 : 0,
    'perm_premises' => trim((string) $payload['perm_premises']),
    'perm_sub_locality' => trim((string) ($payload['perm_sub_locality'] ?? '')),
    'perm_locality' => trim((string) $payload['perm_locality']),
    'perm_country' => trim((string) $payload['perm_country']),
    'perm_state' => trim((string) $payload['perm_state']),
    'perm_district' => trim((string) $payload['perm_district']),
    'perm_pin_code' => trim((string) $payload['perm_pin_code']),
];

$stmt = $db->prepare(
    'INSERT INTO applicant_step2_address (
        applicant_id, corr_premises, corr_sub_locality, corr_locality, corr_country, corr_state, corr_district, corr_pin_code,
        same_as_correspondence, perm_premises, perm_sub_locality, perm_locality, perm_country, perm_state, perm_district, perm_pin_code
    ) VALUES (
        :applicant_id, :corr_premises, :corr_sub_locality, :corr_locality, :corr_country, :corr_state, :corr_district, :corr_pin_code,
        :same_as_correspondence, :perm_premises, :perm_sub_locality, :perm_locality, :perm_country, :perm_state, :perm_district, :perm_pin_code
    ) ON DUPLICATE KEY UPDATE
        corr_premises = VALUES(corr_premises),
        corr_sub_locality = VALUES(corr_sub_locality),
        corr_locality = VALUES(corr_locality),
        corr_country = VALUES(corr_country),
        corr_state = VALUES(corr_state),
        corr_district = VALUES(corr_district),
        corr_pin_code = VALUES(corr_pin_code),
        same_as_correspondence = VALUES(same_as_correspondence),
        perm_premises = VALUES(perm_premises),
        perm_sub_locality = VALUES(perm_sub_locality),
        perm_locality = VALUES(perm_locality),
        perm_country = VALUES(perm_country),
        perm_state = VALUES(perm_state),
        perm_district = VALUES(perm_district),
        perm_pin_code = VALUES(perm_pin_code)'
);
$stmt->execute($saveData);

upsertApplicantProgress($db, (int) $applicant['id'], ['step2_address_completed' => 1, 'last_tab' => 'address']);

jsonResponse(['success' => true, 'message' => 'Address details saved successfully.']);
