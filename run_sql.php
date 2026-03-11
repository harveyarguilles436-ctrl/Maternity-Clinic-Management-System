<?php
include 'config.php';

$sql = file_get_contents('repair_constraints_v3.sql');

if ($conn->multi_query($sql)) {
    echo "Tables created successfully.";
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error creating tables: " . $conn->error;
}
?>
