<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';


const BASE_APPLICATION_FEE = 3000;

function calculateApplicationFee(string $group1, string $group2): int
{
    $hasGroup1 = trim($group1) !== '';
    $hasGroup2 = trim($group2) !== '';

    if ($hasGroup1 && $hasGroup2) {
        return BASE_APPLICATION_FEE * 2;
    }

    if ($hasGroup1 || $hasGroup2) {
        return BASE_APPLICATION_FEE;
    }

    return 0;
}

function generateDemoTransactionReference(): string
{
    return 'DEMO' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
}

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
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
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
    $numericSegmentLength = APPLICATION_ID_TOTAL_LENGTH - strlen(APPLICATION_PREFIX);
    return APPLICATION_PREFIX . str_pad((string) $id, $numericSegmentLength, '0', STR_PAD_LEFT);
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


function loginAdminSession(array $admin): void
{
    ensureSessionStarted();
    session_regenerate_id(true);
    $_SESSION['admin_auth'] = [
        'id' => (int) $admin['id'],
        'username' => (string) $admin['username'],
        'full_name' => (string) ($admin['full_name'] ?? ''),
    ];
}

function logoutAdminSession(): void
{
    ensureSessionStarted();
    unset($_SESSION['admin_auth']);
}

function getLoggedInAdminSession(): ?array
{
    ensureSessionStarted();
    $auth = $_SESSION['admin_auth'] ?? null;

    if (!is_array($auth) || !isset($auth['id'], $auth['username'])) {
        return null;
    }

    return $auth;
}

function requireAdminLoginForPage(string $redirectPath = 'login.php'): array
{
    $admin = getLoggedInAdminSession();
    if ($admin !== null) {
        return $admin;
    }

    header('Location: ' . $redirectPath);
    exit;
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

function getAddressReferenceData(): array
{
    return [
        'countries' => ['India'],
        'states_by_country' => [
            'India' => ['West Bengal'],
        ],
        'districts_by_state' => [
            'West Bengal' => ['Kolkata', 'Howrah', 'North 24 Parganas', 'South 24 Parganas'],
        ],
    ];
}

function getCourseOptions(): array
{
    return [
        'group_1' => ['DHS', 'FPM', 'MPhil CP', 'MAN', 'MPH', 'MPhil PSW', 'MPhil RMTS', 'MPT', 'MOT', 'MPO', 'MSLP', 'M.Sc. PH-HP', 'M.Sc. MB', 'M.Sc. MM'],
        'group_2' => ['DHPE', 'Dip Diet', 'FCCT', 'FRMTS', 'M. Sc CCS', 'M. Sc OTS', 'M. Sc PS', 'MHA', 'MSc MBT', 'MSc MLT', 'PGDDRM', 'M. Sc PH-MCH'],
        'exam_cities' => ['Kolkata - Salt Lake / New Town'],
    ];
}

function validateIndianPinCode(string $pinCode): bool
{
    return (bool) preg_match('/^[1-9][0-9]{5}$/', $pinCode);
}

function validateStep2AddressInput(array $data): array
{
    $errors = [];
    $reference = getAddressReferenceData();
    $allowedCountries = $reference['countries'];
    $allowedStatesByCountry = $reference['states_by_country'];
    $allowedDistrictsByState = $reference['districts_by_state'];

    foreach (['corr', 'perm'] as $prefix) {
        $premises = trim((string) ($data[$prefix . '_premises'] ?? ''));
        $locality = trim((string) ($data[$prefix . '_locality'] ?? ''));
        $country = trim((string) ($data[$prefix . '_country'] ?? ''));
        $state = trim((string) ($data[$prefix . '_state'] ?? ''));
        $district = trim((string) ($data[$prefix . '_district'] ?? ''));
        $pinCode = trim((string) ($data[$prefix . '_pin_code'] ?? ''));

        if ($premises === '') {
            $errors[$prefix . '_premises'] = 'Premises No./Village Name is required.';
        }
        if ($locality === '') {
            $errors[$prefix . '_locality'] = 'Locality/City/Town/Village/Post Office is required.';
        }
        if (!in_array($country, $allowedCountries, true)) {
            $errors[$prefix . '_country'] = 'Please select a valid country.';
        }
        if (!in_array($state, $allowedStatesByCountry[$country] ?? [], true)) {
            $errors[$prefix . '_state'] = 'Please select a valid state.';
        }
        if (!in_array($district, $allowedDistrictsByState[$state] ?? [], true)) {
            $errors[$prefix . '_district'] = 'Please select a valid district.';
        }
        if (!validateIndianPinCode($pinCode)) {
            $errors[$prefix . '_pin_code'] = 'PIN Code must be a valid 6-digit Indian PIN.';
        }
    }

    return $errors;
}

function validateStep2CoursesInput(array $data): array
{
    $errors = [];
    $courseOptions = getCourseOptions();
    $group1 = trim((string) ($data['course_group_1'] ?? ''));
    $group2 = trim((string) ($data['course_group_2'] ?? ''));
    $examCity = trim((string) ($data['exam_city'] ?? ''));

    if ($group1 !== '' && !in_array($group1, $courseOptions['group_1'], true)) {
        $errors['course_group_1'] = 'Please select a valid Group-1 course.';
    }
    if ($group2 !== '' && !in_array($group2, $courseOptions['group_2'], true)) {
        $errors['course_group_2'] = 'Please select a valid Group-2 course.';
    }

    if ($group1 === '' && $group2 === '') {
        $errors['course_group_1'] = 'Select one course from Group-1 or Group-2.';
        $errors['course_group_2'] = 'Select one course from Group-1 or Group-2.';
    }

    if (!in_array($examCity, $courseOptions['exam_cities'], true)) {
        $errors['exam_city'] = 'Please select a valid exam city.';
    }

    return $errors;
}

function upsertApplicantProgress(PDO $db, int $applicantId, array $fields): void
{
    $allowedFields = ['step2_basic_completed', 'step2_address_completed', 'step2_courses_completed', 'step2_images_completed', 'last_tab', 'final_submitted_at', 'payment_final_submitted_at'];
    $setParts = [];
    $params = ['applicant_id' => $applicantId];

    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowedFields, true)) {
            continue;
        }
        $setParts[] = $key . ' = VALUES(' . $key . ')';
        $params[$key] = $value;
    }

    if ($setParts === []) {
        return;
    }

    $columns = array_keys($params);
    $sql = 'INSERT INTO applicant_progress (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')'
        . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $setParts);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

