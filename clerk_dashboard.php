<?php
ob_start();
session_start();
include 'config.php';

// Security: Check Login & Role
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (strcasecmp($_SESSION['role'], 'Clerk') !== 0) { header("Location: login.php"); exit; }

// --- DATA FOR GRAPH (Last 7 Days) ---
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date_loop = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date_loop));
    $q_graph = $conn->query("SELECT COUNT(*) FROM appointment WHERE appointment_date = '$date_loop'");
    $chart_data[] = $q_graph->fetch_row()[0];
}

// --- PREPARE PATIENT DATA FOR AUTOCOMPLETE ---
$pat_array = [];
$patients_q = $conn->query("SELECT p.patient_id, u.name FROM patient p JOIN users u ON p.user_id = u.user_id ORDER BY u.name ASC");
while($p_row = $patients_q->fetch_assoc()) {
    // Check if patient has any 'Paid' down payment recently (optional but used in billing)
    $pid_val = $p_row['patient_id'];
    $dp_check = $conn->query("SELECT appointment_id FROM appointment WHERE patient_id='$pid_val' AND down_payment_status='Paid' AND status IN ('Pending', 'Confirmed') LIMIT 1");
    $p_row['has_paid_dp'] = ($dp_check->num_rows > 0);
    $pat_array[] = $p_row;
}

// Session Messages
$msg_bill = '';
if (isset($_SESSION['msg'])) {
    $msg_bill = $_SESSION['msg'];
    unset($_SESSION['msg']);
}
// Capture SweetAlert objects
$swal_success = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : null;
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : null;
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);

// Capture Auto-Show Receipt Data
$auto_receipt = isset($_SESSION['auto_receipt']) ? $_SESSION['auto_receipt'] : null;
unset($_SESSION['auto_receipt']);

// --- ACTIONS ---

// New Action: Mark DP as Paid (Cash)
if (isset($_POST['record_cash_dp'])) {
    $aid = $_POST['appt_id'];
    $stmt = $conn->prepare("UPDATE appointment SET down_payment_status='Paid' WHERE appointment_id=?");
    $stmt->bind_param("i", $aid);
    if($stmt->execute()) {
        $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm flex items-center gap-2'><i class='fas fa-check-circle'></i> Cash Down Payment recorded!</div>";
        header("Location: clerk_dashboard.php");
        exit;
    }
}

// 1. Check-in EXISTING Patient (Walk-in)
if (isset($_POST['checkin_existing'])) {
    $pid = $_POST['patient_id_walkin'];
    $service = $_POST['service'] ?? 'Maternal Checkup'; // Capture Service
    $appointment_date = $_POST['appointment_date'] ?? date('Y-m-d');
    $appointment_time = $_POST['appointment_time'] ?? date('h:i A');
    
    // Validate that date and time are provided
    if (empty($appointment_date) || empty($appointment_time)) {
        $_SESSION['msg'] = "<div class='bg-red-50 text-red-700 p-3 rounded-xl mb-4 border border-red-200 text-sm'><i class='fas fa-exclamation-circle'></i> Please select both date and time for the appointment.</div>";
        header("Location: clerk_dashboard.php");
        exit;
    }
    
    // Fix: Defined $check properly to prevent error
    $check = $conn->query("SELECT appointment_id FROM appointment WHERE patient_id='$pid' AND appointment_date='$appointment_date' AND appointment_time='$appointment_time' AND status NOT IN ('Cancelled', 'Rejected')");
    
    if($check->num_rows > 0) {
        $_SESSION['msg'] = "<div class='bg-yellow-50 text-yellow-700 p-3 rounded-xl mb-4 border border-yellow-200 text-sm'><i class='fas fa-exclamation-circle'></i> This time slot is already booked for this patient.</div>";
        header("Location: clerk_dashboard.php");
        exit;
    } else {
        // VALIDATION: Time Range Check (REMOVED FOR CLERK - 24/7 Access)
        // $hour = (int)date("H", strtotime($appointment_time));
        // if ($hour < 6 || $hour > 17) { ... }

        // Check if the time slot is full (max 4 appointments per slot - 4 Midwives)
        // Special Rule: 12:00 PM is ALWAYS available
        $slotCheck = $conn->query("SELECT COUNT(*) as count FROM appointment WHERE appointment_date='$appointment_date' AND appointment_time='".date("H:i:s", strtotime($appointment_time))."' AND status NOT IN ('Cancelled', 'Rejected')");
        $slotData = $slotCheck->fetch_assoc();
        
        $is_noon = (date("H:i", strtotime($appointment_time)) === '12:00');

        if ($slotData['count'] >= 4 && !$is_noon) {
            $_SESSION['msg'] = "<div class='bg-yellow-50 text-yellow-700 p-3 rounded-xl mb-4 border border-yellow-200 text-sm'><i class='fas fa-exclamation-circle'></i> This time slot is fully booked. Please select another time.</div>";
            header("Location: clerk_dashboard.php");
            exit;
        }
        
        // Walk-in is automatically CONFIRMED so Midwife sees it immediately
        $dp_status = isset($_POST['dp_paid_cash']) ? 'Paid' : 'Pending';
        $time_db = date("H:i:s", strtotime($appointment_time));
        $stmt = $conn->prepare("INSERT INTO appointment (patient_id, appointment_date, appointment_time, service, status, down_payment_status) VALUES (?, ?, ?, ?, 'Confirmed', ?)");
        $stmt->bind_param("issss", $pid, $appointment_date, $time_db, $service, $dp_status);
        
        if($stmt->execute()) {
             $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm flex items-center gap-2'><i class='fas fa-check-circle'></i> Checked in successfully for $service! " . ($dp_status == 'Paid' ? '(DP Paid)' : '') . "</div>";
             header("Location: clerk_dashboard.php");
             exit;
        }
    }
}

// 2. Register & Check-in NEW Patient
if (isset($_POST['reg_walkin'])) {
    $name = trim($_POST['name']); 
    $contact = preg_replace('/[^0-9]/', '', $_POST['contact']);
    
    $email = trim($_POST['email'] ?? '');
    
    // Improved check: only block if BOTH name and contact exist, or if email exists if provided
    $exists = false;
    if (!empty($email)) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if($check->get_result()->num_rows > 0) $exists = true;
    } else {
        $check = $conn->prepare("SELECT u.user_id FROM users u JOIN patient p ON u.user_id=p.user_id WHERE u.name = ? AND u.contact_number = ?");
        $check->bind_param("ss", $name, $contact);
        $check->execute();
        if($check->get_result()->num_rows > 0) $exists = true;
    }
    
    if(!$exists) {
        $user = strtolower(str_replace(' ', '', $name)) . rand(10,99);
        $pass = password_hash('123456', PASSWORD_DEFAULT);
        
        $email = trim($_POST['email'] ?? '');
        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role, email) VALUES (?, ?, ?, 'Client', ?)");
        $stmt->bind_param("ssss", $name, $user, $pass, $email);
        
        if($stmt->execute()) {
            $uid = $stmt->insert_id;
            
            // Add Address & Contact to user record
            $address = $_POST['address'] ?? '';
            $contact = preg_replace('/[^0-9]/', '', $_POST['contact']);
            $up_user = $conn->prepare("UPDATE users SET address = ?, contact_number = ? WHERE user_id = ?");
            $up_user->bind_param("ssi", $address, $contact, $uid);
            $up_user->execute();

            $stmt2 = $conn->prepare("INSERT INTO patient (user_id, name, contact_no) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $uid, $name, $contact);
            $stmt2->execute();
            $new_pid = $stmt2->insert_id;

            $_SESSION['swal_success'] = [
                'title' => 'Registered Successfully!',
                'text'  => "Patient $name has been registered. Search for them in the 'Existing Patient' section to book.",
                'icon'  => 'success',
                'timer' => 5000
            ];
            
            $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm'><i class='fas fa-check-circle'></i> <b>Registered!</b> User: $user | Pass: 123456</div>";
            header("Location: clerk_dashboard.php?new_reg_id=$new_pid&new_reg_name=" . urlencode($name));
            exit;
        } else {
            $msg_reg = "<div class='bg-red-50 text-red-600 p-3 rounded-xl mb-4 border border-red-200 text-sm'>Database Error</div>";
        }
    } else {
        $msg_reg = "<div class='bg-red-50 text-red-600 p-3 rounded-xl mb-4 border border-red-200 text-sm'>Patient already exists.</div>";
    }
}

// 3. Create Bill
if (isset($_POST['create_bill'])) {
    $pid = $_POST['patient_id']; 
    $amt = $_POST['amount'];
    if(!empty($pid) && !empty($amt)) {
        $status = isset($_POST['bill_paid_cash']) ? 'Paid' : 'Unpaid';
        $method = isset($_POST['bill_paid_cash']) ? 'Cash' : NULL;
        $paid_amt = isset($_POST['bill_paid_cash']) ? $amt : 0;
        
        $stmt = $conn->prepare("INSERT INTO billing (patient_id, bill_date, total_amount, status, payment_method, paid_amount) VALUES (?, NOW(), ?, ?, ?, ?)");
        $stmt->bind_param("idssd", $pid, $amt, $status, $method, $paid_amt);
        if($stmt->execute()) {
            $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm'><i class='fas fa-check-circle'></i> Official Bill Generated " . ($status == 'Paid' ? 'and Paid via Cash!' : 'Successfully.') . "</div>";
            header("Location: clerk_dashboard.php");
            exit;
        }
    }
}

