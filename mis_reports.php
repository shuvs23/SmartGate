<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

/*
=========================================================
ACCESS CONTROL
=========================================================
*/
$allowed_roles = ['super_admin', 'MIS'];

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', $allowed_roles, true)
) {
    header("Location: login.php");
    exit;
}

/*
=========================================================
HELPERS
=========================================================
*/
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function selected($a, $b)
{
    return (string)$a === (string)$b ? 'selected' : '';
}

/*
=========================================================
FILTERS
=========================================================
*/
$year = trim($_GET['year'] ?? '');
$semester = trim($_GET['semester'] ?? '');
$month = trim($_GET['month'] ?? '');
$week = trim($_GET['week'] ?? '');
$day = trim($_GET['day'] ?? '');
$direction = trim($_GET['direction'] ?? '');
$program = trim($_GET['program'] ?? '');

/*
=========================================================
AVAILABLE YEARS
=========================================================
*/
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

/*
=========================================================
AVAILABLE PROGRAMS
=========================================================
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
=========================================================
BUILD ATTENDANCE FILTER
=========================================================
*/

$where = [];
$types = '';
$params = [];

/*
YEAR
*/
if ($year !== '' && ctype_digit($year)) {

    $where[] = "YEAR(a.scan_time) = ?";
    $types .= "i";
    $params[] = (int)$year;
}

/*
SEMESTER
*/
if ($semester === '1st') {

    /*
     * August - December
     */
    $where[] = "MONTH(a.scan_time) BETWEEN 8 AND 12";

} elseif ($semester === '2nd') {

    /*
     * January - May
     */
    $where[] = "MONTH(a.scan_time) BETWEEN 1 AND 5";

} elseif ($semester === 'Summer') {

    /*
     * June - July
     */
    $where[] = "MONTH(a.scan_time) BETWEEN 6 AND 7";
}

/*
MONTH
*/
if ($month !== '' && ctype_digit($month)) {

    $month_number = (int)$month;

    if ($month_number >= 1 && $month_number <= 12) {

        $where[] = "MONTH(a.scan_time) = ?";
        $types .= "i";
        $params[] = $month_number;
    }
}

/*
WEEK
*/
if ($week !== '' && ctype_digit($week)) {

    $week_number = (int)$week;

    if ($week_number >= 1 && $week_number <= 53) {

        $where[] = "WEEK(a.scan_time, 1) = ?";
        $types .= "i";
        $params[] = $week_number;
    }
}

/*
DAY
*/
if ($day !== '') {

    $date_object =
        DateTime::createFromFormat('Y-m-d', $day);

    if (
        $date_object &&
        $date_object->format('Y-m-d') === $day
    ) {

        $where[] = "DATE(a.scan_time) = ?";
        $types .= "s";
        $params[] = $day;
    }
}

/*
DIRECTION
*/
if (
    $direction === 'IN' ||
    $direction === 'OUT'
) {

    $where[] = "a.direction = ?";
    $types .= "s";
    $params[] = $direction;
}

/*
PROGRAM
*/
if ($program !== '') {

    $where[] = "s.program = ?";
    $types .= "s";
    $params[] = $program;
}

/*
FINAL WHERE
*/
$where_sql = '';

