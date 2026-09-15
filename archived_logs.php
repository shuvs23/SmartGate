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
    http_response_code(403);
    die("Access denied.");
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

/* =========================================================
   FILTERS
   ========================================================= */

$search     = trim($_GET['search'] ?? '');
$year       = trim($_GET['year'] ?? '');
$semester   = trim($_GET['semester'] ?? '');
$month      = trim($_GET['month'] ?? '');
$week       = trim($_GET['week'] ?? '');
$day        = trim($_GET['day'] ?? '');
$direction  = trim($_GET['direction'] ?? '');

/* =========================================================
   CSV EXPORT
   ========================================================= */

if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    $where = [];
    $params = [];
    $types = "";

    if ($search !== '') {
        $where[] = "(
            a.student_id LIKE ?
            OR s.full_name LIKE ?
            OR s.program LIKE ?
            OR s.section LIKE ?
            OR a.device LIKE ?
            OR a.remarks LIKE ?
        )";

        $search_value = "%{$search}%";

        for ($i = 0; $i < 6; $i++) {
            $params[] = $search_value;
            $types .= "s";
        }
    }

    if ($year !== '') {
        $where[] = "YEAR(a.scan_time) = ?";
        $params[] = (int)$year;
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
        $params[] = (int)$month;
        $types .= "i";
    }

    if ($week !== '') {
        $where[] = "WEEK(a.scan_time, 1) = ?";
        $params[] = (int)$week;
        $types .= "i";
    }

    if ($day !== '') {
        $where[] = "DATE(a.scan_time) = ?";
        $params[] = $day;
        $types .= "s";
    }

    if ($direction !== '' && in_array($direction, ['IN', 'OUT'], true)) {
        $where[] = "a.direction = ?";
        $params[] = $direction;
        $types .= "s";
    }

    $where_sql = !empty($where)
        ? "WHERE " . implode(" AND ", $where)
        : "";

    $sql = "
        SELECT
            a.id,
            a.original_id,
            a.student_id,
            s.full_name,
            s.program,
            s.year_level,
            s.section,
            a.direction,
            a.scan_time,
            a.device,
            a.remarks,
            a.archived_by,
            u.full_name AS archived_by_name,
            a.archived_time
        FROM archived_attendance_logs a
        LEFT JOIN students s
            ON s.student_id = a.student_id
        LEFT JOIN users u
            ON u.id = a.archived_by
        $where_sql
        ORDER BY a.scan_time DESC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("Database query error.");
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $filename = "smartgate_archived_logs_" . date("Y-m-d_H-i-s") . ".csv";

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $output = fopen("php://output", "w");

    fputcsv($output, [
        "Archive ID",
        "Original Attendance ID",
        "Student ID",
        "Student Name",
        "Program",
        "Year Level",
        "Section",
        "Direction",
        "Scan Date & Time",
        "Device",
        "Remarks",
        "Archived By",
        "Archived Date & Time"
    ]);

    while ($row = $result->fetch_assoc()) {

        fputcsv($output, [
            $row['id'],
            $row['original_id'],
            $row['student_id'],
            $row['full_name'] ?? '',
            $row['program'] ?? '',
            $row['year_level'] ?? '',
            $row['section'] ?? '',
            $row['direction'],
            $row['scan_time'],
            $row['device'],
            $row['remarks'] ?? '',
            $row['archived_by_name'] ?? '',
            $row['archived_time']
        ]);
    }

    fclose($output);
    exit;
}

/* =========================================================
   BUILD FILTER QUERY
   ========================================================= */

$where = [];
$params = [];
$types = "";

if ($search !== '') {

    $where[] = "(
        a.student_id LIKE ?
        OR s.full_name LIKE ?
        OR s.program LIKE ?
        OR s.section LIKE ?
        OR a.device LIKE ?
        OR a.remarks LIKE ?
    )";

    $search_value = "%{$search}%";

    for ($i = 0; $i < 6; $i++) {
        $params[] = $search_value;
        $types .= "s";
    }
}

if ($year !== '') {
    $where[] = "YEAR(a.scan_time) = ?";
    $params[] = (int)$year;
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
    $params[] = (int)$month;
    $types .= "i";
}

if ($week !== '') {
    $where[] = "WEEK(a.scan_time, 1) = ?";
    $params[] = (int)$week;
    $types .= "i";
}

