<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();

require_once "db.php";

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId   = (int)$_SESSION["user_id"];
$fullName = $_SESSION["full_name"] ?? "User";
$role     = $_SESSION["role"] ?? "";

/*
|--------------------------------------------------------------------------
| ALLOWED ROLES
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    "super_admin",
    "CCDU",
    "Guidance",
    "Security"
];

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    die("Access denied.");
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/

$canAdd = in_array(
    $role,
    [
        "super_admin",
        "CCDU",
        "Security"
    ],
    true
);

$canEdit = in_array(
    $role,
    [
        "super_admin",
        "CCDU",
        "Guidance"
    ],
    true
);

$isSecurityViewOnly =
    ($role === "Security");

$isGuidance =
    ($role === "Guidance");

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET["search"] ?? ""
);

$year = trim(
    $_GET["year"] ?? ""
);

$semester = trim(
    $_GET["semester"] ?? ""
);

$month = trim(
    $_GET["month"] ?? ""
);

$week = trim(
    $_GET["week"] ?? ""
);

$day = trim(
    $_GET["day"] ?? ""
);

$status = trim(
    $_GET["status"] ?? ""
);

/*
|--------------------------------------------------------------------------
| YEAR OPTIONS
|--------------------------------------------------------------------------
*/

$years = [];

$result = $conn->query("
    SELECT DISTINCT
        YEAR(violation_time) AS violation_year
    FROM student_violations
    ORDER BY violation_year DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if (!empty($row["violation_year"])) {

            $years[] =
                $row["violation_year"];
        }
    }
}

/*
|--------------------------------------------------------------------------
| MONTH OPTIONS
|--------------------------------------------------------------------------
*/

$months = [
    1  => "January",
    2  => "February",
    3  => "March",
    4  => "April",
    5  => "May",
    6  => "June",
    7  => "July",
    8  => "August",
    9  => "September",
    10 => "October",
    11 => "November",
    12 => "December"
];

/*
|--------------------------------------------------------------------------
| WEEK OPTIONS
|--------------------------------------------------------------------------
*/

$weeks = [];

for ($i = 1; $i <= 53; $i++) {
    $weeks[] = $i;
}

/*
|--------------------------------------------------------------------------
| BUILD FILTERS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $where[] = "
        (
            v.student_id LIKE ?
            OR s.full_name LIKE ?
            OR s.program LIKE ?
            OR s.section LIKE ?
            OR v.violation_type LIKE ?
            OR v.description LIKE ?
            OR v.action_taken LIKE ?
            OR v.status LIKE ?
            OR u.full_name LIKE ?
        )
    ";

    $searchValue =
        "%" . $search . "%";

    for ($i = 0; $i < 9; $i++) {
        $params[] =
            $searchValue;
    }

    $types .=
        "sssssssss";
}

/*
|--------------------------------------------------------------------------
| YEAR
|--------------------------------------------------------------------------
*/

