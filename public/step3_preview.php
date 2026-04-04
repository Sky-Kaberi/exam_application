<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 3 - Preview & Final Submit</title>
    <style>
        body { font-family: Arial,sans-serif; background:#f4f7fb; margin:0; padding:20px; }
        .card { max-width:1000px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#184d9b; color:#fff; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
        .body { padding:20px; }
        .section { border:1px solid #d7e0ed; border-radius:10px; padding:14px; margin-bottom:14px; }
        .section h3 { margin:0 0 10px; color:#123f7f; }
        .row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 12px; }
        .item { padding:8px; background:#f9fbff; border-radius:8px; }
        .k { color:#4d5f79; font-size:12px; display:block; }
        .v { color:#132235; font-weight:600; word-break:break-word; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        a, button { padding:10px 12px; border:none; border-radius:8px; cursor:pointer; text-decoration:none; background:#184d9b; color:#fff; }
        button.secondary, a.secondary { background:#5b6b83; }
        .status { margin-top:10px; font-size:14px; }
        img { max-width:220px; max-height:140px; border:1px solid #d7e0ed; border-radius:8px; padding:4px; }
        .address-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .address-block { border:1px solid #d7e0ed; border-radius:8px; padding:10px; background:#f9fbff; }
        .address-block h4 { margin:0 0 8px; color:#123f7f; font-size:15px; }
        .address-line { margin:0; color:#132235; font-weight:600; line-height:1.45; word-break:break-word; }
        @media (max-width:768px){ .row{grid-template-columns:1fr;} }
        @media (max-width:768px){ .address-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <div>
            <h2 style="margin:0;">Step 3: Preview & Final Submit</h2>
            <small>Application Number: <?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <div><a href="../ajax/logout.php" class="secondary">Logout</a></div>
    </div>
    <div class="body">
        <div id="previewRoot"></div>
        <div class="actions">
            <a href="step2.php" class="secondary">Back to Step 2</a>
            <button id="finalSubmitBtn">Proceed to Fee Payment</button>
        </div>
        <div class="status" id="previewStatus"></div>
    </div>
</div>
<script>
const root = document.getElementById('previewRoot');
const statusNode = document.getElementById('previewStatus');
const finalSubmitBtn = document.getElementById('finalSubmitBtn');

function value(v) { return (v === null || v === undefined || v === '') ? '-' : v; }
function formatFee(v) { return Number(v) > 0 ? `INR ${Number(v)}/-` : '-'; }

function renderSection(title, fields, editTab) {
  const rows = fields.map(([k, v]) => `<div class="item"><span class="k">${k}</span><span class="v">${value(v)}</span></div>`).join('');
  return `<div class="section"><h3>${title}</h3><div class="row">${rows}</div><div style="margin-top:10px;"><a href="step2.php?tab=${editTab}" class="secondary">Edit</a></div></div>`;
}

function clean(v) {
  return (v === null || v === undefined) ? '' : String(v).trim();
}

function joinParts(parts) {
  const values = parts.map(clean).filter(Boolean);
  return values.length ? values.join(', ') : '';
}

function formatAddressLines(address, prefix) {
  const line1 = joinParts([address?.[`${prefix}_premises`], address?.[`${prefix}_sub_locality`]]);
  const line2 = joinParts([address?.[`${prefix}_locality`] || address?.[`${prefix}_district`], address?.[`${prefix}_state`]]);
  const country = clean(address?.[`${prefix}_country`]);
  const pin = clean(address?.[`${prefix}_pin_code`]);
  const line3 = country && pin ? `${country} – ${pin}` : (country || pin);
  const lines = [line1, line2, line3].filter(Boolean);
  return lines.length ? lines : ['-'];
}

function renderAddressBlock(title, lines) {
  const lineNodes = lines.map((line) => `<p class="address-line">${value(line)}</p>`).join('');
  return `<div class="address-block"><h4>${title}</h4>${lineNodes}</div>`;
}

function renderAddressSection(address) {
  const corrLines = formatAddressLines(address, 'corr');
  const permLines = formatAddressLines(address, 'perm');
  return `
    <div class="section">
      <h3>Address Details</h3>
      <div class="address-grid">
        ${renderAddressBlock('Correspondence Address', corrLines)}
        ${renderAddressBlock('Permanent Address', permLines)}
      </div>
      <div style="margin-top:10px;"><a href="step2.php?tab=address" class="secondary">Edit</a></div>
    </div>`;
}

async function loadPreview() {
  const response = await fetch(`../ajax/preview.php?t=${Date.now()}`, { cache: 'no-store' });
  const data = await response.json();

  if (!response.ok || !data.success) {
    root.innerHTML = '<p>Unable to load preview data.</p>';
    return;
  }

  const d = data.data;
  const completed = Number(d.progress.step2_basic_completed) && Number(d.progress.step2_address_completed) && Number(d.progress.step2_courses_completed) && Number(d.progress.step2_images_completed);
  if (!completed) {
    statusNode.textContent = 'Please complete all Step 2 tabs before final submission.';
    statusNode.style.color = '#b42318';
    finalSubmitBtn.disabled = true;
  }

  root.innerHTML = [
    renderSection('Step 1 - Registration', [
      ['Candidate Name', d.step1?.candidate_name], ['Father Name', d.step1?.father_name], ['Mother Name', d.step1?.mother_name], ['Date of Birth', d.step1?.date_of_birth],
      ['Gender', d.step1?.gender], ['Identification Type', d.step1?.identification_type], ['Identification No', d.step1?.identification_no], ['Mobile', d.step1?.mobile_no], ['Email', d.step1?.email_id]
    ], 'basic'),
    renderSection('Step 2 - Basic Info', [
      ['Nationality', d.basic?.nationality], ['Domicile', d.basic?.domicile], ['Religion', d.basic?.religion], ['Category', d.basic?.category],
      ['PwD', d.basic?.pwd_status], ['Disability Type', d.basic?.disability_type], ['Disability %', d.basic?.disability_percentage], ['Qualifying Exam', d.basic?.qualifying_examination],
      ['Year of Passing', d.basic?.year_of_passing], ['Institute', d.basic?.institute_name_address]
    ], 'basic'),
    renderAddressSection(d.address),
    renderSection('Step 2 - Course Selection', [
      ['Group-1 Course', d.courses?.course_group_1], ['Group-2 Course', d.courses?.course_group_2], ['Exam City', d.courses?.exam_city], ['Application Fee', formatFee(d.courses?.application_fee)]
    ], 'courses'),
    `<div class="section"><h3>Step 2 - Images</h3><div class="row"><div class="item"><span class="k">Photograph</span>${d.images?.photo_path ? `<img src="../public/${d.images.photo_path}" alt="Photograph">` : '<span class="v">-</span>'}</div><div class="item"><span class="k">Signature</span>${d.images?.signature_path ? `<img src="../public/${d.images.signature_path}" alt="Signature">` : '<span class="v">-</span>'}</div></div><div style="margin-top:10px;"><a href="step2.php?tab=image" class="secondary">Edit</a></div></div>`
  ].join('');
}

window.addEventListener('pageshow', () => {
  loadPreview().catch(() => null);
});

finalSubmitBtn.addEventListener('click', async () => {
  statusNode.textContent = '';
  finalSubmitBtn.disabled = true;

  const response = await fetch('../ajax/preview.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ confirm: true })
  });
  const data = await response.json();

  if (!response.ok || !data.success) {
    statusNode.textContent = data.message || 'Final submission failed.';
    statusNode.style.color = '#b42318';
    finalSubmitBtn.disabled = false;
    return;
  }

  statusNode.textContent = data.message || 'Preview submitted successfully. Redirecting to fee payment...';
  statusNode.style.color = '#0a7a35';
  window.location.href = 'step4_fee_payment.php';
});

loadPreview().catch(() => null);
</script>
</body>
</html>
