<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// Authorized personnel
$allowed_roles = [
    'super_admin',
    'CCDU',
    'Guidance',
    'Security'
];

if (!in_array($role, $allowed_roles, true)) {
    die("Access Denied.");
}

$violation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$violation_id) {
    die("Invalid violation ID.");
}

// Get violation record
$stmt = $conn->prepare("
    SELECT
        v.id,
        v.student_id,
        s.full_name,
        s.program,
        s.year_level,
        s.section,
        v.violation_type,
        v.description,
        v.action_taken,
        v.status,
        v.violation_time
    FROM student_violations v
    INNER JOIN students s
        ON v.student_id = s.student_id
    WHERE v.id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $violation_id);
$stmt->execute();
$result = $stmt->get_result();
$violation = $result->fetch_assoc();
$stmt->close();

if (!$violation) {
    die("Violation record not found.");
}

$error = "";
$success = "";

// Update violation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    smartgate_require_csrf();

    $new_status = $_POST['status'] ?? '';
    $new_action = trim($_POST['action_taken'] ?? '');

    $allowed_statuses = [
        'Pending',
        'Referred',
        'Resolved'
    ];

    if (!in_array($new_status, $allowed_statuses, true)) {
        $error = "Invalid status selected.";
    } else {

        $old_status = $violation['status'];

        $stmt = $conn->prepare("
            UPDATE student_violations
            SET
                status = ?,
                action_taken = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            $error = "Unable to prepare update.";
        } else {

            $stmt->bind_param(
                "ssi",
                $new_status,
                $new_action,
                $violation_id
            );

            if ($stmt->execute()) {

                // Record the change in the audit trail
                $audit_action = "UPDATE VIOLATION";
                $audit_description =
                    "Violation #{$violation_id} for student {$violation['student_id']} "
                    . "status changed from {$old_status} to {$new_status}.";

                $audit_target_type = "VIOLATION";
                $audit_target_id = (string)$violation_id;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

                $audit_stmt = $conn->prepare("
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

                if ($audit_stmt) {
                    $audit_stmt->bind_param(
                        "isssss",
                        $user_id,
                        $audit_action,
                        $audit_description,
                        $audit_target_type,
                        $audit_target_id,
                        $ip_address
                    );
                    $audit_stmt->execute();
                    $audit_stmt->close();
                }

                $success = "Violation record updated successfully.";

                // Refresh displayed values
                $violation['status'] = $new_status;
                $violation['action_taken'] = $new_action;

            } else {
                $error = "Failed to update violation record.";
            }

            $stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Violation - SmartGate</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        .header {
            background: #0b3d91;
            color: white;
            padding: 20px 30px;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
        }

        .header p {
            margin: 5px 0 0;
            opacity: .9;
        }

        .container {
            max-width: 850px;
            margin: 25px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-box {
            background: #f7f9fc;
            padding: 12px;
            border-radius: 6px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
            color: #0b3d91;
        }

        label {
            display: block;
            font-weight: bold;
            margin: 15px 0 7px;
        }

        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: Arial, sans-serif;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .buttons {
            margin-top: 20px;
        }

        button,
        .back-btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }

        button {
            background: #0b3d91;
            color: white;
        }

        .back-btn {
            background: #777;
            color: white;
            margin-left: 6px;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .error {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        @media (max-width: 650px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<div class="header">
    <h1>Edit Student Violation</h1>
    <p>
        SmartGate Management System |
        Logged in as: <?= htmlspecialchars($_SESSION['full_name']) ?>
    </p>
</div>

<div class="container">

    <div class="card">

        <?php if ($success !== ''): ?>
            <div class="success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="info-grid">

            <div class="info-box">
                <strong>Violation ID</strong>
                <?= (int)$violation['id'] ?>
            </div>

            <div class="info-box">
                <strong>Student ID</strong>
                <?= htmlspecialchars($violation['student_id']) ?>
            </div>

            <div class="info-box">
                <strong>Student Name</strong>
                <?= htmlspecialchars($violation['full_name']) ?>
            </div>

            <div class="info-box">
                <strong>Program / Section</strong>
                <?= htmlspecialchars($violation['program']) ?>
                /
                <?= htmlspecialchars($violation['section']) ?>
            </div>

            <div class="info-box">
                <strong>Violation Type</strong>
                <?= htmlspecialchars($violation['violation_type']) ?>
            </div>

            <div class="info-box">
                <strong>Violation Date / Time</strong>
                <?= htmlspecialchars($violation['violation_time']) ?>
            </div>

            <div class="info-box" style="grid-column: 1 / -1;">
                <strong>Description</strong>
                <?= htmlspecialchars($violation['description'] ?? '') ?>
            </div>

        </div>

        <form method="POST">
            <?= smartgate_csrf_field() ?>

            <label for="status">Status</label>
            <select name="status" id="status" required>
                <option value="Pending"
                    <?= $violation['status'] === 'Pending' ? 'selected' : '' ?>>
                    Pending
                </option>

                <option value="Referred"
                    <?= $violation['status'] === 'Referred' ? 'selected' : '' ?>>
                    Referred
                </option>

                <option value="Resolved"
                    <?= $violation['status'] === 'Resolved' ? 'selected' : '' ?>>
                    Resolved
                </option>
            </select>

            <label for="action_taken">Action Taken</label>
            <textarea
                name="action_taken"
                id="action_taken"
                placeholder="Enter the action taken..."
            ><?= htmlspecialchars($violation['action_taken'] ?? '') ?></textarea>

            <div class="buttons">
                <button type="submit">
                    Save Changes
                </button>

                <a href="violations.php" class="back-btn">
                    ← Back to Violations
                </a>
            </div>

        </form>

    </div>

</div>

</div>
</body>
</html>