if ($day !== '') {
    $where[] = "DATE(a.scan_time) = ?";
    $params[] = $day;
    $types .= "s";
}

if ($direction !== '' && in_array($direction, ['IN', 'OUT'], true)) {
    $where[] = "a.direction = ?";
    $params[] = $direction;
    $types .= "s";
}

$where_sql = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

/* =========================================================
   MAIN DATA
   ========================================================= */

$sql = "
    SELECT
        a.id,
        a.original_id,
        a.student_id,
        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.photo,
        a.direction,
        a.scan_time,
        a.device,
        a.remarks,
        a.archived_by,
        u.full_name AS archived_by_name,
        a.archived_time
    FROM archived_attendance_logs a
    LEFT JOIN students s
        ON s.student_id = a.student_id
    LEFT JOIN users u
        ON u.id = a.archived_by
    $where_sql
    ORDER BY a.scan_time DESC
";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Database query error.");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$logs = [];

while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

/* =========================================================
   SUMMARY STATISTICS
   ========================================================= */

$total_filtered = count($logs);

$filtered_in = 0;
$filtered_out = 0;

foreach ($logs as $log) {

    if ($log['direction'] === 'IN') {
        $filtered_in++;
    }

    if ($log['direction'] === 'OUT') {
        $filtered_out++;
    }
}

/* =========================================================
   OVERALL STATISTICS
   ========================================================= */

$overall_total = 0;
$today_total = 0;
$overall_in = 0;
$overall_out = 0;

$stats_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN DATE(scan_time) = CURDATE() THEN 1 ELSE 0 END) AS today_total,
        SUM(CASE WHEN direction = 'IN' THEN 1 ELSE 0 END) AS total_in,
        SUM(CASE WHEN direction = 'OUT' THEN 1 ELSE 0 END) AS total_out
    FROM archived_attendance_logs
";

$stats_result = $conn->query($stats_sql);

if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {

    $overall_total = (int)$stats_row['total'];
    $today_total   = (int)$stats_row['today_total'];
    $overall_in    = (int)$stats_row['total_in'];
    $overall_out   = (int)$stats_row['total_out'];
}

/* =========================================================
   YEARS
   ========================================================= */

$years = [];

