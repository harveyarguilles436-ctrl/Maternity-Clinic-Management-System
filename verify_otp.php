<?php
session_start();
include 'config.php';
include 'mail_config.php';
include 'SimpleEmail.php';

$error = "";
$success = "";

// Redirect if no registration data
if (!isset($_SESSION['registration']) || !isset($_SESSION['otp'])) {
    header("Location: register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $user_otp = trim($_POST['otp_code']);

        if ($user_otp == $_SESSION['otp']) {
            // OTP Verified - Proceed to Registration
            $reg_data = $_SESSION['registration'];
            
            $name = $reg_data['name'];
            $email = $reg_data['email'];
            $username = $reg_data['username'];
            $role = 'Client';
            $contact = $reg_data['contact'];

            // Hash Password
            $hash = password_hash($reg_data['password'], PASSWORD_DEFAULT);

            // Insert User
            $stmt = $conn->prepare("INSERT INTO users (name, email, username, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $username, $hash, $role);

            if ($stmt->execute()) {
                $uid = $stmt->insert_id;

                // Create Patient Profile
                $stmt2 = $conn->prepare("INSERT INTO patient (user_id, name, contact_no) VALUES (?, ?, ?)");
                $stmt2->bind_param("iss", $uid, $name, $contact);

                if ($stmt2->execute()) {
                    // Clear Session
                    unset($_SESSION['registration']);
                    unset($_SESSION['otp']);
                    
                    header("Location: login.php?success=registered");
                    exit;
                } else {
                    $error = "Error creating patient data. Please contact admin.";
                }
            } else {
                $error = "Database Error: " . $conn->error;
            }
        } else {
            $error = "Invalid OTP. Please try again.";
        }
    }
    
    if (isset($_POST['resend_otp'])) {
        // Resend Logic
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $email = $_SESSION['registration']['email'];
        
        $subject = "Your Verification Code (Resent)";
        $message = "
        <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f6f8;'>
            <div style='max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <h2 style='color: #00897b; text-align: center;'>New Code Request</h2>
                <div style='background: #e0f2f1; color: #00695c; font-size: 32px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 20px 0; letter-spacing: 5px;'>
                    $otp
                </div>
            </div>
        </div>";
        
        $mail = new SimpleEmail(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME);

        if($mail->send($email, $subject, $message)) {
             $success = "New code sent to your email.";
        } else {
             $error = "Failed to send email. Check configuration.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: "#00897b", 
                    "primary-hover": "#00695c",
                    "background-light": "#f4f6f8",
                    "text-main": "#37474f",
                    "text-secondary": "#607d8b",
                },
                fontFamily: { display: ["Manrope", "sans-serif"] },
                boxShadow: { soft: "0 4px 20px -2px rgba(0,137,123,0.15)" }
            }
        }
    }
    </script>
    <style> body { font-family: 'Manrope', sans-serif; } </style>
</head>
<body class="bg-background-light min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Background Decor -->
    <div class="absolute -top-20 -left-20 w-72 h-72 bg-primary/10 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-20 -right-20 w-72 h-72 bg-blue-400/10 rounded-full blur-3xl"></div>

    <div class="bg-white max-w-[400px] w-full rounded-2xl shadow-soft border border-slate-100 p-8 text-center relative z-10">
        
        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-3xl">mark_email_read</span>
        </div>

        <h1 class="text-xl font-bold text-text-main mb-2">Verify Your Email</h1>
        <p class="text-text-secondary text-sm mb-6">
            We sent a 6-digit code to <br> 
            <span class="font-bold text-text-main"><?php echo htmlspecialchars($_SESSION['registration']['email']); ?></span>
        </p>

        <?php if ($error): ?>
            <div class="bg-rose-50 text-rose-500 p-3 rounded-lg text-xs font-bold mb-4 flex items-center justify-center gap-2 border border-rose-100">
                <span class="material-symbols-outlined text-sm">error</span> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-50 text-emerald-600 p-3 rounded-lg text-xs font-bold mb-4 flex items-center justify-center gap-2 border border-emerald-100">
                <span class="material-symbols-outlined text-sm">check_circle</span> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <input type="text" name="otp_code" maxlength="6" placeholder="000000" 
                       class="w-full text-center text-3xl tracking-[0.5em] font-bold py-3 border border-slate-200 rounded-xl focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all text-text-main placeholder-slate-200"
                       required pattern="[0-9]{6}" autocomplete="off">
            </div>

            <button type="submit" name="verify_otp" class="w-full py-3 bg-primary hover:bg-primary-hover text-white font-bold rounded-xl shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all hover:-translate-y-0.5">
                Verify & Create Account
            </button>
        </form>

        <form method="POST" class="mt-6">
            <p class="text-xs text-text-secondary">Didn't receive the code?</p>
            <button type="submit" name="resend_otp" class="text-sm font-bold text-primary hover:text-primary-hover mt-1 bg-transparent border-none cursor-pointer hover:underline transition-colors">
                Resend Code
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-100">
            <a href="register.php" class="flex items-center justify-center gap-1 text-xs text-text-secondary hover:text-text-main transition-colors">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Registration
            </a>
            <p class="text-[10px] text-slate-300 mt-4">System v2.1 (SMTP)</p>
        </div>

    </div>

</body>
</html>
