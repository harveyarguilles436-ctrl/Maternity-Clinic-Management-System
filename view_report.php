<?php
include 'config.php';

// NO LOGIN CHECK HERE - This allows anyone with the link to view the report.

// --- GET PARAMETERS ---
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'Appointments';

// Title
$report_title = strtoupper($report_type) . " REPORT"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_type; ?> Report</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { "primary": "#0f766e", "text-main": "#334155", "border": "#e2e8f0" },
                    fontFamily: { "sans": ["Inter", "sans-serif"] }
                }
            }
        }
    </script>

    <style>
        /* Force Professional Layout */
        body { background: white; color: black; font-family: 'Inter', sans-serif; }
        .report-container { max-width: 900px; margin: 40px auto; border: 1px solid #ddd; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        
        /* Table Styles */
        table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 20px; }
        th { border-bottom: 2px solid #000; text-transform: uppercase; font-weight: 700; padding: 10px 5px; text-align: left; }
        td { border-bottom: 1px solid #ddd; padding: 10px 5px; }

        /* Print Optimization */
        @media print {
            .no-print { display: none !important; }
            .report-container { border: none; box-shadow: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="max-width: 900px; margin: 20px auto; display: flex; justify-content: space-between;">
        <button onclick="window.history.back()" class="text-sm font-bold text-gray-500 hover:text-black">← Back</button>
        <button onclick="window.print()" class="bg-primary text-white px-4 py-2 rounded text-sm font-bold shadow hover:bg-teal-800">Print Report</button>
    </div>

    <div class="report-container">
        
        <div class="text-center mb-8">
            <h1 style="font-size: 26px; font-weight: 800; text-transform: uppercase; margin: 0; color: black; letter-spacing: -0.5px;">Mother Therese Mothers Clinic</h1>
            <p style="font-size: 13px; color: #333; margin: 4px 0 0 0;">97 B.S. Aquino Avenue, Tangos, Baliwag City, 3006 Bulacan</p>
            <p style="font-size: 13px; color: #333; margin: 0 0 15px 0;">Contact: 0917 843 4589</p>
            <div style="border-bottom: 2px solid #000; margin-bottom: 25px;"></div>
            
            <h2 style="font-size: 18px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; text-align: left; color: black;">
                <?php echo $report_title; ?>
            </h2>
            <p style="font-size: 12px; text-align: left; margin-bottom: 20px; color: #000;">
                <strong>Period:</strong> <?php echo date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?>
            </p>
        </div>

        <table class="w-full text-left">
            <thead>
                <tr>
                    <?php if($report_type == 'Appointments'): ?>
                        <th>Date</th><th>Time</th><th>Patient Name</th><th>Status</th>
                    <?php elseif($report_type == 'Sales'): ?>
                        <th>Date</th><th>Bill ID</th><th>Patient Name</th><th>Amount</th><th>Method</th>
                    <?php else: ?>
                        <th>ID</th><th>Full Name</th><th>Contact</th><th>Type</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($report_type == 'Appointments') {
                    $sql = "SELECT a.*, p.name FROM appointment a JOIN patient p ON a.patient_id = p.patient_id WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date' ORDER BY a.appointment_date DESC";
                    $res = $conn->query($sql);
                    if($res->num_rows > 0) {
                        while($row = $res->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['appointment_date']}</td>
                                <td>".date('h:i A', strtotime($row['appointment_time']))."</td>
                                <td style='font-weight:bold;'>{$row['name']}</td>
                                <td>{$row['status']}</td>
                            </tr>";
                        }
                    } else echo "<tr><td colspan='4' style='text-align:center; padding:20px;'>No records found.</td></tr>";
                } elseif ($report_type == 'Sales') {
                    // Combine Billing (Balance) and Appointment Downpayments (Deposits)
                    // Include both active and cancelled appointments with paid downpayments
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
                                    'Deposit' as method, 
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
                    $total = 0;
                    if($res && $res->num_rows > 0) {
                        while($row = $res->fetch_assoc()) {
                            $total += $row['amount'];
                            $type_label = $row['type'];
                            echo "<tr>
                                <td>".date('M d, Y', strtotime($row['pay_date']))."</td>
                                <td>#{$row['ref_id']}</td>
                                <td>{$row['name']}</td>
                                <td>₱".number_format($row['amount'], 2)."</td>
                                <td>{$row['method']} <span style='font-size:10px; color:#666;'>({$type_label})</span></td>
                            </tr>";
                        }
                        echo "<tr><td colspan='5' style='border-top:2px solid #000; padding-top:15px;'></td></tr>";
                        echo "<tr style='font-weight:bold; background:#f8fafc;'><td colspan='3' style='text-align:right;'>TOTAL SALES:</td><td colspan='2' style='color:#0f766e;'>₱".number_format($total, 2)."</td></tr>";
                    } else echo "<tr><td colspan='5' style='text-align:center; padding:20px;'>No sales records found.</td></tr>";
                } else {
                    $sql = "SELECT * FROM patient ORDER BY patient_id DESC";
                    $res = $conn->query($sql);
                    while($row = $res->fetch_assoc()) {
                        echo "<tr><td>{$row['patient_id']}</td><td style='font-weight:bold;'>{$row['name']}</td><td>{$row['contact_no']}</td><td>Client</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <div style="margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div style="font-size: 10px; color: #666;">
                <p>System Generated Report</p>
                <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
            </div>
            <div style="text-align: center;">
                <div style="border-bottom: 1px solid #000; width: 200px; margin-bottom: 5px;"></div>
                <p style="font-weight: bold; text-transform: uppercase; font-size: 12px; color: black;">Administrator</p>
                <p style="font-size: 10px; color: #444;">Authorized Signature</p>
            </div>
        </div>

    </div>

</body>
</html>