<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function bootstrapJsonErrorHandling(): void
{
    ini_set('display_errors', '0');

    set_exception_handler(static function (Throwable $exception): void {
        jsonResponse([
            'success' => false,
            'message' => 'Server error occurred. Please try again.',
        ], 500);
    });
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    $json = json_encode($payload);
    if ($json === false) {
        $json = '{"success":false,"message":"Unable to encode JSON response."}';
        http_response_code(500);
    }
    echo $json;
    exit;
}

function decodeJsonRequestBody(): array
{
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        return [];
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
    }

    return $payload;
}

function getClientIpAddress(): ?string
{
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['HTTP_CLIENT_IP'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $parts = array_map('trim', explode(',', $candidate));
        foreach ($parts as $part) {
            if (filter_var($part, FILTER_VALIDATE_IP) !== false) {
                return $part;
            }
        }
    }

    return null;
}

function generateOtp(): string
{
    return (string) random_int(100000, 999999);
}

function generateApplicationIdFromId(int $id): string
{
    return APPLICATION_PREFIX . str_pad((string) $id, 7, '0', STR_PAD_LEFT);
}

function loginApplicantSession(array $applicant): void
{
    ensureSessionStarted();
    session_regenerate_id(true);
    $_SESSION['applicant_auth'] = [
        'id' => (int) $applicant['id'],
        'application_id' => (string) $applicant['application_id'],
        'candidate_name' => (string) $applicant['candidate_name'],
    ];
}

function logoutApplicantSession(): void
{
    ensureSessionStarted();
    unset($_SESSION['applicant_auth']);
}

function getLoggedInApplicantSession(): ?array
{
    ensureSessionStarted();
    $auth = $_SESSION['applicant_auth'] ?? null;

    if (!is_array($auth) || !isset($auth['id'], $auth['application_id'])) {
        return null;
    }

    return $auth;
}

function requireApplicantLoginForPage(string $redirectPath = 'login.php'): array
{
    $applicant = getLoggedInApplicantSession();
    if ($applicant !== null) {
        return $applicant;
    }

    header('Location: ' . $redirectPath);
    exit;
}

function requireApplicantLoginForJson(): array
{
    $applicant = getLoggedInApplicantSession();
    if ($applicant !== null) {
        return $applicant;
    }

    jsonResponse(['success' => false, 'message' => 'Login required.'], 401);
}

function getCategoryOptionsByDomicile(string $domicile): array
{
    if ($domicile === 'West Bengal') {
        return ['General', 'SC', 'ST', 'OBC-A', 'OBC-B', 'General-EWS'];
    }

    if ($domicile === 'Others') {
        return ['General', 'SC', 'ST', 'OBC'];
    }

    return [];
}

