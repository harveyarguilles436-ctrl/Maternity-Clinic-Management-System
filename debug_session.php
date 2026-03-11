<?php
// Debug session information
session_start();

echo "<h1>Session Debug Information</h1>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Data:\n";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Testing get_available_slots.php</h2>";
$testDate = date('Y-m-d');
echo "<p>Date: $testDate</p>";

// Simulate the AJAX call
$_GET['date'] = $testDate;
ob_start();
include 'get_available_slots.php';
$response = ob_get_clean();

echo "<h3>Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

$data = json_decode($response, true);
if ($data) {
    echo "<h3>Decoded JSON:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
}
?>
