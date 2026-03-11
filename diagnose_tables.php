<?php
include 'config.php';
$tables = ['prenatal_records', 'ultrasound_records', 'laboratory_records', 'postnatal_records', 'family_planning_records', 'immunization_records', 'consultation_records'];

foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "<br>";
        }
    } else {
        echo "Error: " . $conn->error;
    }
    echo "<hr>";
}
?>