if (count($where) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

/*
=========================================================
ATTENDANCE QUERY
=========================================================
*/

$attendance = [];

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
        s.section

    FROM attendance_logs a

    LEFT JOIN students s
        ON s.student_id = a.student_id

    {$where_sql}

    ORDER BY a.scan_time DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if ($types !== '') {

        $bind_names = [];
        $bind_names[] = $types;

        foreach ($params as $key => $value) {
            $bind_names[] = &$params[$key];
        }

        call_user_func_array(
            [$stmt, 'bind_param'],
            $bind_names
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }

    $stmt->close();
}

/*
=========================================================
SUMMARY
=========================================================
*/

$total_records = count($attendance);

$total_in = 0;
$total_out = 0;

$unique_students = [];

foreach ($attendance as $record) {

    if ($record['direction'] === 'IN') {
        $total_in++;
    }

    if ($record['direction'] === 'OUT') {
        $total_out++;
    }

    if (!empty($record['student_id'])) {
        $unique_students[$record['student_id']] = true;
    }
}

$total_students = count($unique_students);

/*
=========================================================
PROGRAM SUMMARY
=========================================================
*/

$program_summary = [];

foreach ($attendance as $record) {

    $program_name =
        trim((string)($record['program'] ?? ''));

    if ($program_name === '') {
        $program_name = 'Unknown';
    }

    if (!isset($program_summary[$program_name])) {

        $program_summary[$program_name] = [
            'total' => 0,
            'in' => 0,
            'out' => 0
        ];
    }

    $program_summary[$program_name]['total']++;

    if ($record['direction'] === 'IN') {
        $program_summary[$program_name]['in']++;
    }

    if ($record['direction'] === 'OUT') {
        $program_summary[$program_name]['out']++;
    }
}

ksort($program_summary);

/*
=========================================================
DIRECTION PERCENTAGES
=========================================================
*/

$in_percentage = 0;
$out_percentage = 0;

if ($total_records > 0) {

    $in_percentage =
        round(
            ($total_in / $total_records) * 100,
            1
        );

    $out_percentage =
        round(
            ($total_out / $total_records) * 100,
            1
        );
}

/*
=========================================================
CSV EXPORT
=========================================================
*/

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'csv'
) {

    $filename =
        'smartgate_mis_report_' .
        date('Ymd_His') .
        '.csv';

    header(
        'Content-Type: text/csv; charset=utf-8'
    );

    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );

    $output =
        fopen('php://output', 'w');

    /*
     * UTF-8 BOM for Excel.
     */
    fprintf(
        $output,
        chr(0xEF) .
        chr(0xBB) .
        chr(0xBF)
    );

    fputcsv(
        $output,
        [
            'Student ID',
            'Student Name',
            'Program',
            'Year Level',
            'Section',
            'Direction',
            'Scan Time',
            'Device',
            'Remarks'
        ]
    );

    foreach ($attendance as $record) {

        fputcsv(
            $output,
            [
                $record['student_id'],
                $record['full_name'],
                $record['program'],
                $record['year_level'],
                $record['section'],
                $record['direction'],
                $record['scan_time'],
                $record['device'],
                $record['remarks']
            ]
        );
    }

    fclose($output);

    exit;
}

