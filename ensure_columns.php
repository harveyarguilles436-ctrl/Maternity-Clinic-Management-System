<?php
require 'config.php';
$conn->query("ALTER TABLE patient ADD COLUMN IF NOT EXISTS birthdate DATE");
$conn->query("ALTER TABLE patient ADD COLUMN IF NOT EXISTS sex VARCHAR(10)");
$conn->query("ALTER TABLE patient ADD COLUMN IF NOT EXISTS age INT");
?>
