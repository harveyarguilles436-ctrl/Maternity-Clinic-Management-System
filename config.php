<?php
// You must find these exact values in your InfinityFree Control Panel -> MySQL Databases
$servername = "sql205.infinityfree.com"; // Found in your screenshot
$username = "if0_40821738";              // Found in your screenshot
$password = "7n4SxReJJyn";  // The password you use to login to the Control Panel
$dbname = "if0_40821738_mcmis_db";          // The database name you created in Step 1

// Set Timezone to Philippines
date_default_timezone_set('Asia/Manila');

$conn = new mysqli($servername, $username, $password, $dbname);

// Sync MySQL Timezone with PHP
$conn->query("SET time_zone = '+08:00'");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>