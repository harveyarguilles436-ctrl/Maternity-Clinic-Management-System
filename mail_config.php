<?php
// EMAIL CONFIGURATION
// You need to generate an App Password for your Gmail.
// 1. Go to Google Account > Security > 2-Step Verification > Enable it.
// 2. Go to Search and type "App Passwords".
// 3. Create a new App Password (name it "Clinic App").
// 4. Copy the 16-character password and paste it below.

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_USER', 'arguillesharvey29@gmail.com'); // <--- REPLACE THIS
define('SMTP_PASS', 'ejitgpasydtwvtto');  // <--- REPLACE THIS WITH YOUR APP PASSWORD
define('SMTP_FROM_NAME', 'Mother Therese Clinic');
?>
