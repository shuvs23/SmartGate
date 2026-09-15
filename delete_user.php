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

// Prevent Super Admin from deleting their own account
if ($id === intval($_SESSION['user_id'])) {
    header("Location: user_management.php");
    exit;
}

// Get user information first
$stmt = $conn->prepare("
    SELECT username, full_name
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

// Delete user
$delete = $conn->prepare("
    DELETE FROM users
    WHERE id = ?
");

$delete->bind_param("i", $id);

if ($delete->execute()) {

    // Audit trail
    $action = "DELETE USER";
    $description = "Deleted user: " . $user['username']
                 . " (" . $user['full_name'] . ")";

    $target_type = "USER";
    $target_id = (string)$id;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

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
