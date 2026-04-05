<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
$db = getDb();
$progress = getApplicantProgress($db, (int) $applicant['id']);
if ($progress['final_submitted_at'] === null) {
    header('Location: step3_preview.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 4 - Fee Payment</title>
    <style>
        body { font-family: Arial,sans-serif; background:#f4f7fb; margin:0; padding:20px; }
        .card { max-width:860px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#184d9b; color:#fff; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .body { padding:20px; }
        .box { border:1px solid #d7e0ed; border-radius:10px; padding:14px; margin-bottom:14px; }
        .line { margin:8px 0; }
        .label { color:#4d5f79; font-size:13px; }
        .value { color:#132235; font-weight:700; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        a, button { padding:10px 12px; border:none; border-radius:8px; cursor:pointer; text-decoration:none; background:#184d9b; color:#fff; }
        .secondary { background:#5b6b83; }
        .status { margin-top:10px; font-size:14px; }
        .declarations { border:1px solid #d7e0ed; border-radius:10px; padding:12px; margin-top:12px; background:#f9fbff; }
        .declarations h3 { margin:0 0 10px; font-size:15px; color:#123f7f; }
        .declaration-item { display:flex; gap:8px; align-items:flex-start; margin-bottom:8px; color:#132235; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <div>
            <h2 style="margin:0;">Step 4: Fee Payment (Demo)</h2>
            <small>Application Number: <?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <div><a href="../ajax/logout.php" class="secondary">Logout</a></div>
    </div>
    <div class="body">
        <div class="box" id="paymentInfo"></div>
        <div class="declarations">
            <h3>Declaration</h3>
            <label class="declaration-item">
                <input type="checkbox" id="declarationA">
                <span>I hereby declare that the information furnished in this application is true and correct to the best of my knowledge and belief.</span>
            </label>
            <label class="declaration-item">
                <input type="checkbox" id="declarationB">
                <span>I understand that if any information is found incorrect at any stage, my candidature may be cancelled.</span>
            </label>
        </div>
        <div class="actions">
            <a href="step3_preview.php" class="secondary">Back to Preview</a>
            <button id="payBtn">Simulate Successful Payment</button>
            <button id="finalSubmitAfterPaymentBtn" style="display:none;">Final Submission</button>
        </div>
        <div class="status" id="paymentStatus"></div>
    </div>
</div>
<script>
const paymentInfo = document.getElementById('paymentInfo');
const paymentStatus = document.getElementById('paymentStatus');
const payBtn = document.getElementById('payBtn');
const finalSubmitAfterPaymentBtn = document.getElementById('finalSubmitAfterPaymentBtn');
const declarationA = document.getElementById('declarationA');
const declarationB = document.getElementById('declarationB');
let isPaymentAlreadyDone = false;

function value(v) { return (v === null || v === undefined || v === '') ? '-' : v; }
function formatFee(v) { return Number(v) > 0 ? `INR ${Number(v)}/-` : '-'; }
function areDeclarationsAccepted() { return declarationA.checked && declarationB.checked; }

function syncCheckboxState(checkbox, checked, disabled) {
  checkbox.checked = checked;
  checkbox.disabled = disabled;

  if (checked) {
    checkbox.setAttribute('checked', '');
  } else {
    checkbox.removeAttribute('checked');
  }

  if (disabled) {
    checkbox.setAttribute('disabled', '');
  } else {
    checkbox.removeAttribute('disabled');
  }
}

function updatePayButtonState() {
  if (isPaymentAlreadyDone) {
    payBtn.disabled = true;
    return;
  }

  payBtn.disabled = !areDeclarationsAccepted();
}

function render(data) {
  const selected = [data.course_group_1, data.course_group_2].filter(Boolean).join(' | ') || '-';
  paymentInfo.innerHTML = `
    <div class="line"><span class="label">Applicant Name:</span> <span class="value">${value(data.candidate_name)}</span></div>
    <div class="line"><span class="label">Selected Papers/Courses:</span> <span class="value">${selected}</span></div>
    <div class="line"><span class="label">Payable Amount:</span> <span class="value">${formatFee(data.payable_amount)}</span></div>
    <div class="line"><span class="label">Bank/Service Charges:</span> <span class="value">Applicable as per payment provider (not calculated in demo mode)</span></div>
    <div class="line"><span class="label">Payment Status:</span> <span class="value">${value(data.payment_status)}</span></div>
    <div class="line"><span class="label">Payment Mode:</span> <span class="value">${value(data.payment_mode)}</span></div>
    <div class="line"><span class="label">Transaction Reference:</span> <span class="value">${value(data.transaction_reference)}</span></div>
    <div class="line"><span class="label">Payment Date/Time:</span> <span class="value">${value(data.payment_datetime)}</span></div>
  `;

  const paid = data.payment_status === 'paid';
  isPaymentAlreadyDone = paid;
  const finalSubmitted = !!data.payment_final_submitted_at;
  payBtn.style.display = paid ? 'none' : 'inline-block';
  finalSubmitAfterPaymentBtn.style.display = paid && !finalSubmitted ? 'inline-block' : 'none';

  if (paid) {
    syncCheckboxState(declarationA, true, true);
    syncCheckboxState(declarationB, true, true);
  } else {
    syncCheckboxState(declarationA, false, false);
    syncCheckboxState(declarationB, false, false);
  }

  updatePayButtonState();

  if (finalSubmitted) {
    paymentStatus.textContent = 'Final submission already completed. Redirecting to confirmation page...';
    paymentStatus.style.color = '#0a7a35';
    window.location.href = 'step5_confirmation.php';
  }
}

async function loadPaymentDetails() {
  const response = await fetch(`../ajax/payment.php?t=${Date.now()}`);
  const data = await response.json();

  if (!response.ok || !data.success) {
    paymentStatus.textContent = data.message || 'Unable to load payment details.';
    paymentStatus.style.color = '#b42318';
    payBtn.disabled = true;
    return;
  }

  render(data.data);
}

payBtn.addEventListener('click', async () => {
  if (!areDeclarationsAccepted()) {
    paymentStatus.textContent = 'Please accept both declarations before payment.';
    paymentStatus.style.color = '#b42318';
    return;
  }

  payBtn.disabled = true;
  paymentStatus.textContent = '';

  const response = await fetch('../ajax/payment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'pay',
      declaration_a: declarationA.checked,
      declaration_b: declarationB.checked
    })
  });
  const data = await response.json();

  if (!response.ok || !data.success) {
    paymentStatus.textContent = data.message || 'Demo payment failed.';
    paymentStatus.style.color = '#b42318';
    payBtn.disabled = false;
    return;
  }

  paymentStatus.textContent = data.message || 'Payment successful.';
  paymentStatus.style.color = '#0a7a35';
  await loadPaymentDetails();
});

declarationA.addEventListener('change', updatePayButtonState);
declarationB.addEventListener('change', updatePayButtonState);

finalSubmitAfterPaymentBtn.addEventListener('click', async () => {
  finalSubmitAfterPaymentBtn.disabled = true;
  paymentStatus.textContent = '';

  const response = await fetch('../ajax/payment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'final_submit' })
  });
  const data = await response.json();

  if (!response.ok || !data.success) {
    paymentStatus.textContent = data.message || 'Final submission failed.';
    paymentStatus.style.color = '#b42318';
    finalSubmitAfterPaymentBtn.disabled = false;
    return;
  }

  paymentStatus.textContent = data.message || 'Final submission successful. Redirecting to confirmation page...';
  paymentStatus.style.color = '#0a7a35';
  window.location.href = 'step5_confirmation.php';
});

loadPaymentDetails().catch(() => null);
</script>
</body>
</html>
