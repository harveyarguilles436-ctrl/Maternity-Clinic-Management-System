<?php
ob_start();
session_start();
include 'config.php';

$error = "";

// 1. Auto-Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'Admin') { header("Location: admin_dashboard.php"); exit; }
    if ($role === 'Clerk') { header("Location: clerk_dashboard.php"); exit; }
    if ($role === 'Midwife') { header("Location: midwife_dashboard.php"); exit; }
    if ($role === 'Client') { header("Location: client_dashboard.php"); exit; }
}

// 2. Handle Login Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, name, password, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check Status BEFORE verifying password (or after, doesn't matter much security-wise for this level, but stricter is better)
            if ($user['status'] === 'Inactive') {
                $error = "Your account has been deactivated. Contact Admin.";
            } 
            elseif (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['role']    = $user['role'];
                // Optionally store status in session if we want to check it partly, but DB check is better for real-time ban
                
                switch ($user['role']) {
                    case 'Admin':   header("Location: admin_dashboard.php"); break;
                    case 'Clerk':   header("Location: clerk_dashboard.php"); break;
                    case 'Midwife': header("Location: midwife_dashboard.php"); break;
                    case 'Client':  header("Location: client_dashboard.php"); break;
                    default:        header("Location: index.php");
                }
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Username not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Mother Therese Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{font-family:'Manrope',sans-serif;}</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 relative">

    <a href="index.php" class="absolute top-6 left-6 flex items-center gap-2 text-slate-500 hover:text-teal-700 font-bold transition-colors z-10">
        <div class="w-8 h-8 rounded-full bg-white shadow-sm flex items-center justify-center text-teal-600 hover:bg-teal-50 border border-slate-200">
            <i class="fas fa-arrow-left"></i>
        </div>
        <span class="hidden sm:inline">Back to Home</span>
    </a>

    <div class="bg-white max-w-md w-full rounded-2xl shadow-xl border border-slate-100 p-8">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-teal-50 text-teal-700 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fas fa-user-circle"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Welcome Back</h1>
            <p class="text-slate-500 text-sm">Sign in to access your dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-bold mb-6 flex items-center gap-2 border border-red-100">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Username</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" required placeholder="Enter your username" 
                           class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl focus:border-teal-600 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Password</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400"><i class="fas fa-lock"></i></span>
                    <input type="password" id="passwordInput" name="password" required placeholder="Enter your password" 
                           class="w-full pl-10 pr-12 py-3 border border-slate-200 rounded-xl focus:border-teal-600 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                    
                    <button type="button" onclick="togglePassword()" class="absolute right-4 top-3.5 text-slate-400 hover:text-teal-700 focus:outline-none transition-colors">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
                <div class="flex justify-end mt-2">
                    <a href="forgot_password.php" class="text-xs font-semibold text-teal-600 hover:text-teal-800 hover:underline transition-colors">
                        Forgot Password?
                    </a>
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-teal-700 hover:bg-teal-800 text-white font-bold rounded-xl shadow-lg shadow-teal-700/20 transition-all active:scale-95">
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center text-sm text-slate-500">
            Don't have an account? <a href="register.php" class="text-teal-700 font-bold hover:underline">Register Here</a>
        </div>

    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>

</body>
</html>