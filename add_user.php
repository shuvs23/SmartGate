<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

// Only Super Admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    smartgate_require_csrf();

    $username   = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';
    $password   = is_string($passwordInput) ? $passwordInput : '';
    $full_name  = trim($_POST['full_name'] ?? '');
    $roleInput  = $_POST['role'] ?? '';
    $role       = is_string($roleInput) ? $roleInput : '';
    $department = trim($_POST['department'] ?? '');

    // Basic validation
    if (
        $username === "" ||
        $password === "" ||
        strlen($password) < 8 ||
        $full_name === "" ||
        $role === "" ||
        !smartgate_allowed_role($role)
    ) {

        $error = "Please fill in all required fields.";

    } else {

        // Check if username already exists
        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $check->bind_param("s", $username);
        $check->execute();

        $existing = $check->get_result();

        if ($existing->num_rows > 0) {

            $error = "Username already exists.";

        } else {

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $conn->prepare("
                INSERT INTO users
                (username, password, full_name, role, department, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");

            $stmt->bind_param(
                "sssss",
                $username,
                $hashed_password,
                $full_name,
                $role,
                $department
            );

            if ($stmt->execute()) {

                $new_user_id = $stmt->insert_id;

                // Audit trail
                $action = "ADD USER";
                $description = "Added user: " . $username;

                $audit = $conn->prepare("
                    INSERT INTO system_audit_logs
                    (user_id, action, description, target_type, target_id, ip_address)
                    VALUES (?, ?, ?, 'USER', ?, ?)
                ");

                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                $target_id = (string)$new_user_id;

                $audit->bind_param(
                    "issss",
                    $_SESSION['user_id'],
                    $action,
                    $description,
                    $target_id,
                    $ip_address
                );

                $audit->execute();

                $success = "User added successfully.";

                // Clear form values
                $username = "";
                $full_name = "";
                $role = "";
                $department = "";
            } else {

                $error = "Failed to add user.";
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

    <title>Add User - SmartGate</title>

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

    <h1>Add System User</h1>

    <a href="user_management.php" class="back-btn">
        ← Back to User Management
    </a>

</div>

<div class="container">

    <div class="form-card">

        <h2>Create New User</h2>

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

        <form method="POST">

            <?= smartgate_csrf_field() ?>

            <div class="form-group">

                <label>
                    Username <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars($username ?? '') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Password <span class="required">*</span>
                </label>

                <input
                    type="password"
                    name="password"
                    required
                >

                <div class="help-text">
                    The password will be securely hashed before being stored.
                </div>

            </div>

            <div class="form-group">

                <label>
                    Full Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="full_name"
                    value="<?= htmlspecialchars($full_name ?? '') ?>"
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
                        <?= (($role ?? '') === 'MIS') ? 'selected' : '' ?>>
                        MIS
                    </option>

                    <option value="Security"
                        <?= (($role ?? '') === 'Security') ? 'selected' : '' ?>>
                        Security
                    </option>

                    <option value="CCDU"
                        <?= (($role ?? '') === 'CCDU') ? 'selected' : '' ?>>
                        CCDU
                    </option>

                    <option value="Guidance"
                        <?= (($role ?? '') === 'Guidance') ? 'selected' : '' ?>>
                        Guidance
                    </option>

                    <option value="Library"
                        <?= (($role ?? '') === 'Library') ? 'selected' : '' ?>>
                        Library
                    </option>

                    <option value="IGP"
                        <?= (($role ?? '') === 'IGP') ? 'selected' : '' ?>>
                        IGP
                    </option>

                </select>

                <div class="help-text">
                    Super Admin accounts are managed separately.
                </div>

            </div>

            <div class="form-group">

                <label>
                    Department
                </label>

                <input
                    type="text"
                    name="department"
                    value="<?= htmlspecialchars($department ?? '') ?>"
                    placeholder="Example: MIS"
                >

            </div>

            <div class="buttons">

                <button type="submit" class="save-btn">
                    Add User
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
