<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT course_group_1, course_group_2, exam_city, application_fee FROM applicant_step2_courses WHERE applicant_id = :applicant_id LIMIT 1');
    $stmt->execute(['applicant_id' => $applicant['id']]);
    $row = $stmt->fetch();

    jsonResponse([
        'success' => true,
        'data' => $row ?: ['course_group_1' => '', 'course_group_2' => '', 'exam_city' => '', 'application_fee' => 0],
        'options' => getCourseOptions(),
    ]);
}

$payload = decodeJsonRequestBody();
$errors = validateStep2CoursesInput($payload);
if ($errors !== []) {
    jsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
}

$courseGroup1 = trim((string) ($payload['course_group_1'] ?? ''));
$courseGroup2 = trim((string) ($payload['course_group_2'] ?? ''));
$applicationFee = calculateApplicationFee($courseGroup1, $courseGroup2);

$stmt = $db->prepare(
    'INSERT INTO applicant_step2_courses (applicant_id, course_group_1, course_group_2, exam_city, application_fee)
     VALUES (:applicant_id, :course_group_1, :course_group_2, :exam_city, :application_fee)
     ON DUPLICATE KEY UPDATE
        course_group_1 = VALUES(course_group_1),
        course_group_2 = VALUES(course_group_2),
        exam_city = VALUES(exam_city),
        application_fee = VALUES(application_fee)'
);
$stmt->execute([
    'applicant_id' => $applicant['id'],
    'course_group_1' => $courseGroup1,
    'course_group_2' => $courseGroup2,
    'exam_city' => trim((string) $payload['exam_city']),
    'application_fee' => $applicationFee,
]);

upsertApplicantProgress($db, (int) $applicant['id'], ['step2_courses_completed' => 1, 'last_tab' => 'courses']);

jsonResponse(['success' => true, 'message' => 'Course selection saved successfully.', 'data' => ['application_fee' => $applicationFee]]);
