<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

/*
|--------------------------------------------------------------------------
| SMARTGATE AUDIT TRAIL
|--------------------------------------------------------------------------
| Only Super Admin can access the complete audit trail.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'super_admin') {
    header("Location: admin.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search    = trim($_GET['search'] ?? '');
$year      = trim($_GET['year'] ?? '');
$semester  = trim($_GET['semester'] ?? '');
$month     = trim($_GET['month'] ?? '');
$week      = trim($_GET['week'] ?? '');
$day       = trim($_GET['day'] ?? '');
$action    = trim($_GET['action'] ?? '');


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
        al.action LIKE ?
        OR al.description LIKE ?
        OR u.username LIKE ?
        OR u.full_name LIKE ?
        OR al.target_type LIKE ?
        OR al.target_id LIKE ?
        OR al.ip_address LIKE ?
    )";

    $search_param = "%{$search}%";

    for ($i = 0; $i < 7; $i++) {
        $params[] = $search_param;
    }

    $types .= "sssssss";
}

if ($year !== '') {
    $where[] = "YEAR(al.audit_time) = ?";
    $params[] = (int)$year;
    $types .= "i";
}

if ($semester !== '') {

    if ($semester === '1st') {
        $where[] = "MONTH(al.audit_time) BETWEEN 8 AND 12";
    }

    if ($semester === '2nd') {
        $where[] = "MONTH(al.audit_time) BETWEEN 1 AND 5";
    }

    if ($semester === 'Summer') {
        $where[] = "MONTH(al.audit_time) BETWEEN 6 AND 7";
    }
}

if ($month !== '') {
    $where[] = "MONTH(al.audit_time) = ?";
    $params[] = (int)$month;
    $types .= "i";
}

if ($week !== '') {
    $where[] = "WEEK(al.audit_time, 1) = ?";
    $params[] = (int)$week;
    $types .= "i";
}

if ($day !== '') {
    $where[] = "DATE(al.audit_time) = ?";
    $params[] = $day;
    $types .= "s";
}

if ($action !== '') {
    $where[] = "al.action = ?";
    $params[] = $action;
    $types .= "s";
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
| GET AUDIT LOGS
|--------------------------------------------------------------------------
*/

$audit_logs = [];

