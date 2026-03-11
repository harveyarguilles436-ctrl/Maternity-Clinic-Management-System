<?php
require 'config.php';
$res = $conn->query("SELECT * FROM service_pricing");
$out = "";
while($row = $res->fetch_assoc()){
    foreach($row as $k => $v) $out .= "$k: $v | ";
    $out .= "\n";
}
file_put_contents('service_details.txt', $out);
?>
