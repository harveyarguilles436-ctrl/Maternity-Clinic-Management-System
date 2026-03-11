<?php
include 'config.php';
$res = $conn->query("SELECT DISTINCT service FROM appointment");
while($row = $res->fetch_assoc()) {
    echo "Service: [" . $row['service'] . "]\n";
}
?>
