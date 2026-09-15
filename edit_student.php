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

$error = "";
$success = "";

$studentDbId = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

if ($studentDbId <= 0) {
    die("Invalid student ID.");
}


/* ================================
   UPDATE STUDENT
================================ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    smartgate_require_csrf();

    $studentId = trim($_POST["student_id"] ?? "");
    $fullName = trim($_POST["full_name"] ?? "");
    $program = trim($_POST["program"] ?? "");
    $yearLevel = trim($_POST["year_level"] ?? "");
    $section = trim($_POST["section"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $parentEmail = trim($_POST["parent_email"] ?? "");
    $qrCode = trim($_POST["qr_code"] ?? "");
    $qrValidFrom = trim($_POST["qr_valid_from"] ?? "");
    $qrValidUntil = trim($_POST["qr_valid_until"] ?? "");
    if (
        $studentId === "" ||
        $fullName === "" ||
        $program === "" ||
        $yearLevel === "" ||
        $section === ""
    ) {

        $error = "Please fill in all required fields.";

    } elseif (
        ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
        ($parentEmail !== "" && !filter_var($parentEmail, FILTER_VALIDATE_EMAIL))
    ) {

        $error = "Please enter valid email addresses.";

    } else {

        /* Check duplicate Student ID */

        $checkSql = "
            SELECT id
            FROM students
            WHERE student_id = ?
            AND id != ?
            LIMIT 1
        ";

        $checkStmt = $conn->prepare($checkSql);

        $checkStmt->bind_param(
            "si",
            $studentId,
            $studentDbId
        );

        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {

            $error = "Student ID already belongs to another student.";

        }

        $checkStmt->close();


        /* Check duplicate QR */

        if ($error === "" && $qrCode !== "") {

            $qrSql = "
                SELECT id
                FROM students
                WHERE qr_code = ?
                AND id != ?
                LIMIT 1
            ";

            $qrStmt = $conn->prepare($qrSql);

            $qrStmt->bind_param(
                "si",
                $qrCode,
                $studentDbId
            );

            $qrStmt->execute();

            $qrResult = $qrStmt->get_result();

            if ($qrResult->num_rows > 0) {

                $error =
                    "QR code is already assigned to another student.";

            }

            $qrStmt->close();
        }


        /* Update */

        if ($error === "") {

            $sql = "
        UPDATE students
        SET
        student_id = ?,
        full_name = ?,
        program = ?,
        year_level = ?,
        section = ?,
        email = ?,
        parent_email = ?,
        qr_code = ?,
        qr_valid_from = NULLIF(?, ''),
        qr_valid_until = NULLIF(?, '')
    WHERE id = ?
";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {

                $error =
                    "Unable to save the student right now.";
                error_log("SmartGate edit student prepare failed: " . $conn->error);

            } else {

                $stmt->bind_param(
                "ssssssssssi",
                $studentId,
                $fullName,
                $program,
                $yearLevel,
                $section,
                $email,
                $parentEmail,
                $qrCode,
                $qrValidFrom,
                $qrValidUntil,
                $studentDbId
            );

                if ($stmt->execute()) {

                    /* ================================
                       AUDIT TRAIL
                    ================================= */

                    $action = "EDIT STUDENT";

                    $description =
                        "Edited student " .
                        $studentId .
                        " (" .
                        $fullName .
                        ").";

                    $targetType = "STUDENT";

                    $targetId = $studentId;

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


                    $success =
                        "Student information updated successfully.";

                } else {

                    error_log("SmartGate edit student failed: " . $stmt->error);
                    $error = "Unable to save the student right now.";

                }

                $stmt->close();
            }
        }
    }
}


/* ================================
   GET STUDENT
================================ */

