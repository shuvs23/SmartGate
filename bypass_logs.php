<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$year = trim($_GET['year'] ?? '');
$semester = trim($_GET['semester'] ?? '');
$month = trim($_GET['month'] ?? '');
$week = trim($_GET['week'] ?? '');
$day = trim($_GET['day'] ?? '');
$direction = trim($_GET['direction'] ?? '');

$sql = "SELECT b.id, b.student_id, b.visitor_name,
               s.full_name, s.program, s.year_level, s.section,
               b.direction, b.reason, b.bypass_time,
               u.full_name AS processed_by
        FROM bypass_logs b
        LEFT JOIN students s ON b.student_id = s.student_id
        INNER JOIN users u ON b.user_id = u.id
        WHERE 1=1";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (b.student_id LIKE ? OR b.visitor_name LIKE ? OR s.full_name LIKE ? OR s.program LIKE ? OR s.section LIKE ? OR b.reason LIKE ? OR u.full_name LIKE ?)";
    $value = "%{$search}%";
    for ($i = 0; $i < 7; $i++) { $params[] = $value; }
    $types .= 'sssssss';
}
if ($year !== '') {
    $sql .= " AND YEAR(b.bypass_time) = ?";
    $params[] = (int)$year;
    $types .= 'i';
}
if ($semester === '1st') $sql .= " AND MONTH(b.bypass_time) BETWEEN 8 AND 12";
elseif ($semester === '2nd') $sql .= " AND MONTH(b.bypass_time) BETWEEN 1 AND 5";
elseif ($semester === 'Summer') $sql .= " AND MONTH(b.bypass_time) BETWEEN 6 AND 7";
if ($month !== '') {
    $sql .= " AND MONTH(b.bypass_time) = ?";
    $params[] = (int)$month;
    $types .= 'i';
}
if ($week !== '') {
    $sql .= " AND WEEK(b.bypass_time, 1) = ?";
    $params[] = (int)$week;
    $types .= 'i';
}
if ($day !== '') {
    $sql .= " AND DATE(b.bypass_time) = ?";
    $params[] = $day;
    $types .= 's';
}
if ($direction === 'IN' || $direction === 'OUT') {
    $sql .= " AND b.direction = ?";
    $params[] = $direction;
    $types .= 's';
}
$sql .= " ORDER BY b.bypass_time DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Query preparation failed: " . $conn->error);
if ($params) {
    $bind = [$types];
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $bind);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = [];
$totalIn = 0; $totalOut = 0; $studentCount = 0; $visitorCount = 0;
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
    if ($row['direction'] === 'IN') $totalIn++; else $totalOut++;
    if (!empty($row['student_id'])) $studentCount++; else $visitorCount++;
}
$stmt->close();

$today = ['total'=>0,'in'=>0,'out'=>0];
$r = $conn->query("SELECT COUNT(*) total, SUM(direction='IN') today_in, SUM(direction='OUT') today_out FROM bypass_logs WHERE DATE(bypass_time)=CURDATE()");
if ($r && ($x = $r->fetch_assoc())) {
    $today['total']=(int)$x['total']; $today['in']=(int)$x['today_in']; $today['out']=(int)$x['today_out'];
}

