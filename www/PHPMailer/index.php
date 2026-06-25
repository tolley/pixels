<?php

require_once 'Exception.php';
require_once 'PHPMailer.php';
require_once 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail(string $to, string $username, string $token): bool {
    $env = parse_ini_file( __DIR__ . '/../.env', false, INI_SCANNER_RAW );

    $gmailHost     = $env['GMAIL_HOST'];
    $gmailUsername = $env['GMAIL_USERNAME'];
    $gmailPassword = $env['GMAIL_PASSWORD'];
    $appUrl        = $env['APP_URL'];
    $appEmail      = $env['APP_EMAIL'];

    $url    = "$appUrl/auth.php?action=verify&token=$token";

    //Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        //Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;           // Enable verbose debug output
        $mail->isSMTP();                                 // Send using SMTP
        $mail->Host       = $gmailHost;                  // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                        // Enable SMTP authentication
        $mail->Username   = $gmailUsername;              // SMTP username
        $mail->Password   = $gmailPassword;              // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
        $mail->Port       = 465;                         // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        $mail->addReplyTo( $appEmail, 'Reply to ' . $appEmail );
        $mail->SetFrom($appEmail, $appEmail);

        $mail->From = $appEmail;

        $mail->addAddress( $to );

        //Content
        $mail->isHTML( true ); // Set email format to HTML
        $mail->Subject = 'Verify your email address';
        $mail->Body    = "Hi $username,\n\nVerify your email:\n\n$url\n\nExpires in 24 hours.";
        // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }

}


