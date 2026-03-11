<?php
ob_start();
session_start();
include 'config.php';

// Security: Check Login & Role
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (strcasecmp($_SESSION['role'], 'Client') !== 0) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

// Get Linked Patient ID
$pat_query = $conn->query("SELECT patient_id, name, contact_no FROM patient WHERE user_id = '$user_id'");
$patient = $pat_query->fetch_assoc();
$patient_id = $patient ? $patient['patient_id'] : 0;
$patient_name = $patient ? $patient['name'] : 'User';
$contact = $patient ? $patient['contact_no'] : '';

// Use session messages for redirects
$session_msg = '';
if (isset($_SESSION['msg'])) {
    $session_msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Capture SweetAlert objects
$swal_success = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : null;
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : null;
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);

$msg = $session_msg;

// 1. Booking Logic
// 1. Booking Logic (Enhanced with Payment Proof + Gap Validation)
if (isset($_POST['book'])) {
    $date = $_POST['date']; 
    $time = date("H:i:s", strtotime($_POST['time'])); // Convert 12h to 24h for DB
    $service = $_POST['service'];
    
    // VALIDATION 0: Check for Existing Active Appointment
    $chk_active = $conn->query("SELECT appointment_id FROM appointment WHERE patient_id='$patient_id' AND status IN ('Pending', 'Confirmed', 'Arrived') AND down_payment_status != 'Rejected'");
    if ($chk_active->num_rows > 0) {
         $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>You currently have an active appointment. Please complete or cancel it before booking a new one.</div>';
    }
    // VALIDATION 1: Blocked Date
    else
    if ($conn->query("SELECT * FROM blocked_dates WHERE blocked_date='$date'")->num_rows > 0) {
        $msg = '<div class="alert alert-danger">Selected date is unavailable (Blocked by Admin). Please choose another.</div>';
    } 
    // VALIDATION 2: Past Date
    elseif (strtotime($date . ' ' . $time) < time()) {
        $msg = '<div class="alert alert-danger">Error: Cannot book past dates/times.</div>';
    } 
    else {
        // VALIDATION 3: Time Range Check (6 AM - 9 PM)
        $hour = (int)date("H", strtotime($time));
        if ($hour < 6 || $hour > 21) {
            $msg = '<div class="alert alert-danger"><i class="fas fa-clock me-2"></i>Invalid appointment time. 6:00 AM to 9:00 PM only.</div>';
        } else {
            // VALIDATION 4: Capacity Check (Max 4 Midwives)
            // Special Rule: 12:00 PM is ALWAYS available
            $check_cap = $conn->query("SELECT COUNT(*) FROM appointment WHERE appointment_date='$date' AND appointment_time='$time' AND status NOT IN ('Cancelled', 'Rejected')");
            $current_cnt = $check_cap->fetch_row()[0];
            
            $is_noon = (date("H:i", strtotime($time)) === '12:00');

            if ($current_cnt >= 4 && !$is_noon) {
                 $msg = '<div class="alert alert-danger"><i class="fas fa-ban me-2"></i>This time slot is fully booked. Please select another time.</div>';
            } else {
            // ... File Upload & Insert Logic ... (Standard)
            $proof_filename = null;
            $status_insert = 'Pending';
            $dp_status_insert = 'Reviewing';
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                
                $file_ext = strtolower(pathinfo($_FILES["payment_proof"]["name"], PATHINFO_EXTENSION));
                $new_filename = "proof_" . time() . "_" . uniqid() . "." . $file_ext;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["payment_proof"]["tmp_name"], $target_file)) {
                    $proof_filename = $new_filename;
                }
            } elseif (!empty($_POST['reference_no']) && strpos($_POST['reference_no'], 'PAYID') === 0) {
                // Handle Simulated Payment (No File)
                $proof_filename = 'Digital_Transaction';
                // Auto-Verify Digital Payments
                $status_insert = 'Confirmed';
                $dp_status_insert = 'Paid';
            }
            
            $ref_no = $_POST['reference_no'] ?? '';
            
            // Safely add column if it doesn't exist to prevent SQL errors
            $check_col = $conn->query("SHOW COLUMNS FROM appointment LIKE 'reference_no'");
            if($check_col && $check_col->num_rows == 0) {
                $conn->query("ALTER TABLE appointment ADD COLUMN reference_no VARCHAR(50) DEFAULT NULL");
            }

            $check_col2 = $conn->query("SHOW COLUMNS FROM appointment LIKE 'payment_mode'");
            if($check_col2 && $check_col2->num_rows == 0) {
                $conn->query("ALTER TABLE appointment ADD COLUMN payment_mode VARCHAR(20) DEFAULT 'DownPayment'");
            }

            // Check input key (support all variations, prioritize force)
            $raw_mode = $_POST['force_payment_mode'] ?? ($_POST['payment_type'] ?? ($_POST['payment_mode'] ?? 'dp'));
            $pay_mode = (strtolower($raw_mode) == 'full') ? 'Full' : 'DownPayment';
            
            $stmt = $conn->prepare("INSERT INTO appointment (patient_id, appointment_date, appointment_time, service, status, payment_proof, down_payment_status, reference_no, payment_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssss", $patient_id, $date, $time, $service, $status_insert, $proof_filename, $dp_status_insert, $ref_no, $pay_mode);
            
            if($stmt->execute()) {
                $last_id = $stmt->insert_id;
                $conn->query("UPDATE appointment SET payment_mode='$pay_mode' WHERE appointment_id='$last_id'");
                $mode_display = ($pay_mode == 'Full') ? 'Full Payment' : '50% Downpayment';
                $_SESSION['swal_success'] = [
                    'title' => 'Booking Requested!',
                    'text'  => "Your appointment request has been sent! (Payment: $mode_display)",
                    'icon'  => 'success',
                    'timer' => 5000
                ];
                $_SESSION['msg'] = '<div class="alert alert-success">Booking Requested Successfully! (Mode: '.$mode_display.')</div>';
                header("Location: client_dashboard.php");
                exit;
            } else {
                $msg = '<div class="alert alert-danger">Failed to request booking.</div>';
            }
        }
    }
}
}

// 2. Cancellation Logic (UPDATED WITH REASON)
if (isset($_POST['cancel_appt_submit'])) {
    $aid = $_POST['cancel_id'];
    $reason_select = $_POST['cancel_reason_select'];
    
    // Logic: If 'Others' is selected, use the Note input. Otherwise, use the dropdown value.
    if ($reason_select === 'Others') {
        $final_reason = $_POST['cancel_reason_note'];
    } else {
        $final_reason = $reason_select;
    }

    $stmt = $conn->prepare("UPDATE appointment SET status='Cancelled', cancel_reason=? WHERE appointment_id=? AND patient_id=?");
    $stmt->bind_param("sii", $final_reason, $aid, $patient_id);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = '<div class="alert alert-warning">Appointment cancelled. Reason: '.htmlspecialchars($final_reason).'</div>';
        header("Location: client_dashboard.php");
        exit;
    } else {
        $msg = '<div class="alert alert-danger">Failed to cancel appointment.</div>';
    }
}

// 2. Submit Rating for Completed Appointment
// 2. Submit Rating for Completed Appointment
// 2. Submit Rating for Completed Appointment
if (isset($_POST['submit_rating'])) {
    // Enable error reporting for this block to catch issues
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $appt_id = intval($_POST['appointment_id']);
        $rating = intval($_POST['rating']);
        $review = trim($_POST['review_text']);
        
        // Ensure patient_id is valid
        if (empty($patient_id) || $patient_id <= 0) {
           throw new Exception("Invalid Patient ID. Please re-login.");
        }

        // Verify appointment is completed and belongs to this patient
        $check = $conn->prepare("SELECT service FROM appointment WHERE appointment_id=? AND patient_id=? AND status='Completed'");
        $check->bind_param("ii", $appt_id, $patient_id);
        $check->execute();
        $check->bind_result($service_name);
        
        if($check->fetch()) {
            $check->close(); // Close first statement
            
            // Fallback if service name is null
            if (empty($service_name)) $service_name = "General Service";

            // Check if already rated (Robust check)
            $check_rated = $conn->prepare("SELECT rating_id FROM ratings WHERE appointment_id=?");
            $check_rated->bind_param("i", $appt_id);
            $check_rated->execute();
            $check_rated->store_result();
            
            if($check_rated->num_rows == 0) {
                $check_rated->close();
                
                $stmt = $conn->prepare("INSERT INTO ratings (appointment_id, patient_id, service_name, rating, review_text) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iisis", $appt_id, $patient_id, $service_name, $rating, $review);
                
                if($stmt->execute()) {
                    $_SESSION['msg'] = '<div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle me-2"></i>Thank you! Your feedback helps us improve.</div>';
                    header("Location: client_dashboard.php");
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-circle me-2"></i>Failed to save rating.</div>';
                }
                $stmt->close();
            } else {
                $check_rated->close();
                $_SESSION['msg'] = '<div class="alert alert-info border-0 shadow-sm"><i class="fas fa-info-circle me-2"></i>You have already rated this appointment.</div>';
                header("Location: client_dashboard.php");
                exit;
            }
        } else {
            $check->close();
            $msg = '<div class="alert alert-danger border-0 shadow-sm">Invalid appointment or not yet completed.</div>';
        }

    } catch (Exception $e) {
        // Catch ANY error and show it nicely instead of 500ing
        $msg = '<div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i>System Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// 2.5 Submit Bill Payment Proof
if (isset($_POST['pay_bill_proof'])) {
    $bid = $_POST['bill_id'];
    
    // File Upload
    if (isset($_FILES['bill_proof']) && $_FILES['bill_proof']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES["bill_proof"]["name"], PATHINFO_EXTENSION));
        $new_filename = "bill_" . $bid . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["bill_proof"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("UPDATE billing SET status='Pending', proof_image=?, payment_method='GCash' WHERE bill_id=?");
            $stmt->bind_param("si", $new_filename, $bid);
            if($stmt->execute()) {
                $_SESSION['msg'] = '<div class="alert alert-success">Payment proof submitted! Waiting for clerk verification.</div>';
                header("Location: client_dashboard.php#billing");
                exit;
            } else {
                $msg = '<div class="alert alert-danger">Database error.</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger">Failed to upload file.</div>';
        }
    }
}

