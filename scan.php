<?php

header("Content-Type: application/json");
header("Cache-Control: no-store");

require_once __DIR__ . "/security.php";
smartgate_require_device_auth();

require_once __DIR__ . "/db.php";


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "POST is required."
    ]);

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | GET QR CODE
    |--------------------------------------------------------------------------
    */

    $qr_code = "";

    // JSON request from ESP32
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (
        is_array($input) &&
        isset($input["qr_code"])
    ) {

        $qr_code = trim(
            (string)$input["qr_code"]
        );
    }


    // Normal POST fallback
    if (
        $qr_code === "" &&
        isset($_POST["qr_code"])
    ) {

        $qr_code = trim(
            (string)$_POST["qr_code"]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK QR CODE
    |--------------------------------------------------------------------------
    */

    if (
        $qr_code === "" ||
        strlen($qr_code) > 100
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "QR code is required."
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | FIND STUDENT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            student_id,
            full_name,
            program,
            year_level,
            section,
            email,
            parent_email,
            qr_code,
            qr_valid_from,
            qr_valid_until,
            photo,
            current_status,
            is_active
        FROM students
        WHERE student_id = ?
           OR qr_code = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {

        throw new Exception(
            "Failed to prepare student query."
        );
    }


    $stmt->bind_param(
        "ss",
        $qr_code,
        $qr_code
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to execute student query."
        );
    }


    $result = $stmt->get_result();

    $student = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | INVALID QR
    |--------------------------------------------------------------------------
    */

    if (!$student) {

        $message = "Invalid QR code.";


        $stmt = $conn->prepare("
            INSERT INTO scan_events
                (
                    student_id,
                    success,
                    message,
                    scan_time,
                    device
                )
            VALUES
                (
                    NULL,
                    0,
                    ?,
                    NOW(),
                    'SMARTGATE'
                )
        ");


        if ($stmt) {

            $stmt->bind_param(
                "s",
                $message
            );

            $stmt->execute();

            $stmt->close();
        }


        $conn->commit();


        http_response_code(200);

        echo json_encode([
            "success" => false,
            "message" => $message
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | INACTIVE STUDENT
    |--------------------------------------------------------------------------
    */

    if ((int)$student["is_active"] !== 1) {

        $message = "Student account is inactive.";


        $stmt = $conn->prepare("
            INSERT INTO scan_events
                (
                    student_id,
                    success,
                    message,
                    scan_time,
                    device
                )
            VALUES
                (
                    ?,
                    0,
                    ?,
                    NOW(),
                    'SMARTGATE'
                )
        ");


        if ($stmt) {

            $stmt->bind_param(
                "ss",
                $student["student_id"],
                $message
            );

            $stmt->execute();

            $stmt->close();
        }


        $conn->commit();


        http_response_code(200);

        echo json_encode([
            "success" => false,
            "message" => $message,
            "student_id" =>
                $student["student_id"],
            "full_name" =>
                $student["full_name"]
        ]);

        exit;
    }
    /*
     |--------------------------------------------------------------------------
     | QR VALIDITY DATE CHECK
     |--------------------------------------------------------------------------
     |
     | NULL validity dates mean no date restriction.
     |
     */

    $now = date("Y-m-d H:i:s");

    if (
        !empty($student["qr_valid_from"]) &&
        $now < $student["qr_valid_from"]
    ) {

        $message = "QR code is not yet valid.";

        $eventStmt = $conn->prepare("
            INSERT INTO scan_events
            (
                student_id,
                success,
                message,
                scan_time,
                device
            )
            VALUES
            (
                ?,
                0,
                ?,
                NOW(),
                'SMARTGATE'
            )
        ");

        if ($eventStmt) {

            $eventStmt->bind_param(
                "ss",
                $student["student_id"],
                $message
            );

            $eventStmt->execute();
            $eventStmt->close();
        }

        $conn->commit();

        http_response_code(200);

        echo json_encode([
            "success" => false,
            "message" => $message,
            "student_id" => $student["student_id"],
            "full_name" => $student["full_name"],
            "qr_valid_from" => $student["qr_valid_from"],
            "qr_valid_until" => $student["qr_valid_until"]
        ]);

        exit;
    }

    if (
        !empty($student["qr_valid_until"]) &&
        $now > $student["qr_valid_until"]
    ) {

        $message = "QR code has expired.";

        $eventStmt = $conn->prepare("
            INSERT INTO scan_events
            (
                student_id,
                success,
                message,
                scan_time,
                device
            )
            VALUES
            (
                ?,
                0,
                ?,
                NOW(),
                'SMARTGATE'
            )
        ");

        if ($eventStmt) {

            $eventStmt->bind_param(
                "ss",
                $student["student_id"],
                $message
            );

            $eventStmt->execute();
            $eventStmt->close();
        }

        $conn->commit();

        http_response_code(200);

        echo json_encode([
            "success" => false,
            "message" => $message,
            "student_id" => $student["student_id"],
            "full_name" => $student["full_name"],
            "qr_valid_from" => $student["qr_valid_from"],
            "qr_valid_until" => $student["qr_valid_until"]
        ]);

        exit;
    }




    /*
    |--------------------------------------------------------------------------
    | DETERMINE DIRECTION
    |--------------------------------------------------------------------------
    |
    | Current status OUT = next scan is IN
    |
    | Current status IN = next scan is OUT
    |
    */

    if (
        strtoupper(
            trim(
                (string)$student["current_status"]
            )
        ) === "OUT"
    ) {

        $direction = "IN";

    } else {

        $direction = "OUT";
    }


    /*
    |--------------------------------------------------------------------------
    | SAME-DIRECTION COOLDOWN
    |--------------------------------------------------------------------------
    |
    | IMPORTANT FIX
    |
    | Only repeated scans of the SAME direction
    | are blocked for 10 seconds.
    |
    | IN  -> OUT = ALLOWED
    | OUT -> IN  = ALLOWED
    |
    | IN  -> IN  = BLOCKED
    | OUT -> OUT = BLOCKED
    |
    |--------------------------------------------------------------------------
    */

    $cooldownStmt = $conn->prepare("
        SELECT
            scan_time
        FROM attendance_logs
        WHERE student_id = ?
          AND direction = ?
          AND scan_time >= DATE_SUB(
                NOW(),
                INTERVAL 10 SECOND
          )
        ORDER BY id DESC
        LIMIT 1
    ");


    if (!$cooldownStmt) {

        throw new Exception(
            "Failed to prepare cooldown query."
        );
    }


    $cooldownStmt->bind_param(
        "ss",
        $student["student_id"],
        $direction
    );


    if (!$cooldownStmt->execute()) {

        throw new Exception(
            "Failed to execute cooldown query."
        );
    }


    $cooldownResult =
        $cooldownStmt->get_result();


    $recentScan =
        $cooldownResult->fetch_assoc();


    $cooldownStmt->close();


    /*
    |--------------------------------------------------------------------------
    | DUPLICATE SAME-DIRECTION SCAN
    |--------------------------------------------------------------------------
    */

    if ($recentScan) {

        $message =
            "Scan ignored. Please wait a few seconds before scanning again.";


        $eventStmt = $conn->prepare("
            INSERT INTO scan_events
                (
                    student_id,
                    success,
                    message,
                    scan_time,
                    device
                )
            VALUES
                (
                    ?,
                    0,
                    ?,
                    NOW(),
                    'SMARTGATE'
                )
        ");


        if ($eventStmt) {

            $eventStmt->bind_param(
                "ss",
                $student["student_id"],
                $message
            );

            $eventStmt->execute();

            $eventStmt->close();
        }


        $conn->commit();


        http_response_code(429);


        echo json_encode([

            "success" => false,

            "message" => $message,

            "student_id" =>
                $student["student_id"],

            "full_name" =>
                $student["full_name"],

            "direction" =>
                $direction,

            "last_scan_time" =>
                $recentScan["scan_time"]
        ]);


        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE ATTENDANCE
    |--------------------------------------------------------------------------
    */

    try {


        /*
        |----------------------------------------------------------
        | INSERT ATTENDANCE LOG
        |----------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO attendance_logs
                (
                    student_id,
                    direction,
                    scan_time,
                    device,
                    remarks
                )
            VALUES
                (
                    ?,
                    ?,
                    NOW(),
                    'SMARTGATE',
                    NULL
                )
        ");


        if (!$stmt) {

            throw new Exception(
                "Failed to prepare attendance query."
            );
        }


        $stmt->bind_param(
            "ss",
            $student["student_id"],
            $direction
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Failed to save attendance."
            );
        }


        $stmt->close();


        /*
        |----------------------------------------------------------
        | UPDATE STUDENT STATUS
        |----------------------------------------------------------
        */

        $stmt = $conn->prepare("
            UPDATE students
            SET current_status = ?
            WHERE student_id = ?
        ");


        if (!$stmt) {

            throw new Exception(
                "Failed to prepare status update."
            );
        }


        $stmt->bind_param(
            "ss",
            $direction,
            $student["student_id"]
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Failed to update student status."
            );
        }


        $stmt->close();


        /*
        |----------------------------------------------------------
        | SUCCESSFUL SCAN EVENT
        |----------------------------------------------------------
        */

        $message =
            "QR scan successful - " .
            $direction;


        $stmt = $conn->prepare("
            INSERT INTO scan_events
                (
                    student_id,
                    success,
                    message,
                    scan_time,
                    device
                )
            VALUES
                (
                    ?,
                    1,
                    ?,
                    NOW(),
                    'SMARTGATE'
                )
        ");


        if (!$stmt) {

            throw new Exception(
                "Failed to prepare scan event."
            );
        }


        $stmt->bind_param(
            "ss",
            $student["student_id"],
            $message
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Failed to save scan event."
            );
        }


        $stmt->close();


        /*
        |----------------------------------------------------------
        | COMMIT
        |----------------------------------------------------------
        */

        $conn->commit();

    }

    catch (Exception $e) {

        $conn->rollback();


        http_response_code(500);


        echo json_encode([
            "success" => false,
            "message" =>
                "Failed to save attendance."
        ]);


        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | GET ACTUAL SCAN TIME
    |--------------------------------------------------------------------------
    */

    $scan_time =
        date("Y-m-d H:i:s");


    $stmt = $conn->prepare("
        SELECT
            scan_time
        FROM attendance_logs
        WHERE student_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "s",
            $student["student_id"]
        );


        if ($stmt->execute()) {

            $result =
                $stmt->get_result();


            $log =
                $result->fetch_assoc();


            if (
                $log &&
                !empty($log["scan_time"])
            ) {

                $scan_time =
                    $log["scan_time"];
            }
        }


        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    |
    | ESP32 receives this response.
    |
    */

    http_response_code(200);


    echo json_encode([

        "success" => true,

        "message" =>
            "QR scan successful.",

        "student_id" =>
            $student["student_id"],

        "full_name" =>
            $student["full_name"],

        "program" =>
            $student["program"],

        "year_level" =>
            $student["year_level"],

        "section" =>
            $student["section"],

        "direction" =>
            $direction,

        "current_status" =>
            $direction,

        "photo" =>
            $student["photo"],

        "scan_time" =>
            $scan_time
    ]);


    exit;

}


/*
|--------------------------------------------------------------------------
| GENERAL SERVER ERROR
|--------------------------------------------------------------------------
*/

catch (Exception $e) {

    if (
        isset($conn) &&
        $conn instanceof mysqli
    ) {

        try {

            $conn->rollback();

        } catch (Throwable $ignored) {

        }
    }


    http_response_code(500);


    echo json_encode([

        "success" => false,

        "message" =>
            "Server error."
    ]);


    exit;
}

?>