function validateStep2BasicInput(array $data): array
{
    $errors = [];
    $nationality = trim((string) ($data['nationality'] ?? 'Indian'));
    $domicile = trim((string) ($data['domicile'] ?? ''));
    $religion = trim((string) ($data['religion'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $pwdStatus = trim((string) ($data['pwd_status'] ?? 'No'));
    $disabilityType = trim((string) ($data['disability_type'] ?? ''));
    $disabilityPercentage = trim((string) ($data['disability_percentage'] ?? ''));
    $qualifyingExam = trim((string) ($data['qualifying_examination'] ?? ''));
    $passStatus = trim((string) ($data['pass_status'] ?? ''));
    $yearOfPassing = trim((string) ($data['year_of_passing'] ?? ''));
    $instituteAddress = trim((string) ($data['institute_name_address'] ?? ''));

    if ($nationality !== 'Indian') {
        $errors['nationality'] = 'Only Indian nationality is allowed.';
    }

    if (!in_array($domicile, ['West Bengal', 'Others'], true)) {
        $errors['domicile'] = 'Please select a valid domicile.';
    }

    $allowedReligions = ['Hinduism', 'Islam', 'Christianity', 'Buddhism', 'Sikhism', 'Jainism', 'Other'];
    if (!in_array($religion, $allowedReligions, true)) {
        $errors['religion'] = 'Please select a valid religion.';
    }

    $allowedCategories = getCategoryOptionsByDomicile($domicile);
    if (!in_array($category, $allowedCategories, true)) {
        $errors['category'] = 'Please select a valid category for the selected domicile.';
    }

    if (!in_array($pwdStatus, ['Yes', 'No'], true)) {
        $errors['pwd_status'] = 'Please select PwD status.';
    }

    if ($pwdStatus === 'Yes') {
        if (!in_array($disabilityType, ['Locomotor disability in lower limb', 'Others'], true)) {
            $errors['disability_type'] = 'Please select type of disability.';
        }
        if ($disabilityPercentage === '' || !is_numeric($disabilityPercentage)) {
            $errors['disability_percentage'] = 'Enter a valid disability percentage.';
        }
    }

    if ($qualifyingExam === '') {
        $errors['qualifying_examination'] = 'Qualifying examination is required.';
    }

    if ($passStatus !== 'Passed') {
        $errors['pass_status'] = 'Pass Status must be Passed.';
    }

    if ($yearOfPassing === '') {
        $errors['year_of_passing'] = 'Year of Passing is required.';
    } elseif (!preg_match('/^\d{4}$/', $yearOfPassing)) {
        $errors['year_of_passing'] = 'Enter a valid 4-digit year.';
    }

    if ($instituteAddress === '') {
        $errors['institute_name_address'] = 'Institute Name and address is required.';
    } elseif (mb_strlen($instituteAddress) < 5) {
        $errors['institute_name_address'] = 'Please enter the full institute name and address.';
    }

    return $errors;
}

function validateRegistrationInput(array $data): array
{
    $errors = [];
    $namePattern = '/^[A-Za-z ]+$/';
    $salutationPattern = '/\b(?:late|mr|ms|mrs|dr|prof)\b\.?/i';
    $allowedGenders = ['Male', 'Female', 'Third Gender'];
    $allowedIdentificationTypes = [
        'School ID card',
        'Voter ID',
        'Passport',
        'Ration Card with Photograph',
        'Class 10 admit card with Photograph',
        'Any other Valid Govt. Identity card With Photograph',
    ];

    $candidateName = trim((string) ($data['candidate_name'] ?? ''));
    if ($candidateName === '') {
        $errors['candidate_name'] = 'Candidate name is required.';
    } elseif (strlen($candidateName) > 46) {
        $errors['candidate_name'] = 'Candidate name must be maximum 46 characters.';
    } elseif (!preg_match($namePattern, $candidateName)) {
        $errors['candidate_name'] = 'Candidate name can only contain letters and spaces.';
    }

    $fatherName = trim((string) ($data['father_name'] ?? ''));
    if ($fatherName === '') {
        $errors['father_name'] = 'Father name is required.';
    } elseif (strlen($fatherName) > 46) {
        $errors['father_name'] = 'Father name must be maximum 46 characters.';
    } elseif (!preg_match($namePattern, $fatherName)) {
        $errors['father_name'] = 'Father name can only contain letters and spaces.';
    } elseif (preg_match($salutationPattern, $fatherName)) {
        $errors['father_name'] = 'Father name must not include salutations such as Late, Mr., Ms., Mrs., Dr., Prof.';
    }

    $motherName = trim((string) ($data['mother_name'] ?? ''));
    if ($motherName === '') {
        $errors['mother_name'] = 'Mother name is required.';
    } elseif (strlen($motherName) > 46) {
        $errors['mother_name'] = 'Mother name must be maximum 46 characters.';
    } elseif (!preg_match($namePattern, $motherName)) {
        $errors['mother_name'] = 'Mother name can only contain letters and spaces.';
    } elseif (preg_match($salutationPattern, $motherName)) {
        $errors['mother_name'] = 'Mother name must not include salutations such as Late, Mr., Ms., Mrs., Dr., Prof.';
    }

    if (!preg_match('/^[0-9]{10}$/', $data['mobile_no'] ?? '')) {
        $errors['mobile_no'] = 'Enter a valid 10 digit mobile number.';
    }

    if (!filter_var($data['email_id'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors['email_id'] = 'Enter a valid email address.';
    }

    if (!in_array($data['gender'] ?? '', $allowedGenders, true)) {
        $errors['gender'] = 'Select a valid gender.';
    }

    if (trim((string) ($data['date_of_birth'] ?? '')) === '') {
        $errors['date_of_birth'] = 'Date of birth is required.';
    }

    $password = (string) ($data['password'] ?? '');
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*\-]).{8,13}$/', $password)) {
        $errors['password'] = 'Password must be 8-13 chars and include uppercase, lowercase, number and special character.';
    }

    if ($password !== (string) ($data['confirm_password'] ?? '')) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (trim($data['identification_no'] ?? '') === '') {
        $errors['identification_no'] = 'Identification number is required.';
    }

    if (!in_array($data['identification_type'] ?? '', $allowedIdentificationTypes, true)) {
        $errors['identification_type'] = 'Identification type is invalid.';
    }

    if (trim((string) ($data['captcha_answer'] ?? '')) === '') {
        $errors['captcha_answer'] = 'CAPTCHA answer is required.';
    }

    return $errors;
}

function createLocalCaptchaChallenge(): array
{
    $left = random_int(CAPTCHA_MIN_VALUE, CAPTCHA_MAX_VALUE);
    $right = random_int(CAPTCHA_MIN_VALUE, CAPTCHA_MAX_VALUE);
    $_SESSION['captcha_answer'] = (string) ($left + $right);

    return [
        'question' => "What is {$left} + {$right}?",
    ];
}

function verifyLocalCaptchaAnswer(string $answer): array
{
    $expected = (string) ($_SESSION['captcha_answer'] ?? '');
    if ($expected === '') {
        return [
            'success' => false,
            'message' => 'CAPTCHA session expired. Please refresh and try again.',
        ];
    }

    if (trim($answer) !== $expected) {
        return [
            'success' => false,
            'message' => 'CAPTCHA answer is incorrect.',
        ];
    }

    unset($_SESSION['captcha_answer']);

    return ['success' => true, 'message' => 'CAPTCHA verified.'];
}

function createOtpRecord(PDO $db, string $channel, string $recipient): array
{
    try {
        $stmt = $db->prepare('SELECT * FROM otp_verifications WHERE channel = :channel AND recipient = :recipient ORDER BY id DESC LIMIT 1');
        $stmt->execute(['channel' => $channel, 'recipient' => $recipient]);
        $latest = $stmt->fetch();

        if ($latest && strtotime((string) $latest['created_at']) > time() - OTP_RESEND_LIMIT_SECONDS) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting another OTP.',
            ];
        }

        $otp = generateOtp();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $stmt = $db->prepare('INSERT INTO otp_verifications (channel, recipient, otp_code, expires_at) VALUES (:channel, :recipient, :otp_code, :expires_at)');
        $stmt->execute([
            'channel' => $channel,
            'recipient' => $recipient,
            'otp_code' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => $expiresAt,
        ]);

        $logLine = sprintf("[%s] %s OTP for %s is %s\n", date('c'), strtoupper($channel), $recipient, $otp);
        @file_put_contents(__DIR__ . '/../logs/otp.log', $logLine, FILE_APPEND);

        if ($channel === 'email') {
            $emailSent = sendEmailOtp($recipient, $otp);

            return [
                'success' => $emailSent,
                'message' => $emailSent
                    ? 'OTP sent to email successfully.'
                    : 'Unable to send OTP email right now. Please retry.',
            ];
        }

        return [
            'success' => true,
            'message' => 'OTP generated successfully.',
            'display_otp' => $otp,
        ];
    } catch (Throwable $exception) {
        ensureSessionStarted();

        $otp = generateOtp();
        $_SESSION['otp_fallback'][$channel][$recipient] = [
            'otp_code' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => time() + (OTP_EXPIRY_MINUTES * 60),
            'verified_at' => null,
        ];

        $message = $channel === 'email'
            ? 'OTP generated in fallback mode. Use the OTP shown below to verify email.'
            : 'OTP generated successfully.';

        return [
            'success' => true,
            'message' => $message,
            'display_otp' => $otp,
        ];
    }
}

