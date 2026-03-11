<?php
require 'config.php';
$res = $conn->query("DESCRIBE users");
while($row = $res->fetch_assoc()) echo $row['Field'] . " (" . $row['Type'] . ")\n";
echo "---\n";
$res = $conn->query("DESCRIBE patient");
while($row = $res->fetch_assoc()) echo $row['Field'] . " (" . $row['Type'] . ")\n";
?>
