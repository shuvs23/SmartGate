<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$allowedRoles = ["super_admin", "MIS"];

if (!in_array($_SESSION["role"], $allowedRoles, true)) {
    die("Access denied.");
}

require_once "db.php";

smartgate_require_csrf();


/* ================================
   GET STUDENT
================================ */

$studentDbId = (int)($_POST["id"] ?? 0);

if ($studentDbId <= 0) {
    die("Invalid student ID.");
}


/* ================================
   GET CURRENT INFORMATION
================================ */

$sql = "
    SELECT
        id,
        student_id,
        full_name,
        is_active
    FROM students
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $studentDbId
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();
    $conn->close();

    die("Student not found.");
}

$student = $result->fetch_assoc();

$stmt->close();


/* ================================
   TOGGLE STATUS
================================ */

$newStatus =
    ((int)$student["is_active"] === 1)
    ? 0
    : 1;

$updateSql = "
    UPDATE students
    SET is_active = ?
    WHERE id = ?
";

$updateStmt = $conn->prepare($updateSql);

$updateStmt->bind_param(
    "ii",
    $newStatus,
    $studentDbId
);

if (!$updateStmt->execute()) {

    $updateStmt->close();
    $conn->close();

    die("Unable to update student status.");

}

$updateStmt->close();


/* ================================
   AUDIT TRAIL
================================ */

$action =
    $newStatus === 1
    ? "ACTIVATE STUDENT"
    : "DEACTIVATE STUDENT";

$statusText =
    $newStatus === 1
    ? "activated"
    : "deactivated";

$description =
    "Student " .
    $student["student_id"] .
    " (" .
    $student["full_name"] .
    ") was " .
    $statusText .
    ".";

$targetType = "STUDENT";

$targetId =
    $student["student_id"];

$ipAddress =
    $_SERVER["REMOTE_ADDR"] ?? null;

$userId =
    (int)$_SESSION["user_id"];


$auditSql = "
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
";

$auditStmt =
    $conn->prepare($auditSql);

if ($auditStmt) {

    $auditStmt->bind_param(
        "isssss",
        $userId,
        $action,
        $description,
        $targetType,
        $targetId,
        $ipAddress
    );

    $auditStmt->execute();

    $auditStmt->close();
}


$conn->close();


/* ================================
   RETURN TO STUDENT MANAGEMENT
================================ */

header(
    "Location: student_management.php"
);

exit;

?>
