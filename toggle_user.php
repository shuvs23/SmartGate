<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

// Only Super Admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit;
}

smartgate_require_csrf();

// Get user ID
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: user_management.php");
    exit;
}

// Prevent Super Admin from deactivating their own account
if ($id === intval($_SESSION['user_id'])) {
    header("Location: user_management.php");
    exit;
}

// Get current user status
$stmt = $conn->prepare("
    SELECT username, is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: user_management.php");
    exit;
}

$user = $result->fetch_assoc();

// Toggle status
$new_status = $user['is_active'] ? 0 : 1;

$update = $conn->prepare("
    UPDATE users
    SET is_active = ?
    WHERE id = ?
");

$update->bind_param("ii", $new_status, $id);

if ($update->execute()) {

    // Determine audit action
    if ($new_status === 1) {
        $action = "ACTIVATE USER";
        $description = "Activated user: " . $user['username'];
    } else {
        $action = "DEACTIVATE USER";
        $description = "Deactivated user: " . $user['username'];
    }

    $target_type = "USER";
    $target_id = (string)$id;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    // Audit trail
    $audit = $conn->prepare("
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

    $audit->bind_param(
        "isssss",
        $_SESSION['user_id'],
        $action,
        $description,
        $target_type,
        $target_id,
        $ip_address
    );

    $audit->execute();
}

header("Location: user_management.php");
exit;
?>
