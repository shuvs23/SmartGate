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

/* =========================================================
   FILTERS
========================================================= */

$year     = trim($_GET['year'] ?? '');
$semester = trim($_GET['semester'] ?? '');
$month    = trim($_GET['month'] ?? '');
$week     = trim($_GET['week'] ?? '');
$day      = trim($_GET['day'] ?? '');

$where = [];
$params = [];
$types = '';

/*
    Attendance filters
*/

if ($year !== '') {
    $where[] = "YEAR(a.scan_time) = ?";
    $params[] = $year;
    $types .= "i";
}

if ($semester !== '') {
    if ($semester === '1st') {
        $where[] = "MONTH(a.scan_time) BETWEEN 8 AND 12";
    } elseif ($semester === '2nd') {
        $where[] = "MONTH(a.scan_time) BETWEEN 1 AND 5";
    } elseif ($semester === 'Summer') {
        $where[] = "MONTH(a.scan_time) BETWEEN 6 AND 7";
    }
}

if ($month !== '') {
    $where[] = "MONTH(a.scan_time) = ?";
    $params[] = $month;
    $types .= "i";
}

if ($week !== '') {
    $where[] = "WEEK(a.scan_time, 1) = ?";
    $params[] = $week;
    $types .= "i";
}

if ($day !== '') {
    $where[] = "DATE(a.scan_time) = ?";
    $params[] = $day;
    $types .= "s";
}

$attendanceWhere = '';

if (!empty($where)) {
    $attendanceWhere = "WHERE " . implode(" AND ", $where);
}


/* =========================================================
   HELPER
========================================================= */

function fetchScalar($conn, $sql, $params = [], $types = '')
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_row();

    $stmt->close();

    return $row[0] ?? 0;
}


/* =========================================================
   ATTENDANCE SUMMARY
========================================================= */

$totalAttendance = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM attendance_logs a
     $attendanceWhere",
    $params,
    $types
);

$inCount = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM attendance_logs a
     $attendanceWhere " .
     (!empty($attendanceWhere) ? " AND " : "WHERE ") .
     "a.direction = 'IN'",
    $params,
    $types
);

$outCount = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM attendance_logs a
     $attendanceWhere " .
     (!empty($attendanceWhere) ? " AND " : "WHERE ") .
     "a.direction = 'OUT'",
    $params,
    $types
);


/* =========================================================
   STUDENT TOTALS
========================================================= */

$totalStudents = fetchScalar(
    $conn,
    "SELECT COUNT(*) FROM students WHERE is_active = 1"
);

$currentIn = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM students
     WHERE is_active = 1
     AND current_status = 'IN'"
);

$currentOut = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM students
     WHERE is_active = 1
     AND current_status = 'OUT'"
);


/* =========================================================
   BYPASS ANALYTICS
========================================================= */

$bypassWhere = [];
$bypassParams = [];
$bypassTypes = '';

if ($year !== '') {
    $bypassWhere[] = "YEAR(b.bypass_time) = ?";
    $bypassParams[] = $year;
    $bypassTypes .= "i";
}

if ($semester !== '') {
    if ($semester === '1st') {
        $bypassWhere[] = "MONTH(b.bypass_time) BETWEEN 8 AND 12";
    } elseif ($semester === '2nd') {
        $bypassWhere[] = "MONTH(b.bypass_time) BETWEEN 1 AND 5";
    } elseif ($semester === 'Summer') {
        $bypassWhere[] = "MONTH(b.bypass_time) BETWEEN 6 AND 7";
    }
}

if ($month !== '') {
    $bypassWhere[] = "MONTH(b.bypass_time) = ?";
    $bypassParams[] = $month;
    $bypassTypes .= "i";
}

if ($week !== '') {
    $bypassWhere[] = "WEEK(b.bypass_time, 1) = ?";
    $bypassParams[] = $week;
    $bypassTypes .= "i";
}

