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
    "Security"
];

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    die("Access denied.");
}

$userId   = $_SESSION["user_id"];
$fullName = $_SESSION["full_name"] ?? "User";

$processError = "";
$processSuccess = "";
$processType = "student";
$processStudentId = "";
$processVisitorName = "";
$processDirection = "IN";
$processReason = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["process_bypass"])
) {
    smartgate_require_csrf();

    $processTypeInput = $_POST["bypass_type"] ?? "student";
    $processType = is_string($processTypeInput)
        ? $processTypeInput
        : "student";

    $studentInput = $_POST["student_id"] ?? "";
    $visitorInput = $_POST["visitor_name"] ?? "";
    $directionInput = $_POST["bypass_direction"] ?? "";
    $reasonInput = $_POST["reason"] ?? "";

    $processStudentId = is_string($studentInput)
        ? trim($studentInput)
        : "";
    $processVisitorName = is_string($visitorInput)
        ? trim($visitorInput)
        : "";
    $processDirection = is_string($directionInput)
        ? strtoupper(trim($directionInput))
        : "";
    $processReason = is_string($reasonInput)
        ? trim($reasonInput)
        : "";

    if (!in_array($processType, ["student", "visitor"], true)) {
        $processError = "Please select a valid bypass type.";
    } elseif (!in_array($processDirection, ["IN", "OUT"], true)) {
        $processError = "Please select a valid direction.";
    } elseif ($processReason === "" || strlen($processReason) > 255) {
        $processError = "A reason is required and must be 255 characters or fewer.";
    } elseif ($processType === "student" && $processStudentId === "") {
        $processError = "Student ID is required for a student bypass.";
    } elseif ($processType === "visitor" && $processVisitorName === "") {
        $processError = "Visitor name is required for a visitor bypass.";
    } else {
        $studentIdForDb = null;
        $visitorNameForDb = null;

        if ($processType === "student") {
            $studentCheck = $conn->prepare(
                "SELECT student_id FROM students WHERE student_id = ? AND is_active = 1 LIMIT 1"
            );

            if (!$studentCheck) {
                $processError = "Unable to validate the student right now.";
                error_log("SmartGate bypass student check failed: " . $conn->error);
            } else {
                $studentCheck->bind_param("s", $processStudentId);
                $studentCheck->execute();
                $studentResult = $studentCheck->get_result();
                $studentExists = $studentResult->num_rows === 1;
                $studentCheck->close();

                if (!$studentExists) {
                    $processError = "Active student was not found.";
                } else {
                    $studentIdForDb = $processStudentId;
                }
            }
        } else {
            $visitorNameForDb = $processVisitorName;
        }

        if ($processError === "") {
            $conn->begin_transaction();

            try {
                $bypassStmt = $conn->prepare(
                    "INSERT INTO bypass_logs
                    (student_id, visitor_name, user_id, direction, reason)
                    VALUES (?, ?, ?, ?, ?)"
                );

                if (!$bypassStmt) {
                    throw new Exception("Unable to prepare bypass record.");
                }

                $bypassStmt->bind_param(
                    "ssiss",
                    $studentIdForDb,
                    $visitorNameForDb,
                    $userId,
                    $processDirection,
                    $processReason
                );

                if (!$bypassStmt->execute()) {
                    throw new Exception($bypassStmt->error);
                }

                $bypassId = $bypassStmt->insert_id;
                $bypassStmt->close();

                $displayStmt = $conn->prepare(
                    "INSERT INTO display_events
                    (display_type, student_id, visitor_name, direction, reason)
                    VALUES ('BYPASS', ?, ?, ?, ?)"
                );

                if (!$displayStmt) {
                    throw new Exception("Unable to prepare display event.");
                }

                $displayStmt->bind_param(
                    "ssss",
                    $studentIdForDb,
                    $visitorNameForDb,
                    $processDirection,
                    $processReason
                );

                if (!$displayStmt->execute()) {
                    throw new Exception($displayStmt->error);
                }

                $displayStmt->close();

                $auditAction = "CREATE BYPASS";
                $auditDescription =
                    "Processed " . $processType . " bypass for " .
                    ($studentIdForDb ?? $visitorNameForDb) .
                    " (" . $processDirection . "): " . $processReason;
                $auditTargetType = "BYPASS";
                $auditTargetId = (string)$bypassId;
                $auditIp = $_SERVER["REMOTE_ADDR"] ?? null;

                $auditStmt = $conn->prepare(
                    "INSERT INTO system_audit_logs
                    (user_id, action, description, target_type, target_id, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if (!$auditStmt) {
                    throw new Exception("Unable to prepare audit record.");
                }

                $auditStmt->bind_param(
                    "isssss",
                    $userId,
                    $auditAction,
                    $auditDescription,
                    $auditTargetType,
                    $auditTargetId,
                    $auditIp
                );

                if (!$auditStmt->execute()) {
                    throw new Exception($auditStmt->error);
                }

                $auditStmt->close();
                $conn->commit();

                header("Location: bypass.php?bypass=success");
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log("SmartGate bypass processing failed: " . $e->getMessage());
                $processError = "Unable to process the bypass right now.";
            }
        }
    }
}

if (($_GET["bypass"] ?? "") === "success") {
    $processSuccess = "Bypass processed successfully and sent to the display.";
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
| FILTERS
|--------------------------------------------------------------------------
*/

$search    = trim($_GET["search"] ?? "");
$year      = trim($_GET["year"] ?? "");
$semester  = trim($_GET["semester"] ?? "");
$month     = trim($_GET["month"] ?? "");
$week      = trim($_GET["week"] ?? "");
$day       = trim($_GET["day"] ?? "");
$direction = trim($_GET["direction"] ?? "");

/*
|--------------------------------------------------------------------------
| YEAR OPTIONS
|--------------------------------------------------------------------------
*/

$years = [];

$result = $conn->query("
    SELECT DISTINCT
        YEAR(bypass_time) AS bypass_year
    FROM bypass_logs
    WHERE bypass_time IS NOT NULL
    ORDER BY bypass_year DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if (!empty($row["bypass_year"])) {
            $years[] = $row["bypass_year"];
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
| BUILD FILTER QUERY
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
            b.student_id LIKE ?
            OR b.visitor_name LIKE ?
            OR s.full_name LIKE ?
            OR s.program LIKE ?
            OR s.section LIKE ?
            OR b.reason LIKE ?
            OR u.full_name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    for ($i = 0; $i < 7; $i++) {
        $params[] = $searchValue;
    }

    $types .= "sssssss";
}

/*
|--------------------------------------------------------------------------
| YEAR
|--------------------------------------------------------------------------
*/

if ($year !== "") {

    $where[] = "
        YEAR(b.bypass_time) = ?
    ";

    $params[] = (int)$year;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| SEMESTER
|--------------------------------------------------------------------------
*/

if ($semester === "1st") {

    $where[] = "
        MONTH(b.bypass_time) BETWEEN 8 AND 12
    ";

} elseif ($semester === "2nd") {

    $where[] = "
        MONTH(b.bypass_time) BETWEEN 1 AND 5
    ";

} elseif ($semester === "Summer") {

    $where[] = "
        MONTH(b.bypass_time) BETWEEN 6 AND 7
    ";
}

/*
|--------------------------------------------------------------------------
| MONTH
|--------------------------------------------------------------------------
*/

if ($month !== "") {

    $where[] = "
        MONTH(b.bypass_time) = ?
    ";

    $params[] = (int)$month;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| WEEK
|--------------------------------------------------------------------------
*/

if ($week !== "") {

    $where[] = "
        WEEK(b.bypass_time, 1) = ?
    ";

    $params[] = (int)$week;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| DAY
|--------------------------------------------------------------------------
*/

if ($day !== "") {

    $where[] = "
        DATE(b.bypass_time) = ?
    ";

    $params[] = $day;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| DIRECTION
|--------------------------------------------------------------------------
*/

if (
    $direction === "IN" ||
    $direction === "OUT"
) {

    $where[] = "
        b.direction = ?
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

        b.id,
        b.student_id,
        b.visitor_name,
        b.user_id,
        b.direction,
        b.reason,
        b.bypass_time,

        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.photo,

        u.full_name AS processed_by

    FROM bypass_logs b

    LEFT JOIN students s
        ON s.student_id = b.student_id

    LEFT JOIN users u
        ON u.id = b.user_id
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
        b.bypass_time DESC
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
| FILTERED STATISTICS
|--------------------------------------------------------------------------
*/

$filteredTotal = count($logs);

$filteredIn = 0;
$filteredOut = 0;

$filteredStudents = 0;
$filteredVisitors = 0;

foreach ($logs as $log) {

    if ($log["direction"] === "IN") {
        $filteredIn++;
    }

    if ($log["direction"] === "OUT") {
        $filteredOut++;
    }

    if (!empty($log["student_id"])) {
        $filteredStudents++;
    } else {
        $filteredVisitors++;
    }
}

/*
|--------------------------------------------------------------------------
| TODAY'S STATISTICS
|--------------------------------------------------------------------------
*/

$todayBypass = 0;
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

    FROM bypass_logs

    WHERE DATE(bypass_time) = CURDATE()
");

if ($result && $row = $result->fetch_assoc()) {

    $todayBypass = (int)$row["total"];
    $todayIn     = (int)$row["total_in"];
    $todayOut    = (int)$row["total_out"];
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
    Bypass Logs • SmartGate
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
        repeat(5, 1fr);

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
| PROCESS BYPASS FORM
|--------------------------------------------------------------------------
*/

.bypass-form-grid {

    grid-template-columns:
        1fr
        2fr
        2fr
        1fr
        2fr
        auto;
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

    min-width: 1150px;

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
| PERSON
|--------------------------------------------------------------------------
*/

.person-cell {

    display: flex;

    align-items: center;

    gap: 9px;

    min-width: 190px;
}

.person-photo {

    width: 38px;

    height: 38px;

    border-radius: 9px;

    object-fit: cover;

    background: #e2e8f0;
}

.person-placeholder {

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

.person-name {

    color: #17233b;

    font-size: 11px;

    font-weight: 700;
}

.person-id {

    margin-top: 3px;

    color: #64748b;

    font-size: 9px;
}

/*
|--------------------------------------------------------------------------
| TYPE BADGES
|--------------------------------------------------------------------------
*/

.badge {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        4px 8px;

    border-radius: 999px;

    font-size: 9px;

    font-weight: 700;

    white-space: nowrap;
}

.badge-student {

    background: #dbeafe;

    color: #1d4ed8;
}

.badge-visitor {

    background: #f3e8ff;

    color: #7e22ce;
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
| REASON
|--------------------------------------------------------------------------
*/

.reason {

    max-width: 220px;

    white-space: normal;

    line-height: 1.4;
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
    .stats,
    .filters-panel,
    .header-actions {

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

    .person-photo,
    .person-placeholder {

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
            repeat(3, 1fr);
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

    .bypass-form-grid .filter-actions {

        width: 100%;
    }

    .bypass-form-grid .filter-actions .btn {

        width: 100%;
    }
}

@media (max-width: 1400px) {

    .filters.bypass-form-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }
}

@media (max-width: 900px) {

    .filters.bypass-form-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 650px) {

    .filters.bypass-form-grid {

        grid-template-columns:
            1fr;
    }
}

/* Process Bypass popup */
.bypass-modal {
    display: none;
    position: fixed;
    inset: 0;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, .58);
    z-index: 2000;
}

.bypass-modal.is-open {
    display: flex;
}

.bypass-modal-dialog {
    width: min(720px, 94vw);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .30);
}

.bypass-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    padding: 24px 26px 19px;
    border-bottom: 1px solid #e2e8f0;
}

.bypass-modal-close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 8px;
    background: #f1f5f9;
    color: #334155;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

.bypass-modal-close:hover {
    background: #e2e8f0;
}

.bypass-modal .panel-body {
    padding: 30px;
}

.bypass-modal .panel-title {
    font-size: 18px;
}

.bypass-modal .panel-subtitle {
    font-size: 12px;
    margin-top: 5px;
}

.bypass-modal .form-group label {
    font-size: 11px;
    margin-bottom: 7px;
}

.bypass-modal .form-control {
    min-height: 44px;
    padding: 10px 12px;
    border-radius: 9px;
    font-size: 12px;
}

.bypass-modal .btn {
    min-height: 42px;
    padding: 10px 16px;
    font-size: 12px;
}

.bypass-modal .form-group {
    min-width: 0;
}

.bypass-modal .form-control {
    background: #f8fbff;
}

.bypass-modal .form-control:hover {
    border-color: #93c5fd;
}

.bypass-modal .filter-actions .btn {
    min-width: 92px;
}

@media (min-width: 901px) {
    .bypass-modal-dialog {
        min-height: 0;
    }

    .bypass-modal .panel-body form {
        width: 100%;
    }
}

.bypass-modal .filters.bypass-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 18px;
    align-items: stretch;
}

.bypass-modal .filter-actions {
    grid-column: auto;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 4px;
}

.bypass-modal .form-group {
    width: 100%;
}

.bypass-modal .form-group + .form-group {
    margin-top: 0;
}

.bypass-modal .form-control {
    width: 100%;
}

.bypass-modal .filter-actions {
    grid-column: 1 / -1;
    justify-content: flex-end;
}

.page-alert {
    margin-bottom: 16px;
    padding: 11px 13px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}

.page-alert.success {
    background: #dcfce7;
    color: #166534;
}

body.modal-open {
    overflow: hidden;
}

@media (max-width: 900px) {
    .bypass-modal-dialog {
        width: min(720px, 94vw);
    }

    .bypass-modal .filters.bypass-form-grid {
        grid-template-columns: 1fr;
    }

    .bypass-modal .filter-actions {
        grid-column: auto;
        justify-content: flex-end;
    }
}

@media (max-width: 650px) {
    .bypass-modal {
        padding: 12px;
    }

    .bypass-modal-dialog {
        width: 100%;
        max-height: calc(100vh - 24px);
    }

    .bypass-modal .filters.bypass-form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .bypass-modal .filter-actions {
        grid-column: auto;
        width: 100%;
        justify-content: stretch;
    }

    .bypass-modal .filter-actions .btn {
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


        <a
            href="bypass.php"
        >
            <span class="icon">⚡</span>
            Bypass
        </a>


        <a
            href="bypass_logs.php"
            class="active"
        >
            <span class="icon">↪</span>
            Bypass Logs
        </a>


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
                Bypass Logs
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
                    Bypass
                </h1>

                <p>
                    Review authorized student and visitor
                    bypass transactions processed by Security.
                </p>

            </div>


            <div class="header-actions">

                <button
                    type="button"
                    id="open-process-bypass"
                    class="btn btn-primary"
                >
                    ⚡ Process Bypass
                </button>


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

        <?php if ($processSuccess !== ""): ?>
            <div class="page-alert success">
                <?= e($processSuccess) ?>
            </div>
        <?php endif; ?>

        <div
            class="bypass-modal <?= $processError !== "" ? "is-open" : "" ?>"
            id="process-bypass"
            aria-hidden="<?= $processError !== "" ? "false" : "true" ?>"
            data-open-on-load="<?= $processError !== "" ? "true" : "false" ?>"
        >
            <div
                class="bypass-modal-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="process-bypass-title"
            >
                <div class="bypass-modal-header">
                    <div>
                        <div class="panel-title" id="process-bypass-title">Process Bypass</div>
                        <div class="panel-subtitle">
                            Record an authorized student or visitor entry/exit.
                        </div>
                    </div>

                    <button
                        type="button"
                        class="bypass-modal-close"
                        id="close-process-bypass"
                        aria-label="Close Process Bypass"
                    >
                        &times;
                    </button>
                </div>

                <div class="panel-body">
                    <?php if ($processError !== ""): ?>
                        <div style="margin-bottom:14px;padding:11px 13px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:700;">
                            <?= e($processError) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="bypass.php">
                        <?= smartgate_csrf_field() ?>
                        <input type="hidden" name="process_bypass" value="1">

                        <div class="filters bypass-form-grid">
                            <div class="form-group">
                                <label for="bypass_type">Type</label>
                                <select class="form-control" id="bypass_type" name="bypass_type">
                                    <option value="student" <?= $processType === "student" ? "selected" : "" ?>>Student</option>
                                    <option value="visitor" <?= $processType === "visitor" ? "selected" : "" ?>>Visitor</option>
                                </select>
                            </div>

                            <div class="form-group" id="student-field">
                                <label for="student_id">Student ID</label>
                                <input class="form-control" id="student_id" name="student_id" value="<?= e($processStudentId) ?>" placeholder="e.g. 3031-0001">
                            </div>

                            <div class="form-group" id="visitor-field">
                                <label for="visitor_name">Visitor Name</label>
                                <input class="form-control" id="visitor_name" name="visitor_name" value="<?= e($processVisitorName) ?>" placeholder="Full name">
                            </div>

                            <div class="form-group">
                                <label for="bypass_direction">Direction</label>
                                <select class="form-control" id="bypass_direction" name="bypass_direction">
                                    <option value="IN" <?= $processDirection === "IN" ? "selected" : "" ?>>IN</option>
                                    <option value="OUT" <?= $processDirection === "OUT" ? "selected" : "" ?>>OUT</option>
                                </select>
                            </div>

                            <!-- STUDENT BYPASS REASON -->
                            <div class="form-group" id="student-reason-field">
                                <label for="student_reason">Reason for Student Bypass</label>
                                <select class="form-control" id="student_reason">
                                    <option value="">Select reason...</option>
                                    <option value="No ID">No ID</option>
                                    <option value="Lost ID">Lost ID</option>
                                    <option value="Forgot ID">Forgot ID</option>
                                    <option value="Damaged ID">Damaged ID</option>
                                    <option value="ID Card Problem">ID Card Problem</option>
                                    <option value="QR Code Problem">QR Code Problem</option>
                                    <option value="Phone / Device Problem">Phone / Device Problem</option>
                                    <option value="Other">Other</option>
                                </select>

                                <div style="margin-top:6px;color:#94a3b8;font-size:10px;">
                                    Select the applicable reason for the student's bypass.
                                </div>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="student_reason_other"
                                    value=""
                                    maxlength="255"
                                    placeholder="Enter other reason"
                                    style="display:none;margin-top:8px;"
                                >

                                <!-- The backend continues receiving the existing 'reason' field. -->
                                <input
                                    type="hidden"
                                    id="reason"
                                    name="reason"
                                    value="<?= e($processReason) ?>"
                                >
                            </div>

                            <!-- VISITOR BYPASS REASON -->
                            <div class="form-group" id="visitor-reason-field" style="display:none;">
                                <label for="visitor_reason">Reason for Visitor Bypass</label>
                                <input
                                    class="form-control"
                                    id="visitor_reason"
                                    value="<?= e($processReason) ?>"
                                    maxlength="255"
                                    placeholder="Reason for bypass"
                                >
                            </div>

                            <div class="filter-actions">
                                <button type="button" class="btn btn-secondary" id="cancel-process-bypass">Cancel</button>
                                <button type="submit" class="btn btn-primary">Process</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function () {
            const modal = document.getElementById('process-bypass');
            const openButton = document.getElementById('open-process-bypass');
            const closeButton = document.getElementById('close-process-bypass');
            const cancelButton = document.getElementById('cancel-process-bypass');
            const type = document.getElementById('bypass_type');
            const studentField = document.getElementById('student-field');
            const visitorField = document.getElementById('visitor-field');
            const studentInput = document.getElementById('student_id');
            const visitorInput = document.getElementById('visitor_name');

            const studentReasonField = document.getElementById('student-reason-field');
            const visitorReasonField = document.getElementById('visitor-reason-field');
            const studentReason = document.getElementById('student_reason');
            const studentReasonOther = document.getElementById('student_reason_other');
            const reasonInput = document.getElementById('reason');
            const visitorReason = document.getElementById('visitor_reason');

            function openModal() {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                window.setTimeout(function () {
                    type.focus();
                }, 0);
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
            }

            function syncBypassFields() {
                const isStudent = type.value === 'student';

                studentField.style.display = isStudent ? '' : 'none';
                visitorField.style.display = isStudent ? 'none' : '';

                studentReasonField.style.display = isStudent ? '' : 'none';
                visitorReasonField.style.display = isStudent ? 'none' : '';

                studentInput.required = isStudent;
                visitorInput.required = !isStudent;

                studentReason.disabled = !isStudent;
                studentReasonOther.disabled = !isStudent;
                visitorReason.disabled = isStudent;

                if (isStudent) {
                    visitorReason.removeAttribute('name');
                    reasonInput.name = 'reason';
                    reasonInput.required = true;

                    // Restore previously selected reason when returning to Student.
                    if (reasonInput.value && studentReason.value === '') {
                        const optionExists = Array.from(studentReason.options)
                            .some(option => option.value === reasonInput.value);

                        if (optionExists) {
                            studentReason.value = reasonInput.value;
                        } else if (reasonInput.value !== '') {
                            studentReason.value = 'Other';
                            studentReasonOther.value = reasonInput.value;
                            studentReasonOther.style.display = '';
                        }
                    }
                } else {
                    visitorReason.name = 'reason';
                    reasonInput.removeAttribute('name');
                    reasonInput.required = false;
                }

                syncStudentReason();
            }

            function syncStudentReason() {
                const selected = studentReason.value;

                if (selected === 'Other') {
                    studentReasonOther.style.display = '';
                    studentReasonOther.required = true;
                    reasonInput.value = studentReasonOther.value.trim();
                } else {
                    studentReasonOther.style.display = 'none';
                    studentReasonOther.required = false;
                    reasonInput.value = selected;
                }
            }

            studentReason.addEventListener('change', syncStudentReason);

            studentReasonOther.addEventListener('input', function () {
                if (studentReason.value === 'Other') {
                    reasonInput.value = studentReasonOther.value.trim();
                }
            });

            visitorReason.addEventListener('input', function () {
                if (type.value === 'visitor') {
                    visitorReason.value = visitorReason.value.slice(0, 255);
                }
            });

            type.addEventListener('change', syncBypassFields);

            const bypassForm = modal.querySelector('form');

            bypassForm.addEventListener('submit', function () {
                if (type.value === 'student') {
                    syncStudentReason();
                } else {
                    reasonInput.value = visitorReason.value.trim();
                    reasonInput.name = 'reason';
                    visitorReason.removeAttribute('name');
                }
            });

            openButton.addEventListener('click', openModal);
            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
            syncBypassFields();

            if (modal.dataset.openOnLoad === 'true') {
                openModal();
            }
        }());
        </script>


        <!-- ==================================================
             STATISTICS
        =================================================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Today's Bypass
                </div>

                <div class="stat-number">
                    <?= number_format($todayBypass) ?>
                </div>

                <div class="stat-note">
                    All bypass transactions today
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
                    Bypass entries today
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
                    Bypass exits today
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Filtered Students
                </div>

                <div class="stat-number">
                    <?= number_format($filteredStudents) ?>
                </div>

                <div class="stat-note">
                    Student bypass records
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Filtered Visitors
                </div>

                <div class="stat-number">
                    <?= number_format($filteredVisitors) ?>
                </div>

                <div class="stat-note">
                    Visitor bypass records
                </div>

            </div>

        </section>


        <!-- ==================================================
             FILTER PANEL
        =================================================== -->

        <section
            class="panel filters-panel"
        >


            <div class="panel-header">

                <div>

                    <div class="panel-title">
                        Search & Filters
                    </div>

                    <div class="panel-subtitle">
                        Filter bypass history by person,
                        academic period, date, or direction.
                    </div>

                </div>

            </div>


            <div class="panel-body">


                <form
                    method="GET"
                    action="bypass_logs.php"
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
                                placeholder="ID, visitor, name, reason..."
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
                                        Week <?= $weekNumber ?>
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
                                    All Directions
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


                        <!-- BUTTONS -->

                        <div class="filter-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Filter
                            </button>

                            <a
                                href="bypass_logs.php"
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
                        Bypass History
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
                            $filteredIn
                        ) ?>

                        IN

                        /

                        <?= number_format(
                            $filteredOut
                        ) ?>

                        OUT

                    </div>

                </div>

            </div>


            <div class="table-wrapper">


                <?php if (
                    $filteredTotal > 0
                ): ?>


                    <table
                        id="bypassTable"
                    >


                        <thead>

                            <tr>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Person
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
                                    Reason
                                </th>

                                <th>
                                    Date & Time
                                </th>

                                <th>
                                    Processed By
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $logs
                            as $log
                        ): ?>


                            <tr>


                                <!-- TYPE -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $log["student_id"]
                                        )
                                    ): ?>

                                        <span
                                            class="
                                                badge
                                                badge-student
                                            "
                                        >
                                            STUDENT
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                badge
                                                badge-visitor
                                            "
                                        >
                                            VISITOR
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- PERSON -->

                                <td>


                                    <div
                                        class="person-cell"
                                    >


                                        <?php if (
                                            !empty(
                                                $log["student_id"]
                                            )
                                        ): ?>


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
                                                    $log[
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
                                                        person-photo
                                                    "
                                                    alt="Student"
                                                    onerror="
                                                        this.style.display='none';
                                                        this.nextElementSibling.style.display='flex';
                                                    "
                                                >

                                                <div
                                                    class="
                                                        person-placeholder
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
                                                        person-placeholder
                                                    "
                                                >
                                                    👤
                                                </div>

                                            <?php endif; ?>


                                            <div>

                                                <div
                                                    class="
                                                        person-name
                                                    "
                                                >
                                                    <?= e(
                                                        $log[
                                                            "full_name"
                                                        ]
                                                        ?:
                                                        "Unknown Student"
                                                    ) ?>
                                                </div>

                                                <div
                                                    class="
                                                        person-id
                                                    "
                                                >
                                                    <?= e(
                                                        $log[
                                                            "student_id"
                                                        ]
                                                    ) ?>
                                                </div>

                                            </div>


                                        <?php else: ?>


                                            <div
                                                class="
                                                    person-placeholder
                                                "
                                            >
                                                👤
                                            </div>


                                            <div>

                                                <div
                                                    class="
                                                        person-name
                                                    "
                                                >
                                                    <?= e(
                                                        $log[
                                                            "visitor_name"
                                                        ]
                                                        ?:
                                                        "Visitor"
                                                    ) ?>
                                                </div>

                                                <div
                                                    class="
                                                        person-id
                                                    "
                                                >
                                                    Visitor
                                                </div>

                                            </div>


                                        <?php endif; ?>


                                    </div>

                                </td>


                                <!-- PROGRAM -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $log["student_id"]
                                        )
                                    ): ?>

                                        <?= e(
                                            $log["program"]
                                            ?? "-"
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>


                                <!-- YEAR -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $log["student_id"]
                                        )
                                    ): ?>

                                        <?= e(
                                            $log["year_level"]
                                            ?? "-"
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>


                                <!-- SECTION -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $log["student_id"]
                                        )
                                    ): ?>

                                        <?= e(
                                            $log["section"]
                                            ?? "-"
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

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


                                <!-- REASON -->

                                <td>

                                    <div
                                        class="reason"
                                    >

                                        <?= e(
                                            $log["reason"]
                                            ?: "-"
                                        ) ?>

                                    </div>

                                </td>


                                <!-- TIME -->

                                <td>

                                    <?= e(
                                        date(
                                            "M d, Y h:i:s A",
                                            strtotime(
                                                $log[
                                                    "bypass_time"
                                                ]
                                            )
                                        )
                                    ) ?>

                                </td>


                                <!-- USER -->

                                <td>

                                    <?= e(
                                        $log[
                                            "processed_by"
                                        ]
                                        ?:
                                        "Unknown User"
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>


                <?php else: ?>


                    <div class="empty">

                        No bypass records match
                        the selected filters.

                    </div>


                <?php endif; ?>


            </div>

        </section>


        <div class="footer">

            SmartGate • Bypass Logs

        </div>


    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| EXPORT CSV
|--------------------------------------------------------------------------
*/

function exportCSV() {

    const table =
        document.getElementById(
            "bypassTable"
        );


    if (!table) {

        alert(
            "There are no bypass records to export."
        );

        return;
    }


    let csv = [];


    const rows =
        table.querySelectorAll(
            "tr"
        );


    rows.forEach(function(row) {

        const columns =
            row.querySelectorAll(
                "th, td"
            );


        let rowData = [];


        columns.forEach(function(column) {

            let text =
                column.innerText
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
        "smartgate_bypass_logs_" +
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