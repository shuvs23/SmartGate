<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

/*
|--------------------------------------------------------------------------
| SMARTGATE USER MANAGEMENT
|--------------------------------------------------------------------------
| Only Super Admin can access this page.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'super_admin') {
    header("Location: admin.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$status_filter = trim($_GET['status'] ?? '');


/*
|--------------------------------------------------------------------------
| BUILD FILTER
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = '';

if ($search !== '') {

    $where[] = "(
        username LIKE ?
        OR full_name LIKE ?
        OR department LIKE ?
    )";

    $search_param = "%{$search}%";

    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;

    $types .= "sss";
}

$valid_roles = [
    'super_admin',
    'MIS',
    'Security',
    'CCDU',
    'Guidance',
    'Library',
    'IGP'
];

if (
    $role_filter !== '' &&
    in_array($role_filter, $valid_roles, true)
) {
    $where[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($status_filter === 'ACTIVE') {
    $where[] = "is_active = 1";
}

if ($status_filter === 'INACTIVE') {
    $where[] = "is_active = 0";
}

$where_sql = '';

if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function bindDynamicParams($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return;
    }

    $refs = [];

    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }

    array_unshift($refs, $types);

    call_user_func_array(
        [$stmt, 'bind_param'],
        $refs
    );
}


/*
|--------------------------------------------------------------------------
| GET USERS
|--------------------------------------------------------------------------
*/

$users = [];