/*
=========================================================
PDF EXPORT
=========================================================
*/

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'pdf'
) {

    /*
     * TCPDF location.
     *
     * Keep this compatible with your existing
     * TCPDF installation.
     */

    $tcpdf_paths = [
        __DIR__ . '/tcpdf/tcpdf.php',
        __DIR__ . '/TCPDF/tcpdf.php',
        __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php'
    ];

    $tcpdf_loaded = false;

    foreach ($tcpdf_paths as $tcpdf_path) {

        if (file_exists($tcpdf_path)) {

            require_once $tcpdf_path;

            $tcpdf_loaded = true;

            break;
        }
    }

    if (!$tcpdf_loaded) {

        die(
            'TCPDF was not found. Please check your TCPDF installation.'
        );
    }


    /*
     * Create PDF.
     */

    $pdf = new TCPDF(
        'L',
        'mm',
        'A4',
        true,
        'UTF-8',
        false
    );


    $pdf->SetCreator('SmartGate');

    $pdf->SetAuthor('SmartGate');

    $pdf->SetTitle(
        'SmartGate Attendance Report'
    );

    $pdf->SetSubject(
        'MIS Attendance Report'
    );

    $pdf->setPrintHeader(false);

    $pdf->setPrintFooter(false);

    $pdf->SetMargins(
        10,
        10,
        10
    );

    $pdf->SetAutoPageBreak(
        true,
        10
    );

    $pdf->AddPage();


    /*
     * Report heading.
     */

    $pdf->SetFont(
        'helvetica',
        'B',
        18
    );

    $pdf->Cell(
        0,
        8,
        'SMARTGATE',
        0,
        1,
        'C'
    );


    $pdf->SetFont(
        'helvetica',
        'B',
        13
    );

    $pdf->Cell(
        0,
        7,
        'Attendance Report',
        0,
        1,
        'C'
    );


    $pdf->SetFont(
        'helvetica',
        '',
        8
    );

    $filter_text = 'Filters: ';

    $filter_parts = [];

    if ($year !== '') {
        $filter_parts[] =
            'Year: ' . $year;
    }

    if ($semester !== '') {
        $filter_parts[] =
            'Semester: ' . $semester;
    }

    if ($month !== '') {
        $filter_parts[] =
            'Month: ' . $month;
    }

    if ($week !== '') {
        $filter_parts[] =
            'Week: ' . $week;
    }

    if ($day !== '') {
        $filter_parts[] =
            'Day: ' . $day;
    }

    if ($direction !== '') {
        $filter_parts[] =
            'Direction: ' . $direction;
    }

    if ($program !== '') {
        $filter_parts[] =
            'Program: ' . $program;
    }

    if (count($filter_parts) === 0) {
        $filter_text .= 'All attendance records';
    } else {
        $filter_text .=
            implode(' | ', $filter_parts);
    }

    $pdf->MultiCell(
        0,
        5,
        $filter_text,
        0,
        'C'
    );


    $pdf->Cell(
        0,
        5,
        'Generated: ' .
        date('F d, Y h:i A'),
        0,
        1,
        'C'
    );

    $pdf->Ln(4);


    /*
     * Summary.
     */

    $pdf->SetFont(
        'helvetica',
        'B',
        9
    );

    $pdf->Cell(
        45,
        6,
        'Total Records',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        45,
        6,
        'Total Students',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        45,
        6,
        'Total IN',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        45,
        6,
        'Total OUT',
        1,
        1,
        'C'
    );


    $pdf->SetFont(
        'helvetica',
        '',
        9
    );

    $pdf->Cell(
        45,
        7,
        number_format($total_records),
        1,
        0,
        'C'
    );

    $pdf->Cell(
        45,
        7,
        number_format($total_students),
        1,
        0,
        'C'
    );

    $pdf->Cell(
        45,
        7,
        number_format($total_in),
        1,
        0,
        'C'
    );

    $pdf->Cell(
        45,
        7,
        number_format($total_out),
        1,
        1,
        'C'
    );

    $pdf->Ln(5);


    /*
     * Program summary heading.
     */

    $pdf->SetFont(
        'helvetica',
        'B',
        10
    );

    $pdf->Cell(
        0,
        6,
        'Summary by Program',
        0,
        1,
        'L'
    );


    /*
     * Program summary table.
     */

    $pdf->SetFont(
        'helvetica',
        'B',
        8
    );

    $pdf->Cell(
        65,
        6,
        'Program',
        1,
        0,
        'L'
    );

    $pdf->Cell(
        35,
        6,
        'Total',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        35,
        6,
        'IN',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        35,
        6,
        'OUT',
        1,
        1,
        'C'
    );


    $pdf->SetFont(
        'helvetica',
        '',
        8
    );

    foreach (
        $program_summary
        as $program_name => $summary
    ) {

        $pdf->Cell(
            65,
            6,
            $program_name,
            1,
            0,
            'L'
        );

        $pdf->Cell(
            35,
            6,
            number_format($summary['total']),
            1,
            0,
            'C'
        );

        $pdf->Cell(
            35,
            6,
            number_format($summary['in']),
            1,
            0,
            'C'
        );

        $pdf->Cell(
            35,
            6,
            number_format($summary['out']),
            1,
            1,
            'C'
        );
    }


    $pdf->Ln(5);


    /*
     * Detailed attendance.
     */

    $pdf->SetFont(
        'helvetica',
        'B',
        10
    );

    $pdf->Cell(
        0,
        6,
        'Detailed Attendance',
        0,
        1,
        'L'
    );


    $pdf->SetFont(
        'helvetica',
        'B',
        7
    );


    $pdf->Cell(
        10,
        6,
        '#',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        25,
        6,
        'Student ID',
        1,
        0,
        'L'
    );

    $pdf->Cell(
        55,
        6,
        'Student Name',
        1,
        0,
        'L'
    );

    $pdf->Cell(
        35,
        6,
        'Program',
        1,
        0,
        'L'
    );

    $pdf->Cell(
        20,
        6,
        'Year',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        25,
        6,
        'Section',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        20,
        6,
        'Direction',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        45,
        6,
        'Scan Time',
        1,
        0,
        'C'
    );

    $pdf->Cell(
        32,
        6,
        'Device',
        1,
        1,
        'L'
    );


    $pdf->SetFont(
        'helvetica',
        '',
        7
    );


    $report_number = 1;

    foreach ($attendance as $record) {

        $pdf->Cell(
            10,
            5,
            $report_number++,
            1,
            0,
            'C'
        );

        $pdf->Cell(
            25,
            5,
            $record['student_id'],
            1,
            0,
            'L'
        );

        $pdf->Cell(
            55,
            5,
            $record['full_name'] ?? '',
            1,
            0,
            'L'
        );

        $pdf->Cell(
            35,
            5,
            $record['program'] ?? '',
            1,
            0,
            'L'
        );

        $pdf->Cell(
            20,
            5,
            $record['year_level'] ?? '',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            25,
            5,
            $record['section'] ?? '',
            1,
            0,
            'C'
        );

        $pdf->Cell(
            20,
            5,
            $record['direction'],
            1,
            0,
            'C'
        );

        $pdf->Cell(
            45,
            5,
            $record['scan_time'],
            1,
            0,
            'C'
        );

        $pdf->Cell(
            32,
            5,
            $record['device'] ?? 'SMARTGATE',
            1,
            1,
            'L'
        );
    }


    /*
     * Output PDF.
     */

    $pdf->Output(
        'smartgate_attendance_report.pdf',
        'I'
    );

    exit;
}


