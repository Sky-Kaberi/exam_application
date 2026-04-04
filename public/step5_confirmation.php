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
        .card { max-width:1100px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#184d9b; color:#fff; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .body { padding:20px; }
        .section { border:1px solid #d7e0ed; border-radius:10px; padding:14px; margin-bottom:14px; }
        .section h3 { margin:0 0 10px; color:#123f7f; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px 12px; }
        .item { padding:10px; border:1px solid #d7e0ed; border-radius:8px; background:#f9fbff; }
        .k { color:#4d5f79; font-size:12px; display:block; }
        .v { color:#132235; font-weight:700; word-break:break-word; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        a, button { padding:10px 12px; border:none; border-radius:8px; cursor:pointer; text-decoration:none; background:#184d9b; color:#fff; }
        .secondary { background:#5b6b83; }
        .status { margin-top:10px; font-size:14px; }
        .images { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px 12px; }
        .images img { max-width:240px; max-height:160px; border:1px solid #d7e0ed; border-radius:8px; padding:4px; background:#fff; }
        @media print {
            body { background:#fff; padding:0; }
            .header, .actions { display:none !important; }
            .card { box-shadow:none; border-radius:0; max-width:none; }
        }
        @media (max-width:768px){ .grid,.images{ grid-template-columns:1fr; } }
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
        <div id="confirmationRoot"></div>
        <div class="actions">
            <a href="step4_fee_payment.php" class="secondary">Back to Fee Payment</a>
            <button id="printBtn">Print / Download Confirmation</button>
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

function renderSection(title, fields) {
  const rows = fields.map(([label, v]) => item(label, v)).join('');
  return `<div class="section"><h3>${title}</h3><div class="grid">${rows}</div></div>`;
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
  confirmationRoot.innerHTML = [
    renderSection('Step 1 - Registration', [
      ['Application No', d.step1?.application_id],
      ['Candidate Name', d.step1?.candidate_name],
      ['Father Name', d.step1?.father_name],
      ['Mother Name', d.step1?.mother_name],
      ['Date of Birth', d.step1?.date_of_birth],
      ['Gender', d.step1?.gender],
      ['Identification Type', d.step1?.identification_type],
      ['Identification No', d.step1?.identification_no],
      ['Mobile', d.step1?.mobile_no],
      ['Email', d.step1?.email_id]
    ]),
    renderSection('Step 2 - Basic Info', [
      ['Nationality', d.basic?.nationality],
      ['Domicile', d.basic?.domicile],
      ['Religion', d.basic?.religion],
      ['Category', d.basic?.category],
      ['Sub Category Details', d.basic?.sub_category_details],
      ['PwD', d.basic?.pwd_status],
      ['Disability Type', d.basic?.disability_type],
      ['Disability %', d.basic?.disability_percentage],
      ['Qualifying Exam', d.basic?.qualifying_examination],
      ['Pass Status', d.basic?.pass_status],
      ['Year of Passing', d.basic?.year_of_passing],
      ['Institute', d.basic?.institute_name_address]
    ]),
    renderSection('Step 2 - Address', [
      ['Corr Premises', d.address?.corr_premises],
      ['Corr Sub-locality', d.address?.corr_sub_locality],
      ['Corr Locality', d.address?.corr_locality],
      ['Corr Country', d.address?.corr_country],
      ['Corr State', d.address?.corr_state],
      ['Corr District', d.address?.corr_district],
      ['Corr PIN', d.address?.corr_pin_code],
      ['Same as Correspondence', Number(d.address?.same_as_correspondence) ? 'Yes' : 'No'],
      ['Perm Premises', d.address?.perm_premises],
      ['Perm Sub-locality', d.address?.perm_sub_locality],
      ['Perm Locality', d.address?.perm_locality],
      ['Perm Country', d.address?.perm_country],
      ['Perm State', d.address?.perm_state],
      ['Perm District', d.address?.perm_district],
      ['Perm PIN', d.address?.perm_pin_code]
    ]),
    renderSection('Step 2 - Course Selection', [
      ['Group-1 Course', d.courses?.course_group_1],
      ['Group-2 Course', d.courses?.course_group_2],
      ['Exam City', d.courses?.exam_city],
      ['Application Fee', formatFee(d.courses?.application_fee)]
    ]),
    renderSection('Step 4/5 - Payment & Confirmation', [
      ['Payment Status', d.step1?.payment_status],
      ['Payment Mode', d.step1?.payment_mode],
      ['Amount Paid', formatFee(d.step1?.payment_amount)],
      ['Transaction/Reference No', d.step1?.transaction_reference],
      ['Payment Date/Time', d.step1?.payment_datetime],
      ['Demo Payment Flag', Number(d.step1?.payment_demo_flag) ? 'Yes' : 'No'],
      ['Confirmation Date/Time', d.confirmation_datetime]
    ]),
    `<div class="section"><h3>Step 2 - Uploaded Images</h3><div class="images"><div class="item"><span class="k">Photograph</span>${d.images?.photo_path ? `<img src="../public/${d.images.photo_path}" alt="Photograph">` : '<span class="v">-</span>'}</div><div class="item"><span class="k">Signature</span>${d.images?.signature_path ? `<img src="../public/${d.images.signature_path}" alt="Signature">` : '<span class="v">-</span>'}</div></div></div>`
  ].join('');
}

document.getElementById('printBtn').addEventListener('click', () => window.print());
loadConfirmation().catch(() => null);
</script>
</body>
</html>
