<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";

date_default_timezone_set("Asia/Manila");

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();

    // DEBUG
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = 'html';

    // GMAIL SMTP
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // PUT YOUR GMAIL ADDRESS HERE
    $mail->Username = 'laderastristan@gmail.com';

    // PUT YOUR 16-DIGIT APP PASSWORD HERE
    $mail->Password = 'atymfbvlvvyxscsn';

    // Gmail STARTTLS
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->Timeout = 30;
    $mail->SMTPConnectTimeout = 15;

    $mail->CharSet = 'UTF-8';

    // Sender MUST be the authenticated Gmail account
    $mail->setFrom(
        'laderastristan@gmail.com',
        'SmartGate'
    );

    // TEMPORARY TEST RECIPIENT
    $mail->addAddress(
        'smartgatedemo1@gmail.com',
    );

    $mail->isHTML(true);

    $mail->Subject =
        'SmartGate Gmail SMTP Test';

    $mail->Body = '
        <h2>SmartGate Email Test</h2>
        <p>If you received this email, Gmail SMTP is working.</p>
        <p>Date: ' . date('F j, Y') . '</p>
    ';

    $mail->AltBody =
        "SmartGate Gmail SMTP Test\n\n" .
        "If you received this email, Gmail SMTP is working.\n" .
        "Date: " . date('F j, Y');

    $mail->send();

    echo "<h2>SUCCESS</h2>";
    echo "<p>Email was sent successfully.</p>";

} catch (Exception $e) {

    echo "<h2>FAILED</h2>";

    echo "<pre>";
    echo "PHPMailer Error:\n";
    echo htmlspecialchars($mail->ErrorInfo);
    echo "\n\nException:\n";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>";
}