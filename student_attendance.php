<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

/* =========================================================
   ACCESS CONTROL
   ========================================================= */
$allowed_roles = ['super_admin', 'MIS'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   HELPERS
   ========================================================= */
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function selected($a, $b)
{
    return (string)$a === (string)$b ? 'selected' : '';
}

/* =========================================================
   FILTERS
   ========================================================= */
$search = trim($_GET['search'] ?? '');
$year = trim($_GET['year'] ?? '');
$semester = trim($_GET['semester'] ?? '');
$month = trim($_GET['month'] ?? '');
$week = trim($_GET['week'] ?? '');
$day = trim($_GET['day'] ?? '');
$direction = trim($_GET['direction'] ?? '');

$selected_student_id = trim($_GET['student_id'] ?? '');

/* =========================================================
   STUDENT SEARCH
   ========================================================= */
$students = [];

if ($search !== '') {

    $search_like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT
            student_id,
            full_name,
            program,
            year_level,
            section,
            email,
            parent_email,
            photo,
            current_status,
            is_active
        FROM students
        WHERE
            student_id LIKE ?
            OR full_name LIKE ?
            OR program LIKE ?
        ORDER BY full_name ASC
        LIMIT 50
    ");

    $stmt->bind_param(
        "sss",
        $search_like,
        $search_like,
        $search_like
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $stmt->close();
}

/* =========================================================
   SELECTED STUDENT
   ========================================================= */
$student = null;

if ($selected_student_id !== '') {

    $stmt = $conn->prepare("
        SELECT
            student_id,
            full_name,
            program,
            year_level,
            section,
            email,
            parent_email,
            photo,
            current_status,
            is_active,
            created_at,
            updated_at
        FROM students
        WHERE student_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $selected_student_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    $stmt->close();
}

/* =========================================================
   ATTENDANCE DATA
   ========================================================= */
$attendance = [];

$total_scans = 0;
$total_in = 0;
$total_out = 0;

/*
 * Only load attendance when a student has been selected.
 */
if ($student) {

    /*
     * Build dynamic WHERE clause safely.
     */
    $where = [
        "student_id = ?"
    ];

    $types = "s";
    $params = [$selected_student_id];

    /* -----------------------------------------------------
       YEAR
       ----------------------------------------------------- */
    if ($year !== '' && ctype_digit($year)) {

        $where[] = "YEAR(scan_time) = ?";
        $types .= "i";
        $params[] = (int)$year;
    }

    /* -----------------------------------------------------
       SEMESTER
       ----------------------------------------------------- */
    if ($semester !== '') {

        if ($semester === '1st') {

            /*
             * August - December
             */
            $where[] = "MONTH(scan_time) BETWEEN 8 AND 12";

        } elseif ($semester === '2nd') {

            /*
             * January - May
             */
            $where[] = "MONTH(scan_time) BETWEEN 1 AND 5";

        } elseif ($semester === 'Summer') {

            /*
             * June - July
             */
            $where[] = "MONTH(scan_time) BETWEEN 6 AND 7";
        }
    }

    /* -----------------------------------------------------
       MONTH
       ----------------------------------------------------- */
    if ($month !== '' && ctype_digit($month)) {

        $month_number = (int)$month;

        if ($month_number >= 1 && $month_number <= 12) {

            $where[] = "MONTH(scan_time) = ?";
            $types .= "i";
            $params[] = $month_number;
        }
    }

    /* -----------------------------------------------------
       WEEK
       ----------------------------------------------------- */
    if ($week !== '' && ctype_digit($week)) {

        $week_number = (int)$week;

        if ($week_number >= 1 && $week_number <= 53) {

            $where[] = "WEEK(scan_time, 1) = ?";
            $types .= "i";
            $params[] = $week_number;
        }
    }

    /* -----------------------------------------------------
       DAY
       ----------------------------------------------------- */
    if ($day !== '') {

        /*
         * Expected format:
         * YYYY-MM-DD
         */
        $date_object = DateTime::createFromFormat('Y-m-d', $day);

        if (
            $date_object &&
            $date_object->format('Y-m-d') === $day
        ) {

            $where[] = "DATE(scan_time) = ?";
            $types .= "s";
            $params[] = $day;
        }
    }

    /* -----------------------------------------------------
       DIRECTION
       ----------------------------------------------------- */
    if ($direction === 'IN' || $direction === 'OUT') {

        $where[] = "direction = ?";
        $types .= "s";
        $params[] = $direction;
    }

    /* -----------------------------------------------------
       FINAL QUERY
       ----------------------------------------------------- */
    $sql = "
        SELECT
            id,
            student_id,
            direction,
            scan_time,
            device,
            remarks
        FROM attendance_logs
        WHERE " . implode(" AND ", $where) . "
        ORDER BY scan_time DESC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $bind_names = [];
        $bind_names[] = $types;

        foreach ($params as $key => $value) {
            $bind_names[] = &$params[$key];
        }

        call_user_func_array(
            [$stmt, 'bind_param'],
            $bind_names
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }

        $stmt->close();
    }

    /* =====================================================
       FILTERED SUMMARY
       ===================================================== */
    $total_scans = count($attendance);

    foreach ($attendance as $record) {

        if ($record['direction'] === 'IN') {
            $total_in++;
        }

        if ($record['direction'] === 'OUT') {
            $total_out++;
        }
    }
}

/* =========================================================
   AVAILABLE YEARS
   ========================================================= */
$years = [];

$result = $conn->query("
    SELECT DISTINCT YEAR(scan_time) AS year_value
    FROM attendance_logs
    ORDER BY year_value DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if (!empty($row['year_value'])) {
            $years[] = $row['year_value'];
        }
    }
}

/* =========================================================
   CSV EXPORT
   ========================================================= */
if (
    isset($_GET['export']) &&
    $_GET['export'] === 'csv' &&
    $student
) {

    /*
     * Reuse currently loaded filtered attendance.
     */

    $filename =
        'student_attendance_' .
        preg_replace('/[^A-Za-z0-9_-]/', '_', $student['student_id']) .
        '_' .
        date('Ymd_His') .
        '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );

    $output = fopen('php://output', 'w');

    /*
     * UTF-8 BOM for Excel compatibility.
     */
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'Student ID',
        'Student Name',
        'Program',
        'Year Level',
        'Section',
        'Direction',
        'Scan Time',
        'Device',
        'Remarks'
    ]);

    foreach ($attendance as $record) {

        fputcsv($output, [
            $student['student_id'],
            $student['full_name'],
            $student['program'],
            $student['year_level'],
            $student['section'],
            $record['direction'],
            $record['scan_time'],
            $record['device'],
            $record['remarks']
        ]);
    }

    fclose($output);
    exit;
}

