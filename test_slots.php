<?php
// Simple test file to check if get_available_slots.php is working
session_start();

// Set a test session for clerk
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'clerk';

echo "<h1>Testing get_available_slots.php</h1>";

$testDate = date('Y-m-d'); // Today's date
echo "<p>Testing with date: $testDate</p>";

// Make a request to the endpoint
$url = "http://localhost/download/get_available_slots.php?date=" . $testDate;
echo "<p>URL: $url</p>";

// Use file_get_contents to fetch
$response = @file_get_contents($url);

if ($response === false) {
    echo "<p style='color: red;'>ERROR: Could not fetch data from endpoint</p>";
    echo "<p>Trying direct include instead...</p>";
    
    // Try direct include
    $_GET['date'] = $testDate;
    ob_start();
    include 'get_available_slots.php';
    $response = ob_get_clean();
}

echo "<h2>Response:</h2>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to decode JSON
$data = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<h2>Decoded JSON:</h2>";
    echo "<pre>" . print_r($data, true) . "</pre>";
} else {
    echo "<p style='color: red;'>JSON Decode Error: " . json_last_error_msg() . "</p>";
}
?>
