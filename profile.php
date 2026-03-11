<?php
session_start();
include 'config.php';
// Enable Error Reporting for debugging 500 error
include 'mail_config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Auto-fix Patient Schema (Standard Compatibility)
$check_bd = $conn->query("SHOW COLUMNS FROM patient LIKE 'birthdate'");
if ($check_bd->num_rows == 0) $conn->query("ALTER TABLE patient ADD birthdate DATE");

$check_sx = $conn->query("SHOW COLUMNS FROM patient LIKE 'sex'");
if ($check_sx->num_rows == 0) $conn->query("ALTER TABLE patient ADD sex VARCHAR(20)");

$check_ag = $conn->query("SHOW COLUMNS FROM patient LIKE 'age'");
if ($check_ag->num_rows == 0) $conn->query("ALTER TABLE patient ADD age INT");
include 'SimpleEmail.php';

// Security Check
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$msg = "";
$msg_type = "";
$show_otp_modal = false;

// --- 1. HANDLE OTP VERIFICATION (STEP 2) ---
if (isset($_POST['verify_profile_otp'])) {
    $entered_otp = trim($_POST['otp_code']);
    
    if (isset($_SESSION['pending_profile_update']) && $entered_otp == $_SESSION['pending_profile_otp']) {
        // Retrieve stored data
        $p = $_SESSION['pending_profile_update'];
        
        // Construct SQL for Pending Data
        $sql = "UPDATE users SET name=?, username=?, email=?, api_address=?, license_number=?, specialization=?, contact_number=?, address=?";
        $types = "ssssssss";
        $params = [
            $p['name'], $p['username'], $p['email'], $p['api_address'], 
            $p['license_number'], $p['specialization'], $p['contact_number'], $p['address']
        ];

        // Add Password if pending
        if (!empty($p['new_password_hash'])) {
            $sql .= ", password=?";
            $types .= "s";
            $params[] = $p['new_password_hash'];
        }
        
        $sql .= " WHERE user_id=?";
        $types .= "i";
        $params[] = $user_id;

        // Execute Update
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $_SESSION['name'] = $p['name']; // Update Session Name
            $msg = "Profile and Security settings updated successfully!";
            $msg_type = "success";

            // Sync to Patient if Client
            if ($role === 'Client') {
                $up_pat = $conn->prepare("UPDATE patient SET name=?, birthdate=?, sex=? WHERE user_id=?");
                $up_pat->bind_param("sssi", $p['name'], $p['birthdate'], $p['sex'], $user_id);
                $up_pat->execute();
            }
            
            // Clear Pending
            unset($_SESSION['pending_profile_update']);
            unset($_SESSION['pending_profile_otp']);
        } else {
            $msg = "Database Error during verification.";
            $msg_type = "error";
        }
    } else {
        $msg = "Invalid OTP. Changes not saved. Please try again.";
        $msg_type = "error";
        $show_otp_modal = true; // Keep modal open
    }
}

