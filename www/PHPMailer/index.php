<?php

require_once 'Exception.php';
require_once 'PHPMailer.php';
require_once 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Creates a basic email object using the env variables and returns it.
 * 
 * @return  object  PHPMailer object configured to send an email
 */
function createEmailObject(): object {
    $env = parse_ini_file( __DIR__ . '/../.env', false, INI_SCANNER_RAW );

    $gmailHost     = $env['GMAIL_HOST'];
    $gmailUsername = $env['GMAIL_USERNAME'];
    $gmailPassword = $env['GMAIL_PASSWORD'];
    $appUrl        = $env['APP_URL'];
    $appEmail      = $env['APP_EMAIL'];

    //Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);
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

    return $mail;
}

/**
 * Sends the user an email in order to verify their account
 * @param string to: The user's email address
 * @param string username: The user's choosen name
 * @param string token: The verification token that we generated
 * @return bool True if the email was sent successfully, false otherwise
 */
function sendVerificationEmail(string $to, string $username, string $token): bool {
    $env = parse_ini_file( __DIR__ . '/../.env', false, INI_SCANNER_RAW );

    try {
        //Create an instance; passing `true` enables exceptions
        $mail = createEmailObject();

        $mail->addAddress( $to );

        $appUrl = rtrim( $env['APP_URL'] ?: 'https://pixels.tolleycoder.com', '/');
        $url    = "$appUrl/auth?action=verify&token=$token";

        //Content
        $mail->isHTML( true ); // Set email format to HTML
        $mail->Subject = 'Verify your email address';
        $mail->Body    = "<span>
                            <h1>Hi $username!</h1>
                            Please verify your email: <a href='$url'>$appUrl/auth</a>
                            <br />

                            <span>Expires in 24 hours.</span>
                        </span>";
        // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }

}

/**
 * Sends a user an email containing weather or not the pixels they submitted
 * where approved or not.
 * @param   string email: The user's email address
 * @return  void
 */
function sendPixelReviewEmail( string $to, string $username, string $status ): void {
    $appUrl   = rtrim(getenv('APP_URL') ?: 'https://pixels.tolleycoder.com', '/');
    $imageUrl = $appUrl . '/image.php';
    $approved = $status === 'approved';
    $subject  = $approved ? 'Your pixels have been approved!' : 'Your pixels were not approved';

    $headingColor = $approved ? '#2e7d32' : '#c62828';
    $heading      = $approved ? 'Pixels Approved!' : 'Pixel Submission Update';
    $safeUser     = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeUrl      = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');

    $statusLine = $approved
        ? 'Great news! Your pixel submission has been <strong>approved</strong> and your colors are now live on the canvas!'
        : 'Unfortunately your pixel submission was <strong>not approved</strong> this time. Feel free to try again!';

    $body  = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>';
    $body .= '<body style="font-family:sans-serif;max-width:560px;margin:40px auto;color:#333">';
    $body .= '<h2 style="color:' . $headingColor . '">' . $heading . '</h2>';
    $body .= '<p>Hi ' . $safeUser . ',</p>';
    $body .= '<p>' . $statusLine . '</p>';
    $body .= '<p><a href="' . $safeUrl . '" style="display:inline-block;padding:10px 20px;background:#1565c0;color:#fff;border-radius:4px;text-decoration:none">View the Canvas</a></p>';
    $body .= '<p>Thank you so much for contributing and helping make this experiment look amazing &mdash; every pixel counts!</p>';
    $body .= '<p style="color:#888;font-size:0.85em">&mdash; The Pixel Playground Team</p>';
    $body .= '</body></html>';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Pixel Playground <noreply@pixelplayground.local>\r\n";

    try {
        $mail = createEmailObject();

        $mail->addAddress( $to );

        //Content
        $mail->isHTML( true ); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = 'Thank you for contributing!  You can see it at http://pixels.tolleycoder.com/image';

        $mail->send();
    } catch( Exception $e ) {
        // Ignore this exception, for now....
    }
}