if ($day !== '') {
    $bypassWhere[] = "DATE(b.bypass_time) = ?";
    $bypassParams[] = $day;
    $bypassTypes .= "s";
}

$bypassFilter = '';

if (!empty($bypassWhere)) {
    $bypassFilter = "WHERE " . implode(" AND ", $bypassWhere);
}

$totalBypass = fetchScalar(
    $conn,
    "SELECT COUNT(*) FROM bypass_logs b $bypassFilter",
    $bypassParams,
    $bypassTypes
);

$bypassStudents = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM bypass_logs b
     $bypassFilter " .
     (!empty($bypassFilter) ? " AND " : "WHERE ") .
     "b.student_id IS NOT NULL",
    $bypassParams,
    $bypassTypes
);

$bypassVisitors = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM bypass_logs b
     $bypassFilter " .
     (!empty($bypassFilter) ? " AND " : "WHERE ") .
     "b.student_id IS NULL",
    $bypassParams,
    $bypassTypes
);


/* =========================================================
   VIOLATION ANALYTICS
========================================================= */

$violationWhere = [];
$violationParams = [];
$violationTypes = '';

if ($year !== '') {
    $violationWhere[] = "YEAR(v.violation_time) = ?";
    $violationParams[] = $year;
    $violationTypes .= "i";
}

if ($semester !== '') {
    if ($semester === '1st') {
        $violationWhere[] = "MONTH(v.violation_time) BETWEEN 8 AND 12";
    } elseif ($semester === '2nd') {
        $violationWhere[] = "MONTH(v.violation_time) BETWEEN 1 AND 5";
    } elseif ($semester === 'Summer') {
        $violationWhere[] = "MONTH(v.violation_time) BETWEEN 6 AND 7";
    }
}

if ($month !== '') {
    $violationWhere[] = "MONTH(v.violation_time) = ?";
    $violationParams[] = $month;
    $violationTypes .= "i";
}

if ($week !== '') {
    $violationWhere[] = "WEEK(v.violation_time, 1) = ?";
    $violationParams[] = $week;
    $violationTypes .= "i";
}

if ($day !== '') {
    $violationWhere[] = "DATE(v.violation_time) = ?";
    $violationParams[] = $day;
    $violationTypes .= "s";
}

$violationFilter = '';

if (!empty($violationWhere)) {
    $violationFilter = "WHERE " . implode(" AND ", $violationWhere);
}

$totalViolations = fetchScalar(
    $conn,
    "SELECT COUNT(*) FROM student_violations v $violationFilter",
    $violationParams,
    $violationTypes
);

$pendingViolations = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM student_violations v
     $violationFilter " .
     (!empty($violationFilter) ? " AND " : "WHERE ") .
     "v.status = 'Pending'",
    $violationParams,
    $violationTypes
);

$referredViolations = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM student_violations v
     $violationFilter " .
     (!empty($violationFilter) ? " AND " : "WHERE ") .
     "v.status = 'Referred'",
    $violationParams,
    $violationTypes
);

$resolvedViolations = fetchScalar(
    $conn,
    "SELECT COUNT(*) 
     FROM student_violations v
     $violationFilter " .
     (!empty($violationFilter) ? " AND " : "WHERE ") .
     "v.status = 'Resolved'",
    $violationParams,
    $violationTypes
);


/* =========================================================
   CCDU ANALYTICS
========================================================= */

$ccduViolations = fetchScalar(
    $conn,
    "SELECT COUNT(*)
     FROM student_violations v
     INNER JOIN users u
        ON v.recorded_by = u.id
     $violationFilter " .
     (!empty($violationFilter) ? " AND " : "WHERE ") .
     "u.role = 'CCDU'",
    $violationParams,
    $violationTypes
);

