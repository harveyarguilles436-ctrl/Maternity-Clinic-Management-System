<?php
// Script to create the medical_records table
include 'config.php';

echo "<h2>Creating Medical Records Table...</h2>";

$sql = "CREATE TABLE IF NOT EXISTS medical_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    midwife_id INT NOT NULL,
    service_type VARCHAR(50) NOT NULL,
    checkup_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Common fields
    medications TEXT,
    findings TEXT,
    next_visit DATE,
    
    -- Prenatal specific
    blood_pressure VARCHAR(20),
    weight_kg VARCHAR(10),
    fetal_heart_rate VARCHAR(20),
    aog_weeks INT,
    fundic_height VARCHAR(10),
    fetal_presentation VARCHAR(50),
    vaginal_bleeding VARCHAR(10),
    fever VARCHAR(10),
    pallor VARCHAR(10),
    edema VARCHAR(10),
    
    -- Ultrasound specific
    indication VARCHAR(100),
    result_summary TEXT,
    
    -- Laboratory specific
    test_type VARCHAR(100),
    lab_status VARCHAR(50),
    
    -- Postnatal specific
    temperature VARCHAR(20),
    uterine_involution VARCHAR(50),
    lochia VARCHAR(50),
    breastfeeding VARCHAR(50),
    
    -- Family Planning specific
    fp_method VARCHAR(100),
    fp_chosen VARCHAR(100),
    
    -- Immunization specific
    vaccine_type VARCHAR(100),
    dose_number VARCHAR(50),
    
    -- Walk-in specific
    chief_complaint TEXT,
    vital_signs TEXT,
    
    FOREIGN KEY (patient_id) REFERENCES patient(patient_id),
    FOREIGN KEY (midwife_id) REFERENCES users(user_id),
    INDEX idx_patient (patient_id),
    INDEX idx_date (checkup_date),
    INDEX idx_service (service_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: medical_records table created successfully!</p>";
    echo "<p>You can now use the midwife dashboard to save medical records.</p>";
    echo "<p><a href='midwife_dashboard.php'>Go to Midwife Dashboard</a></p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ ERROR: " . $conn->error . "</p>";
    
    // Check if table already exists
    $check = $conn->query("SHOW TABLES LIKE 'medical_records'");
    if($check->num_rows > 0) {
        echo "<p style='color: orange;'>ℹ️ The table already exists. You're good to go!</p>";
        echo "<p><a href='midwife_dashboard.php'>Go to Midwife Dashboard</a></p>";
    }
}

$conn->close();
?>
