<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$allowedRoles = ["super_admin", "MIS"];
if (!in_array($_SESSION["role"] ?? "", $allowedRoles, true)) {
    die("Access denied.");
}

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    die("Invalid student ID.");
}

$stmt = $conn->prepare("SELECT id, student_id, full_name, program, year_level, section, qr_code, photo, is_active FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Student not found.");
}

$student = $result->fetch_assoc();
$stmt->close();
$conn->close();

$qrValue = trim((string)($student["qr_code"] ?? ""));
if ($qrValue === "") {
    $qrValue = $student["student_id"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student QR - <?= htmlspecialchars($student["student_id"]) ?></title>

<style>
* {
    box-sizing: border-box;
}

:root {
    --blue: #0d47a1;
    --blue-2: #1769c2;
    --blue-light: #eaf3ff;
    --text: #1e293b;
    --muted: #64748b;
    --border: #d9e5f2;
    --green: #15803d;
    --green-bg: #dcfce7;
    --red: #b91c1c;
    --red-bg: #fee2e2;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: Arial, Helvetica, sans-serif;
    color: var(--text);
    background:
        linear-gradient(135deg, #f3f8fd 0%, #edf4fa 100%);
}

/* MAIN PAGE */
.page {
    width: min(100%, 900px);
    margin: 0 auto;
    padding: 28px 18px;
}

/* MAIN CARD */
.card {
    background: #fff;
    border: 1px solid #dce7f2;
    border-radius: 20px;
    padding: 30px 34px 32px;
    box-shadow: 0 12px 32px rgba(13, 71, 161, .09);
}

/* COMPACT PAGE */
.header {
    display: none;
}

/* PAGE HEADING */
.page-heading {
    text-align: center;
    margin: 0 0 18px;
}

.page-heading-title {
    color: var(--blue);
    font-size: 22px;
    font-weight: 850;
    letter-spacing: -.2px;
}

.page-heading-subtitle {
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
}

/* STUDENT INFORMATION */
.student {
    display: grid;
    grid-template-columns: 115px minmax(0, 1fr);
    align-items: center;
    gap: 20px;
    width: min(100%, 650px);
    margin: 0 auto 25px;
    padding: 17px;
    background: #f8fbff;
    border: 1px solid #d5e5f5;
    border-radius: 15px;
    text-align: left;
}

.photo-wrap {
    width: 115px;
    height: 140px;
    border-radius: 12px;
    overflow: hidden;
    background: #e8eef5;
    border: 1px solid #d6e2ee;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.photo-placeholder {
    color: #8a9aae;
    font-size: 11px;
    font-weight: 800;
    text-align: center;
}

.info {
    min-width: 0;
}

.info h2 {
    margin: 0 0 11px;
    color: var(--blue);
    font-size: 25px;
    line-height: 1.15;
    font-weight: 800;
}

.info-list {
    display: grid;
    gap: 5px;
}

.info-row {
    display: flex;
    gap: 7px;
    align-items: baseline;
    font-size: 14px;
    line-height: 1.35;
}

.info-label {
    color: #52657d;
    font-weight: 800;
}

.info-value {
    color: #233c5b;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .4px;
}

.status::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.active {
    background: var(--green-bg);
    color: var(--green);
}

.inactive {
    background: var(--red-bg);
    color: var(--red);
}

/* QR SECTION */
.qr-section {
    text-align: center;
    border-top: 1px solid #e3ebf4;
    padding-top: 18px;
}

.qr-label {
    color: #52657d;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.qr-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px;
    background: #fff;
    border: 1px solid #d7e4f1;
    border-radius: 13px;
    box-shadow: 0 5px 16px rgba(15, 45, 80, .06);
}

#qrcode {
    width: 205px;
    height: 205px;
}

#qrcode img,
#qrcode canvas {
    display: block;
    width: 205px !important;
    height: 205px !important;
}

.qr-value-label {
    margin-top: 11px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .7px;
    text-transform: uppercase;
}

.value {
    margin-top: 3px;
    color: var(--blue);
    font-size: 18px;
    font-weight: 850;
}

/* BUTTONS */
.buttons {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 18px;
}

.btn {
    min-width: 125px;
    border: 0;
    border-radius: 8px;
    padding: 10px 16px;
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    text-align: center;
}

.btn-primary {
    color: #fff;
    background: #1468a8;
    box-shadow: 0 4px 10px rgba(13, 71, 161, .14);
}

.btn-secondary {
    color: #334155;
    background: #edf3f9;
    border: 1px solid #d6e1ec;
}

/* TABLET */
@media (max-width: 700px) {
    .page {
        padding: 18px 12px;
    }

    .card {
        padding: 24px 18px 25px;
        border-radius: 17px;
    }

    .page-heading-title {
        font-size: 20px;
    }

    .student {
        grid-template-columns: 100px minmax(0, 1fr);
        gap: 15px;
        padding: 14px;
    }

    .photo-wrap {
        width: 100px;
        height: 122px;
    }

    .info h2 {
        font-size: 21px;
    }

    .info-row {
        font-size: 13px;
    }

    #qrcode,
    #qrcode img,
    #qrcode canvas {
        width: 180px !important;
        height: 180px !important;
    }
}

