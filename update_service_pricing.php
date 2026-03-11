<?php
require 'config.php';

// Add case_rate column if it doesn't exist
$conn->query("ALTER TABLE service_pricing ADD COLUMN IF NOT EXISTS case_rate DECIMAL(10,2) DEFAULT 0.00");

$price_overrides = [
    'MCP01' => 12500.00,
    'NSD01' => 11000.00,
    'NCP'   => 5000.00,
    'ANC01' => 1500.00,
    'ANC02' => 2000.00
];

$case_rates = [
    'MCP01' => 15600.00,
    'NSD01' => 12675.00,
    'NCP'   => 5752.50,
    'ANC01' => 2925.00,
    'ANC02' => 4192.50
];

foreach ($price_overrides as $code => $cash_price) {
    $cr = isset($case_rates[$code]) ? $case_rates[$code] : 0;
    
    // Update matching rows where service_name starts with the code
    $pattern = $code . "%";
    $stmt = $conn->prepare("UPDATE service_pricing SET price = ?, case_rate = ? WHERE service_name LIKE ?");
    $stmt->bind_param("dds", $cash_price, $cr, $pattern);
    $stmt->execute();
    $stmt->close();
}

echo "Database updated successfully with Cash Prices and Case Rates.";
?>
