<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Application - Step 1 Registration</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f7fb; margin:0; padding:24px; }
        .container { max-width: 960px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#184d9b; color:#fff; padding:20px 24px; }
        .content { padding:24px; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .field, .field-full { display:flex; flex-direction:column; gap:6px; }
        .field-full { grid-column:1 / -1; }
        input, select, button { padding:11px 12px; border:1px solid #cad5e2; border-radius:8px; font-size:14px; }
        .otp-row { display:flex; gap:8px; }
        .otp-row input { flex:1; }
        button { background:#184d9b; color:#fff; cursor:pointer; border:none; }
        button.secondary { background:#5b6b83; }
        button:disabled { opacity:.6; cursor:not-allowed; }
        .status { margin-top:8px; font-size:13px; }
        .status.success { color:#0a7a35; }
        .status.error { color:#b42318; }
        .app-box { background:#eef4ff; border:1px dashed #184d9b; padding:14px; border-radius:8px; margin-bottom:20px; display:none; }
        @media (max-width:768px){ .grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Online Registration - Step 1</h1>
        <p>Create your profile, verify mobile/email through OTP, and generate the application ID.</p>
    </div>
    <div class="content">
        <div class="app-box" id="appBox"></div>
        <form id="registrationForm">
            <div class="grid">
                <div class="field"><label>Candidate Name</label><input type="text" name="candidate_name" maxlength="46" pattern="[A-Za-z ]+" required><small>As registered in class 12 Examination. Max 46 letters/spaces.</small></div>
                <div class="field"><label>Father's Name</label><input type="text" name="father_name" maxlength="46" pattern="[A-Za-z ]+" required><small>As registered in class 12 Examination. Do not use salutations like Late, Mr., Ms., Mrs., Dr., Prof.</small></div>
                <div class="field"><label>Mother's Name</label><input type="text" name="mother_name" maxlength="46" pattern="[A-Za-z ]+" required><small>As registered in class 12 Examination. Do not use salutations like Late, Mr., Ms., Mrs., Dr., Prof.</small></div>
                <div class="field"><label>Date of Birth</label><input type="date" name="date_of_birth" required></div>
                <div class="field"><label>Gender</label><select name="gender" required><option value="">Select</option><option>Male</option><option>Female</option><option>Third Gender</option></select></div>
                <div class="field"><label>Identification Type</label><select name="identification_type" required><option value="">Select</option><option>School ID card</option><option>Voter ID</option><option>Passport</option><option>Ration Card with Photograph</option><option>Class 10 admit card with Photograph</option><option>Any other Valid Govt. Identity card With Photograph</option></select></div>
                <div class="field"><label id="identificationNoLabel">Identification Number</label><input type="text" name="identification_no" id="identificationNoInput" required></div>
                <div class="field"><label>Password</label><input type="password" name="password" required></div>
                <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
                <div class="field"><label>Enter Security PIN</label><input type="text" name="security_pin" required></div>
                <div class="field-full">
                    <label>Mobile Number</label>
                    <div class="otp-row">
                        <input type="text" name="mobile_no" maxlength="10" required>
                        <button type="button" id="sendMobileOtp">Send Mobile OTP</button>
                    </div>
                    <small>By providing mobile number, you agree to receive updates/notifications.</small>
                    <div class="otp-row" style="margin-top:8px;">
                        <input type="text" name="mobile_otp" maxlength="6" placeholder="Enter mobile OTP">
                        <button type="button" class="secondary" id="verifyMobileOtp">Verify Mobile OTP</button>
                    </div>
                    <div class="status" id="mobileStatus"></div>
                </div>
                <div class="field-full">
                    <label>Email ID</label>
                    <div class="otp-row">
                        <input type="email" name="email_id" required>
                        <button type="button" id="sendEmailOtp">Send Email OTP</button>
                    </div>
                    <small>By providing email ID, you agree to receive updates/notifications.</small>
                    <div class="otp-row" style="margin-top:8px;">
                        <input type="text" name="email_otp" maxlength="6" placeholder="Enter email OTP">
                        <button type="button" class="secondary" id="verifyEmailOtp">Verify Email OTP</button>
                    </div>
                    <div class="status" id="emailStatus"></div>
                </div>
                <div class="field-full">
                    <button type="submit" id="submitBtn">Complete Step 1 Registration</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script src="../assets/js/registration.js"></script>
</body>
</html>
