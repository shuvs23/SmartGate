<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();

require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| This module is intended for:
| - IGP
| - Library
| - Super Admin
| - MIS
|--------------------------------------------------------------------------
*/

$allowed_roles = [
    'super_admin',
    'MIS',
    'IGP',
    'Library'
];

if (!in_array($_SESSION['role'], $allowed_roles, true)) {
    header("Location: admin.php");
    exit;
}

$role = $_SESSION['role'];
$search = trim($_GET['search'] ?? '');

$students = [];

/*
|--------------------------------------------------------------------------
| SEARCH STUDENTS
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql = "
        SELECT
            id,
            student_id,
            full_name,
            program,
            year_level,
            section,
            email,
            current_status,
            is_active,
            photo,
            created_at,
            updated_at
        FROM students
        WHERE
            student_id LIKE ?
            OR full_name LIKE ?
            OR program LIKE ?
            OR section LIKE ?
        ORDER BY full_name ASC
        LIMIT 100
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $search_value = "%" . $search . "%";

        $stmt->bind_param(
            "ssss",
            $search_value,
            $search_value,
            $search_value,
            $search_value
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        $stmt->close();
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | SHOW RECENT / DEFAULT STUDENT LIST
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            student_id,
            full_name,
            program,
            year_level,
            section,
            email,
            current_status,
            is_active,
            photo,
            created_at,
            updated_at
        FROM students
        ORDER BY full_name ASC
        LIMIT 100
    ";

    $result = $conn->query($sql);

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$total_students = 0;
$active_students = 0;
$inactive_students = 0;
$current_in = 0;
$current_out = 0;

$stats_sql = "
    SELECT
        COUNT(*) AS total_students,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_students,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_students,
        SUM(CASE WHEN current_status = 'IN' THEN 1 ELSE 0 END) AS current_in,
        SUM(CASE WHEN current_status = 'OUT' THEN 1 ELSE 0 END) AS current_out
    FROM students
";

$stats_result = $conn->query($stats_sql);

if ($stats_result && $stats = $stats_result->fetch_assoc()) {

    $total_students = (int)$stats['total_students'];
    $active_students = (int)$stats['active_students'];
    $inactive_students = (int)$stats['inactive_students'];
    $current_in = (int)$stats['current_in'];
    $current_out = (int)$stats['current_out'];
}

/*
|--------------------------------------------------------------------------
| ESCAPE HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
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

<title>Student Access | SmartGate</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #1e293b;
}

.page {
    width: 96%;
    max-width: 1500px;
    margin: 25px auto;
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 4px 18px rgba(0,0,0,.06);

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.header h1 {
    margin: 0;
    color: #123b67;
    font-size: 27px;
}

.header p {
    margin: 7px 0 0;
    color: #64748b;
}

.role-badge {
    display: inline-block;
    margin-top: 10px;
    padding: 6px 12px;
    border-radius: 20px;
    background: #e0ecff;
    color: #123b67;
    font-size: 12px;
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    text-decoration: none;
    border: none;
    cursor: pointer;

    padding: 10px 15px;
    border-radius: 9px;

    font-size: 13px;
    font-weight: 700;
}

.btn-primary {
    background: #123b67;
    color: #ffffff;
}

.btn-secondary {
    background: #e2e8f0;
    color: #1e293b;
}

.btn-search {
    background: #2563eb;
    color: #ffffff;
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px;

    box-shadow:
        0 4px 15px rgba(15,23,42,.06);
}

.stat-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.stat-value {
    margin-top: 7px;
    font-size: 27px;
    font-weight: 800;
    color: #123b67;
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

.card {
    background: #ffffff;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;

    box-shadow:
        0 4px 15px rgba(15,23,42,.06);
}

.card-title {
    margin: 0 0 15px;
    font-size: 18px;
    font-weight: 800;
    color: #123b67;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;

    padding: 12px;

    border: 1px solid #cbd5e1;
    border-radius: 9px;

    font-size: 14px;
}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

thead {
    background: #123b67;
    color: #ffffff;
}

th {
    padding: 13px;
    text-align: left;

    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .3px;

    white-space: nowrap;
}

td {
    padding: 12px;

    border-bottom: 1px solid #edf1f5;

    font-size: 13px;
    vertical-align: middle;
}

tbody tr:hover {
    background: #f8fafc;
}

/*
|--------------------------------------------------------------------------
| STUDENT
|--------------------------------------------------------------------------
*/

.student-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-photo {
    width: 42px;
    height: 42px;

    object-fit: cover;
    border-radius: 50%;

    background: #e2e8f0;
}

.student-name {
    font-weight: 700;
}

.student-id {
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
}

/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;
    font-weight: 800;
}

.badge-in {
    background: #dcfce7;
    color: #166534;
}

.badge-out {
    background: #fee2e2;
    color: #991b1b;
}

.badge-active {
    background: #dcfce7;
    color: #166534;
}

.badge-inactive {
    background: #e2e8f0;
    color: #475569;
}

