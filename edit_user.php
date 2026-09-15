<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

// Only Super Admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit;
}

// Get user ID
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: user_management.php");
    exit;
}

// Get existing user
$stmt = $conn->prepare("
    SELECT id, username, full_name, role, department, is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: user_management.php");
    exit;
}

$user = $result->fetch_assoc();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    smartgate_require_csrf();

    $username   = trim($_POST['username'] ?? '');
    $full_name  = trim($_POST['full_name'] ?? '');
    $roleInput  = $_POST['role'] ?? '';
    $role       = is_string($roleInput) ? $roleInput : '';
    $department = trim($_POST['department'] ?? '');
    $newPasswordInput = $_POST['password'] ?? '';
    $new_password = is_string($newPasswordInput) ? $newPasswordInput : '';

    // Validation
    if (
        $username === "" ||
        $full_name === "" ||
        $role === "" ||
        !smartgate_allowed_role($role) ||
        ($new_password !== "" && strlen($new_password) < 8)
    ) {

        $error = "Please fill in all required fields.";

    } else {

        // Check if another user already uses the username
        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
              AND id != ?
            LIMIT 1
        ");

        $check->bind_param("si", $username, $id);
        $check->execute();

        $existing = $check->get_result();

        if ($existing->num_rows > 0) {

            $error = "Username already exists.";

        } else {

            /*
             * If password field is empty:
             * keep the existing password.
             *
             * If password was entered:
             * update the password with a secure hash.
             */

            if ($new_password !== "") {

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $update = $conn->prepare("
                    UPDATE users
                    SET username = ?,
                        password = ?,
                        full_name = ?,
                        role = ?,
                        department = ?
                    WHERE id = ?
                ");

                $update->bind_param(
                    "sssssi",
                    $username,
                    $hashed_password,
                    $full_name,
                    $role,
                    $department,
                    $id
                );

            } else {

                $update = $conn->prepare("
                    UPDATE users
                    SET username = ?,
                        full_name = ?,
                        role = ?,
                        department = ?
                    WHERE id = ?
                ");

                $update->bind_param(
                    "ssssi",
                    $username,
                    $full_name,
                    $role,
                    $department,
                    $id
                );
            }

            if ($update->execute()) {

                // Audit trail
                $action = "EDIT USER";
                $description = "Edited user: " . $username;
                $target_type = "USER";
                $target_id = (string)$id;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

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
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $audit->bind_param(
                    "isssss",
                    $_SESSION['user_id'],
                    $action,
                    $description,
                    $target_type,
                    $target_id,
                    $ip_address
                );

                $audit->execute();

                $success = "User updated successfully.";

                // Reload updated user
                $stmt = $conn->prepare("
                    SELECT id, username, full_name, role, department, is_active
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

            } else {

                $error = "Failed to update user.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit User - SmartGate</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .header {
            background: #0b3d91;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 25px;
        }

        .back-btn {
            background: white;
            color: #0b3d91;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: bold;
        }

        .container {
            width: 95%;
            max-width: 700px;
            margin: 40px auto;
        }

        .form-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .form-card h2 {
            margin-top: 0;
            color: #0b3d91;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .required {
            color: #dc3545;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #0b3d91;
        }

        .help-text {
            margin-top: 5px;
            font-size: 12px;
            color: #777;
        }

        .error {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .status-box {
            padding: 10px;
            background: #f1f5f9;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .active {
            color: #198754;
            font-weight: bold;
        }

        .inactive {
            color: #dc3545;
            font-weight: bold;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .save-btn,
        .cancel-btn {
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .save-btn {
            background: #198754;
            color: white;
            flex: 1;
        }

        .cancel-btn {
            background: #6c757d;
            color: white;
            flex: 1;
        }

    </style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<div class="header">

    <h1>Edit System User</h1>

    <a href="user_management.php" class="back-btn">
        ← Back to User Management
    </a>

</div>

<div class="container">

    <div class="form-card">

        <h2>Edit User Information</h2>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>

        <div class="status-box">

            Current Status:

            <?php if ($user['is_active']): ?>

                <span class="active">ACTIVE</span>

            <?php else: ?>

                <span class="inactive">INACTIVE</span>

            <?php endif; ?>

        </div>

        <form method="POST">

            <?= smartgate_csrf_field() ?>

            <div class="form-group">

                <label>
                    Username <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars($user['username']) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Full Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="full_name"
                    value="<?= htmlspecialchars($user['full_name']) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Role <span class="required">*</span>
                </label>

                <select name="role" required>

                    <option value="">-- Select Role --</option>

                    <option value="MIS"
                        <?= $user['role'] === 'MIS' ? 'selected' : '' ?>>
                        MIS
                    </option>

                    <option value="Security"
                        <?= $user['role'] === 'Security' ? 'selected' : '' ?>>
                        Security
                    </option>

                    <option value="CCDU"
                        <?= $user['role'] === 'CCDU' ? 'selected' : '' ?>>
                        CCDU
                    </option>

                    <option value="Guidance"
                        <?= $user['role'] === 'Guidance' ? 'selected' : '' ?>>
                        Guidance
                    </option>

                    <option value="Library"
                        <?= $user['role'] === 'Library' ? 'selected' : '' ?>>
                        Library
                    </option>

                    <option value="IGP"
                        <?= $user['role'] === 'IGP' ? 'selected' : '' ?>>
                        IGP
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Department
                </label>

                <input
                    type="text"
                    name="department"
                    value="<?= htmlspecialchars($user['department'] ?? '') ?>"
                    placeholder="Example: MIS"
                >

            </div>

            <div class="form-group">

                <label>
                    New Password
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Leave blank to keep current password"
                >

                <div class="help-text">
                    Only enter a password if you want to change it.
                </div>

            </div>

            <div class="buttons">

                <button type="submit" class="save-btn">
                    Save Changes
                </button>

                <a href="user_management.php" class="cancel-btn">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</div>
</body>

</html>
