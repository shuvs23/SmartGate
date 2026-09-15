<?php
require_once __DIR__ . "/security.php";
smartgate_start_session();
require_once "db.php";

/* =========================================================
   ACCESS CONTROL
   ========================================================= */

$allowed_roles = ['super_admin', 'MIS'];

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', $allowed_roles, true)
) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   HELPERS
   ========================================================= */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   VARIABLES
   ========================================================= */

$message = '';
$message_type = '';

$added = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$preview_rows = [];
$show_preview = false;


/* =========================================================
   CSV PREVIEW
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['preview_csv'])
) {

    smartgate_require_csrf();

    if (
        !isset($_FILES['csv_file']) ||
        $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK
    ) {

        $message = 'Please select a valid CSV file.';
        $message_type = 'error';

    } else {

        $file = $_FILES['csv_file'];

        if ($file['size'] > 5 * 1024 * 1024) {

            $message =
                'The CSV file is too large. Maximum size is 5 MB.';

            $message_type = 'error';

        } else {

            $extension =
                strtolower(
                    pathinfo(
                        $file['name'],
                        PATHINFO_EXTENSION
                    )
                );

            if ($extension !== 'csv') {

                $message =
                    'Only CSV files are allowed.';

                $message_type = 'error';

            } else {

                $handle = fopen($file['tmp_name'], 'r');

                if (!$handle) {

                    $message =
                        'Unable to read the uploaded CSV file.';

                    $message_type = 'error';

                } else {

                    /*
                     * Read header.
                     */
                    $header = fgetcsv($handle);

                    if (!$header) {

                        $message =
                            'The CSV file is empty.';

                        $message_type = 'error';

                    } else {

                        /*
                         * Remove UTF-8 BOM.
                         */
                        if (
                            isset($header[0])
                        ) {

                            $header[0] =
                                preg_replace(
                                    '/^\xEF\xBB\xBF/',
                                    '',
                                    $header[0]
                                );
                        }

                        /*
                         * Normalize header names.
                         */
                        $header = array_map(
                            function ($value) {

                                return strtolower(
                                    trim($value)
                                );

                            },
                            $header
                        );

                        /*
                         * Expected columns.
                         */
                        $required_columns = [
                            'student_id',
                            'full_name',
                            'program',
                            'year_level',
                            'section'
                        ];

                        $optional_columns = [
                        'email',
                        'parent_email',
                        'qr_valid_from',
                        'qr_valid_until'
                    ];
                        $missing_columns = [];

                        foreach (
                            $required_columns
                            as $required
                        ) {

                            if (
                                !in_array(
                                    $required,
                                    $header,
                                    true
                                )
                            ) {

                                $missing_columns[] =
                                    $required;
                            }
                        }

                        if (
                            count($missing_columns) > 0
                        ) {

                            $message =
                                'Missing required column(s): ' .
                                implode(
                                    ', ',
                                    $missing_columns
                                );

                            $message_type = 'error';

                        } else {

                            /*
                             * Read rows for preview.
                             */
                            $row_number = 1;

                            while (
                                ($row = fgetcsv($handle))
                                !== false
                            ) {

                                $row_number++;

                                if (
                                    count(
                                        array_filter(
                                            $row,
                                            function ($v) {
                                                return trim(
                                                    (string)$v
                                                ) !== '';
                                            }
                                        )
                                    ) === 0
                                ) {
                                    continue;
                                }

                                $data = [];

                                foreach (
                                    $header as $index => $column
                                ) {

                                    $data[$column] =
                                        isset($row[$index])
                                            ? trim(
                                                (string)$row[$index]
                                            )
                                            : '';
                                }

                                $preview_rows[] = [
                                    'row' => $row_number,
                                    'student_id' =>
                                        $data['student_id']
                                        ?? '',
                                    'full_name' =>
                                        $data['full_name']
                                        ?? '',
                                    'program' =>
                                        $data['program']
                                        ?? '',
                                    'year_level' =>
                                        $data['year_level']
                                        ?? '',
                                    'section' =>
                                        $data['section']
                                        ?? '',
                                    'email' =>
                                        $data['email']
                                        ?? '',
                                    'parent_email' =>
                                        $data['parent_email']
                                        ?? '',
                                    'qr_valid_from' =>
                                        $data['qr_valid_from']
                                        ?? '',
                                    'qr_valid_until' =>
                                        $data['qr_valid_until']
                                        ?? ''
                                ];
                            }

                            fclose($handle);

                            $show_preview = true;

                            if (
                                count($preview_rows) === 0
                            ) {

                                $message =
                                    'No student records were found in the CSV.';

                                $message_type = 'error';

                                $show_preview = false;
                            }
                        }
                    }

                    if (
                        is_resource($handle)
                    ) {
                        fclose($handle);
                    }
                }
            }
        }
    }
}