if ($year !== "") {

    $where[] =
        "YEAR(v.violation_time) = ?";

    $params[] =
        (int)$year;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| SEMESTER
|--------------------------------------------------------------------------
*/

if ($semester === "1st") {

    $where[] = "
        MONTH(v.violation_time)
        BETWEEN 8 AND 12
    ";

} elseif ($semester === "2nd") {

    $where[] = "
        MONTH(v.violation_time)
        BETWEEN 1 AND 5
    ";

} elseif ($semester === "Summer") {

    $where[] = "
        MONTH(v.violation_time)
        BETWEEN 6 AND 7
    ";
}

/*
|--------------------------------------------------------------------------
| MONTH
|--------------------------------------------------------------------------
*/

if ($month !== "") {

    $where[] =
        "MONTH(v.violation_time) = ?";

    $params[] =
        (int)$month;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| WEEK
|--------------------------------------------------------------------------
*/

if ($week !== "") {

    $where[] = "
        WEEK(v.violation_time, 1) = ?
    ";

    $params[] =
        (int)$week;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| DAY
|--------------------------------------------------------------------------
*/

if ($day !== "") {

    $where[] =
        "DATE(v.violation_time) = ?";

    $params[] =
        $day;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $status,
        [
            "Pending",
            "Referred",
            "Resolved"
        ],
        true
    )
) {

    $where[] =
        "v.status = ?";

    $params[] =
        $status;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| MAIN QUERY
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        v.id,
        v.student_id,
        v.violation_type,
        v.description,
        v.action_taken,
        v.recorded_by,
        v.violation_time,
        v.status,

        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.email,
        s.photo,

        u.full_name AS recorded_by_name

    FROM student_violations v

    INNER JOIN students s
        ON s.student_id = v.student_id

    LEFT JOIN users u
        ON u.id = v.recorded_by

";

/*
|--------------------------------------------------------------------------
| WHERE
|--------------------------------------------------------------------------
*/

if (!empty($where)) {

    $sql .= "
        WHERE
        " .
        implode(
            " AND ",
            $where
        );
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "

    ORDER BY
        v.violation_time DESC

";

/*
|--------------------------------------------------------------------------
| FETCH
|--------------------------------------------------------------------------
*/

$violations = [];

$stmt = $conn->prepare(
    $sql
);

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result =
        $stmt->get_result();

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $violations[] =
            $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| FILTERED COUNTS
|--------------------------------------------------------------------------
*/

$filteredTotal =
    count($violations);

$filteredPending = 0;
$filteredReferred = 0;
$filteredResolved = 0;

foreach (
    $violations
    as $violation
) {

    if (
        $violation["status"]
        === "Pending"
    ) {

        $filteredPending++;

    } elseif (
        $violation["status"]
        === "Referred"
    ) {

        $filteredReferred++;

    } elseif (
        $violation["status"]
        === "Resolved"
    ) {

        $filteredResolved++;
    }
}

/*
|--------------------------------------------------------------------------
| OVERALL COUNTS
|--------------------------------------------------------------------------
*/

$totalViolations = 0;
$totalPending = 0;
$totalReferred = 0;
$totalResolved = 0;

$result = $conn->query("
    SELECT

        COUNT(*) AS total,

        SUM(
            CASE
                WHEN status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pending,

        SUM(
            CASE
                WHEN status = 'Referred'
                THEN 1
                ELSE 0
            END
        ) AS referred,

        SUM(
            CASE
                WHEN status = 'Resolved'
                THEN 1
                ELSE 0
            END
        ) AS resolved

    FROM student_violations
");

if (
    $result &&
    $row =
        $result->fetch_assoc()
) {

    $totalViolations =
        (int)$row["total"];

    $totalPending =
        (int)$row["pending"];

    $totalReferred =
        (int)$row["referred"];

    $totalResolved =
        (int)$row["resolved"];
}

/*
|--------------------------------------------------------------------------
| CURRENT DATE
|--------------------------------------------------------------------------
*/

$currentDate =
    date("F d, Y");

$currentTime =
    date("h:i A");

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
    Student Violations • SmartGate
</title>

<style>

/*
|--------------------------------------------------------------------------
| GLOBAL
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f1f5f9;

    color: #17233b;
}

a {
    text-decoration: none;
}

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.sidebar {

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 255px;

    min-height: 100vh;

    background:
        linear-gradient(
            180deg,
            #0f3d91,
            #123b7a,
            #0f172a
        );

    color: #ffffff;

    overflow-y: auto;

    z-index: 100;
}

.brand {

    padding: 25px 22px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);
}

.brand-title {

    font-size: 23px;

    font-weight: 800;
}

.brand-subtitle {

    margin-top: 5px;

    color: #bfdbfe;

    font-size: 12px;
}

.user-box {

    padding: 18px 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);
}

.user-name {

    font-size: 14px;

    font-weight: 700;
}

.user-role {

    margin-top: 5px;

    color: #bfdbfe;

    font-size: 12px;
}

.nav {

    padding: 15px 12px;
}

.nav-section {

    margin:
        13px 10px 7px;

    color: #93c5fd;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1px;
}

.nav a {

    display: flex;

    align-items: center;

    gap: 11px;

    padding:
        11px 12px;

    margin-bottom: 3px;

    border-radius: 9px;

    color: #dbeafe;

    font-size: 13px;
}

.nav a:hover {

    background:
        rgba(255,255,255,.12);

    color: #ffffff;
}

.nav a.active {

    background: #ffffff;

    color: #174ea6;

    font-weight: 700;
}

.icon {

    width: 22px;

    text-align: center;
}

/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.main {

    margin-left: 255px;

    width:
        calc(100% - 255px);

    min-height: 100vh;
}

/*
|--------------------------------------------------------------------------
| TOPBAR
|--------------------------------------------------------------------------
*/

.topbar {

    min-height: 72px;

    background: #ffffff;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        13px 28px;

    position: sticky;

    top: 0;

    z-index: 50;
}

.page-title {

    font-size: 20px;

    font-weight: 800;
}

.page-date {

    margin-top: 4px;

    color: #64748b;

    font-size: 12px;
}

.top-user {

    display: flex;

    align-items: center;

    gap: 11px;
}

.avatar {

    width: 39px;

    height: 39px;

    border-radius: 50%;

    background: #dbeafe;

    color: #1d4ed8;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;
}

.top-user-name {

    font-size: 13px;

    font-weight: 700;
}

.top-user-role {

    margin-top: 2px;

    color: #64748b;

    font-size: 11px;
}

/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

.content {

    padding: 27px;
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 20px;
}

.page-heading h1 {

    margin: 0;

    font-size: 24px;
}

.page-heading p {

    margin:
        6px 0 0;

    color: #64748b;

    font-size: 13px;
}

.header-actions {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}

/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    min-height: 38px;

    padding:
        9px 13px;

    border: 0;

    border-radius: 9px;

    font-size: 11px;

    font-weight: 700;

    cursor: pointer;
}

.btn-primary {

    background: #2563eb;

    color: #ffffff;
}

.btn-primary:hover {

    background: #1d4ed8;
}

.btn-secondary {

    background: #e2e8f0;

    color: #334155;
}

.btn-secondary:hover {

    background: #cbd5e1;
}

.btn-success {

    background: #16a34a;

    color: #ffffff;
}

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 20px;
}

.stat-card {

    background: #ffffff;

    border:
        1px solid #e2e8f0;

    border-radius: 14px;

    padding: 18px;

    box-shadow:
        0 5px 18px
        rgba(15,23,42,.04);
}

.stat-label {

    color: #64748b;

    font-size: 11px;

    font-weight: 600;
}

.stat-number {

    margin-top: 8px;

    color: #17233b;

    font-size: 27px;

    font-weight: 800;
}

.stat-note {

    margin-top: 4px;

    color: #94a3b8;

    font-size: 10px;
}

/*
|--------------------------------------------------------------------------
| PANEL
|--------------------------------------------------------------------------
*/

.panel {

    background: #ffffff;

    border:
        1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 5px 18px
        rgba(15,23,42,.04);

    margin-bottom: 20px;

    overflow: hidden;
}

.panel-header {

    padding:
        17px 19px;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.panel-title {

    font-size: 14px;

    font-weight: 800;
}

.panel-subtitle {

    margin-top: 4px;

    color: #94a3b8;

    font-size: 11px;
}

.panel-body {

    padding: 18px;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

.filters {

    display: grid;

    grid-template-columns:
        2fr
        repeat(5, 1fr)
        1fr
        auto;

    gap: 10px;

    align-items: end;
}

.form-group label {

    display: block;

    margin-bottom: 6px;

    color: #475569;

    font-size: 10px;

    font-weight: 700;
}

.form-control {

    width: 100%;

    min-height: 39px;

    padding:
        8px 10px;

    border:
        1px solid #cbd5e1;

    border-radius: 8px;

    background: #ffffff;

    color: #17233b;

    font-size: 11px;

    outline: none;
}

.form-control:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}

.filter-actions {

    display: flex;

    gap: 7px;
}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-header {

    padding:
        17px 19px;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.table-count {

    color: #64748b;

    font-size: 11px;
}

.table-count strong {

    color: #17233b;
}

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 1250px;

    border-collapse: collapse;
}

thead th {

    padding:
        12px 13px;

    background: #f8fafc;

    color: #64748b;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .4px;

    text-align: left;

    white-space: nowrap;
}

tbody td {

    padding:
        12px 13px;

    border-top:
        1px solid #f1f5f9;

    color: #334155;

    font-size: 11px;

    font-weight: 400;

    vertical-align: middle;
}

tbody td strong {

    color: #17233b;

    font-weight: 600;
}

tbody tr:hover {

    background: #f8fafc;
}

/*
|--------------------------------------------------------------------------
| STUDENT
|--------------------------------------------------------------------------
*/

.student-cell {

    display: flex;

    align-items: center;

    gap: 9px;

    min-width: 185px;
}

.student-photo {

    width: 40px;

    height: 40px;

    border-radius: 9px;

    object-fit: cover;

    background: #e2e8f0;
}

.student-placeholder {

    width: 40px;

    height: 40px;

    border-radius: 9px;

    background: #dbeafe;

    color: #2563eb;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;
}

.student-name {

    color: #17233b;

    font-size: 11px;

    font-weight: 700;
}

.student-id {

    margin-top: 3px;

    color: #64748b;

    font-size: 9px;
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.badge {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        5px 9px;

    border-radius: 999px;

    font-size: 9px;

    font-weight: 700;

    white-space: nowrap;
}

.badge-pending {

    background: #fef3c7;

    color: #92400e;
}

.badge-referred {

    background: #dbeafe;

    color: #1d4ed8;
}

.badge-resolved {

    background: #dcfce7;

    color: #166534;
}

/*
|--------------------------------------------------------------------------
| VIOLATION
|--------------------------------------------------------------------------
*/

.violation-type {

    color: #17233b;

    font-weight: 700;

    font-size: 11px;
}

.description {

    max-width: 230px;

    color: #475569;

    line-height: 1.4;

    white-space: normal;
}

.action-taken {

    max-width: 220px;

    color: #475569;

    line-height: 1.4;

    white-space: normal;
}

.date-cell {

    white-space: nowrap;

    color: #475569;

    font-size: 10px;
}

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.row-actions {

    display: flex;

    flex-wrap: wrap;

    gap: 6px;
}

.action-btn {

    display: inline-flex;

    border: 0;

    cursor: pointer;

    font-family: inherit;

    align-items: center;

    justify-content: center;

    min-height: 30px;

    padding:
        6px 9px;

    border-radius: 7px;

    font-size: 9px;

    font-weight: 700;
}

.action-view {

    background: #e2e8f0;

    color: #334155;
}

.action-edit {

    background: #dbeafe;

    color: #1d4ed8;
}

.action-email {

    background: #dcfce7;

    color: #166534;
}

/*
|--------------------------------------------------------------------------
| EMAIL NOTICE
|--------------------------------------------------------------------------
*/

.email-note {

    margin-top: 4px;

    color: #64748b;

    font-size: 9px;
}

/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    padding: 45px 20px;

    text-align: center;

    color: #94a3b8;

    font-size: 12px;
}

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {

    padding:
        5px 0 20px;

    text-align: center;

    color: #94a3b8;

    font-size: 10px;
}

/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    body {

        background: #ffffff;
    }

    .sidebar,
    .topbar,
    .page-header,
    .stats,
    .filters-panel,
    .header-actions,
    .row-actions {

        display: none !important;
    }

    .main {

        width: 100%;

        margin: 0;
    }

    .content {

        padding: 0;
    }

    .panel {

        border: 0;

        box-shadow: none;
    }

    .table-wrapper {

        overflow: visible;
    }

    table {

        min-width: 0;
    }

    thead th,
    tbody td {

        font-size: 8px;
    }

    .student-photo,
    .student-placeholder {

        display: none;
    }
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1400px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .filters {

        grid-template-columns:
            repeat(4, 1fr);
    }
}

@media (max-width: 900px) {

    .sidebar {

        width: 220px;
    }

    .main {

        margin-left: 220px;

        width:
            calc(100% - 220px);
    }

    .page-header {

        align-items: flex-start;

        flex-direction: column;
    }

    .filters {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 650px) {

    .sidebar {

        position: relative;

        width: 100%;

        min-height: auto;
    }

    .main {

        margin-left: 0;

        width: 100%;
    }

    .topbar {

        position: relative;

        padding:
            14px 18px;
    }

    .top-user {

        display: none;
    }

    .content {

        padding: 17px;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .filters {

        grid-template-columns: 1fr;
    }

    .header-actions {

        width: 100%;
    }

    .header-actions .btn {

        flex: 1;
    }
}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body class="legacy-shell">

<?php include "smartgate_sidebar.php"; ?>


<!-- ==========================================================
     SIDEBAR
=========================================================== -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-title">
            SmartGate
        </div>

        <div class="brand-subtitle">
            Student Entry Management System
        </div>

    </div>


    <div class="user-box">

        <div class="user-name">
            <?= e($fullName) ?>
        </div>

        <div class="user-role">
            <?= e($role) ?>
        </div>

    </div>


    <nav class="nav">


        <div class="nav-section">
            Main
        </div>


        <a href="admin.php">
            <span class="icon">⌂</span>
            Dashboard
        </a>


        <?php if (
            $role === "super_admin" ||
            $role === "MIS"
        ): ?>

            <a href="student_management.php">
                <span class="icon">👨‍🎓</span>
                Student Management
            </a>

            <a href="mis_upload.php">
                <span class="icon">⬆</span>
                MIS Student Upload
            </a>

            <a href="student_attendance.php">
                <span class="icon">▣</span>
                Student Attendance
            </a>

        <?php endif; ?>


        <?php if ($role === "super_admin"): ?>

            <a href="user_management.php">
                <span class="icon">👥</span>
                User Management
            </a>

        <?php endif; ?>


        <?php if (
            $role === "super_admin" ||
            $role === "MIS" ||
            $role === "Security"
        ): ?>

            <a href="attendance_logs.php">
                <span class="icon">✓</span>
                Attendance Logs
            </a>

        <?php endif; ?>


        <?php if (
            $role === "super_admin" ||
            $role === "MIS"
        ): ?>

            <a href="analytics.php">
                <span class="icon">▥</span>
                Analytics
            </a>

            <a href="mis_reports.php">
                <span class="icon">▤</span>
                MIS Reports
            </a>

            <a href="archived_logs.php">
                <span class="icon">▱</span>
                Archived Logs
            </a>

        <?php endif; ?>


        <?php if (
            $role === "super_admin" ||
            $role === "Security"
        ): ?>

            <a href="bypass.php">
                <span class="icon">⚡</span>
                Bypass
            </a>

            <a href="bypass_logs.php">
                <span class="icon">↪</span>
                Bypass Logs
            </a>

        <?php endif; ?>


        <a
            href="violations.php"
            class="active"
        >
            <span class="icon">⚠</span>
            Student Violations
        </a>


        <?php if ($role === "super_admin"): ?>

            <div class="nav-section">
                Administration
            </div>

            <a href="audit_logs.php">
                <span class="icon">◷</span>
                Audit Trail
            </a>

        <?php endif; ?>


        <div class="nav-section">
            System
        </div>


        <a
            href="display.php"
            target="_blank"
        >
            <span class="icon">🖥</span>
            SmartGate Display
        </a>


        <a href="logout.php">
            <span class="icon">↪</span>
            Logout
        </a>


    </nav>

</aside>


<!-- ==========================================================
     MAIN
=========================================================== -->

<main class="main sg-page-shell">


    <!-- TOPBAR -->

    <header class="topbar">

        <div>

            <div class="page-title">
                Student Violations
            </div>

            <div class="page-date">

                <?= e($currentDate) ?>

                •

                <?= e($currentTime) ?>

            </div>

        </div>


        <div class="top-user">

            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $fullName,
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div>

                <div class="top-user-name">
                    <?= e($fullName) ?>
                </div>

                <div class="top-user-role">
                    <?= e($role) ?>
                </div>

            </div>

        </div>

    </header>


    <div class="content">


        <!-- ==================================================
             HEADER
        =================================================== -->

        <div class="page-header">


            <div class="page-heading">

                <h1>
                    Student Violations
                </h1>

                <p>
                    Record, review, refer, and resolve
                    student violation cases.
                </p>

            </div>


            <div class="header-actions">


                <?php if ($canAdd): ?>

                    <a
                        href="add_violation.php"
                        class="btn btn-primary"
                    >
                        + Add Violation
                    </a>

                <?php endif; ?>


                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="window.print()"
                >
                    🖨 Print
                </button>


                <button
                    type="button"
                    class="btn btn-success"
                    onclick="exportCSV()"
                >
                    ⇩ Export CSV
                </button>


            </div>

        </div>


        <!-- ==================================================
             SUMMARY
        =================================================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Total Violations
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $totalViolations
                    ) ?>
                </div>

                <div class="stat-note">
                    All recorded cases
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Pending
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $totalPending
                    ) ?>
                </div>

                <div class="stat-note">
                    Cases awaiting action
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Referred
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $totalReferred
                    ) ?>
                </div>

                <div class="stat-note">
                    Cases referred for follow-up
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Resolved
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $totalResolved
                    ) ?>
                </div>

                <div class="stat-note">
                    Completed cases
                </div>

            </div>


        </section>


        <!-- ==================================================
             FILTERS
        =================================================== -->

        <section
            class="
                panel
                filters-panel
            "
        >


            <div class="panel-header">

                <div>

                    <div class="panel-title">
                        Search & Filters
                    </div>

                    <div class="panel-subtitle">
                        Filter violation records by
                        student, academic period, date,
                        or status.
                    </div>

                </div>

            </div>


            <div class="panel-body">


                <form
                    method="GET"
                    action="violations.php"
                >


                    <div class="filters">


                        <!-- SEARCH -->

                        <div class="form-group">

                            <label>
                                Search
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="ID, name, violation..."
                                value="<?= e(
                                    $search
                                ) ?>"
                            >

                        </div>


                        <!-- YEAR -->

                        <div class="form-group">

                            <label>
                                Year
                            </label>

                            <select
                                name="year"
                                class="form-control"
                            >

                                <option value="">
                                    All Years
                                </option>

                                <?php foreach (
                                    $years
                                    as $item
                                ): ?>

                                    <option
                                        value="<?= e(
                                            $item
                                        ) ?>"
                                        <?= (string)$year ===
                                            (string)$item
                                                ? "selected"
                                                : "" ?>
                                    >
                                        <?= e(
                                            $item
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- SEMESTER -->

                        <div class="form-group">

                            <label>
                                Semester
                            </label>

                            <select
                                name="semester"
                                class="form-control"
                            >

                                <option value="">
                                    All Semesters
                                </option>

                                <option
                                    value="1st"
                                    <?= $semester === "1st"
                                        ? "selected"
                                        : "" ?>
                                >
                                    1st Semester
                                </option>

                                <option
                                    value="2nd"
                                    <?= $semester === "2nd"
                                        ? "selected"
                                        : "" ?>
                                >
                                    2nd Semester
                                </option>

                                <option
                                    value="Summer"
                                    <?= $semester === "Summer"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Summer
                                </option>

                            </select>

                        </div>


                        <!-- MONTH -->

                        <div class="form-group">

                            <label>
                                Month
                            </label>

                            <select
                                name="month"
                                class="form-control"
                            >

                                <option value="">
                                    All Months
                                </option>

                                <?php foreach (
                                    $months
                                    as $number =>
                                        $name
                                ): ?>

                                    <option
                                        value="<?= $number ?>"
                                        <?= (string)$month ===
                                            (string)$number
                                                ? "selected"
                                                : "" ?>
                                    >
                                        <?= e(
                                            $name
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- WEEK -->

                        <div class="form-group">

                            <label>
                                Week
                            </label>

                            <select
                                name="week"
                                class="form-control"
                            >

                                <option value="">
                                    All Weeks
                                </option>

                                <?php foreach (
                                    $weeks
                                    as $weekNumber
                                ): ?>

                                    <option
                                        value="<?= $weekNumber ?>"
                                        <?= (string)$week ===
                                            (string)$weekNumber
                                                ? "selected"
                                                : "" ?>
                                    >
                                        Week
                                        <?= $weekNumber ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- DAY -->

                        <div class="form-group">

                            <label>
                                Day
                            </label>

                            <input
                                type="date"
                                name="day"
                                class="form-control"
                                value="<?= e(
                                    $day
                                ) ?>"
                            >

                        </div>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-control"
                            >

                                <option value="">
                                    All Statuses
                                </option>

                                <option
                                    value="Pending"
                                    <?= $status === "Pending"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Pending
                                </option>

                                <option
                                    value="Referred"
                                    <?= $status === "Referred"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Referred
                                </option>

                                <option
                                    value="Resolved"
                                    <?= $status === "Resolved"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Resolved
                                </option>

                            </select>

                        </div>


                        <!-- BUTTONS -->

                        <div class="filter-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Filter
                            </button>

                            <a
                                href="violations.php"
                                class="btn btn-secondary"
                            >
                                Clear
                            </a>

                        </div>


                    </div>

                </form>

            </div>

        </section>


        <!-- ==================================================
             TABLE
        =================================================== -->

        <section class="panel">


            <div class="table-header">

                <div>

                    <div class="panel-title">
                        Violation Records
                    </div>

                    <div class="table-count">

                        Showing

                        <strong>
                            <?= number_format(
                                $filteredTotal
                            ) ?>
                        </strong>

                        record(s)

                        •

                        <?= number_format(
                            $filteredPending
                        ) ?>
                        Pending

                        •

                        <?= number_format(
                            $filteredReferred
                        ) ?>
                        Referred

                        •

                        <?= number_format(
                            $filteredResolved
                        ) ?>
                        Resolved

                    </div>

                </div>

            </div>


            <div class="table-wrapper">


                <?php if (
                    $filteredTotal > 0
                ): ?>


                    <table
                        id="violationsTable"
                    >


                        <thead>

                            <tr>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Program
                                </th>

                                <th>
                                    Year / Section
                                </th>

                                <th>
                                    Violation
                                </th>

                                <th>
                                    Description
                                </th>

                                <th>
                                    Action Taken
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Date & Time
                                </th>

                                <th>
                                    Recorded By
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $violations
                            as $violation
                        ): ?>


                            <tr>


                                <!-- STUDENT -->

                                <td>


                                    <div
                                        class="student-cell"
                                    >


                                        <?php

                                        $photo =
                                            trim(
                                                $violation[
                                                    "photo"
                                                ] ?? ""
                                            );

                                        if (
                                            $photo === ""
                                        ) {

                                            $photo =
                                                "photos/" .
                                                $violation[
                                                    "student_id"
                                                ] .
                                                ".jpg";
                                        }

                                        ?>


                                        <?php if (
                                            $photo !== ""
                                        ): ?>

                                            <img
                                                src="<?= e(
                                                    $photo
                                                ) ?>"
                                                class="
                                                    student-photo
                                                "
                                                alt="Student"
                                                onerror="
                                                    this.style.display='none';
                                                    this.nextElementSibling.style.display='flex';
                                                "
                                            >

                                            <div
                                                class="
                                                    student-placeholder
                                                "
                                                style="
                                                    display:none;
                                                "
                                            >
                                                👤
                                            </div>

                                        <?php else: ?>

                                            <div
                                                class="
                                                    student-placeholder
                                                "
                                            >
                                                👤
                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <div
                                                class="
                                                    student-name
                                                "
                                            >
                                                <?= e(
                                                    $violation[
                                                        "full_name"
                                                    ]
                                                ) ?>
                                            </div>

                                            <div
                                                class="
                                                    student-id
                                                "
                                            >
                                                <?= e(
                                                    $violation[
                                                        "student_id"
                                                    ]
                                                ) ?>
                                            </div>

                                        </div>


                                    </div>


                                </td>


                                <!-- PROGRAM -->

                                <td>

                                    <?= e(
                                        $violation[
                                            "program"
                                        ]
                                    ) ?>

                                </td>


                                <!-- YEAR / SECTION -->

                                <td>

                                    <?= e(
                                        $violation[
                                            "year_level"
                                        ]
                                    ) ?>

                                    /

                                    <?= e(
                                        $violation[
                                            "section"
                                        ]
                                    ) ?>

                                </td>


                                <!-- VIOLATION -->

                                <td>

                                    <div
                                        class="
                                            violation-type
                                        "
                                    >

                                        <?= e(
                                            $violation[
                                                "violation_type"
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DESCRIPTION -->

                                <td>

                                    <div
                                        class="
                                            description
                                        "
                                    >

                                        <?= e(
                                            $violation[
                                                "description"
                                            ]
                                            ?:
                                            "-"
                                        ) ?>

                                    </div>

                                </td>


                                <!-- ACTION TAKEN -->

                                <td>

                                    <div
                                        class="
                                            action-taken
                                        "
                                    >

                                        <?= e(
                                            $violation[
                                                "action_taken"
                                            ]
                                            ?:
                                            "-"
                                        ) ?>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>


                                    <?php if (
                                        $violation[
                                            "status"
                                        ]
                                        === "Pending"
                                    ): ?>

                                        <span
                                            class="
                                                badge
                                                badge-pending
                                            "
                                        >
                                            Pending
                                        </span>

                                    <?php elseif (
                                        $violation[
                                            "status"
                                        ]
                                        === "Referred"
                                    ): ?>

                                        <span
                                            class="
                                                badge
                                                badge-referred
                                            "
                                        >
                                            Referred
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                badge
                                                badge-resolved
                                            "
                                        >
                                            Resolved
                                        </span>

                                    <?php endif; ?>


                                </td>


                                <!-- DATE -->

                                <td>

                                    <div
                                        class="date-cell"
                                    >

                                        <?= e(
                                            date(
                                                "M d, Y",
                                                strtotime(
                                                    $violation[
                                                        "violation_time"
                                                    ]
                                                )
                                            )
                                        ) ?>

                                        <br>

                                        <?= e(
                                            date(
                                                "h:i:s A",
                                                strtotime(
                                                    $violation[
                                                        "violation_time"
                                                    ]
                                                )
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <!-- RECORDED BY -->

                                <td>

                                    <?= e(
                                        $violation[
                                            "recorded_by_name"
                                        ]
                                        ?:
                                        "Unknown User"
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>


                                    <div
                                        class="
                                            row-actions
                                        "
                                    >


                                        <!-- VIEW -->

                                        <a
                                            href="edit_violation.php?id=<?= (int)$violation["id"] ?>"
                                            class="
                                                action-btn
                                                action-view
                                            "
                                        >

                                            View

                                        </a>


                                        <!-- EDIT -->

                                        <?php if (
                                            $canEdit
                                        ): ?>

                                            <a
                                                href="edit_violation.php?id=<?= (int)$violation["id"] ?>"
                                                class="
                                                    action-btn
                                                    action-edit
                                                "
                                            >

                                                Edit

                                            </a>

                                        <?php endif; ?>


                                        <!-- GUIDANCE EMAIL -->

                                        <?php if (
                                            $isGuidance
                                        ): ?>


                                            <?php if (
                                                !empty(
                                                    $violation[
                                                        "email"
                                                    ]
                                                )
                                            ): ?>

                                                <form method="POST" action="send_violation_email.php" style="display:inline" onsubmit="return confirm('Send a violation notification email to this student?');">
                                                    <?= smartgate_csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$violation["id"] ?>">
                                                    <button type="submit" class="action-btn action-email">Send Email</button>
                                                </form>


                                            <?php else: ?>

                                                <span
                                                    class="
                                                        action-btn
                                                        action-view
                                                    "
                                                    title="
                                                        No student email is registered.
                                                    "
                                                >

                                                    No Email

                                                </span>

                                            <?php endif; ?>


                                        <?php endif; ?>


                                    </div>


                                    <?php if (
                                        $isGuidance &&
                                        !empty(
                                            $violation[
                                                "email"
                                            ]
                                        )
                                    ): ?>

                                        <div
                                            class="
                                                email-note
                                            "
                                        >

                                            <?= e(
                                                $violation[ 
                                                    "email"
                                                ]
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>


                <?php else: ?>


                    <div class="empty">

                        No violation records
                        match the selected filters.

                    </div>


                <?php endif; ?>


            </div>

        </section>


        <div class="footer">

            SmartGate • Student Violations

        </div>


    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
*/

function exportCSV() {

    const table =
        document.getElementById(
            "violationsTable"
        );


    if (!table) {

        alert(
            "There are no violation records to export."
        );

        return;
    }


    let csv = [];


    const rows =
        table.querySelectorAll(
            "tr"
        );


    rows.forEach(
        function(row) {

            const columns =
                row.querySelectorAll(
                    "th, td"
                );


            let rowData = [];


            columns.forEach(
                function(column) {

                    /*
                    | Clone cell so action
                    | buttons can be removed.
                    */

                    const clone =
                        column.cloneNode(
                            true
                        );


                    const buttons =
                        clone.querySelectorAll(
                            "a, button"
                        );


                    buttons.forEach(
                        function(button) {

                            button.remove();

                        }
                    );


                    let text =
                        clone.innerText
                            .replace(
                                /\s+/g,
                                " "
                            )
                            .trim();


                    text =
                        '"' +
                        text.replace(
                            /"/g,
                            '""'
                        ) +
                        '"';


                    rowData.push(
                        text
                    );

                }
            );


            csv.push(
                rowData.join(",")
            );

        }
    );


    const blob =
        new Blob(
            [
                csv.join("\n")
            ],
            {
                type:
                    "text/csv;charset=utf-8;"
            }
        );


    const url =
        URL.createObjectURL(
            blob
        );


    const link =
        document.createElement(
            "a"
        );


    link.href =
        url;


    link.download =
        "smartgate_violations_" +
        new Date()
            .toISOString()
            .slice(0, 10) +
        ".csv";


    document.body.appendChild(
        link
    );


    link.click();


    document.body.removeChild(
        link
    );


    URL.revokeObjectURL(
        url
    );

}

</script>


</body>

</html>