// 3. Reschedule Logic
if (isset($_POST['update_appt'])) {
    $aid = $_POST['appt_id'];
    $new_date = $_POST['new_date'];
    $new_time = $_POST['new_time'];
    
    // 1. Validate Date (Future)
    if (strtotime($new_date . ' ' . $new_time) < time()) {
        $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error: Cannot reschedule to a past date/time.</div>';
    } 
    // 2. Validate Blocked Date
    elseif ($conn->query("SELECT * FROM blocked_dates WHERE blocked_date='$new_date'")->num_rows > 0) {
        $msg = '<div class="alert alert-danger"><i class="fas fa-ban me-2"></i>Selected date is unavailable (Blocked by Admin).</div>';
    }
    // 2.5 Validate Time Range (6 AM - 9 PM)
    elseif (((int)date("H", strtotime($new_time)) < 6) || ((int)date("H", strtotime($new_time)) > 21)) {
        $msg = '<div class="alert alert-danger"><i class="fas fa-clock me-2"></i>Invalid appointment time. 6:00 AM to 9:00 PM only.</div>';
    }
    else {
        // 3. Capacity Check (Standard approach: Ensure slot isn't full)
        // Check how many ACTIVE appointments are in the new slot
        $cap_sql = "SELECT COUNT(*) FROM appointment WHERE appointment_date='$new_date' AND appointment_time='$new_time' AND status NOT IN ('Cancelled', 'Rejected')";
        $current_cnt = $conn->query($cap_sql)->fetch_row()[0];
        
        if ($current_cnt >= 4) {
             $msg = '<div class="alert alert-danger"><i class="fas fa-users-slash me-2"></i>This time slot is fully booked. Please choose another.</div>';
        } else {
            // 4. Update (Allow Rescheduling for Pending AND Confirmed appointments)
            // Note: We keep the existing status (e.g., if Confirmed, it stays Confirmed in new slot)
            $stmt = $conn->prepare("UPDATE appointment SET appointment_date=?, appointment_time=? WHERE appointment_id=? AND patient_id=? AND status IN ('Pending', 'Confirmed')");
            $stmt->bind_param("ssii", $new_date, $new_time, $aid, $patient_id);
            
            if($stmt->execute() && $stmt->affected_rows > 0) {
                $_SESSION['msg'] = '<div class="alert alert-success"><i class="fas fa-calendar-check me-2"></i>Appointment rescheduled successfully.</div>';
                header("Location: client_dashboard.php");
                exit;
            } else {
                // If affected_rows is 0, it means either ID invalid OR Status was not allowed (e.g. Completed)
                $msg = '<div class="alert alert-danger">Failed to reschedule. (Appointment may be Completed or Cancelled)</div>';
            }
        }
    }
}