/* =========================================================
   IMPORT CSV
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['import_csv'])
) {

    smartgate_require_csrf();

    if (
        !isset($_FILES['csv_file']) ||
        $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK
    ) {

        $message =
            'Please select a valid CSV file.';

        $message_type = 'error';

    } else {

        $file = $_FILES['csv_file'];

        if ($file['size'] > 5 * 1024 * 1024) {

            $message =
                'The CSV file is too large. Maximum size is 5 MB.';

            $message_type = 'error';

        } else {

            $extension =
                strtolower(
                    pathinfo(
                        $file['name'],
                        PATHINFO_EXTENSION
                    )
                );

            if ($extension !== 'csv') {

                $message =
                    'Only CSV files are allowed.';

                $message_type = 'error';

            } else {

                $handle = fopen($file['tmp_name'], 'r');

                if (!$handle) {

                    $message =
                        'Unable to read the uploaded CSV file.';

                    $message_type = 'error';

                } else {

                    $header = fgetcsv($handle);

                    if (!$header) {

                        $message =
                            'The CSV file is empty.';

                        $message_type = 'error';

                    } else {

                        /*
                         * Remove BOM.
                         */
                        if (
                            isset($header[0])
                        ) {

                            $header[0] =
                                preg_replace(
                                    '/^\xEF\xBB\xBF/',
                                    '',
                                    $header[0]
                                );
                        }

                        /*
                         * Normalize header.
                         */
                        $header = array_map(
                            function ($value) {

                                return strtolower(
                                    trim($value)
                                );

                            },
                            $header
                        );

                        $required_columns = [
                            'student_id',
                            'full_name',
                            'program',
                            'year_level',
                            'section'
                        ];

                        $missing_columns = [];

                        foreach (
                            $required_columns
                            as $required
                        ) {

                            if (
                                !in_array(
                                    $required,
                                    $header,
                                    true
                                )
                            ) {

                                $missing_columns[] =
                                    $required;
                            }
                        }

                        if (
                            count($missing_columns) > 0
                        ) {

                            $message =
                                'Missing required column(s): ' .
                                implode(
                                    ', ',
                                    $missing_columns
                                );

                            $message_type = 'error';

                        } else {

                            /*
                             * Start transaction.
                             */
                            $conn->begin_transaction();

                            try {

                                $row_number = 1;

                                while (
                                    ($row = fgetcsv($handle))
                                    !== false
                                ) {

                                    $row_number++;

                                    /*
                                     * Ignore empty rows.
                                     */
                                    if (
                                        count(
                                            array_filter(
                                                $row,
                                                function ($v) {
                                                    return trim(
                                                        (string)$v
                                                    ) !== '';
                                                }
                                            )
                                        ) === 0
                                    ) {
                                        continue;
                                    }

                                    $data = [];

                                    foreach (
                                        $header
                                        as $index => $column
                                    ) {

                                        $data[$column] =
                                            isset($row[$index])
                                                ? trim(
                                                    (string)$row[$index]
                                                )
                                                : '';
                                    }


                                    /* =================================
                                       GET VALUES
                                       ================================= */

                                    $student_id =
                                        $data['student_id']
                                        ?? '';

                                    $full_name =
                                        $data['full_name']
                                        ?? '';

                                    $program =
                                        $data['program']
                                        ?? '';

                                    $year_level =
                                        $data['year_level']
                                        ?? '';

                                    $section =
                                        $data['section']
                                        ?? '';

                                    $email =
                                        $data['email']
                                        ?? '';

                                    $parent_email =
                                        $data['parent_email']
                                        ?? '';

                                    $qr_valid_from =
                                        $data['qr_valid_from']
                                        ?? '';

                                    $qr_valid_until =
                                        $data['qr_valid_until']
                                        ?? '';


                                    /* =================================
                                       VALIDATION
                                       ================================= */

                                    if (
                                        $student_id === '' ||
                                        $full_name === '' ||
                                        $program === '' ||
                                        $year_level === '' ||
                                        $section === ''
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Required field is missing.";

                                        continue;
                                    }


                                    /*
                                     * Length validation.
                                     */

                                    if (
                                        mb_strlen(
                                            $student_id
                                        ) > 50
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Student ID is too long.";

                                        continue;
                                    }

                                    if (
                                        mb_strlen(
                                            $full_name
                                        ) > 150
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Full name is too long.";

                                        continue;
                                    }

                                    if (
                                        mb_strlen(
                                            $program
                                        ) > 100
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Program is too long.";

                                        continue;
                                    }

                                    if (
                                        mb_strlen(
                                            $year_level
                                        ) > 20
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Year level is too long.";

                                        continue;
                                    }

                                    if (
                                        mb_strlen(
                                            $section
                                        ) > 50
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Section is too long.";

                                        continue;
                                    }


                                    /*
                                     * Email validation.
                                     */

                                    if (
                                        $email !== '' &&
                                        !filter_var(
                                            $email,
                                            FILTER_VALIDATE_EMAIL
                                        )
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Invalid student email.";

                                        continue;
                                    }


                                    if (
                                        $parent_email !== '' &&
                                        !filter_var(
                                            $parent_email,
                                            FILTER_VALIDATE_EMAIL
                                        )
                                    ) {

                                        $errors[] =
                                            "Row {$row_number}: " .
                                            "Invalid parent email.";

                                        continue;
                                    }


                                    /*
                                     * Check existing student.
                                     */

                                    $check =
                                        $conn->prepare("
                                            SELECT
                                                id,
                                                qr_code,
                                                current_status,
                                                is_active
                                            FROM students
                                            WHERE student_id = ?
                                            LIMIT 1
                                        ");

                                    $check->bind_param(
                                        "s",
                                        $student_id
                                    );

                                    $check->execute();

                                    $existing =
                                        $check
                                            ->get_result()
                                            ->fetch_assoc();

                                    $check->close();


                                    /* =================================
                                       UPDATE EXISTING
                                       ================================= */

                                    if ($existing) {

                                        /*
                                         * IMPORTANT:
                                         * Preserve the existing QR
                                         * if it already exists.
                                         *
                                         * If it is missing, assign
                                         * student ID as QR.
                                         */

                                        $qr_code =
                                            !empty(
                                                $existing['qr_code']
                                            )
                                            ? $existing['qr_code']
                                            : $student_id;


                                        $update =
                                            $conn->prepare("
                                                UPDATE students
                                                SET
                                                    full_name = ?,
                                                    program = ?,
                                                    year_level = ?,
                                                    section = ?,
                                                    email = ?,
                                                    parent_email = ?,
                                                    qr_code = ?,
                                                    qr_valid_from = NULLIF(?, ''),
                                                    qr_valid_until = NULLIF(?, ''),
                                                    is_active = 1,
                                                    updated_at =
                                                        CURRENT_TIMESTAMP
                                                WHERE student_id = ?
                                            ");

                                        $update->bind_param(
                                        "ssssssssss",
                                        $full_name,
                                        $program,
                                        $year_level,
                                        $section,
                                        $email,
                                        $parent_email,
                                        $qr_code,
                                        $qr_valid_from,
                                        $qr_valid_until,
                                        $student_id
                                    );

                                        if (
                                            $update->execute()
                                        ) {

                                            $updated++;

                                        } else {

                                            $errors[] =
                                                "Row {$row_number}: " .
                                                "Unable to update student.";

                                        }

                                        $update->close();


                                    } else {

                                        /* =================================
                                           INSERT NEW STUDENT
                                           ================================= */

                                        /*
                                         * QR CODE = STUDENT ID
                                         *
                                         * This is important because your
                                         * GM861 scanner reads the value
                                         * encoded in the student's QR.
                                         */

                                        $qr_code =
                                            $student_id;


                                        $insert =
                                            $conn->prepare("
                                                INSERT INTO students
                                                (
                                                    student_id,
                                                    full_name,
                                                    program,
                                                    year_level,
                                                    section,
                                                    email,
                                                    parent_email,
                                                    qr_code,
                                                    qr_valid_from,
                                                    qr_valid_until,
                                                    photo,
                                                    current_status,
                                                    is_active
                                                )
                                                VALUES
                                                (
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    NULLIF(?, ''),
                                                    NULLIF(?, ''),
                                                    ?,
                                                    'OUT',
                                                    1
                                                )
                                            ");


                                        $photo =
                                            'photos/' .
                                            $student_id .
                                            '.jpg';


                                        $insert->bind_param(
                                        "sssssssssss",
                                        $student_id,
                                        $full_name,
                                        $program,
                                        $year_level,
                                        $section,
                                        $email,
                                        $parent_email,
                                        $qr_code,
                                        $qr_valid_from,
                                        $qr_valid_until,
                                        $photo
                                    );


                                        if (
                                            $insert->execute()
                                        ) {

                                            $added++;

                                        } else {

                                            /*
                                             * Duplicate/constraint
                                             * errors are skipped rather
                                             * than crashing the whole
                                             * import.
                                             */

                                            if (
                                                $conn->errno === 1062
                                            ) {

                                                $skipped++;

                                                $errors[] =
                                                    "Row {$row_number}: " .
                                                    "Duplicate student ID or QR code.";

                                            } else {

                                                $errors[] =
                                                    "Row {$row_number}: " .
                                                    "Unable to add student.";

                                            }
                                        }

                                        $insert->close();
                                    }
                                }


                                /* =================================
                                   AUDIT TRAIL
                                   ================================= */

                                $audit_description =
                                    "MIS CSV upload: " .
                                    basename($file['name']) .
                                    " | Added: {$added}" .
                                    " | Updated: {$updated}" .
                                    " | Skipped: {$skipped}" .
                                    " | Errors: " .
                                    count($errors);


                                $audit =
                                    $conn->prepare("
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
                                        (
                                            ?,
                                            'STUDENT_CSV_UPLOAD',
                                            ?,
                                            'STUDENT',
                                            NULL,
                                            ?
                                        )
                                    ");


                                $user_id =
                                    (int)$_SESSION['user_id'];

                                $ip =
                                    $_SERVER['REMOTE_ADDR']
                                    ?? '';


                                $audit->bind_param(
                                    "iss",
                                    $user_id,
                                    $audit_description,
                                    $ip
                                );

                                $audit->execute();
                                $audit->close();


                                /*
                                 * Commit.
                                 */
                                $conn->commit();


                                $message =
                                    'CSV import completed successfully.';

                                $message_type =
                                    'success';


                            } catch (
                                Throwable $e
                            ) {

                                $conn->rollback();

                                $message =
                                    'Import failed. No database changes were saved.';

                                $message_type =
                                    'error';

                                $errors[] =
                                    'System error: ' .
                                    $e->getMessage();
                            }
                        }
                    }

                    fclose($handle);
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

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>MIS Student Upload | SmartGate</title>


<style>

/* =========================================================
   PAGE
   ========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    background: #f4f7fb;

    color: #1f2937;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}

.mis-upload-page {
    max-width: 1500px;
    margin: 0 auto;
}


/* =========================================================
   HEADER
   ========================================================= */

.page-header {
    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

    margin-bottom: 24px;
}

.page-title h1 {
    margin: 0;

    color: #0f172a;

    font-size: 28px;
    font-weight: 700;
}

.page-title p {
    margin: 7px 0 0;

    color: #64748b;

    font-size: 14px;
}


/* =========================================================
   CARDS
   ========================================================= */

.card {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 12px;

    padding: 22px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px rgba(15, 23, 42, 0.04);
}

.card-title {
    margin-bottom: 6px;

    color: #0f172a;

    font-size: 16px;
    font-weight: 700;
}

.card-description {
    margin-bottom: 18px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.5;
}


/* =========================================================
   INSTRUCTIONS
   ========================================================= */

.instructions {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 12px;
}

.instruction-item {
    padding: 14px;

    border-radius: 9px;

    background: #f8fafc;

    border: 1px solid #e2e8f0;
}

.instruction-number {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    width: 25px;
    height: 25px;

    margin-right: 7px;

    border-radius: 50%;

    background: #2563eb;

    color: #ffffff;

    font-size: 11px;
    font-weight: 700;
}

.instruction-title {
    font-size: 13px;
    font-weight: 700;

    color: #0f172a;
}

.instruction-text {
    margin-top: 6px;

    color: #64748b;

    font-size: 12px;

    line-height: 1.5;
}


/* =========================================================
   UPLOAD AREA
   ========================================================= */

.upload-form {
    display: flex;

    align-items: center;

    gap: 12px;

    flex-wrap: wrap;
}

.file-input {
    flex: 1;

    min-width: 250px;

    padding: 10px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    background: #ffffff;

    font-size: 13px;
}


/* =========================================================
   BUTTONS
   ========================================================= */

.btn {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    border: none;

    border-radius: 8px;

    padding: 10px 15px;

    font-size: 13px;

    font-weight: 600;

    text-decoration: none;

    cursor: pointer;
}

.btn-primary {
    background: #2563eb;

    color: #ffffff;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-success {
    background: #15803d;

    color: #ffffff;
}

.btn-success:hover {
    background: #166534;
}

.btn-secondary {
    background: #e2e8f0;

    color: #334155;
}

.btn-secondary:hover {
    background: #cbd5e1;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {
    padding: 14px 16px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 13px;

    line-height: 1.5;
}

.alert-success {
    background: #dcfce7;

    color: #166534;

    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary-grid {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 14px;

    margin-bottom: 20px;
}

.summary-card {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 10px;

    padding: 17px;
}

.summary-label {
    color: #64748b;

    font-size: 11px;

    font-weight: 600;

    text-transform: uppercase;
}

.summary-value {
    margin-top: 5px;

    color: #0f172a;

    font-size: 26px;

    font-weight: 700;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-card {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 12px;

    overflow: hidden;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px rgba(15, 23, 42, 0.04);
}

.table-header {
    padding: 17px 20px;

    border-bottom: 1px solid #e2e8f0;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.table-title {
    color: #0f172a;

    font-size: 15px;

    font-weight: 700;
}

.table-subtitle {
    margin-top: 4px;

    color: #64748b;

    font-size: 12px;
}

.table-wrapper {
    width: 100%;

    overflow-x: auto;
}

table {
    width: 100%;

    min-width: 1000px;

    border-collapse: collapse;
}

thead {
    background: #f8fafc;
}

th {
    padding: 11px 13px;

    text-align: left;

    color: #64748b;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .4px;

    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 12px 13px;

    color: #334155;

    font-size: 12px;

    border-bottom: 1px solid #edf2f7;
}

tbody tr:hover {
    background: #f8fafc;
}


/* =========================================================
   ERROR LIST
   ========================================================= */

.error-list {
    margin: 12px 0 0;

    padding-left: 20px;

    color: #991b1b;

    font-size: 12px;

    line-height: 1.7;
}


/* =========================================================
   BADGES
   ========================================================= */

.badge {
    display: inline-flex;

    padding: 4px 8px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 700;
}

.badge-ready {
    background: #dbeafe;

    color: #1d4ed8;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .instructions {
        grid-template-columns: 1fr;
    }

    .summary-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 600px) {

    .page-header {
        flex-direction: column;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .upload-form {
        flex-direction: column;

        align-items: stretch;
    }

    .file-input {
        min-width: 0;
    }

}


/* =========================================================
   PRINT
   ========================================================= */

@media print {

    .smartgate-sidebar,
    .smartgate-mobile-toggle,
    .smartgate-overlay,
    .upload-form,
    .instructions,
    .page-header .header-actions {
        display: none !important;
    }

    .smartgate-main {
        margin-left: 0 !important;

        padding: 0 !important;
    }

    body {
        background: #ffffff;
    }

}

</style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>


<body>

<?php
include "smartgate_sidebar.php";
?>

<div class="smartgate-main sg-page-shell">

<div class="mis-upload-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                MIS Student Upload
            </h1>

            <p>
                Import and update student records using
                the official MIS CSV format.
            </p>

        </div>

    </div>


    <!-- =====================================================
         ALERT
         ===================================================== -->

    <?php if ($message !== ''): ?>

        <div
            class="
                alert
                <?= $message_type === 'success'
                    ? 'alert-success'
                    : 'alert-error'
                ?>
        ">

            <?= h($message) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         IMPORT SUMMARY
         ===================================================== -->

    <?php if (
        $added > 0 ||
        $updated > 0 ||
        $skipped > 0 ||
        count($errors) > 0
    ): ?>

        <div class="summary-grid">

            <div class="summary-card">

                <div class="summary-label">
                    Added
                </div>

                <div class="summary-value">
                    <?= number_format($added) ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Updated
                </div>

                <div class="summary-value">
                    <?= number_format($updated) ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Skipped
                </div>

                <div class="summary-value">
                    <?= number_format($skipped) ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Errors
                </div>

                <div class="summary-value">
                    <?= number_format(count($errors)) ?>
                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         INSTRUCTIONS
         ===================================================== -->

    <div class="card">

        <div class="card-title">
            CSV Format
        </div>

        <div class="card-description">

    Your CSV must contain the required student columns.
    Email, parent email, QR valid from, and QR valid until
    are optional.

</div>


        <div class="instructions">

            <div class="instruction-item">

                <span class="instruction-number">
                    1
                </span>

                <span class="instruction-title">
                    Required Columns
                </span>

                <div class="instruction-text">

                    student_id,
                    full_name,
                    program,
                    year_level,
                    section

                </div>

            </div>


            <div class="instruction-item">

                <span class="instruction-number">
                    2
                </span>

                <span class="instruction-title">
                    Optional Columns
                </span>

                <div class="instruction-text">

                email,
                parent_email,
                qr_valid_from,
                qr_valid_until

</div>

            </div>


            <div class="instruction-item">

                <span class="instruction-number">
                    3
                </span>

                <span class="instruction-title">
                    QR Code
                </span>

                <div class="instruction-text">

                    New students automatically receive a
                    QR code value equal to their Student ID.

                </div>

            </div>


            <div class="instruction-item">

                <span class="instruction-number">
                    4
                </span>

                <span class="instruction-title">
                    Existing Students
                </span>

                <div class="instruction-text">

                    Existing records are updated instead of
                    creating duplicate students.

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         UPLOAD
         ===================================================== -->

    <div class="card">

        <div class="card-title">
            Upload MIS Student CSV
        </div>

        <div class="card-description">

            Maximum file size:
            <strong>5 MB</strong>.

        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
            class="upload-form"
        >

            <?= smartgate_csrf_field() ?>

            <input
                type="file"
                name="csv_file"
                class="file-input"
                accept=".csv,text/csv"
                required
            >


            <button
                type="submit"
                name="preview_csv"
                value="1"
                class="btn btn-secondary"
            >
                Preview CSV
            </button>


            <button
                type="submit"
                name="import_csv"
                value="1"
                class="btn btn-primary"
                onclick="
                    return confirm(
                        'Are you sure you want to import this CSV file?'
                    );
                "
            >
                Import Students
            </button>

        </form>

    </div>


    <!-- =====================================================
         ERRORS
         ===================================================== -->

    <?php if (count($errors) > 0): ?>

        <div class="card">

            <div class="card-title">
                Import Messages
            </div>

            <ul class="error-list">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= h($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         CSV PREVIEW
         ===================================================== -->

    <?php if ($show_preview): ?>

        <div class="table-card">

            <div class="table-header">

                <div>

                    <div class="table-title">
                        CSV Preview
                    </div>

                    <div class="table-subtitle">

                        Showing up to
                        <?= number_format(count($preview_rows)) ?>
                        records from the uploaded file.

                    </div>

                </div>


                <span class="badge badge-ready">
                    READY FOR REVIEW
                </span>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Row
                            </th>

                            <th>
                                Student ID
                            </th>

                            <th>
                                Full Name
                            </th>

                            <th>
                                Program
                            </th>

                            <th>
                                Year
                            </th>

                            <th>
                                Section
                            </th>

                            <th>
                                Student Email
                            </th>

                            <th>
                                Parent Email
                            </th>

                            <th>
                                QR Valid From
                            </th>

                            <th>
                                QR Valid Until
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $preview_rows
                        as $preview
                    ): ?>

                        <tr>

                            <td>
                                <?= h($preview['row']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= h(
                                        $preview['student_id']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= h(
                                    $preview['full_name']
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $preview['program']
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $preview['year_level']
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $preview['section']
                                ) ?>
                            </td>

                            <td>
                                <?= $preview['email']
                                    ? h(
                                        $preview['email']
                                    )
                                    : '—'
                                ?>
                            </td>

                            <td>
                                <?= $preview['parent_email']
                                    ? h(
                                        $preview['parent_email']
                                    )
                                    : '—'
                                ?>
                            </td>

                            <td>
                                <?= $preview['qr_valid_from']
                                    ? h($preview['qr_valid_from'])
                                    : '—'
                                ?>
                            </td>

                            <td>
                                <?= $preview['qr_valid_until']
                                    ? h($preview['qr_valid_until'])
                                    : '—'
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SAMPLE CSV
         ===================================================== -->

    <div class="card">

        <div class="card-title">
            Example CSV Structure
        </div>

        <div class="card-description">

            Your CSV should follow this column order or contain
            these column names.

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            student_id
                        </th>

                        <th>
                            full_name
                        </th>

                        <th>
                            program
                        </th>

                        <th>
                            year_level
                        </th>

                        <th>
                            section
                        </th>

                        <th>
                            email
                        </th>

                        <th>
                            parent_email
                        </th>

                        <th>
                            qr_valid_from
                        </th>

                        <th>
                            qr_valid_until
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <tr>

                        <td>
                            3031-0001
                        </td>

                        <td>
                            Keifer Watson
                        </td>

                        <td>
                            BSIT
                        </td>

                        <td>
                            1
                        </td>

                        <td>
                            1A
                        </td>

                        <td>
                            student@example.com
                        </td>

                        <td>
                            parent@example.com
                        </td>

                        <td>
                            2026-09-12 08:00:00
                        </td>

                        <td>
                            2026-12-31 23:59:59
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


</div>

</div>

</body>

</html>