<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["super_admin","CCDU","Security"], true)) {
    die("Access Denied.");
}

$violation_types = [
    "No Student ID / Forgot Student ID",
    "Damaged Student ID",
    "Lost Student ID",
    "Expired Student ID",
    "Unauthorized Use of Student ID",
    "Tampered Student ID",
    "Invalid / Unrecognized QR Code",
    "Attempted Unauthorized Entry",
    "Attempted Unauthorized Exit",
    "Other / Manual Violation"
];

$offense_messages = [
    1 => "You have been issued a First Offense Notice. Please be reminded to observe and comply with the school's rules and regulations to avoid further violations.",
    2 => "You have been issued a Second Offense Notice. You are strongly advised to take this matter seriously and ensure compliance with the school's rules and regulations to prevent further disciplinary action.",
    3 => "You have been issued a Third Offense Notice. You are required to proceed to the Guidance Office for appropriate guidance and further action regarding the violation."
];

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    smartgate_require_csrf();

    $student_id = trim($_POST["student_id"] ?? "");
    $violation_type = trim($_POST["violation_type"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $action_taken = trim($_POST["action_taken"] ?? "");

    if ($student_id === "" || $violation_type === "") {
        $error = "Student number and violation type are required.";
    } elseif (!in_array($violation_type, $violation_types, true)) {
        $error = "Invalid violation type.";
    } else {
        $stmt = $conn->prepare("SELECT student_id, full_name FROM students WHERE student_id=? AND is_active=1 LIMIT 1");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            $error = "Active student was not found.";
        } else {

            /*
             * Count the student's existing violations.
             * The maximum allowed offense is 3.
             */
            $countStmt = $conn->prepare("
                SELECT COUNT(*) AS violation_count
                FROM student_violations
                WHERE student_id = ?
            ");

            if (!$countStmt) {
                $error = "Failed to check the student's violation history.";
            } else {
                $countStmt->bind_param("s", $student_id);
                $countStmt->execute();
                $countResult = $countStmt->get_result()->fetch_assoc();
                $countStmt->close();

                $previousViolations = (int)($countResult["violation_count"] ?? 0);

                /*
                 * Block any violation beyond the 3rd offense.
                 */
                if ($previousViolations >= 3) {
                    $error = "This student has already reached the maximum limit of 3 offenses. No additional violation can be recorded.";
                } else {

                    $offenseNumber = $previousViolations + 1;
                    $offenseNotice = $offense_messages[$offenseNumber];

                    /*
                     * Automatically record the appropriate offense notice.
                     * If the user entered an action, keep it and append the
                     * official offense notice.
                     */
                    if ($action_taken !== "") {
                        $action_taken =
                            $action_taken .
                            "\n\n" .
                            $offenseNotice;
                    } else {
                        $action_taken = $offenseNotice;
                    }

                    /*
                     * Third offense automatically indicates referral to
                     * the Guidance Office.
                     */
                    if ($offenseNumber === 3) {
                        $guidanceNotice =
                            "Guidance Office Referral: Student is required to proceed to the Guidance Office for appropriate guidance and further action regarding the violation.";

                        $action_taken .=
                            "\n\n" .
                            $guidanceNotice;
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO student_violations
                        (student_id, violation_type, description, action_taken, recorded_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {
                        $error = "Failed to prepare the violation record.";
                    } else {
                        $stmt->bind_param(
                            "ssssi",
                            $student_id,
                            $violation_type,
                            $description,
                            $action_taken,
                            $user_id
                        );

                        if ($stmt->execute()) {
                            $violation_id = $stmt->insert_id;
                            $stmt->close();

                            $audit = $conn->prepare("
                                INSERT INTO system_audit_logs
                                (user_id, action, description, target_type, target_id, ip_address)
                                VALUES (?, 'CREATE VIOLATION', ?, 'VIOLATION', ?, ?)
                            ");

                            if ($audit) {
                                $audit_description =
                                    "Recorded {$violation_type} for student {$student_id} as Offense {$offenseNumber} of 3.";

                                if ($offenseNumber === 3) {
                                    $audit_description .=
                                        " Student referred to Guidance Office.";
                                }

                                $target_id = (string)$violation_id;
                                $ip = $_SERVER["REMOTE_ADDR"] ?? "";

                                $audit->bind_param(
                                    "isss",
                                    $user_id,
                                    $audit_description,
                                    $target_id,
                                    $ip
                                );

                                $audit->execute();
                                $audit->close();
                            }

                            header("Location: violations.php");
                            exit;
                        }

                        $error = "Failed to record the violation.";
                        $stmt->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Violation - SmartGate</title>
<style>
body{margin:0;background:#f4f6f9;font-family:Arial,sans-serif}.header{background:#0b3d91;color:#fff;padding:20px 30px}
.container{max-width:850px;margin:30px auto;padding:0 15px}.card{background:#fff;padding:25px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
label{display:block;font-weight:bold;margin:15px 0 7px}input,select,textarea{width:100%;padding:11px;border:1px solid #ccc;border-radius:6px;font:inherit}textarea{min-height:100px;resize:vertical}
button,.back{display:inline-block;margin-top:20px;padding:11px 18px;border:0;border-radius:6px;background:#0b3d91;color:#fff;text-decoration:none;font-weight:bold;cursor:pointer}.back{background:#777;margin-left:7px}
.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:6px;font-weight:bold}
.hint{font-size:13px;color:#64748b;margin-top:5px}
.offense-info{margin-top:18px;padding:14px;border-radius:8px;background:#eff6ff;color:#1e3a8a;font-size:13px;line-height:1.5}
</style>
<link rel="stylesheet" href="smartgate_theme.css">
</head>
<body>
<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<div class="header"><h1>Add Student Violation</h1><div>SmartGate Management System</div></div>
<div class="container">
<div class="card">
<?php if($error!==""): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="offense-info">
<strong>Violation Limit: 3 Offenses</strong><br>
1st Offense → First Offense Notice<br>
2nd Offense → Second Offense Notice<br>
3rd Offense → Third Offense + Guidance Office Referral<br>
4th Offense → Not Allowed
</div>

<form method="POST">
<?=smartgate_csrf_field()?>
<label>Student Number</label>
<input type="text" name="student_id" placeholder="e.g. 3031-0001" required>

<label>Violation Type</label>
<select name="violation_type" required>
<option value="">-- Select Violation --</option>
<?php foreach($violation_types as $type): ?>
<option value="<?=htmlspecialchars($type)?>" <?=($_POST["violation_type"]??"")===$type?"selected":""?>><?=htmlspecialchars($type)?></option>
<?php endforeach; ?>
</select>

<label>Description</label>
<textarea name="description" placeholder="Explain what happened..."><?=htmlspecialchars($_POST["description"]??"")?></textarea>

<label>Action Taken</label>
<textarea name="action_taken" placeholder="Example: Referred to Guidance Council"><?=htmlspecialchars($_POST["action_taken"]??"")?></textarea>

<button type="submit">Save Violation</button>
<a class="back" href="violations.php">← Back</a>
</form>
</div>
</div>
</div>
</body>
</html>
