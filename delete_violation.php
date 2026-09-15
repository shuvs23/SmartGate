<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$allowedRoles = [
    "super_admin",
    "CCDU",
    "Guidance"
];

if (!in_array($_SESSION["role"] ?? "", $allowedRoles, true)) {
    http_response_code(403);
    exit("Access denied.");
}

smartgate_require_csrf();

$violationId = (int)($_POST["id"] ?? 0);

if ($violationId <= 0) {
    header("Location: violations.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT student_id, violation_type
    FROM student_violations
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    header("Location: violations.php");
    exit;
}

$stmt->bind_param("i", $violationId);
$stmt->execute();
$violation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$violation) {
    header("Location: violations.php");
    exit;
}

$delete = $conn->prepare(
    "DELETE FROM student_violations WHERE id = ?"
);

if (!$delete) {
    header("Location: violations.php");
    exit;
}

$delete->bind_param("i", $violationId);
$deleted = $delete->execute();
$delete->close();

if ($deleted) {
    $audit = $conn->prepare("
        INSERT INTO system_audit_logs
        (user_id, action, description, target_type, target_id, ip_address)
        VALUES (?, 'DELETE VIOLATION', ?, 'VIOLATION', ?, ?)
    ");

    if ($audit) {
        $description =
            "Deleted {$violation["violation_type"]} for student " .
            $violation["student_id"] . ".";
        $targetId = (string)$violationId;
        $ip = $_SERVER["REMOTE_ADDR"] ?? "";
        $userId = (int)$_SESSION["user_id"];

        $audit->bind_param(
            "isss",
            $userId,
            $description,
            $targetId,
            $ip
        );
        $audit->execute();
        $audit->close();
    }
}

$conn->close();
header("Location: violations.php");
exit;

