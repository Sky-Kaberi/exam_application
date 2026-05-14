<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$admin = requireAdminLoginForPage('login.php');
$db = getDb();

$messages = [];
$errors = [];

function paymentStatusBadgeClass(string $status): string
{
    if ($status === 'paid') {
        return 'success';
    }

    if ($status === 'pending_verification') {
        return 'warning';
    }

    if ($status === 'rejected') {
        return 'danger';
    }

    return 'secondary';
}

function paymentStatusLabel(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicantId = (int) ($_POST['applicant_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $adminNote = trim((string) ($_POST['payment_admin_note'] ?? ''));

    if ($applicantId <= 0 || !in_array($action, ['mark_paid', 'reject'], true)) {
        $errors[] = 'Invalid payment verification request.';
    } elseif ($action === 'reject' && $adminNote === '') {
        $errors[] = 'Admin note / rejection reason is required when rejecting a payment.';
    } else {
        $currentStmt = $db->prepare('SELECT payment_status FROM applicants WHERE id = :id LIMIT 1');
        $currentStmt->execute(['id' => $applicantId]);
        $currentStatus = $currentStmt->fetchColumn();

        if ($currentStatus === false) {
            $errors[] = 'Candidate payment record not found.';
        } elseif ((string) $currentStatus !== 'pending_verification') {
            $errors[] = 'Only pending payment submissions can be accepted or rejected.';
        } elseif ($action === 'mark_paid') {
            // Admin verification is the only place where candidate payments become paid.
            $updateStmt = $db->prepare(
                'UPDATE applicants
                 SET payment_status = :payment_status,
                     payment_verified_at = NOW(),
                     payment_verified_by = :payment_verified_by,
                     payment_admin_note = :payment_admin_note
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'payment_status' => 'paid',
                'payment_verified_by' => $admin['id'],
                'payment_admin_note' => $adminNote !== '' ? $adminNote : null,
                'id' => $applicantId,
            ]);
            $messages[] = 'Payment marked as Paid successfully.';
        } else {
            $updateStmt = $db->prepare(
                'UPDATE applicants
                 SET payment_status = :payment_status,
                     payment_verified_at = NULL,
                     payment_verified_by = NULL,
                     payment_admin_note = :payment_admin_note
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'payment_status' => 'rejected',
                'payment_admin_note' => $adminNote,
                'id' => $applicantId,
            ]);
            $messages[] = 'Payment rejected successfully.';
        }
    }
}

$statusFilter = trim((string) ($_GET['status'] ?? 'pending_verification'));
if (!in_array($statusFilter, ['all', 'not_submitted', 'pending_verification', 'paid', 'rejected'], true)) {
    $statusFilter = 'pending_verification';
}

$conditions = [];
$params = [];
if ($statusFilter !== 'all') {
    $conditions[] = 'a.payment_status = :payment_status';
    $params['payment_status'] = $statusFilter;
}
$whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

$listStmt = $db->prepare(
    "SELECT a.id, a.application_id, a.candidate_name, a.mobile_no, a.email_id,
            a.payment_status, a.payment_amount, a.sbi_reference_no, a.sbi_payment_date,
            a.sbi_receipt_path, a.payment_submitted_at, a.payment_verified_at,
            a.payment_verified_by, a.payment_admin_note,
            u.username AS verified_by_username, u.full_name AS verified_by_name
     FROM applicants a
     LEFT JOIN admin_users u ON u.id = a.payment_verified_by
     {$whereSql}
     ORDER BY CASE WHEN a.payment_status = 'pending_verification' THEN 0 ELSE 1 END,
              a.payment_submitted_at DESC,
              a.created_at DESC
     LIMIT 200"
);
$listStmt->execute($params);
$payments = $listStmt->fetchAll();
$hasActionablePayments = false;
foreach ($payments as $payment) {
    if ((string) $payment['payment_status'] === 'pending_verification') {
        $hasActionablePayments = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Admin Dashboard</a>
        <span class="navbar-text text-white">Payment Verification</span>
        <div class="ms-auto text-white">
            Welcome, <?= htmlspecialchars((string) ($admin['full_name'] ?: $admin['username']), ENT_QUOTES, 'UTF-8') ?> |
            <a class="text-white" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid pb-4">
    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">Payment Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="pending_verification" <?= $statusFilter === 'pending_verification' ? 'selected' : '' ?>>Pending Verification</option>
                        <option value="not_submitted" <?= $statusFilter === 'not_submitted' ? 'selected' : '' ?>>Not Submitted</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid"><button class="btn btn-primary" type="submit">Filter</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Candidate Details</th>
                    <th>SBI Reference No.</th>
                    <th>Payment Date</th>
                    <th>Receipt</th>
                    <th>Status</th>
                    <th>Admin Note</th>
                    <?php if ($hasActionablePayments): ?>
                        <th>Action</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php if ($payments === []): ?>
                    <tr><td colspan="<?= $hasActionablePayments ? 7 : 6 ?>" class="text-center text-muted py-4">No payment records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <div><strong><?= htmlspecialchars((string) $payment['application_id'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                                <div><?= htmlspecialchars((string) $payment['candidate_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars((string) $payment['mobile_no'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars((string) $payment['email_id'], ENT_QUOTES, 'UTF-8') ?></div>
                                <a class="small" href="candidate_details.php?id=<?= (int) $payment['id'] ?>">View candidate details</a>
                            </td>
                            <td><?= htmlspecialchars((string) ($payment['sbi_reference_no'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($payment['sbi_payment_date'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if (!empty($payment['sbi_receipt_path'])): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="../public/<?= htmlspecialchars((string) $payment['sbi_receipt_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">View Receipt</a>
                                <?php else: ?>
                                    <span class="text-muted">Not uploaded</span>
                                <?php endif; ?>
                                <div class="small text-muted mt-1">Submitted: <?= htmlspecialchars((string) ($payment['payment_submitted_at'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= paymentStatusBadgeClass((string) $payment['payment_status']) ?>"><?= htmlspecialchars(paymentStatusLabel((string) $payment['payment_status']), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (!empty($payment['payment_verified_at'])): ?>
                                    <div class="small text-muted mt-1">Verified: <?= htmlspecialchars((string) $payment['payment_verified_at'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="small text-muted">By: <?= htmlspecialchars((string) ($payment['verified_by_name'] ?: $payment['verified_by_username'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= nl2br(htmlspecialchars((string) ($payment['payment_admin_note'] ?: '-'), ENT_QUOTES, 'UTF-8')) ?></td>
                            <?php if ($hasActionablePayments): ?>
                                <td style="min-width: 280px;">
                                    <?php if ((string) $payment['payment_status'] === 'pending_verification'): ?>
                                        <form method="post" class="mb-2">
                                            <input type="hidden" name="applicant_id" value="<?= (int) $payment['id'] ?>">
                                            <textarea class="form-control form-control-sm mb-2" name="payment_admin_note" rows="2" placeholder="Admin note / rejection reason"><?= htmlspecialchars((string) ($payment['payment_admin_note'] ?: ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-success" type="submit" name="action" value="mark_paid">Accept</button>
                                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">Reject</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
