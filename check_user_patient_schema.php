<?php
require 'config.php';
echo "--- Users Table ---\n";
$res1 = $conn->query("SHOW COLUMNS FROM users");
while($row = $res1->fetch_assoc()) echo $row['Field'] . " - " . $row['Type'] . "\n";

echo "\n--- Patient Table ---\n";
$res2 = $conn->query("SHOW COLUMNS FROM patient");
while($row = $res2->fetch_assoc()) echo $row['Field'] . " - " . $row['Type'] . "\n";
?>
