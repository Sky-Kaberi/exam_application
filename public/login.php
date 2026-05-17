<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$loggedInApplicant = getLoggedInApplicantSession();
if ($loggedInApplicant !== null) {
    $db = getDb();
    header('Location: ' . resolveApplicantPostLoginRedirect($db, (int) $loggedInApplicant['id']));
    exit;
}

$newApplicationId = trim((string) ($_GET['application_id'] ?? ($_SESSION['new_application_id'] ?? '')));
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Login</title>
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
        .highlight { background:#fff3d8; border:1px dashed #FFA500; color:#9a5f00; padding:16px; border-radius:10px; margin-bottom:18px; font-size:20px; font-weight:700; }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
        input, button { padding:11px 12px; border-radius:8px; border:1px solid #cad5e2; font-size:14px; }
        button { background:#FFA500; color:#1f2937; border:none; cursor:pointer; width:100%; }
        .error { color:#b42318; font-size:13px; min-height:18px; }
        .link { margin-top:12px; display:block; text-align:center; }
        .create-account-link { margin-top:14px; text-align:center; }
        .create-account-link a { color:#FFA500; font-weight:700; }
        .help-links { margin-top:14px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
        .help-links a { color:#FFA500; }

        .site-brand { display:grid; grid-template-columns:56px minmax(0, 1fr) 56px; align-items:center; gap:12px; text-align:center; margin-bottom:20px; }
        .site-brand img { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:50%; padding:4px; justify-self:center; }
        .site-brand-title { font-weight:700; font-size:clamp(16px, 4vw, 18px); line-height:1.25; }
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
    <?php if ($newApplicationId !== ''): ?>
        <div class="highlight">Your Application Number is: <?= htmlspecialchars($newApplicationId, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <h1>Applicant Login</h1>
    <p>Use your Application Number and Password created in Step 1.</p>
    <form id="loginForm">
        <div class="field">
            <label>Application Number</label>
            <input type="text" name="application_id" value="<?= htmlspecialchars($newApplicationId, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password">
        </div>
        <div class="error" id="loginError"></div>
        <button type="submit">Submit</button>
    </form>
    <div class="create-account-link">
        New applicant? <a href="index.php">Create Account</a>
    </div>
    <div class="help-links">
        <a href="forgot_application_id.php">Forgot Application ID?</a>
        <a href="forgot_password.php<?= $newApplicationId !== '' ? '?application_id=' . urlencode($newApplicationId) : '' ?>">Forgot Password?</a>
    </div>
</div>

<script>
const form = document.getElementById('loginForm');
const errorNode = document.getElementById('loginError');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorNode.textContent = '';

    const payload = Object.fromEntries(new FormData(form).entries());

    try {
        const response = await fetch('../ajax/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            errorNode.textContent = data.message || 'Login failed.';
            return;
        }

        window.location.href = data.redirect_to || 'step2.php';
    } catch (error) {
        errorNode.textContent = 'Unable to login right now. Please try again.';
    }
});
</script>
</body>
</html>