// --- 2. HANDLE FORM SUBMISSION (STEP 1) ---
if (isset($_POST['update_profile'])) {
    // Sanitize Inputs
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $api_address = trim($_POST['api_address'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);

    // Fetch Current Data to compare
    $curr = $conn->query("SELECT * FROM users WHERE user_id=$user_id")->fetch_assoc();
    
    // Check for Duplicates (Username/Email) excluding self
    $check = $conn->prepare("SELECT user_id FROM users WHERE (username=? OR email=?) AND user_id != ?");
    $check->bind_param("ssi", $username, $email, $user_id);
    $check->execute();
    
    if($check->get_result()->num_rows > 0) {
        $msg = "Username or Email already taken.";
        $msg_type = "error";
    } else {
        
        // Detect Critical Changes (Email or Password)
        $email_changed = ($email !== $curr['email']);
        $pass_changed = !empty($password);
        
        if ($email_changed || $pass_changed) {
            // --- CRITICAL UPDATE: REQUIRE OTP ---
            
            // Validate Password if changing
            $pass_hash = null;
            if ($pass_changed) {
                $confirm = trim($_POST['confirm_password']);
                if ($password !== $confirm) {
                    $msg = "Passwords do not match.";
                    $msg_type = "error";
                    $goto_otp = false;
                } elseif (strlen($password) < 8) {
                    $msg = "Password too short (min 8 chars).";
                    $msg_type = "error";
                    $goto_otp = false;
                } else {
                    $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                    $goto_otp = true;
                }
            } else {
                $goto_otp = true;
            }

            if ($goto_otp) {
                // Generate OTP
                $otp = rand(100000, 999999);
                
                // Store Pending Data in Session
                $_SESSION['pending_profile_update'] = [
                    'name' => $name, 'username' => $username, 'email' => $email,
                    'api_address' => $api_address, 'license_number' => $license_number,
                    'specialization' => $specialization, 'contact_number' => $contact_number,
                    'address' => $address, 'new_password_hash' => $pass_hash,
                    'birthdate' => $_POST['birthdate'] ?? null,
                    'sex' => $_POST['sex'] ?? null
                ];
                $_SESSION['pending_profile_otp'] = $otp;
                
                // Send Email (Always to the CURRENT REGISTERED EMAIL for security)
                $target_email = $curr['email']; 
                
                $subject = "Security Authorization - MCMIS";
                $action_text = $email_changed ? "change your email address from <b>{$curr['email']}</b> to <b>$email</b>" : "authorize security changes";
                
                $message = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f6f8;'>
                    <div style='max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                        <h2 style='color: #00897b; text-align: center;'>Authorize Profile Changes</h2>
                        <p style='color: #555; text-align: center;'>You requested to $action_text.</p>
                        <p style='color: #333; font-weight: bold; text-align: center; font-size: 14px;'>Since this is a sensitive action, we sent this code to your CURRENT email to verify it's really you.</p>
                        <div style='background: #e0f2f1; color: #00695c; font-size: 32px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 20px 0; letter-spacing: 5px;'>
                            $otp
                        </div>
                        <p style='color: #999; font-size: 12px; text-align: center;'>If you did not request this, please contact support immediately.</p>
                    </div>
                </div>";

                $mail = new SimpleEmail(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME);
                if ($mail->send($target_email, $subject, $message)) {
                    $show_otp_modal = true;
                    // Also process image immediately to avoid complexity? 
                    // Let's do image now as it's not critical security risk usually, or save it for after?
                    // User said "input their gmail it need first to be verified".
                    // We will skip image saving here and rely on the user re-uploading if OTP fails?
                    // Actually, let's just save the non-critical textual stuff via the OTP loop.
                    // Image upload we can process independently or just ignore for this complex flow step.
                    // For simplicity: We will process image separate from this flow or just apply it now.
                    // Applying image now:
                    if (!empty($_FILES['profile_pic']['name'])) {
                         // Process image even if text is pending? 
                         // Better: Process image ONLY if text is verified? No, that requires storing file.
                         // Compromise: Process image NOW. If they abandon OTP, image stays updated. That's acceptable.
                         process_image_upload($conn, $user_id);
                    }
                } else {
                    $msg = "Failed to create Secure Session (Email Failed).";
                    $msg_type = "error";
                }
            }

        } else {
            // --- NO CRITICAL CHANGES (Just Name/Address/etc) ---
            $stmt = $conn->prepare("UPDATE users SET name=?, username=?, api_address=?, license_number=?, specialization=?, contact_number=?, address=? WHERE user_id=?");
            $stmt->bind_param("sssssssi", $name, $username, $api_address, $license_number, $specialization, $contact_number, $address, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['name'] = $name;
                $msg = "Profile updated successfully!";
                $msg_type = "success";

                // Sync to Patient table if Client
                if ($role === 'Client') {
                    $up_pat = $conn->prepare("UPDATE patient SET name=?, birthdate=?, sex=? WHERE user_id=?");
                    $up_pat->bind_param("sssi", $name, $_POST['birthdate'], $_POST['sex'], $user_id);
                    $up_pat->execute();
                }
                
                // Process Image
                if (!empty($_FILES['profile_pic']['name'])) {
                    process_image_upload($conn, $user_id);
                }
            } else {
                $msg = "database error.";
                $msg_type = "error";
            }
        }
    }
}

function process_image_upload($conn, $uid) {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
    $new_name = "user_" . $uid . "_" . time() . "." . $ext;
    if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        if(move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_dir . $new_name)) {
            $conn->query("UPDATE users SET profile_pic='$new_name' WHERE user_id=$uid");
        }
    }
}

