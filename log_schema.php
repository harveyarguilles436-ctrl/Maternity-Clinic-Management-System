<?php
require 'config.php';
$out = "--- Users Table ---\n";
$res1 = $conn->query("SHOW COLUMNS FROM users");
while($row = $res1->fetch_assoc()) $out .= $row['Field'] . " - " . $row['Type'] . "\n";

$out .= "\n--- Patient Table ---\n";
$res2 = $conn->query("SHOW COLUMNS FROM patient");
while($row = $res2->fetch_assoc()) $out .= $row['Field'] . " - " . $row['Type'] . "\n";

file_put_contents('schema_log.txt', $out);
?>
