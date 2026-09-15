<?php

require_once __DIR__ . "/security.php";
smartgate_security_headers();

require_once __DIR__ . "/db.php";

header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ------------------------------------------------------------
// DISPLAY CONFIGURATION
// ------------------------------------------------------------
// The UI is intended to show an event for 3 seconds.
// A slightly longer server-side detection window prevents the
// browser from missing a very fast scan because of network/HTTP
// polling delay. Once detected, the browser still uses the normal
// 3-second display timer.
const DISPLAY_SECONDS = 3;
const DETECTION_WINDOW_SECONDS = 5;

$response = [
    "success" => false,
    "display" => false,
    "message" => "No active scan."
];


// ============================================================
// GET LATEST QR SCAN
// ============================================================

$scanSql = "
    SELECT
        se.id,
        se.student_id,
        se.success,
        se.message,
        se.scan_time,
        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.photo,
        TIMESTAMPDIFF(
            SECOND,
            se.scan_time,
            NOW()
        ) AS seconds_elapsed
    FROM scan_events se
    LEFT JOIN students s
        ON se.student_id = s.student_id
    ORDER BY se.id DESC
    LIMIT 1
";

$scanResult = $conn->query($scanSql);


// ============================================================
// GET LATEST BYPASS EVENT
// ============================================================

$bypassSql = "
    SELECT
        de.id,
        de.student_id,
        de.visitor_name,
        de.direction,
        de.reason,
        de.display_time,

        s.full_name,
        s.program,
        s.year_level,
        s.section,
        s.photo,

        TIMESTAMPDIFF(
            SECOND,
            de.display_time,
            NOW()
        ) AS seconds_elapsed

    FROM display_events de

    LEFT JOIN students s
        ON de.student_id = s.student_id

    WHERE de.display_type = 'BYPASS'

    ORDER BY de.id DESC
    LIMIT 1
";

$bypassResult = $conn->query($bypassSql);


$scanRow = ($scanResult && $scanResult->num_rows > 0)
    ? $scanResult->fetch_assoc()
    : null;

$bypassRow = ($bypassResult && $bypassResult->num_rows > 0)
    ? $bypassResult->fetch_assoc()
    : null;


// ============================================================
// NO EVENT
// ============================================================

if ($scanRow === null && $bypassRow === null) {

    echo json_encode($response);
    exit;
}


// ============================================================
// DETERMINE WHICH EVENT IS NEWER
// ============================================================

$useBypass = false;

if ($bypassRow !== null && $scanRow === null) {

    $useBypass = true;

} elseif ($bypassRow !== null && $scanRow !== null) {

    $bypassTime = strtotime($bypassRow["display_time"]);
    $scanTime = strtotime($scanRow["scan_time"]);

    if ($bypassTime > $scanTime) {
        $useBypass = true;
    }
}


// ============================================================
// BYPASS DISPLAY
// ============================================================

