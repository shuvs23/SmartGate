<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

// TCPDF
require_once __DIR__ . "/tcpdf/tcpdf.php";

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Only MIS and Super Admin
$allowed_roles = [
    'super_admin',
    'MIS'
];

if (!in_array($_SESSION['role'], $allowed_roles, true)) {
    die("Access Denied.");
}


// --------------------------------------------------
// GET FILTERS
// --------------------------------------------------

$date_from  = trim($_GET['date_from'] ?? '');
$date_to    = trim($_GET['date_to'] ?? '');
$direction  = trim($_GET['direction'] ?? '');
$program    = trim($_GET['program'] ?? '');
$year_level = trim($_GET['year_level'] ?? '');
$section    = trim($_GET['section'] ?? '');
$search     = trim($_GET['search'] ?? '');


// --------------------------------------------------
// BUILD QUERY
// --------------------------------------------------

$sql = "
    SELECT
        a.id,
        a.student_id,
        s.full_name,
        s.program,
        s.year_level,
        s.section,
        a.direction,
        a.scan_time,
        a.device,
        a.remarks
    FROM attendance_logs a
    LEFT JOIN students s
        ON a.student_id = s.student_id
    WHERE 1=1
";

$params = [];
$types = "";


// Search
if ($search !== '') {

    $sql .= "
        AND (
            a.student_id LIKE ?
            OR s.full_name LIKE ?
            OR s.program LIKE ?
            OR s.section LIKE ?
        )
    ";

    $keyword = "%" . $search . "%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= "ssss";
}


// Date From
if ($date_from !== '') {

    $sql .= " AND DATE(a.scan_time) >= ?";

    $params[] = $date_from;
    $types .= "s";
}


// Date To
if ($date_to !== '') {

    $sql .= " AND DATE(a.scan_time) <= ?";

    $params[] = $date_to;
    $types .= "s";
}


// Direction
if ($direction === 'IN' || $direction === 'OUT') {

    $sql .= " AND a.direction = ?";

    $params[] = $direction;
    $types .= "s";
}


// Program
if ($program !== '') {

    $sql .= " AND s.program = ?";

    $params[] = $program;
    $types .= "s";
}


// Year Level
if ($year_level !== '') {

    $sql .= " AND s.year_level = ?";

    $params[] = $year_level;
    $types .= "s";
}


// Section
if ($section !== '') {

    $sql .= " AND s.section = ?";

    $params[] = $section;
    $types .= "s";
}


$sql .= " ORDER BY a.scan_time DESC";


// --------------------------------------------------
// EXECUTE
// --------------------------------------------------

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}


