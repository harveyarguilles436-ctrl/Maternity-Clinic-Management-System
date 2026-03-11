<?php
include 'config.php';
header('Content-Type: application/json');

if(!isset($_GET['pid']) || empty($_GET['pid'])) {
    echo json_encode(['success' => false, 'message' => 'No Patient ID']);
    exit;
}

$pid = intval($_GET['pid']);

$response = ['success' => true];

// Count Prenatal Records
$q = $conn->query("SELECT COUNT(*) as c FROM prenatal_records WHERE patient_id='$pid'");
$response['prenatal_count'] = ($q && $q->num_rows > 0) ? intval($q->fetch_assoc()['c']) : 0;

// Count Delivery Records
$q = $conn->query("SELECT COUNT(*) as c FROM delivery_records WHERE patient_id='$pid'");
$response['delivery_count'] = ($q && $q->num_rows > 0) ? intval($q->fetch_assoc()['c']) : 0;

// Count Newborn Records
$q = $conn->query("SELECT COUNT(*) as c FROM newborn_records WHERE patient_id='$pid'");
$response['newborn_count'] = ($q && $q->num_rows > 0) ? intval($q->fetch_assoc()['c']) : 0;

// Count Postnatal Records
$q = $conn->query("SELECT COUNT(*) as c FROM postnatal_records WHERE patient_id='$pid'");
$response['postnatal_count'] = ($q && $q->num_rows > 0) ? intval($q->fetch_assoc()['c']) : 0;

echo json_encode($response);
?>