if ($useBypass) {

    $elapsed = (int)$bypassRow["seconds_elapsed"];


    // --------------------------------------------------------
    // DISPLAY ONLY FOR 5 SECONDS
    // --------------------------------------------------------

    if ($elapsed < 0 || $elapsed > DETECTION_WINDOW_SECONDS) {

        $response["message"] = "Display expired.";

        echo json_encode($response);
        exit;
    }


    // ========================================================
    // IMPORTANT:
    // DETERMINE VISITOR FIRST
    // ========================================================
    //
    // If visitor_name exists, this is ALWAYS a visitor bypass.
    //
    // We DO NOT determine visitor/student based only on
    // student_id because an old/incorrect record may contain
    // a student_id.
    //

    $visitorName = trim((string)($bypassRow["visitor_name"] ?? ""));
    $studentId   = trim((string)($bypassRow["student_id"] ?? ""));

    $isVisitor = ($visitorName !== "");
    $isStudent = (!$isVisitor && $studentId !== "");


    // ========================================================
    // VISITOR BYPASS
    // ========================================================

    if ($isVisitor) {

        $response = [
            "success" => true,
            "display" => true,

            "access_denied" => false,

            "scan_type" => "bypass",

            "scan_id" => "BYPASS-" . $bypassRow["id"],

            // ------------------------------------------------
            // EXPLICIT VISITOR TYPE
            // ------------------------------------------------

            "bypass_type" => "visitor",

            // ------------------------------------------------
            // VISITOR DATA
            // ------------------------------------------------

            "visitor_name" => $visitorName,

            "reason" => $bypassRow["reason"] ?? "",

            "direction" => $bypassRow["direction"] ?? "",
            "status" => $bypassRow["direction"] ?? "",

            "scan_time" => $bypassRow["display_time"],

            "seconds_elapsed" => $elapsed,

            "seconds_remaining" => max(
                0,
                DISPLAY_SECONDS - $elapsed
            )
        ];


        // ----------------------------------------------------
        // IMPORTANT:
        // DO NOT SEND student_id
        // DO NOT SEND full_name
        // DO NOT SEND program
        // DO NOT SEND year_level
        // DO NOT SEND section
        // DO NOT SEND photo
        //
        // Visitor display should ONLY use:
        // visitor_name + reason
        // ----------------------------------------------------

        echo json_encode(
            $response,
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    // ========================================================
    // STUDENT BYPASS
    // ========================================================

    if ($isStudent) {

        $response = [
            "success" => true,
            "display" => true,

            "access_denied" => false,

            "scan_type" => "bypass",

            "scan_id" => "BYPASS-" . $bypassRow["id"],

            // ------------------------------------------------
            // EXPLICIT STUDENT TYPE
            // ------------------------------------------------

            "bypass_type" => "student",

            // ------------------------------------------------
            // STUDENT DATA
            // ------------------------------------------------

            "student_id" => $studentId,

            "full_name" => $bypassRow["full_name"] ?? "",

            "visitor_name" => "",

            "program" => $bypassRow["program"] ?? "",

            "year_level" => $bypassRow["year_level"] ?? "",

            "section" => $bypassRow["section"] ?? "",

            "photo" => $bypassRow["photo"] ?? "",

            "direction" => $bypassRow["direction"] ?? "",

            "status" => $bypassRow["direction"] ?? "",

            "reason" => $bypassRow["reason"] ?? "",

            "scan_time" => $bypassRow["display_time"],

            "seconds_elapsed" => $elapsed,

            "seconds_remaining" => max(
                0,
                DISPLAY_SECONDS - $elapsed
            )
        ];


        echo json_encode(
            $response,
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    // ========================================================
    // UNKNOWN BYPASS TYPE
    // ========================================================
    //
    // If neither visitor_name nor student_id exists,
    // do NOT accidentally display a student layout.
    //

    $response = [
        "success" => false,
        "display" => false,
        "message" => "Invalid bypass data."
    ];

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ============================================================
// QR SCAN DISPLAY
// ============================================================

$elapsed = (int)$scanRow["seconds_elapsed"];


// ------------------------------------------------------------
// ONLY DISPLAY FOR 5 SECONDS
// ------------------------------------------------------------

if ($elapsed < 0 || $elapsed > DETECTION_WINDOW_SECONDS) {

    $response["message"] = "Display expired.";

    echo json_encode($response);
    exit;
}


// ============================================================
// INVALID QR
// ============================================================

if ((int)$scanRow["success"] === 0) {

    $response = [
        "success" => false,

        "display" => true,

        "access_denied" => true,

        "scan_type" => "invalid",

        "message" => $scanRow["message"],

        "scan_id" => $scanRow["id"],

        "scan_time" => $scanRow["scan_time"],

        "seconds_elapsed" => $elapsed,

        "seconds_remaining" => max(
            0,
            DISPLAY_SECONDS - $elapsed
        )
    ];

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ============================================================
// VALID QR
// ============================================================

$direction = "";

if (strpos($scanRow["message"], " - IN") !== false) {

    $direction = "IN";

} elseif (strpos($scanRow["message"], " - OUT") !== false) {

    $direction = "OUT";
}


// ============================================================
// VALID STUDENT RESPONSE
// ============================================================

$response = [
    "success" => true,

    "display" => true,

    "access_denied" => false,

    "scan_type" => "valid",

    "scan_id" => $scanRow["id"],

    "student_id" => $scanRow["student_id"],

    "full_name" => $scanRow["full_name"],

    "program" => $scanRow["program"],

    "year_level" => $scanRow["year_level"],

    "section" => $scanRow["section"],

    "photo" => $scanRow["photo"],

    "direction" => $direction,

    "status" => $direction,

    "scan_time" => $scanRow["scan_time"],

    "seconds_elapsed" => $elapsed,

    "seconds_remaining" => max(
        0,
        DISPLAY_SECONDS - $elapsed
    )
];


echo json_encode(
    $response,
    JSON_UNESCAPED_UNICODE
);

?>