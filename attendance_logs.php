<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();

require_once "db.php";

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION["role"] ?? "";

$allowedRoles = [
    "super_admin",
    "MIS",
    "Security"
];

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    die("Access denied.");
}

$userId   = $_SESSION["user_id"];
$fullName = $_SESSION["full_name"] ?? "User";

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
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");
$year = trim($_GET["year"] ?? "");
$semester = trim($_GET["semester"] ?? "");
$month = trim($_GET["month"] ?? "");
$week = trim($_GET["week"] ?? "");
$day = trim($_GET["day"] ?? "");
$direction = trim($_GET["direction"] ?? "");

/*
|--------------------------------------------------------------------------
| YEAR OPTIONS
|--------------------------------------------------------------------------
*/

$years = [];

$result = $conn->query("
    SELECT DISTINCT YEAR(scan_time) AS scan_year
    FROM attendance_logs
    ORDER BY scan_year DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if (!empty($row["scan_year"])) {
            $years[] = $row["scan_year"];
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
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = "";

/*
| Search
*/

if ($search !== "") {

    $where[] = "
        (
            a.student_id LIKE ?
            OR s.full_name LIKE ?
            OR s.program LIKE ?
            OR s.section LIKE ?
            OR a.device LIKE ?
            OR a.remarks LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchValue;
    }

    $types .= "ssssss";
}

/*
| Year
*/

if ($year !== "") {

    $where[] = "
        YEAR(a.scan_time) = ?
    ";

    $params[] = (int)$year;

    $types .= "i";
}

/*
| Semester
|
| 1st Semester = August to December
| 2nd Semester = January to May
| Summer       = June to July
*/

if ($semester === "1st") {

    $where[] = "
        MONTH(a.scan_time) BETWEEN 8 AND 12
    ";

} elseif ($semester === "2nd") {

    $where[] = "
        MONTH(a.scan_time) BETWEEN 1 AND 5
    ";

} elseif ($semester === "Summer") {

    $where[] = "
        MONTH(a.scan_time) BETWEEN 6 AND 7
    ";
}

/*
| Month
*/

if ($month !== "") {

    $where[] = "
        MONTH(a.scan_time) = ?
    ";

    $params[] = (int)$month;

    $types .= "i";
}

/*
| Week
*/

if ($week !== "") {

    $where[] = "
        WEEK(a.scan_time, 1) = ?
    ";

    $params[] = (int)$week;

    $types .= "i";
}

/*
| Day
*/

if ($day !== "") {

    $where[] = "
        DATE(a.scan_time) = ?
    ";

    $params[] = $day;

    $types .= "s";
}

/*
| Direction
*/

if (
    $direction === "IN" ||
    $direction === "OUT"
) {

    $where[] = "
        a.direction = ?
    ";

    $params[] = $direction;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| MAIN QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.student_id,
        a.direction,
        a.scan_time,
        a.device,
        a.remarks,

        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.photo

    FROM attendance_logs a

    LEFT JOIN students s
        ON s.student_id = a.student_id
";

if (!empty($where)) {

    $sql .= "
        WHERE
        " . implode(
            " AND ",
            $where
        );
}

$sql .= "
    ORDER BY
        a.scan_time DESC
";

/*
|--------------------------------------------------------------------------
| GET RECORDS
|--------------------------------------------------------------------------
*/

$logs = [];

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| FILTERED SUMMARY
|--------------------------------------------------------------------------
*/

$filteredTotal = count($logs);
$filteredIn = 0;
$filteredOut = 0;

foreach ($logs as $log) {

    if ($log["direction"] === "IN") {
        $filteredIn++;
    }

    if ($log["direction"] === "OUT") {
        $filteredOut++;
    }
}

/*
|--------------------------------------------------------------------------
| TODAY'S SUMMARY
|--------------------------------------------------------------------------
*/

$todayTotal = 0;
$todayIn = 0;
$todayOut = 0;

$result = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(
            CASE
                WHEN direction = 'IN'
                THEN 1
                ELSE 0
            END
        ) AS total_in,
        SUM(
            CASE
                WHEN direction = 'OUT'
                THEN 1
                ELSE 0
            END
        ) AS total_out
    FROM attendance_logs
    WHERE DATE(scan_time) = CURDATE()
");

if ($result && $row = $result->fetch_assoc()) {

    $todayTotal = (int)$row["total"];
    $todayIn = (int)$row["total_in"];
    $todayOut = (int)$row["total_out"];
}

/*
|--------------------------------------------------------------------------
| WEEK OPTIONS
|--------------------------------------------------------------------------
*/

$currentYear = (int)date("Y");
$currentWeek = (int)date("W");

$weeks = [];

for ($i = 0; $i < 53; $i++) {

    $weekNumber = $i + 1;

    $weeks[] = $weekNumber;
}

/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$currentDate = date("F d, Y");
$currentTime = date("h:i A");

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
    Attendance Logs • SmartGate
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

    font-size: 12px;

    color: #64748b;
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

    font-size: 11px;

    color: #64748b;
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

    gap: 8px;

    flex-wrap: wrap;
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
| STATISTICS
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
| TABLE HEADER
|--------------------------------------------------------------------------
*/

.table-header {

    padding:
        17px 19px;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.table-count {

    color: #64748b;

    font-size: 11px;
}

.table-count strong {

    color: #17233b;
}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 1050px;

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

    white-space: nowrap;
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

    min-width: 180px;
}

.student-photo {

    width: 38px;

    height: 38px;

    border-radius: 9px;

    object-fit: cover;

    background: #e2e8f0;
}

.student-placeholder {

    width: 38px;

    height: 38px;

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

    font-weight: 700;

    font-size: 11px;
}

.student-id {

    margin-top: 3px;

    color: #64748b;

    font-size: 9px;
}

/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 42px;

    padding:
        4px 8px;

    border-radius: 999px;

    font-size: 9px;

    font-weight: 700;
}

.badge-in {

    background: #dcfce7;

    color: #166534;
}

.badge-out {

    background: #fee2e2;

    color: #991b1b;
}

/*
|--------------------------------------------------------------------------
| DEVICE
|--------------------------------------------------------------------------
*/

.device {

    color: #475569;

    font-size: 10px;
}

.remarks {

    max-width: 180px;

    overflow: hidden;

    text-overflow: ellipsis;
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

    text-align: center;

    padding:
        5px 0 20px;

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
    .filters-panel,
    .header-actions,
    .filter-actions,
    .stats {

        display: none !important;
    }

    .main {

        margin: 0;

        width: 100%;
    }

    .content {

        padding: 0;
    }

    .panel {

        border: 0;

        box-shadow: none;

        margin: 0;
    }

    .panel-header {

        border-bottom:
            1px solid #000;
    }

    table {

        min-width: 0;
    }

    tbody td,
    thead th {

        font-size: 9px;
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

@media (max-width: 1350px) {

    .filters {

        grid-template-columns:
            repeat(4, 1fr);
    }

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
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


        <a
            href="attendance_logs.php"
            class="active"
        >
            <span class="icon">✓</span>
            Attendance Logs
        </a>


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


        <?php if (
            $role === "super_admin" ||
            $role === "Security" ||
            $role === "CCDU" ||
            $role === "Guidance"
        ): ?>

            <a href="violations.php">
                <span class="icon">⚠</span>
                Student Violations
            </a>

        <?php endif; ?>


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
                Attendance Logs
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
             PAGE HEADER
        =================================================== -->

        <div class="page-header">

            <div class="page-heading">

                <h1>
                    Attendance Logs
                </h1>

                <p>
                    Monitor student entry and exit
                    transactions recorded by SmartGate.
                </p>

            </div>


            <div class="header-actions">

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
             TODAY'S STATISTICS
        =================================================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Today's Scans
                </div>

                <div class="stat-number">
                    <?= number_format($todayTotal) ?>
                </div>

                <div class="stat-note">
                    All attendance transactions today
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Today's IN
                </div>

                <div class="stat-number">
                    <?= number_format($todayIn) ?>
                </div>

                <div class="stat-note">
                    Student entry transactions
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Today's OUT
                </div>

                <div class="stat-number">
                    <?= number_format($todayOut) ?>
                </div>

                <div class="stat-note">
                    Student exit transactions
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Filtered Records
                </div>

                <div class="stat-number">
                    <?= number_format($filteredTotal) ?>
                </div>

                <div class="stat-note">
                    <?= number_format($filteredIn) ?>
                    IN /
                    <?= number_format($filteredOut) ?>
                    OUT
                </div>

            </div>

        </section>


        <!-- ==================================================
             FILTER PANEL
        =================================================== -->

        <section class="panel filters-panel">


            <div class="panel-header">

                <div>

                    <div class="panel-title">
                        Search & Filters
                    </div>

                    <div class="panel-subtitle">
                        Filter attendance records by
                        academic period, date, or direction.
                    </div>

                </div>

            </div>


            <div class="panel-body">

                <form
                    method="GET"
                    action="attendance_logs.php"
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
                                placeholder="ID, name, program..."
                                value="<?= e($search) ?>"
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
                                        value="<?= e($item) ?>"
                                        <?= (string)$year ===
                                            (string)$item
                                                ? "selected"
                                                : "" ?>
                                    >
                                        <?= e($item) ?>
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
                                    as $number => $name
                                ): ?>

                                    <option
                                        value="<?= $number ?>"
                                        <?= (string)$month ===
                                            (string)$number
                                                ? "selected"
                                                : "" ?>
                                    >
                                        <?= e($name) ?>
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
                                value="<?= e($day) ?>"
                            >

                        </div>


                        <!-- DIRECTION -->

                        <div class="form-group">

                            <label>
                                Direction
                            </label>

                            <select
                                name="direction"
                                class="form-control"
                            >

                                <option value="">
                                    All
                                </option>

                                <option
                                    value="IN"
                                    <?= $direction === "IN"
                                        ? "selected"
                                        : "" ?>
                                >
                                    IN
                                </option>

                                <option
                                    value="OUT"
                                    <?= $direction === "OUT"
                                        ? "selected"
                                        : "" ?>
                                >
                                    OUT
                                </option>

                            </select>

                        </div>


                        <!-- ACTIONS -->

                        <div class="filter-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Filter
                            </button>

                            <a
                                href="attendance_logs.php"
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
             ATTENDANCE TABLE
        =================================================== -->

        <section class="panel">


            <div class="table-header">

                <div>

                    <div class="panel-title">
                        Attendance Records
                    </div>

                    <div class="table-count">

                        Showing

                        <strong>
                            <?= number_format($filteredTotal) ?>
                        </strong>

                        record(s)

                    </div>

                </div>

            </div>


            <div class="table-wrapper">


                <?php if (
                    $filteredTotal > 0
                ): ?>


                    <table id="attendanceTable">


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
                                    Direction
                                </th>

                                <th>
                                    Scan Date & Time
                                </th>

                                <th>
                                    Device
                                </th>

                                <th>
                                    Remarks
                                </th>

                                <?php if (
                                    $role === "super_admin" ||
                                    $role === "MIS"
                                ): ?>

                                    <th>
                                        Action
                                    </th>

                                <?php endif; ?>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $logs
                            as $log
                        ): ?>


                            <tr>


                                <!-- STUDENT -->

                                <td>

                                    <div class="student-cell">


                                        <?php

                                        $photo =
                                            trim(
                                                $log["photo"]
                                                ?? ""
                                            );

                                        if (
                                            $photo === ""
                                        ) {

                                            $photo =
                                                "photos/" .
                                                $log["student_id"] .
                                                ".jpg";
                                        }

                                        ?>


                                        <?php if (
                                            $photo !== ""
                                        ): ?>

                                            <img
                                                src="<?= e($photo) ?>"
                                                class="student-photo"
                                                alt="Student"
                                                onerror="
                                                    this.style.display='none';
                                                    this.nextElementSibling.style.display='flex';
                                                "
                                            >

                                            <div
                                                class="student-placeholder"
                                                style="display:none;"
                                            >
                                                👤
                                            </div>

                                        <?php else: ?>

                                            <div
                                                class="student-placeholder"
                                            >
                                                👤
                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <div
                                                class="student-name"
                                            >
                                                <?= e(
                                                    $log["full_name"]
                                                    ??
                                                    "Unknown Student"
                                                ) ?>
                                            </div>

                                            <div
                                                class="student-id"
                                            >
                                                <?= e(
                                                    $log["student_id"]
                                                ) ?>
                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- PROGRAM -->

                                <td>

                                    <?= e(
                                        $log["program"]
                                        ?? "-"
                                    ) ?>

                                </td>


                                <!-- YEAR -->

                                <td>

                                    <?= e(
                                        $log["year_level"]
                                        ?? "-"
                                    ) ?>

                                </td>


                                <!-- SECTION -->

                                <td>

                                    <?= e(
                                        $log["section"]
                                        ?? "-"
                                    ) ?>

                                </td>


                                <!-- DIRECTION -->

                                <td>

                                    <?php if (
                                        $log["direction"]
                                        === "IN"
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


                                <!-- TIME -->

                                <td>

                                    <?= e(
                                        date(
                                            "M d, Y h:i:s A",
                                            strtotime(
                                                $log["scan_time"]
                                            )
                                        )
                                    ) ?>

                                </td>


                                <!-- DEVICE -->

                                <td>

                                    <span
                                        class="device"
                                    >
                                        <?= e(
                                            $log["device"]
                                            ?: "SMARTGATE"
                                        ) ?>
                                    </span>

                                </td>


                                <!-- REMARKS -->

                                <td>

                                    <span
                                        class="remarks"
                                        title="<?= e(
                                            $log["remarks"]
                                            ?? ""
                                        ) ?>"
                                    >
                                        <?= e(
                                            $log["remarks"]
                                            ?: "-"
                                        ) ?>
                                    </span>

                                </td>


                                <!-- ARCHIVE -->

                                <?php if (
                                    $role === "super_admin" ||
                                    $role === "MIS"
                                ): ?>

                                    <td>

                                        <form method="POST" action="archive_attendance.php" style="display:inline" onsubmit="return confirm('Archive this attendance record?');">
                                            <?= smartgate_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$log["id"] ?>">
                                            <button type="submit" class="btn btn-secondary" style="min-height:30px;padding:6px 9px;font-size:9px;">Archive</button>
                                        </form>

                                    </td>

                                <?php endif; ?>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>


                <?php else: ?>


                    <div class="empty">

                        No attendance records
                        match the selected filters.

                    </div>


                <?php endif; ?>


            </div>

        </section>


        <div class="footer">

            SmartGate • Attendance Logs

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
            "attendanceTable"
        );

    if (!table) {

        alert(
            "There are no attendance records to export."
        );

        return;
    }


    let csv = [];


    const rows =
        table.querySelectorAll("tr");


    rows.forEach(function(row) {

        const columns =
            row.querySelectorAll(
                "th, td"
            );

        let rowData = [];


        columns.forEach(function(column) {

            let text =
                column.innerText
                    .replace(/\s+/g, " ")
                    .trim();


            /*
            | Remove Archive button
            */

            if (
                column.querySelector(
                    "a"
                )
            ) {

                const clone =
                    column.cloneNode(true);

                const links =
                    clone.querySelectorAll(
                        "a"
                    );

                links.forEach(
                    function(link) {
                        link.remove();
                    }
                );

                text =
                    clone.innerText
                        .replace(/\s+/g, " ")
                        .trim();
            }


            /*
            | CSV escaping
            */

            text =
                '"' +
                text.replace(
                    /"/g,
                    '""'
                ) +
                '"';


            rowData.push(text);

        });


        csv.push(
            rowData.join(",")
        );

    });


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


    link.href = url;

    link.download =
        "smartgate_attendance_logs_" +
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
