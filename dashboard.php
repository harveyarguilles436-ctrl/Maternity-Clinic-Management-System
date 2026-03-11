<?php
session_start();
include 'config.php';

// Feature H: Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Handle Status Update
if (isset($_GET['confirm_id'])) {
    $conn->query("UPDATE appointment SET status='Confirmed' WHERE appointment_id=" . $_GET['confirm_id']);
    header("Location: dashboard.php");
}

// Feature G: Search and Filter Logic
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

$sql = "SELECT a.*, p.name, p.contact_no 
        FROM appointment a 
        JOIN patient p ON a.patient_id = p.patient_id 
        WHERE p.name LIKE '%$search%'";

if (!empty($filter_date)) {
    $sql .= " AND a.appointment_date = '$filter_date'";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - MCMIS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">
    
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>MCMIS Panel</h3>
            <p>Welcome, <?php echo $_SESSION['name']; ?></p>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active">Appointments</a></li>
            <li><a href="payments.php">Payments</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="?logout=true" class="logout-btn">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <h2>Appointment Management</h2>
        </header>

        <div class="content-wrapper">
            <div class="filter-card">
                <form method="GET" class="filter-form">
                    <input type="text" name="search" placeholder="Search Patient Name..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="date" name="date" value="<?php echo $filter_date; ?>">
                    <button type="submit" class="btn-filter">Filter Results</button>
                    <a href="dashboard.php" class="btn-reset">Reset</a>
                </form>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient Name</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['appointment_date']; ?></td>
                                <td><?php echo date("g:i A", strtotime($row['appointment_time'])); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['status'] == 'Pending'): ?>
                                        <a href="?confirm_id=<?php echo $row['appointment_id']; ?>" class="btn-action">Confirm</a>
                                    <?php else: ?>
                                        <span class="text-muted">No Action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;">No appointments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>