<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "Guidance") {
    http_response_code(403);
    exit("Access denied.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    smartgate_require_csrf();
}

$violationId = (int)($_POST["id"] ?? $_GET["id"] ?? 0);

if ($violationId <= 0) {
    exit("Invalid violation ID.");
}

header("Location: send_email.php?id=" . $violationId);
exit;