$sql = "
    SELECT
        id,
        username,
        full_name,
        role,
        department,
        is_active,
        created_at
    FROM users
    $where_sql
    ORDER BY
        CASE
            WHEN role = 'super_admin' THEN 1
            WHEN role = 'MIS' THEN 2
            WHEN role = 'Security' THEN 3
            WHEN role = 'CCDU' THEN 4
            WHEN role = 'Guidance' THEN 5
            WHEN role = 'Library' THEN 6
            WHEN role = 'IGP' THEN 7
            ELSE 8
        END,
        full_name ASC
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    bindDynamicParams(
        $stmt,
        $types,
        $params
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$total_users = 0;
$active_users = 0;
$inactive_users = 0;
$super_admin_count = 0;
$mis_count = 0;
$security_count = 0;
$ccdu_count = 0;
$guidance_count = 0;
$library_count = 0;
$igp_count = 0;

$result = $conn->query("
    SELECT
        COUNT(*) AS total_users,
        SUM(is_active = 1) AS active_users,
        SUM(is_active = 0) AS inactive_users,
        SUM(role = 'super_admin') AS super_admin_count,
        SUM(role = 'MIS') AS mis_count,
        SUM(role = 'Security') AS security_count,
        SUM(role = 'CCDU') AS ccdu_count,
        SUM(role = 'Guidance') AS guidance_count,
        SUM(role = 'Library') AS library_count,
        SUM(role = 'IGP') AS igp_count
    FROM users
");

if ($result) {

    $stats = $result->fetch_assoc();

    $total_users = (int)($stats['total_users'] ?? 0);
    $active_users = (int)($stats['active_users'] ?? 0);
    $inactive_users = (int)($stats['inactive_users'] ?? 0);
    $super_admin_count = (int)($stats['super_admin_count'] ?? 0);
    $mis_count = (int)($stats['mis_count'] ?? 0);
    $security_count = (int)($stats['security_count'] ?? 0);
    $ccdu_count = (int)($stats['ccdu_count'] ?? 0);
    $guidance_count = (int)($stats['guidance_count'] ?? 0);
    $library_count = (int)($stats['library_count'] ?? 0);
    $igp_count = (int)($stats['igp_count'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'csv'
) {

    header(
        'Content-Type: text/csv; charset=utf-8'
    );

    header(
        'Content-Disposition: attachment; filename=smartgate_users_' .
        date('Y-m-d_H-i-s') .
        '.csv'
    );

    $output = fopen(
        'php://output',
        'w'
    );

    fputcsv(
        $output,
        [
            'Username',
            'Full Name',
            'Role',
            'Department',
            'Account Status',
            'Created At'
        ]
    );

    foreach ($users as $user) {

        fputcsv(
            $output,
            [
                $user['username'],
                $user['full_name'],
                $user['role'],
                $user['department'],
                $user['is_active']
                    ? 'ACTIVE'
                    : 'INACTIVE',
                $user['created_at']
            ]
        );
    }

    fclose($output);
    exit;
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

<title>
    SmartGate User Management
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7fb;

    color: #1e293b;
}

.page {
    max-width: 1500px;

    margin: auto;

    padding: 25px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;
}

.header h1 {
    margin: 0;

    font-size: 28px;
}

.header p {
    margin: 6px 0 0;

    color: #64748b;
}

.actions {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.btn {
    border: none;

    padding: 11px 16px;

    border-radius: 9px;

    font-weight: 700;

    text-decoration: none;

    cursor: pointer;

    display: inline-block;
}

.btn-primary {
    background: #2563eb;

    color: white;
}

.btn-success {
    background: #15803d;

    color: white;
}

.btn-dark {
    background: #0f172a;

    color: white;
}

.btn-secondary {
    background: #e2e8f0;

    color: #1e293b;
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

.stats {
    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 14px;

    margin-bottom: 22px;
}

.stat {
    background: white;

    border-radius: 14px;

    padding: 18px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .06);
}

.stat-label {
    font-size: 12px;

    color: #64748b;

    font-weight: 700;
}

.stat-value {
    font-size: 28px;

    font-weight: 800;

    margin-top: 7px;
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.filter-card {
    background: white;

    padding: 20px;

    border-radius: 14px;

    margin-bottom: 22px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .06);
}

.filter-title {
    font-size: 17px;

    font-weight: 800;

    margin-bottom: 15px;
}

.filters {
    display: grid;

    grid-template-columns:
        2fr 1fr 1fr;

    gap: 10px;
}

.field label {
    display: block;

    font-size: 12px;

    color: #64748b;

    font-weight: 700;

    margin-bottom: 5px;
}

.field input,
.field select {
    width: 100%;

    padding: 10px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    background: white;
}

.filter-buttons {
    display: flex;

    gap: 8px;

    margin-top: 12px;

    flex-wrap: wrap;
}


/*
|--------------------------------------------------------------------------
| ROLE SUMMARY
|--------------------------------------------------------------------------
*/

.role-grid {
    display: grid;

    grid-template-columns:
        repeat(7, 1fr);

    gap: 10px;

    margin-bottom: 22px;
}

.role-box {
    background: white;

    border-radius: 12px;

    padding: 14px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .05);
}

.role-name {
    font-size: 11px;

    color: #64748b;

    font-weight: 700;
}

.role-count {
    font-size: 22px;

    font-weight: 800;

    margin-top: 5px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.card {
    background: white;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .06);
}

.card-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;

    gap: 15px;
}

.card-header h2 {
    margin: 0;

    font-size: 18px;
}

.result-count {
    color: #64748b;

    font-size: 13px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    min-width: 950px;

    border-collapse: collapse;
}

th {
    text-align: left;

    padding: 12px;

    font-size: 12px;

    color: #64748b;

    border-bottom:
        1px solid #e2e8f0;

    white-space: nowrap;
}

td {
    padding: 13px 12px;

    font-size: 13px;

    border-bottom:
        1px solid #eef2f7;

    vertical-align: middle;
}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

.user-name {
    font-weight: 700;
}

.username {
    color: #64748b;

    font-size: 11px;

    margin-top: 3px;
}


/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;
}

.badge-active {
    background: #dcfce7;

    color: #166534;
}

.badge-inactive {
    background: #e2e8f0;

    color: #475569;
}


/* ROLE COLORS */

.badge-super {
    background: #ede9fe;

    color: #6d28d9;
}

.badge-mis {
    background: #dbeafe;

    color: #1d4ed8;
}

.badge-security {
    background: #fee2e2;

    color: #991b1b;
}

.badge-ccdu {
    background: #fef3c7;

    color: #92400e;
}

.badge-guidance {
    background: #fce7f3;

    color: #9d174d;
}

.badge-library {
    background: #dcfce7;

    color: #166534;
}

.badge-igp {
    background: #e0f2fe;

    color: #075985;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.row-actions {
    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.action-btn {
    display: inline-block;

    border: 0;

    cursor: pointer;

    font-family: inherit;

    padding: 7px 10px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;

    background: #e2e8f0;

    color: #1e293b;
}

.action-btn.edit {
    background: #dbeafe;

    color: #1d4ed8;
}

.action-btn.activate {
    background: #dcfce7;

    color: #166534;
}

.action-btn.deactivate {
    background: #fef3c7;

    color: #92400e;
}

.action-btn.delete {
    background: #fee2e2;

    color: #991b1b;
}


/*
|--------------------------------------------------------------------------
| SECURITY NOTE
|--------------------------------------------------------------------------
*/

.security-note {
    margin-top: 15px;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 10px;

    padding: 12px;

    font-size: 12px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    text-align: center;

    padding: 45px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media(max-width:1200px) {

    .stats {
        grid-template-columns:
            repeat(3, 1fr);
    }

    .role-grid {
        grid-template-columns:
            repeat(4, 1fr);
    }

    .filters {
        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media(max-width:800px) {

    .header {
        flex-direction: column;

        align-items: flex-start;
    }

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .role-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .filters {
        grid-template-columns: 1fr;
    }

}

@media(max-width:500px) {

    .page {
        padding: 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .role-grid {
        grid-template-columns: 1fr;
    }

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    body {
        background: white;
    }

    .page {
        max-width: none;

        padding: 0;
    }

    .actions,
    .filter-card,
    .row-actions,
    .security-note {
        display: none !important;
    }

    .card,
    .stat,
    .role-box {
        box-shadow: none;

        border: 1px solid #ddd;
    }

}

table tbody td .user-name {
    color: #64748b !important;
    font-weight: 700 !important;
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
                User Management
            </h1>

            <p>
                Manage SmartGate system accounts
                and role-based access
            </p>

        </div>


        <div class="actions">
            <a
                href="add_user.php"
                class="btn btn-primary"
            >
                + Add User
            </a>

            <a
                href="?<?= htmlspecialchars(
                    http_build_query(
                        array_merge(
                            $_GET,
                            ['export' => 'csv']
                        )
                    )
                ) ?>"
                class="btn btn-success"
            >
                Export CSV
            </a>

            <button
                onclick="window.print()"
                class="btn btn-dark"
            >
                Print
            </button>

        </div>

    </div>


    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat">

            <div class="stat-label">
                TOTAL USERS
            </div>

            <div class="stat-value">
                <?= number_format(
                    $total_users
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                ACTIVE USERS
            </div>

            <div class="stat-value">
                <?= number_format(
                    $active_users
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                INACTIVE USERS
            </div>

            <div class="stat-value">
                <?= number_format(
                    $inactive_users
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                SUPER ADMINS
            </div>

            <div class="stat-value">
                <?= number_format(
                    $super_admin_count
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                MIS USERS
            </div>

            <div class="stat-value">
                <?= number_format(
                    $mis_count
                ) ?>
            </div>

        </div>

    </div>


    <!-- ROLE SUMMARY -->

    <div class="role-grid">

        <div class="role-box">

            <div class="role-name">
                SUPER ADMIN
            </div>

            <div class="role-count">
                <?= $super_admin_count ?>
            </div>

        </div>


        <div class="role-box">

            <div class="role-name">
                MIS
            </div>

            <div class="role-count">
                <?= $mis_count ?>
            </div>

        </div>


        <div class="role-box">

            <div class="role-name">
                SECURITY
            </div>

            <div class="role-count">
                <?= $security_count ?>
            </div>

        </div>


        <div class="role-box">

            <div class="role-name">
                CCDU
            </div>

            <div class="role-count">
                <?= $ccdu_count ?>
            </div>

        </div>


        <div class="role-box">

            <div class="role-name">
                GUIDANCE
            </div>

            <div class="role-count">
                <?= $guidance_count ?>
            </div>

        </div>


        <div class="role-box">

            <div class="role-name">
                LIBRARY
            </div>

            <div class="role-count">
                <?= $library_count ?>
            </div>

        </div>


        <div class="role-box">

            <div class="role-name">
                IGP
            </div>

            <div class="role-count">
                <?= $igp_count ?>
            </div>

        </div>

    </div>


    <!-- FILTER -->

    <div class="filter-card">

        <div class="filter-title">
            Search & Filter Accounts
        </div>


        <form method="GET">

            <div class="filters">


                <div class="field">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="
                            Username, name, department...
                        "
                        value="<?= htmlspecialchars(
                            $search
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Role
                    </label>

                    <select name="role">

                        <option value="">
                            All Roles
                        </option>

                        <?php foreach (
                            $valid_roles
                            as $role
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $role
                                ) ?>"
                                <?= $role_filter === $role
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $role
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        Account Status
                    </label>

                    <select name="status">

                        <option value="">
                            All Accounts
                        </option>

                        <option
                            value="ACTIVE"
                            <?= $status_filter === 'ACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="INACTIVE"
                            <?= $status_filter === 'INACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

            </div>


            <div class="filter-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply Filters
                </button>


                <a
                    href="user_management.php"
                    class="btn btn-secondary"
                >
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- USER TABLE -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2>
                    System Accounts
                </h2>

                <div class="result-count">

                    Showing
                    <strong>
                        <?= number_format(
                            count($users)
                        ) ?>
                    </strong>

                    account(s)

                </div>

            </div>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            User
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Department
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (
                    empty($users)
                ): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="empty"
                        >
                            No user accounts found.
                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $users
                        as $user
                    ): ?>

                        <?php

                        $role_class = '';

                        switch (
                            $user['role']
                        ) {

                            case 'super_admin':
                                $role_class =
                                    'badge-super';
                                break;

                            case 'MIS':
                                $role_class =
                                    'badge-mis';
                                break;

                            case 'Security':
                                $role_class =
                                    'badge-security';
                                break;

                            case 'CCDU':
                                $role_class =
                                    'badge-ccdu';
                                break;

                            case 'Guidance':
                                $role_class =
                                    'badge-guidance';
                                break;

                            case 'Library':
                                $role_class =
                                    'badge-library';
                                break;

                            case 'IGP':
                                $role_class =
                                    'badge-igp';
                                break;
                        }

                        ?>


                        <tr>


                            <!-- USER -->

                            <td>

                                <div
                                    class="user-name"
                                >
                                    <?= htmlspecialchars(
                                        $user[
                                            'full_name'
                                        ]
                                    ) ?>
                                </div>

                                <div
                                    class="username"
                                >
                                    @<?= htmlspecialchars(
                                        $user[
                                            'username'
                                        ]
                                    ) ?>
                                </div>

                            </td>


                            <!-- ROLE -->

                            <td>

                                <span
                                    class="
                                        badge
                                        <?= $role_class ?>
                                    "
                                >
                                    <?= htmlspecialchars(
                                        $user['role']
                                    ) ?>
                                </span>

                            </td>


                            <!-- DEPARTMENT -->

                            <td>

                                <?= !empty(
                                    $user[
                                        'department'
                                    ]
                                )
                                    ? htmlspecialchars(
                                        $user[
                                            'department'
                                        ]
                                    )
                                    : '<span style="color:#94a3b8">Not specified</span>'
                                ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php if (
                                    (int)$user[
                                        'is_active'
                                    ] === 1
                                ): ?>

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


                            <!-- CREATED -->

                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $user[
                                                'created_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div
                                    class="row-actions"
                                >


                                    <a
                                        href="edit_user.php?id=<?= (int)$user['id'] ?>"
                                        class="
                                            action-btn
                                            edit
                                        "
                                    >
                                        Edit
                                    </a>


                                    <?php if (
                                        (int)$user[
                                            'id'
                                        ] ===
                                        (int)$_SESSION[
                                            'user_id'
                                        ]
                                    ): ?>

                                        <span
                                            class="
                                                action-btn
                                            "
                                            title="
                                                You cannot deactivate
                                                your own account.
                                            "
                                        >
                                            Current Account
                                        </span>

                                    <?php else: ?>


                                        <?php if (
                                            (int)$user[
                                                'is_active'
                                            ] === 1
                                        ): ?>

                                            <form method="POST" action="toggle_user.php" style="display:inline" onsubmit="return confirm('Deactivate this user account?');">
                                                <?= smartgate_csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                <button type="submit" class="action-btn deactivate">Deactivate</button>
                                            </form>

                                        <?php else: ?>

                                            <form method="POST" action="toggle_user.php" style="display:inline" onsubmit="return confirm('Activate this user account?');">
                                                <?= smartgate_csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                <button type="submit" class="action-btn activate">Activate</button>
                                            </form>

                                        <?php endif; ?>


                                        <form method="POST" action="delete_user.php" style="display:inline" onsubmit="return confirm('WARNING: Delete this user account permanently?');">
                                            <?= smartgate_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                            <button type="submit" class="action-btn delete">Delete</button>
                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <div class="security-note">

            <strong>
                Security:
            </strong>

            Only the Super Admin can access
            this page and manage SmartGate
            system accounts.

            User actions should also be recorded
            in the system audit trail.

        </div>

    </div>

</div>

</div>
</body>

</html>
