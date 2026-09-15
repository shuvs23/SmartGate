<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/smartgate_mailer.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "Guidance") {
    http_response_code(403);
    exit("Access denied.");
}

$violationId = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

if ($violationId <= 0) {
    exit("Invalid violation ID.");
}

$stmt = $conn->prepare("
    SELECT
        v.id,
        v.student_id,
        v.violation_type,
        v.description,
        v.action_taken,
        v.status,
        v.violation_time,
        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.email
    FROM student_violations v
    INNER JOIN students s ON v.student_id = s.student_id
    WHERE v.id = ?
    LIMIT 1
");

if (!$stmt) {
    exit("Unable to load the violation.");
}

$stmt->bind_param("i", $violationId);
$stmt->execute();
$result = $stmt->get_result();
$violation = $result->fetch_assoc();
$stmt->close();

if (!$violation) {
    exit("Violation record not found.");
}

if (
    empty($violation["email"]) ||
    !filter_var($violation["email"], FILTER_VALIDATE_EMAIL)
) {
    header(
        "Location: violations.php?email=error&message=" .
        urlencode("Student email is missing or invalid.")
    );
    exit;
}

$escape = static fn($value): string => htmlspecialchars(
    (string)$value,
    ENT_QUOTES,
    "UTF-8"
);

$name = $escape($violation["full_name"]);
$studentId = $escape($violation["student_id"]);
$program = $escape($violation["program"]);
$yearSection = $escape(
    $violation["year_level"] . " - " . $violation["section"]
);
$type = $escape($violation["violation_type"]);
$description = $escape($violation["description"] ?? "");
$action = $escape($violation["action_taken"] ?? "");
$status = $escape($violation["status"]);
$time = $escape($violation["violation_time"]);

$htmlBody = "
    <div style='font-family:Arial,sans-serif;line-height:1.6;color:#222'>
        <h2 style='color:#0b3d91'>SmartGate - Student Violation Notice</h2>
        <p>Dear <strong>{$name}</strong>,</p>
        <p>This is an official notification regarding a student violation recorded under your account.</p>
        <div style='background:#f4f6f9;padding:18px;border-radius:8px'>
            <p><strong>Student Name:</strong> {$name}</p>
            <p><strong>Student ID:</strong> {$studentId}</p>
            <p><strong>Program:</strong> {$program}</p>
            <p><strong>Year &amp; Section:</strong> {$yearSection}</p>
            <p><strong>Violation:</strong> {$type}</p>
            <p><strong>Description:</strong> {$description}</p>
            <p><strong>Action Taken:</strong> {$action}</p>
            <p><strong>Status:</strong> {$status}</p>
            <p><strong>Date &amp; Time:</strong> {$time}</p>
        </div>
        <p>Please coordinate with the Guidance Office if clarification is needed.</p>
        <p><strong>SmartGate Guidance Office</strong><br>Mabalacat City College</p>
    </div>
";

$textBody =
    "SmartGate - Student Violation Notice\n\n" .
    "Student Name: " . $violation["full_name"] . "\n" .
    "Student ID: " . $violation["student_id"] . "\n" .
    "Program: " . $violation["program"] . "\n" .
    "Year & Section: " . $violation["year_level"] . " - " . $violation["section"] . "\n" .
    "Violation: " . $violation["violation_type"] . "\n" .
    "Description: " . ($violation["description"] ?? "") . "\n" .
    "Action Taken: " . ($violation["action_taken"] ?? "") . "\n" .
    "Status: " . $violation["status"] . "\n" .
    "Date & Time: " . $violation["violation_time"] . "\n\n" .
    "Please coordinate with the Guidance Office if clarification is needed.";

$sent = smartgate_send_smtp_email(
    $violation["email"],
    $violation["full_name"],
    "SmartGate - Student Violation Notice",
    $htmlBody,
    $textBody
);

if (!$sent) {
    header(
        "Location: violations.php?email=error&message=" .
        urlencode("Email could not be sent. Check the SMTP configuration.")
    );
    exit;
}

$audit = $conn->prepare("
    INSERT INTO system_audit_logs
    (user_id, action, description, target_type, target_id, ip_address)
    VALUES (?, 'SEND VIOLATION EMAIL', ?, 'VIOLATION', ?, ?)
");

if ($audit) {
    $auditDescription =
        "Sent violation notification to student email: " . $violation["email"];
    $targetId = (string)$violationId;
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $userId = (int)$_SESSION["user_id"];
    $audit->bind_param(
        "isss",
        $userId,
        $auditDescription,
        $targetId,
        $ip
    );
    $audit->execute();
    $audit->close();
}

$conn->close();
header("Location: violations.php?email=success");
exit;