/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    padding: 45px;
    text-align: center;
    color: #64748b;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media(max-width:1100px) {

    .stats {
        grid-template-columns: repeat(3, 1fr);
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media(max-width:700px) {

    .page {
        width: 94%;
        margin: 15px auto;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .search-form {
        flex-direction: column;
    }
}

@media(max-width:450px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .header h1 {
        font-size: 22px;
    }
}

/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    body {
        background: #ffffff;
    }

    .page {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .actions,
    .search-form,
    .stats {
        display: none !important;
    }

    .card,
    .header {
        box-shadow: none;
        border: 1px solid #ddd;
    }

}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>

<body>

<?php include "smartgate_sidebar.php"; ?>
<div class="smartgate-main sg-page-shell">
<div class="page">

    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                Student Access
            </h1>

            <p>
                Search and view student access information.
            </p>

            <span class="role-badge">
                <?= e($role) ?> Personnel
            </span>

        </div>


        <div class="actions">
            <button
                type="button"
                onclick="window.print()"
                class="btn btn-secondary"
            >
                Print
            </button>

        </div>

    </div>


    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat">

            <div class="stat-label">
                TOTAL STUDENTS
            </div>

            <div class="stat-value">
                <?= number_format($total_students) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                ACTIVE
            </div>

            <div class="stat-value">
                <?= number_format($active_students) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                INACTIVE
            </div>

            <div class="stat-value">
                <?= number_format($inactive_students) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                CURRENTLY IN
            </div>

            <div class="stat-value">
                <?= number_format($current_in) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                CURRENTLY OUT
            </div>

            <div class="stat-value">
                <?= number_format($current_out) ?>
            </div>

        </div>

    </div>


    <!-- SEARCH -->

    <div class="card">

        <div class="card-title">
            Search Student
        </div>

        <form
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search Student ID, Name, Program, or Section..."
                value="<?= e($search) ?>"
            >

            <button
                type="submit"
                class="btn btn-search"
            >
                Search
            </button>

            <a
                href="student_access.php"
                class="btn btn-secondary"
            >
                Clear
            </a>

        </form>

    </div>


    <!-- STUDENT LIST -->

    <div class="card">

        <div class="card-title">

            Student Access Information

            <?php if ($search !== ''): ?>

                <span
                    style="
                        color:#64748b;
                        font-size:13px;
                        font-weight:normal;
                    "
                >
                    — Search results for
                    "<?= e($search) ?>"
                </span>

            <?php endif; ?>

        </div>


        <div class="table-wrapper">

            <?php if (!empty($students)): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Program
                            </th>

                            <th>
                                Year / Section
                            </th>

                            <th>
                                Access Status
                            </th>

                            <th>
                                Account Status
                            </th>

                            <th>
                                Student Email
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($students as $student): ?>

                        <tr>

                            <!-- STUDENT -->

                            <td>

                                <div class="student-cell">

                                    <?php
                                    $photo = trim(
                                        (string)(
                                            $student['photo'] ?? ''
                                        )
                                    );
                                    ?>

                                    <?php if ($photo !== ''): ?>

                                        <img
                                            src="<?= e($photo) ?>"
                                            class="student-photo"
                                            alt="Student Photo"
                                            onerror="
                                                this.style.display='none';
                                            "
                                        >

                                    <?php else: ?>

                                        <div
                                            class="student-photo"
                                        ></div>

                                    <?php endif; ?>


                                    <div>

                                        <div class="student-name">

                                            <?= e(
                                                $student['full_name']
                                            ) ?>

                                        </div>

                                        <div class="student-id">

                                            <?= e(
                                                $student['student_id']
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <!-- PROGRAM -->

                            <td>

                                <?= e(
                                    $student['program'] ?? '-'
                                ) ?>

                            </td>


                            <!-- YEAR / SECTION -->

                            <td>

                                <?= e(
                                    $student['year_level'] ?? '-'
                                ) ?>

                                /

                                <?= e(
                                    $student['section'] ?? '-'
                                ) ?>

                            </td>


                            <!-- ACCESS -->

                            <td>

                                <?php
                                if (
                                    $student['current_status']
                                    === 'IN'
                                ):
                                ?>

                                    <span
                                        class="
                                            badge
                                            badge-in
                                        "
                                    >
                                        CURRENTLY IN
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            badge
                                            badge-out
                                        "
                                    >
                                        CURRENTLY OUT
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ACCOUNT -->

                            <td>

                                <?php
                                if (
                                    (int)$student['is_active']
                                    === 1
                                ):
                                ?>

                                    <span
                                        class="
                                            badge
                                            badge-active
                                        "
                                    >
                                        ACTIVE
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            badge
                                            badge-inactive
                                        "
                                    >
                                        INACTIVE
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- EMAIL -->

                            <td>

                                <?= !empty(
                                    $student['email']
                                )
                                    ? e($student['email'])
                                    : '-'
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    <h3>
                        No Students Found
                    </h3>

                    <p>
                        Try searching using a different
                        student ID, name, program, or section.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</div>
</body>

</html>
