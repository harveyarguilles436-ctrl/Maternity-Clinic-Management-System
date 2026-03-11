<?php
require_once 'config.php';

// Add paid_amount column to appointment table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM appointment LIKE 'paid_amount'");
if ($check->num_rows == 0) {
    echo "Adding paid_amount column to appointment table...\n";
    $sql = "ALTER TABLE appointment ADD COLUMN paid_amount DECIMAL(10,2) DEFAULT 0.00 AFTER down_payment_status";
    if ($conn->query($sql) === TRUE) {
        echo "Column added successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column paid_amount already exists.\n";
}

// Optional: Backfill existing data based on current service prices (Best Effort)
// This is important because otherwise historic reports will show 0
echo "Backfilling existing paid appointments...\n";
$update = "UPDATE appointment a 
           JOIN service_pricing sp ON a.service = sp.service_name 
           SET a.paid_amount = CASE 
               WHEN a.payment_mode = 'Full' THEN sp.price 
               ELSE sp.price * 0.5 
           END 
           WHERE a.paid_amount = 0 AND (a.down_payment_status = 'Paid' OR a.payment_mode = 'Full')";
$conn->query($update);
echo "Backfill complete.\n";

echo "Done.";
?>
