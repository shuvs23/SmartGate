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

$program = trim($_GET["program"] ?? "");
$year = trim($_GET["year"] ?? "");
$section = trim($_GET["section"] ?? "");
$account = trim($_GET["account"] ?? "");

$sql = "
    SELECT student_id, full_name, program, year_level, section, qr_code, photo, is_active
    FROM students
    WHERE 1=1
";
$types = "";
$params = [];

if ($program !== "") {
    $sql .= " AND program = ?";
    $types .= "s";
    $params[] = $program;
}
if ($year !== "") {
    $sql .= " AND year_level = ?";
    $types .= "s";
    $params[] = $year;
}
if ($section !== "") {
    $sql .= " AND section = ?";
    $types .= "s";
    $params[] = $section;
}
if ($account === "active") {
    $sql .= " AND is_active = 1";
} elseif ($account === "inactive") {
    $sql .= " AND is_active = 0";
}

$sql .= " ORDER BY full_name ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}

if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$programs = [];
$years = [];
$sections = [];

$meta = $conn->query("
    SELECT DISTINCT program, year_level, section
    FROM students
    ORDER BY program, year_level, section
");

if ($meta) {
    while ($m = $meta->fetch_assoc()) {
        $programs[$m["program"]] = true;
        $years[$m["year_level"]] = true;
        $sections[$m["section"]] = true;
    }
}
ksort($programs);
ksort($years);
ksort($sections);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Batch QR Printing - SmartGate</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#1e293b}
.header{background:#0b3d91;color:#fff;padding:20px 30px;display:flex;justify-content:space-between;align-items:center;gap:15px}
.header h1{margin:0;font-size:25px}.header p{margin:5px 0 0;font-size:14px;opacity:.9}
.back{background:#fff;color:#0b3d91;text-decoration:none;padding:10px 16px;border-radius:7px;font-weight:bold}
.container{max-width:1250px;margin:25px auto;padding:0 20px}
.card{background:#fff;border-radius:12px;padding:22px;margin-bottom:20px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
.filters{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
label{display:block;font-size:12px;font-weight:bold;color:#475569;margin-bottom:6px}
select{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}
.actions{display:flex;gap:10px;margin-top:15px;flex-wrap:wrap}
.btn{border:0;background:#1468a8;color:#fff;padding:11px 17px;border-radius:7px;font-weight:bold;text-decoration:none;cursor:pointer}
.btn.secondary{background:#64748b}
.summary{color:#64748b;margin-top:12px}
.qr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px}
.qr-card{border:1px solid #dbe4ef;border-radius:12px;padding:18px;text-align:center;break-inside:avoid;background:#fff}
.qr-card h3{font-size:17px;color:#0b3d91;margin:10px 0 5px}
.qr-card p{margin:4px 0;font-size:13px;color:#475569}
.qr{display:flex;justify-content:center;margin:12px auto}
.photo{width:65px;height:65px;object-fit:cover;border-radius:50%;border:2px solid #dbe4ef}
.not-assigned{color:#b45309;font-weight:bold;padding:35px 0}
.empty{text-align:center;padding:30px;color:#64748b}
@media(max-width:800px){.filters{grid-template-columns:1fr 1fr}}
@media(max-width:500px){.filters{grid-template-columns:1fr}.qr-grid{grid-template-columns:1fr}}
@media print{
    .no-print{display:none!important}
    body{background:#fff}
    .container{max-width:none;margin:0;padding:0}
    .card{box-shadow:none;border:0;padding:0}
    .qr-grid{grid-template-columns:repeat(2,1fr);gap:12px}
    .qr-card{border:1px solid #ddd;padding:12px}
}
</style>
<link rel="stylesheet" href="smartgate_theme.css">
</head>
<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<header class="header no-print">
    <div>
        <h1>Batch QR Printing</h1>
        <p>SmartGate Student QR Codes</p>
    </div>
    <a href="student_management.php" class="back">← Student Management</a>
</header>

<div class="container">
    <div class="card no-print">
        <h2 style="margin-top:0;color:#0b3d91">QR Print Filters</h2>
        <form method="GET">
            <div class="filters">
                <div>
                    <label>PROGRAM</label>
                    <select name="program">
                        <option value="">All Programs</option>
                        <?php foreach (array_keys($programs) as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $program === $p ? "selected" : "" ?>>
                                <?= htmlspecialchars($p) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>YEAR</label>
                    <select name="year">
                        <option value="">All Years</option>
                        <?php foreach (array_keys($years) as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= $year === $y ? "selected" : "" ?>>
                                <?= htmlspecialchars($y) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>SECTION</label>
                    <select name="section">
                        <option value="">All Sections</option>
                        <?php foreach (array_keys($sections) as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $section === $s ? "selected" : "" ?>>
                                <?= htmlspecialchars($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>ACCOUNT</label>
                    <select name="account">
                        <option value="">All Accounts</option>
                        <option value="active" <?= $account === "active" ? "selected" : "" ?>>Active Only</option>
                        <option value="inactive" <?= $account === "inactive" ? "selected" : "" ?>>Inactive Only</option>
                    </select>
                </div>
            </div>
            <div class="actions">
                <button class="btn" type="submit">Apply Filters</button>
                <a class="btn secondary" href="batch_qr.php">Clear</a>
                <button class="btn" type="button" onclick="window.print()">Print QR Codes</button>
            </div>
        </form>
        <div class="summary">
            <?= $result->num_rows ?> student QR code(s) displayed.
        </div>
    </div>

    <div class="card">
        <div class="qr-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($student = $result->fetch_assoc()): ?>
                <?php
                    $qrValue = trim((string)($student["qr_code"] ?? ""));
                    if ($qrValue === "") {
                        $qrValue = $student["student_id"];
                    }
                ?>
                <div class="qr-card">
                    <?php if (!empty($student["photo"])): ?>
                        <img class="photo" src="<?= htmlspecialchars($student["photo"]) ?>" alt="Student Photo">
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($student["full_name"]) ?></h3>
                    <p><strong><?= htmlspecialchars($student["student_id"]) ?></strong></p>
                    <p><?= htmlspecialchars($student["program"]) ?> • <?= htmlspecialchars($student["year_level"]) ?> - <?= htmlspecialchars($student["section"]) ?></p>
                    <div class="qr" id="qr-<?= htmlspecialchars($student["student_id"]) ?>"></div>
                    <p>QR Value: <?= htmlspecialchars($qrValue) ?></p>
                    <?php if (empty($student["qr_code"])): ?>
                        <div class="not-assigned">QR NOT ASSIGNED</div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty">No students match the selected filters.</div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
<?php
$result->data_seek(0);
?>
<?php while ($student = $result->fetch_assoc()): ?>
<?php
    $qrValue = trim((string)($student["qr_code"] ?? ""));
    if ($qrValue === "") $qrValue = $student["student_id"];
?>
new QRCode(document.getElementById("qr-<?= htmlspecialchars($student["student_id"]) ?>"), {
    text: <?= json_encode($qrValue) ?>,
    width: 190,
    height: 190,
    correctLevel: QRCode.CorrectLevel.H
});
<?php endwhile; ?>
</script>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
