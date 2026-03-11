<?php
ob_start(); 
session_start(); 
include 'config.php';

// Security Check
if (!isset($_SESSION['user_id']) || strcasecmp($_SESSION['role'], 'Admin') !== 0) { 
    header("Location: login.php"); 
    exit; 
}

$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Administrator';

// --- LOGIC 1: TABS ---
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
if(isset($_GET['filter'])) $active_tab = 'reports';

// --- LOGIC 1.5: SCHEDULE MANAGEMENT ---
if (isset($_POST['block_date'])) {
    $b_date = $_POST['blocked_date'];
    $reason = $_POST['reason'];
    if(!empty($b_date)) {
        // VALIDATION: Check for existing appointments first
        $stmt_check = $conn->prepare("SELECT appointment_id FROM appointment WHERE appointment_date = ? AND status NOT IN ('Cancelled', 'Rejected')");
        $stmt_check->bind_param("s", $b_date);
        $stmt_check->execute();
        $check_bookings = $stmt_check->get_result();

        if ($check_bookings->num_rows > 0) {
             $err_sched = "Cannot block date. There are " . $check_bookings->num_rows . " existing booking(s).";
        } else {
            $stmt = $conn->prepare("INSERT INTO blocked_dates (blocked_date, reason) VALUES (?, ?)");
            $stmt->bind_param("ss", $b_date, $reason);
            try {
                if($stmt->execute()) $msg_sched = "Date blocked successfully.";
            } catch(Exception $e) {
                $err_sched = "Date already blocked.";
            }
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'unblock' && isset($_GET['id'])) {
    $conn->query("DELETE FROM blocked_dates WHERE id=".intval($_GET['id']));
    header("Location: admin_dashboard.php?tab=schedule");
    exit;
}

// --- LOGIC 2: USER MANAGEMENT ---
if (isset($_POST['add_staff'])) {
    $n = trim($_POST['name']); 
    $u = trim($_POST['username']); 
    $p = trim($_POST['password']); 
    $r = $_POST['role'];

    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $u);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows == 0) {
        $h = password_hash($p, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $n, $u, $h, $r);
        if ($stmt->execute()) $msg = "Staff account created successfully.";
        else $error = "Database Error.";
    } else {
        $error = "Username already exists.";
    }
}

// --- LOGIC 2.5: TOGGLE USER STATUS ---
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $uid = intval($_GET['id']);
    // Check current status
    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows > 0) {
        $curr = $res->fetch_assoc()['status'];
        $new_status = ($curr == 'Inactive') ? 'Active' : 'Inactive';
        
        $update = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $update->bind_param("si", $new_status, $uid);
        if($update->execute()) {
            header("Location: admin_dashboard.php?tab=users&msg=Status updated");
            exit;
        }
    }
}

// --- LOGIC 3: REPLY TO FEEDBACK ---
if (isset($_POST['reply_feedback'])) {
    $fid = $_POST['feedback_id'];
    $reply = trim($_POST['reply_message']);
    if(!empty($reply)) {
        $stmt = $conn->prepare("UPDATE feedback SET reply = ? WHERE feedback_id = ?");
        $stmt->bind_param("si", $reply, $fid);
        if($stmt->execute()) {
            $msg = "Reply sent successfully.";
            header("Location: admin_dashboard.php?tab=feedback");
            exit;
        }
    }
}