$sql = "
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit Student - SmartGate</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #333;
        }

        .header {
            background: #1468a8;
            color: white;
            padding: 20px 30px;
        }

        .header-content {
            max-width: 1200px;
            margin: auto;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .header h1 {
            margin: 0 0 5px;
            font-size: 24px;
        }

        .header p {
            margin: 0;
            font-size: 14px;
        }

        .back {
            display: inline-block;
            padding: 9px 15px;

            background: white;
            color: #1468a8;

            text-decoration: none;
            border-radius: 6px;

            font-weight: bold;
            font-size: 14px;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);
        }

        .card h2 {
            margin-top: 0;
            color: #1468a8;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select {
            padding: 11px;

            border: 1px solid #ccc;
            border-radius: 6px;

            font-size: 14px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #1468a8;
        }

        .required {
            color: #b00020;
        }

        .help {
            margin-top: 5px;
            color: #777;
            font-size: 12px;
        }

        .button-row {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 11px 18px;

            border: none;
            border-radius: 6px;

            background: #1468a8;
            color: white;

            text-decoration: none;
            cursor: pointer;

            font-size: 14px;
        }

        .btn:hover {
            background: #0f568c;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .success {
            margin-bottom: 20px;
            padding: 12px;

            background: #e2f5e8;
            color: #176b35;

            border-radius: 6px;
        }

        .error {
            margin-bottom: 20px;
            padding: 12px;

            background: #ffe5e5;
            color: #b00020;

            border-radius: 6px;
        }

        .info-box {
            margin-bottom: 20px;
            padding: 12px;

            background: #f1f7fc;

            border-left: 4px solid #1468a8;

            font-size: 13px;
        }

        @media (max-width: 700px) {

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">

<header class="header">

    <div class="header-content">

        <div>

            <h1>
                Edit Student
            </h1>

            <p>
                SmartGate Student Management
            </p>

        </div>

        <a
            href="student_management.php"
            class="back"
        >
            Back
        </a>

    </div>

</header>


<div class="container">

    <div class="card">

        <h2>
            Student Information
        </h2>


        <?php if ($success !== ""): ?>

            <div class="success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="info-box">

            Current Status:
            <strong>
                <?= htmlspecialchars($student["current_status"]) ?>
            </strong>

            &nbsp;&nbsp;|&nbsp;&nbsp;

            Account:
            <strong>
                <?= (int)$student["is_active"] === 1
                    ? "ACTIVE"
                    : "INACTIVE" ?>
            </strong>

        </div>


        <form
            method="POST"
            action="edit_student.php"
        >

            <?= smartgate_csrf_field() ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int)$student["id"] ?>"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label for="student_id">

                        Student ID
                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="student_id"
                        name="student_id"
                        value="<?= htmlspecialchars($student["student_id"]) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="full_name">

                        Full Name
                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($student["full_name"]) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="program">

                        Program
                        <span class="required">*</span>

                    </label>

                    <select
                        id="program"
                        name="program"
                        required
                    >

                        <option value="">
                            Select Program
                        </option>

                        <option
                            value="BSIT"
                            <?= $student["program"] === "BSIT"
                                ? "selected"
                                : "" ?>
                        >
                            BSIT
                        </option>

                        <option
                            value="BSTM"
                            <?= $student["program"] === "BSTM"
                                ? "selected"
                                : "" ?>
                        >
                            BSTM
                        </option>

                        <option
                            value="BSHM"
                            <?= $student["program"] === "BSHM"
                                ? "selected"
                                : "" ?>
                        >
                            BSHM
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="year_level">

                        Year Level
                        <span class="required">*</span>

                    </label>

                    <select
                        id="year_level"
                        name="year_level"
                        required
                    >

                        <option value="">
                            Select Year
                        </option>

                        <?php for ($year = 1; $year <= 4; $year++): ?>

                            <option
                                value="<?= $year ?>"
                                <?= $student["year_level"] == $year
                                    ? "selected"
                                    : "" ?>
                            >
                                Year <?= $year ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="section">

                        Section
                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="section"
                        name="section"
                        value="<?= htmlspecialchars($student["section"]) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        Student Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($student["email"] ?? "") ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="parent_email">
                        Parent/Guardian Email
                    </label>

                    <input
                        type="email"
                        id="parent_email"
                        name="parent_email"
                        value="<?= htmlspecialchars($student["parent_email"] ?? "") ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="qr_code">
                        QR Code
                    </label>

                    <input
                        type="text"
                        id="qr_code"
                        name="qr_code"
                        value="<?= htmlspecialchars($student["qr_code"] ?? "") ?>"
                    >

                    <div class="help">
                        Enter the value encoded in the student's QR code.
                    </div>

                </div>

                <div class="form-group">

    <label for="qr_valid_from">
        QR Valid From
    </label>

    <input
        type="datetime-local"
        id="qr_valid_from"
        name="qr_valid_from"
        value="<?= !empty($student["qr_valid_from"])
            ? date("Y-m-d\TH:i", strtotime($student["qr_valid_from"]))
            : "" ?>"
    >

    <div class="help">
        Date and time when the student's QR code becomes valid.
    </div>

</div>


<div class="form-group">

    <label for="qr_valid_until">
        QR Valid Until
    </label>

    <input
        type="datetime-local"
        id="qr_valid_until"
        name="qr_valid_until"
        value="<?= !empty($student["qr_valid_until"])
            ? date("Y-m-d\TH:i", strtotime($student["qr_valid_until"]))
            : "" ?>"
    >

    <div class="help">
        Date and time when the student's QR code expires.
    </div>

</div>


            </div>


            <div class="button-row">

                <button
                    type="submit"
                    class="btn"
                >
                    Save Changes
                </button>

                <a
                    href="student_management.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>


</div>
</body>

</html>

<?php

$conn->close();

?>
