<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

bootstrapJsonErrorHandling();

$captchaChallenge = createLocalCaptchaChallenge();

jsonResponse([
    'success' => true,
    'message' => 'CAPTCHA refreshed.',
    'question' => $captchaChallenge['question'] ?? '',
]);