/*
=========================================================
EXPORT URLS
=========================================================
*/

$csv_params = $_GET;
$csv_params['export'] = 'csv';

$csv_url =
    'mis_reports.php?' .
    http_build_query($csv_params);


$pdf_params = $_GET;
$pdf_params['export'] = 'pdf';

$pdf_url =
    'mis_reports.php?' .
    http_build_query($pdf_params);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>MIS Reports | SmartGate</title>


<style>

/* =========================================================
   BASE
   ========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    background: #f4f7fb;

    color: #1f2937;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}

.mis-report-page {
    max-width: 1500px;
    margin: 0 auto;
}


/* =========================================================
   HEADER
   ========================================================= */

.page-header {
    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

    margin-bottom: 22px;
}

.page-title h1 {
    margin: 0;

    color: #0f172a;

    font-size: 28px;

    font-weight: 700;
}

.page-title p {
    margin: 7px 0 0;

    color: #64748b;

    font-size: 14px;
}

.header-actions {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


/* =========================================================
   BUTTONS
   ========================================================= */

.btn {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding: 10px 15px;

    border: none;

    border-radius: 8px;

    font-size: 13px;

    font-weight: 600;

    text-decoration: none;

    cursor: pointer;
}

.btn-primary {
    background: #2563eb;

    color: #ffffff;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-success {
    background: #15803d;

    color: #ffffff;
}

.btn-success:hover {
    background: #166534;
}

.btn-secondary {
    background: #e2e8f0;

    color: #334155;
}

.btn-secondary:hover {
    background: #cbd5e1;
}

.btn-light {
    background: #ffffff;

    color: #334155;

    border: 1px solid #dbe3ec;
}

.btn-light:hover {
    background: #f8fafc;
}


/* =========================================================
   CARD
   ========================================================= */

.card {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px rgba(15,23,42,0.04);
}

.card-title {
    color: #0f172a;

    font-size: 15px;

    font-weight: 700;

    margin-bottom: 15px;
}


/* =========================================================
   FILTERS
   ========================================================= */

.filters {
    display: grid;

    grid-template-columns:
        repeat(7, minmax(110px, 1fr));

    gap: 10px;
}

.filter-group label {
    display: block;

    margin-bottom: 5px;

    color: #64748b;

    font-size: 11px;

    font-weight: 600;
}

.filter-control {
    width: 100%;

    padding: 9px 10px;

    border: 1px solid #cbd5e1;

    border-radius: 7px;

    background: #ffffff;

    color: #334155;

    font-size: 12px;
}

.filter-actions {
    display: flex;

    gap: 8px;

    margin-top: 14px;

    flex-wrap: wrap;
}


/* =========================================================
   STATISTICS
   ========================================================= */

.stats-grid {
    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 13px;

    margin-bottom: 20px;
}

.stat-card {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 11px;

    padding: 17px;
}

.stat-label {
    color: #64748b;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;
}

.stat-value {
    margin-top: 6px;

    color: #0f172a;

    font-size: 25px;

    font-weight: 700;
}

.stat-description {
    margin-top: 4px;

    color: #94a3b8;

    font-size: 10px;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        minmax(0, 1fr);

    gap: 20px;

    margin-bottom: 20px;
}

.summary-card {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 12px;

    overflow: hidden;
}

.summary-card-header {
    padding: 16px 18px;

    border-bottom: 1px solid #e2e8f0;
}

.summary-card-title {
    color: #0f172a;

    font-size: 14px;

    font-weight: 700;
}

.summary-card-description {
    margin-top: 3px;

    color: #64748b;

    font-size: 11px;
}


/* =========================================================
   DIRECTION OVERVIEW
   ========================================================= */

.direction-overview {
    padding: 20px;
}

.direction-row {
    margin-bottom: 18px;
}

.direction-row:last-child {
    margin-bottom: 0;
}

.direction-heading {
    display: flex;

    justify-content: space-between;

    margin-bottom: 7px;

    color: #334155;

    font-size: 12px;

    font-weight: 600;
}

.progress-track {
    height: 9px;

    overflow: hidden;

    background: #e2e8f0;

    border-radius: 999px;
}

.progress-bar {
    height: 100%;

    background: #2563eb;

    border-radius: 999px;
}

.progress-bar.out {
    background: #64748b;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-card {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 12px;

    overflow: hidden;

    margin-bottom: 20px;
}

.table-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding: 17px 20px;

    border-bottom: 1px solid #e2e8f0;
}