if (!empty($params)) {

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


// --------------------------------------------------
// SUMMARY
// --------------------------------------------------

$logs = [];

$total_records = 0;
$total_in = 0;
$total_out = 0;

while ($row = $result->fetch_assoc()) {

    $logs[] = $row;

    $total_records++;

    if ($row['direction'] === 'IN') {
        $total_in++;
    }

    if ($row['direction'] === 'OUT') {
        $total_out++;
    }
}


// --------------------------------------------------
// TCPDF
// --------------------------------------------------

$pdf = new TCPDF(
    'L',
    'mm',
    'A4',
    true,
    'UTF-8',
    false
);


// PDF information
$pdf->SetCreator('SmartGate');
$pdf->SetAuthor($_SESSION['full_name']);
$pdf->SetTitle('SmartGate Attendance Report');
$pdf->SetSubject('Attendance Report');


// Margins
$pdf->SetMargins(10, 10, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(5);


// Disable automatic header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);


// Add page
$pdf->AddPage();


// --------------------------------------------------
// TITLE
// --------------------------------------------------

$pdf->SetFont('helvetica', 'B', 18);

$pdf->Cell(
    0,
    10,
    'SMARTGATE',
    0,
    1,
    'C'
);


$pdf->SetFont('helvetica', 'B', 14);

$pdf->Cell(
    0,
    8,
    'Attendance Report',
    0,
    1,
    'C'
);


$pdf->SetFont('helvetica', '', 9);

$generated = date('F d, Y h:i A');

$pdf->Cell(
    0,
    6,
    'Generated: ' . $generated,
    0,
    1,
    'C'
);


// --------------------------------------------------
// FILTER INFORMATION
// --------------------------------------------------

$filter_text = [];

if ($date_from !== '') {
    $filter_text[] = 'From: ' . $date_from;
}

if ($date_to !== '') {
    $filter_text[] = 'To: ' . $date_to;
}

if ($direction !== '') {
    $filter_text[] = 'Direction: ' . $direction;
}

if ($program !== '') {
    $filter_text[] = 'Program: ' . $program;
}

if ($year_level !== '') {
    $filter_text[] = 'Year Level: ' . $year_level;
}

if ($section !== '') {
    $filter_text[] = 'Section: ' . $section;
}

if ($search !== '') {
    $filter_text[] = 'Search: ' . $search;
}


if (!empty($filter_text)) {

    $pdf->Ln(3);

    $pdf->SetFont('helvetica', '', 9);

    $pdf->MultiCell(
        0,
        6,
        'Filters: ' . implode(' | ', $filter_text),
        0,
        'L'
    );
}


// --------------------------------------------------
// SUMMARY
// --------------------------------------------------

$pdf->Ln(3);

$pdf->SetFont('helvetica', 'B', 10);

$pdf->Cell(
    90,
    7,
    'Total Records: ' . $total_records,
    0,
    0,
    'L'
);

$pdf->Cell(
    90,
    7,
    'Total IN: ' . $total_in,
    0,
    0,
    'L'
);

$pdf->Cell(
    90,
    7,
    'Total OUT: ' . $total_out,
    0,
    1,
    'L'
);


// --------------------------------------------------
// TABLE
// --------------------------------------------------

$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 7);


// Table header
$headers = [
    'ID',
    'Student ID',
    'Student Name',
    'Program',
    'Year',
    'Section',
    'Direction',
    'Scan Date & Time',
    'Device',
    'Remarks'
];

$widths = [
    10,
    25,
    42,
    25,
    15,
    18,
    20,
    38,
    25,
    55
];


// Header
$pdf->SetFont('helvetica', 'B', 7);

for ($i = 0; $i < count($headers); $i++) {

    $pdf->Cell(
        $widths[$i],
        8,
        $headers[$i],
        1,
        0,
        'C'
    );
}

$pdf->Ln();


// Table rows
$pdf->SetFont('helvetica', '', 7);

if (!empty($logs)) {

    foreach ($logs as $log) {

        $values = [
            $log['id'],
            $log['student_id'],
            $log['full_name'] ?? 'Unknown Student',
            $log['program'] ?? '-',
            $log['year_level'] ?? '-',
            $log['section'] ?? '-',
            $log['direction'],
            $log['scan_time'],
            $log['device'] ?? '-',
            $log['remarks'] ?? '-'
        ];


        $max_lines = 1;

        for ($i = 0; $i < count($values); $i++) {

            $line_count = $pdf->getNumLines(
                (string)$values[$i],
                $widths[$i]
            );

            if ($line_count > $max_lines) {
                $max_lines = $line_count;
            }
        }


        $row_height = max(6, $max_lines * 4);


        for ($i = 0; $i < count($values); $i++) {

            $align = 'L';

            if (
                $i === 0 ||
                $i === 4 ||
                $i === 6
            ) {
                $align = 'C';
            }

            $pdf->MultiCell(
                $widths[$i],
                $row_height,
                htmlspecialchars(
                    (string)$values[$i]
                ),
                1,
                $align,
                false,
                0,
                '',
                '',
                true,
                0,
                false,
                true,
                $row_height,
                'M'
            );
        }

        $pdf->Ln($row_height);
    }

} else {

    $pdf->Cell(
        array_sum($widths),
        10,
        'No attendance records found.',
        1,
        1,
        'C'
    );
}


// --------------------------------------------------
// OUTPUT PDF
// --------------------------------------------------

$filename =
    'SmartGate_Attendance_Report_' .
    date('Y-m-d_H-i-s') .
    '.pdf';

$pdf->Output(
    $filename,
    'I'
);

exit;

?>
