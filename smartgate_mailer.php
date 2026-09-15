<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";


/*
|--------------------------------------------------------------------------
| SMTP CONFIGURATION
|--------------------------------------------------------------------------
*/

function smartgate_smtp_config(): array
{
    $fileConfig = [];
    $configFile = __DIR__ . "/mail_config.php";

    if (is_file($configFile)) {
        $loaded = require $configFile;

        if (is_array($loaded)) {
            $fileConfig = $loaded;
        }
    }

    $env = static function (
        string $name,
        string $fallback = ''
    ): string {

        $value = getenv($name);

        return $value === false
            ? $fallback
            : trim((string)$value);
    };


    $username = $env(
        'SMARTGATE_SMTP_USERNAME',
        (string)(
            $fileConfig['smtp_username']
            ?? $fileConfig['username']
            ?? ''
        )
    );


    return [

        'host' => $env(
            'SMARTGATE_SMTP_HOST',
            (string)(
                $fileConfig['smtp_host']
                ?? 'smtp.gmail.com'
            )
        ),

        'port' => (int)$env(
            'SMARTGATE_SMTP_PORT',
            (string)(
                $fileConfig['smtp_port']
                ?? '587'
            )
        ),

        'username' => $username,

        'password' => $env(
            'SMARTGATE_SMTP_PASSWORD',
            (string)(
                $fileConfig['smtp_password']
                ?? $fileConfig['password']
                ?? ''
            )
        ),

        'encryption' => strtolower(
            $env(
                'SMARTGATE_SMTP_ENCRYPTION',
                (string)(
                    $fileConfig['smtp_encryption']
                    ?? 'tls'
                )
            )
        ),

        'from_email' => $env(
            'SMARTGATE_SMTP_FROM_EMAIL',
            (string)(
                $fileConfig['from_email']
                ?? $username
            )
        ),

        'from_name' => $env(
            'SMARTGATE_SMTP_FROM_NAME',
            (string)(
                $fileConfig['from_name']
                ?? 'SmartGate'
            )
        )
    ];
}


/*
|--------------------------------------------------------------------------
| SEND SMTP EMAIL
|--------------------------------------------------------------------------
*/

function smartgate_send_smtp_email(
    string $recipient,
    string $recipientName,
    string $subject,
    string $htmlBody,
    string $textBody
): bool {

    if (!filter_var(
        $recipient,
        FILTER_VALIDATE_EMAIL
    )) {

        error_log(
            'SmartGate Email Error: Invalid recipient email.'
        );

        return false;
    }


    $config = smartgate_smtp_config();


    if (
        $config['host'] === '' ||
        $config['port'] <= 0 ||
        $config['username'] === '' ||
        $config['password'] === '' ||
        !filter_var(
            $config['from_email'],
            FILTER_VALIDATE_EMAIL
        )
    ) {

        error_log(
            'SmartGate Email Error: SMTP configuration is incomplete.'
        );

        return false;
    }


    $mail = new PHPMailer(true);


    try {

        $mail->isSMTP();

        $mail->SMTPDebug = 2;
$mail->Debugoutput = function($str, $level) {
    error_log("PHPMailer DEBUG: " . trim($str));
};

        $mail->Host =
            $config['host'];

        $mail->SMTPAuth =
            true;

        $mail->Username =
            $config['username'];

        $mail->Password =
            $config['password'];

        $mail->Port =
            $config['port'];

        $mail->Timeout =
            12;

        $mail->SMTPConnectTimeout =
            8;

        $mail->CharSet =
            'UTF-8';


        /*
        |--------------------------------------------------------------------------
        | ENCRYPTION
        |--------------------------------------------------------------------------
        */

        if (
            $config['encryption'] === 'ssl' ||
            $config['encryption'] === 'smtps'
        ) {

            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_SMTPS;

        } else {

            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;
        }


        /*
        |--------------------------------------------------------------------------
        | EMAIL
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $mail->addAddress(
            $recipient,
            $recipientName
        );

        $mail->isHTML(true);

        $mail->Subject =
            $subject;

        $mail->Body =
            $htmlBody;

        $mail->AltBody =
            $textBody;


        /*
        |--------------------------------------------------------------------------
        | SEND
        |--------------------------------------------------------------------------
        */

        $mail->send();

        return true;


    } catch (PHPMailerException $e) {

        /*
        |--------------------------------------------------------------------------
        | SHOW ACTUAL SMTP ERROR
        |--------------------------------------------------------------------------
        */

        error_log(
            'SmartGate Email Error: ' .
            $mail->ErrorInfo
        );

        error_log(
            'SmartGate PHPMailer Exception: ' .
            $e->getMessage()
        );

        return false;


    } catch (Throwable $e) {

        error_log(
            'SmartGate Email Error: ' .
            $e->getMessage()
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| SMARTGATE PARENT NOTIFICATION
|--------------------------------------------------------------------------
*/

function sendSmartGateEmail(
    string $recipient,
    string $studentName,
    string $studentId,
    string $direction,
    string $scanTime
): bool {


    /*
    |--------------------------------------------------------------------------
    | PHILIPPINE DATE
    |--------------------------------------------------------------------------
    */

    date_default_timezone_set(
        'Asia/Manila'
    );


    /*
    |--------------------------------------------------------------------------
    | DATE ONLY
    |--------------------------------------------------------------------------
    |
    | Regardless of what timestamp is received,
    | the email displays DATE ONLY.
    |
    */

    $dateOnly = date(
        "F j, Y",
        strtotime($scanTime)
    );


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    */

    $safeStudentId =
        htmlspecialchars(
            $studentId,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeStudentName =
        htmlspecialchars(
            $studentName,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeDirection =
        htmlspecialchars(
            $direction,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeDate =
        htmlspecialchars(
            $dateOnly,
            ENT_QUOTES,
            'UTF-8'
        );


    /*
    |--------------------------------------------------------------------------
    | HTML EMAIL
    |--------------------------------------------------------------------------
    */

    $htmlBody = "

        <div
            style='
                font-family:Arial,sans-serif;
                max-width:600px;
                margin:auto;
                padding:20px;
            '
        >

            <h2>
                SmartGate Notification
            </h2>

            <p>
                Dear Parent/Guardian,
            </p>

            <p>
                Your child has successfully
                passed through the SmartGate system.
            </p>

            <p>
                <strong>Student ID:</strong>
                {$safeStudentId}
            </p>

            <p>
                <strong>Student Name:</strong>
                {$safeStudentName}
            </p>

            <p>
                <strong>Direction:</strong>
                {$safeDirection}
            </p>

            <p>
                <strong>Date:</strong>
                {$safeDate}
            </p>

            <p>
                <strong>SmartGate Status:</strong>
                Access Granted
            </p>

        </div>

    ";


    /*
    |--------------------------------------------------------------------------
    | PLAIN TEXT EMAIL
    |--------------------------------------------------------------------------
    */

    $textBody =
        "SmartGate Notification\n\n" .

        "Student ID: " .
        $studentId .
        "\n" .

        "Student Name: " .
        $studentName .
        "\n" .

        "Direction: " .
        $direction .
        "\n" .

        "Date: " .
        $dateOnly .
        "\n\n" .

        "SmartGate Status: Access Granted";


    /*
    |--------------------------------------------------------------------------
    | SEND
    |--------------------------------------------------------------------------
    */

    return smartgate_send_smtp_email(
        $recipient,
        'Parent/Guardian',
        'SmartGate Student ' .
        $direction .
        ' Notification',
        $htmlBody,
        $textBody
    );
}

?>