function sendEmailOtp(string $recipient, string $otp): bool
{
    include_once("../class/class.phpmailer.php");

    $subject = 'Your Email OTP for Exam Application';
    $content = "Your OTP for exam application registration is: {$otp}. It is valid for " . OTP_EXPIRY_MINUTES . " minutes.";

    try {
        $mail = new PHPMailer();

        // SMTP SETTINGS (IMPORTANT)
        $mail_1 = new PHPMailer();

		$mail_1->IsSMTP();
		
		// REQUIRED SETTINGS
		//$mail_1->Host = "localhost"; // try this first (IIS/Plesk often works)
		//$mail_1->SMTPAuth = false;   // try without auth first
		
		$mail_1->From = "admin@wbjeeb.in";
		$mail_1->FromName = "WBJEEB";
		$mail_1->AddAddress($recipient);
		
		$mail_1->Subject = $subject;
		$mail_1->IsHTML(false);
		$mail_1->Body = $content;
		
		if (!$mail_1->Send()) {
			error_log("Mailer Error: " . $mail_1->ErrorInfo);
			return false;
		}
		
		return true;
		
    } catch (Exception $e) {
        error_log("Mailer Exception: " . $e->getMessage());
        return false;
    }
}
function verifyOtpRecord(PDO $db, string $channel, string $recipient, string $otp): bool
{
    try {
        $stmt = $db->prepare('SELECT * FROM otp_verifications WHERE channel = :channel AND recipient = :recipient AND verified_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute(['channel' => $channel, 'recipient' => $recipient]);
        $record = $stmt->fetch();

        if (!$record) {
            return false;
        }

        if (strtotime((string) $record['expires_at']) < time()) {
            return false;
        }

        if (!password_verify($otp, (string) $record['otp_code'])) {
            return false;
        }

        $update = $db->prepare('UPDATE otp_verifications SET verified_at = NOW() WHERE id = :id');
        $update->execute(['id' => $record['id']]);

        return true;
    } catch (Throwable $exception) {
        ensureSessionStarted();
        $record = $_SESSION['otp_fallback'][$channel][$recipient] ?? null;
        if (!is_array($record)) {
            return false;
        }

        if (($record['expires_at'] ?? 0) < time()) {
            return false;
        }

        if (!password_verify($otp, (string) ($record['otp_code'] ?? ''))) {
            return false;
        }

        $_SESSION['otp_fallback'][$channel][$recipient]['verified_at'] = time();

        return true;
    }
}
