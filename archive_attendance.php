<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Only MIS and Super Admin can archive attendance records
$allowed_roles = [
    'super_admin',
    'MIS'
];

if (!in_array($_SESSION['role'], $allowed_roles, true)) {
    die("Access Denied.");
}

smartgate_require_csrf();

// Get attendance record ID
$attendance_id = intval($_POST['id'] ?? 0);

if ($attendance_id <= 0) {
    die("Invalid attendance record.");
}

// Get attendance record
$stmt = $conn->prepare("
    SELECT
        id,
        student_id,
        direction,
        scan_time,
        device,
        remarks
    FROM attendance_logs
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $attendance_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Attendance record not found.");
}

$log = $result->fetch_assoc();

$stmt->close();

// Start transaction
$conn->begin_transaction();

try {

    // Insert into archive
    $archive_stmt = $conn->prepare("
        INSERT INTO archived_attendance_logs
        (
            original_id,
            student_id,
            direction,
            scan_time,
            device,
            remarks,
            archived_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $archive_stmt->bind_param(
        "isssssi",
        $log['id'],
        $log['student_id'],
        $log['direction'],
        $log['scan_time'],
        $log['device'],
        $log['remarks'],
        $_SESSION['user_id']
    );

    if (!$archive_stmt->execute()) {
        throw new Exception("Failed to archive attendance record.");
    }

    $archive_stmt->close();

    // Delete original attendance record
    $delete_stmt = $conn->prepare("
        DELETE FROM attendance_logs
        WHERE id = ?
    ");

    $delete_stmt->bind_param("i", $attendance_id);

    if (!$delete_stmt->execute()) {
        throw new Exception("Failed to remove original attendance record.");
    }

    $delete_stmt->close();

    // Audit trail
    $audit_action = "ARCHIVE ATTENDANCE";
    $audit_description =
        "Archived attendance record ID "
        . $attendance_id
        . " for student "
        . $log['student_id'];

    $target_type = "ATTENDANCE";
    $target_id = (string)$attendance_id;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    $audit_stmt = $conn->prepare("
        INSERT INTO system_audit_logs
        (
            user_id,
            action,
            description,
            target_type,
            target_id,
            ip_address
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $audit_stmt->bind_param(
        "isssss",
        $_SESSION['user_id'],
        $audit_action,
        $audit_description,
        $target_type,
        $target_id,
        $ip_address
    );

    if (!$audit_stmt->execute()) {
        throw new Exception("Failed to create audit record.");
    }

    $audit_stmt->close();

    // Commit
    $conn->commit();

    header("Location: attendance_logs.php?archive=success");
    exit;

} catch (Exception $e) {

    // Rollback if anything failed
    $conn->rollback();

    error_log("SmartGate archive attendance failed: " . $e->getMessage());
    die("Unable to archive the attendance record right now.");
}
?>
