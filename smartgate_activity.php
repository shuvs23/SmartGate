<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();
smartgate_security_headers();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Authentication required."
    ]);
    exit;
}

require_once __DIR__ . "/db.php";

$result = $conn->query(
    "SELECT
        (SELECT COALESCE(MAX(id), 0) FROM scan_events) AS latest_scan_id,
        (SELECT COALESCE(MAX(id), 0) FROM bypass_logs) AS latest_bypass_id"
);

if (!$result) {
    error_log("SmartGate activity check failed: " . $conn->error);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Activity check unavailable."
    ]);
    exit;
}

$activity = $result->fetch_assoc() ?: [];

echo json_encode([
    "success" => true,
    "scan_id" => (int)($activity["latest_scan_id"] ?? 0),
    "bypass_id" => (int)($activity["latest_bypass_id"] ?? 0)
]);

