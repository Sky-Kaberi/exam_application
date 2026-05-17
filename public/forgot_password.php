<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicationId = trim((string) ($_GET['application_id'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>

        * { box-sizing: border-box; }
        html, body { max-width: 100%; overflow-x: hidden; }
        img { max-width: 100%; height: auto; }
        input, select, textarea, button { max-width: 100%; }
        .container, .card, .page-wrap { width: 100%; }
        .header > div, .site-brand > div { min-width: 0; }
        .header > div:last-child { display:flex; gap:8px; flex-wrap:wrap; }
        h1, h2, h3, p, small, label, a, button { overflow-wrap: anywhere; }
        body { font-family: Arial, sans-serif; background:#fff8ec; margin:0; padding:24px; }
        .card { max-width:540px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:24px; }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
        input, button { padding:11px 12px; border-radius:8px; border:1px solid #cad5e2; font-size:14px; }
        button { background:#FFA500; color:#1f2937; border:none; cursor:pointer; width:100%; }
        .status { font-size:13px; min-height:18px; margin-bottom:12px; }
        .success { color:#0a7a35; }
        .error { color:#b42318; }
        .link { margin-top:12px; display:block; text-align:center; }

        .site-brand { display:grid; grid-template-columns:56px minmax(0, 1fr) 56px; align-items:center; justify-content:center; gap:12px; text-align:center; }
        .site-brand img { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:50%; padding:4px; }
        .site-brand-title { font-weight:700; font-size:18px; line-height:1.25; }
        .site-brand-exam { font-weight:700; font-size:15px; margin-top:2px; }

        @media (max-width:600px){
            body { padding:10px; }
            .content, .body { padding:14px; }
            .header { align-items:stretch; flex-direction:column; }
            .header > div:last-child { width:100%; }
            .header a, .header-login-link { display:inline-flex; justify-content:center; text-align:center; white-space:normal; }
            .site-brand { grid-template-columns:36px minmax(0, 1fr) 36px; gap:6px; }
            .site-brand img { width:36px; height:36px; padding:3px; }
            .site-brand-title { font-size:clamp(12px, 3.8vw, 15px); line-height:1.15; overflow-wrap:normal; }
            .site-brand-exam { font-size:12px; }
            h1 { font-size:22px; }
            h2 { font-size:20px; }
            .otp-row, .actions { flex-direction:column; }
            .tabs { display:grid; grid-template-columns:1fr; }
            .tab-btn { width:100%; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="site-brand">
                <img src="https://upload.wikimedia.org/wikipedia/en/thumb/4/46/West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg/250px-West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg.png" alt="West Bengal Joint Entrance Examinations Board Logo">
                <div>
                    <div class="site-brand-title">West Bengal Joint Entrance Examinations Board</div>
                    <div class="site-brand-exam">JEMPAS(PG) - 2025</div>
                </div>
                <img src="https://upload.wikimedia.org/wikipedia/en/thumb/4/46/West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg/250px-West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg.png" alt="West Bengal Joint Entrance Examinations Board Logo">
            </div>
    <h1>Forgot Password</h1>
    <p>Enter your Application ID, registered email ID and mobile number. A new password will be sent by SMS and email.</p>
    <form id="forgotPasswordForm">
        <div class="field">
            <label>Application ID</label>
            <input type="text" name="application_id" value="<?= htmlspecialchars($applicationId, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="field">
            <label>Email ID</label>
            <input type="email" name="email_id" required>
        </div>
        <div class="field">
            <label>Mobile Number</label>
            <input type="text" name="mobile_no" maxlength="10" required>
        </div>
        <div class="status" id="status"></div>
        <button type="submit">Send Password</button>
    </form>
    <a class="link" href="login.php">Back to Login</a>
</div>
<script>
const form = document.getElementById('forgotPasswordForm');
const statusNode = document.getElementById('status');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    statusNode.textContent = '';
    statusNode.className = 'status';

    try {
        const response = await fetch('../ajax/forgot_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
        });
        const data = await response.json();
        statusNode.textContent = data.message || (response.ok ? 'Request processed.' : 'Request failed.');
        statusNode.className = `status ${response.ok && data.success ? 'success' : 'error'}`;
    } catch (error) {
        statusNode.textContent = 'Unable to process request right now. Please try again.';
        statusNode.className = 'status error';
    }
});
</script>
</body>
</html>