$ccduPending = fetchScalar(
    $conn,
    "SELECT COUNT(*)
     FROM student_violations v
     INNER JOIN users u
        ON v.recorded_by = u.id
     $violationFilter " .
     (!empty($violationFilter) ? " AND " : "WHERE ") .
     "u.role = 'CCDU'
      AND v.status = 'Pending'",
    $violationParams,
    $violationTypes
);

$ccduResolved = fetchScalar(
    $conn,
    "SELECT COUNT(*)
     FROM student_violations v
     INNER JOIN users u
        ON v.recorded_by = u.id
     $violationFilter " .
     (!empty($violationFilter) ? " AND " : "WHERE ") .
     "u.role = 'CCDU'
      AND v.status = 'Resolved'",
    $violationParams,
    $violationTypes
);


/* =========================================================
   PROGRAM DISTRIBUTION
========================================================= */

$programData = [];

$result = $conn->query("
    SELECT program, COUNT(*) AS total
    FROM students
    WHERE is_active = 1
    GROUP BY program
    ORDER BY total DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $programData[] = $row;
    }
}


/* =========================================================
   DAILY ATTENDANCE TREND
========================================================= */

$dailyData = [];

$stmt = $conn->prepare("
    SELECT 
        DATE(a.scan_time) AS scan_date,
        SUM(a.direction = 'IN') AS total_in,
        SUM(a.direction = 'OUT') AS total_out
    FROM attendance_logs a
    $attendanceWhere
    GROUP BY DATE(a.scan_time)
    ORDER BY scan_date ASC
    LIMIT 31
");

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dailyData[] = $row;
    }

    $stmt->close();
}


/* =========================================================
   VIOLATION STATUS DATA
========================================================= */

$violationStatusData = [
    'Pending' => $pendingViolations,
    'Referred' => $referredViolations,
    'Resolved' => $resolvedViolations
];


/* =========================================================
   VIOLATION TYPES
========================================================= */

$violationTypesData = [];

$stmt = $conn->prepare("
    SELECT
        violation_type,
        COUNT(*) AS total
    FROM student_violations v
    $violationFilter
    GROUP BY violation_type
    ORDER BY total DESC
    LIMIT 10
");

if ($stmt) {

    if (!empty($violationParams)) {
        $stmt->bind_param(
            $violationTypes,
            ...$violationParams
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $violationTypesData[] = $row;
    }

    $stmt->close();
}


/* =========================================================
   RECENT ATTENDANCE
========================================================= */

$recentAttendance = [];

$result = $conn->query("
    SELECT
        a.student_id,
        s.full_name,
        s.program,
        a.direction,
        a.scan_time
    FROM attendance_logs a
    LEFT JOIN students s
        ON a.student_id = s.student_id
    ORDER BY a.scan_time DESC
    LIMIT 8
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentAttendance[] = $row;
    }
}


/* =========================================================
   MONTH LIST
========================================================= */

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


/* =========================================================
   EXPORT CSV
========================================================= */

if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename=smartgate_analytics_' .
        date('Y-m-d_H-i-s') .
        '.csv'
    );

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'SMARTGATE ANALYTICS'
    ]);

    fputcsv($output, []);

    fputcsv($output, [
        'Metric',
        'Value'
    ]);

    fputcsv($output, [
        'Total Attendance',
        $totalAttendance
    ]);

    fputcsv($output, [
        'Total IN',
        $inCount
    ]);

    fputcsv($output, [
        'Total OUT',
        $outCount
    ]);

    fputcsv($output, [
        'Active Students',
        $totalStudents
    ]);

    fputcsv($output, [
        'Currently IN',
        $currentIn
    ]);

    fputcsv($output, [
        'Currently OUT',
        $currentOut
    ]);

    fputcsv($output, [
        'Total Bypass',
        $totalBypass
    ]);

    fputcsv($output, [
        'Student Bypass',
        $bypassStudents
    ]);

    fputcsv($output, [
        'Visitor Bypass',
        $bypassVisitors
    ]);

    fputcsv($output, [
        'Total Violations',
        $totalViolations
    ]);

    fputcsv($output, [
        'Pending Violations',
        $pendingViolations
    ]);

    fputcsv($output, [
        'Referred Violations',
        $referredViolations
    ]);

    fputcsv($output, [
        'Resolved Violations',
        $resolvedViolations
    ]);

    fputcsv($output, [
        'CCDU Violations',
        $ccduViolations
    ]);

    fputcsv($output, [
        'CCDU Pending',
        $ccduPending
    ]);

    fputcsv($output, [
        'CCDU Resolved',
        $ccduResolved
    ]);

    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>SmartGate Analytics</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

