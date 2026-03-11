<?php
include 'config.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

if (empty($term)) {
    echo json_encode(['success' => false, 'error' => 'No search term provided']);
    exit;
}

// Search for service by name or code
$stmt = $conn->prepare("
    SELECT service_id, service_name, price 
    FROM service_pricing 
    WHERE is_active = 1 
    AND (LOWER(service_name) LIKE ? OR LOWER(service_name) LIKE ?)
    LIMIT 1
");

$searchTerm1 = '%' . strtolower($term) . '%';
$searchTerm2 = strtolower($term) . '%';
$stmt->bind_param("ss", $searchTerm1, $searchTerm2);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'service_id' => $row['service_id'],
        'service_name' => $row['service_name'],
        'price' => $row['price']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Service not found']);
}

$stmt->close();
$conn->close();
?>
