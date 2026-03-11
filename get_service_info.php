<?php
include 'config.php';
header('Content-Type: application/json');

if (isset($_GET['service'])) {
    $service = $_GET['service'];
    $stmt = $conn->prepare("SELECT price, case_rate, service_category FROM service_pricing WHERE service_name = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $service);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'success' => true, 
            'price' => (float)$row['price'], 
            'case_rate' => (float)($row['case_rate'] ?? 0),
            'category' => $row['service_category']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Service not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No service specified']);
}
?>
