<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$admin = requireAdminLoginForPage('login.php');
$db = getDb();

$allowedPageSizes = [10, 25, 50, 100];
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = (int) ($_GET['page_size'] ?? 10);
if (!in_array($pageSize, $allowedPageSizes, true)) {
    $pageSize = 10;
}

$filters = [
    'application_id' => trim((string) ($_GET['application_id'] ?? '')),
    'candidate_name' => trim((string) ($_GET['candidate_name'] ?? '')),
    'mobile_no' => trim((string) ($_GET['mobile_no'] ?? '')),
    'email_id' => trim((string) ($_GET['email_id'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'domicile' => trim((string) ($_GET['domicile'] ?? '')),
    'payment_status' => trim((string) ($_GET['payment_status'] ?? '')),
    'application_status' => trim((string) ($_GET['application_status'] ?? '')),
    'course' => trim((string) ($_GET['course'] ?? '')),
    'created_from' => trim((string) ($_GET['created_from'] ?? '')),
    'created_to' => trim((string) ($_GET['created_to'] ?? '')),
];

$conditions = [];
$params = [];

if ($filters['application_id'] !== '') {
    $conditions[] = 'a.application_id LIKE :application_id';
    $params['application_id'] = '%' . $filters['application_id'] . '%';
}
if ($filters['candidate_name'] !== '') {
    $conditions[] = 'a.candidate_name LIKE :candidate_name';
    $params['candidate_name'] = '%' . $filters['candidate_name'] . '%';
}
if ($filters['mobile_no'] !== '') {
    $conditions[] = 'a.mobile_no LIKE :mobile_no';
    $params['mobile_no'] = '%' . $filters['mobile_no'] . '%';
}
if ($filters['email_id'] !== '') {
    $conditions[] = 'a.email_id LIKE :email_id';
    $params['email_id'] = '%' . $filters['email_id'] . '%';
}
if ($filters['category'] !== '') {
    $conditions[] = 'b.category = :category';
    $params['category'] = $filters['category'];
}
if ($filters['domicile'] !== '') {
    $conditions[] = 'b.domicile = :domicile';
    $params['domicile'] = $filters['domicile'];
}
if (in_array($filters['payment_status'], ['paid', 'unpaid'], true)) {
    $conditions[] = 'a.payment_status = :payment_status';
    $params['payment_status'] = $filters['payment_status'];
}
if ($filters['application_status'] !== '') {
    if ($filters['application_status'] === 'draft') {
        $conditions[] = 'p.final_submitted_at IS NULL';
    } elseif ($filters['application_status'] === 'submitted') {
        $conditions[] = 'p.final_submitted_at IS NOT NULL';
    } elseif ($filters['application_status'] === 'payment_submitted') {
        $conditions[] = 'p.payment_final_submitted_at IS NOT NULL';
    }
}
if ($filters['course'] !== '') {
    $conditions[] = '(c.course_group_1 = :course OR c.course_group_2 = :course)';
    $params['course'] = $filters['course'];
}
if ($filters['created_from'] !== '') {
    $conditions[] = 'DATE(a.created_at) >= :created_from';
    $params['created_from'] = $filters['created_from'];
}
if ($filters['created_to'] !== '') {
    $conditions[] = 'DATE(a.created_at) <= :created_to';
    $params['created_to'] = $filters['created_to'];
}

$whereSql = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$countSql = "SELECT COUNT(*)
FROM applicants a
LEFT JOIN applicant_step2_basic b ON b.applicant_id = a.id
LEFT JOIN applicant_step2_courses c ON c.applicant_id = a.id
LEFT JOIN applicant_progress p ON p.applicant_id = a.id
{$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $pageSize));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $pageSize;

$listSql = "SELECT a.id, a.application_id, a.candidate_name, a.mobile_no, a.email_id, a.date_of_birth,
       a.payment_status, a.created_at,
       b.category, b.domicile,
       c.course_group_1, c.course_group_2,
       p.final_submitted_at, p.payment_final_submitted_at
FROM applicants a
LEFT JOIN applicant_step2_basic b ON b.applicant_id = a.id
LEFT JOIN applicant_step2_courses c ON c.applicant_id = a.id
LEFT JOIN applicant_progress p ON p.applicant_id = a.id
{$whereSql}
ORDER BY a.created_at DESC
LIMIT :limit OFFSET :offset";
$listStmt = $db->prepare($listSql);
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value);
}
$listStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$candidates = $listStmt->fetchAll();

