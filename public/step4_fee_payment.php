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

$paymentStatusStmt = $db->prepare('SELECT payment_status FROM applicants WHERE id = :id LIMIT 1');
$paymentStatusStmt->execute(['id' => $applicant['id']]);
if ((string) ($paymentStatusStmt->fetchColumn() ?: 'not_submitted') === 'paid') {
    header('Location: step5_confirmation.php');
    exit;
}

$sbiCollectUrl = 'https://onlinesbi.sbi.bank.in/sbicollect/icollecthome.htm?saralID=-912860120';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 4 - Fee Payment</title>
    <style>

        * { box-sizing: border-box; }
        html, body { max-width: 100%; overflow-x: hidden; }
        img { max-width: 100%; height: auto; }
        input, select, textarea, button { max-width: 100%; }
        .container, .card, .page-wrap { width: 100%; }
        .header > div, .site-brand > div { min-width: 0; }
        .header > div:last-child { display:flex; gap:8px; flex-wrap:wrap; }
        h1, h2, h3, p, small, label, a, button { overflow-wrap: anywhere; }
        body { font-family: Arial,sans-serif; background:#fff8ec; margin:0; padding:20px; }
        .card { max-width:860px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#FFA500; color:#1f2937; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .body { padding:20px; }
        .box { border:1px solid #d7e0ed; border-radius:10px; padding:14px; margin-bottom:14px; }
        .line { margin:8px 0; }
        .label { color:#4d5f79; font-size:13px; }
        .value { color:#132235; font-weight:700; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        a, button { padding:10px 12px; border:none; border-radius:8px; cursor:pointer; text-decoration:none; background:#FFA500; color:#1f2937; }
        button:disabled { opacity:.65; cursor:not-allowed; }
        .secondary { background:#5b6b83; color:#fff; }
        .status { margin-top:10px; font-size:14px; }
        .declarations { border:1px solid #d7e0ed; border-radius:10px; padding:12px; margin-top:12px; background:#f9fbff; }
        .declarations h3 { margin:0 0 10px; font-size:15px; color:#c97800; }
        .declaration-item { display:flex; gap:8px; align-items:flex-start; margin-bottom:8px; color:#132235; }
        .instructions { background:#fff3d8; border:1px solid #e8b45b; border-radius:10px; padding:14px; margin-bottom:14px; color:#9a5f00; }
        .instructions p { margin:10px 0 0; }
        .form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field.full { grid-column:1 / -1; }
        input { padding:10px 11px; border:1px solid #cad5e2; border-radius:8px; font-size:14px; }
        .error { color:#b42318; font-size:12px; min-height:14px; }
        .muted { color:#5b6b83; font-size:13px; }
        .notice { border-radius:10px; padding:12px; margin-bottom:14px; }
        .notice.rejected { background:#fff1f0; border:1px solid #f5b8b2; color:#9f1d12; }
        .notice.pending { background:#fff8e1; border:1px solid #f0d98a; color:#7a4b00; }
        .receipt-link { display:inline-block; padding:6px 9px; border-radius:7px; background:#5b6b83; color:#fff; margin-top:4px; }

        .site-brand { display:flex; align-items:center; justify-content:center; gap:12px; text-align:center; flex-wrap:wrap; }
        .site-brand img { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:50%; padding:4px; }
        .site-brand-title { font-weight:700; font-size:18px; line-height:1.25; }
        .site-brand-exam { font-weight:700; font-size:15px; margin-top:2px; }
        @media (max-width:768px){ .header{align-items:flex-start; flex-direction:column;} .form-grid{grid-template-columns:1fr;} }

        @media (max-width:600px){
            body { padding:10px; }
            .content, .body { padding:14px; }
            .header { align-items:stretch; flex-direction:column; }
            .header > div:last-child { width:100%; }
            .header a, .header-login-link { display:inline-flex; justify-content:center; text-align:center; white-space:normal; }
            .site-brand { gap:8px; }
            .site-brand img { width:42px; height:42px; flex:0 0 42px; }
            .site-brand-title { font-size:clamp(14px, 4.4vw, 16px); }
            .site-brand-exam { font-size:13px; }
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
    <div class="header">
        <div>
            <div class="site-brand">
                <img src="https://upload.wikimedia.org/wikipedia/en/thumb/4/46/West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg/250px-West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg.png" alt="West Bengal Joint Entrance Examinations Board Logo">
                <div>
                    <div class="site-brand-title">West Bengal Joint Entrance Examinations Board</div>
                    <div class="site-brand-exam">JEMPAS(PG) - 2025</div>
                </div>
                <img src="https://upload.wikimedia.org/wikipedia/en/thumb/4/46/West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg/250px-West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg.png" alt="West Bengal Joint Entrance Examinations Board Logo">
            </div>
            <h2 style="margin:0;">Step 4: Fee Payment</h2>
            <small>Application Number: <?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <div><a href="change_password.php?back=step4_fee_payment.php" class="secondary">Change Password</a> <a href="../ajax/logout.php" class="secondary">Logout</a></div>
    </div>
    <div class="body">
        <div class="box" id="paymentInfo"></div>
        <div id="paymentReviewNotice"></div>
        <div class="instructions" id="paymentInstructions">
            <a href="<?= htmlspecialchars($sbiCollectUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Click here to make the payment using SBI Collect</a>
            <p><strong>After making payment, fill the form below and submit.</strong></p>
        </div>
        <form id="paymentConfirmationForm" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="submit_payment">
            <div class="form-grid">
                <div class="field">
                    <label for="transactionId">SBI Collect Reference Number <span aria-hidden="true">*</span></label>
                    <input type="text" id="transactionId" name="transaction_id" required maxlength="80" autocomplete="off" aria-required="true">
                    <div class="error" data-error-for="transaction_id"></div>
                </div>
                <div class="field">
                    <label for="paymentDate">Payment Date <span aria-hidden="true">*</span></label>
                    <input type="date" id="paymentDate" name="payment_date" required aria-required="true">
                    <div class="error" data-error-for="payment_date"></div>
                </div>
                <div class="field full">
                    <label for="paymentReceipt">Upload SBI Collect Payment Receipt <span aria-hidden="true">*</span></label>
                    <input type="file" id="paymentReceipt" name="payment_receipt" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required aria-required="true">
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
            </div>
        </form>
        <div class="status" id="paymentStatus"></div>
    </div>
</div>
<script>
const paymentInfo = document.getElementById('paymentInfo');
const paymentStatus = document.getElementById('paymentStatus');
const paymentConfirmationForm = document.getElementById('paymentConfirmationForm');
const paymentReviewNotice = document.getElementById('paymentReviewNotice');
const paymentInstructions = document.getElementById('paymentInstructions');
const submitPaymentBtn = document.getElementById('submitPaymentBtn');
const declarationA = document.getElementById('declarationA');
const declarationB = document.getElementById('declarationB');
let isPaymentAlreadyDone = false;

function value(v) { return (v === null || v === undefined || v === '') ? '-' : v; }
function escapeHtml(v) {
  return String(value(v)).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
}
function formatFee(v) { return Number(v) > 0 ? `INR ${Number(v)}/-` : '-'; }
function statusLabel(status) { return String(status || 'not_submitted').replace(/_/g, ' ').replace(/\b\w/g, (ch) => ch.toUpperCase()); }
function hasSubmittedSbiDetails(data) {
  return Boolean(data.transaction_reference || data.payment_date || data.payment_receipt_file || data.payment_submitted_at);
}
function isRejected(data) { return data.payment_status === 'rejected'; }
function receiptLink(path) {
  if (!path) return '-';
  return `<a class="receipt-link" href="${escapeHtml(path)}" target="_blank" rel="noopener noreferrer">View uploaded receipt</a>`;
}
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

function validateMandatoryPaymentFields() {
  const errors = {};
  const transactionReference = paymentConfirmationForm.elements.transaction_id.value.trim();
  const paymentDate = paymentConfirmationForm.elements.payment_date.value.trim();
  const paymentReceipt = paymentConfirmationForm.elements.payment_receipt;
  const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

  if (!transactionReference) {
    errors.transaction_id = 'SBI Collect Reference Number is required.';
  }
  if (!paymentDate) {
    errors.payment_date = 'Payment Date is required.';
  }
  if (!paymentReceipt.files || paymentReceipt.files.length === 0) {
    errors.payment_receipt = 'SBI Collect Payment Receipt upload is required.';
  } else {
    const fileName = paymentReceipt.files[0].name || '';
    const extension = fileName.includes('.') ? fileName.split('.').pop().toLowerCase() : '';
    if (!allowedExtensions.includes(extension)) {
      errors.payment_receipt = 'Payment receipt must be a PDF, JPG, JPEG, or PNG file.';
    }
  }

  showErrors(errors);
  return Object.keys(errors).length === 0;
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
  const previousPaymentHtml = hasSubmittedSbiDetails(data) ? `
    <hr>
    <div class="line"><span class="label">Previous Payment Status:</span> <span class="value">${escapeHtml(statusLabel(data.payment_status))}</span></div>
    <div class="line"><span class="label">Previous SBI Collect Reference Number:</span> <span class="value">${escapeHtml(data.transaction_reference)}</span></div>
    <div class="line"><span class="label">Previous Payment Date:</span> <span class="value">${escapeHtml(data.payment_date)}</span></div>
    <div class="line"><span class="label">Previous Receipt:</span> <span class="value">${receiptLink(data.sbi_receipt_path || data.payment_receipt_file)}</span></div>
    <div class="line"><span class="label">Submitted At:</span> <span class="value">${escapeHtml(data.payment_submitted_at)}</span></div>
    <div class="line"><span class="label">Admin Note / Rejection Reason:</span> <span class="value">${escapeHtml(data.payment_admin_note)}</span></div>
  ` : '';
  paymentInfo.innerHTML = `
    <div class="line"><span class="label">Applicant Name:</span> <span class="value">${escapeHtml(data.candidate_name)}</span></div>
    <div class="line"><span class="label">Selected Papers/Courses:</span> <span class="value">${escapeHtml(selected)}</span></div>
    <div class="line"><span class="label">Payable Amount:</span> <span class="value">${formatFee(data.payable_amount)}</span></div>
    ${previousPaymentHtml}
  `;

  isPaymentAlreadyDone = data.payment_status === 'paid';
  const detailsSubmitted = hasSubmittedSbiDetails(data);
  const allowResubmission = Boolean(data.resubmission_allowed) || isRejected(data);
  const lockSubmittedDetails = detailsSubmitted && !allowResubmission;
  paymentReviewNotice.innerHTML = '';

  if (isPaymentAlreadyDone) {
    paymentStatus.textContent = 'Payment verified. Redirecting to confirmation page...';
    paymentStatus.style.color = '#0a7a35';
    window.location.href = 'step5_confirmation.php';
    return;
  }

  paymentConfirmationForm.style.display = lockSubmittedDetails ? 'none' : 'block';
  paymentInstructions.style.display = lockSubmittedDetails ? 'none' : 'block';
  submitPaymentBtn.disabled = lockSubmittedDetails;
  submitPaymentBtn.textContent = allowResubmission && detailsSubmitted ? 'Submit Updated Payment Details' : 'Submit Payment Details';

  if (isRejected(data)) {
    paymentConfirmationForm.elements.transaction_id.value = '';
    paymentConfirmationForm.elements.payment_date.value = '';
  } else {
    if (data.transaction_reference) {
      paymentConfirmationForm.elements.transaction_id.value = data.transaction_reference;
    }
    if (data.payment_date) {
      paymentConfirmationForm.elements.payment_date.value = data.payment_date;
    }
  }

  syncCheckboxState(declarationA, false, false);
  syncCheckboxState(declarationB, false, false);

  if (lockSubmittedDetails) {
    paymentReviewNotice.innerHTML = '<div class="notice pending"><strong>Payment submitted for verification.</strong> Once your payment is verified you will be able to view & download the confirmation receipt.</div>';
    paymentStatus.textContent = 'Once your payment is verified you will be able to view & download the confirmation receipt.';
    paymentStatus.style.color = '#9a5f00';
  } else if (allowResubmission && detailsSubmitted) {
    paymentReviewNotice.innerHTML = '<div class="notice rejected"><strong>Your earlier payment submission was rejected.</strong> Please enter the updated SBI Collect reference number, payment date, and upload the corrected SBI Collect receipt. After submission it will move to Pending Verification.</div>';
    paymentStatus.textContent = 'Please submit updated payment details for verification.';
    paymentStatus.style.color = '#9f1d12';
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

  if (!validateMandatoryPaymentFields()) {
    paymentStatus.textContent = 'Please complete all mandatory payment fields before submitting payment details.';
    paymentStatus.style.color = '#b42318';
    return;
  }

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


loadPaymentDetails().catch(() => null);
</script>
</body>
</html>
