<?php
require 'config.php';

// Ensure columns exist in users table
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20)");

// Ensure columns exist in patient table
$conn->query("ALTER TABLE patient ADD COLUMN IF NOT EXISTS birthdate DATE");
$conn->query("ALTER TABLE patient ADD COLUMN IF NOT EXISTS sex VARCHAR(10)");

echo "Database maintenance completed.";
?>