// 4. Pay Bill
if (isset($_POST['pay_bill'])) {
    $bid = $_POST['bill_id']; 
    $method = $_POST['method'];
    $q = $conn->query("SELECT b.total_amount, b.bill_date, u.name 
                       FROM billing b 
                       JOIN patient p ON b.patient_id = p.patient_id 
                       JOIN users u ON p.user_id = u.user_id 
                       WHERE b.bill_id='$bid'");
    $row = $q->fetch_assoc();
    $total = $row['total_amount'];
    $p_name = $row['name'];
    $b_date = $row['bill_date'];

    // If method is GCash, append reference number to method string or specific column?
    // User requested "encode gcash refererence number".
    // Let's modify payment_method to include Ref #, e.g., "GCash (Ref: 12345)"
    if(isset($_POST['reference_no']) && !empty($_POST['reference_no'])) {
        $ref = $_POST['reference_no'];
        $method .= " (Ref: $ref)";
    }

    $update = $conn->prepare("UPDATE billing SET status='Paid', payment_method=?, paid_amount=? WHERE bill_id=?");
    $update->bind_param("sdi", $method, $total, $bid);
    if($update->execute()) {
        $_SESSION['swal_success'] = [
            'title' => 'Payment Successful!',
            'text'  => "Bill #$bid paid via $method.",
            'icon'  => 'success',
            'timer' => 2000
        ];
        
        // Auto-show Receipt
        $_SESSION['auto_receipt'] = [
            'id' => $bid,
            'date' => date('M d, Y', strtotime($b_date)), // Use original bill date or CURDATE? usually payment date. Let's use CURDATE for payment.
            'date_paid' => date('M d, Y'),
            'name' => $p_name,
            'amount' => number_format($total, 2),
            'method' => $method
        ];

        $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm'><i class='fas fa-check-circle'></i> Bill #$bid settled via $method.</div>";
        header("Location: clerk_dashboard.php");
        exit;
    }
}

// 5. Appointment Actions
if (isset($_POST['confirm_appt'])) {
    // Setting status to 'Confirmed' makes it visible to the Midwife
    if($conn->query("UPDATE appointment SET status='Confirmed' WHERE appointment_id='{$_POST['appt_id']}'")) {
        $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm'><i class='fas fa-check-circle'></i> Appointment confirmed.</div>";
        header("Location: clerk_dashboard.php");
        exit;
    }
}

// 6. Cancel Appointment WITH REASON (UPDATED FOR CLERK)
if (isset($_POST['cancel_appt_with_reason'])) {
    $aid = $_POST['cancel_id'];
    $reason_select = $_POST['cancel_reason_select'];
    
    // Logic: If 'Others' is selected, use the Note input. Otherwise, use the dropdown value.
    if ($reason_select === 'Others') {
        $final_reason = $_POST['cancel_reason_note'];
    } else {
        $final_reason = $reason_select;
    }
    
    $stmt = $conn->prepare("UPDATE appointment SET status='Cancelled', cancel_reason=? WHERE appointment_id=?");
    $stmt->bind_param("si", $final_reason, $aid);
    if($stmt->execute()) {
        $_SESSION['msg'] = "<div class='bg-red-50 text-red-700 p-3 rounded-xl mb-4 border border-red-200 text-sm'><i class='fas fa-window-close'></i> Appointment cancelled.</div>";
        header("Location: clerk_dashboard.php");
        exit;
    }
}

// 7. Verify Down Payment
if (isset($_POST['review_payment'])) {
    $aid = $_POST['appt_id'];
    $action = $_POST['review_payment']; // Use the button value directly
    $new_status = ($action === 'approve') ? 'Paid' : 'Rejected';
    
        // Check current status to avoid un-cancelling
        $chk_st = $conn->query("SELECT status FROM appointment WHERE appointment_id='$aid'");
        $cur_status = ($chk_st && $chk_st->num_rows > 0) ? $chk_st->fetch_row()[0] : '';

        if ($action === 'approve') {
            if ($cur_status === 'Cancelled') {
                // Keep Cancelled, just mark Paid (Revenue)
                $stmt = $conn->prepare("UPDATE appointment SET down_payment_status='Paid' WHERE appointment_id=?");
            } else {
                // Auto-confirm/Schedule the appointment
                $stmt = $conn->prepare("UPDATE appointment SET down_payment_status='Paid', status='Confirmed' WHERE appointment_id=?");
            }
        } else {
            $stmt = $conn->prepare("UPDATE appointment SET down_payment_status='Rejected', status='Rejected' WHERE appointment_id=?");
        }
    
    $stmt->bind_param("i", $aid);
    if($stmt->execute()) {
        $_SESSION['swal_success'] = [
            'title' => 'Payment Verified!',
            'text'  => "Down Payment marked as $new_status.",
            'icon'  => 'success'
        ];
        $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm'>Down Payment marked as $new_status.</div>";
        header("Location: clerk_dashboard.php");
        exit;
    }
}

// 8. Verify FINAL Bill Payment
// 8. Verify FINAL Bill Payment
if (isset($_POST['review_bill_payment'])) {
    // ROBUST FIX: Get ID and Action from button value (format: action_id)
    $raw_val = $_POST['review_bill_payment'];
    $parts = explode('_', $raw_val);
    $action = $parts[0];
    
    // Fallback: Check if ID came from hidden input (cached pages)
    $bid = isset($parts[1]) ? intval($parts[1]) : (isset($_POST['bill_id']) ? intval($_POST['bill_id']) : 0);
    
    if ($bid > 0) {
        $new_status = ($action === 'approve') ? 'Paid' : 'Rejected';
        
        if ($action === 'approve') {
            // STEP 1: FORCE STATUS UPDATE (Critical)
            $stmt1 = $conn->prepare("UPDATE billing SET status='Paid' WHERE bill_id=?");
            $stmt1->bind_param("i", $bid);
            $msg_success = $stmt1->execute();
            $stmt1->close();

            if ($stmt2) {
                $stmt2->bind_param("i", $bid);
                $stmt2->execute();
                $stmt2->close();
            }

            // Fetch info for Receipt
            $info_q = $conn->query("SELECT b.total_amount, u.name FROM billing b JOIN patient p ON b.patient_id=p.patient_id JOIN users u ON p.user_id=u.user_id WHERE b.bill_id='$bid'");
            $info = $info_q->fetch_assoc();

            if($msg_success) {
                 $_SESSION['swal_success'] = [
                    'title' => 'Bill Payment Verified!',
                    'text'  => "Bill #$bid marked as Paid.",
                    'icon'  => 'success'
                 ];
                 
                 // Auto-show Receipt
                 $_SESSION['auto_receipt'] = [
                    'id' => $bid,
                    'date' => date('M d, Y'),
                    'name' => $info['name'],
                    'amount' => number_format($info['total_amount'], 2),
                    'method' => 'GCash' // Assumed since it's verification
                 ];

                 $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 text-sm border border-green-200'><i class='fas fa-check-circle'></i> Bill #$bid marked as Paid.</div>";
                 header("Location: clerk_dashboard.php");
                 exit;
            } else {
                 $msg_bill = "<div class='bg-red-50 text-red-700 p-3 rounded-xl mb-4 text-sm border border-red-200'>Error updating status for Bill #$bid.</div>";
            }
        } else {
            // Reject logic
            $stmt = $conn->prepare("UPDATE billing SET status='Rejected' WHERE bill_id=?");
            $stmt->bind_param("i", $bid);
            if($stmt->execute()) {
                $stmt->close();
                $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 text-sm border border-green-200'><i class='fas fa-check-circle'></i> Bill #$bid Rejected.</div>";
                header("Location: clerk_dashboard.php");
                exit;
            }
        }
    }
}

// 9. Review Pending Charges
if (isset($_POST['review_charge'])) {
    try {
        $charge_id = intval($_POST['charge_id']);
        $action = $_POST['review_charge']; // 'approve' or 'reject'
        $clerk_id = $_SESSION['user_id'];
        
        if ($action === 'approve' || $action === 'approve_cash' || $action === 'approve_gcash' || $action === 'approve_philhealth') {
            // Get charge details safely
            $charge_query = $conn->prepare("SELECT patient_id, total_amount, unit_price, quantity, is_philhealth FROM pending_charges WHERE charge_id = ?");
            $charge_query->bind_param("i", $charge_id);
            $charge_query->execute();
            $charge_query->bind_result($pid, $amt, $unit_price, $quantity, $is_philhealth);
            
            if ($charge_query->fetch()) {
                $charge_query->close(); // Close this so we can run other queries
                
                // Recalculate total to ensure downpayment is deducted
                $original_total = $unit_price * $quantity;
                $stored_amount = $amt; // Keep original stored amount
                $final_amount = $amt;
                $needs_update = false;
                
                // Get notes to check if deduction was already applied
                $notes_query = $conn->prepare("SELECT notes FROM pending_charges WHERE charge_id = ?");
                $notes_query->bind_param("i", $charge_id);
                $notes_query->execute();
                $notes_query->bind_result($charge_notes);
                $notes_query->fetch();
                $notes_query->close();
                
                $notes_text = $charge_notes ?? '';
                $already_deducted = (stripos($notes_text, 'Downpayment Deducted') !== false || stripos($notes_text, 'Fully Paid Online') !== false);
                
                // Only recalculate if:
                // 1. Stored amount equals original (no deduction applied) OR is very close (within 0.01)
                // 2. Notes don't already mention downpayment deduction or full payment
                // 3. Not a PhilHealth no-balance-billing case (where total should be 0)
                $needs_recalc = (abs($amt - $original_total) < 0.01) && !$already_deducted && !($is_philhealth && $amt == 0);
                
                if ($needs_recalc) {
                    // Check for paid downpayment
                    $dp_check = $conn->query("SELECT payment_mode FROM appointment WHERE patient_id='$pid' AND down_payment_status='Paid' AND status NOT IN ('Cancelled', 'Rejected') ORDER BY appointment_date DESC, appointment_id DESC LIMIT 1");
                    
                    if ($dp_check->num_rows > 0) {
                        $dp_row = $dp_check->fetch_assoc();
                        $mode = $dp_row['payment_mode'] ?? 'DownPayment';
                        
                        // Check if DP was already used in another charge today
                        $dp_used = $conn->query("SELECT charge_id FROM pending_charges WHERE patient_id='$pid' AND charge_id != '$charge_id' AND (notes LIKE '%Downpayment Deducted%' OR notes LIKE '%Fully Paid Online%') AND DATE(created_at)=CURDATE()");
                        
                        if ($dp_used->num_rows == 0) {
                            if (strcasecmp($mode, 'Full') === 0) {
                                // For Full payment, we keep the total in billing for records but the clerk won't collect more
                                // Wait, if it's Full, we actually want final_amount to be the original for the BILL status 'Paid'
                                $final_amount = $original_total;
                                // No change to final_amount needed since it's already $original_total
                            } else {
                                // Apply 50% downpayment deduction
                                $deduction = $original_total * 0.5;
                                $final_amount = max(0, $original_total - $deduction);
                                $needs_update = true;
                                $notes_text .= ($notes_text ? ' ' : '') . "[-" . number_format($deduction, 2) . " Downpayment Deducted]";
                            }
                        }
                    }
                }
                
                $amt = $final_amount; // Use recalculated amount for billing
                
                // Logic: Check if Client PRE-PAID in Full
                $check_prepay = $conn->query("SELECT payment_mode FROM appointment WHERE patient_id='$pid' AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 2 DAY) AND status IN ('Confirmed','Arrived','Completed') ORDER BY appointment_id DESC LIMIT 1");
                $is_full_paid = false;
                if($check_prepay && $check_prepay->num_rows > 0) {
                    $appt_row = $check_prepay->fetch_row();
                    if($appt_row[0] == 'Full') {
                        $is_full_paid = true;
                    }
                }

                // Determine billing status and method
                if ($is_full_paid && $action === 'approve') {
                     $bill_status = 'Paid';
                     $bill_method = 'Pre-paid Online';
                } else {
                     $bill_status = ($action === 'approve') ? 'Unpaid' : 'Paid';
                     $bill_method = NULL;
                }
                
                if ($action === 'approve_cash') $bill_method = 'Cash';
                if ($action === 'approve_philhealth') $bill_method = 'PhilHealth';
                
                $paid_amt = ($bill_status === 'Paid') ? $amt : 0;
                
                // Create bill
                $bill_stmt = $conn->prepare("INSERT INTO billing (patient_id, total_amount, status, payment_method, paid_amount, bill_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
                $bill_stmt->bind_param("idssd", $pid, $amt, $bill_status, $bill_method, $paid_amt);
                $bill_stmt->execute();
                $new_bill_id = $bill_stmt->insert_id;
                $bill_stmt->close();
                
                // Update charge status
                if ($needs_update) {
                    $up_st = $conn->prepare("UPDATE pending_charges SET status='Approved', reviewed_by=?, reviewed_at=NOW(), total_amount=?, notes=? WHERE charge_id=?");
                    $up_st->bind_param("idis", $clerk_id, $final_amount, $notes_text, $charge_id);
                } else {
                    $up_st = $conn->prepare("UPDATE pending_charges SET status='Approved', reviewed_by=?, reviewed_at=NOW() WHERE charge_id=?");
                    $up_st->bind_param("ii", $clerk_id, $charge_id);
                }
                $up_st->execute();
                $up_st->close();
                
                $final_msg = ($bill_status === 'Paid') ? "Charge approved and paid via $bill_method." : "Charge approved and unpaid bill created.";
                if ($action === 'approve_philhealth') $final_msg = "PhilHealth claim validated and accepted.";
                
                $_SESSION['swal_success'] = [
                    'title' => 'Charge Approved!',
                    'text'  => $final_msg,
                    'icon'  => 'success'
                ];

                // Auto-show Receipt if PAID immediately
                if($bill_status === 'Paid') {
                    $nm_q = $conn->query("SELECT u.name FROM patient p JOIN users u ON p.user_id=u.user_id WHERE p.patient_id='$pid'");
                    $pname_val = ($nm_q->num_rows > 0) ? $nm_q->fetch_row()[0] : 'Patient';

                    $_SESSION['auto_receipt'] = [
                        'id' => $new_bill_id,
                        'date' => date('M d, Y'),
                        'name' => $pname_val,
                        'amount' => number_format($paid_amt, 2),
                        'method' => $bill_method
                    ];
                }

                $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-3 rounded-xl mb-4 border border-green-200 text-sm'><i class='fas fa-check-circle me-2'></i>$final_msg</div>";
                header("Location: clerk_dashboard.php#charges");
                exit;
            } else {
                $charge_query->close();
            }
        } else {
            // Reject charge
            $update_stmt = $conn->prepare("UPDATE pending_charges SET status='Rejected', reviewed_by=?, reviewed_at=NOW() WHERE charge_id=?");
            $update_stmt->bind_param("ii", $clerk_id, $charge_id);
            if($update_stmt->execute()) {
                $_SESSION['msg'] = "<div class='bg-yellow-50 text-yellow-700 p-3 rounded-xl mb-4 border border-yellow-200 text-sm'>Charge rejected.</div>";
                header("Location: clerk_dashboard.php");
                exit;
            }
            $update_stmt->close();
        }
        
    } catch (Exception $e) {
        $msg_bill = "<div class='bg-red-50 text-red-700 p-3 rounded-xl mb-4 border border-red-200 text-sm'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clerk Portal</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: "#0f766e", "primary-light": "#ccfbf1", surface: "#ffffff", background: "#f8fafc", text: "#334155", muted: "#64748b" },
                    fontFamily: { sans: ["Manrope", "sans-serif"] }
                }
            }
        }
    </script>
    <style>
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .nav-item.active { background-color: #f0fdfa; color: #0f766e; border-right: 3px solid #0f766e; font-weight: 700; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
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
                timer: 3000,
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

            // Auto-Receipt Popup
            <?php if($auto_receipt): ?>
                setTimeout(() => {
                    viewReceipt(
                        "<?php echo $auto_receipt['id']; ?>",
                        "<?php echo $auto_receipt['date']; ?>",
                        "<?php echo addslashes($auto_receipt['name']); ?>",
                        "<?php echo $auto_receipt['amount']; ?>",
                        "<?php echo $auto_receipt['method']; ?>"
                    );
                }, 500); // Small delay to ensure SweetAlert doesn't conflict visually
            <?php endif; ?>
        });
    </script>