$years = [];
$r = $conn->query("SELECT DISTINCT YEAR(bypass_time) y FROM bypass_logs ORDER BY y DESC");
if ($r) while ($x=$r->fetch_assoc()) $years[]=$x['y'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bypass Logs - SmartGate</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#1f2937}.header{background:#0b3d91;color:#fff;padding:22px 30px;display:flex;justify-content:space-between;align-items:center;gap:20px}.header h1{margin:0;font-size:25px}.header p{margin:6px 0 0;font-size:14px;opacity:.9}.back{background:#fff;color:#0b3d91;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:700}.container{width:95%;max-width:1550px;margin:28px auto}.intro{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:15px;flex-wrap:wrap}.intro h2{margin:0;color:#0b3d91}.intro p{margin:5px 0 0;color:#64748b}.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:15px;margin-bottom:20px}.card{background:#fff;border-radius:12px;padding:19px;box-shadow:0 4px 14px rgba(0,0,0,.07)}.stat-label{font-size:12px;text-transform:uppercase;color:#64748b;font-weight:700}.stat-number{font-size:29px;font-weight:800;margin-top:7px;color:#0b3d91}.in .stat-number{color:#198754}.out .stat-number{color:#dc3545}.visitor .stat-number{color:#7c3aed}.filters h3{margin:0 0 15px;color:#0b3d91}.filter-grid{display:grid;grid-template-columns:2fr repeat(6,1fr);gap:12px}.field label{display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px}.field input,.field select{width:100%;padding:10px;border:1px solid #d7dee8;border-radius:8px;font-size:14px;background:#fff}.actions{display:flex;gap:9px;margin-top:14px;flex-wrap:wrap}.btn{border:0;border-radius:8px;padding:10px 15px;font-weight:700;cursor:pointer;text-decoration:none;font-size:13px}.primary{background:#0b3d91;color:#fff}.secondary{background:#64748b;color:#fff}.green{background:#198754;color:#fff}.table-card{margin-top:20px;padding:0;overflow:hidden}.table-head{padding:17px 20px;display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;border-bottom:1px solid #e5e7eb}.table-head strong{color:#0f172a}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1200px}th{background:#0b3d91;color:#fff;padding:13px 12px;text-align:left;font-size:12px;white-space:nowrap}td{padding:12px;border-bottom:1px solid #e8edf3;font-size:13px;vertical-align:middle;font-weight:400}tr:hover{background:#f8fafc}.type{display:inline-block;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:700}.type.student{background:#e0edff;color:#174ea6}.type.visitor{background:#ede9fe;color:#6d28d9}.direction{display:inline-block;min-width:52px;text-align:center;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}.direction.in{background:#d1fae5;color:#166534}.direction.out{background:#fee2e2;color:#991b1b}.id{font-weight:600;color:#334155}.reason{max-width:280px}.empty{text-align:center;padding:45px;color:#64748b}.count{color:#64748b;font-size:13px}@media(max-width:1100px){.cards{grid-template-columns:repeat(2,1fr)}.filter-grid{grid-template-columns:1fr 1fr}}@media(max-width:650px){.cards{grid-template-columns:1fr}.filter-grid{grid-template-columns:1fr}.header{flex-direction:column;align-items:flex-start}}@media print{.header,.filters,.intro,.cards,.table-head .actions{display:none!important}.container{width:100%;max-width:none;margin:0}.table-card{box-shadow:none}.table-head{padding:8px 0}th,td{padding:7px;font-size:10px}}
</style>
<link rel="stylesheet" href="smartgate_theme.css">
</head>
<body>
<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<header class="header"><div><h1>Bypass Logs</h1><p>Authorized bypass transaction history • <?=htmlspecialchars($_SESSION['full_name'] ?? '')?> • <?=htmlspecialchars($_SESSION['role'] ?? '')?></p></div></header>
<div class="container">
<div class="intro"><div><h2>Bypass Transaction Records</h2><p>Review student and visitor bypass activity.</p></div></div>
<div class="cards">
<div class="card"><div class="stat-label">Today's Bypass</div><div class="stat-number"><?=$today['total']?></div></div>
<div class="card in"><div class="stat-label">Today's IN</div><div class="stat-number"><?=$today['in']?></div></div>
<div class="card out"><div class="stat-label">Today's OUT</div><div class="stat-number"><?=$today['out']?></div></div>
<div class="card"><div class="stat-label">Filtered Students</div><div class="stat-number"><?=$studentCount?></div></div>
<div class="card visitor"><div class="stat-label">Filtered Visitors</div><div class="stat-number"><?=$visitorCount?></div></div>
</div>
<div class="card filters"><h3>Filter Bypass Logs</h3><form method="GET"><div class="filter-grid">
<div class="field"><label>Search</label><input name="search" value="<?=htmlspecialchars($search)?>" placeholder="Student ID, name, visitor, reason..."></div>
<div class="field"><label>Year</label><select name="year"><option value="">All Years</option><?php foreach($years as $y):?><option value="<?=htmlspecialchars($y)?>" <?=$year==$y?'selected':''?>><?=htmlspecialchars($y)?></option><?php endforeach;?></select></div>
<div class="field"><label>Semester</label><select name="semester"><option value="">All Semesters</option><option value="1st" <?=$semester==='1st'?'selected':''?>>1st</option><option value="2nd" <?=$semester==='2nd'?'selected':''?>>2nd</option><option value="Summer" <?=$semester==='Summer'?'selected':''?>>Summer</option></select></div>
<div class="field"><label>Month</label><select name="month"><option value="">All Months</option><?php for($m=1;$m<=12;$m++):?><option value="<?=$m?>" <?=$month==$m?'selected':''?>><?=date('F',mktime(0,0,0,$m,1))?></option><?php endfor;?></select></div>
<div class="field"><label>Week</label><select name="week"><option value="">All Weeks</option><?php for($w=1;$w<=53;$w++):?><option value="<?=$w?>" <?=$week==$w?'selected':''?>>Week <?=$w?></option><?php endfor;?></select></div>
<div class="field"><label>Day</label><input type="date" name="day" value="<?=htmlspecialchars($day)?>"></div>
<div class="field"><label>Direction</label><select name="direction"><option value="">All Directions</option><option value="IN" <?=$direction==='IN'?'selected':''?>>IN</option><option value="OUT" <?=$direction==='OUT'?'selected':''?>>OUT</option></select></div>
</div><div class="actions"><button class="btn primary">Apply Filters</button><a class="btn secondary" href="bypass_logs.php">Clear Filters</a></div></form></div>
<div class="card table-card"><div class="table-head"><div><strong>Bypass Records</strong> <span class="count">• <?=$studentCount+$visitorCount?> record(s)</span></div><div class="actions" style="margin:0"><button class="btn primary" onclick="window.print()">🖨 Print</button><button class="btn green" onclick="exportCSV()">⬇ Export CSV</button></div></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Type</th><th>Student / Visitor ID</th><th>Name</th><th>Program</th><th>Year</th><th>Section</th><th>Direction</th><th>Reason</th><th>Date & Time</th><th>Processed By</th></tr></thead><tbody>
<?php if($logs): foreach($logs as $log): $isStudent=!empty($log['student_id']); ?><tr>
<td><?=htmlspecialchars($log['id'])?></td><td><span class="type <?=$isStudent?'student':'visitor'?>"><?=$isStudent?'STUDENT':'VISITOR'?></span></td><td class="id"><?=htmlspecialchars($log['student_id'] ?: '—')?></td><td><?=htmlspecialchars($isStudent ? ($log['full_name'] ?: 'Unknown Student') : ($log['visitor_name'] ?: 'Unknown Visitor'))?></td><td><?=htmlspecialchars($log['program'] ?: '—')?></td><td><?=htmlspecialchars($log['year_level'] ?: '—')?></td><td><?=htmlspecialchars($log['section'] ?: '—')?></td><td><span class="direction <?=strtolower($log['direction'])?>"><?=htmlspecialchars($log['direction'])?></span></td><td class="reason"><?=htmlspecialchars($log['reason'])?></td><td><?=htmlspecialchars($log['bypass_time'])?></td><td><?=htmlspecialchars($log['processed_by'])?></td>
</tr><?php endforeach; else:?><tr><td colspan="11" class="empty">No bypass records found for the selected filters.</td></tr><?php endif;?></tbody></table></div></div>
</div>
<script>
function exportCSV(){const rows=document.querySelectorAll('table tr');const out=[];rows.forEach(r=>{const cells=r.querySelectorAll('th,td');const vals=[];cells.forEach(c=>{let v=c.innerText.replace(/\s+/g,' ').trim();vals.push('"'+v.replace(/"/g,'""')+'"')});if(vals.length)out.push(vals.join(','));});const blob=new Blob([out.join('\r\n')],{type:'text/csv;charset=utf-8;'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='smartgate_bypass_logs_'+new Date().toISOString().slice(0,10)+'.csv';document.body.appendChild(a);a.click();a.remove();}
</script>
</div>
</body></html>