$year_result = $conn->query("
    SELECT DISTINCT YEAR(scan_time) AS year
    FROM archived_attendance_logs
    ORDER BY year DESC
");

if ($year_result) {

    while ($row = $year_result->fetch_assoc()) {

        if (!empty($row['year'])) {
            $years[] = $row['year'];
        }
    }
}

/* =========================================================
   MONTHS
   ========================================================= */

$months = [
    1  => 'January',
    2  => 'February',
    3  => 'March',
    4  => 'April',
    5  => 'May',
    6  => 'June',
    7  => 'July',
    8  => 'August',
    9  => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

$current_week = (int)date('W');

/* =========================================================
   HELPER
   ========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function build_query($extra = [])
{
    $current = $_GET;

    unset($current['export']);

    foreach ($extra as $key => $value) {

        if ($value === null || $value === '') {
            unset($current[$key]);
        } else {
            $current[$key] = $value;
        }
    }

    return http_build_query($current);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Archived Attendance Logs | SmartGate</title>

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

.container {
    width: 96%;
    max-width: 1500px;
    margin: 30px auto;
}

/* HEADER */

.page-header {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 28px;
    color: #123b67;
}

.page-header p {
    margin: 0;
    color: #64748b;
}

/* TOP NAV */

.top-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 18px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border: none;
    border-radius: 9px;
    padding: 10px 15px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
}

.btn-primary {
    background: #123b67;
    color: #ffffff;
}

.btn-secondary {
    background: #e8eef5;
    color: #123b67;
}

.btn-success {
    background: #198754;
    color: #ffffff;
}

.btn-print {
    background: #475569;
    color: #ffffff;
}

/* STATISTICS */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card {
    background: #ffffff;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 7px;
}

.stat-value {
    font-size: 30px;
    font-weight: 700;
    color: #123b67;
}

.stat-small {
    margin-top: 6px;
    font-size: 12px;
    color: #94a3b8;
}

/* FILTERS */

.filter-card {
    background: #ffffff;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
}

.filter-title {
    font-size: 17px;
    font-weight: 700;
    color: #123b67;
    margin-bottom: 15px;
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr repeat(6, 1fr);
    gap: 10px;
}

.form-control,
select {
    width: 100%;
    min-height: 42px;
    padding: 9px 11px;
    border: 1px solid #d7dee8;
    border-radius: 8px;
    background: #ffffff;
    font-size: 14px;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 13px;
}

/* TABLE */

.table-card {
    background: #ffffff;
    border-radius: 15px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
    overflow: hidden;
}

.table-header {
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h2 {
    margin: 0;
    font-size: 18px;
    color: #123b67;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1250px;
}

thead {
    background: #123b67;
    color: #ffffff;
}

th {
    padding: 13px 12px;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .3px;
    white-space: nowrap;
}

td {
    padding: 12px;
    border-bottom: 1px solid #edf1f5;
    font-size: 13px;
    vertical-align: middle;
}

tbody tr:hover {
    background: #f8fafc;
}

.student-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-photo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
}

.student-name {
    font-weight: 600;
}

.student-id {
    font-size: 12px;
    color: #64748b;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
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

.badge-archive {
    background: #e0e7ff;
    color: #3730a3;
}

.empty {
    padding: 50px;
    text-align: center;
    color: #64748b;
}

/* FOOTER */

.footer-summary {
    padding: 15px 20px;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #64748b;
    font-size: 13px;
}

/* RESPONSIVE */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 650px) {

    .container {
        width: 94%;
        margin: 15px auto;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .page-header h1 {
        font-size: 23px;
    }
}

/* PRINT */

@media print {

    body {
        background: #ffffff;
    }

    .container {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .top-actions,
    .filter-card,
    .stats,
    .footer-summary {
        display: none !important;
    }

    .page-header {
        box-shadow: none;
        padding: 0 0 15px;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .table-card {
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

    .student-cell {
        display: block;
    }
}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <h1>Archived Attendance Logs</h1>

        <p>
            Historical attendance records preserved after being archived from the active attendance logs.
        </p>

        <div class="top-actions">
            <a href="attendance_logs.php" class="btn btn-secondary">
                Attendance Logs
            </a>

            <a href="mis_reports.php" class="btn btn-secondary">
                MIS Reports
            </a>

            <a
                href="?<?= e(build_query(['export' => 'csv'])) ?>"
                class="btn btn-success"
            >
                Export CSV
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-print"
            >
                Print
            </button>

        </div>

    </div>


    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat-card">

            <div class="stat-label">
                TOTAL ARCHIVED
            </div>

            <div class="stat-value">
                <?= number_format($overall_total) ?>
            </div>

            <div class="stat-small">
                All archived attendance records
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                ARCHIVED TODAY
            </div>

            <div class="stat-value">
                <?= number_format($today_total) ?>
            </div>

            <div class="stat-small">
                Records archived from today's attendance
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                TOTAL IN
            </div>

            <div class="stat-value">
                <?= number_format($overall_in) ?>
            </div>

            <div class="stat-small">
                Archived entry transactions
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                TOTAL OUT
            </div>

            <div class="stat-value">
                <?= number_format($overall_out) ?>
            </div>

            <div class="stat-small">
                Archived exit transactions
            </div>

        </div>

    </div>


    <!-- FILTERS -->

    <div class="filter-card">

        <div class="filter-title">
            Search & Filter Archived Records
        </div>

        <form method="GET">

            <div class="filter-grid">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search student ID, name, program, section..."
                    value="<?= e($search) ?>"
                >


                <select name="year">

                    <option value="">Year</option>

                    <?php foreach ($years as $item_year): ?>

                        <option
                            value="<?= e($item_year) ?>"
                            <?= ($year == $item_year) ? 'selected' : '' ?>
                        >
                            <?= e($item_year) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <select name="semester">

                    <option value="">Semester</option>

                    <option
                        value="1st"
                        <?= ($semester === '1st') ? 'selected' : '' ?>
                    >
                        1st Semester
                    </option>

                    <option
                        value="2nd"
                        <?= ($semester === '2nd') ? 'selected' : '' ?>
                    >
                        2nd Semester
                    </option>

                    <option
                        value="Summer"
                        <?= ($semester === 'Summer') ? 'selected' : '' ?>
                    >
                        Summer
                    </option>

                </select>


                <select name="month">

                    <option value="">Month</option>

                    <?php foreach ($months as $month_number => $month_name): ?>

                        <option
                            value="<?= $month_number ?>"
                            <?= ((string)$month === (string)$month_number) ? 'selected' : '' ?>
                        >
                            <?= e($month_name) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <select name="week">

                    <option value="">Week</option>

                    <?php for ($w = 1; $w <= 53; $w++): ?>

                        <option
                            value="<?= $w ?>"
                            <?= ((string)$week === (string)$w) ? 'selected' : '' ?>
                        >
                            Week <?= $w ?>
                        </option>

                    <?php endfor; ?>

                </select>


                <input
                    type="date"
                    name="day"
                    class="form-control"
                    value="<?= e($day) ?>"
                    title="Specific Day"
                >


                <select name="direction">

                    <option value="">Direction</option>

                    <option
                        value="IN"
                        <?= ($direction === 'IN') ? 'selected' : '' ?>
                    >
                        IN
                    </option>

                    <option
                        value="OUT"
                        <?= ($direction === 'OUT') ? 'selected' : '' ?>
                    >
                        OUT
                    </option>

                </select>

            </div>


            <div class="filter-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply Filters
                </button>

                <a
                    href="archived_logs.php"
                    class="btn btn-secondary"
                >
                    Clear Filters
                </a>

            </div>

        </form>

    </div>


    <!-- TABLE -->

    <div class="table-card">

        <div class="table-header">

            <h2>
                Archived Records
                — <?= number_format($total_filtered) ?>
            </h2>

        </div>


        <div class="table-wrapper">

            <?php if (!empty($logs)): ?>

                <table>

                    <thead>

                        <tr>

                            <th>Archive ID</th>

                            <th>Student</th>

                            <th>Program</th>

                            <th>Year / Section</th>

                            <th>Direction</th>

                            <th>Scan Date & Time</th>

                            <th>Device</th>

                            <th>Remarks</th>

                            <th>Archived By</th>

                            <th>Archived Date & Time</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($logs as $log): ?>

                        <tr>

                            <td>

                                <span class="badge badge-archive">

                                    #<?= e($log['id']) ?>

                                </span>

                            </td>


                            <td>

                                <div class="student-cell">

                                    <?php

                                    $photo = trim((string)($log['photo'] ?? ''));

                                    if ($photo !== ''):

                                    ?>

                                        <img
                                            src="<?= e($photo) ?>"
                                            class="student-photo"
                                            alt="Student"
                                            onerror="this.style.display='none';"
                                        >

                                    <?php else: ?>

                                        <div class="student-photo"></div>

                                    <?php endif; ?>


                                    <div>

                                        <div class="student-name">

                                            <?= e($log['full_name'] ?? 'Unknown Student') ?>

                                        </div>

                                        <div class="student-id">

                                            <?= e($log['student_id']) ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <td>
                                <?= e($log['program'] ?? '-') ?>
                            </td>


                            <td>

                                <?= e($log['year_level'] ?? '-') ?>

                                /

                                <?= e($log['section'] ?? '-') ?>

                            </td>


                            <td>

                                <?php if ($log['direction'] === 'IN'): ?>

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
                                <?= e($log['scan_time']) ?>
                            </td>


                            <td>
                                <?= e($log['device'] ?? '-') ?>
                            </td>


                            <td>
                                <?= e($log['remarks'] ?? '-') ?>
                            </td>


                            <td>
                                <?= e($log['archived_by_name'] ?? 'System') ?>
                            </td>


                            <td>
                                <?= e($log['archived_time']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    <h3>No Archived Records Found</h3>

                    <p>
                        There are no archived attendance records matching the selected filters.
                    </p>

                </div>

            <?php endif; ?>

        </div>


        <div class="footer-summary">

            Showing
            <strong><?= number_format($total_filtered) ?></strong>
            filtered archived record(s).

            &nbsp; | &nbsp;

            IN:
            <strong><?= number_format($filtered_in) ?></strong>

            &nbsp; | &nbsp;

            OUT:
            <strong><?= number_format($filtered_out) ?></strong>

        </div>

    </div>

</div>

</div>
</body>
</html>