</head>
<body class="bg-background text-text font-sans h-screen flex overflow-hidden">
    
    <?php
    // --- COUNTS FOR BADGES ---
    // Appointments: Count Pending status OR Down Payment 'Reviewing'
    $cnt_appt = $conn->query("SELECT COUNT(*) FROM appointment WHERE status='Pending' OR down_payment_status='Reviewing'")->fetch_row()[0];
    
    // Charges: Count Pending
    $cnt_charges = $conn->query("SELECT COUNT(*) FROM pending_charges WHERE status='Pending'")->fetch_row()[0];
    
    // Billing: Count Pending (Payment Review) OR Unpaid
    $cnt_bills = $conn->query("SELECT COUNT(*) FROM billing WHERE status IN ('Pending', 'Unpaid')")->fetch_row()[0];
    ?>

    <aside class="w-72 bg-surface h-[95vh] my-auto ml-4 flex flex-col shadow-xl z-20 rounded-3xl border border-gray-100 hidden md:flex">
        <div class="p-8 flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary shadow-sm">
                <i class="fas fa-hospital-user text-xl"></i>
            </div>
            <div>
                <h1 class="font-extrabold text-xl text-primary tracking-tight">MCMIS Clinic</h1>
                <p class="text-xs text-muted font-semibold uppercase tracking-wider">Clerk Portal</p>
            </div>
        </div>

        <nav class="flex-1 px-6 space-y-2">
            <button onclick="switchTab('dashboard', this)" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-muted font-medium transition-all hover:bg-gray-50 text-sm active">
                <i class="fas fa-th-large text-lg"></i> Dashboard
            </button>
            <button onclick="switchTab('register', this)" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-muted font-medium transition-all hover:bg-gray-50 text-sm">
                <i class="fas fa-user-plus text-lg"></i> Check-in / Register
            </button>
            <button onclick="switchTab('appointments', this)" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-muted font-medium transition-all hover:bg-gray-50 text-sm">
                <div class="relative">
                    <i class="fas fa-calendar-check text-lg"></i>
                    <?php if($cnt_appt > 0): ?><span class="absolute -top-1 -right-2 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span><?php endif; ?>
                </div> 
                <span>Appointments</span>
            </button>
            <button onclick="switchTab('charges', this)" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-muted font-medium transition-all hover:bg-gray-50 text-sm">
                <div class="relative">
                    <i class="fas fa-clipboard-check text-lg"></i>
                    <?php if($cnt_charges > 0): ?><span class="absolute -top-1 -right-2 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span><?php endif; ?>
                </div>
                <span>Review Charges</span>
            </button>
            <button onclick="switchTab('billing', this)" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-muted font-medium transition-all hover:bg-gray-50 text-sm">
                 <div class="relative">
                    <i class="fas fa-wallet text-lg"></i>
                    <?php if($cnt_bills > 0): ?><span class="absolute -top-1 -right-2 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span><?php endif; ?>
                </div>
                <span>Billing</span>
            </button>
            <a href="profile.php" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-muted font-medium transition-all hover:bg-gray-50 text-sm">
                <i class="fas fa-user-circle text-lg"></i> My Profile
            </a>
        </nav>

        <div class="p-6 mt-auto">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl transition-colors text-sm font-bold">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 h-full overflow-y-auto p-4 md:p-8">
        
        <div class="flex justify-between items-center mb-8 pl-2">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Front Desk Operations</h2>
                <p class="text-sm text-muted">Manage patient flow and daily tasks</p>
            </div>
            <div class="flex items-center gap-3 bg-white px-5 py-2.5 rounded-full shadow-sm border border-gray-100">
                <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs uppercase">
                    <?php echo isset($_SESSION['name']) ? substr($_SESSION['name'], 0, 1) : 'U'; ?>
                </div>
                <span class="text-sm font-bold text-gray-700"><?php echo $_SESSION['name'] ?? 'User'; ?></span>
            </div>
        </div>

        <div id="dashboard" class="tab-content active">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-calendar-day"></i></div>
                    <div><p class="text-xs font-bold text-muted uppercase">Today's Appts</p><h3 class="text-3xl font-extrabold text-gray-800"><?php echo $conn->query("SELECT COUNT(*) FROM appointment WHERE appointment_date = CURDATE()")->fetch_row()[0]; ?></h3></div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 bg-purple-50 text-purple-500 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-users"></i></div>
                    <div><p class="text-xs font-bold text-muted uppercase">New Patients</p><h3 class="text-3xl font-extrabold text-gray-800"><?php echo $conn->query("SELECT COUNT(*) FROM patient")->fetch_row()[0]; ?></h3></div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 bg-green-50 text-green-500 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-coins"></i></div>
                    <div><p class="text-xs font-bold text-muted uppercase">Pending Bills</p><h3 class="text-3xl font-extrabold text-gray-800"><?php echo $conn->query("SELECT COUNT(*) FROM billing WHERE status='Unpaid'")->fetch_row()[0]; ?></h3></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg text-gray-800">Patient Traffic (Last 7 Days)</h3>
                    <div class="text-xs font-bold text-primary bg-primary-light px-3 py-1 rounded-full uppercase">Weekly Overview</div>
                </div>
                <div class="h-64 w-full">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>

        <div id="register" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 h-full">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fas fa-user-check"></i></div>
                        <h3 class="text-lg font-bold text-gray-800">Existing Patient?</h3>
                    </div>
                    <p class="text-sm text-muted mb-4">Search for a registered patient to create an immediate walk-in appointment.</p>
                    
                    <?php 
                    // Fetch Active Services & Group them to match Client View
                    $svc_opts = "";
                    $pkgs = "";
                    $others = "";
                    
                    $q_svc = $conn->query("SELECT service_name, service_category FROM service_pricing WHERE is_active=1 ORDER BY service_name ASC");
                    if($q_svc->num_rows > 0) {
                        while($s = $q_svc->fetch_assoc()) {
                            $sc = strtolower($s['service_category']);
                            // Exclude Meds & Supplies (same as Client Dashboard)
                            if (strpos($sc, 'medicine') !== false || strpos($sc, 'supplies') !== false) continue;
                            
                            $opt = "<option value=\"".htmlspecialchars($s['service_name'])."\">".htmlspecialchars($s['service_name'])."</option>";
                            
                            if (strpos($sc, 'package') !== false) {
                                $pkgs .= $opt;
                            } else {
                                $others .= $opt;
                            }
                        }
                    }
                    
                    // Build Optgroups
                    if($pkgs) $svc_opts .= "<optgroup label=\"Packages\">$pkgs</optgroup>";
                    if($others) $svc_opts .= "<optgroup label=\"Other Services\">$others</optgroup>";
                    
                    // Fallback
                    if(empty($svc_opts)) $svc_opts = "<option value=\"Prenatal Checkup\">Prenatal Checkup</option>";
                    
                    if(isset($msg_walkin)) echo $msg_walkin; 
                    ?>

                    <form method="POST" class="space-y-4" id="walkin_form">
                        <div class="relative">
                            <label class="text-xs font-bold text-muted uppercase mb-1 block">Search Patient Name</label>
                            <input type="hidden" name="patient_id_walkin" id="walkin_hidden_id" required>
                            <div class="relative">
                                <input type="text" id="walkin_search_input" placeholder="Type name to search..." class="w-full border-gray-200 rounded-xl bg-gray-50 h-12 px-4 focus:ring-2 focus:ring-blue-500 transition-shadow" autocomplete="off">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400"><i class="fas fa-search"></i></div>
                            </div>
                            <div id="walkin_dropdown" class="custom-scroll hidden absolute z-50 w-full bg-white border border-gray-100 rounded-xl shadow-xl mt-1 max-h-48 overflow-y-auto"></div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-muted uppercase mb-1 block">Service / Purpose</label>
                            <select name="service" id="walkin_service" onchange="fetchSvcInfo(this, 'walkin_price_display')" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-4 text-sm focus:ring-2 focus:ring-blue-500" required>
                                <option value="">-- Select Service --</option>
                                <?php echo $svc_opts; ?>
                            </select>
                            <div id="walkin_price_display" class="mt-2 space-y-1 hidden"></div>
                        </div>

                        <!-- Calendar Section -->
                        <div>
                            <label class="text-xs font-bold text-muted uppercase mb-1 block">Select Appointment Date</label>
                            <div class="bg-white border border-gray-200 rounded-xl p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <button type="button" onclick="changeMonth(-1)" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                        <i class="fas fa-chevron-left text-gray-600"></i>
                                    </button>
                                    <div class="text-center">
                                        <div class="text-sm font-bold text-gray-800" id="current_month">January 2026</div>
                                    </div>
                                    <button type="button" onclick="changeMonth(1)" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                        <i class="fas fa-chevron-right text-gray-600"></i>
                                    </button>
                                </div>
                                <div class="grid grid-cols-7 gap-1 text-center text-xs font-bold text-gray-500 mb-2">
                                    <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
                                </div>
                                <div id="calendar_days" class="grid grid-cols-7 gap-1"></div>
                            </div>
                            <input type="hidden" name="appointment_date" id="selected_date" required>
                        </div>

                        <!-- Time Slots Section -->
                        <div id="time_slots_section" class="hidden">
                            <label class="text-xs font-bold text-muted uppercase mb-1 block">Select Time Slot</label>
                            <div class="bg-white border border-gray-200 rounded-xl p-3 max-h-64 overflow-y-auto custom-scroll">
                                <div id="time_slots_container" class="space-y-2"></div>
                            </div>
                            <input type="hidden" name="appointment_time" id="selected_time" required>
                        </div>

                        <div class="flex items-center gap-2 py-2">
                            <input type="checkbox" name="dp_paid_cash" id="dp_paid_existing" class="w-4 h-4 text-primary rounded border-gray-300">
                            <label for="dp_paid_existing" class="text-xs font-bold text-gray-600">Patient paid 50% Downpayment (Cash)?</label>
                        </div>

                        <button type="submit" name="checkin_existing" id="walkin_submit_btn" class="w-full h-12 bg-teal-50 hover:bg-teal-100 text-primary font-bold rounded-xl transition-all" disabled>
                            <i class="fas fa-check-circle mr-2"></i> Confirm Appointment
                        </button>
                    </form>
                </div>

                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 h-full">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center"><i class="fas fa-user-plus"></i></div>
                        <h3 class="text-lg font-bold text-gray-800">New Patient?</h3>
                    </div>
                    <p class="text-sm text-muted mb-4">Register a new profile. You can book their appointment immediately after.</p>

                    <?php if(isset($msg_reg)) echo $msg_reg; ?>

                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-muted uppercase mb-1 block">Full Name</label>
                                <input type="text" name="name" required class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-4 focus:ring-2 focus:ring-primary transition-shadow text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-muted uppercase mb-1 block">Email Address</label>
                                <input type="email" name="email" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-4 focus:ring-2 focus:ring-primary transition-shadow text-sm" placeholder="optional@gmail.com">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                            <div>
                                <label class="text-xs font-bold text-muted uppercase mb-1 block">Contact Number</label>
                                <input type="text" name="contact" required maxlength="11" placeholder="09XXXXXXXXX" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-4 focus:ring-2 focus:ring-primary transition-shadow text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-muted uppercase mb-1 block">Full Address <span class="text-gray-400 font-normal">(Optional)</span></label>
                            <input type="text" name="address" placeholder="Barangay, City..." class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-4 focus:ring-2 focus:ring-primary transition-shadow text-sm">
                        </div>

                        <button type="submit" name="reg_walkin" class="w-full h-12 bg-primary hover:bg-teal-800 text-white font-bold rounded-xl shadow-lg transition-all mt-4">
                            <i class="fas fa-plus mr-2"></i> Register New Patient
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div id="appointments" class="tab-content">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden min-h-[500px]">
                <div class="p-6 border-b border-gray-50"><h3 class="font-bold text-lg text-gray-800">Manage Appointments</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-bold text-muted uppercase tracking-wide">
                            <tr>
                                <th class="p-5 font-bold">Date</th>
                                <th class="p-5 font-bold">Time</th>
                                <th class="p-5 font-bold">Patient</th>
                                <th class="p-5 font-bold">Service</th>
                                <th class="p-5 font-bold">Status</th>
                                <th class="p-5 font-bold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php 
                            // DEDUPLICATION FIX: Group by Patient + Date + Hour to enforce visual 1-hour gap
                            $appts = $conn->query("SELECT a.*, u.name 
                                                 FROM appointment a 
                                                 JOIN patient p ON a.patient_id=p.patient_id 
                                                 JOIN users u ON p.user_id = u.user_id 
                                                 GROUP BY p.patient_id, a.appointment_date, HOUR(a.appointment_time)
                                                 ORDER BY a.appointment_date DESC, a.appointment_time ASC");
                            while($row = $appts->fetch_assoc()):   
                                $statusColor = '';
                                $statusText = $row['status'];
                                switch($statusText) {
                                    case 'Confirmed': $statusColor = 'bg-green-100 text-green-700'; break;
                                    case 'Cancelled': $statusColor = 'bg-yellow-100 text-yellow-800'; break;
                                    case 'Pending':   $statusColor = 'bg-yellow-100 text-yellow-800'; break;
                                    case 'Completed': $statusColor = 'bg-blue-100 text-blue-700'; break;
                                    default:          $statusColor = 'bg-gray-100 text-gray-600';
                                }
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-5 text-gray-600 font-medium"><?php echo $row['appointment_date']; ?></td>
                                <td class="p-5 font-mono text-gray-500 text-xs"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                <td class="p-5 font-bold text-gray-800"><?php echo $row['name']; ?></td>
                                <td class="p-5 font-medium text-primary"><?php echo htmlspecialchars($row['service'] ?? 'Prenatal Checkup'); ?></td>
                                <td class="p-5">
                                    <span class="px-3 py-1 rounded-md text-xs font-bold <?php echo $statusColor; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                    <div class="mt-1">
                                        <?php if($row['down_payment_status'] == 'Paid'): ?>
                                            <span class="text-[10px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded font-bold border border-blue-100"><i class="fas fa-check-circle mr-1"></i>DP Paid</span>
                                        <?php elseif($row['down_payment_status'] == 'Reviewing'): ?>
                                            <span class="text-[10px] bg-orange-50 text-orange-600 px-1.5 py-0.5 rounded font-bold border border-orange-100 italic"><i class="fas fa-clock mr-1"></i>Reviewing DP</span>
                                        <?php else: ?>
                                            <span class="text-[10px] bg-gray-50 text-gray-400 px-1.5 py-0.5 rounded font-bold border border-gray-100">DP Unpaid</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($row['cancel_reason']): ?>
                                        <div class="text-xs text-red-500 mt-1 italic">Note: <?php echo $row['cancel_reason']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-5 text-right">
                                    <div class="inline-flex items-center justify-end gap-3">
                                        <?php if($row['down_payment_status'] == 'Reviewing'): ?>
                                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider bg-gray-50 px-2 py-1 rounded border border-gray-100">View Only</span>
                                        <?php else: ?>
                                            
                                            <?php if($row['down_payment_status'] != 'Paid'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="appt_id" value="<?php echo $row['appointment_id']; ?>">
                                                <button type="submit" name="record_cash_dp" title="Received Cash DP" class="text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-full w-8 h-8 flex items-center justify-center transition-all bg-white border border-blue-100">
                                                    <i class="fas fa-hand-holding-usd"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>

                                            <?php if($row['status']=='Pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="appt_id" value="<?php echo $row['appointment_id']; ?>">
                                                <button type="submit" name="confirm_appt" title="Confirm" class="text-green-500 hover:text-green-700 hover:bg-green-50 rounded-full w-8 h-8 flex items-center justify-center transition-all">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <button type="button" onclick="openCancelModal(<?php echo $row['appointment_id']; ?>)" title="Cancel" class="text-red-500 hover:text-red-700 hover:bg-red-50 rounded-full w-8 h-8 flex items-center justify-center transition-all">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="billing" class="tab-content">
            <!-- Down Payment Review Section -->
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 mb-8 hidden">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="font-bold text-lg text-gray-800">Review Down Payments</h3>
                        <p class="text-xs text-muted">Verify uploaded proofs from clients.</p>
                    </div>
                    <span class="bg-teal-50 text-teal-700 px-3 py-1 rounded-full text-xs font-bold">
                        <?php echo $conn->query("SELECT COUNT(*) FROM appointment WHERE down_payment_status='Reviewing' AND status != 'Rejected'")->fetch_row()[0]; ?> Pending
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-bold text-muted uppercase">
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Patient</th>
                                <th class="p-3">Service</th>
                                    <th class="p-3">Payment Details</th>
                                    <th class="p-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php 
                                $reviews = $conn->query("SELECT a.*, u.name FROM appointment a JOIN patient p ON a.patient_id=p.patient_id JOIN users u ON p.user_id=u.user_id WHERE a.down_payment_status='Reviewing' AND a.status != 'Rejected'");
                                if($reviews->num_rows > 0):
                                    while($rev = $reviews->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3"><?php echo date('M d', strtotime($rev['appointment_date'])); ?></td>
                                        <td class="p-3 font-bold">
                                            <?php echo htmlspecialchars($rev['name']); ?>
                                            <?php if($rev['status'] == 'Cancelled') echo '<span class="text-red-500 text-[10px] uppercase font-bold bg-red-50 border border-red-100 px-1 rounded ms-1">Cancelled</span>'; ?>
                                        </td>
                                        <td class="p-3 text-xs w-1/4"><?php echo htmlspecialchars($rev['service']); ?></td>
                                        <td class="p-3">
                                            <?php if(strpos($rev['reference_no'], 'PAYID') === 0 || $rev['payment_proof'] == 'Digital_Transaction'): ?>
                                                <div class="flex flex-col">
                                                    <span class="inline-flex items-center gap-1 text-blue-600 font-bold text-xs bg-blue-50 px-2 py-1 rounded-md mb-1 w-fit">
                                                        <i class="fas fa-check-circle"></i> GCash Verified
                                                    </span>
                                                    <?php if(isset($rev['payment_mode']) && $rev['payment_mode'] == 'Full'): ?>
                                                        <span class="inline-flex items-center gap-1 text-teal-700 font-bold text-xs bg-teal-100 px-2 py-1 rounded-md mb-1 w-fit border border-teal-200">
                                                            <i class="fas fa-star"></i> PAID IN FULL
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="font-mono text-[10px] text-gray-500 select-all">Ref: <?php echo htmlspecialchars($rev['reference_no']); ?></span>
                                                </div>
                                            <?php elseif($rev['payment_proof']): ?>
                                                <!-- Legacy File Support -->
                                                <button onclick="viewProof('uploads/<?php echo $rev['payment_proof']; ?>', '<?php echo htmlspecialchars($rev['reference_no'] ?? 'N/A'); ?>')" class="text-gray-600 hover:text-blue-600 text-xs font-bold border border-gray-200 rounded px-2 py-1 bg-white">
                                                    <i class="fas fa-paperclip mr-1"></i> File Proof
                                                </button>
                                                <?php if(!empty($rev['reference_no'])): ?>
                                                    <div class="text-[10px] text-gray-400 mt-1 font-mono"><?php echo $rev['reference_no']; ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-red-400 text-xs italic">No Details</span>
                                            <?php endif; ?>
                                        </td>
                                    <td class="p-3 text-right">
                                        <form method="POST" class="inline-flex gap-2">
                                            <input type="hidden" name="appt_id" value="<?php echo $rev['appointment_id']; ?>">
                                            <button type="submit" name="review_payment" value="approve" class="bg-green-100 text-green-700 hover:bg-green-200 px-3 py-1 rounded-lg text-xs font-bold transition-colors">
                                                Approve
                                            </button>
                                            <button type="submit" name="review_payment" value="reject" class="bg-red-100 text-red-700 hover:bg-red-200 px-3 py-1 rounded-lg text-xs font-bold transition-colors">
                                                Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="p-4 text-center text-muted text-xs">No pending payments to review.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Merged Billing Section Continued: Final Bill & History -->



            <!-- Merged Billing Section Continued: Final Bill & History -->




            <!-- PENDING SETTLEMENTS (Priority View) -->
            <div class="bg-red-50 rounded-3xl shadow-sm border border-red-100 overflow-hidden mb-8 relative z-10">
                <div class="p-6 border-b border-red-100 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-lg text-red-800"><i class="fas fa-exclamation-circle me-2"></i> Pending Settlements</h3>
                        <p class="text-xs text-red-600">These bills require immediate payment attention.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-red-100/50 text-xs font-bold text-red-800 uppercase">
                            <tr><th class="p-4">ID</th><th class="p-4">Patient</th><th class="p-4">Amount</th><th class="p-4">Status</th><th class="p-4 text-right">Action</th></tr>
                        </thead>
                        <tbody class="divide-y divide-red-100">
                            <?php
                            $unpaid = $conn->query("SELECT b.*, u.name FROM billing b JOIN patient p ON b.patient_id=p.patient_id JOIN users u ON p.user_id = u.user_id WHERE b.status IN ('Unpaid', 'Rejected', 'Pending') ORDER BY b.bill_date ASC");
                            if($unpaid->num_rows > 0):
                                while($r = $unpaid->fetch_assoc()):
                            ?>
                            <tr class="hover:bg-red-50 transition-colors bg-white">
                                <td class="p-4 font-mono text-xs text-red-500 font-bold">#<?php echo $r['bill_id']; ?></td>
                                <td class="p-4 font-bold text-gray-800"><?php echo $r['name']; ?></td>
                                <td class="p-4 font-bold text-red-600">₱<?php echo number_format($r['total_amount'], 2); ?></td>
                                <td class="p-4">
                                    <?php if($r['status']=='Pending'): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Verifying</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700"><?php echo $r['status']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <form method="POST" class="inline-flex gap-2 justify-end">
                                        <input type="hidden" name="bill_id" value="<?php echo $r['bill_id']; ?>">
                                        <input type="hidden" name="method" value="">
                                        
                                        <!-- Cash Button -->
                                        <button type="submit" name="pay_bill" onclick="this.form.method.value='Cash'" class="bg-white border border-green-600 text-green-700 hover:bg-green-50 px-4 py-1.5 rounded-lg text-xs font-bold shadow-sm flex items-center gap-1 transition-all">
                                            <i class="fas fa-money-bill-wave"></i> Cash
                                        </button>

                                        <!-- PayMongo Button (Triggers Modal) -->
                                        <button type="button" onclick="openPayMongoModal(<?php echo $r['bill_id']; ?>, <?php echo $r['total_amount']; ?>)" class="bg-gray-800 hover:bg-black text-white px-4 py-1.5 rounded-lg text-xs font-bold shadow-sm flex items-center gap-1 transition-all">
                                            <i class="fas fa-wallet"></i> PayMongo
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="p-8 text-center text-gray-400">No pending settlements.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TRANSACTION HISTORY -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-50"><h3 class="font-bold text-lg text-gray-800">Transaction History</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-bold text-muted uppercase"><tr><th class="p-4">ID</th><th class="p-4">Patient</th><th class="p-4">Paid Amount</th><th class="p-4">Date</th><th class="p-4 text-right">Receipt</th></tr></thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php
                            $history = $conn->query("SELECT b.*, u.name FROM billing b JOIN patient p ON b.patient_id=p.patient_id JOIN users u ON p.user_id = u.user_id WHERE b.status = 'Paid' ORDER BY b.bill_date DESC LIMIT 50");
                            while($r = $history->fetch_assoc()):
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 font-mono text-xs text-muted">#<?php echo $r['bill_id']; ?></td>
                                <td class="p-4 font-bold text-gray-800"><?php echo $r['name']; ?></td>
                                <td class="p-4 font-medium text-green-600">₱<?php echo number_format($r['paid_amount'], 2); ?></td>
                                <td class="p-4 text-gray-500 text-xs"><?php echo date('M d, Y', strtotime($r['bill_date'])); ?></td>
                                <td class="p-4 text-right">
                                    <button onclick="viewReceipt(<?php echo $r['bill_id']; ?>, '<?php echo date('M d, Y', strtotime($r['bill_date'])); ?>', '<?php echo $r['name']; ?>', '<?php echo number_format($r['paid_amount'], 2); ?>', '<?php echo $r['payment_method']; ?>')" class="text-primary hover:text-teal-800 font-bold text-xs underline">
                                        View Receipt
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MOVED CHARGES TAB -->
        <div id="charges" class="tab-content">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                <div class="p-6 border-b border-gray-50 bg-gradient-to-r from-teal-50 to-white">
                    <h3 class="font-bold text-xl text-gray-800">Pending Service Charges</h3>
                    <p class="text-sm text-gray-500">Review and approve charges recorded by midwives</p>
                </div>
                
                <?php if(isset($msg_bill)) echo $msg_bill; ?>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-muted font-bold">
                            <tr>
                                <th class="p-4">Date</th>
                                <th class="p-4">Patient</th>
                                <th class="p-4">Service</th>
                                <th class="p-4">Recorded By</th>
                                <th class="p-4">Qty</th>
                                <th class="p-4">Unit Price</th>
                                <th class="p-4">Total</th>
                                <th class="p-4">Notes</th>
                                <th class="p-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $pending = $conn->query("
                                SELECT pc.*, sp.service_name, sp.service_category,
                                       u_patient.name as patient_name,
                                       u_midwife.name as midwife_name
                                FROM pending_charges pc
                                JOIN service_pricing sp ON pc.service_id = sp.service_id
                                JOIN patient p ON pc.patient_id = p.patient_id
                                JOIN users u_patient ON p.user_id = u_patient.user_id
                                JOIN users u_midwife ON pc.recorded_by = u_midwife.user_id
                                WHERE pc.status = 'Pending'
                                ORDER BY pc.created_at DESC
                            ");
                            
                            if ($pending->num_rows > 0):
                                while ($ch = $pending->fetch_assoc()):
                                    // AUTO-CORRECTION LOGIC: 
                                    // 1. FIRST PRIORITY: If the Midwife already saved a discounted amount, trust it.
                                    $original_total = $ch['unit_price'] * $ch['quantity'];
                                    $recorded_total = $ch['total_amount'];
                                    
                                    $notes_text = $ch['notes'] ?? '';
                                    $already_full = (stripos($notes_text, 'Fully Paid Online') !== false);
                                    $already_deducted = (stripos($notes_text, 'Downpayment Deducted') !== false || (abs($original_total - $recorded_total) > 0.01));
                                    
                                    $deduction_amt = 0;
                                    $is_full_prepaid = false;
                                    
                                    // Skip for PhilHealth 0-balance
                                    $is_philhealth_zero = ($ch['is_philhealth'] && $ch['total_amount'] == 0);
                                    
                                    if (!$is_philhealth_zero && !$already_deducted && !$already_full) {
                                        // 2. SECOND PRIORITY: If Midwife saved FULL PRICE, try to auto-deduct for the Clerk
                                        $dp_check = $conn->query("SELECT payment_mode, service FROM appointment WHERE patient_id='{$ch['patient_id']}' AND down_payment_status='Paid' AND status NOT IN ('Cancelled', 'Rejected') ORDER BY appointment_date DESC, appointment_id DESC LIMIT 1");
                                        
                                        if ($dp_check->num_rows > 0) {
                                            $dp_row = $dp_check->fetch_assoc();
                                            $mode = $dp_row['payment_mode'] ?? 'DownPayment';
                                            $booked_svc = $dp_row['service'] ?? '';
                                            
                                            // Check if DP/Full-Pay was already used on the SAME DAY this charge was created
                                            $charge_date = date('Y-m-d', strtotime($ch['created_at']));
                                            $dp_used = $conn->query("SELECT charge_id FROM pending_charges WHERE patient_id='{$ch['patient_id']}' AND charge_id != '{$ch['charge_id']}' AND (notes LIKE '%Downpayment Deducted%' OR notes LIKE '%Fully Paid Online%' OR abs(total_amount - (unit_price*quantity)) > 0.01) AND DATE(created_at)='$charge_date'");
                                            $not_used_yet = ($dp_used->num_rows == 0);

                                            if (strcasecmp($mode, 'Full') === 0) {
                                                // FULL PAYMENT logic: Must match the service AND not be used yet
                                                // We use a prefix match (first 5 chars) to be flexible (e.g. ANC01 matches ANC01 - Prenatal)
                                                $s1 = strtolower(substr(trim($ch['service_name']), 0, 5));
                                                $s2 = strtolower(substr(trim($booked_svc), 0, 5));
                                                $is_match = ($s1 === $s2 || stripos($ch['service_name'], $booked_svc) !== false || stripos($booked_svc, $ch['service_name']) !== false);
                                                
                                                if ($is_match && $not_used_yet) {
                                                    $is_full_prepaid = true;
                                                    $deduction_amt = $original_total;
                                                }
                                            } else {
                                                // PARTIAL DP logic: Apply to first charge of the day
                                                if ($not_used_yet) {
                                                    $deduction_amt = $original_total * 0.5;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Final display total (priority: already deducted > auto-calculated)
                                    if ($already_deducted) {
                                        $display_total = $recorded_total;
                                        $deduction_amt = $original_total - $recorded_total;
                                    } elseif ($is_full_prepaid) {
                                        $display_total = 0;
                                    } else {
                                        $display_total = $original_total - $deduction_amt;
                                    }
                            ?>
                                <tr class="hover:bg-gray-50 <?php echo $ch['is_philhealth'] ? 'bg-blue-50/30' : ''; ?>">
                                    <td class="p-4 text-gray-600 whitespace-nowrap"><?php echo date('M d, Y', strtotime($ch['created_at'])); ?></td>
                                    <td class="p-4 font-bold">
                                        <div><?php echo htmlspecialchars($ch['patient_name']); ?></div>
                                        <?php 
                                        // Specific visual check for the Clerk: Does this patient HAVE a paid DP right now?
                                        $v3 = $conn->query("SELECT payment_mode FROM appointment WHERE patient_id='{$ch['patient_id']}' AND down_payment_status='Paid' AND status NOT IN ('Cancelled', 'Rejected') ORDER BY appointment_date DESC, appointment_id DESC LIMIT 1");
                                        if($v3->num_rows > 0): 
                                            $v3_row = $v3->fetch_assoc();
                                            $lbl = (strcasecmp($v3_row['payment_mode'] ?? '', 'Full') === 0) ? 'Fully Paid' : 'DP Paid';
                                        ?>
                                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 mt-1 rounded-md text-[9px] font-extrabold bg-green-50 text-green-700 border border-green-200 uppercase tracking-tighter">
                                                <i class="fas fa-check-circle"></i> <?php echo $lbl; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-medium"><?php echo htmlspecialchars($ch['service_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($ch['service_category']); ?></div>
                                    </td>
                                    <td class="p-4 text-gray-600"><?php echo htmlspecialchars($ch['midwife_name']); ?></td>
                                    <td class="p-4 text-center font-bold"><?php echo $ch['quantity']; ?></td>
                                    <td class="p-4 text-gray-700">₱<?php echo number_format($ch['unit_price'], 2); ?></td>
                                    <td class="p-4 font-bold <?php echo $ch['is_philhealth'] ? 'text-blue-600' : 'text-primary'; ?>">
                                        <div class="flex flex-col">
                                            <?php if($deduction_amt > 0): ?>
                                                <span class="text-[10px] text-gray-400 font-normal line-through">₱<?php echo number_format($original_total, 2); ?></span>
                                                <span class="text-[10px] text-red-500 font-normal">-₱<?php echo number_format($deduction_amt, 2); ?></span>
                                            <?php endif; ?>
                                            
                                            <span class="text-sm">₱<?php echo number_format($display_total, 2); ?></span>
                                            
                                            <?php if ($is_full_prepaid || $already_full): ?>
                                                <div class="text-[9px] uppercase tracking-tighter text-green-600"><i class="fas fa-check-double"></i> Fully Paid Online</div>
                                            <?php elseif ($ch['is_philhealth']): ?>
                                                <div class="text-[9px] uppercase tracking-tighter text-blue-500"><i class="fas fa-check-circle"></i> PhilHealth Covered</div>
                                            <?php elseif ($deduction_amt > 0): ?>
                                                <div class="text-[9px] uppercase tracking-tighter text-orange-500"><i class="fas fa-info-circle"></i> Balance Due</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-600 max-w-xs">
                                        <?php if (!empty($ch['notes'])): ?>
                                            <span class="text-xs italic"><?php echo htmlspecialchars($ch['notes']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-right">
                                        <form method="POST" class="inline-flex gap-2">
                                            <input type="hidden" name="charge_id" value="<?php echo $ch['charge_id']; ?>">
                                            
                                            <?php if ($ch['is_philhealth']): ?>
                                                <!-- PhilHealth Specific Action -->
                                                <button type="submit" name="review_charge" value="approve_philhealth" title="Accept PhilHealth" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-1">
                                                    <i class="fas fa-file-contract"></i> Accept PHIC
                                                </button>
                                            <?php else: ?>
                                                <!-- Standard Approve -->
                                                <button type="submit" name="review_charge" value="approve" title="Approve Charge" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-1">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            <?php endif; ?>

                                            <button type="submit" name="review_charge" value="reject" title="Reject" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-bold rounded-lg transition-all border border-red-100 flex items-center gap-1">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <tr><td colspan="9" class="p-8 text-center text-gray-400">No pending charges to review.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <div id="cancelModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-96 p-6 shadow-2xl">
            <h3 class="font-bold text-lg text-gray-800 mb-4">Cancel Appointment</h3>
            <form method="POST">
                <input type="hidden" name="cancel_id" id="modal_cancel_id">
                
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason for cancellation:</label>
                <select name="cancel_reason_select" id="clerk_reason_select" class="w-full border-gray-200 rounded-xl mb-4 p-3 text-sm focus:ring-red-500 focus:border-red-500" onchange="toggleClerkReasonInput(this)" required>
                    <option value="">-- Select Reason --</option>
                    <option value="Patient Request">Patient Request</option>
                    <option value="Doctor Unavailable">Doctor Unavailable</option>
                    <option value="Schedule Conflict">Schedule Conflict</option>
                    <option value="No Show">No Show</option>
                    <option value="Emergency">Emergency</option>
                    <option value="Others">Others</option>
                </select>

                <div id="clerk_other_reason_div" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Please specify:</label>
                    <textarea name="cancel_reason_note" id="clerk_reason_note" class="w-full border-gray-200 rounded-xl mb-4 p-3 text-sm focus:ring-red-500 focus:border-red-500" rows="3" placeholder="Enter reason..."></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('cancelModal').classList.add('hidden')" class="px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-100 rounded-lg">Close</button>
                    <button type="submit" name="cancel_appt_with_reason" class="px-4 py-2 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="imageModal" class="fixed inset-0 bg-black/90 hidden z-[60] flex items-center justify-center p-4">
        <div class="relative max-w-lg w-full bg-white rounded-xl overflow-hidden shadow-2xl">
            <!-- Header -->
            <div class="bg-gray-50 border-b p-4 flex justify-between items-center">
                <div>
                     <h3 class="font-bold text-gray-800">Proof of Payment</h3>
                     <p class="text-xs text-gray-500">GCash Ref: <span id="modalRefNo" class="font-mono font-bold text-blue-600 select-all">---</span></p>
                </div>
                <button onclick="document.getElementById('imageModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-700 text-2xl font-bold">&times;</button>
            </div>
            <!-- Body -->
            <div class="p-4 bg-gray-900 flex justify-center">
                 <img id="modalImage" src="" class="max-h-[60vh] w-auto rounded shadow-sm">
            </div>
        </div>
    </div>

    <!-- Receipt Modal (Tailwind Version) -->
    <div id="receiptModal" class="fixed inset-0 bg-black/75 hidden z-[70] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="font-bold text-gray-800">Official Receipt</h3>
                <button onclick="document.getElementById('receiptModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700 font-bold text-xl">&times;</button>
            </div>
            <!-- Modal Body (Scrollable & Gray Background) -->
            <div class="p-6 bg-gray-100 max-h-[70vh] overflow-y-auto custom-scroll">
                <div id="printableReceipt" class="p-8 bg-white font-sans text-sm relative border-2 border-dashed border-gray-300 shadow-md">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <img src="mababy.jpg" style="height: 60px;" class="mx-auto mb-3">
                        <h5 class="font-bold text-sm uppercase tracking-wider text-gray-800">Mother Therese Mothers Clinic Lying - In</h5>
                        <p class="text-[10px] text-gray-500">97 B.S. Aquino Ave, Tangos, Baliwag City</p>
                        <p class="text-[10px] text-gray-500">Contact: 0917 843 4589</p>
                    </div>
                    
                    <!-- Info Row -->
                    <div class="flex justify-between items-end mb-6 pb-4 border-b border-gray-300 border-dashed">
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase font-bold mb-1">Receipt No.</p>
                            <p class="font-mono text-xl font-bold text-gray-800 leading-none">#<span id="r_id">000000</span></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-gray-500 uppercase font-bold mb-1">Date Paid</p>
                            <p class="font-bold text-gray-800 leading-none"><span id="r_date">Jan 01, 2026</span></p>
                        </div>
                    </div>

                    <!-- Patient Row -->
                    <div class="mb-6">
                        <p class="text-[10px] text-gray-500 uppercase font-bold mb-1">Received From</p>
                        <p class="text-lg font-bold text-gray-800 border-b border-gray-200 pb-1 w-full"><span id="r_name">Patient Name</span></p>
                    </div>

                    <!-- Amount Box -->
                    <div class="bg-gray-50 p-4 rounded-xl mb-6 border border-gray-100">
                        <div class="flex justify-between mb-2">
                            <span id="r_amount_label" class="text-gray-500 text-xs">Amount Paid</span>
                            <span class="font-bold text-lg text-gray-800">₱<span id="r_amount1">0.00</span></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 text-xs">Payment Method</span>
                            <span class="font-bold text-xs uppercase bg-white border px-2 py-1 rounded text-gray-700 shadow-sm"><span id="r_method">CASH</span></span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="text-center mt-8">
                        <div class="inline-flex items-center gap-2 border border-green-500 text-green-600 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest bg-green-50">
                            <i class="fas fa-check-circle"></i> Payment Complete
                        </div>
                        <p class="mt-4 text-[10px] text-gray-400 italic">This serves as your official proof of payment.<br>Thank you for trusting us!</p>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-gray-50 border-t flex justify-end gap-2">
                <button onclick="printReceipt()" class="px-4 py-2 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow"><i class="fas fa-print mr-2"></i>Print Receipt</button>
                <button onclick="document.getElementById('receiptModal').classList.add('hidden')" class="px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-200 rounded-lg">Close</button>
            </div>
        </div>
    </div>

    <!-- GCash Payment Modal -->
    <!-- PayMongo Simulation Modal (Tailwind) -->
    <div id="payMongoModal" class="fixed inset-0 bg-black/80 hidden z-[80] flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden relative transform transition-all scale-100">
            <!-- Header -->
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <div class="font-bold text-xl tracking-tight text-gray-800">pay<span class="text-green-500">mongo</span></div>
                <button onclick="closePayMongoModal()" class="text-gray-400 hover:text-gray-700 text-2xl font-bold leading-none">&times;</button>
            </div>
            
            <!-- Body -->
            <div class="p-6">
                <!-- Amount Display -->
                <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
                    <span class="text-gray-500 text-sm">Amount Due</span>
                    <span class="text-xl font-bold text-gray-900" id="pm_amount_display">₱0.00</span>
                </div>

                <!-- Payment Methods -->
                <div id="pm_methods" class="space-y-3">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Select Channel</p>
                    
                    <button onclick="simulateClerkPayment('GCash')" class="w-full group hover:bg-blue-50 border border-gray-200 hover:border-blue-200 rounded-xl p-4 flex items-center justify-between transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <span class="font-bold text-gray-700 group-hover:text-blue-700">GCash</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-300 group-hover:text-blue-400"></i>
                    </button>
                    <!-- GrabPay and Card removed as per request -->
                </div>

                <!-- QR Scan Simulation -->
                <div id="pm_qr_scan" class="hidden py-6 text-center">
                    <p class="text-sm font-bold text-gray-800 mb-4">Scan QR Code to Pay</p>
                    <div class="bg-white p-3 rounded-xl shadow-inner border border-gray-100 inline-block mb-4 relative">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/QR_code_for_mobile_English_Wikipedia.svg/1200px-QR_code_for_mobile_English_Wikipedia.svg.png" class="w-40 h-40 object-contain opacity-90">
                        <!-- Scan overlay animation -->
                        <div class="absolute top-0 left-0 w-full h-1 bg-blue-500 opacity-50 shadow-[0_0_10px_rgba(59,130,246,0.5)] animate-[scan_2s_ease-in-out_infinite]"></div>
                        <style>@keyframes scan { 0%,100% { top: 10%; } 50% { top: 90%; } }</style>
                    </div>
                    <div class="flex items-center justify-center gap-2">
                         <div class="spinner-border w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                         <span class="text-xs text-blue-600 font-bold animate-pulse">Waiting for scan...</span>
                    </div>
                </div>

                <!-- Processing State -->
                <div id="pm_processing" class="hidden py-8 text-center">
                    <div class="inline-block w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                    <div class="font-bold text-gray-800 animate-pulse">Processing Transaction...</div>
                    <p class="text-xs text-gray-500 mt-2">Connecting to payment gateway</p>
                </div>

                <!-- Success State -->
                <div id="pm_success" class="hidden py-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 text-green-500 rounded-full mb-4 animate-bounce">
                        <i class="fas fa-check text-3xl"></i>
                    </div>
                    <h4 class="font-bold text-xl text-gray-900 mb-1">Payment Successful!</h4>
                    <p class="text-sm text-gray-500 font-mono" id="pm_ref_display">REF: ---</p>
                </div>

                <!-- Hidden Form for Submission -->
                <form method="POST" id="paymongo_clerk_form">
                    <input type="hidden" name="pay_bill" value="1">
                    <input type="hidden" name="bill_id" id="pm_bill_id">
                    <input type="hidden" name="method" id="pm_method_input">
                    <input type="hidden" name="reference_no" id="pm_ref_input">
                </form>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-50 p-3 text-center">
                 <div class="text-[10px] text-gray-400 flex items-center justify-center gap-1">
                    <i class="fas fa-lock"></i> Secured by PayMongo Test Environment
                 </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId, el) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            el.classList.add('active');
        }

        function viewReceipt(id, date, name, amount, method) {
            document.getElementById('r_id').innerText = String(id).padStart(6, '0');
            document.getElementById('r_date').innerText = date;
            document.getElementById('r_name').innerText = name;
            document.getElementById('r_amount1').innerText = amount;
            
            const methodEl = document.getElementById('r_method');
            const labelEl = document.getElementById('r_amount_label'); 
            
            methodEl.innerText = method;
            
            // Better visual approach for Full Pre-paid Online
            if (method.toUpperCase().includes('ONLINE') || method.toUpperCase().includes('DIGITAL') || method.toUpperCase().includes('GCASH')) {
                methodEl.parentElement.className = "font-bold text-xs uppercase bg-blue-50 border-blue-200 border px-2 py-1 rounded text-blue-700 shadow-sm";
                if(labelEl) labelEl.innerText = "Amount Settled";
            } else {
                methodEl.parentElement.className = "font-bold text-xs uppercase bg-white border px-2 py-1 rounded text-gray-700 shadow-sm";
                if(labelEl) labelEl.innerText = "Amount Paid";
            }
            
            // Show modal manually since we are not using bootstrap JS in clerk dashboard usually, 
            // but we need to check if we have included bootstrap or just tailwind. 
            // The file uses Tailwind mostly. We will use a Tailwind Modal approach.
            document.getElementById('receiptModal').classList.remove('hidden');
        }

        function printReceipt() {
             var content = document.getElementById('printableReceipt').innerHTML;
             var win = window.open('', '', 'height=600,width=400');
             // Use Tailwind for printing to match the modal look
             win.document.write('<html><head><title>Receipt</title><script src="https://cdn.tailwindcss.com"><\/script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head><body class="bg-gray-100 flex items-center justify-center min-h-screen"><div class="bg-white p-4 w-full">' + content + '</div></body></html>');
             win.document.close();
             // Delay print slightly to ensure styles load
             setTimeout(() => {
                 win.print();
             }, 500);
        }

        function openPayMongoModal(billId, amount) {
            document.getElementById('pm_bill_id').value = billId;
            document.getElementById('pm_amount_display').innerText = '₱' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2});
            
            document.getElementById('pm_methods').classList.remove('hidden');
            document.getElementById('pm_qr_scan').classList.add('hidden'); // Reset QR
            document.getElementById('pm_processing').classList.add('hidden');
            document.getElementById('pm_success').classList.add('hidden');
            
            document.getElementById('payMongoModal').classList.remove('hidden');
        }

        function closePayMongoModal() {
            document.getElementById('payMongoModal').classList.add('hidden');
        }

        function simulateClerkPayment(method) {
            document.getElementById('pm_method_input').value = method;
            
            // 1. Show QR Code
            document.getElementById('pm_methods').classList.add('hidden');
            document.getElementById('pm_qr_scan').classList.remove('hidden');
            
            // 2. Simulate Scan Delay (3s)
            setTimeout(() => {
                document.getElementById('pm_qr_scan').classList.add('hidden');
                document.getElementById('pm_processing').classList.remove('hidden');
                
                // 3. Process Payment
                setTimeout(() => {
                    const ref = 'PAYID-' + Math.random().toString(36).substr(2, 9).toUpperCase();
                    document.getElementById('pm_ref_display').innerText = 'REF: ' + ref;
                    document.getElementById('pm_ref_input').value = ref;
                    
                    document.getElementById('pm_processing').classList.add('hidden');
                    document.getElementById('pm_success').classList.remove('hidden');
                    
                    // 4. Submit
                    setTimeout(() => {
                         document.getElementById('paymongo_clerk_form').submit();
                    }, 1500);
                }, 1500);
            }, 3000);
        }

        function openCancelModal(id) {
            document.getElementById('modal_cancel_id').value = id;
            
            // Reset modal state
            document.getElementById('clerk_reason_select').value = "";
            document.getElementById('clerk_other_reason_div').classList.add('hidden');
            document.getElementById('clerk_reason_note').required = false;

            document.getElementById('cancelModal').classList.remove('hidden');
        }

        function viewProof(url, refNo) {
            document.getElementById('modalImage').src = url;
            document.getElementById('modalRefNo').textContent = refNo || "N/A";
            document.getElementById('imageModal').classList.remove('hidden');
        }

        // JS Logic for Combo Box + Note
        function toggleClerkReasonInput(select) {
            var noteDiv = document.getElementById('clerk_other_reason_div');
            var noteInput = document.getElementById('clerk_reason_note');
            
            if(select.value === 'Others') {
                noteDiv.classList.remove('hidden');
                noteInput.required = true;
            } else {
                noteDiv.classList.add('hidden');
                noteInput.required = false;
                noteInput.value = ''; // clear
            }
        }

        // --- GRAPH INITIALIZATION ---
        const ctx = document.getElementById('trafficChart');
        if(ctx && typeof Chart !== 'undefined') {
            try {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'Daily Appointments',
                            data: <?php echo json_encode($chart_data); ?>,
                            borderColor: '#0f766e',
                            backgroundColor: (context) => {
                                const bg = context.chart.ctx.createLinearGradient(0, 0, 0, 200);
                                bg.addColorStop(0, 'rgba(15, 118, 110, 0.2)');
                                bg.addColorStop(1, 'rgba(15, 118, 110, 0)');
                                return bg;
                            },
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#0f766e',
                            pointBorderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { stepSize: 1 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            } catch(e) { console.error("Chart Error:", e); }
        }

        // Inject PHP data into JS
        var patientsData = <?php echo json_encode($pat_array); ?>;

        function switchTab(tabId, el) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            el.classList.add('active');
        }

        // --- REUSABLE AUTOCOMPLETE FUNCTION ---
        function setupAutocomplete(inputId, listId, hiddenId, onSelectCallback = null) {
            const input = document.getElementById(inputId);
            const list = document.getElementById(listId);
            
            if (!input || !list) return; // Prevent crash if elements missing
            const hidden = document.getElementById(hiddenId);

            function renderList(items) {
                list.innerHTML = '';
                if (items.length === 0) {
                    list.innerHTML = '<div class="p-3 text-sm text-gray-500 text-center">No patient found</div>';
                    return;
                }
                items.forEach(patient => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-teal-50 cursor-pointer border-b border-gray-50 text-sm font-medium transition-colors flex justify-between items-center';
                    
                    let badge = '';
                    if(patient.has_paid_dp) {
                        badge = '<span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold ml-2">DP PAID</span>';
                    }
                    div.innerHTML = `<span>${patient.name}</span>${badge}`;
                    div.onclick = () => {
                        input.value = patient.name;
                        hidden.value = patient.patient_id;
                        list.classList.add('hidden');
                        if (onSelectCallback) onSelectCallback(patient);
                    };
                    list.appendChild(div);
                });
            }

            input.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                hidden.value = ''; 
                if (term.length > 0) {
                    const filtered = patientsData.filter(p => p.name.toLowerCase().includes(term))
                        .sort((a, b) => {
                            const aStarts = a.name.toLowerCase().startsWith(term) ? 0 : 1;
                            const bStarts = b.name.toLowerCase().startsWith(term) ? 0 : 1;
                            return aStarts - bStarts || a.name.localeCompare(b.name);
                        });
                    renderList(filtered);
                    list.classList.remove('hidden');
                } else {
                    list.classList.add('hidden');
                }
            });

            input.addEventListener('focus', () => {
                const term = input.value.toLowerCase();
                renderList(term ? patientsData.filter(p => p.name.toLowerCase().includes(term)) : patientsData);
                list.classList.remove('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !list.contains(e.target)) {
                    list.classList.add('hidden');
                }
            });
        }

        function calculateBill() {
            const fee = parseFloat(document.getElementById('total_service_fee').value) || 0;
            const dp = parseFloat(document.getElementById('deducted_dp').value) || 0;
            const total = Math.max(0, fee - dp);
            document.getElementById('final_amount').value = total.toFixed(2);
        }

        // Calendar and Time Slot Management for Walk-in
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        function renderCalendar() {
            const calendarDays = document.getElementById('calendar_days');
            if (!calendarDays) return;

            const firstDay = new Date(currentYear, currentMonth, 1);
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();

            document.getElementById('current_month').textContent = 
                firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

            let html = '';
            
            // Add empty cells for days before the first day of the month
            for (let i = 0; i < startingDayOfWeek; i++) {
                html += '<div class="p-2"></div>';
            }

            // Add day cells
            for (let day = 1; day <= daysInMonth; day++) {
                const currentDate = new Date(currentYear, currentMonth, day);
                const isPast = currentDate < today;
                const isToday = currentDate.getTime() === today.getTime();
                
                let classes = 'p-2 text-center rounded-lg cursor-pointer transition-all ';
                if (isPast) {
                    classes += 'text-gray-400 cursor-not-allowed';
                } else if (isToday) {
                    classes += 'bg-blue-100 text-blue-700 hover:bg-blue-200 font-semibold';
                } else {
                    classes += 'hover:bg-blue-50 text-gray-700';
                }

                const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const onclick = isPast ? '' : `onclick="selectDate('${dateStr}')"`;
                
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
            // Update selected date input
            document.getElementById('selected_date').value = dateStr;
            
            // Update visual selection
            document.querySelectorAll('#calendar_days > div').forEach(el => {
                el.classList.remove('bg-blue-600', 'text-white', 'font-bold');
                if (el.dataset.date === dateStr && !el.classList.contains('text-gray-400')) {
                    el.classList.add('bg-blue-600', 'text-white', 'font-bold');
                }
            });

            // Get selected service
            const serviceSelect = document.getElementById('walkin_service');
            if (!serviceSelect || !serviceSelect.value) {
                Swal.fire('Error', 'Please select a service first', 'error');
                return;
            }

            // Fetch available time slots
            fetchTimeSlots(dateStr);
        }

        function fetchTimeSlots(date) {
            const timeSlotsSection = document.getElementById('time_slots_section');
            const timeSlotsContainer = document.getElementById('time_slots_container');
            
            // Show loading state
            timeSlotsContainer.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-600"></i> Loading available slots...</div>';
            timeSlotsSection.classList.remove('hidden');

            // Fetch available slots via AJAX
            fetch('get_available_slots.php?date=' + date)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.text(); // Get as text first to see what we're getting
                })
                .then(text => {
                    console.log('Response text:', text); // Debug log
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            renderTimeSlots(data.slots);
                        } else {
                            console.error('API Error:', data.error);
                            timeSlotsContainer.innerHTML = '<div class="text-center py-4 text-red-600">Error: ' + (data.error || 'Unknown error') + '</div>';
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e, 'Response:', text);
                        timeSlotsContainer.innerHTML = '<div class="text-center py-4 text-red-600">Invalid response from server</div>';
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    timeSlotsContainer.innerHTML = '<div class="text-center py-4 text-red-600">Error: ' + error.message + '</div>';
                });
        }

        function renderTimeSlots(slots) {
            const timeSlotsContainer = document.getElementById('time_slots_container');
            
            if (slots.length === 0) {
                timeSlotsContainer.innerHTML = '<div class="text-center py-4 text-gray-500">No available time slots for this date</div>';
                return;
            }

            let html = '<div class="grid grid-cols-3 gap-3">';
            slots.forEach(slot => {
                const available = slot.available;
                const booked = slot.booked || 0;
                const maxSlots = 4; // Max 4 Midwives
                
                // Different styles for available vs unavailable
                let classes, statusBadge, onclick;
                
                if (available) {
                    // Available - Green theme
                    classes = 'bg-green-50 border-2 border-green-500 text-green-700 hover:bg-green-600 hover:text-white hover:border-green-600 cursor-pointer';
                    statusBadge = `<span class="text-xs block mt-1 font-normal">${booked}/${maxSlots} booked</span>`;
                    onclick = `onclick="selectTimeSlot('${slot.time}')"`;
                } else {
                    // Unavailable - Red theme
                    classes = 'bg-red-50 border-2 border-red-300 text-red-400 cursor-not-allowed opacity-60';
                    statusBadge = `<span class="text-xs block mt-1 font-normal">Full (${booked}/${maxSlots})</span>`;
                    onclick = '';
                }
                
                html += `
                    <button type="button" 
                            class="time-slot-btn px-3 py-3 rounded-lg font-semibold transition-all ${classes}" 
                            ${onclick} 
                            data-time="${slot.time}"
                            ${!available ? 'disabled' : ''}>
                        <div class="text-sm">${slot.time}</div>
                        ${statusBadge}
                    </button>
                `;
            });
            html += '</div>';
            
            timeSlotsContainer.innerHTML = html;
        }

        function selectTimeSlot(time) {
            // Update selected time input
            document.getElementById('selected_time').value = time;
            
            // Update visual selection - remove previous selection styling
            document.querySelectorAll('.time-slot-btn').forEach(btn => {
                // Remove selected state classes
                btn.classList.remove('!bg-blue-600', '!text-white', '!border-blue-600', 'ring-4', 'ring-blue-300');
                
                // Add selected state to clicked button
                if (btn.dataset.time === time && !btn.disabled) {
                    btn.classList.add('!bg-blue-600', '!text-white', '!border-blue-600', 'ring-4', 'ring-blue-300');
                }
            });

            // Enable submit button
            document.getElementById('walkin_submit_btn').disabled = false;
        }

        function fetchSvcInfo(select, displayId) {
            const display = document.getElementById(displayId);
            const val = select.value;
            if(!val) {
                display.classList.add('hidden');
                display.innerHTML = '';
                return;
            }
            fetch('get_service_info.php?service=' + encodeURIComponent(val))
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        let html = `
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] font-bold text-teal-700 uppercase flex items-center gap-1">
                                    <i class="fas fa-money-bill-wave"></i> Cash Price: ₱${parseFloat(data.price).toLocaleString('en-US', {minimumFractionDigits: 2})}
                                </span>`;
                        
                        if(data.case_rate > 0) {
                            html += `
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 max-w-fit rounded text-[9px] font-bold bg-green-50 text-green-700 border border-green-200 uppercase">
                                    <i class="fas fa-shield-alt"></i> PHIC Case Rate: ₱${parseFloat(data.case_rate).toLocaleString('en-US', {minimumFractionDigits: 2})}
                                </span>`;
                        }
                        
                        html += `</div>`;
                        display.innerHTML = html;
                        display.classList.remove('hidden');
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Setup Walk-in Autocomplete
            setupAutocomplete('walkin_search_input', 'walkin_dropdown', 'walkin_hidden_id');

            // --- AUTO-FILL FOR NEWLY REGISTERED PATIENT ---
            const urlParams = new URLSearchParams(window.location.search);
            const newRegId = urlParams.get('new_reg_id');
            const newRegName = urlParams.get('new_reg_name');
            
            if (newRegId && newRegName) {
                // Populate the Existing Patient Search
                const searchInput = document.getElementById('walkin_search_input');
                const hiddenInput = document.getElementById('walkin_hidden_id');
                if (searchInput && hiddenInput) {
                    searchInput.value = decodeURIComponent(newRegName);
                    hiddenInput.value = newRegId;
                    
                    // Switch to register tab and scroll to existing patient form
                    switchTab('register', document.querySelector('button[onclick*="register"]'));
                    document.getElementById('walkin_form').scrollIntoView({ behavior: 'smooth' });
                    
                    // Highlight the input
                    searchInput.classList.add('ring-4', 'ring-blue-200', 'bg-blue-50');
                    setTimeout(() => searchInput.classList.remove('ring-4', 'ring-blue-200', 'bg-blue-50'), 3000);
                }
            }

            // Setup Billing Autocomplete with new logic
            setupAutocomplete('bill_search_input', 'bill_dropdown', 'bill_hidden_id', function(patient) {
                const dpInput = document.getElementById('deducted_dp');
                const note = document.getElementById('dp_feedback');
                
                if (patient.has_paid_dp) {
                    dpInput.value = "500.00";
                    note.innerHTML = '<span class="text-green-600 flex items-center gap-1"><i class="fas fa-check-circle"></i> Down payment found. ₱500.00 deducted.</span>';
                } else {
                    dpInput.value = "0.00";
                    note.innerHTML = '<span class="text-orange-500 flex items-center gap-1"><i class="fas fa-info-circle"></i> No recent paid down payment found.</span>';
                }
                calculateBill(); // Recalculate immediately
            });

            // Initialize calendar
            renderCalendar();

            // Add form validation for walk-in submission
            const walkinForm = document.getElementById('walkin_form');
            if (walkinForm) {
                walkinForm.addEventListener('submit', function(e) {
                    const selectedDate = document.getElementById('selected_date').value;
                    const selectedTime = document.getElementById('selected_time').value;
                    
                    if (!selectedDate || !selectedTime) {
                        e.preventDefault();
                        Swal.fire('Error', 'Please select both date and time for the appointment', 'error');
                        return false;
                    }
                });
            }
        });


    </script>
</body>
</html>