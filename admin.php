<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();

require_once "db.php";

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId      = $_SESSION["user_id"];
$username    = $_SESSION["username"] ?? "";
$fullName    = $_SESSION["full_name"] ?? "User";
$role        = $_SESSION["role"] ?? "";
$department  = $_SESSION["department"] ?? "";

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function hasRole($roles, $role)
{
    return in_array($role, $roles, true);
}

/*
|--------------------------------------------------------------------------
| ROLE PERMISSIONS
|--------------------------------------------------------------------------
*/

$isSuperAdmin = ($role === "super_admin");
$isMIS        = ($role === "MIS");
$isSecurity   = ($role === "Security");
$isCCDU       = ($role === "CCDU");
$isGuidance   = ($role === "Guidance");
$isIGP        = ($role === "IGP");
$isLibrary    = ($role === "Library");

/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$totalStudents = 0;
$activeStudents = 0;
$inactiveStudents = 0;
$currentIn = 0;
$currentOut = 0;

$todayScans = 0;
$todayIn = 0;
$todayOut = 0;
$todayBypass = 0;
$pendingViolations = 0;

/*
|--------------------------------------------------------------------------
| STUDENT COUNTS
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT
        COUNT(*) AS total_students,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_students,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_students,
        SUM(CASE WHEN current_status = 'IN' THEN 1 ELSE 0 END) AS current_in,
        SUM(CASE WHEN current_status = 'OUT' THEN 1 ELSE 0 END) AS current_out
    FROM students
");

if ($result && $row = $result->fetch_assoc()) {

    $totalStudents   = (int)$row["total_students"];
    $activeStudents  = (int)$row["active_students"];
    $inactiveStudents = (int)$row["inactive_students"];
    $currentIn       = (int)$row["current_in"];
    $currentOut      = (int)$row["current_out"];
}

/*
|--------------------------------------------------------------------------
| TODAY'S ATTENDANCE
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT
        COUNT(*) AS today_scans,
        SUM(CASE WHEN direction = 'IN' THEN 1 ELSE 0 END) AS today_in,
        SUM(CASE WHEN direction = 'OUT' THEN 1 ELSE 0 END) AS today_out
    FROM attendance_logs
    WHERE DATE(scan_time) = CURDATE()
");

if ($result && $row = $result->fetch_assoc()) {

    $todayScans = (int)$row["today_scans"];
    $todayIn    = (int)$row["today_in"];
    $todayOut   = (int)$row["today_out"];
}

/*
|--------------------------------------------------------------------------
| TODAY'S BYPASS
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM bypass_logs
    WHERE DATE(bypass_time) = CURDATE()
");

if ($result && $row = $result->fetch_assoc()) {
    $todayBypass = (int)$row["total"];
}

/*
|--------------------------------------------------------------------------
| PENDING VIOLATIONS
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM student_violations
    WHERE status = 'Pending'
");

if ($result && $row = $result->fetch_assoc()) {
    $pendingViolations = (int)$row["total"];
}

/*
|--------------------------------------------------------------------------
| RECENT ATTENDANCE
|--------------------------------------------------------------------------
*/

$recentAttendance = [];

