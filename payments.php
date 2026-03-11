<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) header("Location: login.php");

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'];
    $amount = $_POST['amount'];
    $method = $_POST['method'];

    if ($amount <= 0) {
        $msg = "<div class='alert error'>Amount must be greater than 0.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO billing (patient_id, bill_date, total_amount, payment_method, status) VALUES (?, NOW(), ?, ?, 'Paid')");
        $stmt->bind_param("ids", $patient_id, $amount, $method);
        if($stmt->execute()){
            $msg = "<div class='alert success'>Payment recorded successfully.</div>";
        } else {
            $msg = "<div class='alert error'>Database Error.</div>";
        }
    }
}

// Fetch Patients for Dropdown
$patients = $conn->query("SELECT patient_id, name FROM patient ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments - MCMIS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>MCMIS Panel</h3>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Appointments</a></li>
            <li><a href="payments.php" class="active">Payments</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="dashboard.php?logout=true">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <h2>Record Payment</h2>
        </header>

        <div class="content-wrapper">
            <?php if(isset($msg)) echo $msg; ?>
            
            <div class="form-wrapper" style="max-width: 600px; margin: 0;">
                <form method="POST">
                    <div class="form-group">
                        <label>Select Patient</label>
                        <select name="patient_id" required>
                            <option value="">-- Choose Patient --</option>
                            <?php while($p = $patients->fetch_assoc()): ?>
                                <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Amount (PHP)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="method" required>
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Record Transaction</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>