/* =========================================================
   CURRENT QUERY STRING FOR EXPORT
   ========================================================= */
$export_params = $_GET;
$export_params['export'] = 'csv';

$export_url = 'student_attendance.php?' .
    http_build_query($export_params);

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Student Attendance | SmartGate</title>

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
    color: #1f2937;
}

/* =========================================================
   MAIN CONTENT
   ========================================================= */

.student-attendance-page {
    max-width: 1500px;
    margin: 0 auto;
}

/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
}

.page-title h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
}

.page-title p {
    margin: 7px 0 0;
    color: #64748b;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* =========================================================
   BUTTONS
   ========================================================= */

.btn {
    border: none;
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}

.btn-primary {
    background: #0f4c81;
    color: #fff;
}

.btn-primary:hover {
    background: #0b3c66;
}

.btn-secondary {
    background: #e2e8f0;
    color: #334155;
}

.btn-secondary:hover {
    background: #cbd5e1;
}

.btn-success {
    background: #15803d;
    color: #fff;
}

.btn-success:hover {
    background: #166534;
}

.btn-light {
    background: #fff;
    color: #334155;
    border: 1px solid #dbe3ec;
}

.btn-light:hover {
    background: #f8fafc;
}

/* =========================================================
   SEARCH CARD
   ========================================================= */

.search-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.search-title {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 13px;
    color: #0f172a;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    min-width: 0;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
}

.search-input:focus {
    border-color: #0f4c81;
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.08);
}

