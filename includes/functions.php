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

function generateOtp(): string
{
    return (string) random_int(100000, 999999);
}

function generateApplicationIdFromId(int $id): string
{
    return '2510' . str_pad((string) $id, 7, '0', STR_PAD_LEFT);
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
