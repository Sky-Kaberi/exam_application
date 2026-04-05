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
        body { font-family: Arial, sans-serif; background:#f4f7fb; margin:0; padding:24px; }
        .card { max-width:540px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:24px; }
        .highlight { background:#eef4ff; border:1px dashed #184d9b; color:#0f3d83; padding:16px; border-radius:10px; margin-bottom:18px; font-size:20px; font-weight:700; }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
        input, button { padding:11px 12px; border-radius:8px; border:1px solid #cad5e2; font-size:14px; }
        button { background:#184d9b; color:#fff; border:none; cursor:pointer; width:100%; }
        .error { color:#b42318; font-size:13px; min-height:18px; }
        .link { margin-top:12px; display:block; text-align:center; }
    </style>
</head>
<body>
<div class="card">
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
        <button type="submit">Login and Continue to Step 2</button>
    </form>
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