// 4. Submit Feedback Logic
if (isset($_POST['submit_feedback'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO feedback (patient_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $patient_id, $message);
        if($stmt->execute()) {
            $_SESSION['msg'] = '<div class="alert alert-success">Thank you! Your feedback has been sent to the admin.</div>';
            header("Location: client_dashboard.php");
            exit;
        } else {
            $msg = '<div class="alert alert-danger">Error sending feedback.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal - Mother Therese Clinic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Flatpickr for Calendar + Airbnb Theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <style>
        :root { --primary: #0f766e; --secondary: #134e4a; --light: #f0fdfa; }
        body { background-color: #f8fafc; font-family: 'Segoe UI', sans-serif; }
        
        .sidebar { width: 280px; min-height: 100vh; background: white; position: fixed; top: 0; left: 0; border-right: 1px solid #e2e8f0; z-index: 1000; }
        .sidebar-header { padding: 2rem 1.5rem; color: var(--primary); font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; }
        .nav-link { color: #64748b; padding: 1rem 1.5rem; font-weight: 600; transition: all 0.2s; border-left: 4px solid transparent; display: flex; align-items: center; gap: 12px; }
        .nav-link:hover, .nav-link.active { background-color: var(--light); color: var(--primary); border-left-color: var(--primary); }
        .main-content { margin-left: 280px; padding: 2rem; }
        .welcome-card { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 16px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 10px 30px -10px rgba(15, 118, 110, 0.4); }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .receipt-modal { font-family: 'Courier New', Courier, monospace; }
        .flatpickr-day.fully-booked { background: #fca5a5 !important; border-color: #fca5a5 !important; color: #7f1d1d !important; cursor: not-allowed; text-decoration: line-through; }
        .flatpickr-day.busy-day:after { content: "•"; position: absolute; bottom: 2px; left: 0; right: 0; text-align: center; color: #f59e0b; font-size: 14px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 1.5rem; overflow: hidden; }
        .card-header { background: white; border-bottom: 1px solid #f1f5f9; padding: 1.5rem; font-weight: 700; color: #334155; }
        
        /* New Styles for Calculator */
        .calc-box { background: #e0f2f1; border: 2px dashed #0f766e; border-radius: 15px; padding: 20px; text-align: center; }
        .result-box { display: none; margin-top: 20px; animation: fadeIn 0.5s; }
        .big-date { font-size: 2.5rem; font-weight: 800; color: #0f766e; }

        /* Custom Receipt Modal (Matching Clerk Design) */
        .custom-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.75); backdrop-filter: blur(4px);
            z-index: 2000; display: none; align-items: center; justify-content: center;
        }
        .custom-modal-overlay.active { display: flex; }
        .custom-modal-box {
            background: white; width: 100%; max-width: 28rem;
            border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden; animation: modalPop 0.3s ease-out;
        }
        @keyframes modalPop { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .stat-item { background: white; padding: 10px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Calendar & Time Slots Styles */
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
        .calendar-day-header { text-align: center; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px; }
        .calendar-day { 
            padding: 8px; text-align: center; border-radius: 8px; cursor: pointer; transition: all 0.2s; 
            font-size: 0.9rem; color: #334155;
        }
        .calendar-day:hover:not(.disabled):not(.selected) { background-color: #f1f5f9; }
        .calendar-day.selected { background-color: #0f766e; color: white; font-weight: 700; }
        .calendar-day.disabled { color: #cbd5e1; cursor: not-allowed; }
        .calendar-day.today { background-color: #ccfbf1; color: #0f766e; font-weight: 600; border: 1px solid #99f6e4; }
        
        .time-slot-btn {
            width: 100%; text-align: center; padding: 12px; border-radius: 10px; border: 2px solid #e2e8f0;
            background: white; transition: all 0.2s; position: relative;
        }
        .time-slot-btn:hover:not(:disabled) { border-color: #0f766e; color: #0f766e; background: #f0fdfa; }
        .time-slot-btn.selected { background-color: #0f766e !important; color: white !important; border-color: #0f766e !important; box-shadow: 0 0 0 3px #99f6e4; }
        .time-slot-btn:disabled { background-color: #f1f5f9; border-color: #e2e8f0; color: #cbd5e1; cursor: not-allowed; opacity: 0.7; }
        
        .slot-available { border-color: #10b981; color: #047857; background: #ecfdf5; }
        .slot-available:hover { background: #10b981; color: white; }
        
        .slot-full-booked { 
            border-color: #ef4444 !important; 
            color: #7f1d1d !important; 
            background: #fee2e2 !important; 
            opacity: 0.8;
            cursor: not-allowed !important;
        }
        .slot-full-booked .time-text { text-decoration: line-through; }
        
        .slot-past { background-color: #f1f5f9 !important; border-color: #e2e8f0 !important; color: #94a3b8 !important; cursor: not-allowed; }

        @media (max-width: 991px) {
            .sidebar { position: relative; width: 100%; min-height: auto; border-right: none; }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($swal_success): ?>
            Swal.fire({
                title: "<?php echo addslashes($swal_success['title']); ?>",
                text: "<?php echo addslashes($swal_success['text']); ?>",
                icon: "success",
                timer: <?php echo isset($swal_success['timer']) ? $swal_success['timer'] : 3000; ?>,
                showConfirmButton: false,
                backdrop: `rgba(0,0,0,0.4) left top no-repeat`
            });
            <?php endif; ?>

            <?php if($swal_error): ?>
            Swal.fire({
                title: "<?php echo addslashes($swal_error['title']); ?>",
                text: "<?php echo addslashes($swal_error['text']); ?>",
                icon: "error",
                confirmButtonColor: "#0f766e"
            });
            <?php endif; ?>
        });
    </script>
</head>
</head>
<body>
    <?php
    // --- BADGE COUNTS ---
    // Count Appointments where down_payment is 'Reviewing' or 'Rejected' (Action Needed/Pending)
    $cnt_dp_rev = $conn->query("SELECT COUNT(*) FROM appointment WHERE patient_id='$patient_id' AND down_payment_status IN ('Reviewing', 'Rejected')")->fetch_row()[0];
    
    // Count Unpaid Bills
    $cnt_unpaid_bills = $conn->query("SELECT COUNT(*) FROM billing WHERE patient_id='$patient_id' AND status='Unpaid'")->fetch_row()[0];
    
    // Check for Active Appointment (Pending, Confirmed, Arrived)
    $chk_active_nav = $conn->query("SELECT appointment_id FROM appointment WHERE patient_id='$patient_id' AND status IN ('Pending', 'Confirmed', 'Arrived')");
    $hasActiveAppt = $chk_active_nav->num_rows > 0;
    ?>

    <div class="d-flex flex-column flex-lg-row">
        <nav class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-heartbeat fa-lg"></i> MCMIS Portal
            </div>
            <div class="nav flex-column" role="tablist">
                <a class="nav-link active" data-bs-toggle="pill" href="#home"><i class="fas fa-home"></i> Overview
                    <?php if($cnt_dp_rev > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.65rem;"><?php echo $cnt_dp_rev; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" data-bs-toggle="pill" href="#predictor"><i class="fas fa-baby"></i> Due Date Predictor</a> <a class="nav-link" data-bs-toggle="pill" href="#records"><i class="fas fa-file-medical-alt"></i> Medical Records</a>
                <a class="nav-link" data-bs-toggle="pill" href="#billing"><i class="fas fa-receipt"></i> Billing & Receipts
                    <?php if($cnt_unpaid_bills > 0): ?>
                         <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.65rem;"><?php echo $cnt_unpaid_bills; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" data-bs-toggle="pill" href="#book"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
                <a class="nav-link" data-bs-toggle="pill" href="#feedback"><i class="fas fa-comment-dots"></i> Feedback</a>
                
                <a class="nav-link" href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                
                <a class="nav-link mt-auto text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <main class="main-content flex-grow-1">
            
            <div class="welcome-card">
                <h1 class="h3 fw-bold">Hello, <?php echo htmlspecialchars($patient_name); ?>!</h1>
                <p class="mb-0 opacity-90">Track your health journey, view records, and manage payments.</p>
            </div>

            <?php if (!empty($msg)) echo $msg; ?>

            <div class="tab-content">
                
                <div class="tab-pane fade show active" id="home">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>My Appointments</span>
                            <button class="btn btn-sm btn-primary rounded-pill" onclick="document.querySelector('[href=\'#book\']').click()">+ New</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light"><tr><th>Date</th><th>Time</th><th>Service</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php
                                    // Show all appointments ordered by date and time
                                    $res = $conn->query("SELECT * FROM appointment WHERE patient_id='$patient_id' ORDER BY appointment_date DESC, appointment_time DESC");
                                    if ($res->num_rows > 0):
                                        while($row = $res->fetch_assoc()): 
                                            // Improved Status Logic
                                            $badgeClass = 'secondary';
                                            $statusText = $row['status'];
                                            
                                            switch($statusText) {
                                                case 'Pending':   $badgeClass = 'warning'; break;
                                                case 'Confirmed': $badgeClass = 'primary'; break;
                                                case 'Arrived':   $badgeClass = 'info text-dark'; break;
                                                case 'Completed': $badgeClass = 'success'; break;
                                                case 'Cancelled': $badgeClass = 'danger'; break;
                                                case 'Rejected':  $badgeClass = 'danger'; break;
                                                case 'No Show':   $badgeClass = 'dark'; break;
                                                default:          $badgeClass = 'secondary';
                                            }

                                            // Payment Status Logic
                                            $payStatus = $row['down_payment_status'] ?? 'Pending';
                                            $payBadge = 'secondary';
                                            if($payStatus == 'Reviewing') $payBadge = 'info';
                                            elseif($payStatus == 'Paid') $payBadge = 'success';
                                            elseif($payStatus == 'Rejected') {
                                                $payBadge = 'danger';
                                                // Override Appointment Status to match
                                                $statusText = 'Rejected'; 
                                                $badgeClass = 'danger';
                                            }
                                    ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['service'] ?? 'General Checkup'); ?></td>
                                                
                                                <td>
                                                    <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                                                    <?php if($row['cancel_reason']): ?>
                                                        <br><small class="text-muted fst-italic" style="font-size:0.75rem;">Note: <?php echo htmlspecialchars($row['cancel_reason']); ?></small>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <span class="badge bg-<?php echo $payBadge; ?>"><?php echo $payStatus; ?></span>
                                                    
                                                    <?php 
                                                    // Digital Transaction Check
                                                    $isDigital = (strpos($row['reference_no'] ?? '', 'PAYID') === 0 || ($row['payment_proof'] ?? '') == 'Digital_Transaction');
                                                    
                                                    if($isDigital): 
                                                    ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle" style="font-size: 0.65rem;">
                                                                <i class="fas fa-check-circle me-1"></i> GCash Verified
                                                            </span>
                                                            <div class="text-muted font-monospace" style="font-size: 0.65rem; margin-top:2px;">Ref: <?php echo htmlspecialchars($row['reference_no'] ?? ''); ?></div>
                                                        </div>
                                                    <?php elseif(!empty($row['payment_proof'])): ?>
                                                        <br><a href="uploads/<?php echo $row['payment_proof']; ?>" target="_blank" class="text-primary text-decoration-none small"><i class="fas fa-paperclip"></i> View Proof</a>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php if(in_array($row['status'], ['Pending', 'Confirmed']) && $payStatus !== 'Rejected'): ?>
                                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="openRescheduleModal(<?php echo $row['appointment_id']; ?>, '<?php echo $row['appointment_date']; ?>', '<?php echo $row['appointment_time']; ?>')">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="openClientCancelModal(<?php echo $row['appointment_id']; ?>)">
                                                            Cancel
                                                        </button>
                                                    <?php elseif($payStatus === 'Rejected'): ?>
                                                        <span class="text-muted small">Locked (Rejected)</span>
                                                    <?php elseif($row['status'] == 'Completed'): ?>
                                                        <?php
                                                        // Check if already rated
                                                        $check_rating = $conn->query("SELECT rating FROM ratings WHERE appointment_id='{$row['appointment_id']}'");
                                                        if($check_rating && $check_rating->num_rows > 0): 
                                                            $my_rating = $check_rating->fetch_assoc()['rating'];
                                                        ?>
                                                            <div class="text-warning small" title="You rated this <?php echo $my_rating; ?> stars">
                                                                <?php for($i=1; $i<=5; $i++): ?>
                                                                    <i class="fas fa-star<?php echo $i <= $my_rating ? '' : ' text-muted opacity-25'; ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-warning shadow-sm" onclick="openRatingModal(<?php echo $row['appointment_id']; ?>, '<?php echo htmlspecialchars($row['service'], ENT_QUOTES); ?>')">
                                                                <i class="far fa-star me-1"></i> Rate
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Locked</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="text-center p-4 text-muted">No appointments found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="predictor">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-calculator me-2"></i> Baby Due Date Predictor
                        </div>
                        <div class="card-body">
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="calc-box mb-4">
                                        <h5 class="mb-3 text-secondary">When was the first day of your Last Period (LMP)?</h5>
                                        <div class="input-group mb-3 justify-content-center">
                                            <input type="date" id="client_lmp" class="form-control form-control-lg" style="max-width: 300px;">
                                            <button class="btn btn-primary" onclick="predictDueDate()"><i class="fas fa-magic"></i> Predict</button>
                                        </div>
                                    </div>

                                    <div id="prediction_result" class="result-box">
                                        <div class="text-center">
                                            <p class="text-muted mb-0 uppercase small fw-bold">ESTIMATED DELIVERY DATE</p>
                                            <div class="big-date" id="edd_display">Oct 24, 2026</div>
                                            <hr>
                                            <div class="stat-grid">
                                                <div class="stat-item">
                                                    <div class="small text-muted">Current Status</div>
                                                    <div class="fw-bold fs-5 text-primary" id="aog_display">8 Weeks</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="small text-muted">Days Remaining</div>
                                                    <div class="fw-bold fs-5 text-warning" id="days_display">220 Days</div>
                                                </div>
                                            </div>
                                            <p class="mt-3 small text-muted fst-italic">* This is an estimate based on Naegele's Rule. Please consult your midwife for confirmation.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="records">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white p-3 border-bottom-0">
                             <h5 class="fw-bold text-primary mb-3"><i class="fas fa-file-medical-alt me-2"></i>My Medical Records</h5>
                             <?php
                                $c_fp = $conn->query("SELECT COUNT(*) FROM family_planning_records WHERE patient_id='$patient_id'")->fetch_row()[0];
                                $c_imm = $conn->query("SELECT COUNT(*) FROM immunization_records WHERE patient_id='$patient_id'")->fetch_row()[0];
                             ?>
                             <ul class="nav nav-pills nav-fill bg-light p-1 rounded" id="recordTabs" role="tablist" style="font-size: 0.85rem;">
                                <li class="nav-item"><button class="nav-link active rounded fw-bold" data-bs-toggle="tab" data-bs-target="#tab-prenatal">Prenatal</button></li>
                                <li class="nav-item"><button class="nav-link rounded fw-bold" data-bs-toggle="tab" data-bs-target="#tab-delivery">Delivery</button></li>
                                <li class="nav-item"><button class="nav-link rounded fw-bold" data-bs-toggle="tab" data-bs-target="#tab-newborn">Newborn</button></li>
                                <li class="nav-item"><button class="nav-link rounded fw-bold" data-bs-toggle="tab" data-bs-target="#tab-postnatal">Postnatal</button></li>
                                <?php if($c_fp > 0): ?><li class="nav-item"><button class="nav-link rounded fw-bold" data-bs-toggle="tab" data-bs-target="#tab-fp">Family Planning</button></li><?php endif; ?>
                                <?php if($c_imm > 0): ?><li class="nav-item"><button class="nav-link rounded fw-bold" data-bs-toggle="tab" data-bs-target="#tab-immuno">Immunization</button></li><?php endif; ?>
                            </ul>
                        </div>
                        <div class="card-body p-0">
                            <div class="tab-content" id="recordTabsContent">
                                
                                <!-- PRENATAL TAB -->
                                <div class="tab-pane fade show active" id="tab-prenatal">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date / AOG</th><th>Vitals</th><th>Inclusions Given</th><th>Risks</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM prenatal_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                    $risks = [];
                                                    if(($r['vaginal_bleeding']??'No')=='Yes') $risks[]='Bleeding';
                                                    if(($r['fever']??'No')=='Yes') $risks[]='Fever';
                                                    if(($r['pallor']??'No')=='Yes') $risks[]='Pallor';
                                                    
                                                    // Inclusions Check
                                                    $inc = [];
                                                    if(isset($r['iron_supp']) && $r['iron_supp']) $inc[] = 'Iron';
                                                    if(isset($r['calcium_supp']) && $r['calcium_supp']) $inc[] = 'Calcium';
                                                    if(isset($r['tetanus_toxoid']) && $r['tetanus_toxoid']) $inc[] = 'Tetanus';
                                                    if(isset($r['deworming']) && $r['deworming']) $inc[] = 'Deworming';
                                                    if(isset($r['birth_plan']) && $r['birth_plan']) $inc[] = 'Birth Plan';
                                                    if(isset($r['dental_advice']) && $r['dental_advice']) $inc[] = 'Dental';
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary">
                                                        <?php echo date('M d, Y', strtotime($r['checkup_date'])); ?>
                                                        <div class="small text-muted fw-normal">AOG: <?php echo !empty($r['aog_weeks']) ? $r['aog_weeks'].' wks' : '-'; ?></div>
                                                        <?php 
                                                            $is_anc02 = strpos(($r['findings']??''), '[ANC02') !== false;
                                                            if($is_anc02) echo '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary mt-1" style="font-size:0.65rem">ANC02 / Labor Watch</span>';
                                                            else echo '<span class="badge bg-pink-100 text-pink-700 border border-pink-200 mt-1" style="font-size:0.65rem">ANC01 / Prenatal</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if($is_anc02): 
                                                             $raw = $r['findings'] ?? '';
                                                             preg_match('/Phase: (.*?)( \|| \.| \n|$)/', $raw, $m_ph);
                                                             preg_match('/Dilation: (.*?)( \|| \.| \n|$)/', $raw, $m_di);
                                                        ?>
                                                        <span class="fw-bold text-primary"><?php echo $m_ph[1] ?? 'Monitoring'; ?></span><br>
                                                        Dilation: <?php echo $m_di[1] ?? '-'; ?>
                                                        <?php else: ?>
                                                        BP: <?php echo $r['blood_pressure']; ?><br>Wt: <?php echo $r['weight_kg']; ?>kg<br>FHR: <?php echo $r['fetal_heart_rate']; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if(!empty($inc)): ?>
                                                            <div class="d-flex gap-1 flex-wrap">
                                                                <?php foreach($inc as $i): ?><span class="badge bg-success bg-opacity-10 text-success border border-success"><?php echo $i; ?></span><?php endforeach; ?>
                                                            </div>
                                                        <?php else: echo '<span class="text-muted">-</span>'; endif; ?>
                                                    </td>
                                                    <td><?php echo !empty($risks) ? '<span class="badge bg-danger">'.implode(', ',$risks).'</span>' : '<span class="badge bg-light text-dark border">Normal</span>'; ?></td>
                                                    <td><span class="text-muted fst-italic">"<?php echo $r['findings']; ?>"</span></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="5" class="text-center p-4 text-muted">No records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- ULTRASOUND TAB -->
                                <div class="tab-pane fade" id="tab-ultrasound">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Indication</th><th>Result Summary</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM ultrasound_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td class="fw-bold"><?php echo $r['indication']; ?></td>
                                                    <td><?php echo $r['result_summary']; ?></td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="4" class="text-center p-4 text-muted">No records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- LAB TAB -->
                                <div class="tab-pane fade" id="tab-lab">
                                     <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Test Type</th><th>Status</th><th>Findings & Meds</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM laboratory_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td class="fw-bold"><?php echo $r['test_type']; ?></td>
                                                    <td><span class="badge bg-<?php echo $r['lab_status']=='Normal'?'success':($r['lab_status']=='Pending'?'warning':'danger'); ?>"><?php echo $r['lab_status']; ?></span></td>
                                                    <td><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="4" class="text-center p-4 text-muted">No records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- POSTNATAL TAB -->
                                <div class="tab-pane fade" id="tab-postnatal">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Vitals</th><th>Status</th><th>Inclusions</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM postnatal_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                    // Inclusions
                                                    $inc = [];
                                                    if(isset($r['vit_a']) && $r['vit_a']) $inc[] = 'Vit A';
                                                    if(isset($r['iron_supp']) && $r['iron_supp']) $inc[] = 'Iron';
                                                    if(isset($r['fp_counseling']) && $r['fp_counseling']) $inc[] = 'FP Counseling';
                                                    if(isset($r['perineal_care']) && $r['perineal_care']) $inc[] = 'Perineal Care';
                                                    if(isset($r['breastfeeding_support']) && $r['breastfeeding_support']) $inc[] = 'BF Support';
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td>BP: <?php echo $r['blood_pressure']; ?><br>Temp: <?php echo $r['temperature']; ?></td>
                                                    <td>
                                                        <div class="small">Uterus: <?php echo $r['uterine_involution']; ?></div>
                                                        <div class="small">Lochia: <?php echo $r['lochia']; ?></div>
                                                        <span class="badge bg-info text-dark mt-1"><?php echo $r['breastfeeding_status']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if(!empty($inc)): ?>
                                                            <div class="d-flex gap-1 flex-wrap">
                                                                <?php foreach($inc as $i): ?><span class="badge bg-success bg-opacity-10 text-success border border-success"><?php echo $i; ?></span><?php endforeach; ?>
                                                            </div>
                                                        <?php else: echo '<span class="text-muted text-xs">None</span>'; endif; ?>
                                                    </td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="5" class="text-center p-4 text-muted">No records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- DELIVERY TAB (NEW) -->
                                <div class="tab-pane fade" id="tab-delivery">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date/Time</th><th>Baby Details</th><th>Apgar Scores</th><th>EINC Protocol</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                // Assuming delivery_records table exists
                                                $rec = $conn->query("SELECT * FROM delivery_records WHERE patient_id='$patient_id' ORDER BY delivery_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                    // EINC
                                                    $einc = [];
                                                    if(isset($r['einc_dry']) && $r['einc_dry']) $einc[] = 'Drying';
                                                    if(isset($r['einc_ssc']) && $r['einc_ssc']) $einc[] = 'Skin-to-Skin';
                                                    if(isset($r['einc_cord']) && $r['einc_cord']) $einc[] = 'Cord Care';
                                                    if(isset($r['einc_breast']) && $r['einc_breast']) $einc[] = 'Breastfeeding';
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['delivery_date'])); ?><br><small class="text-muted"><?php echo date('h:i A', strtotime($r['delivery_time'])); ?></small></td>
                                                    <td>
                                                        <strong><?php echo $r['sex']; ?></strong><br>
                                                        Wt: <?php echo $r['weight_g']; ?>g | Len: <?php echo $r['length_cm']; ?>cm
                                                    </td>
                                                    <td>
                                                        1min: <strong><?php echo $r['apgar_1min']; ?></strong><br>
                                                        5min: <strong><?php echo $r['apgar_5min']; ?></strong>
                                                    </td>
                                                    <td>
                                                         <?php if(!empty($einc)): ?>
                                                            <div class="d-flex gap-1 flex-wrap">
                                                                <?php foreach($einc as $i): ?><span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><?php echo $i; ?></span><?php endforeach; ?>
                                                            </div>
                                                        <?php else: echo '<span class="text-muted text-xs">-</span>'; endif; ?>
                                                    </td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="5" class="text-center p-4 text-muted">No delivery records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- NEWBORN TAB (NEW) -->
                                <div class="tab-pane fade" id="tab-newborn">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Weight</th><th>Care Provided (NCP)</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                // Assuming newborn_records table exists
                                                $rec = $conn->query("SELECT * FROM newborn_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                    // NCP
                                                    $ncp = [];
                                                    if(($r['bcg_given']??0)) $ncp[] = 'BCG';
                                                    if(($r['hepb_given']??0)) $ncp[] = 'HepB';
                                                    if(isset($r['vit_k']) && $r['vit_k']) $ncp[] = 'Vit K';
                                                    if(isset($r['eye_prophylaxis']) && $r['eye_prophylaxis']) $ncp[] = 'Eye P.';
                                                    if(($r['nbs_done']??0)) $ncp[] = 'NBS';
                                                    if(isset($r['cord_care']) && $r['cord_care']) $ncp[] = 'Cord Care';
                                                    if(($r['hearing_test']??0)) $ncp[] = 'Hearing';
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td><?php echo $r['weight_g'] ?? $r['birth_weight']; ?> g</td>
                                                    <td>
                                                        <?php if(!empty($ncp)): ?>
                                                            <div class="d-flex gap-1 flex-wrap">
                                                                <?php foreach($ncp as $i): ?><span class="badge bg-info bg-opacity-10 text-dark border border-info"><?php echo $i; ?></span><?php endforeach; ?>
                                                            </div>
                                                        <?php else: echo '<span class="text-muted text-xs">-</span>'; endif; ?>
                                                    </td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="4" class="text-center p-4 text-muted">No newborn records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- DELIVERY TAB -->
                                <div class="tab-pane fade" id="tab-delivery">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date / Time</th><th>Baby Details</th><th>Apgar Scores</th><th>EINC Protocols</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM delivery_records WHERE patient_id='$patient_id' ORDER BY delivery_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                    $einc = [];
                                                    if(isset($r['einc_dry']) && $r['einc_dry']) $einc[] = 'Drying';
                                                    if(isset($r['einc_ssc']) && $r['einc_ssc']) $einc[] = 'Skin-to-Skin';
                                                    if(isset($r['einc_cord']) && $r['einc_cord']) $einc[] = 'Cord Care';
                                                    if(isset($r['einc_breast']) && $r['einc_breast']) $einc[] = 'Breastfeeding';
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['delivery_date'])); ?><br><small class="text-muted"><?php echo date('h:i A', strtotime($r['delivery_time'])); ?></small></td>
                                                    <td><span class="badge bg-<?php echo $r['sex']=='Male'?'primary':'info'; ?>"><?php echo $r['sex']; ?></span><br>Wt: <?php echo $r['weight_g']; ?>g | L: <?php echo $r['length_cm']; ?>cm</td>
                                                    <td>1m: <span class="fw-bold"><?php echo $r['apgar_1min']; ?></span> | 5m: <span class="fw-bold"><?php echo $r['apgar_5min']; ?></span></td>
                                                    <td>
                                                        <?php if(!empty($einc)): ?>
                                                            <?php foreach($einc as $i): ?><span class="badge bg-pink text-dark border border-pink-subtle me-1 mb-1" style="font-size:0.65rem; background-color: #fce7f3; color: #be185d;"><?php echo $i; ?></span><?php endforeach; ?>
                                                        <?php else: echo '<span class="text-muted">-</span>'; endif; ?>
                                                    </td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="5" class="text-center p-4 text-muted">No delivery records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- NEWBORN TAB -->
                                <div class="tab-pane fade" id="tab-newborn">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Growth</th><th>Services Done</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM newborn_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                    $svcs = [];
                                                    if($r['bcg_given']) $svcs[] = 'BCG';
                                                    if($r['hepb_given']) $svcs[] = 'HepB';
                                                    if($r['nbs_done']) $svcs[] = 'NBS';
                                                    if($r['hearing_test']) $svcs[] = 'Hearing';
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td>Weight: <?php echo $r['weight_g']; ?>g</td>
                                                    <td><?php echo !empty($svcs) ? '<span class="badge bg-success">'.implode('</span> <span class="badge bg-success">', $svcs).'</span>' : '-'; ?></td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="4" class="text-center p-4 text-muted">No newborn records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- FAMILY PLANNING TAB -->
                                <div class="tab-pane fade" id="tab-fp">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Method Discussed</th><th>Method Chosen</th><th>Findings</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM family_planning_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td><?php echo $r['method_discussed']; ?></td>
                                                    <td class="fw-bold text-success"><?php echo $r['method_chosen']; ?></td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="4" class="text-center p-4 text-muted">No records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- IMMUNIZATION TAB -->
                                <div class="tab-pane fade" id="tab-immuno">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Vaccine</th><th>Dose</th><th>Findings</th><th>Next Visit</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM immunization_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td class="fw-bold"><?php echo $r['vaccine_type']; ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo $r['dose_number']; ?></span></td>
                                                    <td class="text-muted fst-italic"><?php echo $r['findings']; ?></td>
                                                    <td class="text-danger fw-bold"><?php echo $r['next_visit'] ? date('M d', strtotime($r['next_visit'])) : '-'; ?></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="5" class="text-center p-4 text-muted">No records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- CONSULTATION TAB -->
                                <div class="tab-pane fade" id="tab-consu">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-light small text-muted text-uppercase"><tr><th class="ps-4">Date</th><th>Complaint</th><th>Vitals</th><th>Findings & Meds</th></tr></thead>
                                            <tbody class="small align-middle">
                                                <?php
                                                $rec = $conn->query("SELECT * FROM consultation_records WHERE patient_id='$patient_id' ORDER BY checkup_date DESC");
                                                if($rec->num_rows > 0): while($r = $rec->fetch_assoc()):
                                                ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo date('M d, Y', strtotime($r['checkup_date'])); ?></td>
                                                    <td class="fw-bold"><?php echo $r['chief_complaint']; ?></td>
                                                    <td><?php echo $r['vital_signs']; ?></td>
                                                    <td><span class="fw-bold">Rx:</span> <?php echo $r['medications']; ?><br><span class="text-muted fst-italic">"<?php echo $r['findings']; ?>"</span></td>
                                                </tr>
                                                <?php endwhile; else: echo '<tr><td colspan="4" class="text-center p-4 text-muted">No records found.</td></tr>'; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="billing">
                     <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card h-100 border-warning">
                                <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis">
                                    <i class="fas fa-exclamation-circle me-2"></i> To Settle
                                </div>
                                <div class="card-body">
                                    <table class="table align-middle">
                                        <thead><tr><th>Date</th><th>Bill ID</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                                        <tbody>
                                            <?php
                                            $unpaid = $conn->query("SELECT * FROM billing WHERE patient_id='$patient_id' AND (status='Unpaid' OR status='Rejected' OR status='Pending')");
                                            if($unpaid->num_rows > 0): while($b = $unpaid->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($b['bill_date'])); ?></td>
                                                    <td>#<?php echo $b['bill_id']; ?></td>
                                                    <td class="fw-bold">₱<?php echo number_format($b['total_amount'], 2); ?></td>
                                                    <td>
                                                        <?php if($b['status'] == 'Pending'): ?>
                                                            <span class="badge bg-info text-dark">Verifying...</span>
                                                        <?php elseif($b['status'] == 'Rejected'): ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Unpaid</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if($b['status'] != 'Pending'): ?>
                                                            <span class="text-secondary small fw-bold"><i class="fas fa-store me-1"></i> Please Pay at Counter</span>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Processing</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; else: ?>
                                                <tr><td colspan="5" class="text-center text-muted">You have no pending bills. Great!</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header text-success"><i class="fas fa-history me-2"></i> Payment History & Receipts</div>
                                <div class="card-body">
                                    <table class="table table-hover">
                                        <thead><tr><th>Date Paid</th><th>Bill ID</th><th>Amount Paid</th><th>Method</th><th>Receipt</th></tr></thead>
                                        <tbody>
                                            <?php
                                            $paid = $conn->query("SELECT * FROM billing WHERE patient_id='$patient_id' AND status='Paid' ORDER BY bill_date DESC");
                                            if($paid->num_rows > 0): while($p = $paid->fetch_assoc()): 
                                                $receiptData = htmlspecialchars(json_encode([
                                                    'id' => $p['bill_id'], 'date' => date('M d, Y', strtotime($p['bill_date'])),
                                                    'name' => $patient_name, 'amount' => number_format($p['paid_amount'], 2), 'method' => $p['payment_method']
                                                ])); ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($p['bill_date'])); ?></td>
                                                    <td>#<?php echo $p['bill_id']; ?></td>
                                                    <td class="fw-bold text-success">₱<?php echo number_format($p['paid_amount'], 2); ?></td>
                                                    <td>
                                                        <?php if($p['payment_method'] == 'PhilHealth'): ?>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                                <i class="fas fa-check-circle me-1"></i> PhilHealth
                                                            </span>
                                                        <?php else: ?>
                                                            <?php echo $p['payment_method']; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-success" onclick="viewReceipt(<?php echo $p['bill_id']; ?>, '<?php echo date('M d, Y', strtotime($p['bill_date'])); ?>', '<?php echo $patient_name; ?>', '<?php echo number_format($p['paid_amount'], 2); ?>', '<?php echo $p['payment_method']; ?>')">
                                                            <i class="fas fa-eye me-1"></i> View Receipt
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; else: ?>
                                                <tr><td colspan="5" class="text-center text-muted">No payment history found.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="book">
                     <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                            <h4 class="fw-bold text-primary mb-0">Request New Appointment</h4>
                            <p class="text-muted small">Select a service, choose a date, and secure your slot.</p>
                        </div>
                        <div class="card-body px-4 pb-5">
                            
                            <?php 
                            // Re-check logic for UI lock using the same strict query
                            $active_chk_ui = $conn->query("SELECT appointment_id FROM appointment WHERE patient_id='$patient_id' AND status IN ('Pending', 'Confirmed', 'Arrived') AND down_payment_status != 'Rejected'");
                            $hasActiveAppt = ($active_chk_ui->num_rows > 0);
                            
                            if($hasActiveAppt): ?>
                                <div class="alert alert-warning border-warning border-opacity-25 bg-warning bg-opacity-10 rounded-4 p-4 text-center">
                                    <div class="mb-3">
                                        <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm" style="width: 60px; height: 60px;">
                                            <i class="fas fa-calendar-check fa-2x text-warning"></i>
                                        </div>
                                    </div>
                                    <h5 class="fw-bold text-dark">Active Appointment Found</h5>
                                    <p class="text-muted mb-4" style="max-width: 400px; margin: 0 auto;">
                                        You currently have a pending or confirmed appointment. Please complete or cancel your existing booking before requesting a new one.
                                    </p>
                                    <button class="btn btn-primary rounded-pill px-4 fw-bold" onclick="document.querySelector('[href=\'#home\']').click()">
                                        <i class="fas fa-arrow-left me-2"></i> Go to My Appointments
                                    </button>
                                </div>
                            <?php else: ?>

                            <form method="POST" enctype="multipart/form-data" id="bookingForm">
                                <input type="hidden" name="service" id="selected_service" required>
                                
                                <!-- Step 1: Services (Categorized Tabs) -->
                                <h6 class="fw-bold text-uppercase text-secondary mb-3 small"><i class="fas fa-stethoscope me-2"></i> Select Service</h6>
                                
                                <ul class="nav nav-pills mb-3" id="serviceTabs" role="tablist">
                                    <li class="nav-item"><button class="nav-link active rounded-pill btn-sm small px-4 shadow-sm" data-bs-toggle="pill" data-bs-target="#svc-packages" type="button">Available Packages</button></li>
                                </ul>
                                
                                <div class="alert alert-info py-2 px-3 mb-3 border-0 bg-blue-50 text-blue-800 rounded-3">
                                    <div class="d-flex gap-2">
                                        <i class="fas fa-info-circle mt-1"></i>
                                        <div style="font-size: 0.75rem;">
                                            <strong>Pricing Note:</strong> The prices shown below are the <strong>Total Out-of-Pocket Payment</strong> (Cash Price). The Case Rate is the internal PhilHealth figure.
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-content border rounded-3 p-3 bg-light mb-4" style="max-height: 400px; overflow-y: auto;">
                                    <?php
                                    $cats = ['Packages' => 'svc-packages'];
                                    
                                    // Fetch all services
                                    $all_svcs = [];
                                    $meds_supplies = []; // Store meds/supplies for view-only section
                                    
                                    $q = $conn->query("SELECT * FROM service_pricing WHERE is_active = 1 ORDER BY service_name");
                                    while($s = $q->fetch_assoc()) {
                                        $sc = strtolower($s['service_category']);
                                        
                                        if (strpos($sc, 'medicine') !== false || strpos($sc, 'supplies') !== false) {
                                            $meds_supplies[] = $s; // Separate array
                                        } elseif (strpos($sc, 'package') !== false) {
                                            // ONLY SHOW PACKAGES
                                            $all_svcs['Packages'][] = $s;
                                        }
                                    }
                                    
                                    $is_first = true;
                                    foreach($cats as $key => $id):
                                        $active = $is_first ? 'show active' : '';
                                        $is_first = false;
                                    ?>
                                        <div class="tab-pane fade <?php echo $active; ?>" id="<?php echo $id; ?>" role="tabpanel">
                                            <?php if(empty($all_svcs[$key])): ?>
                                                <p class="text-muted small text-center my-3">No services available.</p>
                                            <?php else: ?>
                                                <div class="row g-2">
                                                    <?php foreach($all_svcs[$key] as $item): 
                                                        // PRICE OVERRIDE MAP (Same as Landing Page)
                                                        $price_overrides = [
                                                            'MCP01' => 12500.00,
                                                            'NSD01' => 11000.00,
                                                            'NCP'   => 5000.00,
                                                            'ANC01' => 1500.00,
                                                            'ANC02' => 2000.00
                                                        ];

                                                        // Detect Code
                                                        $parts = explode(' - ', $item['service_name']);
                                                        $code = isset($parts[1]) ? trim($parts[0]) : '';
                                                        if(empty($code)) {
                                                            if(preg_match('/^([A-Z0-9]+)\s*-/', $item['service_name'], $m)) $code = $m[1];
                                                        }

                                                        $display_price = isset($price_overrides[$code]) ? $price_overrides[$code] : $item['price'];
                                                        // Case Rate Map
                                                        $case_rates = [
                                                            'MCP01' => 15600.00,
                                                            'NSD01' => 12675.00,
                                                            'NCP'   => 5752.50,
                                                            'ANC01' => 2925.00,
                                                            'ANC02' => 4192.50
                                                        ];
                                                        $cr = isset($case_rates[$code]) ? $case_rates[$code] : 0;
                                                    ?>
                                                    <div class="col-12 col-md-6">
                                                        <div class="service-item p-3 bg-white border rounded shadow-sm cursor-pointer d-flex justify-content-between align-items-center hover-shadow transition-all" onclick="selectService(this, '<?php echo addslashes($item['service_name']); ?>', <?php echo $display_price; ?>)">
                                                            <div>
                                                                <div class="fw-bold text-dark small mb-1"><?php echo htmlspecialchars($item['service_name']); ?></div>
                                                                
                                                                <?php if($cr > 0): ?>
                                                                    <div class="mb-1">
                                                                        <span class="badge bg-success text-white border border-success px-2 py-1 shadow-sm" style="font-size: 0.75rem;">
                                                                            <i class="fas fa-shield-alt me-1"></i> PhilHealth Case Rate: ₱<?php echo number_format($cr, 2); ?>
                                                                        </span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <div class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                                                            </div>
                                                            <div class="text-end">
                                                                <div class="text-primary fw-bold small whitespace-nowrap">₱<?php echo number_format($display_price, 2); ?></div>
                                                                <div class="text-[10px] text-muted fw-bold uppercase">Cash Price</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="row g-4 mb-4">
                                    <!-- Step 2: Date & Time (Visual Calendar) -->
                                    <div class="col-md-6">
                                        <h6 class="fw-bold text-uppercase text-secondary mb-3 small"><i class="fas fa-calendar-alt me-2"></i> Date & Time</h6>
                                        <div class="p-4 bg-white rounded-4 shadow-sm border">
                                            
                                            <!-- Calendar Header -->
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <button type="button" class="btn btn-light btn-sm rounded shadow-sm" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                                                <div class="fw-bold text-primary" id="current_month">January 2026</div>
                                                <button type="button" class="btn btn-light btn-sm rounded shadow-sm" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                                            </div>
                                            
                                            <!-- Calendar Grid -->
                                            <div class="calendar-grid mb-2">
                                                <div class="calendar-day-header">SUN</div>
                                                <div class="calendar-day-header">MON</div>
                                                <div class="calendar-day-header">TUE</div>
                                                <div class="calendar-day-header">WED</div>
                                                <div class="calendar-day-header">THU</div>
                                                <div class="calendar-day-header">FRI</div>
                                                <div class="calendar-day-header">SAT</div>
                                            </div>
                                            <div id="calendar_days" class="calendar-grid mb-3"></div>
                                            
                                            <input type="hidden" name="date" id="selected_date" required>

                                            <!-- Time Slots Section -->
                                            <div id="time_slots_section" class="d-none border-top pt-3">
                                                <label class="form-label small fw-bold text-muted uppercase d-block mb-2">Select Time Slot</label>
                                                <div id="time_slots_container" class="g-2 row"></div>
                                                <input type="hidden" name="time" id="selected_time" required>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3: Secure Payment -->
                                    <div class="col-md-6">
                                        <h6 class="fw-bold text-uppercase text-secondary mb-3 small"><i class="fas fa-wallet me-2"></i> Down Payment</h6>
                                        <div class="p-4 bg-teal-50 border border-teal-200 rounded-4 text-center h-100 d-flex flex-column justify-content-center">
                                            <div class="mb-3">
                                                <div class="btn-group w-100 mb-3" role="group">
                                                    <input type="radio" class="btn-check" name="payment_type" id="opt_dp" value="dp" checked onchange="updateAmountDue()">
                                                    <label class="btn btn-outline-dark btn-sm fw-bold" for="opt_dp">50% Downpayment</label>

                                                    <input type="radio" class="btn-check" name="payment_type" id="opt_full" value="full" onchange="updateAmountDue()">
                                                    <label class="btn btn-outline-dark btn-sm fw-bold" for="opt_full">Full Payment</label>
                                                </div>

                                                <div class="small fw-bold text-muted uppercase tracking-wide">Amount Due</div>
                                                <div class="display-6 fw-bold text-teal-700" id="display_amount">₱0.00</div>
                                                <div class="text-[10px] text-teal-600">Secure Payment via PayMongo</div>
                                                <input type="hidden" id="selected_service_price" value="0">
                                            </div>
                                            
                                            <button type="button" class="btn btn-dark w-100 py-3 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" onclick="openPayMongoModal()">
                                                <i class="fas fa-lock"></i> Pay Securely
                                            </button>
                                            
                                            <div id="payment_status_display" class="mt-3 text-start bg-white p-3 rounded border d-none">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="bg-success text-white rounded-circle p-1"><i class="fas fa-check"></i></div>
                                                    <div class="lh-1">
                                                        <div class="fw-bold text-success text-xs uppercase">Payment Authorized</div>
                                                        <div class="text-muted text-[10px] font-monospace mt-1" id="display_ref_no">Ref: -</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="hidden" name="reference_no" id="paymongo_ref_no" required>
                                            <input type="hidden" name="force_payment_mode" id="force_payment_mode" value="dp">
                                            <!-- File upload removed for simulated payment -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- PayMongo Simulation Modal -->
                                <div class="modal fade" id="payMongoModal" data-bs-backdrop="static" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg overflow-hidden">
                                            <div class="modal-header border-0 bg-white pb-0">
                                                <div class="fw-bold text-xl tracking-tight">pay<span class="text-success">mongo</span></div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="bg-light p-3 rounded-3 mb-4">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="text-muted small">Merchant</span>
                                                        <span class="fw-bold small text-dark">MCMIS Clinic</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="text-muted small">Service</span>
                                                        <span class="fw-bold small text-dark" id="modal_service_display">-</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="text-muted small">Appointment</span>
                                                        <span class="fw-bold small text-dark" id="modal_datetime_display">-</span>
                                                    </div>
                                                    <div class="border-top my-2"></div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="text-muted small fw-bold">TOTAL AMOUNT</span>
                                                        <span class="fw-bold text-primary fs-5" id="modal_amount_display">₱0.00</span>
                                                    </div>
                                                </div>
                                                
                                                <h6 class="fw-bold small mb-3 text-secondary uppercase">Select Payment Method</h6>
                                                
                                                <div id="pm_methods" class="d-grid gap-2 mb-4">
                                                    <button type="button" class="btn btn-outline-dark text-start p-3 d-flex align-items-center justify-content-between hover-shadow transition-all" onclick="simulateProcessing('GCash')">
                                                        <span class="fw-bold"><i class="fas fa-mobile-alt me-2 text-primary"></i> GCash</span>
                                                        <i class="fas fa-chevron-right text-muted small"></i>
                                                    </button>
                                                </div>
                                                
                                                <div id="pm_qr_scan" class="text-center d-none py-3">
                                                    <p class="small fw-bold mb-3">Scan QR Code to Pay</p>
                                                    <div class="bg-white p-2 d-inline-block border rounded mb-3 position-relative overflow-hidden">
                                                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/QR_code_for_mobile_English_Wikipedia.svg/1200px-QR_code_for_mobile_English_Wikipedia.svg.png" style="width: 150px; opacity: 0.9;">
                                                        <div style="position:absolute; top:0; left:0; width:100%; height:3px; background:#0d6efd; box-shadow: 0 0 10px #0d6efd; animation: scanLine 2s ease-in-out infinite;"></div>
                                                        <style>@keyframes scanLine { 0%{top:0} 50%{top:100%} 100%{top:0} }</style>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                                         <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                         <span class="text-primary small fw-bold">Waiting for scan...</span>
                                                    </div>
                                                </div>
                                                
                                                <div id="pay_processing" class="text-center d-none py-5">
                                                    <div class="spinner-border text-primary mb-3" role="status"></div>
                                                    <div class="text-dark fw-bold animate-pulse">Processing Payment...</div>
                                                    <div class="text-muted text-xs mt-1">Please do not close this window</div>
                                                </div>
                                                
                                                <div id="pay_success" class="text-center d-none py-4">
                                                    <div class="text-success text-6xl mb-3"><i class="fas fa-check-circle fa-3x"></i></div>
                                                    <h5 class="fw-bold text-dark">Payment Successful!</h5>
                                                    <p class="text-muted small">Ref: <span id="modal_ref_display" class="font-monospace"></span></p>
                                                </div>

                                                <div id="pm_error" class="text-center d-none py-4">
                                                    <div class="text-danger text-5xl mb-3"><i class="fas fa-exclamation-circle fa-3x"></i></div>
                                                    <h5 class="fw-bold text-dark mb-2">Payment Failed</h5>
                                                    <p class="text-danger small mb-4 fw-bold" id="pm_error_msg">Unknown Error</p>
                                                    <button type="button" class="btn btn-outline-dark btn-sm rounded-3 fw-bold" data-bs-dismiss="modal">Close & Fix</button>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light py-2 justify-content-center">
                                                <div class="text-[10px] text-muted"><i class="fas fa-lock small me-1"></i> Secured by PayMongo (Test Mode)</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" name="book" id="btn_submit_booking" class="btn btn-primary btn-lg w-100 py-3 rounded-4 fw-bold shadow-sm" style="opacity: 0.6; cursor: not-allowed;" disabled>
                                        <i class="fas fa-check-circle me-2"></i> Submit Booking Request
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>



                <!-- QR Modal -->
                <div class="modal fade" id="qrModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">
                            <div class="modal-header border-0 pb-0">
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center pt-0 pb-4">
                                <h6 class="fw-bold text-primary mb-3">Scan to Pay</h6>
                                <div class="bg-light p-3 rounded-3 d-inline-block mb-3 border">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/d/d0/QR_code_for_mobile_English_Wikipedia.svg" alt="GCash QR" class="img-fluid" style="width: 180px;">
                                </div>
                                <p class="small text-muted mb-0">MCMIS Official GCash</p>
                                <p class="fw-bold text-dark fs-5">0912 345 6789</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="feedback">
                     <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">Send Feedback or Complaint</div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3"><label class="form-label">Your Message</label><textarea name="message" class="form-control" rows="5" required placeholder="Tell us about your experience..."></textarea></div>
                                        <button type="submit" name="submit_feedback" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i> Send to Admin</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">Previous Feedback</div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <?php
                                    $feed_query = $conn->query("SELECT * FROM feedback WHERE patient_id='$patient_id' ORDER BY date_submitted DESC");
                                    if($feed_query->num_rows > 0): while($f = $feed_query->fetch_assoc()): ?>
                                        <div class="border rounded p-3 mb-3 bg-light">
                                            <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($f['date_submitted'])); ?></small>
                                            <p class="mb-2 fw-bold text-dark"><?php echo htmlspecialchars($f['message']); ?></p>
                                            <?php if($f['reply']): ?>
                                                <div class="mt-2 pt-2 border-top border-secondary-subtle">
                                                    <small class="text-primary fw-bold"><i class="fas fa-user-shield me-1"></i> Admin Reply:</small>
                                                    <p class="mb-0 text-secondary"><?php echo htmlspecialchars($f['reply']); ?></p>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Waiting for reply...</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; else: ?>
                                        <p class="text-muted text-center py-4">No feedback history.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reschedule Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appt_id" id="edit_id">
                        <div class="mb-3"><label class="form-label">New Date</label><input type="date" name="new_date" id="edit_date" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">New Time</label><input type="time" name="new_time" id="edit_time" class="form-control" required></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_appt" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clientCancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="cancel_id" id="client_cancel_id">
                        <p>Are you sure you want to cancel? Please select a reason:</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason</label>
                            <select name="cancel_reason_select" id="client_reason_select" class="form-select" onchange="toggleClientReasonInput(this)" required>
                                <option value="">-- Select Reason --</option>
                                <option value="Schedule Conflict">Schedule Conflict</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Financial Issue">Financial Issue</option>
                                <option value="Feeling Better">Feeling Better</option>
                                <option value="Change of Mind">Change of Mind</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>

                        <div class="mb-3" id="client_other_reason_div" style="display:none;">
                            <label class="form-label fw-bold">Please specify:</label>
                            <textarea name="cancel_reason_note" id="client_reason_note" class="form-control" rows="3" placeholder="Type your reason here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Appointment</button>
                        <button type="submit" name="cancel_appt_submit" class="btn btn-danger">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rating Modal -->
    <div class="modal fade" id="ratingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="modal-title text-white fw-bold"><i class="fas fa-star me-2"></i>Rate Your Experience</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="appointment_id" id="rating_appt_id">
                        
                        <div class="text-center mb-4">
                            <div class="mb-2">
                                <span class="badge bg-light text-dark border px-3 py-2">
                                    <i class="fas fa-heartbeat text-danger me-1"></i>
                                    <span id="rating_service_name" class="fw-bold">Service Name</span>
                                </span>
                            </div>
                            <p class="text-muted small mb-0">How would you rate this service?</p>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-center d-block mb-3">Your Rating</label>
                            <div class="star-rating text-center" style="font-size: 2.5rem; cursor: pointer;">
                                <input type="hidden" name="rating" id="rating_value" required>
                                <i class="far fa-star" data-rating="1"></i>
                                <i class="far fa-star" data-rating="2"></i>
                                <i class="far fa-star" data-rating="3"></i>
                                <i class="far fa-star" data-rating="4"></i>
                                <i class="far fa-star" data-rating="5"></i>
                            </div>
                            <div class="text-center mt-2">
                                <small id="rating_text" class="text-muted fst-italic">Click to rate</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Share Your Experience <span class="text-muted small">(Optional)</span></label>
                            <textarea name="review_text" class="form-control" rows="4" placeholder="Tell us about your experience with this service..."></textarea>
                            <div class="form-text"><i class="fas fa-info-circle me-1"></i>Your feedback helps us improve our services</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_rating" class="btn btn-primary" id="submit_rating_btn" disabled>
                            <i class="fas fa-paper-plane me-2"></i>Submit Rating
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Custom Receipt Modal -->
    <div id="receiptModal" class="custom-modal-overlay">
        <div class="custom-modal-box">
             <!-- Header -->
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h6 class="fw-bold text-dark m-0">Official Receipt</h6>
                <button onclick="document.getElementById('receiptModal').classList.remove('active')" class="btn btn-sm text-secondary fw-bold fs-5" style="border:none; background:none;">&times;</button>
            </div>
            
            <!-- Scrollable Body -->
            <div class="p-4" style="background-color: #f3f4f6; max-height: 70vh; overflow-y: auto;">
                <div id="printableReceipt" class="p-4 bg-white" style="font-family: sans-serif; font-size: 0.875rem; position: relative; border: 2px dashed #d1d5db; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <!-- Header Info -->
                    <div class="text-center mb-4">
                        <img src="mababy.jpg" style="height: 60px; display: block; margin: 0 auto 0.75rem;">
                        <h5 style="font-weight: 700; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: #1f2937; margin-bottom: 0;">Mother Therese Mothers Clinic Lying - In</h5>
                        <p style="font-size: 0.625rem; color: #6b7280; margin: 0;">97 B.S. Aquino Ave, Tangos, Baliwag City</p>
                        <p style="font-size: 0.625rem; color: #6b7280; margin: 0;">Contact: 0917 843 4589</p>
                    </div>
                    
                    <!-- Date/ID Row -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 1rem; border-bottom: 1px dashed #d1d5db; margin-bottom: 1.5rem;">
                        <div>
                            <p style="font-size: 0.625rem; color: #6b7280; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Receipt No.</p>
                            <p style="font-family: monospace; font-size: 1.25rem; font-weight: 700; color: #1f2937; line-height: 1; margin: 0;">#<span id="r_id">000000</span></p>
                        </div>
                        <div class="text-end">
                            <p style="font-size: 0.625rem; color: #6b7280; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Date Paid</p>
                            <p style="font-weight: 700; color: #1f2937; line-height: 1; margin: 0;"><span id="r_date">Jan 01, 2026</span></p>
                        </div>
                    </div>

                    <!-- Patient -->
                    <div class="mb-4">
                        <p style="font-size: 0.625rem; color: #6b7280; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Received From</p>
                        <p style="font-size: 1.125rem; font-weight: 700; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.25rem; width: 100%; margin: 0;"><span id="r_name">Patient Name</span></p>
                    </div>

                    <!-- Amount Box -->
                    <div style="background-color: #f9fafb; padding: 1rem; border-radius: 0.75rem; border: 1px solid #f3f4f6; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="color: #6b7280; font-size: 0.75rem;">Amount Paid</span>
                            <span style="font-weight: 700; font-size: 1.125rem; color: #1f2937;">₱<span id="r_amount1">0.00</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #6b7280; font-size: 0.75rem;">Payment Method</span>
                            <span style="font-weight: 700; font-size: 0.75rem; text-transform: uppercase; background: white; border: 1px solid #e5e7eb; padding: 0.25rem 0.5rem; border-radius: 0.25rem; color: #374151;"><span id="r_method">CASH</span></span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="text-center mt-4">
                        <div style="display: inline-flex; align-items: center; gap: 0.5rem; border: 1px solid #22c55e; color: #16a34a; background-color: #f0fdf4; padding: 0.375rem 1rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;">
                            <i class="fas fa-check-circle"></i> Payment Complete
                        </div>
                        <p style="margin-top: 1rem; font-size: 0.625rem; color: #9ca3af; font-style: italic; margin-bottom: 0;">This serves as your official proof of payment.<br>Thank you for trusting us!</p>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="p-3 bg-light border-top text-end">
                <button onclick="document.getElementById('receiptModal').classList.remove('active')" class="btn btn-sm btn-secondary me-1">Close</button>
                <button onclick="printReceipt()" class="btn btn-sm btn-primary"><i class="fas fa-print me-1"></i> Print Receipt</button>
            </div>
        </div>
    </div>

    <!-- Bill Pay Modal -->
    <div class="modal fade" id="billPayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Pay Bill: #<span id="pay_bill_id_display"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body text-center">
                        <input type="hidden" name="bill_id" id="pay_bill_id">
                        
                        <p class="mb-2">Total Amount Due:</p>
                        <h2 class="text-primary fw-bold mb-4">₱<span id="pay_bill_amount"></span></h2>
                        
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Scan to Pay via GCash</h6>
                                <img src="https://upload.wikimedia.org/wikipedia/commons/d/d0/QR_code_for_mobile_English_Wikipedia.svg" class="img-fluid mx-auto border p-2 bg-white rounded" style="max-width: 180px;">
                                <div class="mt-2 text-muted small">MCMIS Official GCash</div>
                            </div>
                        </div>

                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold">Upload Payment Screenshot / Receipt</label>
                            <input type="file" name="bill_proof" class="form-control" accept="image/*" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="pay_bill_proof" class="btn btn-primary w-100">Submit Payment for Verification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openRescheduleModal(id, date, time) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_time').value = time;
            new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
        }

        // NEW: Client Cancel Logic
        function openClientCancelModal(id) {
            document.getElementById('client_cancel_id').value = id;
            // Reset fields
            document.getElementById('client_reason_select').value = "";
            document.getElementById('client_other_reason_div').style.display = 'none';
            document.getElementById('client_reason_note').required = false;
            new bootstrap.Modal(document.getElementById('clientCancelModal')).show();
        }

        function toggleClientReasonInput(select) {
            var noteDiv = document.getElementById('client_other_reason_div');
            var noteInput = document.getElementById('client_reason_note');
            
            if(select.value === 'Others') {
                noteDiv.style.display = 'block';
                noteInput.required = true;
            } else {
                noteDiv.style.display = 'none';
                noteInput.required = false;
                noteInput.value = ''; // clear if hidden
            }
        }

        function viewReceipt(id, date, name, amount, method) {
            document.getElementById('r_id').innerText = String(id).padStart(6, '0');
            document.getElementById('r_date').innerText = date;
            document.getElementById('r_name').innerText = name;
            document.getElementById('r_amount1').innerText = amount;

            document.getElementById('r_method').innerText = method;
            // Use custom modal class logic
            document.getElementById('receiptModal').classList.add('active');
        }

        function printReceipt() {
            var content = document.getElementById('printableReceipt').innerHTML;
            var win = window.open('', '', 'height=600,width=400');
            // Use Tailwind CDN for printing to ensure exact match with design
            win.document.write('<html><head><title>Receipt</title><script src="https://cdn.tailwindcss.com"><\/script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head><body class="bg-gray-100 flex items-center justify-center min-h-screen"><div class="bg-white p-4 w-full">' + content + '</div></body></html>');
            win.document.close();
            setTimeout(() => {
                win.print();
            }, 500);
        }

        function predictDueDate() {
            let lmpInput = document.getElementById('client_lmp').value;
            if(!lmpInput) { alert("Please select your Last Menstrual Period date."); return; }

            let lmpDate = new Date(lmpInput);
            let today = new Date();

            let eddDate = new Date(lmpDate);
            eddDate.setDate(lmpDate.getDate() + 280);

            let diffTime = Math.abs(today - lmpDate);
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
            let weeks = Math.floor(diffDays / 7);

            let remainingTime = eddDate - today;
            let remainingDays = Math.ceil(remainingTime / (1000 * 60 * 60 * 24));

            if(remainingDays < 0) remainingDays = 0; 

            document.getElementById('edd_display').innerText = eddDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('aog_display').innerText = weeks + " Weeks Pregnant";
            document.getElementById('days_display').innerText = remainingDays + " Days to go";
            
            document.getElementById('prediction_result').style.display = 'block';
        }

        // --- NEW BOOKING JS ---
        function selectService(element, serviceName, price) {
            // Deselect ALL service items globally across all tabs
            document.querySelectorAll('.service-item').forEach(el => {
                el.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
                el.classList.add('border', 'bg-white');
            });

            // Select clicked item
            // Remove standard border/bg and add active styles
            element.classList.remove('border', 'bg-white');
            element.classList.add('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');

            // Set hidden input value
            const svcInput = document.getElementById('selected_service');
            if(svcInput) svcInput.value = serviceName;
            
            // Store Price and Recalculate
            const priceInput = document.getElementById('selected_service_price');
            if(priceInput) {
                priceInput.value = price;
                updateAmountDue();
            }

            // RESET Payment Status (Must pay again for new service/price)
            document.getElementById('paymongo_ref_no').value = "";
            document.getElementById('display_ref_no').innerText = "Ref: -";
            document.getElementById('payment_status_display').classList.add('d-none');
            
            // Disable submit button until payment
            const btn = document.getElementById('btn_submit_booking');
            if(btn) {
                btn.disabled = true;
                btn.style.opacity = '0.6';
                btn.style.cursor = 'not-allowed';
            }
        }

        function updateAmountDue() {
            const priceInput = document.getElementById('selected_service_price');
            const amountDisplay = document.getElementById('display_amount');
            
            if (!priceInput || !amountDisplay) return;
            
            const price = parseFloat(priceInput.value) || 0;
            
            // Get selected radio
            const optionEl = document.querySelector('input[name="payment_type"]:checked');
            const option = optionEl ? optionEl.value : 'dp';
            
            // Sync to hidden input
            if(document.getElementById('force_payment_mode')) {
                 document.getElementById('force_payment_mode').value = option;
            }
            
            let amount = 0;
            if (option === 'dp') {
                amount = price * 0.5; // 50% Down Payment
            } else {
                amount = price; // Full Payment
            }
            
            amountDisplay.innerText = '₱' + amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function openBillPayModal(id, amount) {
            document.getElementById('pay_bill_id').value = id;
            document.getElementById('pay_bill_id_display').innerText = id;
            document.getElementById('pay_bill_amount').innerText = amount;
            new bootstrap.Modal(document.getElementById('billPayModal')).show();
        }

        // Rating Modal Functions
        function openRatingModal(appointmentId, serviceName) {
            document.getElementById('rating_appt_id').value = appointmentId;
            document.getElementById('rating_service_name').innerText = serviceName;
            document.getElementById('rating_value').value = '';
            document.getElementById('rating_text').innerText = 'Click to rate';
            document.getElementById('submit_rating_btn').disabled = true;
            
            // Reset stars
            document.querySelectorAll('.star-rating i').forEach(star => {
                star.classList.remove('fas');
                star.classList.add('far');
                star.style.color = '#ddd';
            });
            
            new bootstrap.Modal(document.getElementById('ratingModal')).show();
        }

        // Star Rating Interaction
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-rating i');
            const ratingInput = document.getElementById('rating_value');
            const ratingText = document.getElementById('rating_text');
            const submitBtn = document.getElementById('submit_rating_btn');
            
            const ratingLabels = {
                1: 'Poor',
                2: 'Fair',
                3: 'Good',
                4: 'Very Good',
                5: 'Excellent'
            };

            stars.forEach(star => {
                // Hover effect
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    highlightStars(rating, '#ffc107');
                });

                // Click to select
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    ratingInput.value = rating;
                    ratingText.innerText = ratingLabels[rating];
                    ratingText.style.color = '#ffc107';
                    ratingText.style.fontWeight = 'bold';
                    submitBtn.disabled = false;
                    highlightStars(rating, '#ffc107', true);
                });
            });

            // Reset on mouse leave
            document.querySelector('.star-rating').addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value) || 0;
                highlightStars(currentRating, '#ffc107', true);
            });

            function highlightStars(rating, color, permanent = false) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.remove('far');
                        star.classList.add('fas');
                        star.style.color = color;
                    } else if (!permanent || ratingInput.value == '') {
                        star.classList.remove('fas');
                        star.classList.add('far');
                        star.style.color = '#ddd';
                    }
                });
            }
        });

        // --- Visual Calendar Logic ---
        
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Data from PHP
        const blockedDates = <?php 
            $bd_query = $conn->query("SELECT blocked_date FROM blocked_dates");
            $dates = [];
            while($d = $bd_query->fetch_assoc()) $dates[] = $d['blocked_date'];
            echo json_encode($dates);
        ?>;
        
        // Render Calendar
        function renderCalendar() {
            const calendarDays = document.getElementById('calendar_days');
            if (!calendarDays) return;

            const firstDay = new Date(currentYear, currentMonth, 1);
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay(); // 0 = Sun

            document.getElementById('current_month').textContent = 
                firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

            let html = '';
            
            // Empty cells
            for (let i = 0; i < startingDayOfWeek; i++) {
                html += '<div></div>';
            }

            // Days
            for (let day = 1; day <= daysInMonth; day++) {
                const currentDate = new Date(currentYear, currentMonth, day);
                const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                const isPast = currentDate < today;
                const isSunday = currentDate.getDay() === 0;
                const isBlocked = blockedDates.includes(dateStr);
                const isToday = currentDate.getTime() === today.getTime();
                
                let classes = 'calendar-day';
                let onclick = '';

                if (isPast || isBlocked) {
                    classes += ' disabled';
                } else {
                    if (isToday) classes += ' today';
                    onclick = `onclick="selectDate('${dateStr}')"`;
                }

                html += `<div class="${classes}" ${onclick} data-date="${dateStr}">${day}</div>`;
            }

            calendarDays.innerHTML = html;
        }

        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            } else if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        }

        function selectDate(dateStr) {
            // Update inputs
            document.getElementById('selected_date').value = dateStr;
            
            // Highlight selected
            document.querySelectorAll('.calendar-day').forEach(el => {
                el.classList.remove('selected');
                if (el.dataset.date === dateStr) el.classList.add('selected');
            });
            
            // Fetch Slots
            fetchTimeSlots(dateStr);
        }

        function fetchTimeSlots(date) {
            const container = document.getElementById('time_slots_container');
            const section = document.getElementById('time_slots_section');
            
            section.classList.remove('d-none');
            container.innerHTML = '<div class="col-12 text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading slots...</div>';
            
            fetch('get_available_slots.php?date=' + date)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) throw new Error(data.error);
                    renderTimeSlots(data.slots);
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML = '<div class="col-12 text-center text-danger">Error loading slots.</div>';
                });
        }

        function renderTimeSlots(slots) {
            const container = document.getElementById('time_slots_container');
            
            if (slots.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-muted">No slots available.</div>';
                return;
            }

            let html = '';
            slots.forEach(slot => {
                const isAvailable = slot.available; // Now false if is_past
                const isPast = slot.is_past; 
                const booked = slot.booked || 0;
                const max = 4; // Max 4 Midwives
                const left = max - booked;
                
                let btnClass = isAvailable ? 'slot-available' : 'slot-full-booked';
                let disabled = isAvailable ? '' : 'disabled';
                let onclick = isAvailable ? `onclick="selectTimeSlot('${slot.time}')"` : '';
                
                let statusBadge;
                
                if (isPast) {
                    btnClass = 'slot-past'; 
                    statusBadge = '<span class="badge bg-secondary">Past</span>';
                } else if (!isAvailable) {
                    btnClass = 'slot-full-booked';
                    statusBadge = '<span class="badge bg-danger text-uppercase" style="font-size: 9px;">Fully Booked</span>';
                } else if (booked === 0) {
                    statusBadge = '<span class="text-success small fw-bold"><i class="fas fa-check-circle"></i> Available</span>';
                } else {
                    let color = left <= 2 ? 'text-warning' : 'text-primary';
                    statusBadge = `<span class="${color} small fw-bold">${left} slots left</span>`;
                }

                html += `
                    <div class="col-4">
                        <button type="button" class="time-slot-btn ${btnClass}" ${onclick} ${disabled} data-time="${slot.time}">
                            <div class="fw-bold small time-text">${slot.time}</div>
                            <div style="font-size: 11px; margin-top: 2px;">${statusBadge}</div>
                        </button>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function selectTimeSlot(time) {
            document.getElementById('selected_time').value = time;
            
            document.querySelectorAll('.time-slot-btn').forEach(btn => {
                btn.classList.remove('selected');
                if (btn.dataset.time === time) btn.classList.add('selected');
            });

            // Note: Booking button is now enabled ONLY after payment verification.
        }

        // Initialize on Load
        document.addEventListener('DOMContentLoaded', function() {
            renderCalendar();
        });

        // PayMongo Simulation Logic
        function openPayMongoModal() {
            // Validation
            const service = document.getElementById('selected_service').value;
            const date = document.getElementById('selected_date').value;
            const time = document.getElementById('selected_time').value;

            let errorMsg = "";
            let hasError = false;

            if (!service) {
                errorMsg = "Please select a Service plan.";
                hasError = true;
            } else if (!date) {
                errorMsg = "Please select an Appointment Date.";
                 hasError = true;
            } else if (!time) {
                errorMsg = "Please select a Time Slot.";
                hasError = true;
            }

            // Reset modal state first (ensure everything hidden)
            document.getElementById('pm_methods').classList.add('d-none');
            document.getElementById('pm_qr_scan').classList.add('d-none');
            document.getElementById('pay_processing').classList.add('d-none');
            document.getElementById('pay_success').classList.add('d-none');
            document.getElementById('pm_error').classList.add('d-none');

            if (hasError) {
                document.getElementById('pm_error').classList.remove('d-none');
                document.getElementById('pm_error_msg').innerText = errorMsg;
            } else {
                 document.getElementById('pm_methods').classList.remove('d-none');
                 
                 // Update Modal Summary Details
                 const serviceName = document.getElementById('selected_service').value;
                 
                 document.getElementById('modal_service_display').innerText = serviceName;
                 document.getElementById('modal_datetime_display').innerText = date + ' ' + time;

                 // Update amount in modal
                 const amountText = document.getElementById('display_amount').innerText;
                 document.getElementById('modal_amount_display').innerText = amountText;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('payMongoModal'));
            modal.show();
        }

        function simulateProcessing(method) {
            // 1. Show QR Code
            document.getElementById('pm_methods').classList.add('d-none');
            document.getElementById('pm_qr_scan').classList.remove('d-none');
            
            // 2. Simulate Scan Delay (3s)
            setTimeout(() => {
                document.getElementById('pm_qr_scan').classList.add('d-none');
                document.getElementById('pay_processing').classList.remove('d-none');
                
                // 3. Process Payment
                setTimeout(() => {
                    // Success
                    document.getElementById('pay_processing').classList.add('d-none');
                    document.getElementById('pay_success').classList.remove('d-none');
                    
                    // Gen Reference
                    const ref = 'PAYID-' + Math.random().toString(36).substr(2, 9).toUpperCase();
                    document.getElementById('modal_ref_display').innerText = ref;
                    
                    // Update Main Form
                    document.getElementById('paymongo_ref_no').value = ref;
                    document.getElementById('display_ref_no').innerText = 'Ref: ' + ref;
                    document.getElementById('payment_status_display').classList.remove('d-none');

                    // Enable Booking Button
                    const btn = document.getElementById('btn_submit_booking');
                    if(btn) {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    }
                    
                    // Close modal after delay
                    setTimeout(() => {
                        const el = document.getElementById('payMongoModal');
                        const modal = bootstrap.Modal.getInstance(el);
                        if(modal) modal.hide();
                    }, 1500);
                    
                }, 1500);
            }, 3000);
        }
    </script>
</body>
</html>