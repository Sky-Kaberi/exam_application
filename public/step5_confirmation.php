<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
$db = getDb();

$paymentStatusStmt = $db->prepare('SELECT payment_status FROM applicants WHERE id = :id LIMIT 1');
$paymentStatusStmt->execute(['id' => $applicant['id']]);
$paymentStatus = (string) ($paymentStatusStmt->fetchColumn() ?: 'unpaid');

$progress = getApplicantProgress($db, (int) $applicant['id']);
if ($paymentStatus !== 'paid' || $progress['payment_final_submitted_at'] === null) {
    header('Location: step4_fee_payment.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation Page</title>
    <style>
        :root {
            --ink: #1f2937;
            --muted: #6b7280;
            --border: #d6dde8;
            --header-bg: #f3f6fb;
            --paper-bg: #ffffff;
            --panel-bg: #fbfcfe;
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
            max-width: 1100px;
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
            border-radius: 8px;
        }

        .doc-header {
            text-align: center;
            border: 1px solid var(--border);
            padding: 14px 16px;
            background: var(--header-bg);
            margin-bottom: 14px;
        }

        .doc-header h1,
        .doc-header h2,
        .doc-header p {
            margin: 4px 0;
        }

        .doc-header h1 {
            font-size: 22px;
            letter-spacing: 0.2px;
        }

        .doc-header h2 {
            font-size: 19px;
        }

        .doc-header p {
            color: #4b5563;
            font-size: 14px;
        }

        .main-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 14px;
            align-items: start;
        }

        .card {
            border: 1px solid var(--border);
            background: #fff;
            min-width: 0;
        }

        .card-header {
            background: #f8fafc;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 15px;
            font-weight: 700;
        }

        .card-body {
            padding: 12px;
        }

        .span-8 { grid-column: span 8; }
        .span-6 { grid-column: span 6; }
        .span-4 { grid-column: span 4; }
        .span-12 { grid-column: span 12; }

        .kv-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border: 1px solid var(--border);
            border-bottom: none;
        }

        .kv-item {
            display: grid;
            grid-template-columns: 170px minmax(0, 1fr);
            min-height: 42px;
            border-bottom: 1px solid var(--border);
        }

        .kv-item:nth-child(odd) {
            border-right: 1px solid var(--border);
        }

        .kv-key,
        .kv-value {
            padding: 10px;
            font-size: 14px;
            word-break: break-word;
        }

        .kv-key {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            border-right: 1px solid var(--border);
        }

        .kv-value {
            font-weight: 600;
            background: #fff;
        }

        .media-stack {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .media-box {
            border: 1px solid var(--border);
            background: var(--panel-bg);
            padding: 10px;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
        }

        .media-label {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            letter-spacing: 0.25px;
            text-transform: uppercase;
            margin-bottom: 8px;
            text-align: center;
        }

        .media-preview {
            flex: 1;
            border: 1px dashed var(--border);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 170px;
            overflow: hidden;
        }

        .media-preview img {
            max-width: 100%;
            max-height: 160px;
            object-fit: contain;
            display: block;
        }

        .media-missing {
            color: #6b7280;
            font-size: 13px;
            text-align: center;
            padding: 16px;
        }

        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .address-block {
            border: 1px solid var(--border);
            background: var(--panel-bg);
            padding: 12px;
            min-height: 150px;
        }

        .address-block h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
            color: #111827;
        }

        .address-line {
            margin: 0 0 6px 0;
            line-height: 1.45;
            font-weight: 600;
            word-break: break-word;
        }

        .instructions {
            background: #f8fafc;
        }

        .instructions ul {
            margin: 0;
            padding-left: 20px;
        }

        .instructions li {
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.45;
        }

        .status {
            margin-top: 10px;
            font-size: 14px;
            color: #b42318;
        }

        @page {
            size: A4;
            margin: 10mm;
        }

        @media (max-width: 900px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .span-8,
            .span-6,
            .span-4,
            .span-12 {
                grid-column: span 1;
            }

            .kv-grid {
                grid-template-columns: 1fr;
            }

            .kv-item {
                grid-template-columns: 140px minmax(0, 1fr);
            }

            .kv-item:nth-child(odd) {
                border-right: none;
            }

            .media-stack,
            .address-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            :root {
                --border: #bcc7d6;
                --header-bg: #eef3fb;
                --panel-bg: #fcfdff;
            }

            html, body {
                width: 210mm;
            }

            body {
                margin: 0;
                padding: 0;
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page-wrap {
                max-width: none;
                width: 100%;
                margin: 0;
            }

            .toolbar,
            .status {
                display: none !important;
            }

            .document {
                box-shadow: none;
                border: 1px solid var(--border);
                border-radius: 0;
                padding: 10px;
            }

            .doc-header,
            .card,
            .address-block,
            .media-box,
            .kv-grid,
            .kv-item {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .main-grid {
                grid-template-columns: repeat(12, minmax(0, 1fr));
                gap: 10px;
            }

            .span-8 { grid-column: span 8; }
            .span-6 { grid-column: span 6; }
            .span-4 { grid-column: span 4; }
            .span-12 { grid-column: span 12; }

            .media-stack {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .media-box {
                min-height: 185px;
            }

            .media-preview {
                min-height: 140px;
            }

            .media-preview img {
                max-height: 130px;
            }

            .address-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .address-block {
                min-height: 120px;
            }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="toolbar">
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

function kvGrid(fields) {
    return fields.map(([label, rowValue]) => kvItem(label, rowValue)).join('');
}

function sectionCard(title, bodyHtml, spanClass = 'span-12') {
    return `
        <section class="card ${spanClass}">
            <div class="card-header">${escapeHtml(title)}</div>
            <div class="card-body">${bodyHtml}</div>
        </section>`;
}

function clean(v) {
    return (v === null || v === undefined) ? '' : String(v).trim();
}

function joinParts(parts) {
    const values = parts.map(clean).filter(Boolean);
    return values.length ? values.join(', ') : '';
}

function formatAddressLines(address, prefix) {
    const lines = [
        clean(address?.[`${prefix}_premises`]),
        clean(address?.[`${prefix}_sub_locality`]),
        joinParts([
            address?.[`${prefix}_locality`],
            address?.[`${prefix}_district`]
        ]),
        joinParts([
            address?.[`${prefix}_state`],
            address?.[`${prefix}_country`]
        ]),
        clean(address?.[`${prefix}_pin_code`]) ? `PIN: ${clean(address?.[`${prefix}_pin_code`])}` : ''
    ].filter(Boolean);

    return lines.length ? lines : ['-'];
}

function addressBlock(title, lines) {
    return `
        <article class="address-block">
            <h4>${escapeHtml(title)}</h4>
            ${lines.map(line => `<p class="address-line">${escapeHtml(line)}</p>`).join('')}
        </article>`;
}

function imageBox(label, rawPath, altText) {
    if (!rawPath) {
        return `
            <div class="media-box">
                <div class="media-label">${escapeHtml(label)}</div>
                <div class="media-preview">
                    <div class="media-missing">Image not available</div>
                </div>
            </div>`;
    }

    const safePath = `../public/${String(rawPath).replace(/^\/+/, '')}`;

    return `
        <div class="media-box">
            <div class="media-label">${escapeHtml(label)}</div>
            <div class="media-preview">
                <img
                    src="${safePath}"
                    alt="${escapeHtml(altText)}"
                    onerror="this.parentNode.innerHTML='<div class=&quot;media-missing&quot;>Image not available</div>';"
                >
            </div>
        </div>`;
}

function buildHeader(step1, confirmationDateTime) {
    const sessionYear = '2025';
    return `
        <header class="doc-header">
            <h1>National Examination Board</h1>
            <p>Entrance Examination Application</p>
            <p>Session / Year: ${escapeHtml(sessionYear)}</p>
            <h2>Confirmation Page</h2>
            <p>Application Number: <strong>${escapeHtml(step1?.application_id)}</strong></p>
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

    const summaryHtml = `
        <div class="kv-grid">
            ${kvGrid([
                ['Application Number', d.step1?.application_id],
                ['Candidate Name', d.step1?.candidate_name],
                ["Father's Name", d.step1?.father_name],
                ["Mother's Name", d.step1?.mother_name],
                ['Date of Birth', d.step1?.date_of_birth],
                ['Gender', d.step1?.gender],
                ['Mobile Number', d.step1?.mobile_no],
                ['Email Address', d.step1?.email_id],
                ['Category', d.basic?.category],
                ['Domicile', d.basic?.domicile]
            ])}
        </div>`;

    const imagesHtml = `
        <div class="media-stack">
            ${imageBox('Candidate Photograph', d.images?.photo_path, 'Candidate Photograph')}
            ${imageBox('Candidate Signature', d.images?.signature_path, 'Candidate Signature')}
        </div>`;

    const courseHtml = `
        <div class="kv-grid">
            ${kvGrid([
                ['Group-1 Selected Course', d.courses?.course_group_1],
                ['Group-2 Selected Course', d.courses?.course_group_2],
                ['Total Papers Selected', totalPapers],
                ['Application Fee Amount', formatFee(d.courses?.application_fee)]
            ])}
        </div>`;

    const paymentHtml = `
        <div class="kv-grid">
            ${kvGrid([
                ['Payment Status', d.step1?.payment_status],
                ['Payment Mode', d.step1?.payment_mode],
                ['Transaction Reference', d.step1?.transaction_reference],
                ['Payment Date & Time', d.step1?.payment_datetime],
                ['Amount Paid', formatFee(d.step1?.payment_amount)],
                ['Acknowledgement Generated On', d.confirmation_datetime]
            ])}
        </div>`;

    const addressHtml = `
        <div class="address-grid">
            ${addressBlock('Correspondence Address', formatAddressLines(d.address, 'corr'))}
            ${addressBlock('Permanent Address', formatAddressLines(d.address, 'perm'))}
        </div>`;

    const instructionsHtml = `
        <div class="instructions">
            <ul>
                <li>The candidate is advised to keep this confirmation page for future reference.</li>
                <li>This page should be produced at the time of further admission/examination process, if required.</li>
            </ul>
        </div>`;

    confirmationRoot.innerHTML = `
        ${buildHeader(d.step1, d.confirmation_datetime)}

        <div class="main-grid">
            ${sectionCard('Application Summary', summaryHtml, 'span-8')}
            ${sectionCard('Candidate Images', imagesHtml, 'span-4')}
            ${sectionCard('Course / Paper Details', courseHtml, 'span-6')}
            ${sectionCard('Payment Details', paymentHtml, 'span-6')}
            ${sectionCard('Address Details', addressHtml, 'span-12')}
            ${sectionCard('Important Instructions', instructionsHtml, 'span-12')}
        </div>
    `;
}

document.getElementById('printBtn').addEventListener('click', () => window.print());

loadConfirmation().catch(() => {
    confirmationStatus.textContent = 'Unable to load confirmation page.';
});
</script>
</body>
</html>