.page {
    max-width: 1500px;
    margin: auto;
    padding: 25px;
}

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
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    border: 0;
    border-radius: 9px;
    padding: 11px 16px;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-secondary {
    background: #e2e8f0;
    color: #1e293b;
}

.btn-print {
    background: #0f172a;
    color: white;
}


/* FILTER */

.filter-card {
    background: white;
    padding: 20px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .06);
    margin-bottom: 22px;
}

.filter-title {
    font-size: 17px;
    font-weight: 700;
    margin-bottom: 15px;
}

.filters {
    display: grid;
    grid-template-columns:
        repeat(6, minmax(120px, 1fr));
    gap: 12px;
}

.field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 6px;
}

.field select,
.field input {
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
}

.filter-buttons {
    display: flex;
    align-items: end;
    gap: 8px;
}


/* STAT CARDS */

.stats {
    display: grid;
    grid-template-columns:
        repeat(5, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.stat {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .06);
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
}

.stat-value {
    font-size: 30px;
    font-weight: 800;
    margin-top: 7px;
}


/* DASHBOARD GRID */

.grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .06);
}

.card h2 {
    margin: 0 0 18px;
    font-size: 18px;
}


/* BAR CHART */

.bar-row {
    margin-bottom: 15px;
}

.bar-header {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 6px;
}

.bar-track {
    height: 12px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: #2563eb;
    border-radius: 10px;
}


/* TREND */

.trend {
    display: flex;
    align-items: end;
    gap: 8px;
    height: 220px;
    padding-top: 20px;
}

.trend-day {
    flex: 1;
    height: 100%;
    display: flex;
    align-items: end;
    gap: 3px;
    min-width: 12px;
}

.trend-bar {
    flex: 1;
    min-height: 2px;
    border-radius: 4px 4px 0 0;
}

.trend-in {
    background: #16a34a;
}

.trend-out {
    background: #dc2626;
}

.trend-labels {
    display: flex;
    gap: 8px;
}

.trend-label {
    flex: 1;
    text-align: center;
    font-size: 9px;
    color: #64748b;
    overflow: hidden;
}


/* STATUS */

