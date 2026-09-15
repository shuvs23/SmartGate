<?php

header("Content-Type: application/json");
header("Cache-Control: no-store");

require_once __DIR__ . "/security.php";
smartgate_require_device_auth();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/smartgate_mailer.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST is required.'
    ]);
    exit;
}

try {

    // Get JSON data from ESP32
    $input = json_decode(file_get_contents("php://input"), true);

    // Also allow normal POST data
    if (!is_array($input)) {
        $input = $_POST;
    }

    $student_id = trim($input["student_id"] ?? "");
    $direction  = strtoupper(trim($input["direction"] ?? ""));

    /*
    |--------------------------------------------------------------------------
    | DATE FOR PARENT NOTIFICATION ONLY
    |--------------------------------------------------------------------------
    | Database time is NOT changed.
    | Parent email will show DATE ONLY.
    */

    date_default_timezone_set('Asia/Manila');

    $scan_time = date("F j, Y");

    // Validate student ID
    if ($student_id === "" || strlen($student_id) > 50) {
        echo json_encode([
            "success" => false,
            "message" => "Student ID is required."
        ]);
        exit;
    }

    // Validate direction
    if ($direction !== "IN" && $direction !== "OUT") {
        echo json_encode([
            "success" => false,
            "message" => "Invalid direction."
        ]);
        exit;
    }

    // Find student
    $stmt = $conn->prepare("
        SELECT
            student_id,
            full_name,
            parent_email
        FROM students
        WHERE student_id = ?
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->bind_param("s", $student_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    $stmt->close();

    // Student not found
    if (!$student) {
        echo json_encode([
            "success" => false,
            "message" => "Student not found."
        ]);
        exit;
    }

    // Parent email not registered
    if (empty($student["parent_email"])) {
        echo json_encode([
            "success" => false,
            "message" => "Parent email is not registered.",
            "student_id" => $student["student_id"]
        ]);
        exit;
    }

    // Send email
    $emailSent = sendSmartGateEmail(
        $student["parent_email"],
        $student["full_name"],
        $student["student_id"],
        $direction,
        $scan_time
    );

    if ($emailSent) {

        echo json_encode([
            "success" => true,
            "message" => "Notification email sent successfully.",
            "student_id" => $student["student_id"],
            "full_name" => $student["full_name"],
            "direction" => $direction,
            "scan_time" => $scan_time
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => "Failed to send notification email.",
            "student_id" => $student["student_id"],
            "direction" => $direction
        ]);
    }

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

?>