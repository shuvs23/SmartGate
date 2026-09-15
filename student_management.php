<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$allowed_roles = ['super_admin', 'MIS'];

if (!in_array($_SESSION['role'], $allowed_roles, true)) {
    header("Location: admin.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search     = trim($_GET['search'] ?? '');
$program    = trim($_GET['program'] ?? '');
$year_level = trim($_GET['year_level'] ?? '');
$section    = trim($_GET['section'] ?? '');
$status     = trim($_GET['status'] ?? '');
$account    = trim($_GET['account'] ?? '');


/*
|--------------------------------------------------------------------------
| BUILD FILTER
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = '';

if ($search !== '') {

    $where[] = "(
        s.student_id LIKE ?
        OR s.full_name LIKE ?
        OR s.email LIKE ?
        OR s.parent_email LIKE ?
    )";

    $search_param = "%{$search}%";

    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;

    $types .= "ssss";
}

if ($program !== '') {
    $where[] = "s.program = ?";
    $params[] = $program;
    $types .= "s";
}

if ($year_level !== '') {
    $where[] = "s.year_level = ?";
    $params[] = $year_level;
    $types .= "s";
}

if ($section !== '') {
    $where[] = "s.section = ?";
    $params[] = $section;
    $types .= "s";
}

if ($status === 'IN' || $status === 'OUT') {
    $where[] = "s.current_status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($account === 'ACTIVE') {
    $where[] = "s.is_active = 1";
}

if ($account === 'INACTIVE') {
    $where[] = "s.is_active = 0";
}

$where_sql = '';

if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function bindDynamicParams($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return;
    }

    $refs = [];

    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }

    array_unshift($refs, $types);

    call_user_func_array(
        [$stmt, 'bind_param'],
        $refs
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT DATA
|--------------------------------------------------------------------------
*/

$students = [];

$sql = "
    SELECT
        s.id,
        s.student_id,
        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.email,
        s.parent_email,
        s.qr_code,
        s.photo,
        s.current_status,
        s.is_active,
        s.created_at,
        s.updated_at
    FROM students s
    $where_sql
    ORDER BY s.full_name ASC
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    bindDynamicParams($stmt, $types, $params);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$total_students = (int)($conn->query("
    SELECT COUNT(*)
    FROM students
")->fetch_row()[0] ?? 0);

$active_students = (int)($conn->query("
    SELECT COUNT(*)
    FROM students
    WHERE is_active = 1
")->fetch_row()[0] ?? 0);

$inactive_students = (int)($conn->query("
    SELECT COUNT(*)
    FROM students
    WHERE is_active = 0
")->fetch_row()[0] ?? 0);

$current_in = (int)($conn->query("
    SELECT COUNT(*)
    FROM students
    WHERE current_status = 'IN'
      AND is_active = 1
")->fetch_row()[0] ?? 0);

$current_out = (int)($conn->query("
    SELECT COUNT(*)
    FROM students
    WHERE current_status = 'OUT'
      AND is_active = 1
")->fetch_row()[0] ?? 0);

$qr_assigned = (int)($conn->query("
    SELECT COUNT(*)
    FROM students
    WHERE qr_code IS NOT NULL
      AND qr_code <> ''
")->fetch_row()[0] ?? 0);

$qr_missing = max(
    0,
    $total_students - $qr_assigned
);


/*
|--------------------------------------------------------------------------
| PROGRAMS
|--------------------------------------------------------------------------
*/

$programs = [];

$result = $conn->query("
    SELECT DISTINCT program
    FROM students
    WHERE program IS NOT NULL
      AND program <> ''
    ORDER BY program ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $programs[] = $row['program'];
    }
}


/*
|--------------------------------------------------------------------------
| YEAR LEVELS
|--------------------------------------------------------------------------
*/

$year_levels = [];

$result = $conn->query("
    SELECT DISTINCT year_level
    FROM students
    WHERE year_level IS NOT NULL
      AND year_level <> ''
    ORDER BY year_level ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $year_levels[] = $row['year_level'];
    }
}


/*
|--------------------------------------------------------------------------
| SECTIONS
|--------------------------------------------------------------------------
*/

$sections = [];

$result = $conn->query("
    SELECT DISTINCT section
    FROM students
    WHERE section IS NOT NULL
      AND section <> ''
    ORDER BY section ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $sections[] = $row['section'];
    }
}


/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'csv'
) {

    header(
        'Content-Type: text/csv; charset=utf-8'
    );

    header(
        'Content-Disposition: attachment; filename=smartgate_students_' .
        date('Y-m-d_H-i-s') .
        '.csv'
    );

    $output = fopen(
        'php://output',
        'w'
    );

    fputcsv(
        $output,
        [
            'Student ID',
            'Full Name',
            'Program',
            'Year Level',
            'Section',
            'Student Email',
            'Parent Email',
            'QR Code',
            'Current Status',
            'Account Status',
            'Created At'
        ]
    );

    foreach ($students as $student) {

        fputcsv(
            $output,
            [
                $student['student_id'],
                $student['full_name'],
                $student['program'],
                $student['year_level'],
                $student['section'],
                $student['email'],
                $student['parent_email'],
                $student['qr_code'],
                $student['current_status'],
                $student['is_active']
                    ? 'ACTIVE'
                    : 'INACTIVE',
                $student['created_at']
            ]
        );
    }

    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    SmartGate Student Management
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7fb;

    color: #1e293b;
}

.page {
    max-width: 1600px;

    margin: auto;

    padding: 25px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;
}

.header h1 {
    margin: 0;

    font-size: 28px;
}

.header p {
    margin: 6px 0 0;

    color: #64748b;
}

.actions {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.btn {
    border: none;

    padding: 11px 16px;

    border-radius: 9px;

    font-weight: 700;

    text-decoration: none;

    cursor: pointer;

    display: inline-block;
}

.btn-primary {
    background: #2563eb;

    color: white;
}

.btn-success {
    background: #15803d;

    color: white;
}

.btn-dark {
    background: #0f172a;

    color: white;
}

.btn-secondary {
    background: #e2e8f0;

    color: #1e293b;
}

.btn-warning {
    background: #f59e0b;

    color: #111827;
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

.stats {
    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 14px;

    margin-bottom: 22px;
}

.stat {
    background: white;

    border-radius: 14px;

    padding: 18px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .06);
}

.stat-label {
    font-size: 12px;

    color: #64748b;

    font-weight: 700;
}

.stat-value {
    font-size: 28px;

    font-weight: 800;

    margin-top: 7px;
}


/*
|--------------------------------------------------------------------------
| FILTER CARD
|--------------------------------------------------------------------------
*/

.filter-card {
    background: white;

    padding: 20px;

    border-radius: 14px;

    margin-bottom: 22px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .06);
}

.filter-title {
    font-size: 17px;

    font-weight: 800;

    margin-bottom: 15px;
}

.filters {
    display: grid;

    grid-template-columns:
        2fr
        repeat(5, 1fr);

    gap: 10px;
}

.field label {
    display: block;

    font-size: 12px;

    color: #64748b;

    font-weight: 700;

    margin-bottom: 5px;
}

.field input,
.field select {
    width: 100%;

    padding: 10px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    background: white;
}

.filter-buttons {
    display: flex;

    gap: 8px;

    margin-top: 12px;

    flex-wrap: wrap;
}


/*
|--------------------------------------------------------------------------
| TABLE CARD
|--------------------------------------------------------------------------
*/

.card {
    background: white;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .06);
}

.card-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 18px;
}

.card-header h2 {
    margin: 0;

    font-size: 18px;
}

.result-count {
    color: #64748b;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1200px;
}

th {
    text-align: left;

    padding: 12px;

    font-size: 12px;

    color: #64748b;

    border-bottom:
        1px solid #e2e8f0;

    white-space: nowrap;
}

td {
    padding: 12px;

    font-size: 13px;

    border-bottom:
        1px solid #eef2f7;

    vertical-align: middle;
}


/*
|--------------------------------------------------------------------------
| STUDENT
|--------------------------------------------------------------------------
*/

.student-cell {
    display: flex;

    align-items: center;

    gap: 10px;
}

.student-photo {
    width: 44px;

    height: 44px;

    border-radius: 10px;

    object-fit: cover;

    background: #e2e8f0;

    border: 1px solid #cbd5e1;
}

.student-name {
    font-weight: 700;
}

.student-id {
    font-size: 11px;

    color: #64748b;

    margin-top: 2px;
}


/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;
}

.badge-in {
    background: #dcfce7;

    color: #166534;
}

.badge-out {
    background: #fee2e2;

    color: #991b1b;
}

.badge-active {
    background: #dbeafe;

    color: #1d4ed8;
}

.badge-inactive {
    background: #e2e8f0;

    color: #475569;
}

.badge-qr {
    background: #dcfce7;

    color: #166534;
}

.badge-noqr {
    background: #fef3c7;

    color: #92400e;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.row-actions {
    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.action-btn {
    display: inline-block;

    border: 0;

    cursor: pointer;

    font-family: inherit;

    padding: 7px 9px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;

    background: #e2e8f0;

    color: #1e293b;
}

.action-btn.primary {
    background: #dbeafe;

    color: #1d4ed8;
}

.action-btn.success {
    background: #dcfce7;

    color: #166534;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    text-align: center;

    padding: 45px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer-note {
    margin-top: 15px;

    font-size: 12px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media(max-width:1200px) {

    .stats {
        grid-template-columns:
            repeat(3, 1fr);
    }

    .filters {
        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media(max-width:800px) {

    .header {
        flex-direction: column;

        align-items: flex-start;
    }

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .filters {
        grid-template-columns: 1fr;
    }

}

@media(max-width:500px) {

    .page {
        padding: 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    body {
        background: white;
    }

    .page {
        max-width: none;

        padding: 0;
    }

    .actions,
    .filter-card,
    .row-actions {
        display: none !important;
    }

    .stats {
        grid-template-columns:
            repeat(3, 1fr);
    }

    .card,
    .stat {
        box-shadow: none;

        border: 1px solid #ddd;
    }

    table {
        min-width: 0;
    }

    th,
    td {
        font-size: 9px;

        padding: 6px;
    }

    .student-photo {
        display: none;
    }

}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<div class="page">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                Student Management
            </h1>

            <p>
                Manage student accounts,
                QR codes, profiles, and gate status
            </p>

        </div>


        <div class="actions">
            <a
                href="mis_upload.php"
                class="btn btn-primary"
            >
                MIS Student Upload
            </a>

            <a
                href="batch_qr.php"
                class="btn btn-success"
            >
                Batch QR
            </a>

            <a
                href="?<?= htmlspecialchars(
                    http_build_query(
                        array_merge(
                            $_GET,
                            ['export' => 'csv']
                        )
                    )
                ) ?>"
                class="btn btn-dark"
            >
                Export CSV
            </a>

            <button
                onclick="window.print()"
                class="btn btn-secondary"
            >
                Print
            </button>

        </div>

    </div>


    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat">

            <div class="stat-label">
                TOTAL STUDENTS
            </div>

            <div class="stat-value">
                <?= number_format(
                    $total_students
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                ACTIVE
            </div>

            <div class="stat-value">
                <?= number_format(
                    $active_students
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                INACTIVE
            </div>

            <div class="stat-value">
                <?= number_format(
                    $inactive_students
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                CURRENTLY IN
            </div>

            <div class="stat-value">
                <?= number_format(
                    $current_in
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                CURRENTLY OUT
            </div>

            <div class="stat-value">
                <?= number_format(
                    $current_out
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                QR ASSIGNED
            </div>

            <div class="stat-value">
                <?= number_format(
                    $qr_assigned
                ) ?>
            </div>

        </div>

    </div>


    <!-- FILTER -->

    <div class="filter-card">

        <div class="filter-title">
            Search & Filter Students
        </div>


        <form method="GET">

            <div class="filters">


                <div class="field">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="
                            Student ID, name, email...
                        "
                        value="<?= htmlspecialchars(
                            $search
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Program
                    </label>

                    <select name="program">

                        <option value="">
                            All Programs
                        </option>

                        <?php foreach (
                            $programs
                            as $item
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $item
                                ) ?>"
                                <?= $program === $item
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $item
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        Year Level
                    </label>

                    <select name="year_level">

                        <option value="">
                            All Year Levels
                        </option>

                        <?php foreach (
                            $year_levels
                            as $item
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $item
                                ) ?>"
                                <?= $year_level === $item
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $item
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        Section
                    </label>

                    <select name="section">

                        <option value="">
                            All Sections
                        </option>

                        <?php foreach (
                            $sections
                            as $item
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $item
                                ) ?>"
                                <?= $section === $item
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $item
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        Gate Status
                    </label>

                    <select name="status">

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="IN"
                            <?= $status === 'IN'
                                ? 'selected'
                                : '' ?>
                        >
                            Currently IN
                        </option>

                        <option
                            value="OUT"
                            <?= $status === 'OUT'
                                ? 'selected'
                                : '' ?>
                        >
                            Currently OUT
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label>
                        Account
                    </label>

                    <select name="account">

                        <option value="">
                            All Accounts
                        </option>

                        <option
                            value="ACTIVE"
                            <?= $account === 'ACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="INACTIVE"
                            <?= $account === 'INACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

            </div>


            <div class="filter-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply Filters
                </button>


                <a
                    href="student_management.php"
                    class="btn btn-secondary"
                >
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- STUDENT TABLE -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2>
                    Student Records
                </h2>

                <div class="result-count">

                    Showing
                    <strong>
                        <?= number_format(
                            count($students)
                        ) ?>
                    </strong>

                    filtered student records

                </div>

            </div>


            <?php if ($qr_missing > 0): ?>

                <div
                    style="
                        font-size:12px;
                        color:#92400e;
                        font-weight:700;
                    "
                >

                    <?= number_format(
                        $qr_missing
                    ) ?>

                    students without QR

                </div>

            <?php endif; ?>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Student
                        </th>

                        <th>
                            Program
                        </th>

                        <th>
                            Year
                        </th>

                        <th>
                            Section
                        </th>

                        <th>
                            Student Email
                        </th>

                        <th>
                            Parent Email
                        </th>

                        <th>
                            QR
                        </th>

                        <th>
                            Gate Status
                        </th>

                        <th>
                            Account
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (
                    empty($students)
                ): ?>

                    <tr>

                        <td
                            colspan="10"
                            class="empty"
                        >
                            No students found
                            matching your filters.
                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $students
                        as $student
                    ): ?>

                        <?php

                        $photo =
                            trim(
                                $student['photo']
                                ?? ''
                            );

                        if ($photo === '') {

                            $photo =
                                'photos/' .
                                $student['student_id'] .
                                '.jpg';
                        }

                        ?>

                        <tr>


                            <!-- STUDENT -->

                            <td>

                                <div
                                    class="student-cell"
                                >

                                    <img
                                        src="<?= htmlspecialchars(
                                            $photo
                                        ) ?>"
                                        class="student-photo"
                                        alt="Student Photo"
                                        onerror="
                                            this.style.display='none'
                                        "
                                    >


                                    <div>

                                        <div
                                            class="student-name"
                                        >
                                            <?= htmlspecialchars(
                                                $student[
                                                    'full_name'
                                                ]
                                            ) ?>
                                        </div>

                                        <div
                                            class="student-id"
                                        >
                                            <?= htmlspecialchars(
                                                $student[
                                                    'student_id'
                                                ]
                                            ) ?>
                                        </div>

                                    </div>

                                </div>

                            </td>


                            <!-- PROGRAM -->

                            <td>

                                <?= htmlspecialchars(
                                    $student[
                                        'program'
                                    ]
                                ) ?>

                            </td>


                            <!-- YEAR -->

                            <td>

                                <?= htmlspecialchars(
                                    $student[
                                        'year_level'
                                    ]
                                ) ?>

                            </td>


                            <!-- SECTION -->

                            <td>

                                <?= htmlspecialchars(
                                    $student[
                                        'section'
                                    ]
                                ) ?>

                            </td>


                            <!-- EMAIL -->

                            <td>

                                <?php if (
                                    !empty(
                                        $student['email']
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $student['email']
                                    ) ?>

                                <?php else: ?>

                                    <span
                                        style="
                                            color:#94a3b8;
                                        "
                                    >
                                        Not provided
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- PARENT EMAIL -->

                            <td>

                                <?php if (
                                    !empty(
                                        $student[
                                            'parent_email'
                                        ]
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $student[
                                            'parent_email'
                                        ]
                                    ) ?>

                                <?php else: ?>

                                    <span
                                        style="
                                            color:#94a3b8;
                                        "
                                    >
                                        Not provided
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- QR -->

                            <td>

                                <?php if (
                                    !empty(
                                        $student[
                                            'qr_code'
                                        ]
                                    )
                                ): ?>

                                    <span
                                        class="
                                            badge
                                            badge-qr
                                        "
                                    >
                                        ASSIGNED
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            badge
                                            badge-noqr
                                        "
                                    >
                                        NOT ASSIGNED
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- GATE STATUS -->

                            <td>

                                <?php if (
                                    $student[
                                        'current_status'
                                    ] === 'IN'
                                ): ?>

                                    <span
                                        class="
                                            badge
                                            badge-in
                                        "
                                    >
                                        IN
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            badge
                                            badge-out
                                        "
                                    >
                                        OUT
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ACCOUNT -->

                            <td>

                                <?php if (
                                    (int)$student[
                                        'is_active'
                                    ] === 1
                                ): ?>

                                    <span
                                        class="
                                            badge
                                            badge-active
                                        "
                                    >
                                        ACTIVE
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            badge
                                            badge-inactive
                                        "
                                    >
                                        INACTIVE
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div
                                    class="row-actions"
                                >


                                    <a
                                        href="edit_student.php?id=<?= (int)$student['id'] ?>"
                                        class="
                                            action-btn
                                            primary
                                        "
                                    >
                                        Edit
                                    </a>


                                    <a
                                        href="student_qr.php?id=<?= (int)$student['id'] ?>"
                                        class="
                                            action-btn
                                            success
                                        "
                                    >
                                        QR
                                    </a>


                                    <?php if (
                                        (int)$student[
                                            'is_active'
                                        ] === 1
                                    ): ?>

                                        <form method="POST" action="toggle_student.php" style="display:inline" onsubmit="return confirm('Deactivate this student account?');">
                                            <?= smartgate_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
                                            <button type="submit" class="action-btn">Deactivate</button>
                                        </form>

                                    <?php else: ?>

                                        <form method="POST" action="toggle_student.php" style="display:inline" onsubmit="return confirm('Activate this student account?');">
                                            <?= smartgate_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
                                            <button type="submit" class="action-btn success">Activate</button>
                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <div class="footer-note">

            QR assigned:
            <strong>
                <?= number_format(
                    $qr_assigned
                ) ?>
            </strong>

            &nbsp; | &nbsp;

            QR missing:
            <strong>
                <?= number_format(
                    $qr_missing
                ) ?>
            </strong>

            &nbsp; | &nbsp;

            Currently IN:
            <strong>
                <?= number_format(
                    $current_in
                ) ?>
            </strong>

            &nbsp; | &nbsp;

            Currently OUT:
            <strong>
                <?= number_format(
                    $current_out
                ) ?>
            </strong>

        </div>

    </div>

</div>

</div>
</body>

</html>
