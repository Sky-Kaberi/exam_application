<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
$db = getDb();

$paymentStatusStmt = $db->prepare('SELECT payment_status FROM applicants WHERE id = :id LIMIT 1');
$paymentStatusStmt->execute(['id' => $applicant['id']]);
$paymentStatus = (string) ($paymentStatusStmt->fetchColumn() ?: 'not_submitted');

// New payment-verification gate: direct URL access is blocked until an admin marks payment as paid.
if ($paymentStatus !== 'paid') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirmation Pending</title>
        <style>
            body { font-family: Arial, sans-serif; background:#eef2f7; margin:0; padding:24px; color:#1f2937; }
            .card { max-width:720px; margin:50px auto; background:#fff; border-radius:12px; padding:24px; box-shadow:0 10px 24px rgba(0,0,0,.08); }
            .alert { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:8px; padding:14px; margin:12px 0 18px; }
            a { display:inline-block; padding:10px 14px; border-radius:8px; background:#FFA500; color:#1f2937; text-decoration:none; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Confirmation Receipt Pending</h1>
            <div class="alert">Once your payment is verified you will be able to view & download the confirmation receipt.</div>
            <a href="step4_fee_payment.php">Back to Fee Payment</a>
            <a href="change_password.php?back=step4_fee_payment.php">Change Password</a>
        </div>
    </body>
    </html>
    <?php
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
            --border: #e8b45b;
            --header-bg: #fff3d8;
            --paper-bg: #ffffff;
            --panel-bg: #fbfcfe;
        }

        * { box-sizing: border-box; }
        html, body { max-width: 100%; overflow-x: hidden; }
        img { max-width: 100%; height: auto; }
        .page-wrap { width: 100%; }
        h1, h2, h3, p, small, label, a, button { overflow-wrap: anywhere; }

        body {
            margin: 0;
            padding: 20px;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: #fff8ec;
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
            border: 1px solid #c97800;
            border-radius: 6px;
            text-decoration: none;
            color: #1f2937;
            background: #FFA500;
            font-size: 14px;
            cursor: pointer;
        }

        .btn.secondary {
            background: #6b7280;
            border-color: #6b7280;
            color: #fff;
        }

        .document {
            background: var(--paper-bg);
            border: 1px solid var(--border);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
            padding: 22px;
            border-radius: 8px;
        }

        .doc-header {
            border: 1px solid var(--border);
            padding: 14px 16px;
            background: var(--header-bg);
            margin-bottom: 14px;
        }

        .board-header {
            display: grid;
            grid-template-columns: 74px 1fr 74px;
            align-items: center;
            gap: 14px;
            text-align: center;
        }

        .board-logo {
            width: 68px;
            height: 68px;
            object-fit: contain;
            justify-self: center;
        }

        .doc-title {
            text-align: center;
            margin-top: 10px;
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
            background: #fff3d8;
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
            background: #fff8ec;
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
            background: #fff8ec;
        }

        .candidate-declaration {
            border: 1px solid var(--border);
            background: #fffdf7;
            margin-top: 14px;
            padding: 14px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .candidate-declaration h3 {
            margin: 0 0 8px;
            font-size: 16px;
        }

        .candidate-declaration p {
            margin: 0 0 8px;
            line-height: 1.45;
            font-size: 14px;
        }

        .signature-block {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
            text-align: center;
        }

        .signature-box {
            min-width: 220px;
        }

        .signature-box img {
            max-width: 180px;
            max-height: 60px;
            object-fit: contain;
            display: block;
            margin: 0 auto 4px;
        }

        .signature-line {
            border-top: 1px solid var(--ink);
            padding-top: 5px;
            font-weight: 700;
        }

        .doc-footer {
            border-top: 1px solid var(--border);
            margin-top: 16px;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            color: #374151;
            font-size: 13px;
            font-weight: 600;
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

        @media (max-width: 600px) {
            .doc-header {
                padding: 10px;
            }

            .board-header {
                grid-template-columns: 36px minmax(0, 1fr) 36px;
                gap: 6px;
            }

            .board-logo {
                width: 36px;
                height: 36px;
            }

            .doc-header h1 {
                font-size: clamp(12px, 3.8vw, 15px);
                line-height: 1.15;
                overflow-wrap: normal;
            }

            .doc-header h2 {
                font-size: 13px;
            }

            .doc-header p {
                font-size: 12px;
            }
        }

        @media print {
            :root {
                --border: #d89a21;
                --header-bg: #fff3d8;
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
            .candidate-declaration,
            .doc-footer,
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
        <a href="change_password.php?back=step5_confirmation.php" class="btn secondary">Change Password</a>
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

const BOARD_LOGO_URL = 'https://upload.wikimedia.org/wikipedia/en/thumb/4/46/West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg/250px-West_Bengal_Joint_Entrance_Examinations_Board_Logo.svg.png';
const HEADER_EXAM_NAME = 'JEMPAS(PG) - 2025';
const FOOTER_EXAM_NAME = 'JEMAS(PG) - 2025';
const DECLARATION_TEXTS = [
    'I hereby declare that the information furnished in this application is true and correct to the best of my knowledge and belief.',
    'I understand that if any information is found incorrect at any stage, my candidature may be cancelled.'
];

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
    return `
        <header class="doc-header">
            <div class="board-header">
                <img class="board-logo" src="${BOARD_LOGO_URL}" alt="West Bengal Joint Entrance Examinations Board Logo">
                <div>
                    <h1>West Bengal Joint Entrance Examinations Board</h1>
                    <h2>${escapeHtml(HEADER_EXAM_NAME)}</h2>
                </div>
                <img class="board-logo" src="${BOARD_LOGO_URL}" alt="West Bengal Joint Entrance Examinations Board Logo">
            </div>
            <div class="doc-title">
                <h2>Confirmation Receipt</h2>
                <p>Application Number: <strong>${escapeHtml(step1?.application_id)}</strong></p>
            </div>
        </header>`;
}

function formatDownloadDate(date = new Date()) {
    return date.toLocaleString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function buildDeclaration(step1, images) {
    const safeSignaturePath = images?.signature_path ? `../public/${String(images.signature_path).replace(/^\/+/, '')}` : '';
    const signatureImage = safeSignaturePath
        ? `<img src="${safeSignaturePath}" alt="Candidate Signature" onerror="this.remove();">`
        : '';

    return `
        <section class="candidate-declaration">
            <h3>Declaration Agreed by Candidate</h3>
            ${DECLARATION_TEXTS.map(text => `<p>${escapeHtml(text)}</p>`).join('')}
            <div class="signature-block">
                <div class="signature-box">
                    ${signatureImage}
                    <div class="signature-line">Signature</div>
                    <div>${escapeHtml(step1?.candidate_name)}</div>
                </div>
            </div>
        </section>`;
}

function buildFooter() {
    return `
        <footer class="doc-footer">
            <span>Downloading Date:${escapeHtml(formatDownloadDate())}</span>
            <span>${escapeHtml(FOOTER_EXAM_NAME)}</span>
        </footer>`;
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
                ['SBI Collect Reference Number', d.step1?.transaction_reference],
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
        ${buildDeclaration(d.step1, d.images)}
        ${buildFooter()}
    `;
}

document.getElementById('printBtn').addEventListener('click', () => window.print());

loadConfirmation().catch(() => {
    confirmationStatus.textContent = 'Unable to load confirmation page.';
});
</script>
</body>
</html>
