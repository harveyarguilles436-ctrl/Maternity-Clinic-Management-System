<?php
include 'config.php';

$sql_file = 'create_medical_records_tables.sql';
if (!file_exists($sql_file)) {
    die("Error: SQL file not found.");
}

$sql = file_get_contents($sql_file);
$queries = explode(';', $sql);

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        if ($conn->query($query) === TRUE) {
            echo "Query executed successfully.\n";
        } else {
            echo "Error executing query: " . $conn->error . "\n";
        }
    }
}
echo "Database setup completed.";
?>
