<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
$db = getDb();
$paymentStatusStmt = $db->prepare('SELECT payment_status FROM applicants WHERE id = :id LIMIT 1');
$paymentStatusStmt->execute(['id' => $applicant['id']]);
$paymentStatus = (string) ($paymentStatusStmt->fetchColumn() ?: 'unpaid');
if ($paymentStatus !== 'paid') {
    header('Location: step4_fee_payment.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 5 - Confirmation</title>
    <style>
        body { font-family: Arial,sans-serif; background:#f4f7fb; margin:0; padding:20px; }
        .card { max-width:900px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#184d9b; color:#fff; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .body { padding:20px; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px 12px; }
        .item { padding:10px; border:1px solid #d7e0ed; border-radius:8px; background:#f9fbff; }
        .k { color:#4d5f79; font-size:12px; display:block; }
        .v { color:#132235; font-weight:700; word-break:break-word; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        a, button { padding:10px 12px; border:none; border-radius:8px; cursor:pointer; text-decoration:none; background:#184d9b; color:#fff; }
        .secondary { background:#5b6b83; }
        .status { margin-top:10px; font-size:14px; }
        @media print {
            body { background:#fff; padding:0; }
            .header, .actions { display:none !important; }
            .card { box-shadow:none; border-radius:0; max-width:none; }
        }
        @media (max-width:768px){ .grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <div>
            <h2 style="margin:0;">Step 5: Confirmation Page</h2>
            <small>Application Number: <?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <div><a href="../ajax/logout.php" class="secondary">Logout</a></div>
    </div>
    <div class="body">
        <div class="grid" id="confirmationRoot"></div>
        <div class="actions">
            <a href="step4_fee_payment.php" class="secondary">Back to Fee Payment</a>
            <button id="printBtn">Print Confirmation</button>
        </div>
        <div class="status" id="confirmationStatus"></div>
    </div>
</div>
<script>
const confirmationRoot = document.getElementById('confirmationRoot');
const confirmationStatus = document.getElementById('confirmationStatus');

function value(v) { return (v === null || v === undefined || v === '') ? '-' : v; }
function formatFee(v) { return Number(v) > 0 ? `INR ${Number(v)}/-` : '-'; }

function item(label, valueText) {
  return `<div class="item"><span class="k">${label}</span><span class="v">${value(valueText)}</span></div>`;
}

async function loadConfirmation() {
  const response = await fetch(`../ajax/confirmation.php?t=${Date.now()}`);
  const data = await response.json();

  if (!response.ok || !data.success) {
    confirmationStatus.textContent = data.message || 'Unable to load confirmation page.';
    confirmationStatus.style.color = '#b42318';
    confirmationRoot.innerHTML = '';
    return;
  }

  const d = data.data;
  const selected = [d.course_group_1, d.course_group_2].filter(Boolean).join(' | ') || '-';

  confirmationRoot.innerHTML = [
    item('Application No', d.application_id),
    item('Applicant Name', d.candidate_name),
    item('Selected Papers/Courses', selected),
    item('Payable Amount', formatFee(d.payable_amount)),
    item('Payment Status', d.payment_status),
    item('Payment Mode', d.payment_mode),
    item('Transaction/Reference No', d.transaction_reference),
    item('Payment Date/Time', d.payment_datetime),
    item('Confirmation Date/Time', d.confirmation_datetime)
  ].join('');
}

document.getElementById('printBtn').addEventListener('click', () => window.print());
loadConfirmation().catch(() => null);
</script>
</body>
</html>
