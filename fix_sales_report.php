<?php
include 'config.php';

echo "<h2>System Diagnostic & Repair</h2>";

// 1. CHECK AND ADD 'created_at' COLUMN
$check = $conn->query("SHOW COLUMNS FROM appointment LIKE 'created_at'");
if($check && $check->num_rows == 0) {
    echo "Column 'created_at' is MISSING. Attempting to add...<br>";
    $sql = "ALTER TABLE appointment ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if($conn->query($sql)) {
        echo "<span style='color:green'>SUCCESS: Added 'created_at' column.</span><br>";
        
        // Backfill data for existing records
        $update = "UPDATE appointment SET created_at = CONCAT(appointment_date, ' ', appointment_time) WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'";
        if($conn->query($update)) {
            echo "<span style='color:green'>SUCCESS: Backfilled old dates.</span><br>";
        }
    } else {
        echo "<span style='color:red'>ERROR: Could not add column. " . $conn->error . "</span><br>";
    }
} else {
    echo "<span style='color:green'>✓ Column 'created_at' exists.</span><br>";
}

// 2. DIAGNOSE HIDDEN SALES
echo "<h3>Recent Appointments with Payment Proof</h3>";
$sql = "SELECT appointment_id, patient_id, appointment_date, status, down_payment_status, created_at, payment_proof FROM appointment WHERE payment_proof IS NOT NULL ORDER BY appointment_id DESC LIMIT 10";
$res = $conn->query($sql);

if($res && $res->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%'>";
    echo "<tr style='background:#f0f0f0'><th>ID</th><th>Date</th><th>Status</th><th>Pay Status</th><th>Created At</th><th>Proof</th><th>Sales Report Verdict</th></tr>";
    
    while($row = $res->fetch_assoc()) {
        $verdict = "";
        if($row['down_payment_status'] == 'Paid') {
            $verdict = "<span style='color:green'>VISIBLE IN REPORT</span>";
        } else {
            $verdict = "<span style='color:red'>HIDDEN (Status is '{$row['down_payment_status']}')</span><br><small>Must be 'Paid'</small>";
        }
        
        echo "<tr>";
        echo "<td>{$row['appointment_id']}</td>";
        echo "<td>{$row['appointment_date']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['down_payment_status']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "<td>" . ($row['payment_proof'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$verdict}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No appointments with payment proofs found.<br>";
}

echo "<br><br><a href='admin_dashboard.php?tab=reports' style='font-size:18px; font-weight:bold'>&larr; Return to Reports</a>";
?>