?>
<?php
// --- FETCH CURRENT DATA ---
$sql_f = "SELECT u.*, p.birthdate, p.sex FROM users u LEFT JOIN patient p ON u.user_id = p.user_id WHERE u.user_id = ?";
$stmt = $conn->prepare($sql_f);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Determine Dashboard Link based on Role
$dashboard_link = "#";
switch($role) {
    case 'Admin': $dashboard_link = "admin_dashboard.php"; break;
    case 'Clerk': $dashboard_link = "clerk_dashboard.php"; break;
    case 'Midwife': $dashboard_link = "midwife_dashboard.php"; break;
    case 'Client': $dashboard_link = "client_dashboard.php"; break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MCMIS</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-7xl w-full bg-white rounded-[2rem] shadow-2xl overflow-hidden flex flex-col md:flex-row h-auto md:h-[90vh] ring-1 ring-black/5">
        
        <!-- Left Sidebar (Profile Overview) -->
        <div class="md:w-[320px] bg-[#0f766e] p-8 text-white flex flex-col items-center relative shrink-0 overflow-hidden">
            <!-- Background Decoration -->
            <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
                <div class="absolute -top-20 -left-20 w-64 h-64 bg-teal-400 rounded-full mix-blend-overlay filter blur-3xl"></div>
                <div class="absolute bottom-0 right-0 w-80 h-80 bg-emerald-400 rounded-full mix-blend-overlay filter blur-3xl"></div>
            </div>

            <a href="<?php echo $dashboard_link; ?>" class="absolute top-8 left-8 text-white/70 hover:text-white flex items-center gap-2 text-xs font-bold transition-all hover:-translate-x-1">
                <i class="fas fa-arrow-left"></i> BACK
            </a>

            <div class="relative group mt-12 mb-6">
                <div class="w-36 h-36 rounded-full border-4 border-white/20 shadow-2xl bg-white relative z-10 overflow-hidden">
                    <?php if($user['profile_pic']): ?>
                        <img src="uploads/<?php echo $user['profile_pic']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-teal-50 text-teal-700 text-5xl font-bold">
                            <?php echo substr($user['name'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Status Indicator -->
                <div class="absolute bottom-2 right-2 w-6 h-6 bg-emerald-400 border-4 border-[#0f766e] rounded-full z-20"></div>
            </div>

            <h2 class="text-2xl font-bold leading-tight text-center"><?php echo $user['name']; ?></h2>
            <p class="text-teal-200/80 text-sm font-medium mt-1 mb-8"><?php echo $user['email']; ?></p>

            <div class="w-full space-y-4 relative z-10">
                <div class="bg-white/10 rounded-2xl p-4 border border-white/5 backdrop-blur-md">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-teal-200">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <div>
                            <span class="block text-teal-200/60 text-[10px] uppercase font-bold tracking-wider">Role</span>
                            <span class="text-sm font-bold text-white"><?php echo $user['role']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white/10 rounded-2xl p-4 border border-white/5 backdrop-blur-md">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-teal-200">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="overflow-hidden">
                            <span class="block text-teal-200/60 text-[10px] uppercase font-bold tracking-wider">Location</span>
                            <span id="sidebarAddressDisplay" class="text-sm font-medium text-white truncate block">
                                <?php echo !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not set'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-auto pt-8 text-teal-200/40 text-[10px] tracking-widest uppercase font-bold">
                MCMIS Secure Profile
            </div>
        </div>

        <!-- Right Content (Form) -->
        <div class="flex-1 bg-slate-50 overflow-y-auto custom-scrollbar relative flex flex-col">
            
            <!-- Header -->
            <div class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-slate-200 px-8 py-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Account Settings</h1>
                    <p class="text-slate-500 text-sm">Manage your personal information and privacy.</p>
                </div>
                <button form="profileForm" type="submit" name="update_profile" class="px-6 py-3 bg-teal-700 hover:bg-teal-800 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-700/20 transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>

            <div class="p-8 md:p-10 max-w-4xl mx-auto w-full">
                <?php if($msg): ?>
                    <div class="mb-8 p-4 rounded-xl text-sm font-bold flex items-center gap-3 animate-fade-in <?php echo $msg_type == 'success' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                        <i class="fas <?php echo $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> text-xl"></i>
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <form id="profileForm" method="POST" enctype="multipart/form-data" class="space-y-8">
                    
                    <!-- IDENTITY SECTION -->
                    <section class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
                        <h3 class="text-xs font-bold text-slate-400 flex items-center gap-2 uppercase tracking-widest mb-8">
                            <span class="w-8 h-[2px] bg-teal-500"></span> Identity Verification
                        </h3>
                        
                        <div class="flex flex-col md:flex-row gap-10 items-start">
                            <!-- Photo Upload -->
                            <div class="shrink-0 flex flex-col items-center gap-4">
                                <div class="relative w-32 h-32 rounded-3xl overflow-hidden bg-slate-100 ring-4 ring-white shadow-lg group cursor-pointer hover:ring-teal-50 transition-all" onclick="document.getElementById('fileInput').click()">
                                    <?php if($user['profile_pic']): ?>
                                        <img src="uploads/<?php echo $user['profile_pic']; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-300 group-hover:text-teal-500 transition-colors">
                                            <i class="fas fa-camera text-3xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-[2px]">
                                        <i class="fas fa-pen text-white text-lg drop-shadow-md"></i>
                                    </div>
                                </div>
                                <input id="fileInput" type="file" name="profile_pic" class="hidden">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Max 2MB (JPG/PNG)</p>
                            </div>

                            <!-- Fields -->
                            <div class="grow grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Full Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full mt-2 px-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-semibold text-slate-700">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Username</label>
                                    <div class="relative mt-2">
                                        <span class="absolute left-4 top-3.5 text-slate-400 font-bold">@</span>
                                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required class="w-full pl-9 pr-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-semibold text-slate-700">
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Email Address <span class="bg-teal-100 text-teal-700 text-[9px] px-2 py-0.5 rounded ml-2">VERIFIED</span></label>
                                    <div class="relative mt-2">
                                        <i class="fas fa-envelope absolute left-4 top-3.5 text-slate-400"></i>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-semibold text-slate-700">
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-2 ml-1">Note: A valid email is required for password recovery and notifications.</p>
                                </div>

                                <?php if($role === 'Client'): ?>
                                    <div>
                                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date of Birth</label>
                                        <input type="date" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>" class="w-full mt-2 px-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-semibold text-slate-700">
                                    </div>
                                    <div>
                                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Sex</label>
                                        <select name="sex" class="w-full mt-2 px-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-semibold text-slate-700">
                                            <option value="Female" <?php echo ($user['sex']=='Female')?'selected':''; ?>>Female</option>
                                            <option value="Male" <?php echo ($user['sex']=='Male')?'selected':''; ?>>Male</option>
                                            <option value="Other" <?php echo ($user['sex']=='Other')?'selected':''; ?>>Other</option>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <!-- ROLE DETAILS SECTION -->
                    <section class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
                        <h3 class="text-xs font-bold text-slate-400 flex items-center gap-2 uppercase tracking-widest mb-8">
                            <span class="w-8 h-[2px] bg-teal-500"></span> 
                            <?php echo ($role === 'Client') ? 'Contact & Address' : 'Professional Credentials'; ?>
                        </h3>

                        <?php if($role === 'Client'): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Mobile Number</label>
                                    <div class="relative mt-2 max-w-sm">
                                        <span class="absolute left-4 top-3.5 text-slate-400 text-sm">🇵🇭 +63</span>
                                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" placeholder="912 345 6789" class="w-full pl-16 pr-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-bold text-slate-700 font-mono">
                                    </div>
                                </div>

                                <div class="md:col-span-2 border-t border-slate-50 pt-6">
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Home Address</label>
                                    
                                    <!-- Visual Address Badge -->
                                    <?php if(!empty($user['address'])): ?>
                                    <div class="mt-2 mb-4 p-3 bg-teal-50/50 rounded-xl border border-teal-100 text-teal-800 text-sm font-medium flex items-start gap-3">
                                        <i class="fas fa-map-pin mt-1 text-teal-500"></i>
                                        <?php echo htmlspecialchars($user['address']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Address Logic -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
                                        <select id="region" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer"><option value="">Region</option></select>
                                        <select id="province" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer opacity-50" disabled><option value="">Province</option></select>
                                        <select id="city" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer opacity-50" disabled><option value="">City</option></select>
                                        <select id="barangay" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer opacity-50" disabled><option value="">Barangay</option></select>
                                    </div>
                                    <input type="hidden" name="address" id="full_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                </div>
                            </div>

                        <?php else: // Admin/Midwife/Clerk ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">License Number</label>
                                    <div class="relative mt-2">
                                        <i class="fas fa-certificate absolute left-4 top-3.5 text-amber-500"></i>
                                        <input type="text" name="license_number" value="<?php echo htmlspecialchars($user['license_number'] ?? ''); ?>" placeholder="PRC-XXXXXX" class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-semibold text-slate-700">
                                    </div>
                                </div>

                                <?php if(in_array($role, ['Midwife', 'Clerk'])): ?>
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Specialization</label>
                                    <div class="relative mt-2">
                                        <i class="fas fa-stethoscope absolute left-4 top-3.5 text-teal-500"></i>
                                        <select name="specialization" class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all font-semibold text-slate-700 appearance-none">
                                            <option value="" disabled <?php echo empty($user['specialization']) ? 'selected' : ''; ?>>Select Type</option>
                                            <option value="Midwifery" <?php echo ($user['specialization']=='Midwifery')?'selected':''; ?>>Midwifery</option>
                                            <option value="Clinic Staff" <?php echo ($user['specialization']=='Clinic Staff')?'selected':''; ?>>Clinic Staff</option>
                                        </select>
                                        <i class="fas fa-chevron-down absolute right-4 top-4 text-slate-300 pointer-events-none"></i>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Added Address Section for Staff -->
                                <div class="md:col-span-2 border-t border-slate-50 pt-6">
                                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Address / Clinic Location</label>
                                    
                                    <!-- Visual Address Badge -->
                                    <?php if(!empty($user['address'])): ?>
                                    <div class="mt-2 mb-4 p-3 bg-teal-50/50 rounded-xl border border-teal-100 text-teal-800 text-sm font-medium flex items-start gap-3">
                                        <i class="fas fa-map-pin mt-1 text-teal-500"></i>
                                        <?php echo htmlspecialchars($user['address']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Address Logic -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
                                        <select id="region" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer"><option value="">Region</option></select>
                                        <select id="province" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer opacity-50" disabled><option value="">Province</option></select>
                                        <select id="city" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer opacity-50" disabled><option value="">City</option></select>
                                        <select id="barangay" class="w-full px-3 py-3 rounded-xl bg-slate-50 border-0 ring-1 ring-slate-200 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-teal-500 transition-all cursor-pointer opacity-50" disabled><option value="">Barangay</option></select>
                                    </div>
                                    <input type="hidden" name="address" id="full_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- SECURITY SECTION -->
                    <section class="bg-red-50/20 p-8 rounded-3xl shadow-sm border border-red-100/50">
                        <h3 class="text-xs font-bold text-red-500 flex items-center gap-2 uppercase tracking-widest mb-8">
                            <span class="w-8 h-[2px] bg-red-400"></span> Security
                        </h3>
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase ml-1">New Password</label>
                                <div class="relative mt-2">
                                    <i class="fas fa-lock absolute left-4 top-3.5 text-red-300"></i>
                                    <input type="password" name="password" placeholder="Min 8 characters" class="w-full pl-10 pr-4 py-3 rounded-xl bg-white border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-red-400 transition-all font-bold text-slate-700 placeholder-slate-300">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Confirm Password</label>
                                <div class="relative mt-2">
                                    <i class="fas fa-check-circle absolute left-4 top-3.5 text-red-300"></i>
                                    <input type="password" name="confirm_password" placeholder="Re-type password" class="w-full pl-10 pr-4 py-3 rounded-xl bg-white border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-red-400 transition-all font-bold text-slate-700 placeholder-slate-300">
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="h-10"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- OTP MODAL -->
    <?php if ($show_otp_modal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-fade-in">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center relative border border-white/20">
            <div class="w-16 h-16 bg-teal-50 text-teal-600 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
                <i class="fas fa-shield-alt text-2xl"></i>
            </div>
            
            <h2 class="text-xl font-bold text-slate-800 mb-2">Security Verification</h2>
            <p class="text-slate-500 text-sm mb-6">
                We sent a 6-digit code to your <b>current registered email</b> to authorize these changes.
            </p>

            <form method="POST" class="space-y-4">
                <input type="text" name="otp_code" maxlength="6" autofocus placeholder="000000" class="w-full text-center text-3xl tracking-[0.5em] font-bold py-3 border border-slate-200 rounded-xl focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-slate-700">
                
                <button type="submit" name="verify_profile_otp" class="w-full py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow-lg shadow-teal-600/20 transition-all">
                    Verify & Save
                </button>
            </form>

            <a href="profile.php" class="block mt-4 text-xs font-bold text-slate-400 hover:text-slate-600 cursor-pointer">
                Cancel Update
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Address Logic Script -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const regionSelect = document.getElementById('region');
        const provinceSelect = document.getElementById('province');
        const citySelect = document.getElementById('city');
        const barangaySelect = document.getElementById('barangay');
        const fullAddressInput = document.getElementById('full_address');
        const sidebarAddressDisplay = document.getElementById('sidebarAddressDisplay');

        if (!regionSelect) return; 

        const apiBase = 'https://psgc.gitlab.io/api';

        // Load Regions
        fetch(`${apiBase}/regions`)
            .then(response => response.json())
            .then(data => {
                data.sort((a,b) => a.name.localeCompare(b.name));
                data.forEach(region => {
                    const option = document.createElement('option');
                    option.value = region.code;
                    option.textContent = region.name + ' (' + region.regionName + ')';
                    regionSelect.appendChild(option);
                });
            });

        // Region Change
        regionSelect.addEventListener('change', function() {
            resetSelects(provinceSelect, citySelect, barangaySelect);
            if(!this.value) return;

            fetch(`${apiBase}/regions/${this.value}/provinces`)
            .then(res => res.json())
            .then(data => {
                data.sort((a,b) => a.name.localeCompare(b.name));
                data.forEach(item => addOption(provinceSelect, item.code, item.name));
                provinceSelect.disabled = false;
                
                // NCR Special Handling (No provinces)
                if(this.value === '130000000') { 
                     fetch(`${apiBase}/regions/${this.value}/cities-municipalities`)
                    .then(r => r.json())
                    .then(cities => {
                         cities.sort((a,b) => a.name.localeCompare(b.name));
                         cities.forEach(c => addOption(citySelect, c.code, c.name));
                         citySelect.disabled = false;
                    });
                }
            });
            updateAddress();
        });

        // Province Change
        provinceSelect.addEventListener('change', function() {
            resetSelects(citySelect, barangaySelect);
            if(!this.value) return;

            fetch(`${apiBase}/provinces/${this.value}/cities-municipalities`)
            .then(res => res.json())
            .then(data => {
                data.sort((a,b) => a.name.localeCompare(b.name));
                data.forEach(item => addOption(citySelect, item.code, item.name));
                citySelect.disabled = false;
            });
            updateAddress();
        });

        // City Change
        citySelect.addEventListener('change', function() {
            resetSelects(barangaySelect);
            if(!this.value) return;

            fetch(`${apiBase}/cities-municipalities/${this.value}/barangays`)
            .then(res => res.json())
            .then(data => {
                data.sort((a,b) => a.name.localeCompare(b.name));
                data.forEach(item => addOption(barangaySelect, item.code, item.name));
                barangaySelect.disabled = false;
            });
            updateAddress();
        });

        barangaySelect.addEventListener('change', updateAddress);

        function resetSelects(...selects) {
            selects.forEach(sel => {
                sel.innerHTML = '<option value="">' + sel.dataset.label || sel.options[0].text + '</option>';
                sel.disabled = true;
                sel.classList.add('opacity-50');
            });
        }

        function addOption(select, value, text) {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = text;
            select.appendChild(opt);
        }

        function updateAddress() {
            const r = regionSelect.options[regionSelect.selectedIndex]?.text || '';
            const p = provinceSelect.options[provinceSelect.selectedIndex]?.text || '';
            const c = citySelect.options[citySelect.selectedIndex]?.text || '';
            const b = barangaySelect.options[barangaySelect.selectedIndex]?.text || '';
            
            if (b && b !== 'Barangay') {
                 let full = `${b}, ${c}, ${p}`;
                 if(p === '' || p === 'Province') full = `${b}, ${c}, ${r}`; 
                 
                 fullAddressInput.value = full;
                 if(sidebarAddressDisplay) sidebarAddressDisplay.textContent = full;
            }
        }
    });
    </script>
</body>
</html>