function applicationStatusLabel(array $row): string
{
    if (!empty($row['payment_final_submitted_at'])) {
        return 'Payment Submitted';
    }
    if (!empty($row['final_submitted_at'])) {
        return 'Submitted';
    }
    return 'Draft';
}

$queryWithoutPage = $_GET;
unset($queryWithoutPage['page']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">Admin Dashboard</span>
        <div class="ms-auto text-white">
            Welcome, <?= htmlspecialchars((string) ($admin['full_name'] ?: $admin['username']), ENT_QUOTES, 'UTF-8') ?> |
            <a class="text-white" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid pb-4">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-12 col-md-3"><input class="form-control" name="application_id" placeholder="Application ID" value="<?= htmlspecialchars($filters['application_id'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12 col-md-3"><input class="form-control" name="candidate_name" placeholder="Candidate Name" value="<?= htmlspecialchars($filters['candidate_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12 col-md-3"><input class="form-control" name="mobile_no" placeholder="Mobile No." value="<?= htmlspecialchars($filters['mobile_no'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12 col-md-3"><input type="email" class="form-control" name="email_id" placeholder="Email" value="<?= htmlspecialchars($filters['email_id'], ENT_QUOTES, 'UTF-8') ?>"></div>

                <div class="col-12 col-md-2"><input class="form-control" name="category" placeholder="Category" value="<?= htmlspecialchars($filters['category'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12 col-md-2">
                    <select class="form-select" name="domicile">
                        <option value="">Domicile</option>
                        <option value="West Bengal" <?= $filters['domicile'] === 'West Bengal' ? 'selected' : '' ?>>West Bengal</option>
                        <option value="Others" <?= $filters['domicile'] === 'Others' ? 'selected' : '' ?>>Others</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <select class="form-select" name="payment_status">
                        <option value="">Payment Status</option>
                        <option value="paid" <?= $filters['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="unpaid" <?= $filters['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <select class="form-select" name="application_status">
                        <option value="">Application Status</option>
                        <option value="draft" <?= $filters['application_status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="submitted" <?= $filters['application_status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="payment_submitted" <?= $filters['application_status'] === 'payment_submitted' ? 'selected' : '' ?>>Payment Submitted</option>
                    </select>
                </div>
                <div class="col-12 col-md-2"><input class="form-control" name="course" placeholder="Course / Group" value="<?= htmlspecialchars($filters['course'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12 col-md-2"><input type="date" class="form-control" name="created_from" value="<?= htmlspecialchars($filters['created_from'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12 col-md-2"><input type="date" class="form-control" name="created_to" value="<?= htmlspecialchars($filters['created_to'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12 col-md-2">
                    <select class="form-select" name="page_size">
                        <?php foreach ($allowedPageSizes as $size): ?>
                            <option value="<?= $size ?>" <?= $pageSize === $size ? 'selected' : '' ?>><?= $size ?> / page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid"><button class="btn btn-primary" type="submit">Search</button></div>
                <div class="col-12 col-md-2 d-grid"><a class="btn btn-outline-secondary" href="dashboard.php">Reset Filters</a></div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Candidates</h2>
        <div class="text-muted">Total matching records: <strong><?= number_format($totalRecords) ?></strong></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Application ID</th>
                    <th>Candidate Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>DOB</th>
                    <th>Category</th>
                    <th>Domicile</th>
                    <th>Course / Group</th>
                    <th>Payment</th>
                    <th>Application Status</th>
                    <th>Created Date</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($candidates === []): ?>
                    <tr><td colspan="12" class="text-center py-4 text-muted">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($candidates as $row): ?>
                        <?php $courseText = trim((string) ($row['course_group_1'] ?: ''));
                        if (trim((string) ($row['course_group_2'] ?: '')) !== '') {
                            $courseText .= ($courseText !== '' ? ' | ' : '') . $row['course_group_2'];
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $row['application_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['candidate_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['mobile_no'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['email_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['date_of_birth'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['category'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['domicile'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($courseText !== '' ? $courseText : '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge text-bg-<?= $row['payment_status'] === 'paid' ? 'success' : 'secondary' ?>"><?= htmlspecialchars((string) ucfirst((string) $row['payment_status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars(applicationStatusLabel($row), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="candidate_details.php?id=<?= (int) $row['id'] ?>">View More Details</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white">
            <nav aria-label="Candidate pagination">
                <ul class="pagination mb-0 flex-wrap">
                    <?php
                    for ($p = 1; $p <= $totalPages; $p++):
                        $query = $queryWithoutPage;
                        $query['page'] = $p;
                        $url = 'dashboard.php?' . http_build_query($query);
                        ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>
</body>
</html>
