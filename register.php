<?php
session_start();
include 'config.php';
include 'mail_config.php';
include 'SimpleEmail.php';

$error = "";
$success = "";

// Initialize variables to keep data in forms if error occurs
$name = "";
$contact = "";
$username = "";
$email = "";

if (isset($_POST['register'])) {
    // Sanitize inputs
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'Client';

    // --- STRONG VALIDATIONS ---
    
    // 1. Check for empty fields
    if (empty($name) || empty($contact) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } 
    // 2. Validate Name: Allow only Letters, Spaces, and Dots
    elseif (!preg_match("/^[a-zA-Z\s\.]+$/", $name)) {
        $error = "Invalid Name. Numbers and symbols are not allowed.";
    }
    // 3. Validate Contact: Must be 11 digits
    elseif (!preg_match("/^[0-9]{11}$/", $contact)) {
        $error = "Contact must be exactly 11 digits.";
    }
    // 4. Validate Email (Standard Format)
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please use a valid email address.";
    }
    // 4.1 Enforce @gmail.com (Prevents typos like .co)
    elseif (substr($email, -10) !== '@gmail.com') {
        $error = "Only @gmail.com addresses are allowed.";
    }
    // 5. Validate Password Length
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } 
    // 6. Validate Password Confirmation
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    else {
        // Check for duplicate username OR email
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username or Email is already taken.";
        } else {
            // --- OTP FLOW START ---
            
            // 1. Generate OTP
            $otp = rand(100000, 999999);
            
            // 2. Store Data in Session (Temporary)
            $_SESSION['registration'] = [
                'name' => $name,
                'contact' => $contact,
                'username' => $username,
                'email' => $email,
                'password' => $password
            ];
            $_SESSION['otp'] = $otp;

            // 3. Send Email using SMTP
            $subject = "Verify Your Account - Mother Therese Clinic";
            $message = "
            <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f6f8;'>
                <div style='max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    <h2 style='color: #00897b; text-align: center;'>Verify Your Email</h2>
                    <p style='color: #555; font-size: 16px; text-align: center;'>Use the code below to complete your registration.</p>
                    <div style='background: #e0f2f1; color: #00695c; font-size: 32px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 20px 0; letter-spacing: 5px;'>
                        $otp
                    </div>
                    <p style='color: #999; font-size: 12px; text-align: center;'>If you didn't request this, ignore this email.</p>
                </div>
            </div>";
            
            $mail = new SimpleEmail(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME);
            
            if($mail->send($email, $subject, $message)) {
                header("Location: verify_otp.php");
                exit;
            } else {
                $error = "Failed to send OTP. Please check your internet or email configuration in mail_config.php.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Patient Registration</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
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
<style> body { font-family: Manrope, sans-serif; } .input-transition { transition: all .2s ease; } </style>
</head>
<body class="bg-background-light min-h-screen flex items-center justify-center relative overflow-hidden py-10">
    <div class="absolute -top-20 -left-20 w-72 h-72 bg-primary/10 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-20 -right-20 w-72 h-72 bg-blue-400/10 rounded-full blur-3xl"></div>

    <main class="w-full max-w-[420px] bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden relative z-10 my-4">
        
        <div class="px-6 pt-5 pb-2">
            <a href="login.php" class="flex items-center text-xs font-semibold text-text-secondary hover:text-primary transition-colors mb-2">
                <span class="material-symbols-outlined text-sm mr-1">arrow_back</span> Back to Login
            </a>
            <div class="text-center">
                <div class="mx-auto mb-2 w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-2xl">person_add</span>
                </div>
                <h1 class="text-xl font-bold text-text-main">Create Account</h1>
            </div>
        </div>

        <form method="POST" class="px-6 pb-6 flex flex-col gap-3">
            
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">badge</span>
                <input name="name" type="text" required placeholder="Full Name" value="<?php echo htmlspecialchars($name); ?>" class="input-transition w-full h-10 pl-10 pr-4 border border-slate-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/10 text-sm">
            </div>

            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">phone_iphone</span>
                <input name="contact" type="text" required placeholder="Mobile (11 Digits)" maxlength="11" value="<?php echo htmlspecialchars($contact); ?>" class="input-transition w-full h-10 pl-10 pr-4 border border-slate-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/10 text-sm">
            </div>

            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">mail</span>
                <input name="email" type="email" required placeholder="Email Address (@gmail.com)" value="<?php echo htmlspecialchars($email); ?>" class="input-transition w-full h-10 pl-10 pr-4 border border-slate-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/10 text-sm">
            </div>

            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">person</span>
                <input name="username" type="text" required placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" class="input-transition w-full h-10 pl-10 pr-4 border border-slate-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/10 text-sm">
            </div>

            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">lock</span>
                <input name="password" id="regPass" type="password" required placeholder="Password (Min 8 chars)" class="input-transition w-full h-10 pl-10 pr-10 border border-slate-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/10 text-sm">
                <button type="button" onclick="togglePassword('regPass', 'eyeIcon1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-secondary hover:text-primary transition-colors focus:outline-none">
                    <span class="material-symbols-outlined text-lg select-none" id="eyeIcon1">visibility</span>
                </button>
            </div>

            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-lg">lock_reset</span>
                <input name="confirm_password" id="confPass" type="password" required placeholder="Confirm Password" class="input-transition w-full h-10 pl-10 pr-10 border border-slate-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/10 text-sm">
                <button type="button" onclick="togglePassword('confPass', 'eyeIcon2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-secondary hover:text-primary transition-colors focus:outline-none">
                    <span class="material-symbols-outlined text-lg select-none" id="eyeIcon2">visibility</span>
                </button>
            </div>

            <?php if ($error): ?>
            <div class="flex items-center gap-2 text-rose-500 text-xs bg-rose-50 p-2 rounded-lg border border-rose-100">
                <span class="material-symbols-outlined text-sm">error</span>
                <?= $error ?>
            </div>
            <?php endif; ?>

            <button type="submit" name="register" class="w-full h-10 mt-1 bg-primary hover:bg-primary-hover text-white font-bold rounded-lg transition shadow hover:-translate-y-0.5 text-sm">
                Register
            </button>

            <div class="text-center text-xs text-text-secondary mt-1">
                Already have an account? 
                <a href="login.php" class="font-semibold text-primary hover:text-primary-hover">Log In</a>
            </div>

        </form>
    </main>

    <script>
    function togglePassword(inputId, iconId) {
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (input.type === "password") {
            input.type = "text";
            icon.textContent = "visibility_off";
            icon.style.color = "#00897b";
        } else {
            input.type = "password";
            icon.textContent = "visibility";
            icon.style.color = "";
        }
    }
    </script>
</body>
</html>