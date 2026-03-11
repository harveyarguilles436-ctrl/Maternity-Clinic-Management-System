<?php
include 'config.php';
$res = $conn->query("SHOW COLUMNS FROM pending_charges");
while($r = $res->fetch_assoc()) echo $r['Field'] . "\n";
?>