$result = $conn->query("
    SELECT
        a.id,
        a.student_id,
        a.direction,
        a.scan_time,
        a.device,
        s.full_name,
        s.program,
        s.year_level,
        s.section
    FROM attendance_logs a
    LEFT JOIN students s
        ON s.student_id = a.student_id
    ORDER BY a.scan_time DESC
    LIMIT 8
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recentAttendance[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| RECENT BYPASS
|--------------------------------------------------------------------------
*/

$recentBypass = [];

$result = $conn->query("
    SELECT
        b.id,
        b.student_id,
        b.visitor_name,
        b.direction,
        b.reason,
        b.bypass_time,
        s.full_name,
        u.full_name AS processed_by
    FROM bypass_logs b
    LEFT JOIN students s
        ON s.student_id = b.student_id
    LEFT JOIN users u
        ON u.id = b.user_id
    ORDER BY b.bypass_time DESC
    LIMIT 6
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recentBypass[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT DATE
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
    SmartGate Dashboard
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
    min-height: 100%;
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
| LAYOUT
|--------------------------------------------------------------------------
*/

.app {
    min-height: 100vh;
    display: flex;
}

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.sidebar {
    width: 255px;
    min-height: 100vh;

    background:
        linear-gradient(
            180deg,
            #0f3d91 0%,
            #123b7a 48%,
            #0f172a 100%
        );

    color: #ffffff;

    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;

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
    letter-spacing: .3px;
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
    font-size: 15px;
    font-weight: 700;
}

.user-role {
    margin-top: 5px;
    font-size: 12px;
    color: #bfdbfe;
}

.nav {
    padding: 15px 12px;
}

.nav-section {
    margin:
        13px 10px 7px;

    font-size: 10px;

    color: #93c5fd;

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

    transition: .2s;
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
    font-size: 15px;
}

/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.main {
    margin-left: 255px;
    width: calc(100% - 255px);
    min-height: 100vh;
}

/*
|--------------------------------------------------------------------------
| TOPBAR
|--------------------------------------------------------------------------
*/

.topbar {
    height: 72px;

    background: #ffffff;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 28px;

    position: sticky;

    top: 0;

    z-index: 50;
}

.page-title {
    font-size: 20px;
    font-weight: 800;
    color: #17233b;
}

.page-date {
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}

.top-user {
    display: flex;
    align-items: center;
    gap: 12px;
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
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
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
| WELCOME
|--------------------------------------------------------------------------
*/

.welcome {
    background:
        linear-gradient(
            135deg,
            #1d4ed8,
            #2563eb,
            #0f3d91
        );

    color: #ffffff;

    border-radius: 18px;

    padding: 25px 27px;

    margin-bottom: 22px;

    box-shadow:
        0 12px 30px
        rgba(37,99,235,.15);
}

.welcome h1 {
    margin: 0;

    font-size: 25px;
}

.welcome p {
    margin:
        8px 0 0;

    color: #dbeafe;

    font-size: 13px;
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

    gap: 16px;

    margin-bottom: 22px;
}

.stat-card {
    background: #ffffff;

    border:
        1px solid #e2e8f0;

    border-radius: 15px;

    padding: 19px;

    box-shadow:
        0 5px 18px
        rgba(15,23,42,.04);
}

.stat-top {
    display: flex;

    align-items: center;

    justify-content: space-between;
}

.stat-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
}

.stat-icon {
    width: 39px;
    height: 39px;

    border-radius: 10px;

    background: #eff6ff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;
}

.stat-number {
    margin-top: 12px;

    font-size: 28px;

    font-weight: 800;

    color: #17233b;
}

.stat-note {
    margin-top: 4px;

    font-size: 11px;

    color: #94a3b8;
}

/*
|--------------------------------------------------------------------------
| QUICK ACCESS
|--------------------------------------------------------------------------
*/

.section-title {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin:
        26px 0 13px;
}

.section-title h2 {
    margin: 0;

    font-size: 17px;

    color: #17233b;
}

.section-title span {
    font-size: 11px;
    color: #94a3b8;
}

.quick-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 13px;
}

.quick-card {
    background: #ffffff;

    border:
        1px solid #e2e8f0;

    border-radius: 13px;

    padding: 17px;

    color: #17233b;

    transition: .2s;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.03);
}

.quick-card:hover {
    transform: translateY(-2px);

    border-color: #93c5fd;

    box-shadow:
        0 8px 20px
        rgba(15,23,42,.08);
}

.quick-icon {
    width: 40px;
    height: 40px;

    border-radius: 10px;

    background: #eff6ff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

    margin-bottom: 11px;
}

.quick-title {
    font-weight: 700;

    font-size: 13px;
}

.quick-desc {
    color: #64748b;

    font-size: 11px;

    margin-top: 4px;

    line-height: 1.5;
}

/*
|--------------------------------------------------------------------------
| TWO COLUMN
|--------------------------------------------------------------------------
*/

.two-column {
    display: grid;

    grid-template-columns:
        1.6fr 1fr;

    gap: 18px;

    margin-top: 22px;
}

.panel {
    background: #ffffff;

    border:
        1px solid #e2e8f0;

    border-radius: 15px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(15,23,42,.04);
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

.panel-link {
    font-size: 11px;
    color: #2563eb;
    font-weight: 700;
}

.panel-body {
    overflow-x: auto;
}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background: #f8fafc;

    color: #64748b;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .4px;

    padding:
        11px 13px;

    text-align: left;

    font-weight: 700;

    white-space: nowrap;
}

tbody td {
    padding:
        12px 13px;

    border-top:
        1px solid #f1f5f9;

    font-size: 12px;

    font-weight: 400;

    color: #334155;

    white-space: nowrap;
}

tbody td strong {
    font-weight: 600;
    color: #17233b;
}

tbody tr:hover {
    background: #f8fafc;
}

/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-flex;

    align-items: center;

    padding:
        4px 8px;

    border-radius: 999px;

    font-size: 10px;

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

.badge-bypass {
    background: #fef3c7;
    color: #92400e;
}

/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    padding: 30px;

    text-align: center;

    color: #94a3b8;

    font-size: 12px;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1200px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .quick-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .two-column {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 800px) {

    .sidebar {
        width: 220px;
    }

    .main {
        margin-left: 220px;

        width:
            calc(100% - 220px);
    }

    .content {
        padding: 18px;
    }
}

@media (max-width: 650px) {

    .sidebar {
        position: relative;

        width: 100%;

        min-height: auto;
    }

    .app {
        display: block;
    }

    .main {
        margin-left: 0;

        width: 100%;
    }

    .topbar {
        position: relative;

        height: auto;

        padding: 15px 18px;
    }

    .top-user {
        display: none;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .quick-grid {
        grid-template-columns: 1fr;
    }
}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>

<div class="app legacy-shell">

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
            <?php if ($department !== ""): ?>
                • <?= e($department) ?>
            <?php endif; ?>
        </div>

    </div>

    <nav class="nav">

        <div class="nav-section">
            Main
        </div>

        <a href="admin.php" class="active">
            <span class="icon">⌂</span>
            Dashboard
        </a>

        <?php if ($isSuperAdmin || $isMIS || $isIGP || $isLibrary): ?>

            <a href="student_access.php">
                <span class="icon">🎓</span>
                Student Access
            </a>

        <?php endif; ?>

        <?php if ($isSuperAdmin || $isMIS): ?>

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

        <?php if ($isSuperAdmin): ?>

            <a href="user_management.php">
                <span class="icon">👥</span>
                User Management
            </a>

        <?php endif; ?>

        <?php if ($isSuperAdmin || $isMIS || $isSecurity): ?>

            <a href="attendance_logs.php">
                <span class="icon">✓</span>
                Attendance Logs
            </a>

        <?php endif; ?>

        <?php if ($isSuperAdmin || $isMIS): ?>

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

        <?php if ($isSuperAdmin || $isSecurity): ?>

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
            $isSuperAdmin ||
            $isSecurity ||
            $isCCDU ||
            $isGuidance
        ): ?>

            <a href="violations.php">
                <span class="icon">⚠</span>
                Student Violations
            </a>

        <?php endif; ?>

        <?php if ($isSuperAdmin): ?>

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

        <a href="display.php" target="_blank">
            <span class="icon">▣</span>
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

<main class="main">

    <!-- TOPBAR -->

    <header class="topbar">

        <div>

            <div class="page-title">
                Dashboard
            </div>

            <div class="page-date">
                <?= e($currentDate) ?>
                •
                <?= e($currentTime) ?>
            </div>

        </div>

        <div class="top-user">

            <div class="avatar">
                <?= e(strtoupper(substr($fullName, 0, 1))) ?>
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

        <!-- WELCOME -->

        <section class="welcome">

            <h1>
                Welcome back, <?= e($fullName) ?>!
            </h1>

            <p>
                Monitor student attendance, gate activity,
                bypass transactions, and SmartGate operations
                from one centralized dashboard.
            </p>

        </section>


        <!-- ==================================================
             STATISTICS
        =================================================== -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Total Students
                    </div>

                    <div class="stat-icon">
                        👨‍🎓
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($totalStudents) ?>
                </div>

                <div class="stat-note">
                    Registered student accounts
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Active Students
                    </div>

                    <div class="stat-icon">
                        ✓
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($activeStudents) ?>
                </div>

                <div class="stat-note">
                    Active SmartGate accounts
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Currently IN
                    </div>

                    <div class="stat-icon">
                        ↗
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($currentIn) ?>
                </div>

                <div class="stat-note">
                    Students currently inside
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Currently OUT
                    </div>

                    <div class="stat-icon">
                        ↙
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($currentOut) ?>
                </div>

                <div class="stat-note">
                    Students currently outside
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Today's Scans
                    </div>

                    <div class="stat-icon">
                        ▣
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($todayScans) ?>
                </div>

                <div class="stat-note">
                    All IN and OUT scans today
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Today's IN
                    </div>

                    <div class="stat-icon">
                        ↑
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($todayIn) ?>
                </div>

                <div class="stat-note">
                    Entry transactions today
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Today's OUT
                    </div>

                    <div class="stat-icon">
                        ↓
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($todayOut) ?>
                </div>

                <div class="stat-note">
                    Exit transactions today
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Pending Violations
                    </div>

                    <div class="stat-icon">
                        ⚠
                    </div>

                </div>

                <div class="stat-number">
                    <?= number_format($pendingViolations) ?>
                </div>

                <div class="stat-note">
                    Student cases requiring action
                </div>

            </div>

        </section>


        <?php if (false): ?>

        <!-- ==================================================
             QUICK ACCESS
        =================================================== -->

        <div class="section-title">

            <h2>
                Quick Access
            </h2>

            <span>
                Available modules for your account
            </span>

        </div>


        <section class="quick-grid">

            <?php if ($isSuperAdmin || $isMIS): ?>

                <a
                    class="quick-card"
                    href="student_management.php"
                >

                    <div class="quick-icon">
                        👨‍🎓
                    </div>

                    <div class="quick-title">
                        Student Management
                    </div>

                    <div class="quick-desc">
                        Manage student accounts,
                        photos, QR codes, and status.
                    </div>

                </a>


                <a
                    class="quick-card"
                    href="mis_upload.php"
                >

                    <div class="quick-icon">
                        ⬆
                    </div>

                    <div class="quick-title">
                        MIS Student Upload
                    </div>

                    <div class="quick-desc">
                        Import or update students
                        using CSV.
                    </div>

                </a>


                <a
                    class="quick-card"
                    href="student_attendance.php"
                >

                    <div class="quick-icon">
                        ▣
                    </div>

                    <div class="quick-title">
                        Student Attendance
                    </div>

                    <div class="quick-desc">
                        Search and review the
                        attendance history of students.
                    </div>

                </a>

            <?php endif; ?>


            <?php if ($isSuperAdmin || $isMIS || $isSecurity): ?>

                <a
                    class="quick-card"
                    href="attendance_logs.php"
                >

                    <div class="quick-icon">
                        ✓
                    </div>

                    <div class="quick-title">
                        Attendance Logs
                    </div>

                    <div class="quick-desc">
                        Review IN and OUT
                        gate transactions.
                    </div>

                </a>

            <?php endif; ?>


            <?php if ($isSuperAdmin || $isSecurity): ?>

                <a
                    class="quick-card"
                    href="bypass.php"
                >

                    <div class="quick-icon">
                        ⚡
                    </div>

                    <div class="quick-title">
                        Bypass
                    </div>

                    <div class="quick-desc">
                        Process authorized
                        student or visitor bypass.
                    </div>

                </a>


                <a
                    class="quick-card"
                    href="bypass_logs.php"
                >

                    <div class="quick-icon">
                        ↪
                    </div>

                    <div class="quick-title">
                        Bypass Logs
                    </div>

                    <div class="quick-desc">
                        Review all bypass
                        transactions.
                    </div>

                </a>

            <?php endif; ?>


            <?php if (
                $isSuperAdmin ||
                $isSecurity ||
                $isCCDU ||
                $isGuidance
            ): ?>

                <a
                    class="quick-card"
                    href="violations.php"
                >

                    <div class="quick-icon">
                        ⚠
                    </div>

                    <div class="quick-title">
                        Student Violations
                    </div>

                    <div class="quick-desc">
                        Manage, review, and
                        resolve student violations.
                    </div>

                </a>

            <?php endif; ?>


            <?php if ($isSuperAdmin || $isMIS): ?>

                <a
                    class="quick-card"
                    href="analytics.php"
                >

                    <div class="quick-icon">
                        ▥
                    </div>

                    <div class="quick-title">
                        Analytics
                    </div>

                    <div class="quick-desc">
                        Analyze attendance,
                        violations, bypass, and CCDU data.
                    </div>

                </a>


                <a
                    class="quick-card"
                    href="mis_reports.php"
                >

                    <div class="quick-icon">
                        ▤
                    </div>

                    <div class="quick-title">
                        MIS Reports
                    </div>

                    <div class="quick-desc">
                        Generate filtered
                        attendance reports and PDF.
                    </div>

                </a>

            <?php endif; ?>


            <?php if ($isSuperAdmin): ?>

                <a
                    class="quick-card"
                    href="user_management.php"
                >

                    <div class="quick-icon">
                        👥
                    </div>

                    <div class="quick-title">
                        User Management
                    </div>

                    <div class="quick-desc">
                        Manage system users,
                        roles, and account status.
                    </div>

                </a>


                <a
                    class="quick-card"
                    href="audit_logs.php"
                >

                    <div class="quick-icon">
                        ◷
                    </div>

                    <div class="quick-title">
                        Audit Trail
                    </div>

                    <div class="quick-desc">
                        Track important system
                        actions and changes.
                    </div>

                </a>

            <?php endif; ?>


            <?php if ($isSuperAdmin || $isMIS): ?>

                <a
                    class="quick-card"
                    href="archived_logs.php"
                >

                    <div class="quick-icon">
                        ▱
                    </div>

                    <div class="quick-title">
                        Archived Logs
                    </div>

                    <div class="quick-desc">
                        Review previously archived
                        attendance records.
                    </div>

                </a>

            <?php endif; ?>


            <?php if ($isSuperAdmin || $isMIS || $isIGP || $isLibrary): ?>

                <a
                    class="quick-card"
                    href="student_access.php"
                >

                    <div class="quick-icon">
                        🎓
                    </div>

                    <div class="quick-title">
                        Student Access
                    </div>

                    <div class="quick-desc">
                        Search and view student access,
                        status, and basic information.
                    </div>

                </a>

            <?php endif; ?>


            <a
                class="quick-card"
                href="display.php"
                target="_blank"
            >

                <div class="quick-icon">
                    🖥
                </div>

                <div class="quick-title">
                    SmartGate Display
                </div>

                <div class="quick-desc">
                    Open the public display
                    for scanned students.
                </div>

            </a>

        </section>

        <?php endif; ?>


        <!-- ==================================================
             RECENT ACTIVITY
        =================================================== -->

        <div class="two-column">


            <!-- RECENT ATTENDANCE -->

            <section class="panel">

                <div class="panel-header">

                    <div class="panel-title">
                        Recent Attendance
                    </div>

                    <?php if (
                        $isSuperAdmin ||
                        $isMIS ||
                        $isSecurity
                    ): ?>

                        <a
                            class="panel-link"
                            href="attendance_logs.php"
                        >
                            View All
                        </a>

                    <?php endif; ?>

                </div>

                <div class="panel-body">

                    <?php if (count($recentAttendance) > 0): ?>

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
                                        Direction
                                    </th>

                                    <th>
                                        Time
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach (
                                $recentAttendance
                                as $attendance
                            ): ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e(
                                                $attendance["full_name"]
                                                ?: "Unknown Student"
                                            ) ?>
                                        </strong>

                                        <br>

                                        <small>
                                            <?= e(
                                                $attendance["student_id"]
                                            ) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <?= e(
                                            $attendance["program"]
                                        ) ?>

                                    </td>

                                    <td>

                                        <?php if (
                                            $attendance["direction"]
                                            === "IN"
                                        ): ?>

                                            <span class="badge badge-in">
                                                IN
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-out">
                                                OUT
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?= e(
                                            date(
                                                "M d, h:i A",
                                                strtotime(
                                                    $attendance["scan_time"]
                                                )
                                            )
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php else: ?>

                        <div class="empty">
                            No attendance activity yet.
                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <!-- RECENT BYPASS -->

            <section class="panel">

                <div class="panel-header">

                    <div class="panel-title">
                        Recent Bypass Activity
                    </div>

                    <?php if (
                        $isSuperAdmin ||
                        $isSecurity
                    ): ?>

                        <a
                            class="panel-link"
                            href="bypass_logs.php"
                        >
                            View All
                        </a>

                    <?php endif; ?>

                </div>

                <div class="panel-body">

                    <?php if (count($recentBypass) > 0): ?>

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Person
                                    </th>

                                    <th>
                                        Direction
                                    </th>

                                    <th>
                                        Time
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach (
                                $recentBypass
                                as $bypass
                            ): ?>

                                <tr>

                                    <td>

                                        <strong>

                                            <?php

                                            if (
                                                !empty(
                                                    $bypass["student_id"]
                                                )
                                            ) {

                                                echo e(
                                                    $bypass["full_name"]
                                                    ?: $bypass["student_id"]
                                                );

                                            } else {

                                                echo e(
                                                    $bypass["visitor_name"]
                                                    ?: "Visitor"
                                                );

                                            }

                                            ?>

                                        </strong>

                                        <br>

                                        <span
                                            class="badge badge-bypass"
                                        >
                                            BYPASS
                                        </span>

                                    </td>

                                    <td>

                                        <?php if (
                                            $bypass["direction"]
                                            === "IN"
                                        ): ?>

                                            <span class="badge badge-in">
                                                IN
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-out">
                                                OUT
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?= e(
                                            date(
                                                "M d, h:i A",
                                                strtotime(
                                                    $bypass["bypass_time"]
                                                )
                                            )
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php else: ?>

                        <div class="empty">
                            No bypass activity yet.
                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>

    </div>

</main>

</div>

</body>
</html>
