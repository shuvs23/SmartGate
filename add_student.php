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


/* ================================
   ADD STUDENT
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


    /* Required fields */

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

        /* Check duplicate student ID */

        $checkSql = "
            SELECT id
            FROM students
            WHERE student_id = ?
            LIMIT 1
        ";

        $checkStmt = $conn->prepare($checkSql);

        $checkStmt->bind_param(
            "s",
            $studentId
        );

        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();


        if ($checkResult->num_rows > 0) {

            $error =
                "Student ID already exists.";

        } else {

            /* Check duplicate QR */

            if ($qrCode !== "") {

                $qrSql = "
                    SELECT id
                    FROM students
                    WHERE qr_code = ?
                    LIMIT 1
                ";

                $qrStmt = $conn->prepare($qrSql);

                $qrStmt->bind_param(
                    "s",
                    $qrCode
                );

                $qrStmt->execute();

                $qrResult = $qrStmt->get_result();


                if ($qrResult->num_rows > 0) {

                    $error =
                        "QR code is already assigned to another student.";

                }

                $qrStmt->close();

            }


            /* Insert student */

            if ($error === "") {

                $sql = "
                    INSERT INTO students
                    (
                        student_id,
                        full_name,
                        program,
                        year_level,
                        section,
                        email,
                        parent_email,
                        qr_code,
                        current_status,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OUT', 1)
                ";

                $stmt = $conn->prepare($sql);


                if (!$stmt) {

                    error_log("SmartGate add student prepare failed: " . $conn->error);
                    $error = "Unable to save the student right now.";

                } else {

                    $stmt->bind_param(
                        "ssssssss",
                        $studentId,
                        $fullName,
                        $program,
                        $yearLevel,
                        $section,
                        $email,
                        $parentEmail,
                        $qrCode
                    );


                    if ($stmt->execute()) {

                        $newStudentId =
                            $conn->insert_id;


                        /* ================================
                           AUDIT TRAIL
                        ================================= */

                        $action =
                            "ADD STUDENT";

                        $description =
                            "Added student " .
                            $studentId .
                            " (" .
                            $fullName .
                            ").";

                        $targetType =
                            "STUDENT";

                        $targetId =
                            $studentId;

                        $ipAddress =
                            $_SERVER["REMOTE_ADDR"] ?? null;

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

                            $userId =
                                (int)$_SESSION["user_id"];

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
                            "Student added successfully.";

                    } else {

                    error_log("SmartGate add student failed: " . $stmt->error);
                    $error = "Unable to save the student right now.";

                    }

                    $stmt->close();

                }

            }

        }

        $checkStmt->close();

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Student - SmartGate</title>


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

            max-width: 900px;

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

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

        }

        .form-group {

            display: flex;

            flex-direction: column;

        }

        .form-group.full {

            grid-column: 1 / -1;

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

            font-size: 12px;

            color: #777;

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

        @media (max-width: 700px) {

            .header-content {

                flex-direction: column;

                align-items: flex-start;

            }

            .form-grid {

                grid-template-columns: 1fr;

            }

            .form-group.full {

                grid-column: auto;

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
                Add Student
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


        <form
            method="POST"
            action="add_student.php"
        >

            <?= smartgate_csrf_field() ?>

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
                        placeholder="Example: 3031-0001"
                        value="<?= htmlspecialchars($_POST["student_id"] ?? "") ?>"
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
                        placeholder="Student full name"
                        value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>"
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
                            <?= ($_POST["program"] ?? "") === "BSIT"
                                ? "selected"
                                : "" ?>
                        >
                            BSIT
                        </option>

                        <option
                            value="BSTM"
                            <?= ($_POST["program"] ?? "") === "BSTM"
                                ? "selected"
                                : "" ?>
                        >
                            BSTM
                        </option>

                        <option
                            value="BSHM"
                            <?= ($_POST["program"] ?? "") === "BSHM"
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

                        <option
                            value="1"
                            <?= ($_POST["year_level"] ?? "") === "1"
                                ? "selected"
                                : "" ?>
                        >
                            Year 1
                        </option>

                        <option
                            value="2"
                            <?= ($_POST["year_level"] ?? "") === "2"
                                ? "selected"
                                : "" ?>
                        >
                            Year 2
                        </option>

                        <option
                            value="3"
                            <?= ($_POST["year_level"] ?? "") === "3"
                                ? "selected"
                                : "" ?>
                        >
                            Year 3
                        </option>

                        <option
                            value="4"
                            <?= ($_POST["year_level"] ?? "") === "4"
                                ? "selected"
                                : "" ?>
                        >
                            Year 4
                        </option>

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
                        placeholder="Example: 1A"
                        value="<?= htmlspecialchars($_POST["section"] ?? "") ?>"
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
                        placeholder="student@example.com"
                        value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
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
                        placeholder="parent@example.com"
                        value="<?= htmlspecialchars($_POST["parent_email"] ?? "") ?>"
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
                        placeholder="QR code value"
                        value="<?= htmlspecialchars($_POST["qr_code"] ?? "") ?>"
                    >

                    <div class="help">

                        Leave blank if the QR code will be assigned later.

                    </div>

                </div>


            </div>


            <div class="button-row">

                <button
                    type="submit"
                    class="btn"
                >
                    Save Student
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
