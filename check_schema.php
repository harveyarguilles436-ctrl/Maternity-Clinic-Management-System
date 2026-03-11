<?php
include 'config.php';
$result = $conn->query("DESCRIBE pending_charges");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