function getApplicantProgress(PDO $db, int $applicantId): array
{
    $stmt = $db->prepare('SELECT step2_basic_completed, step2_address_completed, step2_courses_completed, step2_images_completed, final_submitted_at, payment_final_submitted_at, last_tab FROM applicant_progress WHERE applicant_id = :applicant_id LIMIT 1');
    $stmt->execute(['applicant_id' => $applicantId]);
    $progress = $stmt->fetch();

    return $progress ?: [
        'step2_basic_completed' => 0,
        'step2_address_completed' => 0,
        'step2_courses_completed' => 0,
        'step2_images_completed' => 0,
        'final_submitted_at' => null,
        'payment_final_submitted_at' => null,
        'last_tab' => 'basic',
    ];
}

function detectResumeTab(array $progress): string
{
    if ($progress['step2_basic_completed'] != 1) {
        return 'basic';
    }
    if ($progress['step2_address_completed'] != 1) {
        return 'address';
    }
    if ($progress['step2_courses_completed'] != 1) {
        return 'courses';
    }
    if ($progress['step2_images_completed'] != 1) {
        return 'image';
    }

    return 'preview';
}

function isApplicationProcessCompleted(PDO $db, int $applicantId): bool
{
    $paymentStatusStmt = $db->prepare('SELECT payment_status FROM applicants WHERE id = :id LIMIT 1');
    $paymentStatusStmt->execute(['id' => $applicantId]);
    $paymentStatus = (string) ($paymentStatusStmt->fetchColumn() ?: 'unpaid');

    if ($paymentStatus !== 'paid') {
        return false;
    }

    $progress = getApplicantProgress($db, $applicantId);
    return $progress['payment_final_submitted_at'] !== null;
}

function resolveApplicantPostLoginRedirect(PDO $db, int $applicantId): string
{
    if (isApplicationProcessCompleted($db, $applicantId)) {
        return '../public/step5_confirmation.php';
    }

    $progress = getApplicantProgress($db, $applicantId);
    $resumeTab = detectResumeTab($progress);

    return $resumeTab === 'preview'
        ? '../public/step3_preview.php'
        : '../public/step2.php?tab=' . urlencode($resumeTab);
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

function sendApplicationSubmissionEmail(string $recipient, string $applicationId, string $candidateName): bool
{
    include_once("../class/class.phpmailer.php");

    $subject = 'Application Submitted Successfully';
    $content = "Dear {$candidateName},\n\n"
        . "Your application has been submitted successfully.\n"
        . "Application Number: {$applicationId}\n\n"
        . "Please keep this application number for future reference.\n\n"
        . "Regards,\nWBJEEB";

    try {
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->From = "admin@wbjeeb.in";
        $mail->FromName = "WBJEEB";
        $mail->AddAddress($recipient);
        $mail->Subject = $subject;
        $mail->IsHTML(false);
        $mail->Body = $content;

        if (!$mail->Send()) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
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