/* PHONE */
@media (max-width: 520px) {
    .page {
        padding: 10px 8px;
    }

    .card {
        padding: 22px 13px;
    }

    h1 {
        font-size: 24px;
    }

    .student {
        display: flex;
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }

    .photo-wrap {
        width: 95px;
        height: 115px;
    }

    .info {
        width: 100%;
    }

    .info h2 {
        font-size: 20px;
    }

    .info-list {
        justify-items: center;
    }

    .info-row {
        justify-content: center;
        flex-wrap: wrap;
    }

    #qrcode,
    #qrcode img,
    #qrcode canvas {
        width: 190px !important;
        height: 190px !important;
    }

    .buttons {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}

/* PRINT */
@media print {
    @page {
        margin: 10mm;
    }

    body {
        background: #fff;
    }

    .page {
        width: 100%;
        max-width: none;
        padding: 0;
    }

    .card {
        border: 0;
        box-shadow: none;
        padding: 5px;
    }

    .buttons {
        display: none;
    }

    .student {
        box-shadow: none;
    }

    .qr-box {
        box-shadow: none;
    }
}
</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<div class="page">
    <main class="card">



        <div class="page-heading">
            <div class="page-heading-title">Student QR Code</div>
            <div class="page-heading-subtitle">Digital identification and gate access</div>
        </div>

        <section class="student">

            <div class="photo-wrap">
                <?php if (!empty($student["photo"])): ?>
                    <img
                        class="photo"
                        src="<?= htmlspecialchars($student["photo"]) ?>"
                        alt="Student Photo"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                    >
                    <div class="photo-placeholder" style="display:none;">
                        NO PHOTO
                    </div>
                <?php else: ?>
                    <div class="photo-placeholder">
                        NO PHOTO
                    </div>
                <?php endif; ?>
            </div>

            <div class="info">
                <h2><?= htmlspecialchars($student["full_name"]) ?></h2>

                <div class="info-list">
                    <div class="info-row">
                        <span class="info-label">Student ID:</span>
                        <span class="info-value"><?= htmlspecialchars($student["student_id"]) ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Program:</span>
                        <span class="info-value"><?= htmlspecialchars($student["program"]) ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Year &amp; Section:</span>
                        <span class="info-value">
                            <?= htmlspecialchars($student["year_level"]) ?>
                            -
                            <?= htmlspecialchars($student["section"]) ?>
                        </span>
                    </div>
                </div>

                <span class="status <?= (int)$student["is_active"] === 1 ? "active" : "inactive" ?>">
                    <?= (int)$student["is_active"] === 1 ? "ACTIVE" : "INACTIVE" ?>
                </span>
            </div>

        </section>

        <section class="qr-section">
            <div class="qr-label">Student QR Code</div>

            <div class="qr-box">
                <div id="qrcode"></div>
            </div>

            <div class="qr-value-label">QR Value</div>
            <div class="value"><?= htmlspecialchars($qrValue) ?></div>

            <div class="buttons">
                <button class="btn btn-primary" onclick="window.print()">
                    Print QR
                </button>

                <a class="btn btn-secondary" href="student_management.php">
                    Back to Students
                </a>
            </div>
        </section>

    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById("qrcode"), {
    text: <?= json_encode($qrValue) ?>,
    width: 300,
    height: 300,
    correctLevel: QRCode.CorrectLevel.H
});
</script>

</body>
</html>
