<?php
require 'config.php';
$result = $conn->query("SHOW COLUMNS FROM appointment");
while($row = $result->fetch_assoc()){
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n--- Service Pricing ---\n";
$result = $conn->query("SELECT * FROM service_pricing");
while($row = $result->fetch_assoc()){
    echo $row['service_name'] . " - " . $row['price'] . "\n";
}
?>