.status-row {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.status-name {
    width: 90px;
    font-size: 13px;
    font-weight: 700;
}

.status-track {
    flex: 1;
    height: 14px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.status-fill {
    height: 100%;
}

.pending {
    background: #f59e0b;
}

.referred {
    background: #2563eb;
}

.resolved {
    background: #16a34a;
}


/* CCDU */

.ccdu-box {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 12px;
}

.ccdu-item {
    background: #f8fafc;
    border-radius: 10px;
    padding: 16px;
}

.ccdu-item span {
    display: block;
    font-size: 12px;
    color: #64748b;
}

.ccdu-item strong {
    display: block;
    font-size: 25px;
    margin-top: 5px;
}


/* TABLE */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    font-size: 12px;
    color: #64748b;
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 12px;
    border-bottom: 1px solid #eef2f7;
    font-size: 13px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
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


/* RESPONSIVE */

@media(max-width:1100px) {

    .stats {
        grid-template-columns:
            repeat(3, 1fr);
    }

    .filters {
        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media(max-width:750px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .stats,
    .grid {
        grid-template-columns: 1fr;
    }

    .filters {
        grid-template-columns: 1fr;
    }

    .ccdu-box {
        grid-template-columns: 1fr;
    }

}


/* PRINT */

@media print {

    body {
        background: white;
    }

    .page {
        max-width: none;
        padding: 0;
    }

    .actions,
    .filter-card {
        display: none;
    }

    .card,
    .stat {
        box-shadow: none;
        border: 1px solid #ddd;
    }

}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<div class="page">

    <div class="header">

        <div>
            <h1>Analytics</h1>

            <p>
                Attendance, bypass, violation, and student
                monitoring overview
            </p>
        </div>

        <div class="actions">
            <a
                href="?<?php
                    echo http_build_query(
                        array_merge(
                            $_GET,
                            ['export' => 'csv']
                        )
                    );
                ?>"
                class="btn btn-primary"
            >
                Export CSV
            </a>

            <button
                onclick="window.print()"
                class="btn btn-print"
            >
                Print
            </button>

        </div>

    </div>


    <!-- FILTERS -->

    <div class="filter-card">

        <div class="filter-title">
            Analytics Filters
        </div>

        <form method="GET">

            <div class="filters">

                <div class="field">

                    <label>Year</label>

                    <input
                        type="number"
                        name="year"
                        placeholder="e.g. 2026"
                        value="<?= htmlspecialchars($year) ?>"
                    >

                </div>


                <div class="field">

                    <label>Semester</label>

                    <select name="semester">

                        <option value="">
                            All Semesters
                        </option>

                        <option
                            value="1st"
                            <?= $semester === '1st' ? 'selected' : '' ?>
                        >
                            1st Semester
                        </option>

                        <option
                            value="2nd"
                            <?= $semester === '2nd' ? 'selected' : '' ?>
                        >
                            2nd Semester
                        </option>

                        <option
                            value="Summer"
                            <?= $semester === 'Summer' ? 'selected' : '' ?>
                        >
                            Summer
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label>Month</label>

                    <select name="month">

                        <option value="">
                            All Months
                        </option>

                        <?php foreach ($months as $num => $name): ?>

                            <option
                                value="<?= $num ?>"
                                <?= (string)$month === (string)$num
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $name ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>Week</label>

                    <input
                        type="number"
                        name="week"
                        min="1"
                        max="53"
                        placeholder="1 - 53"
                        value="<?= htmlspecialchars($week) ?>"
                    >

                </div>


                <div class="field">

                    <label>Day</label>

                    <input
                        type="number"
                        name="day"
                        min="1"
                        max="31"
                        placeholder="1 - 31"
                        value="<?= htmlspecialchars($day) ?>"
                    >

                </div>


                <div class="filter-buttons">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="analytics.php"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>

                </div>

            </div>

        </form>

    </div>


    <!-- MAIN STATS -->

    <div class="stats">

        <div class="stat">

            <div class="stat-label">
                Total Scans
            </div>

            <div class="stat-value">
                <?= number_format($totalAttendance) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Total IN
            </div>

            <div class="stat-value">
                <?= number_format($inCount) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Total OUT
            </div>

            <div class="stat-value">
                <?= number_format($outCount) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Active Students
            </div>

            <div class="stat-value">
                <?= number_format($totalStudents) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Total Bypass
            </div>

            <div class="stat-value">
                <?= number_format($totalBypass) ?>
            </div>

        </div>

    </div>


    <!-- SECONDARY STATS -->

    <div class="stats">

        <div class="stat">

            <div class="stat-label">
                Currently IN
            </div>

            <div class="stat-value">
                <?= number_format($currentIn) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Currently OUT
            </div>

            <div class="stat-value">
                <?= number_format($currentOut) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Total Violations
            </div>

            <div class="stat-value">
                <?= number_format($totalViolations) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                CCDU Violations
            </div>

            <div class="stat-value">
                <?= number_format($ccduViolations) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Bypass Visitors
            </div>

            <div class="stat-value">
                <?= number_format($bypassVisitors) ?>
            </div>

        </div>

    </div>


    <!-- CHARTS -->

    <div class="grid">


        <!-- PROGRAM DISTRIBUTION -->

        <div class="card">

            <h2>
                Students by Program
            </h2>

            <?php

            $maxProgram = 1;

            foreach ($programData as $program) {

                if ((int)$program['total'] > $maxProgram) {
                    $maxProgram = (int)$program['total'];
                }

            }

            ?>

            <?php if (empty($programData)): ?>

                <p>No student data available.</p>

            <?php else: ?>

                <?php foreach ($programData as $program): ?>

                    <?php

                    $width =
                        ((int)$program['total'] / $maxProgram)
                        * 100;

                    ?>

                    <div class="bar-row">

                        <div class="bar-header">

                            <span>
                                <?= htmlspecialchars(
                                    $program['program']
                                ) ?>
                            </span>

                            <strong>
                                <?= number_format(
                                    $program['total']
                                ) ?>
                            </strong>

                        </div>

                        <div class="bar-track">

                            <div
                                class="bar-fill"
                                style="width: <?= $width ?>%;"
                            ></div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <!-- VIOLATION STATUS -->

        <div class="card">

            <h2>
                Violation Status
            </h2>

            <?php

            $maxViolation =
                max(
                    1,
                    $pendingViolations,
                    $referredViolations,
                    $resolvedViolations
                );

            ?>

            <div class="status-row">

                <div class="status-name">
                    Pending
                </div>

                <div class="status-track">

                    <div
                        class="status-fill pending"
                        style="width:
                        <?= ($pendingViolations / $maxViolation) * 100 ?>%;"
                    ></div>

                </div>

                <strong>
                    <?= number_format($pendingViolations) ?>
                </strong>

            </div>


            <div class="status-row">

                <div class="status-name">
                    Referred
                </div>

                <div class="status-track">

                    <div
                        class="status-fill referred"
                        style="width:
                        <?= ($referredViolations / $maxViolation) * 100 ?>%;"
                    ></div>

                </div>

                <strong>
                    <?= number_format($referredViolations) ?>
                </strong>

            </div>


            <div class="status-row">

                <div class="status-name">
                    Resolved
                </div>

                <div class="status-track">

                    <div
                        class="status-fill resolved"
                        style="width:
                        <?= ($resolvedViolations / $maxViolation) * 100 ?>%;"
                    ></div>

                </div>

                <strong>
                    <?= number_format($resolvedViolations) ?>
                </strong>

            </div>

        </div>


        <!-- CCDU -->

        <div class="card">

            <h2>
                CCDU Analytics
            </h2>

            <div class="ccdu-box">

                <div class="ccdu-item">

                    <span>
                        Total CCDU Cases
                    </span>

                    <strong>
                        <?= number_format($ccduViolations) ?>
                    </strong>

                </div>


                <div class="ccdu-item">

                    <span>
                        Pending
                    </span>

                    <strong>
                        <?= number_format($ccduPending) ?>
                    </strong>

                </div>


                <div class="ccdu-item">

                    <span>
                        Resolved
                    </span>

                    <strong>
                        <?= number_format($ccduResolved) ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- BYPASS -->

        <div class="card">

            <h2>
                Bypass Analytics
            </h2>

            <div class="ccdu-box">

                <div class="ccdu-item">

                    <span>
                        Total
                    </span>

                    <strong>
                        <?= number_format($totalBypass) ?>
                    </strong>

                </div>


                <div class="ccdu-item">

                    <span>
                        Students
                    </span>

                    <strong>
                        <?= number_format($bypassStudents) ?>
                    </strong>

                </div>


                <div class="ccdu-item">

                    <span>
                        Visitors
                    </span>

                    <strong>
                        <?= number_format($bypassVisitors) ?>
                    </strong>

                </div>

            </div>

        </div>

    </div>


    <!-- DAILY TREND -->

    <div class="card">

        <h2>
            Daily Attendance Trend
        </h2>

        <?php if (empty($dailyData)): ?>

            <p>
                No attendance data available for the selected filters.
            </p>

        <?php else: ?>

            <?php

            $maxDaily = 1;

            foreach ($dailyData as $dayData) {

                $total =
                    (int)$dayData['total_in'] +
                    (int)$dayData['total_out'];

                if ($total > $maxDaily) {
                    $maxDaily = $total;
                }

            }

            ?>

            <div class="trend">

                <?php foreach ($dailyData as $dayData): ?>

                    <?php

                    $inHeight =
                        ((int)$dayData['total_in'] / $maxDaily)
                        * 100;

                    $outHeight =
                        ((int)$dayData['total_out'] / $maxDaily)
                        * 100;

                    ?>

                    <div class="trend-day">

                        <div
                            class="trend-bar trend-in"
                            style="height:
                            <?= max(2, $inHeight) ?>%;"
                            title="IN:
                            <?= (int)$dayData['total_in'] ?>"
                        ></div>

                        <div
                            class="trend-bar trend-out"
                            style="height:
                            <?= max(2, $outHeight) ?>%;"
                            title="OUT:
                            <?= (int)$dayData['total_out'] ?>"
                        ></div>

                    </div>

                <?php endforeach; ?>

            </div>


            <div class="trend-labels">

                <?php foreach ($dailyData as $dayData): ?>

                    <div class="trend-label">

                        <?= htmlspecialchars(
                            date(
                                'm/d',
                                strtotime(
                                    $dayData['scan_date']
                                )
                            )
                        ) ?>

                    </div>

                <?php endforeach; ?>

            </div>

            <br>

            <small>
                <strong>Green:</strong> IN
                &nbsp;&nbsp;
                <strong>Red:</strong> OUT
            </small>

        <?php endif; ?>

    </div>


    <br>


    <!-- VIOLATION TYPES -->

    <div class="card">

        <h2>
            Top Violation Types
        </h2>

        <?php

        $maxViolationType = 1;

        foreach ($violationTypesData as $item) {

            if ((int)$item['total'] > $maxViolationType) {
                $maxViolationType =
                    (int)$item['total'];
            }

        }

        ?>

        <?php if (empty($violationTypesData)): ?>

            <p>
                No violation records found.
            </p>

        <?php else: ?>

            <?php foreach ($violationTypesData as $item): ?>

                <?php

                $width =
                    ((int)$item['total'] /
                    $maxViolationType) * 100;

                ?>

                <div class="bar-row">

                    <div class="bar-header">

                        <span>
                            <?= htmlspecialchars(
                                $item['violation_type']
                            ) ?>
                        </span>

                        <strong>
                            <?= number_format(
                                $item['total']
                            ) ?>
                        </strong>

                    </div>

                    <div class="bar-track">

                        <div
                            class="bar-fill"
                            style="width: <?= $width ?>%;"
                        ></div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <br>


    <!-- RECENT ATTENDANCE -->

    <div class="card">

        <h2>
            Recent Attendance Activity
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Student ID
                        </th>

                        <th>
                            Student Name
                        </th>

                        <th>
                            Program
                        </th>

                        <th>
                            Direction
                        </th>

                        <th>
                            Date & Time
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($recentAttendance)): ?>

                        <tr>

                            <td
                                colspan="5"
                                style="text-align:center;"
                            >
                                No attendance records.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach (
                            $recentAttendance
                            as $row
                        ): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['student_id']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['full_name']
                                        ?? 'Unknown'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['program']
                                        ?? '-'
                                    ) ?>
                                </td>

                                <td>

                                    <?php if (
                                        $row['direction']
                                        === 'IN'
                                    ): ?>

                                        <span
                                            class="badge badge-in"
                                        >
                                            IN
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge badge-out"
                                        >
                                            OUT
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['scan_time']
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</div>
</body>

</html>
