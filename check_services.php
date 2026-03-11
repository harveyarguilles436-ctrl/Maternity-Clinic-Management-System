<?php
include 'config.php';
$res = $conn->query("SELECT * FROM service_pricing");
if($res->num_rows > 0){
    while($row = $res->fetch_assoc()){
        echo "ID: " . $row['service_id'] . " | Name: " . $row['service_name'] . " | Cat: " . $row['service_category'] . "\n";
    }
} else {
    echo "No services found.";
}
?>
