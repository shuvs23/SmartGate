<?php

require_once __DIR__ . "/security.php";
smartgate_start_session();

require_once "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: admin.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    smartgate_require_csrf();

    $username = trim($_POST["username"] ?? "");
    $passwordInput = $_POST["password"] ?? "";
    $password = is_string($passwordInput) ? $passwordInput : "";

    if ($username === "" || $password === "") {
        $error = "Please enter your username and password.";
    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                username,
                password,
                full_name,
                role,
                department,
                is_active
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if ((int)$user["is_active"] !== 1) {

                $error = "This account is inactive.";

            } elseif (password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["department"] = $user["department"];

                // Audit login
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
                    VALUES
                    (?, ?, ?, ?, ?, ?)
                ");

                $action = "LOGIN";
                $description = "User logged into SmartGate.";
                $targetType = "USER";
                $targetId = (string)$user["id"];
                $ip = $_SERVER["REMOTE_ADDR"] ?? null;

                $audit->bind_param(
                    "isssss",
                    $user["id"],
                    $action,
                    $description,
                    $targetType,
                    $targetId,
                    $ip
                );

                $audit->execute();
                $audit->close();

                header("Location: admin.php");
                exit;

            } else {

                $error = "Invalid username or password.";

            }

        } else {

            $error = "Invalid username or password.";

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SmartGate Login</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: Arial, Helvetica, sans-serif;
    background:
        linear-gradient(
            135deg,
            #0f3d91,
            #2563eb,
            #0f172a
        );
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-wrapper {
    width: 100%;
    max-width: 430px;
    padding: 20px;
}

.login-card {
    background: #ffffff;
    border-radius: 22px;
    padding: 35px;
    box-shadow: 0 25px 70px rgba(0,0,0,.25);
}

.logo {
    width: 75px;
    height: 75px;
    margin: 0 auto 18px;
    border-radius: 20px;
    background: #2563eb;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    font-weight: 800;
}

h1 {
    margin: 0;
    text-align: center;
    color: #0f172a;
    font-size: 27px;
}

.subtitle {
    text-align: center;
    color: #64748b;
    margin: 8px 0 30px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-weight: 600;
}

input {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #cbd5e1;
    border-radius: 11px;
    font-size: 15px;
    outline: none;
}

input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

button {
    width: 100%;
    padding: 14px;
    border: 0;
    border-radius: 11px;
    background: #2563eb;
    color: white;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.error {
    margin-bottom: 20px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 14px;
}

.footer {
    margin-top: 25px;
    text-align: center;
    font-size: 12px;
    color: #94a3b8;
}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <div class="logo">
            <img src="assets/smartgate-logo.png" alt="SmartGate logo">
        </div>

        <h1>SmartGate</h1>

        <div class="subtitle">
            Student Entry Management System
        </div>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <?= smartgate_csrf_field() ?>

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button type="submit">
                Sign In
            </button>

        </form>

        <div class="footer">
            SmartGate • Mabalacat City College
        </div>

    </div>

</div>

</body>
</html>
