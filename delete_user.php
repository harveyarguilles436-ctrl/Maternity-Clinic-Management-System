<?php
session_start();
include 'config.php';

// Only Admin can delete
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit; 
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Prevent Admin from deleting themselves
    if ($id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE user_id=$id");
    }
}

header("Location: admin_dashboard.php");
exit;
?>