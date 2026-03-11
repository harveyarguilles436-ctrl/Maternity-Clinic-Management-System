<?php
include 'config.php'; // For timezone
include 'mail_config.php';
include 'SimpleEmail.php';

echo "<h1>Email Configuration Test</h1>";
echo "<p>Testing configuration in <strong>mail_config.php</strong>...</p>";

if (SMTP_USER === 'your_email@gmail.com') {
    die("<h3 style='color:red'>ERROR: You have not configured mail_config.php yet!</h3><p>Please open mail_config.php and add your Gmail address and App Password.</p>");
}

$mail = new SimpleEmail(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME);
$subject = "Test Email from Clinic System";
$message = "<h1>Success!</h1><p>Your email configuration is working correctly.</p>";

echo "<p>Attempting to send test email to: " . SMTP_USER . " ...</p>";

if($mail->send(SMTP_USER, $subject, $message)) {
    echo "<h2 style='color:green'>SUCCESS! Email Sent.</h2>";
    echo "<p>Check your inbox (" . SMTP_USER . ") for the test message.</p>";
} else {
    echo "<h2 style='color:red'>FAILED to send email.</h2>";
    echo "<div style='background:#eee; padding:10px; border:1px solid #ccc; font-family:monospace; margin-bottom:20px;'>";
    echo "<strong>Debug Log:</strong><br>" . $mail->debugLog;
    echo "</div>";
    
    echo "<p><strong>Troubleshooting Guide:</strong></p>";
    echo "<ul>
        <li><strong>Auth Failed?</strong> You are likely using your normal password. Google requires an <a href='https://support.google.com/accounts/answer/185833' target='_blank'>App Password</a>.</li>
        <li><strong>TLS Failed?</strong> Enable <code>extension=openssl</code> in your php.ini.</li>
        <li><strong>Timeout?</strong> Your firewall might be blocking Port 587.</li>
    </ul>";
}
?>
