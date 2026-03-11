<?php
include 'config.php';
$result = $conn->query("SHOW COLUMNS FROM appointment");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