/* =========================================================
   SEARCH RESULTS
   ========================================================= */

.search-results {
    margin-top: 15px;
    border-top: 1px solid #e2e8f0;
    padding-top: 15px;
}

.student-result {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    margin-bottom: 8px;
    background: #fafcff;
}

.student-result-info {
    min-width: 0;
}

.student-result-name {
    font-weight: 700;
    color: #0f172a;
}

.student-result-details {
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}

.no-results {
    color: #64748b;
    font-size: 13px;
    padding: 8px 0;
}

/* =========================================================
   STUDENT PROFILE
   ========================================================= */

.student-profile {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.profile-layout {
    display: flex;
    gap: 22px;
    align-items: center;
}

.student-photo {
    width: 105px;
    height: 105px;
    border-radius: 10px;
    object-fit: cover;
    background: #e2e8f0;
    border: 1px solid #dbe3ec;
    flex-shrink: 0;
}

.student-photo-placeholder {
    width: 105px;
    height: 105px;
    border-radius: 10px;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 12px;
    text-align: center;
    flex-shrink: 0;
}

.profile-main {
    flex: 1;
    min-width: 0;
}

.profile-name {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
}

.profile-id {
    font-size: 14px;
    color: #475569;
    margin-bottom: 14px;
}

.profile-grid {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(120px, 1fr));
    gap: 12px;
}

.profile-item {
    background: #f8fafc;
    border-radius: 8px;
    padding: 10px 12px;
}

.profile-label {
    display: block;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #64748b;
    margin-bottom: 4px;
}

