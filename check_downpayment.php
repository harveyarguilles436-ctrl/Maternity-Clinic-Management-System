<?php
include 'config.php';
header('Content-Type: application/json');

$pid = $_GET['pid'] ?? '';
if (empty($pid)) {
    echo json_encode(['success' => false, 'error' => 'No patient ID']);
    exit;
}

// Check for a confirmed/completed appointment with a paid downpayment today
// Check for any paid downpayment for this patient that hasn't been cancelled/rejected
$q = $conn->query("SELECT appointment_id, payment_mode 
                   FROM appointment 
                   WHERE patient_id='$pid' 
                   AND down_payment_status='Paid' 
                   AND status NOT IN ('Cancelled', 'Rejected') 
                   ORDER BY appointment_date DESC, appointment_id DESC
                   LIMIT 1");

if ($q->num_rows > 0) {
    $row = $q->fetch_assoc();
    $mode = $row['payment_mode'] ?? 'DownPayment';
    echo json_encode(['success' => true, 'has_dp' => true, 'payment_mode' => $mode]);
} else {
    echo json_encode(['success' => true, 'has_dp' => false, 'amount' => 0]);
}
?>