// --- LOGIC 4: REPORT FILTERING ---
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'Appointments';
$report_title = strtoupper($report_type) . " REPORT"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MCMIS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { "primary": "#0f766e", "surface": "#ffffff", "background": "#f8fafc", "text-main": "#334155", "text-sub": "#64748b", "border": "#e2e8f0" },
                    fontFamily: { "sans": ["Inter", "sans-serif"] }
                }
            }
        }
    </script>

    <style>
        @media print {
            body { overflow: visible !important; height: auto !important; }
            body * { visibility: hidden; }
            #printable-area, #printable-area * { visibility: visible; }
            #printable-area { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 20px; background: white; border: none; box-shadow: none; }
            .no-print, button, .filter-box, header, aside { display: none !important; } 
            table { width: 100% !important; border-collapse: collapse !important; font-family: 'Inter', sans-serif; font-size: 10pt; }
            th { border-bottom: 2px solid #000 !important; text-transform: uppercase; font-weight: 700; padding: 8px 5px !important; text-align: left; color: #000 !important; background: transparent !important; }
            td { border-bottom: 1px solid #ddd !important; padding: 8px 5px !important; color: #000 !important; }
            #print-header { display: block !important; margin-bottom: 30px; text-align: center; }
            .summary-grid { display: flex !important; flex-direction: row !important; gap: 15px !important; margin-bottom: 20px !important; }
            .summary-grid > div { flex: 1 !important; padding: 10px !important; border: 1px solid #ccc !important; box-shadow: none !important; }
            .report-section { page-break-inside: auto; margin-bottom: 30px; }
            h3 { font-size: 14pt !important; margin-bottom: 10px !important; }
            #print-footer { display: block !important; margin-top: 50px; }
        }
        #print-header, #print-footer { display: none; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-background text-text-main font-sans h-screen overflow-hidden flex flex-col md:flex-row">

    <aside class="w-64 bg-surface border-r border-border flex-col h-screen fixed md:static z-50 hidden md:flex">
        <div class="p-6 flex items-center gap-3 border-b border-border/50 h-16">
            <div class="size-8 bg-primary/10 rounded-lg flex items-center justify-center text-primary"><i class="fas fa-heartbeat text-lg"></i></div>
            <span class="font-bold text-lg text-text-main">MCMIS Admin</span>
        </div>
        <nav class="flex-1 p-3 space-y-1">
            <a href="?tab=users" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $active_tab=='users'?'bg-primary/10 text-primary border-l-4 border-primary':'text-text-sub hover:bg-slate-50'; ?> transition-colors">
                <i class="fas fa-users-cog w-5 text-center"></i><span class="text-sm font-medium">User Management</span>
            </a>
            <a href="?tab=reports" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $active_tab=='reports'?'bg-primary/10 text-primary border-l-4 border-primary':'text-text-sub hover:bg-slate-50'; ?> transition-colors">
                <i class="fas fa-file-alt w-5 text-center"></i><span class="text-sm font-medium">Reports</span>
            </a>
            <a href="?tab=schedule" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $active_tab=='schedule'?'bg-primary/10 text-primary border-l-4 border-primary':'text-text-sub hover:bg-slate-50'; ?> transition-colors">
                <i class="fas fa-calendar-times w-5 text-center"></i><span class="text-sm font-medium">Schedule</span>
            </a>
            <a href="?tab=feedback" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $active_tab=='feedback'?'bg-primary/10 text-primary border-l-4 border-primary':'text-text-sub hover:bg-slate-50'; ?> transition-colors">
                <i class="fas fa-comments w-5 text-center"></i><span class="text-sm font-medium">Feedback</span>
            </a>
            <a href="?tab=ratings" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?php echo $active_tab=='ratings'?'bg-primary/10 text-primary border-l-4 border-primary':'text-text-sub hover:bg-slate-50'; ?> transition-colors">
                <i class="fas fa-star w-5 text-center"></i><span class="text-sm font-medium">Service Ratings</span>
            </a>
            
            <a href="profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-text-sub hover:bg-slate-50 transition-colors">
                <i class="fas fa-user-circle w-5 text-center"></i><span class="text-sm font-medium">My Profile</span>
            </a>

            <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 mt-auto transition-colors">
                <i class="fas fa-sign-out-alt w-5 text-center"></i><span class="text-sm font-medium">Logout</span>
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="h-16 bg-surface border-b border-border flex items-center justify-between px-6 shrink-0">
            <h1 class="font-bold text-xl text-text-main"><?php echo ucfirst($active_tab); ?></h1>
            <div class="text-sm text-text-sub bg-white border border-border px-3 py-1 rounded-full"><i class="fas fa-user-shield mr-2 text-primary"></i> Administrator</div>
        </header>

        <main class="flex-1 overflow-y-auto bg-background p-4 md:p-8">
            <div class="max-w-7xl mx-auto space-y-6">

                <?php if ($active_tab == 'users'): ?>
                    <section class="bg-surface rounded-xl shadow-sm border border-border/60 overflow-hidden p-6">
                        <h2 class="font-bold text-lg mb-4">Register New Staff</h2>
                        <?php if(isset($msg)) echo "<div class='bg-green-50 text-green-700 p-3 rounded mb-4'>$msg</div>"; ?>
                        <?php if(isset($error)) echo "<div class='bg-red-50 text-red-700 p-3 rounded mb-4'>$error</div>"; ?>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div><label class="text-sm font-medium">Full Name</label><input name="name" type="text" required class="w-full mt-1 rounded-lg border-border text-sm"></div>
                            <div><label class="text-sm font-medium">Role</label><select name="role" class="w-full mt-1 rounded-lg border-border text-sm"><option value="Clerk">Clerk</option><option value="Midwife">Midwife</option><option value="Admin">Admin</option></select></div>
                            <div><label class="text-sm font-medium">Username</label><input name="username" type="text" required class="w-full mt-1 rounded-lg border-border text-sm"></div>
                            <div><label class="text-sm font-medium">Password</label><input name="password" type="text" required class="w-full mt-1 rounded-lg border-border text-sm"></div>
                            <div class="md:col-span-2 flex justify-end"><button type="submit" name="add_staff" class="px-5 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-teal-700">Save Account</button></div>
                        </form>
                    </section>
                    
                    <section class="bg-surface rounded-xl shadow-sm border border-border/60 overflow-hidden">
                        <div class="px-6 py-4 border-b border-border/50 bg-slate-50/50"><h2 class="font-bold text-base">Authorized Personnel</h2></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4 pl-8">Personnel Name</th>
                                        <th class="px-6 py-4">Role</th>
                                        <th class="px-6 py-4">Username</th>
                                        <th class="px-6 py-4">Status</th>
                                        <th class="px-6 py-4 text-right pr-8">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php $users = $conn->query("SELECT * FROM users WHERE role != 'Client' ORDER BY role"); 
                                    while($u = $users->fetch_assoc()): 
                                        $status = $u['status'] ?? 'Active'; 
                                        $is_active = $status === 'Active';
                                    ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="px-6 py-4 pl-8">
                                            <div class="flex items-center gap-4 <?php echo !$is_active ? 'opacity-50 grayscale' : ''; ?>">
                                                <div class="h-10 w-10 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center text-sm font-bold overflow-hidden border-2 border-white shadow-sm ring-1 ring-slate-100 group-hover:ring-teal-200 transition-all">
                                                    <?php if(!empty($u['profile_pic'])): ?>
                                                        <img src="uploads/<?php echo $u['profile_pic']; ?>" class="h-full w-full object-cover">
                                                    <?php else: ?>
                                                        <?php echo substr($u['name'], 0, 1); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="font-bold text-slate-700"><?php echo $u['name']; ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold border <?php echo $u['role']=='Admin' ? 'bg-purple-50 text-purple-700 border-purple-100' : ($u['role']=='Midwife'?'bg-pink-50 text-pink-700 border-pink-100':'bg-blue-50 text-blue-700 border-blue-100'); ?>">
                                                <?php echo $u['role']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500 text-sm font-medium"><?php echo $u['username']; ?></td>
                                        <td class="px-6 py-4">
                                            <?php if($is_active): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-50 text-green-600 border border-green-200 uppercase tracking-wide">
                                                <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div> Active
                                            </span>
                                            <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-200 uppercase tracking-wide">
                                                <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div> Inactive
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right pr-8">
                                            <?php if($u['role'] !== 'Admin'): ?>
                                                <?php if($is_active): ?>
                                                    <a href="?action=toggle_status&id=<?php echo $u['user_id']; ?>" class="text-xs font-bold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg border border-red-100 transition-colors">
                                                        <i class="fas fa-user-slash mr-1"></i> Deactivate
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?action=toggle_status&id=<?php echo $u['user_id']; ?>" class="text-xs font-bold text-teal-600 hover:text-teal-700 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-lg border border-teal-100 transition-colors">
                                                        <i class="fas fa-user-check mr-1"></i> Reactivate
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs italic">Protected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                <?php elseif ($active_tab == 'reports'): ?>
                    
                    <?php
                    // --- 1. HANDLE INPUTS & MULTI-TYPE LOGIC ---
                    $raw_types = isset($_GET['types']) ? $_GET['types'] : (isset($_GET['type']) ? [$_GET['type']] : ['Appointments']);
                    // Ensure it's an array
                    if (!is_array($raw_types)) $raw_types = [$raw_types];
                    
                    $start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
                    $end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
                    
                    // --- 2. PROCESS DATA FOR EACH SELECTED TYPE ---
                    $generated_reports = [];

                    foreach ($raw_types as $current_type) {
                        $data = [];
                        $summary = [];

                        if ($current_type == 'Appointments') {
                            $sql = "SELECT a.*, p.name FROM appointment a JOIN patient p ON a.patient_id = p.patient_id WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date' ORDER BY a.appointment_date DESC";
                            $res = $conn->query($sql);
                            if($res) while($r = $res->fetch_assoc()) $data[] = $r;

                            $summary['total'] = count($data);
                            $summary['completed'] = count(array_filter($data, fn($r) => $r['status'] == 'Completed'));
                            $summary['cancelled'] = count(array_filter($data, fn($r) => $r['status'] == 'Cancelled'));
                            $summary['pending'] = count(array_filter($data, fn($r) => $r['status'] == 'Pending'));

                        } elseif ($current_type == 'Sales') {
                            $sql = "(SELECT DATE(bill_date) as pay_date, bill_id as ref_id, p.name, paid_amount as amount, payment_method as method, 'Service Balance' as type, NULL as appointment_status
                                     FROM billing b 
                                     JOIN patient p ON b.patient_id = p.patient_id 
                                     WHERE DATE(b.bill_date) BETWEEN '$start_date' AND '$end_date' AND b.status='Paid')
                                    UNION ALL
                                    (SELECT a.appointment_date as pay_date, a.appointment_id as ref_id, p.name, 
                                            CASE 
                                                WHEN a.payment_mode = 'Full' THEN sp.price
                                                ELSE sp.price * 0.5
                                            END as amount, 
                                            IF(a.reference_no != '', CONCAT('Ref: ', a.reference_no), 'Online Payment') as method, 
                                            CASE 
                                                WHEN a.status = 'Cancelled' THEN 'Cancelled Booking Downpayment'
                                                ELSE 'Booking Downpayment'
                                            END as type,
                                            a.status as appointment_status
                                     FROM appointment a 
                                     JOIN patient p ON a.patient_id = p.patient_id 
                                     LEFT JOIN service_pricing sp ON a.service = sp.service_name
                                     WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date' 
                                     AND a.down_payment_status='Paid'
                                     AND sp.price IS NOT NULL)
                                    ORDER BY pay_date DESC";
                            
                            $res = $conn->query($sql);
                            if($res) while($r = $res->fetch_assoc()) $data[] = $r;

                            $summary['total_revenue'] = array_sum(array_column($data, 'amount'));
                            $summary['transactions'] = count($data);
                            $summary['avg_transaction'] = $summary['transactions'] > 0 ? $summary['total_revenue'] / $summary['transactions'] : 0;
                            
                        } elseif ($current_type == 'Patients') {
                            $ph_today = date('Y-m-d');
                            $sql = "SELECT p.*, IFNULL(DATE_ADD(u.created_at, INTERVAL 8 HOUR), '$ph_today') as date_registered 
                                    FROM patient p 
                                    JOIN users u ON p.user_id = u.user_id 
                                    WHERE (u.created_at IS NOT NULL AND DATE(DATE_ADD(u.created_at, INTERVAL 8 HOUR)) BETWEEN '$start_date' AND '$end_date') 
                                       OR (u.created_at IS NULL AND '$ph_today' BETWEEN '$start_date' AND '$end_date')
                                    ORDER BY IFNULL(u.created_at, '$ph_today') DESC";
                            $res = $conn->query($sql);
                            if($res) while($r = $res->fetch_assoc()) $data[] = $r;
                            
                            $summary['total_patients'] = count($data);
                        }
                        
                        $generated_reports[$current_type] = ['data' => $data, 'summary' => $summary];
                    }
                    ?>

                    <style>
                        @media print {
                            .report-section { page-break-after: always; margin-bottom: 2rem; }
                            .report-section:last-child { page-break-after: auto; }
                        }
                    </style>

                    <section class="bg-surface rounded-xl shadow-sm border border-border p-5 no-print filter-box">
                        <form method="GET" class="flex flex-col md:flex-row md:items-end gap-6">
                            <input type="hidden" name="filter" value="1"><input type="hidden" name="tab" value="reports">
                            
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-text-sub uppercase mb-2">Select Reports to Generate</label>
                                <div class="flex gap-4 items-center flex-wrap">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="types[]" value="Appointments" class="rounded border-gray-300 text-primary focus:ring-primary" <?php if(in_array('Appointments', $raw_types)) echo 'checked'; ?>>
                                        <span class="ml-2 text-sm font-medium text-gray-700">Appointments</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="types[]" value="Sales" class="rounded border-gray-300 text-primary focus:ring-primary" <?php if(in_array('Sales', $raw_types)) echo 'checked'; ?>>
                                        <span class="ml-2 text-sm font-medium text-gray-700">Sales / Income</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="types[]" value="Patients" class="rounded border-gray-300 text-primary focus:ring-primary" <?php if(in_array('Patients', $raw_types)) echo 'checked'; ?>>
                                        <span class="ml-2 text-sm font-medium text-gray-700">Patients</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="w-40"><label class="block text-xs font-bold text-text-sub uppercase mb-1">From</label><input type="date" name="start" value="<?php echo $start_date; ?>" class="w-full text-sm border-border rounded-lg"></div>
                            <div class="w-40"><label class="block text-xs font-bold text-text-sub uppercase mb-1">To</label><input type="date" name="end" value="<?php echo $end_date; ?>" class="w-full text-sm border-border rounded-lg"></div>
                            <button type="submit" class="px-6 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-teal-700 h-10 shadow-sm">Generate Reports</button>
                        </form>
                    </section>

                    <section id="printable-area" class="bg-surface rounded-xl shadow-md border border-border overflow-hidden p-10 relative mt-4">
                        
                        <!-- MAIN HEADER (Once) -->
                        <div id="print-header">
                            <img src="mababy.jpg" style="width: 100px; height: auto; margin: 0 auto 15px auto; display: block;">
                            <h1 style="font-size: 26px; font-weight: 800; text-transform: uppercase; margin: 0; color: black; letter-spacing: -0.5px;">Mother Therese Mothers Clinic Lying - In</h1>
                            <p style="font-size: 13px; color: #333; margin: 4px 0 0 0;">97 B.S. Aquino Avenue, Tangos, Baliwag City, 3006 Bulacan</p>
                            <p style="font-size: 13px; color: #333; margin: 0 0 15px 0;">Contact: 0917 843 4589</p>
                            <div style="border-bottom: 2px solid #000; margin-bottom: 25px;"></div>
                            
                            <h2 style="font-size: 18px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; text-align: left; color: black;">Consolidated Report</h2>
                            <p style="font-size: 12px; text-align: left; margin-bottom: 20px; color: #000;"><strong>Period:</strong> <?php echo date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></p>
                        </div>
                        
                        <!-- Actions Toolbar -->
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 no-print gap-4" data-html2canvas-ignore="true">
                            <div><h2 class="font-bold text-xl text-text-main">Consolidated Report</h2><p class="text-sm text-text-sub mt-1">Period: <?php echo date('M d, Y', strtotime($start_date)) . " — " . date('M d, Y', strtotime($end_date)); ?></p></div>
                            <div class="flex gap-2">
                                <button onclick="downloadPDF()" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 flex items-center gap-2 shadow-sm"><i class="fas fa-file-pdf"></i> Download PDF</button>
                                <button onclick="window.print()" class="px-4 py-2 bg-white border border-border text-text-main text-sm font-medium rounded-lg hover:bg-gray-50 flex items-center gap-2 shadow-sm"><i class="fas fa-print"></i> Direct Print</button>
                            </div>
                        </div>

                        <!-- RENDER EACH SELECTED REPORT -->
                        <?php foreach($generated_reports as $rtype => $report): 
                            $data = $report['data'];
                            $summary = $report['summary'];
                        ?>
                            <div class="report-section mb-10 pb-10 border-b border-dashed border-gray-300 last:border-0 last:pb-0 last:mb-0">
                                <h3 class="font-bold text-lg text-primary uppercase mb-4 tracking-wide border-l-4 border-primary pl-3"><?php echo $rtype; ?> Report</h3>

                                <!-- SUMMARY CARDS -->
                                <?php if(!empty($data)): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 summary-grid">
                                        <?php if($rtype == 'Sales'): ?>
                                            <div class="bg-gray-50 border border-gray-100 rounded-lg p-4">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase">Total Revenue</p>
                                                <p class="text-xl font-bold text-green-700">₱<?php echo number_format($summary['total_revenue'], 2); ?></p>
                                            </div>
                                            <div class="bg-gray-50 border border-gray-100 rounded-lg p-4">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase">Transactions</p>
                                                <p class="text-xl font-bold text-blue-700"><?php echo $summary['transactions']; ?></p>
                                            </div>
                                            <div class="bg-gray-50 border border-gray-100 rounded-lg p-4">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase">Average</p>
                                                <p class="text-xl font-bold text-indigo-700">₱<?php echo number_format($summary['avg_transaction'], 2); ?></p>
                                            </div>
                                        <?php elseif($rtype == 'Appointments'): ?>
                                             <div class="bg-gray-50 border border-gray-100 rounded-lg p-4">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase">Total</p>
                                                <p class="text-xl font-bold text-gray-700"><?php echo $summary['total']; ?></p>
                                            </div>
                                            <div class="bg-gray-50 border border-gray-100 rounded-lg p-4">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase">Completed</p>
                                                <p class="text-xl font-bold text-green-700"><?php echo $summary['completed']; ?></p>
                                            </div>
                                            <div class="bg-gray-50 border border-gray-100 rounded-lg p-4">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase">Cancelled</p>
                                                <p class="text-xl font-bold text-red-700"><?php echo $summary['cancelled']; ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- DATA TABLE -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-700">
                                                <?php if($rtype == 'Appointments'): ?>
                                                    <th class="px-4 py-2">Date</th><th class="px-4 py-2">Time</th><th class="px-4 py-2">Patient</th><th class="px-4 py-2">Status</th>
                                                <?php elseif($rtype == 'Sales'): ?>
                                                    <th class="px-4 py-2">Date</th><th class="px-4 py-2">Trans ID</th><th class="px-4 py-2">Patient</th><th class="px-4 py-2">Amount</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Details</th>
                                                <?php else: ?>
                                                    <th class="px-4 py-2">Registered</th><th class="px-4 py-2">ID</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">Contact</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 text-sm leading-relaxed text-gray-700">
                                            <?php if(empty($data)): ?>
                                                <tr><td colspan="5" class="py-4 text-center text-gray-400 italic font-medium">No records found.</td></tr>
                                            <?php else: 
                                                foreach($data as $row):
                                                    if ($rtype == 'Appointments') {
                                                        echo "<tr>
                                                            <td class='px-4 py-2'>{$row['appointment_date']}</td>
                                                            <td class='px-4 py-2'>".date('h:i A', strtotime($row['appointment_time']))."</td>
                                                            <td class='px-4 py-2 font-semibold'>{$row['name']}</td>
                                                            <td class='px-4 py-2'>{$row['status']}</td>
                                                        </tr>";
                                                    } elseif ($rtype == 'Sales') {
                                                        $badge_color = (strpos($row['type'], 'Downpayment') !== false) ? 'text-indigo-600' : 'text-green-600';
                                                        echo "<tr>
                                                            <td class='px-4 py-2'>".date('M d, Y', strtotime($row['pay_date']))."</td>
                                                            <td class='px-4 py-2 font-mono text-xs text-gray-500'>#{$row['ref_id']}</td>
                                                            <td class='px-4 py-2'>{$row['name']}</td>
                                                            <td class='px-4 py-2 font-bold'>₱".number_format($row['amount'], 2)."</td>
                                                            <td class='px-4 py-2 text-xs font-bold uppercase $badge_color'>{$row['type']}</td>
                                                            <td class='px-4 py-2 text-xs text-gray-600'>{$row['method']}</td>
                                                        </tr>";
                                                    } else {
                                                         echo "<tr>
                                                            <td class='px-4 py-2'>".date('M d, Y', strtotime($row['date_registered']))."</td>
                                                            <td class='px-4 py-2 font-mono text-xs'>{$row['patient_id']}</td>
                                                            <td class='px-4 py-2 font-semibold'>{$row['name']}</td>
                                                            <td class='px-4 py-2'>{$row['contact_no']}</td>
                                                        </tr>";
                                                    }
                                                endforeach;
                                                // Footer
                                                if($rtype == 'Sales') {
                                                    echo "<tr class='bg-gray-50 font-bold border-t-2 border-gray-200'>
                                                        <td colspan='3' class='px-4 py-2 text-right'>TOTAL:</td>
                                                        <td colspan='2' class='px-4 py-2 text-primary'>₱".number_format($summary['total_revenue'], 2)."</td>
                                                    </tr>";
                                                }
                                            endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div id="print-footer">
                            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                <div style="font-size: 10px; color: #666;">
                                    <p>System Generated Report</p>
                                    <p>Generated on: <span id="print-time"></span></p>
                                </div>
                                <div style="text-align: center;">
                                    <div style="border-bottom: 1px solid #000; width: 200px; margin-bottom: 5px;"></div>
                                    <p style="font-weight: bold; text-transform: uppercase; font-size: 12px; color: black;"><?php echo $admin_name; ?></p>
                                    <p style="font-size: 10px; color: #444;">Administrator Signature</p>
                                </div>
                            </div>
                        </div>
                    </section>

                <?php elseif ($active_tab == 'schedule'): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Add Blocked Date -->
                        <div class="bg-surface rounded-xl shadow-sm border border-border p-6 h-fit">
                            <h2 class="font-bold text-lg mb-4 text-text-main">Block a Date</h2>
                            <p class="text-xs text-text-sub mb-4">Select a date to mark as unavailable for appointments (e.g. Holidays, Staff Outing).</p>
                            
                            <?php if(isset($msg_sched)) echo "<div class='bg-green-50 text-green-700 p-3 rounded mb-4 text-sm'>$msg_sched</div>"; ?>
                            <?php if(isset($err_sched)) echo "<div class='bg-red-50 text-red-700 p-3 rounded mb-4 text-sm'>$err_sched</div>"; ?>

                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-text-sub uppercase mb-1">Date to Block</label>
                                    <input type="date" name="blocked_date" required class="w-full text-sm border-border rounded-lg" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-text-sub uppercase mb-1">Reason</label>
                                    <input type="text" name="reason" placeholder="e.g. No Staff Available" class="w-full text-sm border-border rounded-lg">
                                </div>
                                <button type="submit" name="block_date" class="w-full py-2.5 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition-colors shadow-sm">
                                    <i class="fas fa-ban mr-2"></i> Block Date
                                </button>
                            </form>
                        </div>

                        <!-- List Blocked Dates -->
                        <div class="lg:col-span-2 bg-surface rounded-xl shadow-sm border border-border overflow-hidden">
                            <div class="p-6 border-b border-border/50 bg-slate-50/50">
                                <h2 class="font-bold text-base text-text-main">Blocked Schedule List</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-slate-50 text-xs font-bold text-text-sub uppercase">
                                        <tr>
                                            <th class="p-4 pl-6">Date</th>
                                            <th class="p-4">Reason</th>
                                            <th class="p-4 text-right pr-6">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php 
                                        $blocked = $conn->query("SELECT * FROM blocked_dates WHERE blocked_date >= CURDATE() ORDER BY blocked_date ASC");
                                        if($blocked->num_rows > 0):
                                            while($b = $blocked->fetch_assoc()):
                                        ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="p-4 pl-6 font-bold text-red-600">
                                                <?php echo date('M d, Y', strtotime($b['blocked_date'])); ?>
                                                <span class="text-[10px] bg-red-50 px-2 py-0.5 rounded-full ml-2 border border-red-100"><?php echo date('l', strtotime($b['blocked_date'])); ?></span>
                                            </td>
                                            <td class="p-4 text-text-main"><?php echo htmlspecialchars($b['reason']); ?></td>
                                            <td class="p-4 text-right pr-6">
                                                <a href="?action=unblock&id=<?php echo $b['id']; ?>" class="text-xs font-bold text-green-600 hover:bg-green-50 px-3 py-1.5 rounded-lg transition-colors border border-transparent hover:border-green-100">
                                                    <i class="fas fa-unlock mr-1"></i> Unblock
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="3" class="p-6 text-center text-text-sub italic">No upcoming blocked dates.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($active_tab == 'feedback'): ?>
                    
                    <section class="bg-surface rounded-xl shadow-sm border border-border/60 overflow-hidden">
                        <div class="px-6 py-4 border-b border-border/50 bg-slate-50/50"><h2 class="font-bold text-lg">Patient Feedback</h2></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-text-sub font-bold"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Patient</th><th class="px-6 py-3">Message</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Action</th></tr></thead>
                                <tbody class="divide-y divide-border/50">
                                    <?php 
                                    $feed = $conn->query("SELECT f.*, p.name FROM feedback f JOIN patient p ON f.patient_id=p.patient_id ORDER BY f.date_submitted DESC"); 
                                    while($f = $feed->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-3 text-text-sub"><?php echo date('M d, Y', strtotime($f['date_submitted'])); ?></td>
                                        <td class="px-6 py-3 font-medium"><?php echo $f['name']; ?></td>
                                        <td class="px-6 py-3 text-gray-700" style="max-width: 300px;"><?php echo htmlspecialchars($f['message']); ?></td>
                                        <td class="px-6 py-3"><?php if($f['reply']): ?><span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Replied</span><?php else: ?><span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">Pending</span><?php endif; ?></td>
                                        <td class="px-6 py-3 text-right">
                                            <?php if(!$f['reply']): ?>
                                                <button onclick="openReplyModal(<?php echo $f['feedback_id']; ?>)" class="text-primary hover:underline font-bold">Reply</button>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">Closed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                <?php elseif ($active_tab == 'ratings'): ?>
                    
                    <?php
                    // Calculate rating statistics
                    $total_ratings = $conn->query("SELECT COUNT(*) FROM ratings")->fetch_row()[0];
                    $avg_rating_result = $conn->query("SELECT AVG(rating) FROM ratings");
                    $avg_rating = $avg_rating_result->fetch_row()[0] ?? 0;
                    $avg_rating = round($avg_rating, 1);
                    
                    // Service breakdown
                    $service_stats = $conn->query("SELECT service_name, COUNT(*) as count, AVG(rating) as avg_rating FROM ratings GROUP BY service_name ORDER BY count DESC");
                    ?>

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm border border-border p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-star text-blue-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-text-sub uppercase">Total Ratings</p>
                                    <h3 class="text-2xl font-bold text-text-main"><?php echo $total_ratings; ?></h3>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-border p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-yellow-50 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-text-sub uppercase">Average Rating</p>
                                    <h3 class="text-2xl font-bold text-text-main">
                                        <?php echo $avg_rating; ?> 
                                        <span class="text-sm text-yellow-500">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : ' opacity-30'; ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-border p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-heartbeat text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-text-sub uppercase">Services Rated</p>
                                    <h3 class="text-2xl font-bold text-text-main"><?php echo $service_stats->num_rows; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ratings Table -->
                    <section class="bg-surface rounded-xl shadow-sm border border-border/60 overflow-hidden">
                        <div class="px-6 py-4 border-b border-border/50 bg-slate-50/50">
                            <h2 class="font-bold text-lg">Service Ratings & Reviews</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-text-sub font-bold">
                                    <tr>
                                        <th class="px-6 py-3">Date</th>
                                        <th class="px-6 py-3">Patient</th>
                                        <th class="px-6 py-3">Service</th>
                                        <th class="px-6 py-3">Rating</th>
                                        <th class="px-6 py-3">Review</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/50">
                                    <?php 
                                    $ratings = $conn->query("SELECT r.*, u.name FROM ratings r JOIN patient p ON r.patient_id=p.patient_id JOIN users u ON p.user_id=u.user_id ORDER BY r.created_at DESC");
                                    if($ratings->num_rows > 0):
                                        while($r = $ratings->fetch_assoc()): 
                                            $rating_color = '';
                                            if($r['rating'] >= 4) $rating_color = 'text-green-600';
                                            elseif($r['rating'] == 3) $rating_color = 'text-yellow-600';
                                            else $rating_color = 'text-red-600';
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-3 text-text-sub"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                        <td class="px-6 py-3 font-medium"><?php echo htmlspecialchars($r['name']); ?></td>
                                        <td class="px-6 py-3">
                                            <span class="px-2 py-1 rounded text-xs bg-blue-50 text-blue-700 border border-blue-100">
                                                <?php echo htmlspecialchars($r['service_name']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold <?php echo $rating_color; ?>"><?php echo $r['rating']; ?>/5</span>
                                                <div class="text-yellow-500">
                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $r['rating'] ? '' : ' opacity-30'; ?> text-xs"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 max-w-md">
                                            <?php if(!empty($r['review_text'])): ?>
                                                <p class="italic">"<?php echo htmlspecialchars($r['review_text']); ?>"</p>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">No review provided</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="px-6 py-8 text-center text-text-sub">No ratings received yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                <?php endif; ?>

            </div>
        </main>
    </div>

    <div id="replyModal" class="modal">
        <div class="modal-content">
            <h2 class="text-lg font-bold mb-4">Reply to Patient</h2>
            <form method="POST">
                <input type="hidden" name="feedback_id" id="reply_id">
                <textarea name="reply_message" class="w-full border-gray-300 rounded mb-4" rows="4" placeholder="Type your reply here..." required></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeReplyModal()" class="px-4 py-2 bg-gray-200 rounded text-sm">Cancel</button>
                    <button type="submit" name="reply_feedback" class="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-teal-700">Send Reply</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updatePrintTime() {
            var now = new Date();
            var el = document.getElementById('print-time');
            if(el) el.innerText = now.toLocaleDateString() + ' at ' + now.toLocaleTimeString();
        }
        window.onbeforeprint = updatePrintTime;

        function downloadPDF() {
            updatePrintTime(); 
            var element = document.getElementById('printable-area');
            var opt = { margin: 0.5, filename: 'MCMIS_Report.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' } };
            document.getElementById('print-header').style.display = 'block';
            document.getElementById('print-footer').style.display = 'block';
            html2pdf().set(opt).from(element).save().then(function(){
                document.getElementById('print-header').style.display = 'none';
                document.getElementById('print-footer').style.display = 'none';
            });
        }

        function openReplyModal(id) {
            document.getElementById('reply_id').value = id;
            document.getElementById('replyModal').style.display = 'block';
        }
        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }
    </script>
</body>
</html>