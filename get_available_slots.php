<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Not logged in']);
    exit;
}

// Check if user is authorized (Clerk or Client)
if (!isset($_SESSION['role']) || (strcasecmp($_SESSION['role'], 'Clerk') !== 0 && strcasecmp($_SESSION['role'], 'Client') !== 0)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get the requested date
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (empty($date)) {
    echo json_encode(['success' => false, 'error' => 'Date is required']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
    exit;
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Define available time slots (1-hour intervals)
// Define available time slots
// DEFAULT: 6:00 AM - 9:00 PM (Client View)
$allTimeSlots = [
    '06:00 AM', '07:00 AM', '08:00 AM', '09:00 AM', '10:00 AM', '11:00 AM',
    '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM',
    '06:00 PM', '07:00 PM', '08:00 PM', '09:00 PM'
];

// OVERRIDE: 24/7 for Clerk (Walk-in / Emergency)
if (isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'Clerk') === 0) {
    $allTimeSlots = [
        '12:00 AM', '01:00 AM', '02:00 AM', '03:00 AM', '04:00 AM', '05:00 AM',
        '06:00 AM', '07:00 AM', '08:00 AM', '09:00 AM', '10:00 AM', '11:00 AM',
        '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM',
        '06:00 PM', '07:00 PM', '08:00 PM', '09:00 PM', '10:00 PM', '11:00 PM'
    ];
}

// Fetch booked appointments for the selected date
$bookedSlots = [];

try {
    $query = "SELECT appointment_time FROM appointment WHERE appointment_date = ? AND status != 'Cancelled'";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Query preparation failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("s", $date);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Query execution failed: ' . $stmt->error]);
        exit;
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $bookedSlots[] = $row['appointment_time'];
    }
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Count bookings per slot to determine availability (max 5 per slot)
$slotCounts = array_count_values($bookedSlots);

// Build the response with availability status
$slots = [];
$currentDate = date('Y-m-d');
$currentTime = time(); // Current server timestamp

foreach ($allTimeSlots as $slot) {
    $count = isset($slotCounts[$slot]) ? $slotCounts[$slot] : 0;
    
    // Check if slot is in the past (only if date is today)
    $isPast = false;
    if ($date === $currentDate) {
        $slotTime = strtotime($date . ' ' . $slot);
        if ($slotTime < $currentTime) {
            $isPast = true;
        }
    }
    
    // Available if count < 4 (Midwife Capacity) AND not in past
    // Special Rule: 12:00 PM is ALWAYS available
    $available = ($count < 4 || $slot === '12:00 PM') && !$isPast;
    
    $slots[] = [
        'time' => $slot,
        'available' => $available,
        'booked' => $count,
        'is_past' => $isPast
    ];
}

echo json_encode([
    'success' => true,
    'date' => $date,
    'slots' => $slots
]);
?>
