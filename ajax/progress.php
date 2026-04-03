<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$applicant = requireApplicantLoginForJson();
$db = getDb();
$progress = getApplicantProgress($db, (int) $applicant['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = decodeJsonRequestBody();
    $lastTab = trim((string) ($payload['last_tab'] ?? ''));
    if (!in_array($lastTab, ['basic', 'address', 'courses', 'image'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid tab.'], 422);
    }

    $allowedTabs = ['basic'];
    if ((int) $progress['step2_basic_completed'] === 1) {
        $allowedTabs[] = 'address';
    }
    if ((int) $progress['step2_address_completed'] === 1) {
        $allowedTabs[] = 'courses';
    }
    if ((int) $progress['step2_courses_completed'] === 1) {
        $allowedTabs[] = 'image';
    }
    if (!in_array($lastTab, $allowedTabs, true)) {
        jsonResponse(['success' => false, 'message' => 'Please complete previous tab(s) first.'], 422);
    }

    upsertApplicantProgress($db, (int) $applicant['id'], ['last_tab' => $lastTab]);
    $progress = getApplicantProgress($db, (int) $applicant['id']);
}

jsonResponse([
    'success' => true,
    'progress' => $progress,
    'resume_tab' => detectResumeTab($progress),
]);
