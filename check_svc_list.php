<?php
require 'config.php';
$res = $conn->query("SELECT service_name, service_category FROM service_pricing WHERE is_active=1");
while($r = $res->fetch_assoc()) {
    echo $r['service_name'] . " | " . $r['service_category'] . "\n";
}
?>
