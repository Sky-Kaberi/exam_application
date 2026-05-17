<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
$backUrl = trim((string) ($_GET['back'] ?? ''));
if ($backUrl === '' || preg_match('/^(?:https?:)?\/\//i', $backUrl) || strpos($backUrl, '..') !== false) {
    $backUrl = 'step2.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
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
        .card { max-width:580px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:24px; }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
        input, button, a.button { padding:11px 12px; border-radius:8px; border:1px solid #cad5e2; font-size:14px; }
        button, a.button { background:#FFA500; color:#1f2937; border:none; cursor:pointer; text-decoration:none; text-align:center; }
        button:disabled { opacity:.65; cursor:not-allowed; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
        .actions button, .actions a { flex:1 1 180px; }
        .secondary { background:#5b6b83 !important; color:#fff !important; }
        .status { font-size:13px; min-height:18px; margin-bottom:12px; }
        .success { color:#0a7a35; }
        .error, .field-error { color:#b42318; }
        .field-error { font-size:12px; min-height:14px; }
        .muted { color:#5b6b83; font-size:13px; margin-top:-4px; }
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
        <img src="https://wbjeeb.in/JEMASPG/assets/img/West_Bengal_Joint_Entrance_Examinations_Board_Logo.png" alt="West Bengal Joint Entrance Examinations Board Logo">
        <div>
            <div class="site-brand-title">West Bengal Joint Entrance Examinations Board</div>
            <div class="site-brand-exam">JEMPAS(PG) - 2025</div>
        </div>
        <img src="https://wbjeeb.in/JEMASPG/assets/img/West_Bengal_Joint_Entrance_Examinations_Board_Logo.png" alt="West Bengal Joint Entrance Examinations Board Logo">
    </div>
    <h1>Change Password</h1>
    <p>Application Number: <strong><?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p class="muted">Password must be 8-13 characters and include uppercase, lowercase, number and special character.</p>
    <form id="changePasswordForm" novalidate>
        <div class="field">
            <label for="currentPassword">Current Password</label>
            <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" required>
            <div class="field-error" data-error-for="current_password"></div>
        </div>
        <div class="field">
            <label for="newPassword">New Password</label>
            <input type="password" id="newPassword" name="new_password" autocomplete="new-password" required>
            <div class="field-error" data-error-for="new_password"></div>
        </div>
        <div class="field">
            <label for="confirmPassword">Confirm New Password</label>
            <input type="password" id="confirmPassword" name="confirm_password" autocomplete="new-password" required>
            <div class="field-error" data-error-for="confirm_password"></div>
        </div>
        <div class="status" id="status"></div>
        <div class="actions">
            <a class="button secondary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">Back</a>
            <button type="submit" id="submitBtn">Change Password</button>
        </div>
    </form>
</div>
<script>
const form = document.getElementById('changePasswordForm');
const statusNode = document.getElementById('status');
const submitBtn = document.getElementById('submitBtn');

function clearErrors() {
    form.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
}

function showErrors(errors) {
    Object.entries(errors || {}).forEach(([field, message]) => {
        const node = form.querySelector(`[data-error-for="${field}"]`);
        if (node) node.textContent = message;
    });
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();
    statusNode.textContent = '';
    statusNode.className = 'status';
    submitBtn.disabled = true;

    try {
        const response = await fetch('../ajax/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
        });
        const data = await response.json();
        statusNode.textContent = data.message || (response.ok ? 'Password changed.' : 'Unable to change password.');
        statusNode.className = `status ${response.ok && data.success ? 'success' : 'error'}`;
        if (data.errors) showErrors(data.errors);
        if (response.ok && data.success) form.reset();
    } catch (error) {
        statusNode.textContent = 'Unable to change password right now. Please try again.';
        statusNode.className = 'status error';
    } finally {
        submitBtn.disabled = false;
    }
});
</script>
</body>
</html>