.table-title {
    color: #0f172a;

    font-size: 15px;

    font-weight: 700;
}

.table-subtitle {
    margin-top: 4px;

    color: #64748b;

    font-size: 11px;
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

    min-width: 950px;

    border-collapse: collapse;
}

thead {
    background: #f8fafc;
}

th {
    padding: 11px 13px;

    color: #64748b;

    font-size: 10px;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .4px;

    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 12px 13px;

    color: #334155;

    font-size: 12px;

    border-bottom: 1px solid #edf2f7;
}

tbody tr:hover {
    background: #f8fafc;
}


/* =========================================================
   BADGES
   ========================================================= */

.badge {
    display: inline-flex;

    padding: 5px 9px;

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


/* =========================================================
   EMPTY
   ========================================================= */

.empty-state {
    padding: 45px 20px;

    text-align: center;

    color: #64748b;
}

.empty-title {
    margin-bottom: 5px;

    color: #334155;

    font-weight: 700;
}

.empty-text {
    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1200px) {

    .filters {
        grid-template-columns:
            repeat(4, minmax(110px, 1fr));
    }

    .stats-grid {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

}

@media (max-width: 800px) {

    .page-header {
        flex-direction: column;
    }

    .filters {
        grid-template-columns:
            repeat(2, minmax(110px, 1fr));
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 550px) {

    .filters {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

}


/* =========================================================
   PRINT
   ========================================================= */

@media print {

    .smartgate-sidebar,
    .smartgate-mobile-toggle,
    .smartgate-overlay,
    .header-actions,
    .filter-card,
    .table-actions {
        display: none !important;
    }

    .smartgate-main {
        margin-left: 0 !important;

        padding: 0 !important;
    }

    body {
        background: #ffffff;
    }

    .card,
    .stat-card,
    .summary-card,
    .table-card {
        box-shadow: none;

        border: 1px solid #ccc;
    }

}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>


<body>

<?php
/*
=========================================================
PERSISTENT SIDEBAR
=========================================================
*/
include "smartgate_sidebar.php";
?>


<div class="smartgate-main sg-page-shell">

<div class="mis-report-page">


    <!-- =================================================
         HEADER
         ================================================= -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                MIS Reports
            </h1>

            <p>
                Generate attendance reports for
                monitoring, analysis, and documentation.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="<?= h($pdf_url) ?>"
                class="btn btn-primary"
                target="_blank"
            >
                Export PDF
            </a>


            <a
                href="<?= h($csv_url) ?>"
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


    <!-- =================================================
         FILTER CARD
         ================================================= -->

    <div class="card">

        <div class="card-title">
            Report Filters
        </div>


        <form
            method="GET"
            action="mis_reports.php"
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

                        <?php foreach (
                            $years as $year_item
                        ): ?>

                            <option
                                value="<?= h($year_item) ?>"
                                <?= selected(
                                    $year,
                                    $year_item
                                ) ?>
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
                            <?= selected(
                                $semester,
                                '1st'
                            ) ?>
                        >
                            1st Semester
                        </option>

                        <option
                            value="2nd"
                            <?= selected(
                                $semester,
                                '2nd'
                            ) ?>
                        >
                            2nd Semester
                        </option>

                        <option
                            value="Summer"
                            <?= selected(
                                $semester,
                                'Summer'
                            ) ?>
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

                        foreach (
                            $months
                            as $number => $name
                        ):

                        ?>

                            <option
                                value="<?= $number ?>"
                                <?= selected(
                                    $month,
                                    $number
                                ) ?>
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

                        <?php for (
                            $i = 1;
                            $i <= 53;
                            $i++
                        ): ?>

                            <option
                                value="<?= $i ?>"
                                <?= selected(
                                    $week,
                                    $i
                                ) ?>
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
                            <?= selected(
                                $direction,
                                'IN'
                            ) ?>
                        >
                            IN
                        </option>

                        <option
                            value="OUT"
                            <?= selected(
                                $direction,
                                'OUT'
                            ) ?>
                        >
                            OUT
                        </option>

                    </select>

                </div>


                <!-- PROGRAM -->

                <div class="filter-group">

                    <label>
                        Program
                    </label>

                    <select
                        name="program"
                        class="filter-control"
                    >

                        <option value="">
                            All Programs
                        </option>

                        <?php foreach (
                            $programs
                            as $program_item
                        ): ?>

                            <option
                                value="<?= h(
                                    $program_item
                                ) ?>"
                                <?= selected(
                                    $program,
                                    $program_item
                                ) ?>
                            >
                                <?= h(
                                    $program_item
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Generate Report
                </button>


                <a
                    href="mis_reports.php"
                    class="btn btn-secondary"
                >
                    Clear Filters
                </a>

            </div>

        </form>

    </div>


    <!-- =================================================
         STATISTICS
         ================================================= -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-label">
                Total Records
            </div>

            <div class="stat-value">
                <?= number_format(
                    $total_records
                ) ?>
            </div>

            <div class="stat-description">
                Attendance records
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Students
            </div>

            <div class="stat-value">
                <?= number_format(
                    $total_students
                ) ?>
            </div>

            <div class="stat-description">
                Unique students
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                IN
            </div>

            <div class="stat-value">
                <?= number_format(
                    $total_in
                ) ?>
            </div>

            <div class="stat-description">
                <?= $in_percentage ?>%
                of records
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                OUT
            </div>

            <div class="stat-value">
                <?= number_format(
                    $total_out
                ) ?>
            </div>

            <div class="stat-description">
                <?= $out_percentage ?>%
                of records
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Programs
            </div>

            <div class="stat-value">
                <?= number_format(
                    count($program_summary)
                ) ?>
            </div>

            <div class="stat-description">
                Programs represented
            </div>

        </div>

    </div>


    <!-- =================================================
         SUMMARY SECTION
         ================================================= -->

    <div class="summary-grid">


        <!-- DIRECTION -->

        <div class="summary-card">

            <div class="summary-card-header">

                <div class="summary-card-title">
                    Attendance Direction
                </div>

                <div class="summary-card-description">
                    Distribution of IN and OUT scans.
                </div>

            </div>


            <div class="direction-overview">


                <div class="direction-row">

                    <div class="direction-heading">

                        <span>
                            IN
                        </span>

                        <span>
                            <?= number_format(
                                $total_in
                            ) ?>
                            (<?= $in_percentage ?>%)
                        </span>

                    </div>


                    <div class="progress-track">

                        <div
                            class="progress-bar"
                            style="
                                width:
                                <?= $in_percentage ?>%;
                            "
                        ></div>

                    </div>

                </div>


                <div class="direction-row">

                    <div class="direction-heading">

                        <span>
                            OUT
                        </span>

                        <span>
                            <?= number_format(
                                $total_out
                            ) ?>
                            (<?= $out_percentage ?>%)
                        </span>

                    </div>


                    <div class="progress-track">

                        <div
                            class="progress-bar out"
                            style="
                                width:
                                <?= $out_percentage ?>%;
                            "
                        ></div>

                    </div>

                </div>


            </div>

        </div>


        <!-- PROGRAM SUMMARY -->

        <div class="summary-card">

            <div class="summary-card-header">

                <div class="summary-card-title">
                    Summary by Program
                </div>

                <div class="summary-card-description">
                    Attendance distribution per program.
                </div>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Program
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                                IN
                            </th>

                            <th>
                                OUT
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (
                        count($program_summary) > 0
                    ): ?>

                        <?php foreach (
                            $program_summary
                            as $program_name =>
                            $summary
                        ): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= h(
                                            $program_name
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= number_format(
                                        $summary['total']
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        $summary['in']
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        $summary['out']
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="4"
                                style="text-align:center;"
                            >
                                No program data available.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =================================================
         DETAILED RECORDS
         ================================================= -->

    <div class="table-card">


        <div class="table-header">

            <div>

                <div class="table-title">
                    Detailed Attendance Records
                </div>

                <div class="table-subtitle">

                    <?= number_format(
                        $total_records
                    ) ?>
                    attendance record(s)

                </div>

            </div>


            <div class="table-actions">

                <a
                    href="<?= h($pdf_url) ?>"
                    class="btn btn-primary"
                    target="_blank"
                >
                    PDF
                </a>

                <a
                    href="<?= h($csv_url) ?>"
                    class="btn btn-success"
                >
                    CSV
                </a>

            </div>

        </div>


        <?php if (
            count($attendance) > 0
        ): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

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
                                Year
                            </th>

                            <th>
                                Section
                            </th>

                            <th>
                                Direction
                            </th>

                            <th>
                                Scan Time
                            </th>

                            <th>
                                Device
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $attendance
                        as $index => $record
                    ): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <strong>
                                    <?= h(
                                        $record['student_id']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= h(
                                    $record['full_name']
                                    ?? 'Unknown Student'
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $record['program']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $record['year_level']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $record['section']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>

                                <?php if (
                                    $record['direction']
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
                                <?= h(
                                    $record['scan_time']
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $record['device']
                                    ?: 'SMARTGATE'
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <div class="empty-title">
                    No Attendance Records
                </div>

                <div class="empty-text">
                    No attendance records match
                    the selected filters.
                </div>

            </div>

        <?php endif; ?>

    </div>


</div>

</div>

</body>

</html>