$sql = "
    SELECT
        al.id,
        al.user_id,
        al.action,
        al.description,
        al.target_type,
        al.target_id,
        al.ip_address,
        al.audit_time,

        u.username,
        u.full_name,
        u.role,
        u.department

    FROM system_audit_logs al

    LEFT JOIN users u
        ON al.user_id = u.id

    $where_sql

    ORDER BY al.audit_time DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    bindDynamicParams(
        $stmt,
        $types,
        $params
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $audit_logs[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| SUMMARY STATISTICS
|--------------------------------------------------------------------------
*/

$total_audits = (int)(
    $conn->query("
        SELECT COUNT(*)
        FROM system_audit_logs
    ")->fetch_row()[0] ?? 0
);

$today_audits = (int)(
    $conn->query("
        SELECT COUNT(*)
        FROM system_audit_logs
        WHERE DATE(audit_time) = CURDATE()
    ")->fetch_row()[0] ?? 0
);

$login_audits = (int)(
    $conn->query("
        SELECT COUNT(*)
        FROM system_audit_logs
        WHERE action IN (
            'LOGIN',
            'LOGOUT'
        )
    ")->fetch_row()[0] ?? 0
);

$user_management_audits = (int)(
    $conn->query("
        SELECT COUNT(*)
        FROM system_audit_logs
        WHERE action LIKE '%USER%'
    ")->fetch_row()[0] ?? 0
);


/*
|--------------------------------------------------------------------------
| AVAILABLE ACTIONS
|--------------------------------------------------------------------------
*/

$actions = [];

$result = $conn->query("
    SELECT DISTINCT action
    FROM system_audit_logs
    WHERE action IS NOT NULL
      AND action <> ''
    ORDER BY action ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $actions[] = $row['action'];
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
        'Content-Disposition: attachment; filename=smartgate_audit_trail_' .
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
            'Date & Time',
            'Username',
            'Full Name',
            'Role',
            'Department',
            'Action',
            'Description',
            'Target Type',
            'Target ID',
            'IP Address'
        ]
    );

    foreach ($audit_logs as $log) {

        fputcsv(
            $output,
            [
                $log['audit_time'],
                $log['username'] ?? '',
                $log['full_name'] ?? '',
                $log['role'] ?? '',
                $log['department'] ?? '',
                $log['action'],
                $log['description'],
                $log['target_type'] ?? '',
                $log['target_id'] ?? '',
                $log['ip_address'] ?? ''
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
    SmartGate Audit Trail
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


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}

.stat {
    background: white;

    border-radius: 14px;

    padding: 20px;

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
    font-size: 30px;

    font-weight: 800;

    margin-top: 7px;
}


/*
|--------------------------------------------------------------------------
| FILTER
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
        repeat(6, 1fr);

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
| CARD
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

    min-width: 1250px;

    border-collapse: collapse;
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

    vertical-align: top;
}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

.user-name {
    font-weight: 700;
}

.username {
    margin-top: 3px;

    font-size: 11px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| ACTION BADGE
|--------------------------------------------------------------------------
*/

.action-badge {
    display: inline-block;

    padding: 6px 9px;

    border-radius: 7px;

    background: #dbeafe;

    color: #1d4ed8;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| ROLE BADGE
|--------------------------------------------------------------------------
*/

.role-badge {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 999px;

    background: #e2e8f0;

    color: #334155;

    font-size: 10px;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| TARGET
|--------------------------------------------------------------------------
*/

.target {
    font-size: 11px;

    color: #475569;
}

.target strong {
    color: #1e293b;
}


/*
|--------------------------------------------------------------------------
| IP
|--------------------------------------------------------------------------
*/

.ip {
    font-family:
        Consolas,
        monospace;

    font-size: 11px;

    color: #475569;
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
| SECURITY NOTICE
|--------------------------------------------------------------------------
*/

.notice {
    margin-top: 15px;

    padding: 12px;

    border-radius: 9px;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    color: #64748b;

    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media(max-width:1200px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .filters {
        grid-template-columns:
            repeat(4, 1fr);
    }

}

@media(max-width:800px) {

    .header {
        flex-direction: column;

        align-items: flex-start;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .filters {
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
    .notice {
        display: none !important;
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
        font-size: 8px;

        padding: 6px;
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
                Audit Trail
            </h1>

            <p>
                Complete record of important
                SmartGate system activities
            </p>

        </div>


        <div class="actions">
            <a
                href="?<?= htmlspecialchars(
                    http_build_query(
                        array_merge(
                            $_GET,
                            ['export' => 'csv']
                        )
                    )
                ) ?>"
                class="btn btn-success"
            >
                Export CSV
            </a>

            <button
                onclick="window.print()"
                class="btn btn-dark"
            >
                Print
            </button>

        </div>

    </div>


    <!-- STATISTICS -->

    <div class="stats">


        <div class="stat">

            <div class="stat-label">
                TOTAL AUDIT RECORDS
            </div>

            <div class="stat-value">
                <?= number_format(
                    $total_audits
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                TODAY'S ACTIVITIES
            </div>

            <div class="stat-value">
                <?= number_format(
                    $today_audits
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                LOGIN / LOGOUT
            </div>

            <div class="stat-value">
                <?= number_format(
                    $login_audits
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                USER MANAGEMENT
            </div>

            <div class="stat-value">
                <?= number_format(
                    $user_management_audits
                ) ?>
            </div>

        </div>

    </div>


    <!-- FILTERS -->

    <div class="filter-card">

        <div class="filter-title">
            Search & Filter Audit Trail
        </div>


        <form method="GET">

            <div class="filters">


                <!-- SEARCH -->

                <div class="field">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="
                            Action, user, description,
                            target, IP...
                        "
                        value="<?= htmlspecialchars(
                            $search
                        ) ?>"
                    >

                </div>


                <!-- YEAR -->

                <div class="field">

                    <label>
                        Year
                    </label>

                    <input
                        type="number"
                        name="year"
                        placeholder="2026"
                        value="<?= htmlspecialchars(
                            $year
                        ) ?>"
                    >

                </div>


                <!-- SEMESTER -->

                <div class="field">

                    <label>
                        Semester
                    </label>

                    <select name="semester">

                        <option value="">
                            All Semesters
                        </option>

                        <option
                            value="1st"
                            <?= $semester === '1st'
                                ? 'selected'
                                : '' ?>
                        >
                            1st Semester
                        </option>

                        <option
                            value="2nd"
                            <?= $semester === '2nd'
                                ? 'selected'
                                : '' ?>
                        >
                            2nd Semester
                        </option>

                        <option
                            value="Summer"
                            <?= $semester === 'Summer'
                                ? 'selected'
                                : '' ?>
                        >
                            Summer
                        </option>

                    </select>

                </div>


                <!-- MONTH -->

                <div class="field">

                    <label>
                        Month
                    </label>

                    <select name="month">

                        <option value="">
                            All Months
                        </option>

                        <?php

                        $months = [
                            1 => 'January',
                            2 => 'February',
                            3 => 'March',
                            4 => 'April',
                            5 => 'May',
                            6 => 'June',
                            7 => 'July',
                            8 => 'August',
                            9 => 'September',
                            10 => 'October',
                            11 => 'November',
                            12 => 'December'
                        ];

                        foreach (
                            $months as $num => $name
                        ):

                        ?>

                            <option
                                value="<?= $num ?>"
                                <?= (string)$month ===
                                    (string)$num
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- WEEK -->

                <div class="field">

                    <label>
                        Week
                    </label>

                    <input
                        type="number"
                        name="week"
                        min="1"
                        max="53"
                        placeholder="1-53"
                        value="<?= htmlspecialchars(
                            $week
                        ) ?>"
                    >

                </div>


                <!-- DAY -->

                <div class="field">

                    <label>
                        Day
                    </label>

                    <input
                        type="number"
                        name="day"
                        min="1"
                        max="31"
                        placeholder="1-31"
                        value="<?= htmlspecialchars(
                            $day
                        ) ?>"
                    >

                </div>


                <!-- ACTION -->

                <div class="field">

                    <label>
                        Action
                    </label>

                    <select name="action">

                        <option value="">
                            All Actions
                        </option>

                        <?php foreach (
                            $actions as $item
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $item
                                ) ?>"
                                <?= $action === $item
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

            </div>


            <div class="filter-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply Filters
                </button>


                <a
                    href="audit_logs.php"
                    class="btn btn-secondary"
                >
                    Reset Filters
                </a>

            </div>

        </form>

    </div>


    <!-- AUDIT TABLE -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2>
                    System Audit Records
                </h2>

                <div class="result-count">

                    Showing
                    <strong>
                        <?= number_format(
                            count($audit_logs)
                        ) ?>
                    </strong>

                    filtered audit record(s)

                </div>

            </div>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Date & Time
                        </th>

                        <th>
                            User
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            Target
                        </th>

                        <th>
                            IP Address
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    empty($audit_logs)
                ): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >
                            No audit records found
                            for the selected filters.
                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $audit_logs
                        as $log
                    ): ?>

                        <tr>


                            <!-- DATE -->

                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $log[
                                                'audit_time'
                                            ]
                                        )
                                    )
                                ) ?>

                                <br>

                                <span
                                    style="
                                        color:#64748b;
                                        font-size:11px;
                                    "
                                >

                                    <?= htmlspecialchars(
                                        date(
                                            'h:i:s A',
                                            strtotime(
                                                $log[
                                                    'audit_time'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- USER -->

                            <td>

                                <?php if (
                                    !empty(
                                        $log['full_name']
                                    )
                                ): ?>

                                    <div
                                    class="user-name"
                                    style="color: #64748b !important;"
                                    >
                                    <?= htmlspecialchars(
                                        $log['full_name']
                                    ) ?>
                                </div>

                                    <div
                                        class="username"
                                    >
                                        @<?= htmlspecialchars(
                                            $log[
                                                'username'
                                            ]
                                        ) ?>
                                    </div>

                                <?php else: ?>

                                    <span
                                        style="
                                            color:#94a3b8;
                                        "
                                    >
                                        System
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ROLE -->

                            <td>

                                <?php if (
                                    !empty(
                                        $log['role']
                                    )
                                ): ?>

                                    <span
                                        class="
                                            role-badge
                                        "
                                    >
                                        <?= htmlspecialchars(
                                            $log[
                                                'role'
                                            ]
                                        ) ?>
                                    </span>

                                <?php else: ?>

                                    <span
                                        style="
                                            color:#94a3b8;
                                        "
                                    >
                                        -
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <span
                                    class="
                                        action-badge
                                    "
                                >
                                    <?= htmlspecialchars(
                                        $log[
                                            'action'
                                        ]
                                    ) ?>
                                </span>

                            </td>


                            <!-- DESCRIPTION -->

                            <td>

                                <?= htmlspecialchars(
                                    $log[
                                        'description'
                                    ]
                                ) ?>

                            </td>


                            <!-- TARGET -->

                            <td>

                                <?php if (
                                    !empty(
                                        $log[
                                            'target_type'
                                        ]
                                    ) ||
                                    !empty(
                                        $log[
                                            'target_id'
                                        ]
                                    )
                                ): ?>

                                    <div
                                        class="target"
                                    >

                                        <?php if (
                                            !empty(
                                                $log[
                                                    'target_type'
                                                ]
                                            )
                                        ): ?>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $log[
                                                        'target_type'
                                                    ]
                                                ) ?>
                                            </strong>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $log[
                                                    'target_id'
                                                ]
                                            )
                                        ): ?>

                                            <br>

                                            ID:
                                            <?= htmlspecialchars(
                                                $log[
                                                    'target_id'
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <span
                                        style="
                                            color:#94a3b8;
                                        "
                                    >
                                        -
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- IP -->

                            <td>

                                <span
                                    class="ip"
                                >
                                    <?= htmlspecialchars(
                                        $log[
                                            'ip_address'
                                        ] ?? '-'
                                    ) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>


                </tbody>

            </table>

        </div>


        <div class="notice">

            <strong>
                Audit Trail:
            </strong>

            This page records important
            SmartGate system activities such as
            login/logout, student changes,
            attendance archiving, bypass actions,
            user management, and other
            administrative operations.

        </div>

    </div>

</div>

</div>
</body>

</html>
