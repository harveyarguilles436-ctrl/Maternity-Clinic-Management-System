<?php
ob_start();
session_start();
include 'config.php';
include 'mail_config.php';
include 'SimpleEmail.php';

$error = "";
$success = "";
$step = 1; // 1=Identify, 2=Verify OTP, 3=Reset Password

// Check Session State to determine Step
if (isset($_SESSION['reset_stage'])) {
    if ($_SESSION['reset_stage'] === 'otp') $step = 2;
    if ($_SESSION['reset_stage'] === 'password') $step = 3;
}

// HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- STAGE 1: VERIFY IDENTITY ---
    if (isset($_POST['verify_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);

        // Check if username and email match a record
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($uid);
            $stmt->fetch();
            
            // Generate OTP
            $otp = rand(100000, 999999);
            
            // Send Email
            $subject = "Password Reset Request - Mother Therese Clinic";
            $message = "
            <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f6f8;'>
                <div style='max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    <h2 style='color: #00897b; text-align: center;'>Reset Password</h2>
                    <p style='color: #555; text-align: center;'>You requested a password reset. Use this code to proceed:</p>
                    <div style='background: #e0f2f1; color: #00695c; font-size: 32px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 20px 0; letter-spacing: 5px;'>
                        $otp
                    </div>
                    <p style='color: #999; font-size: 12px; text-align: center;'>Ignore this if you did not request a reset.</p>
                </div>
            </div>";

            $mail = new SimpleEmail(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME);
            
            if ($mail->send($email, $subject, $message)) {
                $_SESSION['reset_stage'] = 'otp';
                $_SESSION['reset_user_id'] = $uid;
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = $otp;
                header("Location: forgot_password.php");
                exit;
            } else {
                $error = "Failed to send OTP. Check internet connection.";
            }

        } else {
            $error = "No account found matching that Username and Email.";
        }
    }

    // --- STAGE 2: VERIFY OTP ---
    if (isset($_POST['verify_otp'])) {
        $user_otp = trim($_POST['otp_code']);
        if ($user_otp == $_SESSION['reset_otp']) {
            $_SESSION['reset_stage'] = 'password';
            header("Location: forgot_password.php");
            exit;
        } else {
            $error = "Invalid OTP Code.";
        }
    }

    // --- STAGE 3: RESET PASSWORD ---
    if (isset($_POST['reset_password'])) {
        $verified_user_id = $_SESSION['reset_user_id'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (strlen($new_pass) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match.";
        } else {
            // Update Password
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hash, $verified_user_id);
            
            if ($stmt->execute()) {
                // Clear Session
                unset($_SESSION['reset_stage']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);
                
                header("Location: login.php?success=reset");
                exit;
            } else {
                $error = "Database error. Please try again.";
            }
        }
    }

    // RESEND OTP
    if (isset($_POST['resend_otp'])) {
        $otp = rand(100000, 999999);
        $email = $_SESSION['reset_email'];
        $_SESSION['reset_otp'] = $otp;

        $message = "Your new password reset code is: $otp";
        $mail = new SimpleEmail(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME);
        $mail->send($email, "Resent Code", $message);
        $success = "New code sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{font-family:'Manrope',sans-serif;}</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white max-w-md w-full rounded-2xl shadow-xl border border-slate-100 p-8">
        
        <div class="mb-6">
            <a href="login.php" class="text-sm font-semibold text-slate-500 hover:text-teal-700 flex items-center gap-1 transition-colors">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>

        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-teal-50 text-teal-700 rounded-xl flex items-center justify-center text-2xl mx-auto mb-3">
                <?php if($step==1) echo '<i class="fas fa-search"></i>'; ?>
                <?php if($step==2) echo '<i class="fas fa-envelope-open-text"></i>'; ?>
                <?php if($step==3) echo '<i class="fas fa-lock"></i>'; ?>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">
                <?php 
                if($step==1) echo "Forgot Password?";
                if($step==2) echo "Verify Your Identity";
                if($step==3) echo "Set New Password";
                ?>
            </h1>
            <p class="text-slate-500 text-sm">
                <?php 
                if($step==1) echo "Enter your details to find your account.";
                if($step==2) echo "Check your email for the verification code.";
                if($step==3) echo "Create a strong new password.";
                ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-bold mb-6 flex items-center gap-2 border border-red-100">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-50 text-green-600 p-3 rounded-lg text-sm font-bold mb-6 flex items-center gap-2 border border-green-100">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- STEP 1: FIND ACCOUNT -->
        <?php if ($step === 1): ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Username</label>
                <input type="text" name="username" required placeholder="Enter your username" 
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-teal-600 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Registered Gmail</label>
                <input type="email" name="email" required placeholder="example@gmail.com" 
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-teal-600 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
            </div>

            <button type="submit" name="verify_user" class="w-full py-3 bg-teal-700 hover:bg-teal-800 text-white font-bold rounded-xl shadow-lg shadow-teal-700/20 transition-all mt-2">
                Send Reset Code
            </button>
        </form>
        <?php endif; ?>

        <!-- STEP 2: VERIFY OTP -->
        <?php if ($step === 2): ?>
        <form method="POST" class="space-y-4">
            <p class="text-center text-sm text-slate-600 bg-slate-50 p-2 rounded">
                Code sent to: <strong><?php echo $_SESSION['reset_email']; ?></strong>
            </p>
            <div>
                <input type="text" name="otp_code" maxlength="6" required placeholder="000000" 
                       class="w-full text-center text-3xl tracking-[0.3em] font-bold py-3 border border-slate-200 rounded-xl focus:border-teal-600 focus:ring-2 focus:ring-teal-100 outline-none transition-all uppercase pattern=[0-9]*"
                       autocomplete="off">
            </div>

            <button type="submit" name="verify_otp" class="w-full py-3 bg-teal-700 hover:bg-teal-800 text-white font-bold rounded-xl shadow-lg shadow-teal-700/20 transition-all mt-2">
                Verify Code
            </button>
        </form>
        <form method="POST" class="mt-4 text-center">
             <button type="submit" name="resend_otp" class="text-sm font-semibold text-teal-600 hover:underline">Resend Code</button>
        </form>
        <?php endif; ?>

        <!-- STEP 3: NEW PASSWORD -->
        <?php if ($step === 3): ?>
        <form method="POST" class="space-y-4">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">New Password</label>
                <div class="relative">
                    <input type="password" id="newPass" name="new_password" required placeholder="Min 8 characters" 
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-teal-600 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                    <i class="fas fa-eye absolute right-4 top-3.5 text-slate-400 cursor-pointer hover:text-teal-700" onclick="toggle('newPass')"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Confirm Password</label>
                <div class="relative">
                    <input type="password" id="confPass" name="confirm_password" required placeholder="Repeat password" 
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-teal-600 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                    <i class="fas fa-eye absolute right-4 top-3.5 text-slate-400 cursor-pointer hover:text-teal-700" onclick="toggle('confPass')"></i>
                </div>
            </div>

            <button type="submit" name="reset_password" class="w-full py-3 bg-teal-700 hover:bg-teal-800 text-white font-bold rounded-xl shadow-lg shadow-teal-700/20 transition-all mt-2">
                Update Password
            </button>
        </form>
        <?php endif; ?>

    </div>

    <script>
        function toggle(id) {
            let input = document.getElementById(id);
            if (input.type === "password") input.type = "text";
            else input.type = "password";
        }
    </script>

</body>
</html>