<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();

require_once "db.php";

$userId = $_SESSION["user_id"] ?? null;

if ($userId) {

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
        VALUES
        (?, ?, ?, ?, ?, ?)
    ");

    $action = "LOGOUT";
    $description = "User logged out of SmartGate.";
    $targetType = "USER";
    $targetId = (string)$userId;
    $ip = $_SERVER["REMOTE_ADDR"] ?? null;

    $audit->bind_param(
        "isssss",
        $userId,
        $action,
        $description,
        $targetType,
        $targetId,
        $ip
    );

    $audit->execute();
    $audit->close();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;

?>
