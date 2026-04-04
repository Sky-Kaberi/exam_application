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
    <title>Confirmation Page</title>
    <style>
        :root {
            --ink: #1a1a1a;
            --muted: #4b5563;
            --border: #cfd6df;
            --header-bg: #f1f5f9;
            --paper-bg: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 20px;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: #eef2f7;
        }
        .page-wrap {
            max-width: 960px;
            margin: 0 auto;
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .btn {
            padding: 10px 14px;
            border: 1px solid #1f2937;
            border-radius: 6px;
            text-decoration: none;
            color: #ffffff;
            background: #1f2937;
            font-size: 14px;
            cursor: pointer;
        }
        .btn.secondary {
            background: #6b7280;
            border-color: #6b7280;
        }
        .document {
            background: var(--paper-bg);
            border: 1px solid var(--border);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
            padding: 22px;
        }
        .doc-header {
            text-align: center;
            border: 1px solid var(--border);
            padding: 14px;
            background: var(--header-bg);
        }
        .doc-header h1,
        .doc-header h2,
        .doc-header p { margin: 4px 0; }
        .doc-header h1 { font-size: 21px; letter-spacing: 0.2px; }
        .doc-header h2 { font-size: 18px; }
        .doc-header p { color: var(--muted); font-size: 14px; }

        .top-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 14px;
            align-items: start;
        }
        .summary-card,
        .media-card,
        .section,
        .note-block {
            border: 1px solid var(--border);
            padding: 12px;
        }
        .section { margin-top: 14px; }
        .section h3,
        .summary-card h3,
        .media-card h3 { margin: 0 0 10px 0; font-size: 16px; }

        .kv-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0;
            border: 1px solid var(--border);
            border-bottom: none;
        }
        .kv-item {
            display: grid;
            grid-template-columns: 170px 1fr;
            border-bottom: 1px solid var(--border);
            min-height: 38px;
        }
        .kv-item:nth-child(odd) { border-right: 1px solid var(--border); }
        .kv-key,
        .kv-value { padding: 9px 10px; font-size: 14px; }
        .kv-key { background: #f8fafc; color: #374151; font-weight: 600; border-right: 1px solid var(--border); }
        .kv-value { font-weight: 600; word-break: break-word; }

        .media-stack {
            display: grid;
            gap: 10px;
        }
        .media-box {
            border: 1px solid var(--border);
            padding: 8px;
            text-align: center;
            min-height: 130px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
        }
        .media-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }
        .media-box img {
            width: 100%;
            height: 95px;
            object-fit: contain;
            object-position: center;
        }
        .media-missing {
            color: #6b7280;
            font-size: 13px;
            border: 1px dashed var(--border);
            padding: 20px 8px;
        }
        .section-title {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-bottom: none;
            padding: 10px;
            font-size: 15px;
            font-weight: 700;
        }
        .note-block {
            margin-top: 14px;
            background: #f8fafc;
        }
        .note-block ul { margin: 8px 0 0 20px; padding: 0; }
        .note-block li { margin-bottom: 6px; font-size: 14px; }
        .status {
            margin-top: 10px;
            font-size: 14px;
            color: #b42318;
        }

        @page { size: A4; margin: 12mm; }
        @media (max-width: 860px) {
            .top-grid { grid-template-columns: 1fr; }
            .kv-grid { grid-template-columns: 1fr; }
            .kv-item { grid-template-columns: 140px 1fr; }
            .kv-item:nth-child(odd) { border-right: none; }
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar, .status { display: none !important; }
            .document { box-shadow: none; border: none; padding: 0; }
            .doc-header, .summary-card, .media-card, .section, .note-block, .kv-grid, .section-title { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="toolbar">
        <a href="step4_fee_payment.php" class="btn secondary">Back to Fee Payment</a>
        <a href="../ajax/logout.php" class="btn secondary">Logout</a>
        <button id="printBtn" class="btn">Print / Download</button>
    </div>

    <div class="document" id="confirmationRoot" aria-live="polite"></div>
    <div class="status" id="confirmationStatus"></div>
</div>

<script>
const confirmationRoot = document.getElementById('confirmationRoot');
const confirmationStatus = document.getElementById('confirmationStatus');

function value(v) {
  return (v === null || v === undefined || v === '') ? '-' : String(v);
}

function formatFee(v) {
  return Number(v) > 0 ? `INR ${Number(v).toLocaleString('en-IN')}/-` : '-';
}

function escapeHtml(v) {
  return value(v)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function countSelectedPapers(courses) {
  let count = 0;
  if (courses?.course_group_1) count += 1;
  if (courses?.course_group_2) count += 1;
  return count;
}

function kvItem(label, itemValue) {
  return `
    <div class="kv-item">
      <div class="kv-key">${escapeHtml(label)}</div>
      <div class="kv-value">${escapeHtml(itemValue)}</div>
    </div>`;
}

function kvSection(title, fields) {
  const rows = fields.map(([label, rowValue]) => kvItem(label, rowValue)).join('');
  return `
    <section class="section">
      <div class="section-title">${escapeHtml(title)}</div>
      <div class="kv-grid">${rows}</div>
    </section>`;
}

function imageBox(label, rawPath, altText) {
  if (!rawPath) {
    return `<div class="media-box"><div class="media-label">${escapeHtml(label)}</div><div class="media-missing">Image not available</div></div>`;
  }

  const safePath = `../public/${String(rawPath).replace(/^\/+/, '')}`;
  return `<div class="media-box"><div class="media-label">${escapeHtml(label)}</div><img src="${safePath}" alt="${escapeHtml(altText)}" onerror="this.replaceWith(Object.assign(document.createElement('div'), {className:'media-missing', textContent:'Image not available'}));"></div>`;
}

function buildHeader(step1, courses, confirmationDateTime) {
  const sessionYear = (confirmationDateTime || '').slice(0, 4) || 'N/A';
  return `
    <header class="doc-header">
      <h1>National Examination Board</h1>
      <p>Entrance Examination Application</p>
      <p>Session / Year: ${escapeHtml(sessionYear)}</p>
      <h2>Confirmation Page</h2>
      <p>Application Number: <strong>${escapeHtml(step1?.application_id)}</strong></p>
      <p>Exam City: <strong>${escapeHtml(courses?.exam_city)}</strong></p>
    </header>`;
}

async function loadConfirmation() {
  const response = await fetch(`../ajax/confirmation.php?t=${Date.now()}`);
  const data = await response.json();

  if (!response.ok || !data.success) {
    confirmationStatus.textContent = data.message || 'Unable to load confirmation page.';
    confirmationRoot.innerHTML = '';
    return;
  }

  const d = data.data || {};
  const totalPapers = countSelectedPapers(d.courses);

  confirmationRoot.innerHTML = `
    ${buildHeader(d.step1, d.courses, d.confirmation_datetime)}

    <section class="top-grid">
      <div class="summary-card">
        <h3>Application Summary</h3>
        <div class="kv-grid">
          ${kvItem('Application Number', d.step1?.application_id)}
          ${kvItem('Candidate Name', d.step1?.candidate_name)}
          ${kvItem("Father's Name", d.step1?.father_name)}
          ${kvItem("Mother's Name", d.step1?.mother_name)}
          ${kvItem('Date of Birth', d.step1?.date_of_birth)}
          ${kvItem('Gender', d.step1?.gender)}
          ${kvItem('Mobile Number', d.step1?.mobile_no)}
          ${kvItem('Email Address', d.step1?.email_id)}
          ${kvItem('Category', d.basic?.category)}
          ${kvItem('Domicile', d.basic?.domicile)}
        </div>
      </div>

      <aside class="media-card" aria-label="Candidate images">
        <h3>Candidate Images</h3>
        <div class="media-stack">
          ${imageBox('Candidate Photograph', d.images?.photo_path, 'Candidate Photograph')}
          ${imageBox('Candidate Signature', d.images?.signature_path, 'Candidate Signature')}
        </div>
      </aside>
    </section>

    ${kvSection('Course / Paper Details', [
      ['Group-1 Selected Course', d.courses?.course_group_1],
      ['Group-2 Selected Course', d.courses?.course_group_2],
      ['Total Papers Selected', totalPapers],
      ['Application Fee Amount', formatFee(d.courses?.application_fee)]
    ])}

    ${kvSection('Payment Details', [
      ['Payment Status', d.step1?.payment_status],
      ['Payment Mode', d.step1?.payment_mode],
      ['Transaction Reference', d.step1?.transaction_reference],
      ['Payment Date & Time', d.step1?.payment_datetime],
      ['Amount Paid', formatFee(d.step1?.payment_amount)],
      ['Acknowledgement Generated On', d.confirmation_datetime]
    ])}

    <section class="note-block">
      <strong>Important Instructions</strong>
      <ul>
        <li>The candidate is advised to keep this confirmation page for future reference.</li>
        <li>This page should be produced at the time of further admission/examination process, if required.</li>
      </ul>
    </section>`;
}

document.getElementById('printBtn').addEventListener('click', () => window.print());
loadConfirmation().catch(() => {
  confirmationStatus.textContent = 'Unable to load confirmation page.';
});
</script>
</body>
</html>
