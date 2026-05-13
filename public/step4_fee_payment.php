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

$sbiCollectUrl = 'https://www.onlinesbi.sbi/sbicollect/icollecthome.htm';
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
        button:disabled { opacity:.65; cursor:not-allowed; }
        .secondary { background:#5b6b83; }
        .status { margin-top:10px; font-size:14px; }
        .declarations { border:1px solid #d7e0ed; border-radius:10px; padding:12px; margin-top:12px; background:#f9fbff; }
        .declarations h3 { margin:0 0 10px; font-size:15px; color:#123f7f; }
        .declaration-item { display:flex; gap:8px; align-items:flex-start; margin-bottom:8px; color:#132235; }
        .instructions { background:#eef4ff; border:1px solid #bfd0ee; border-radius:10px; padding:14px; margin-bottom:14px; color:#163c70; }
        .instructions p { margin:10px 0 0; }
        .form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field.full { grid-column:1 / -1; }
        input { padding:10px 11px; border:1px solid #cad5e2; border-radius:8px; font-size:14px; }
        .error { color:#b42318; font-size:12px; min-height:14px; }
        .muted { color:#5b6b83; font-size:13px; }
        @media (max-width:768px){ .header{align-items:flex-start; flex-direction:column;} .form-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <div>
            <h2 style="margin:0;">Step 4: Fee Payment</h2>
            <small>Application Number: <?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <div><a href="../ajax/logout.php" class="secondary">Logout</a></div>
    </div>
    <div class="body">
        <div class="box" id="paymentInfo"></div>
        <div class="instructions">
            <a href="<?= htmlspecialchars($sbiCollectUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Click here to make the payment using SBI Collect</a>
            <p><strong>After making payment, fill the form below and submit.</strong></p>
        </div>
        <form id="paymentConfirmationForm" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="submit_payment">
            <div class="form-grid">
                <div class="field">
                    <label for="transactionId">Transaction ID <span aria-hidden="true">*</span></label>
                    <input type="text" id="transactionId" name="transaction_id" required maxlength="80" autocomplete="off">
                    <div class="error" data-error-for="transaction_id"></div>
                </div>
                <div class="field">
                    <label for="paymentDate">Payment Date <span aria-hidden="true">*</span></label>
                    <input type="date" id="paymentDate" name="payment_date" required>
                    <div class="error" data-error-for="payment_date"></div>
                </div>
                <div class="field full">
                    <label for="paymentReceipt">Upload SBI Collect Payment Receipt <span aria-hidden="true">*</span></label>
                    <input type="file" id="paymentReceipt" name="payment_receipt" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                    <small class="muted">Allowed formats: PDF, JPG, JPEG, PNG. Maximum size: 2MB.</small>
                    <div class="error" data-error-for="payment_receipt"></div>
                </div>
            </div>
            <div class="declarations">
                <h3>Declaration</h3>
                <label class="declaration-item">
                    <input type="checkbox" id="declarationA" name="declaration_a" value="1">
                    <span>I hereby declare that the information furnished in this application is true and correct to the best of my knowledge and belief.</span>
                </label>
                <label class="declaration-item">
                    <input type="checkbox" id="declarationB" name="declaration_b" value="1">
                    <span>I understand that if any information is found incorrect at any stage, my candidature may be cancelled.</span>
                </label>
            </div>
            <div class="actions">
                <a href="step3_preview.php" class="secondary">Back to Preview</a>
                <button type="submit" id="submitPaymentBtn">Submit Payment Details</button>
                <button type="button" id="finalSubmitAfterPaymentBtn" style="display:none;">Final Submission</button>
            </div>
        </form>
        <div class="status" id="paymentStatus"></div>
    </div>
</div>
<script>
const paymentInfo = document.getElementById('paymentInfo');
const paymentStatus = document.getElementById('paymentStatus');
const paymentConfirmationForm = document.getElementById('paymentConfirmationForm');
const submitPaymentBtn = document.getElementById('submitPaymentBtn');
const finalSubmitAfterPaymentBtn = document.getElementById('finalSubmitAfterPaymentBtn');
const declarationA = document.getElementById('declarationA');
const declarationB = document.getElementById('declarationB');
let isPaymentAlreadyDone = false;

function value(v) { return (v === null || v === undefined || v === '') ? '-' : v; }
function escapeHtml(v) {
  return String(value(v)).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
}
function formatFee(v) { return Number(v) > 0 ? `INR ${Number(v)}/-` : '-'; }
function areDeclarationsAccepted() { return declarationA.checked && declarationB.checked; }

function clearErrors() {
  paymentConfirmationForm.querySelectorAll('.error').forEach((node) => { node.textContent = ''; });
}

function showErrors(errors = {}) {
  Object.entries(errors).forEach(([fieldName, message]) => {
    const node = paymentConfirmationForm.querySelector(`[data-error-for="${fieldName}"]`);
    if (node) node.textContent = message;
  });
}

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

function render(data) {
  const selected = [data.course_group_1, data.course_group_2].filter(Boolean).join(' | ') || '-';
  paymentInfo.innerHTML = `
    <div class="line"><span class="label">Applicant Name:</span> <span class="value">${escapeHtml(data.candidate_name)}</span></div>
    <div class="line"><span class="label">Selected Papers/Courses:</span> <span class="value">${escapeHtml(selected)}</span></div>
    <div class="line"><span class="label">Payable Amount:</span> <span class="value">${formatFee(data.payable_amount)}</span></div>
    <div class="line"><span class="label">Transaction ID:</span> <span class="value">${escapeHtml(data.transaction_reference)}</span></div>
    <div class="line"><span class="label">Payment Date:</span> <span class="value">${escapeHtml(data.payment_date)}</span></div>
  `;

  isPaymentAlreadyDone = data.payment_status === 'paid';
  const paymentSubmitted = data.payment_status === 'payment_submitted';
  const finalSubmitted = !!data.payment_final_submitted_at;
  submitPaymentBtn.disabled = isPaymentAlreadyDone;
  submitPaymentBtn.style.display = isPaymentAlreadyDone ? 'none' : 'inline-block';
  finalSubmitAfterPaymentBtn.style.display = isPaymentAlreadyDone && !finalSubmitted ? 'inline-block' : 'none';

  if (data.transaction_reference) {
    paymentConfirmationForm.elements.transaction_id.value = data.transaction_reference;
  }
  if (data.payment_date) {
    paymentConfirmationForm.elements.payment_date.value = data.payment_date;
  }

  if (isPaymentAlreadyDone) {
    syncCheckboxState(declarationA, true, true);
    syncCheckboxState(declarationB, true, true);
  } else {
    syncCheckboxState(declarationA, false, false);
    syncCheckboxState(declarationB, false, false);
  }

  if (paymentSubmitted && !isPaymentAlreadyDone) {
    paymentStatus.textContent = 'Payment details submitted successfully. The payment will be verified by the office before it is treated as paid.';
    paymentStatus.style.color = '#0a7a35';
  }

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
    submitPaymentBtn.disabled = true;
    return;
  }

  render(data.data);
}

paymentConfirmationForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  clearErrors();

  if (!areDeclarationsAccepted()) {
    paymentStatus.textContent = 'Please accept both declarations before submitting payment details.';
    paymentStatus.style.color = '#b42318';
    return;
  }

  submitPaymentBtn.disabled = true;
  paymentStatus.textContent = '';

  const response = await fetch('../ajax/payment.php', {
    method: 'POST',
    body: new FormData(paymentConfirmationForm)
  });
  const data = await response.json();

  if (!response.ok || !data.success) {
    showErrors(data.errors || {});
    paymentStatus.textContent = data.message || 'Unable to submit payment details.';
    paymentStatus.style.color = '#b42318';
    submitPaymentBtn.disabled = false;
    return;
  }

  paymentStatus.textContent = data.message || 'Payment details submitted successfully.';
  paymentStatus.style.color = '#0a7a35';
  paymentConfirmationForm.elements.payment_receipt.value = '';
  await loadPaymentDetails();
});

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