.profile-value {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.status-badge {
    display: inline-flex;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.status-in {
    background: #dcfce7;
    color: #166534;
}

.status-out {
    background: #e2e8f0;
    color: #475569;
}

/* =========================================================
   STAT CARDS
   ========================================================= */

.stats-grid {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.stat-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 7px;
}

.stat-value {
    font-size: 27px;
    font-weight: 700;
    color: #0f172a;
}

.stat-description {
    margin-top: 5px;
    font-size: 11px;
    color: #94a3b8;
}

/* =========================================================
   FILTER CARD
   ========================================================= */

.filter-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.filter-title {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 15px;
    color: #0f172a;
}

.filters {
    display: grid;
    grid-template-columns:
        repeat(6, minmax(120px, 1fr));
    gap: 10px;
}

.filter-group {
    min-width: 0;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

.filter-control {
    width: 100%;
    padding: 9px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #fff;
    font-size: 13px;
    color: #334155;
}

.filter-actions {
    margin-top: 15px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* =========================================================
   ATTENDANCE TABLE
   ========================================================= */

.table-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.table-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
}

.table-subtitle {
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
}

.table-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

thead {
    background: #f8fafc;
}

th {
    text-align: left;
    padding: 12px 16px;
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 13px 16px;
    font-size: 13px;
    color: #334155;
    border-bottom: 1px solid #edf2f7;
}

tbody tr:hover {
    background: #f8fafc;
}

.direction-badge {
    display: inline-flex;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.direction-in {
    background: #dcfce7;
    color: #166534;
}

.direction-out {
    background: #fee2e2;
    color: #991b1b;
}

.empty-state {
    text-align: center;
    padding: 45px 20px;
    color: #64748b;
}

.empty-state-title {
    font-weight: 700;
    color: #334155;
    margin-bottom: 5px;
}

.empty-state-text {
    font-size: 13px;
}

/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 1100px) {

    .profile-grid {
        grid-template-columns:
            repeat(2, minmax(120px, 1fr));
    }

    .filters {
        grid-template-columns:
            repeat(3, minmax(120px, 1fr));
    }

}

@media (max-width: 700px) {

    .student-attendance-page {
        width: 100%;
    }

    .page-header {
        flex-direction: column;
    }

    .search-form {
        flex-direction: column;
    }

    .profile-layout {
        flex-direction: column;
        align-items: flex-start;
    }

    .profile-grid {
        grid-template-columns: 1fr;
        width: 100%;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .filters {
        grid-template-columns: 1fr;
    }

    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }

}

/* =========================================================
   PRINT
   ========================================================= */

@media print {

    body {
        background: #fff;
    }

    .smartgate-sidebar,
    .smartgate-mobile-toggle,
    .header-actions,
    .search-card,
    .filter-card,
    .table-actions {
        display: none !important;
    }

    .smartgate-main {
        margin-left: 0 !important;
        padding: 0 !important;
    }

    .student-attendance-page {
        max-width: none;
    }

    .student-profile,
    .stat-card,
    .table-card {
        box-shadow: none;
        border: 1px solid #ccc;
    }

    .table-wrapper {
        overflow: visible;
    }

    table {
        min-width: 0;
    }

    th,
    td {
        font-size: 10px;
        padding: 7px;
    }

    .page-title h1 {
        font-size: 22px;
    }

}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php
/*
 * Persistent SmartGate sidebar.
 */
include "smartgate_sidebar.php";
?>

<div class="smartgate-main sg-page-shell">

<div class="student-attendance-page">

    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>Student Attendance</h1>

            <p>
                View and review the complete attendance history
                of individual students.
            </p>

        </div>

        <div class="header-actions">

            <?php if ($student): ?>

                <a
                    href="<?= h($export_url) ?>"
                    class="btn btn-success"
                >
                    Export CSV
                </a>

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="window.print()"
                >
                    Print
                </button>

            <?php endif; ?>

            <a
                href="student_attendance.php"
                class="btn btn-light"
            >
                Reset
            </a>

        </div>

    </div>


    <!-- =====================================================
         STUDENT SEARCH
         ===================================================== -->

    <div class="search-card">

        <div class="search-title">
            Search Student
        </div>

        <form
            method="GET"
            action="student_attendance.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by Student ID, name, or program..."
                value="<?= h($search) ?>"
                autocomplete="off"
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>

        </form>

        <?php if ($search !== ''): ?>

            <div class="search-results">

                <?php if (count($students) > 0): ?>

                    <?php foreach ($students as $item): ?>

                        <div class="student-result">

                            <div class="student-result-info">

                                <div class="student-result-name">
                                    <?= h($item['full_name']) ?>
                                </div>

                                <div class="student-result-details">

                                    <?= h($item['student_id']) ?>
                                    &nbsp; • &nbsp;

                                    <?= h($item['program']) ?>
                                    &nbsp; • &nbsp;

                                    <?= h($item['year_level']) ?>
                                    <?= h($item['section']) ?>

                                </div>

                            </div>

                            <a
                                href="student_attendance.php?student_id=<?= urlencode($item['student_id']) ?>"
                                class="btn btn-primary"
                            >
                                View Attendance
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="no-results">
                        No student found matching
                        "<strong><?= h($search) ?></strong>".
                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>


    <?php if ($student): ?>

        <!-- =================================================
             STUDENT PROFILE
             ================================================= -->

        <div class="student-profile">

            <div class="profile-layout">

                <?php
                $photo_path = '';

                if (!empty($student['photo'])) {

                    $photo_path = $student['photo'];

                } else {

                    $photo_path =
                        'photos/' .
                        $student['student_id'] .
                        '.jpg';
                }

                $photo_exists = file_exists(__DIR__ . '/' . $photo_path);
                ?>

                <?php if ($photo_exists): ?>

                    <img
                        src="<?= h($photo_path) ?>"
                        alt="Student Photo"
                        class="student-photo"
                    >

                <?php else: ?>

                    <div class="student-photo-placeholder">
                        No Photo
                    </div>

                <?php endif; ?>


                <div class="profile-main">

                    <div class="profile-name">
                        <?= h($student['full_name']) ?>
                    </div>

                    <div class="profile-id">
                        Student ID:
                        <strong>
                            <?= h($student['student_id']) ?>
                        </strong>
                    </div>


                    <div class="profile-grid">

                        <div class="profile-item">

                            <span class="profile-label">
                                Program
                            </span>

                            <span class="profile-value">
                                <?= h($student['program']) ?>
                            </span>

                        </div>


                        <div class="profile-item">

                            <span class="profile-label">
                                Year & Section
                            </span>

                            <span class="profile-value">

                                <?= h($student['year_level']) ?>

                                <?= h($student['section']) ?>

                            </span>

                        </div>


                        <div class="profile-item">

                            <span class="profile-label">
                                Current Status
                            </span>

                            <span class="profile-value">

                                <?php if ($student['current_status'] === 'IN'): ?>

                                    <span class="status-badge status-in">
                                        CURRENTLY IN
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-out">
                                        CURRENTLY OUT
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="profile-item">

                            <span class="profile-label">
                                Account Status
                            </span>

                            <span class="profile-value">

                                <?= $student['is_active']
                                    ? 'Active'
                                    : 'Inactive'
                                ?>

                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             STATISTICS
             ================================================= -->

        <div class="stats-grid">

            <div class="stat-card">

                <div class="stat-label">
                    TOTAL SCANS
                </div>

                <div class="stat-value">
                    <?= number_format($total_scans) ?>
                </div>

                <div class="stat-description">
                    Records matching the selected filters
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    TOTAL IN
                </div>

                <div class="stat-value">
                    <?= number_format($total_in) ?>
                </div>

                <div class="stat-description">
                    Recorded entry scans
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    TOTAL OUT
                </div>

                <div class="stat-value">
                    <?= number_format($total_out) ?>
                </div>

                <div class="stat-description">
                    Recorded exit scans
                </div>

            </div>

        </div>


        <!-- =================================================
             FILTERS
             ================================================= -->

        <div class="filter-card">

            <div class="filter-title">
                Attendance Filters
            </div>

            <form
                method="GET"
                action="student_attendance.php"
            >

                <input
                    type="hidden"
                    name="student_id"
                    value="<?= h($selected_student_id) ?>"
                >

                <div class="filters">

                    <!-- YEAR -->

                    <div class="filter-group">

                        <label>
                            Year
                        </label>

                        <select
                            name="year"
                            class="filter-control"
                        >

                            <option value="">
                                All Years
                            </option>

                            <?php foreach ($years as $year_item): ?>

                                <option
                                    value="<?= h($year_item) ?>"
                                    <?= selected($year, $year_item) ?>
                                >
                                    <?= h($year_item) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SEMESTER -->

                    <div class="filter-group">

                        <label>
                            Semester
                        </label>

                        <select
                            name="semester"
                            class="filter-control"
                        >

                            <option value="">
                                All Semesters
                            </option>

                            <option
                                value="1st"
                                <?= selected($semester, '1st') ?>
                            >
                                1st Semester
                            </option>

                            <option
                                value="2nd"
                                <?= selected($semester, '2nd') ?>
                            >
                                2nd Semester
                            </option>

                            <option
                                value="Summer"
                                <?= selected($semester, 'Summer') ?>
                            >
                                Summer
                            </option>

                        </select>

                    </div>


                    <!-- MONTH -->

                    <div class="filter-group">

                        <label>
                            Month
                        </label>

                        <select
                            name="month"
                            class="filter-control"
                        >

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

                            foreach ($months as $number => $name):

                            ?>

                                <option
                                    value="<?= $number ?>"
                                    <?= selected($month, $number) ?>
                                >
                                    <?= $name ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- WEEK -->

                    <div class="filter-group">

                        <label>
                            Week
                        </label>

                        <select
                            name="week"
                            class="filter-control"
                        >

                            <option value="">
                                All Weeks
                            </option>

                            <?php for ($i = 1; $i <= 53; $i++): ?>

                                <option
                                    value="<?= $i ?>"
                                    <?= selected($week, $i) ?>
                                >
                                    Week <?= $i ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>


                    <!-- DAY -->

                    <div class="filter-group">

                        <label>
                            Specific Day
                        </label>

                        <input
                            type="date"
                            name="day"
                            class="filter-control"
                            value="<?= h($day) ?>"
                        >

                    </div>


                    <!-- DIRECTION -->

                    <div class="filter-group">

                        <label>
                            Direction
                        </label>

                        <select
                            name="direction"
                            class="filter-control"
                        >

                            <option value="">
                                All Directions
                            </option>

                            <option
                                value="IN"
                                <?= selected($direction, 'IN') ?>
                            >
                                IN
                            </option>

                            <option
                                value="OUT"
                                <?= selected($direction, 'OUT') ?>
                            >
                                OUT
                            </option>

                        </select>

                    </div>

                </div>


                <div class="filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="student_attendance.php?student_id=<?= urlencode($selected_student_id) ?>"
                        class="btn btn-secondary"
                    >
                        Clear Filters
                    </a>

                </div>

            </form>

        </div>


        <!-- =================================================
             ATTENDANCE HISTORY
             ================================================= -->

        <div class="table-card">

            <div class="table-header">

                <div>

                    <div class="table-title">
                        Attendance History
                    </div>

                    <div class="table-subtitle">

                        <?= number_format($total_scans) ?>
                        record(s) displayed

                    </div>

                </div>


                <div class="table-actions">

                    <a
                        href="<?= h($export_url) ?>"
                        class="btn btn-success"
                    >
                        Export CSV
                    </a>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        onclick="window.print()"
                    >
                        Print
                    </button>

                </div>

            </div>


            <?php if (count($attendance) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Direction
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Time
                                </th>

                                <th>
                                    Device
                                </th>

                                <th>
                                    Remarks
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($attendance as $index => $record): ?>

                            <?php
                            $scan_datetime =
                                new DateTime($record['scan_time']);

                            $direction_class =
                                $record['direction'] === 'IN'
                                    ? 'direction-in'
                                    : 'direction-out';
                            ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <td>

                                    <span
                                        class="
                                            direction-badge
                                            <?= $direction_class ?>
                                        "
                                    >
                                        <?= h($record['direction']) ?>
                                    </span>

                                </td>


                                <td>
                                    <?= h(
                                        $scan_datetime->format('M d, Y')
                                    ) ?>
                                </td>


                                <td>
                                    <?= h(
                                        $scan_datetime->format('h:i:s A')
                                    ) ?>
                                </td>


                                <td>
                                    <?= h(
                                        $record['device'] ?: 'SMARTGATE'
                                    ) ?>
                                </td>


                                <td>
                                    <?= $record['remarks']
                                        ? h($record['remarks'])
                                        : '—'
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-state-title">
                        No Attendance Records
                    </div>

                    <div class="empty-state-text">

                        No attendance records were found for this
                        student using the selected filters.

                    </div>

                </div>

            <?php endif; ?>

        </div>

    <?php else: ?>

        <!-- =================================================
             INITIAL STATE
             ================================================= -->

        <div class="table-card">

            <div class="empty-state">

                <div class="empty-state-title">
                    Select a Student
                </div>

                <div class="empty-state-text">

                    Search for a student above to view their
                    complete SmartGate attendance history.

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

</div>

</body>
</html>
