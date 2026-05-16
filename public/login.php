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
        body { font-family: Arial, sans-serif; background:#fff8ec; margin:0; padding:24px; }
        .card { max-width:540px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:24px; }
        .highlight { background:#fff3d8; border:1px dashed #FFA500; color:#9a5f00; padding:16px; border-radius:10px; margin-bottom:18px; font-size:20px; font-weight:700; }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
        input, button { padding:11px 12px; border-radius:8px; border:1px solid #cad5e2; font-size:14px; }
        button { background:#FFA500; color:#1f2937; border:none; cursor:pointer; width:100%; }
        .error { color:#b42318; font-size:13px; min-height:18px; }
        .link { margin-top:12px; display:block; text-align:center; }
        .help-links { margin-top:14px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
        .help-links a { color:#FFA500; }

        .site-brand { display:flex; align-items:center; justify-content:center; gap:12px; text-align:center; flex-wrap:wrap; }
        .site-brand img { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:50%; padding:4px; }
        .site-brand-title { font-weight:700; font-size:18px; line-height:1.25; }
        .site-brand-exam { font-weight:700; font-size:15px; margin-top:2px; }
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
