<?php
ob_start();
session_start();
include 'config.php';

// Enable Error Reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Security Check
if (!isset($_SESSION['user_id']) || strcasecmp($_SESSION['role'], 'Midwife') !== 0) { 
    header("Location: login.php"); 
    exit; 
}

$midwife_id = $_SESSION['user_id'];

// Auto-fix: Ensure 'is_philhealth' column exists
$check_col = $conn->query("SHOW COLUMNS FROM pending_charges LIKE 'is_philhealth'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE pending_charges ADD COLUMN is_philhealth TINYINT(1) DEFAULT 0");
}

// Auto-fix: Ensure 'delivery_records' table exists and has EINC columns
$conn->query("CREATE TABLE IF NOT EXISTS delivery_records (
    delivery_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    midwife_id INT,
    delivery_date DATE,
    delivery_time TIME,
    sex VARCHAR(10),
    weight_g INT,
    length_cm DECIMAL(5,2),
    apgar_1min INT,
    apgar_5min INT,
    einc_dry TINYINT(1) DEFAULT 0,
    einc_ssc TINYINT(1) DEFAULT 0,
    einc_cord TINYINT(1) DEFAULT 0,
    einc_breast TINYINT(1) DEFAULT 0,
    medications TEXT,
    findings TEXT,
    next_visit DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check and Add specific columns if they don't exist (for existing tables)
$cols_to_check = ['einc_dry', 'einc_ssc', 'einc_cord', 'einc_breast'];
foreach($cols_to_check as $col) {
    if($conn->query("SHOW COLUMNS FROM delivery_records LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE delivery_records ADD COLUMN $col TINYINT(1) DEFAULT 0");
    }
}

// Auto-fix: Ensure 'newborn_records' table exists
$conn->query("CREATE TABLE IF NOT EXISTS newborn_records (
    newborn_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    midwife_id INT,
    checkup_date DATE,
    bcg_given TINYINT(1) DEFAULT 0,
    hepb_given TINYINT(1) DEFAULT 0,
    nbs_done TINYINT(1) DEFAULT 0,
    hearing_test TINYINT(1) DEFAULT 0,
    weight_g INT,
    medications TEXT,
    findings TEXT,
    next_visit DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check and Add specific columns if they don't exist (for delivery)
$cols_to_check = ['einc_dry', 'einc_ssc', 'einc_cord', 'einc_breast', 'partograph_used'];
foreach($cols_to_check as $col) {
    if($conn->query("SHOW COLUMNS FROM delivery_records LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE delivery_records ADD COLUMN $col TINYINT(1) DEFAULT 0");
    }
}

// Auto-fix: Ensure 'prenatal_records' has package columns
$cols_prenatal = ['iron_supp', 'calcium_supp', 'tetanus_toxoid', 'deworming', 'birth_plan', 'dental_advice'];
foreach($cols_prenatal as $col) {
    if($conn->query("SHOW COLUMNS FROM prenatal_records LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE prenatal_records ADD COLUMN $col TINYINT(1) DEFAULT 0");
    }
}

// Auto-fix: Ensure 'postnatal_records' has package columns
$cols_postnatal = ['vit_a', 'iron_supp', 'fp_counseling', 'perineal_care', 'breastfeeding_support'];
foreach($cols_postnatal as $col) {
    if($conn->query("SHOW COLUMNS FROM postnatal_records LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE postnatal_records ADD COLUMN $col TINYINT(1) DEFAULT 0");
    }
}

// Auto-fix: Ensure 'newborn_records' has package columns
$cols_newborn = ['vit_k', 'eye_prophylaxis', 'cord_care'];
foreach($cols_newborn as $col) {
    if($conn->query("SHOW COLUMNS FROM newborn_records LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE newborn_records ADD COLUMN $col TINYINT(1) DEFAULT 0");
    }
}


// Session-based alerts
$alert = '';
if (isset($_SESSION['msg'])) {
    $alert = $_SESSION['msg'];
    unset($_SESSION['msg']);
}
// Capture SweetAlert objects
$swal_success = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : null;
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : null;
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);

$msg = $alert; // Backward compatibility for some blocks

// --- LOGIC 0: AJAX HANDLER FOR SERVING STATUS ---
if (isset($_POST['mark_arrived'])) {
    $pid = $_POST['patient_id'];
    $conn->query("UPDATE appointment SET status='Arrived' WHERE patient_id='$pid' AND appointment_date=CURDATE() AND status='Confirmed'");
    exit;
}

// --- LOGIC 1: RECORD SERVICE DELIVERY ---
if (isset($_POST['save_record'])) {
    $pid = $_POST['patient_id']; 
    $service_type = $_POST['service_type']; // Form Type
    $booked_svc = $_POST['booked_service_name'] ?? $service_type; // Exact Package Name
    $meds = $_POST['medications'];
    $findings = $_POST['findings'];
    $next_visit = $_POST['next_visit']; 

    // Common Validation
    if(empty($pid) || empty($service_type)) {
        $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Please select a patient and service type.</div>";
    } else {
        $query = "";
        $types = "";
        $params = [];

        // Switch based on service type
        switch ($service_type) {
            case 'prenatal':
                // Checkboxes
                $iron = isset($_POST['prenatal_iron']) ? 1 : 0;
                $calc = isset($_POST['prenatal_calcium']) ? 1 : 0;
                $tet  = isset($_POST['prenatal_tetanus']) ? 1 : 0;
                $dew  = isset($_POST['prenatal_deworm']) ? 1 : 0;
                $plan = isset($_POST['svc_birthplan']) ? 1 : 0;
                $dent = isset($_POST['svc_dental']) ? 1 : 0;
                
                $query = "INSERT INTO prenatal_records (patient_id, midwife_id, weight_kg, blood_pressure, fetal_heart_rate, aog_weeks, fundic_height, fetal_presentation, vaginal_bleeding, fever, pallor, edema, medications, findings, next_visit, iron_supp, calcium_supp, tetanus_toxoid, deworming, birth_plan, dental_advice) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $types = "iisssssssssssssiiiiii";
                $params = [
                    $pid, $midwife_id, $_POST['weight'], $_POST['bp'], $_POST['fhr'], $_POST['aog_weeks'], 
                    $_POST['fundic_height'], $_POST['fetal_presentation'], $_POST['vaginal_bleeding'], 
                    $_POST['fever'], $_POST['pallor'], $_POST['edema'], $meds, $findings, $next_visit,
                    $iron, $calc, $tet, $dew, $plan, $dent
                ];
                break;

            case 'labor_watch':
                // ANC02: Labor Watch - Store in Prenatal Records but with specific notes
                $phase = $_POST['labor_phase'] ?? '';
                $cm = $_POST['cervical_dilation'] ?? '';
                $contr = $_POST['contractions'] ?? '';
                $lfhr = $_POST['labor_fhr'] ?? '';
                $parto = isset($_POST['partograph_started']) ? 'Yes' : 'No';
                
                $spec_findings = "[ANC02 Labor Watch] Phase: $phase. Dilation: {$cm}cm. Contractions: $contr. FHR: $lfhr. Partograph: $parto. " . $findings;
                
                // Use default values for standard prenatal columns
                $query = "INSERT INTO prenatal_records (patient_id, midwife_id, checkup_date, medications, findings, next_visit, aog_weeks, blood_pressure, weight_kg) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)";
                // We map to a simplified INSERT if possible, or use the full one with defaults.
                // Re-using the full INSERT from above is safer if table schema allows defaults, but here we construct a specific one.
                // WAIT: The code above uses a specific INSERT string. I should define a new one or reuse.
                // Let's use a specific one for Labor Watch to avoid missing column errors if schema prevents NULL.
                // Current schema likely expects all those columns.
                // I will use the FULL insert but fill specific defaults.
                
                $query = "INSERT INTO prenatal_records (patient_id, midwife_id, checkup_date, medications, findings, next_visit, 
                          weight_kg, blood_pressure, fetal_heart_rate, aog_weeks, 
                          fundic_height, fetal_presentation, vaginal_bleeding, fever, pallor, edema, 
                          iron_supp, calcium_supp, tetanus_toxoid, deworming, birth_plan, dental_advice) 
                          VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                 
                $types = "iissssiiiissssiiiiii"; // 2 ints (pid, mid) + 4 strings + 4 ints (metrics) + 6 strings (risks) + 6 ints (checkboxes) = 22 params?
                // Let's align with the prenatal case above:
                // Types above: "iisssssssssssssiiiiii" (21 chars)
                // Cols: pid(i), mid(i), wt(s), bp(s), fhr(s), aog(s), fund(s), pres(s), vb(s), fev(s), pal(s), ede(s), meds(s), find(s), next(s), iron(i)...(6 ints).
                // Total 2 + 13 strings + 6 ints = 21.
                
                $types = "iisssssssssssssiiiiii";
                $default_metrics = "0";
                $default_risk = "No";
                $default_inc = 0;
                
                $params = [
                    $pid, $midwife_id, 
                    isset($_POST['weight']) ? $_POST['weight'] : '0',  // Weight
                    isset($_POST['bp']) ? $_POST['bp'] : '0/0',        // BP
                    $lfhr,                                             // FHR (from labor watch)
                    '37',                                              // AOG (Assume Term for Labor Watch)
                    '0',                                               // Fundic
                    'Cephalic',                                        // Pres
                    $default_risk, $default_risk, $default_risk, $default_risk, // Risks
                    $meds, 
                    $spec_findings, 
                    $next_visit,
                    $default_inc, $default_inc, $default_inc, $default_inc, $default_inc, $default_inc // Inclusions
                ];
                break;

            case 'ultrasound':
                $query = "INSERT INTO ultrasound_records (patient_id, midwife_id, checkup_date, indication, result_summary, medications, findings, next_visit) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)";
                $types = "iisssss";
                $params = [$pid, $midwife_id, $_POST['indication'], $_POST['result_summary'], $meds, $findings, $next_visit];
                break;

            case 'laboratory':
                $query = "INSERT INTO laboratory_records (patient_id, midwife_id, checkup_date, test_type, lab_status, medications, findings, next_visit) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)";
                $types = "iisssss";
                $params = [$pid, $midwife_id, $_POST['test_type'], $_POST['lab_status'], $meds, $findings, $next_visit];
                break;

            case 'postnatal':
                $delivery_cnt = isset($_POST['delivery_count']) ? (int)$_POST['delivery_count'] : 1;
                if($delivery_cnt > 1) $findings = "[Delivered: $delivery_cnt] " . $findings;
                
                $query = "INSERT INTO postnatal_records (patient_id, midwife_id, checkup_date, blood_pressure, temperature, uterine_involution, lochia, breastfeeding_status, medications, findings, next_visit, vit_a, iron_supp, fp_counseling, perineal_care, breastfeeding_support) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $types = "iissssssssiiiii";
                $params = [
                $pid, 
                    $midwife_id, 
                    $_POST['bp'] ?? '', 
                    $_POST['temperature'] ?? '', 
                    $_POST['uterine_involution'] ?? 'Normal', 
                    $_POST['lochia'] ?? 'Normal', 
                    $_POST['breastfeeding'] ?? 'Exclusive', 
                    $meds, 
                    $findings, 
                    $next_visit,
                    isset($_POST['postnatal_vitA']) ? 1 : 0,
                    isset($_POST['postnatal_iron']) ? 1 : 0,
                    isset($_POST['postnatal_counsel']) ? 1 : 0,
                    isset($_POST['postnatal_perineum']) ? 1 : 0,
                    isset($_POST['postnatal_breast']) ? 1 : 0
                ];
                break;
            
            case 'family_planning':
                $query = "INSERT INTO family_planning_records (patient_id, midwife_id, checkup_date, method_discussed, method_chosen, medications, findings, next_visit) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)";
                $types = "iisssss";
                $params = [$pid, $midwife_id, $_POST['fp_method'], $_POST['fp_chosen'], $meds, $findings, $next_visit];
                break;
            case 'immunization':
                $query = "INSERT INTO immunization_records (patient_id, midwife_id, checkup_date, vaccine_type, dose_number, medications, findings, next_visit) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)";
                $types = "iisssss";
                $params = [$pid, $midwife_id, $_POST['vaccine_type'], $_POST['dose_number'], $meds, $findings, $next_visit];
                break;

            case 'delivery':
                $einc_dry = isset($_POST['einc_dry']) ? 1 : 0;
                $einc_ssc = isset($_POST['einc_ssc']) ? 1 : 0;
                $einc_cord = isset($_POST['einc_cord']) ? 1 : 0;
                $einc_breast = isset($_POST['einc_breast']) ? 1 : 0;
                
                // NEW: Handle Multi-Form Inserts (Newborn & Postnatal) if fields are present
                if(isset($_POST['newborn_weight']) || isset($_POST['bcg']) || isset($_POST['ncp_vitk'])) {
                    // Check for Twins
                    $is_twin = !empty($_POST['newborn_weight_2']) || (isset($_POST['is_twin']));
                    
                    // Baby 1 (Twin A) - Validate required newborn_weight
                    $nb_wt = !empty($_POST['newborn_weight']) ? (int)$_POST['newborn_weight'] : 0;
                    if($nb_wt <= 0) {
                        $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Birth weight is required for Baby A.</div>";
                        break;
                    }
                    
                    $nb_bcg = isset($_POST['bcg']) ? 1 : 0;
                    $nb_hepb = isset($_POST['hepb']) ? 1 : 0;
                    $nb_nbs = isset($_POST['nbs']) ? 1 : 0; 
                    $nb_hearing = isset($_POST['hearing']) ? 1 : 0;
                    $nb_vitk = isset($_POST['ncp_vitk']) ? 1 : 0;
                    $nb_eye = isset($_POST['ncp_eye']) ? 1 : 0;
                    $nb_cord = isset($_POST['cord_care']) ? 1 : 0;
                    
                    $nb_stmt = $conn->prepare("INSERT INTO newborn_records (patient_id, midwife_id, checkup_date, bcg_given, hepb_given, nbs_done, hearing_test, weight_g, medications, findings, next_visit, vit_k, eye_prophylaxis, cord_care) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if($nb_stmt === false) {
                        $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error preparing newborn record: " . $conn->error . "</div>";
                        break;
                    }
                    $nb_findings = $findings . ($is_twin ? " [Baby A]" : ""); // Label Baby A
                    $nb_stmt->bind_param("iiiiiiisssiii", $pid, $midwife_id, $nb_bcg, $nb_hepb, $nb_nbs, $nb_hearing, $nb_wt, $meds, $nb_findings, $next_visit, $nb_vitk, $nb_eye, $nb_cord);
                    if(!$nb_stmt->execute()) {
                        $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error saving Baby A newborn record: " . $nb_stmt->error . "</div>";
                        break;
                    }

                    // Baby 2 (Twin B) - Validate if twin is detected
                    if($is_twin) {
                        $nb2_wt = !empty($_POST['newborn_weight_2']) ? (int)$_POST['newborn_weight_2'] : 0;
                        if($nb2_wt <= 0) {
                            $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Birth weight is required for Baby B (Twin).</div>";
                            break;
                        }
                        
                        $nb2_bcg = isset($_POST['bcg_2']) ? 1 : 0;
                        $nb2_hepb = isset($_POST['hepb_2']) ? 1 : 0;
                        $nb2_nbs = isset($_POST['nbs_2']) ? 1 : 0; 
                        $nb2_hearing = isset($_POST['hearing_2']) ? 1 : 0;
                        $nb2_vitk = isset($_POST['ncp_vitk_2']) ? 1 : 0;
                        $nb2_eye = isset($_POST['ncp_eye_2']) ? 1 : 0;
                        $nb2_cord = isset($_POST['cord_care_2']) ? 1 : 0;
                        
                        $nb_stmt2 = $conn->prepare("INSERT INTO newborn_records (patient_id, midwife_id, checkup_date, bcg_given, hepb_given, nbs_done, hearing_test, weight_g, medications, findings, next_visit, vit_k, eye_prophylaxis, cord_care) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if($nb_stmt2 === false) {
                            $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error preparing Baby B newborn record: " . $conn->error . "</div>";
                            break;
                        }
                        $nb2_findings = $findings . " [Baby B (Twin)]"; // Label Baby B
                        $nb_stmt2->bind_param("iiiiiiisssiii", $pid, $midwife_id, $nb2_bcg, $nb2_hepb, $nb2_nbs, $nb2_hearing, $nb2_wt, $meds, $nb2_findings, $next_visit, $nb2_vitk, $nb2_eye, $nb2_cord);
                        if(!$nb_stmt2->execute()) {
                            $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error saving Baby B newborn record: " . $nb_stmt2->error . "</div>";
                            break;
                        }
                    }
                }

                if(isset($_POST['postnatal_vitA']) || isset($_POST['postnatal_iron'])) {
                     $pn_vitA = isset($_POST['postnatal_vitA']) ? 1 : 0;
                     $pn_iron = isset($_POST['postnatal_iron']) ? 1 : 0;
                     $pn_counsel = isset($_POST['postnatal_counsel']) ? 1 : 0;
                     $pn_peri = isset($_POST['postnatal_perineum']) ? 1 : 0;
                     $pn_bf = isset($_POST['postnatal_breast']) ? 1 : 0;
                     
                     // Use Delivery BP/Temp if Postnatal ones are empty
                     $pn_bp = !empty($_POST['bp']) ? $_POST['bp'] : ($_POST['delivery_bp'] ?? ''); 
                     $pn_temp = !empty($_POST['temperature']) ? $_POST['temperature'] : '';

                     $pn_stmt = $conn->prepare("INSERT INTO postnatal_records (patient_id, midwife_id, checkup_date, blood_pressure, temperature, uterine_involution, lochia, breastfeeding_status, medications, findings, next_visit, vit_a, iron_supp, fp_counseling, perineal_care, breastfeeding_support) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                     if($pn_stmt === false) {
                         $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error preparing postnatal record: " . $conn->error . "</div>";
                         break;
                     }
                     $pn_uterus = $_POST['uterine_involution'] ?? 'Normal';
                     $pn_lochia = $_POST['lochia'] ?? 'Normal';
                     $pn_status = $_POST['breastfeeding'] ?? 'Exclusive';
                     
                     $pn_stmt->bind_param("iissssssssiiiii", $pid, $midwife_id, $pn_bp, $pn_temp, $pn_uterus, $pn_lochia, $pn_status, $meds, $findings, $next_visit, $pn_vitA, $pn_iron, $pn_counsel, $pn_peri, $pn_bf);
                     if(!$pn_stmt->execute()) {
                         $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error saving postnatal record: " . $pn_stmt->error . "</div>";
                         break;
                     }
                }

                // Validate required delivery fields
                if(empty($_POST['delivery_time'])) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Time of Delivery is required.</div>";
                    break;
                }
                if(empty($_POST['sex'])) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Sex of Baby is required.</div>";
                    break;
                }
                if(empty($_POST['baby_weight']) || (int)$_POST['baby_weight'] <= 0) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Baby weight is required.</div>";
                    break;
                }
                if(empty($_POST['baby_length']) || (float)$_POST['baby_length'] <= 0) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Baby length is required.</div>";
                    break;
                }
                if(empty($_POST['apgar1']) || (int)$_POST['apgar1'] < 0) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Apgar (1 min) is required.</div>";
                    break;
                }
                if(empty($_POST['apgar5']) || (int)$_POST['apgar5'] < 0) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Apgar (5 min) is required.</div>";
                    break;
                }
                
                $query = "INSERT INTO delivery_records (patient_id, midwife_id, delivery_date, delivery_time, sex, weight_g, length_cm, apgar_1min, apgar_5min, einc_dry, einc_ssc, einc_cord, einc_breast, medications, findings, next_visit, partograph_used) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $types = "iissidiiiiiiissi";
                $params = [
                    $pid, $midwife_id, $_POST['delivery_time'], $_POST['sex'], 
                    (int)$_POST['baby_weight'], (float)$_POST['baby_length'], 
                    (int)$_POST['apgar1'], (int)$_POST['apgar5'],
                    $einc_dry, $einc_ssc, $einc_cord, $einc_breast,
                    $meds, $findings, $next_visit,
                    isset($_POST['partograph_used']) ? 1 : 0
                ];
                break;

            case 'newborn':
                $is_twin = !empty($_POST['newborn_weight_2']) || (isset($_POST['is_twin']));
                
                // Validate required newborn_weight for Baby 1
                if(empty($_POST['newborn_weight']) || (int)$_POST['newborn_weight'] <= 0) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Birth weight is required for Baby A.</div>";
                    break;
                }
                
                // Validate required newborn_weight for Baby 2 if twin
                if($is_twin && (empty($_POST['newborn_weight_2']) || (int)$_POST['newborn_weight_2'] <= 0)) {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Birth weight is required for Baby B (Twin).</div>";
                    break;
                }
                
                $bcg = isset($_POST['bcg']) ? 1 : 0;
                $hepb = isset($_POST['hepb']) ? 1 : 0;
                $nbs = isset($_POST['nbs']) ? 1 : 0;
                $hearing = isset($_POST['hearing']) ? 1 : 0;
                
                // Baby 1
                $query = "INSERT INTO newborn_records (patient_id, midwife_id, checkup_date, bcg_given, hepb_given, nbs_done, hearing_test, weight_g, medications, findings, next_visit, vit_k, eye_prophylaxis, cord_care) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $types = "iiiiiiisssiii";
                $nw1 = (int)$_POST['newborn_weight'];
                $nf1 = $findings . ($is_twin ? " [Baby A]" : "");
                $params = [
                    $pid, $midwife_id, $bcg, $hepb, $nbs, $hearing, 
                    $nw1, $meds, $nf1, $next_visit,
                    isset($_POST['ncp_vitk']) ? 1 : 0,
                    isset($_POST['ncp_eye']) ? 1 : 0,
                    isset($_POST['cord_care']) ? 1 : 0
                ];

                // Baby 2 (Twin) - Insert Immediately if Twin
                if($is_twin) {
                    $nb2_bcg = isset($_POST['bcg_2']) ? 1 : 0;
                    $nb2_hepb = isset($_POST['hepb_2']) ? 1 : 0;
                    $nb2_nbs = isset($_POST['nbs_2']) ? 1 : 0; 
                    $nb2_hearing = isset($_POST['hearing_2']) ? 1 : 0;
                    $nb2_wt = (int)$_POST['newborn_weight_2'];
                    $nf2 = $findings . " [Baby B (Twin)]";
                    
                    $stmt2 = $conn->prepare("INSERT INTO newborn_records (patient_id, midwife_id, checkup_date, bcg_given, hepb_given, nbs_done, hearing_test, weight_g, medications, findings, next_visit, vit_k, eye_prophylaxis, cord_care) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if($stmt2 === false) {
                        $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error preparing Baby B newborn record: " . $conn->error . "</div>";
                        break;
                    }
                    $nk2 = isset($_POST['ncp_vitk_2']) ? 1 : 0;
                    $ne2 = isset($_POST['ncp_eye_2']) ? 1 : 0;
                    $nc2 = isset($_POST['cord_care_2']) ? 1 : 0;
                    
                    $stmt2->bind_param("iiiiiiisssiii", $pid, $midwife_id, $nb2_bcg, $nb2_hepb, $nb2_nbs, $nb2_hearing, $nb2_wt, $meds, $nf2, $next_visit, $nk2, $ne2, $nc2);
                    if(!$stmt2->execute()) {
                        $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error saving Baby B newborn record: " . $stmt2->error . "</div>";
                        break;
                    }
                }
                break;

            default:
                $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error: Unknown service type.</div>";
                break;
        }

        if (!empty($query)) {
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Database Error: " . $conn->error . "</div>";
            } else {
                $stmt->bind_param($types, ...$params);
                if($stmt->execute()) {
                    $conn->query("UPDATE appointment SET status='Completed' WHERE patient_id='$pid' AND appointment_date=CURDATE() AND status IN ('Confirmed', 'Arrived')");
                    // Get patient name for the redirect
                    $pname_q = $conn->query("SELECT u.name FROM patient p JOIN users u ON p.user_id=u.user_id WHERE p.patient_id='$pid'");
                    $pname = ($pname_q->num_rows > 0) ? $pname_q->fetch_assoc()['name'] : 'Patient';

                    // Prepare Quantity for Redirect (Default 1, unless defined above)
                    $redirect_qty = (isset($delivery_cnt) && $delivery_cnt > 1) ? $delivery_cnt : 1;

                    $_SESSION['swal_success'] = [
                        'title' => 'Success!',
                        'text'  => ucfirst($service_type) . " record saved. Please record the charges now.",
                        'icon'  => 'success',
                        'timer' => 4000
                    ];
                    
                    $extra_msg = "";
                    $twin_param = "";
                    if(isset($is_twin) && $is_twin) {
                        $_SESSION['swal_success']['text'] = "Twins detected! Please charge for Mother (MCP) AND Baby 2 (NCP).";
                        $extra_msg = "<div class='mt-2 text-xs font-bold text-pink-700 bg-pink-100 p-2 rounded transform rotate-1 border border-pink-200 shadow-sm'>👶 TWIN DETECTED: Don't forget to add a separate charge for 'NCP - Newborn Care Package'!</div>";
                        $twin_param = "&twin=1";
                    }

                    $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-4 rounded-2xl shadow-sm border border-green-100 mb-6 flex items-center gap-3'><i class='fas fa-check-circle text-lg'></i> <div><b class='block'>Success!</b> <span class='text-sm'>" . ucfirst($service_type) . " record saved. Please record the charges now.</span>$extra_msg</div></div>";
                    
                    // Redirect to Charges tab with patient and service pre-selected
                    header("Location: midwife_dashboard.php?action=charge&pid=$pid&pname=" . urlencode($pname) . "&svc=" . urlencode($booked_svc) . "&qty=$redirect_qty$twin_param#charges");
                    exit;
                } else {
                    $msg = "<div class='bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 mb-4'>Error saving record: " . $stmt->error . "</div>";
                }
            }
        }
    }
}

// --- LOGIC 2: RECORD SERVICE CHARGE ---
if (isset($_POST['record_charge'])) {
    try {
        $patient_id = $_POST['charge_patient_id'];
        $service_id = $_POST['service_id'];
        $quantity = $_POST['quantity'];
        $notes = $_POST['charge_notes'];
        $is_philhealth = isset($_POST['is_philhealth']) ? 1 : 0;
        
        // Fetch unit price
        // Fetch unit price and name
        $price_stmt = $conn->prepare("SELECT price, service_name FROM service_pricing WHERE service_id = ?");
        $price_stmt->bind_param("i", $service_id);
        
        if(!$price_stmt->execute()) throw new Exception("DB Error");
        $res = $price_stmt->get_result();
        
        if ($res->num_rows == 0) throw new Exception("Invalid service selected.");
        
        $svc_data = $res->fetch_assoc();
        $unit_price = $svc_data['price'];
        $svc_name = $svc_data['service_name'] ?? '';

        // PRICE OVERRIDE MAP (Back-end Enforcement)
        $price_overrides = [
            'MCP01' => 12500.00,
            'NSD01' => 11000.00,
            'NCP'   => 5000.00,
            'ANC01' => 1500.00,
            'ANC02' => 2000.00
        ];

        // Detect Code from Name
        $code = '';
        if(preg_match('/^([A-Z0-9]+)\s*-/', $svc_name, $m)) $code = $m[1];
        else {
             foreach(array_keys($price_overrides) as $k) {
                 if(strpos($svc_name, $k) !== false) { $code = $k; break; }
             }
        }

        if(isset($price_overrides[$code])) {
            $unit_price = $price_overrides[$code];
        }
        $total_amount = $unit_price * $quantity;

        // Deduct Downpayment if exists - SYNCED LOGIC
        $dp_deducted = 0;
        // Search for any valid paid downpayment for this patient
        $check_dp = $conn->query("SELECT appointment_id, payment_mode FROM appointment WHERE patient_id='$patient_id' AND down_payment_status='Paid' AND status NOT IN ('Cancelled', 'Rejected') ORDER BY appointment_date DESC, appointment_id DESC LIMIT 1");
        
        if ($check_dp->num_rows > 0) {
            // Check if DP was already applied to another charge TODAY (using the record's target date)
            $today = date('Y-m-d');
            $dp_used_check = $conn->query("SELECT charge_id FROM pending_charges WHERE patient_id='$patient_id' AND (notes LIKE '%Downpayment Deducted%' OR notes LIKE '%Fully Paid Online%') AND DATE(created_at)='$today'");
            
            if ($dp_used_check->num_rows == 0) {
                $dp_row = $check_dp->fetch_assoc();
                $mode = $dp_row['payment_mode'] ?? 'DownPayment';
                
                if (strcasecmp($mode, 'Full') === 0) {
                    $notes .= " [Fully Paid Online]";
                    $total_amount = 0;
                    $dp_deducted = "Full";
                } else {
                    $deduction = $total_amount * 0.5;
                    $total_amount = max(0, $total_amount - $deduction);
                    $dp_deducted = $deduction;
                    $notes .= " [-" . number_format($deduction, 2) . " Downpayment Deducted]";
                }
            }
        }

        // Apply PhilHealth Discount (No Balance Billing)
        if ($is_philhealth) {
            $total_amount = 0.00;
            $notes .= " [PhilHealth/No Balance Billing Applied]";
        }
        
        // Insert pending charge
        $insert_stmt = $conn->prepare("INSERT INTO pending_charges (patient_id, service_id, quantity, unit_price, total_amount, recorded_by, notes, status, is_philhealth) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
        $insert_stmt->bind_param("iiiddisi", $patient_id, $service_id, $quantity, $unit_price, $total_amount, $midwife_id, $notes, $is_philhealth);
        
        if ($insert_stmt->execute()) {
            $msg_text = "Service charge for patient #" . $patient_id . " is now pending clerk approval.";
            if($dp_deducted > 0) $msg_text .= " (DP Deducted)";
            
            // CHECK FOR TWIN SEQUENCE
            $is_twin_seq = isset($_POST['is_twin_sequence']) && $_POST['is_twin_sequence'] == '1';
            
            if ($is_twin_seq) {
                 // Get Patient Name for Redirect
                 $pres = $conn->query("SELECT u.name FROM patient p JOIN users u ON p.user_id=u.user_id WHERE p.patient_id='$patient_id'");
                 $pname = ($pres && $pres->num_rows > 0) ? $pres->fetch_assoc()['name'] : 'Patient';
                 
                 $_SESSION['swal_success'] = [
                    'title' => 'Step 1 Complete',
                    'text'  => "Mother Charged. Now please record the charge for Baby 2 (NCP).",
                    'icon'  => 'info',
                    'timer' => 5000
                 ];
                 // Redirect back to charge form for NCP
                 header("Location: midwife_dashboard.php?action=charge&pid=$patient_id&pname=" . urlencode($pname) . "&svc=NCP&qty=1&nodp=1#charges");
                 exit;
            }

            // SweetAlert2 Success (Standard)
            $_SESSION['swal_success'] = [
                'title' => 'Charge Recorded!',
                'text'  => $msg_text,
                'icon'  => 'success'
            ];
            
            $_SESSION['msg'] = "<div class='bg-green-50 text-green-700 p-4 rounded-2xl shadow-sm border border-green-100 mb-6 flex items-center gap-3'><i class='fas fa-check-circle text-lg'></i> <div><b class='block'>Charge Recorded!</b> <span class='text-sm'>$msg_text</span></div></div>";
            header("Location: midwife_dashboard.php#charges");
            exit;
        } else {
            throw new Exception("Failed to record charge.");
        }
        
    } catch (Exception $e) {
        // SweetAlert2 Error
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text'  => $e->getMessage(),
            'icon'  => 'error'
        ];
        $msg = "<div class='bg-red-50 text-red-700 p-4 rounded-lg border border-red-200 mb-4'><i class='fas fa-exclamation-circle me-2'></i>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- LOGIC 2: FETCH HISTORY ---
$history_mode = false;
$patient_history = [];
$patient_name = "";
$counts = [];

if (isset($_GET['view_history'])) {
    $history_mode = true;
    $target_pid = $_GET['view_history'];
    
    // Get Patient Name
    $p_stmt = $conn->prepare("SELECT u.name FROM patient p JOIN users u ON p.user_id = u.user_id WHERE p.patient_id = ?");
    $p_stmt->bind_param("i", $target_pid);
    $p_stmt->execute();
    $res = $p_stmt->get_result();
    $patient_name = ($res->num_rows > 0) ? $res->fetch_assoc()['name'] : "Unknown";

    // Get Counts for each service type to optionally show badges (UI enhancement)
    // We will query each table individually just to check existence if needed, or just let the view handle it.
    // For now, we will just set the target_pid and let the VIEW section execute the queries.
}

// --- PREPARE DATA FOR JS FILTER ---
$pat_array = [];
$pts = $conn->query("SELECT p.patient_id, u.name FROM patient p JOIN users u ON p.user_id=u.user_id ORDER BY u.name ASC");
while($p = $pts->fetch_assoc()) { $pat_array[] = $p; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midwife Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#ec4899", "primary-light": "#fce7f3", surface: "#ffffff",
                        background: "#fdf2f8", text: "#831843", muted: "#be185d"
                    },
                    fontFamily: { sans: ["Manrope", "sans-serif"] }
                }
            }
        }
    </script>
    <style>
        .nav-item.active { background-color: #fce7f3; color: #db2777; border-right: 3px solid #db2777; font-weight: 700; }
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #ec4899; border-radius: 3px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #be185d; }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($swal_success): ?>
            Swal.fire({
                title: "<?php echo addslashes($swal_success['title']); ?>",
                text: "<?php echo addslashes($swal_success['text']); ?>",
                icon: "success",
                timer: <?php echo isset($swal_success['timer']) ? $swal_success['timer'] : 3000; ?>,
                showConfirmButton: false,
                backdrop: `rgba(0,0,0,0.4) left top no-repeat`
            });
            <?php endif; ?>

            <?php if($swal_error): ?>
            Swal.fire({
                title: "<?php echo addslashes($swal_error['title']); ?>",
                text: "<?php echo addslashes($swal_error['text']); ?>",
                icon: "error",
                confirmButtonColor: "#ec4899"
            });
            <?php endif; ?>
        });
    </script>
</head>
<body class="bg-background text-text font-sans h-screen flex overflow-hidden">
    
    <?php
    // --- COUNTS FOR BADGES ---
    // Count 'Confirmed' appointments for TODAY (Queue)
    $cnt_queue = $conn->query("SELECT COUNT(*) FROM appointment WHERE status='Confirmed' AND appointment_date=CURDATE()")->fetch_row()[0];
    // Count 'Completed' appointments for TODAY (Likely need charging)
    $cnt_to_charge = $conn->query("SELECT COUNT(*) FROM appointment WHERE status='Completed' AND appointment_date=CURDATE()")->fetch_row()[0];
    ?>

    <aside class="w-72 bg-surface h-[95vh] my-auto ml-4 flex flex-col shadow-xl z-20 rounded-3xl border border-pink-100 hidden md:flex">
        <div class="p-8 flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary shadow-sm">
                <i class="fas fa-baby-carriage text-xl"></i>
            </div>
            <div>
                <h1 class="font-extrabold text-xl text-primary tracking-tight">MCMIS Clinic</h1>
                <p class="text-xs text-muted font-semibold uppercase tracking-wider">Midwife Portal</p>
            </div>
        </div>

        <nav class="flex-1 px-6 space-y-2">
            <button onclick="window.location.href='midwife_dashboard.php'" class="nav-item <?php echo !$history_mode ? 'active' : ''; ?> w-full flex items-center gap-4 px-4 py-4 rounded-xl text-gray-500 font-medium transition-all hover:bg-pink-50 text-sm">
                <i class="fas fa-th-large text-lg"></i> Dashboard
            </button>
            <button onclick="switchTab('service', this)" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-gray-500 font-medium transition-all hover:bg-pink-50 text-sm">
                 <div class="relative">
                    <i class="fas fa-stethoscope text-lg"></i>
                    <?php if($cnt_queue > 0): ?><span class="absolute -top-1 -right-2 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span><?php endif; ?>
                </div>
                <span>Service Delivery</span>
            </button>
            <button onclick="switchTab('charges', this)" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-gray-500 font-medium transition-all hover:bg-pink-50 text-sm">
                <div class="relative">
                    <i class="fas fa-file-invoice-dollar text-lg"></i>
                    <?php if($cnt_to_charge > 0): ?><span class="absolute -top-1 -right-2 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span><?php endif; ?>
                </div>
                <span>Record Charges</span>
            </button>
            <button onclick="switchTab('records', this)" class="nav-item <?php echo $history_mode ? 'active' : ''; ?> w-full flex items-center gap-4 px-4 py-4 rounded-xl text-gray-500 font-medium transition-all hover:bg-pink-50 text-sm">
                <i class="fas fa-folder-open text-lg"></i> Manage Records
            </button>
            <a href="profile.php" class="nav-item w-full flex items-center gap-4 px-4 py-4 rounded-xl text-gray-500 font-medium transition-all hover:bg-pink-50 text-sm">
                <i class="fas fa-user-circle text-lg"></i> My Profile
            </a>
        </nav>

        <div class="p-6 mt-auto">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-50 rounded-xl transition-colors text-sm font-bold">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 h-full overflow-y-auto p-4 md:p-8">

        <div class="flex justify-between items-center mb-8 pl-2">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Maternity Care Unit</h2>
                <p class="text-sm text-gray-500">Patient Monitoring & Records</p>
            </div>
            <div class="flex items-center gap-3 bg-white px-5 py-2.5 rounded-full shadow-sm border border-pink-100">
                <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs uppercase">
                    <?php echo substr($_SESSION['name'], 0, 1); ?>
                </div>
                <span class="text-sm font-bold text-gray-700"><?php echo $_SESSION['name']; ?></span>
            </div>
        </div>

        <?php if ($history_mode): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-pink-100 p-6 animate-fade-in">
                <div class="flex items-center justify-between mb-6 border-b border-pink-50 pb-4">
                    <div>
                        <h3 class="font-extrabold text-xl text-gray-800">Medical History: <?php echo $patient_name; ?></h3>
                        <p class="text-sm text-gray-500">Comprehensive medical records.</p>
                    </div>
                    <a href="midwife_dashboard.php" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-bold hover:bg-gray-200">
                        <i class="fas fa-arrow-left mr-2"></i> Back to List
                    </a>
                </div>

                <!-- TABS -->
                <div class="mb-6 border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500" id="histTabs">
                        <li class="mr-2"><button onclick="showHistTab('prenatal')" class="inline-block p-4 text-primary border-b-2 border-primary rounded-t-lg active group hist-tab-btn" id="btn-prenatal">Prenatal</button></li>
                        <!-- <li class="mr-2"><button onclick="showHistTab('ultrasound')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-ultrasound">Ultrasound</button></li> -->
                        <!-- <li class="mr-2"><button onclick="showHistTab('lab')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-lab">Lab Results</button></li> -->
                        <li class="mr-2"><button onclick="showHistTab('postnatal')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-postnatal">Postnatal</button></li>
                        <li class="mr-2"><button onclick="showHistTab('delivery')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-delivery">Delivery</button></li>
                        <li class="mr-2"><button onclick="showHistTab('newborn')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-newborn">Newborn</button></li>
                        <!-- <li class="mr-2"><button onclick="showHistTab('fp')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-fp">Family Planning</button></li> -->
                        <!-- <li class="mr-2"><button onclick="showHistTab('immuno')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-immuno">Immunization</button></li> -->
                        <!-- <li class="mr-2"><button onclick="showHistTab('walkin')" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg hist-tab-btn" id="btn-walkin">Consultation</button></li> -->
                    </ul>
                </div>

                <!-- TAB CONTENTS -->
                <div id="hist-content-prenatal" class="hist-tab-content">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-pink-50 text-pink-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date / AOG</th><th class="p-4">Vitals & Presentation</th><th class="p-4">Inclusions</th><th class="p-4">Risk Signs (Y/N)</th><th class="p-4">Findings & Meds</th><th class="p-4 rounded-r-xl">Next Visit</th></tr></thead>
                            <tbody class="divide-y divide-pink-50">
                                <?php
                                $h_stmt = $conn->query("SELECT pr.*, u.name AS midwife_name FROM prenatal_records pr LEFT JOIN users u ON pr.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY checkup_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                    $risks = [];
                                    if(($h['vaginal_bleeding']??'No')=='Yes') $risks[]='Bleeding';
                                    if(($h['fever']??'No')=='Yes') $risks[]='Fever'; 
                                    if(($h['pallor']??'No')=='Yes') $risks[]='Pallor';
                                    if(($h['edema']??'No')=='Yes') $risks[]='Edema';
                                ?>
                                <tr class="hover:bg-pink-50/50 transition-colors">
                                    <td class="p-4 align-top">
                                        <div class="font-bold text-primary"><?php echo date('M d, Y', strtotime($h['checkup_date'])); ?></div>
                                        <div class="text-xs text-gray-500 mt-1">AOG: <?php echo !empty($h['aog_weeks']) ? $h['aog_weeks'].' wks' : '-'; ?></div>
                                        <?php 
                                            // Detect ANC Type
                                            $is_anc02 = strpos(($h['findings']??''), '[ANC02') !== false;
                                            $lbl = $is_anc02 ? 'ANC02 / Labor Watch' : 'ANC01 / Prenatal';
                                            $cls = $is_anc02 ? 'bg-purple-100 text-purple-700' : 'bg-pink-100 text-pink-700';
                                        ?>
                                        <span class="inline-block mt-2 px-2 py-1 rounded text-[10px] font-bold uppercase <?php echo $cls; ?>">
                                            <?php echo $lbl; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 align-top text-xs">
                                        <?php if($is_anc02): 
                                            // Extract ANC02 Metrics (Support both Pipe | and Period . separators)
                                            $raw = $h['findings'] ?? '';
                                            preg_match('/Phase: (.*?)( \|| \.| \n|$)/', $raw, $m_ph);
                                            preg_match('/Dilation: (.*?)( \|| \.| \n|$)/', $raw, $m_di);
                                            preg_match('/Contractions: (.*?)( \|| \.| \n|$)/', $raw, $m_co);
                                            preg_match('/FHR: (.*?)( \|| \.| \n|$)/', $raw, $m_fh);
                                        ?>
                                        <div class="space-y-1">
                                            <?php if(!empty($m_ph[1])): ?><div class="text-purple-700 font-bold"><?php echo $m_ph[1]; ?></div><?php endif; ?>
                                            <?php if(!empty($m_di[1])): ?><div>Dilation: <span class="font-bold text-gray-800"><?php echo $m_di[1]; ?></span></div><?php endif; ?>
                                            <?php if(!empty($m_co[1])): ?><div>Contr: <span class="font-bold text-gray-800"><?php echo $m_co[1]; ?></span></div><?php endif; ?>
                                            <?php if(!empty($m_fh[1])): ?><div>FHR: <span class="font-bold text-gray-800"><?php echo $m_fh[1]; ?></span></div><?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                            <div>BP: <span class="font-bold text-gray-700"><?php echo $h['blood_pressure']; ?></span></div>
                                            <div>Wt: <span class="font-bold text-gray-700"><?php echo $h['weight_kg']; ?>kg</span></div>
                                            <div>FHR: <span class="font-bold text-gray-700"><?php echo $h['fetal_heart_rate']; ?></span></div>
                                            <div>Fundic: <span class="font-bold text-gray-700"><?php echo $h['fundic_height']; ?></span></div>
                                            <div class="col-span-2 pt-1 border-t border-gray-100 mt-1">Pres: <span class="font-bold text-gray-800"><?php echo $h['fetal_presentation']; ?></span></div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 align-top">
                                        <?php 
                                            $inc = [];
                                            if($h['iron_supp']??0) $inc[]='Iron'; 
                                            if($h['calcium_supp']??0) $inc[]='Calc';
                                            if($h['tetanus_toxoid']??0) $inc[]='TT';
                                            if($h['deworming']??0) $inc[]='Deworm';
                                            if($h['birth_plan']??0) $inc[]='Plan';
                                            if($h['dental_advice']??0) $inc[]='Dental';
                                            echo !empty($inc) ? '<div class="flex flex-wrap gap-1">'.implode(' ', array_map(function($i){return '<span class="px-1.5 py-0.5 rounded bg-teal-100 text-teal-800 text-[10px] font-bold">'.$i.'</span>';}, $inc)).'</div>' : '-'; 
                                        ?>
                                    </td>
                                    <td class="p-4 align-top"><?php echo !empty($risks) ? '<span class="inline-block px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs font-bold">'.implode(', ', $risks).'</span>' : '<span class="text-green-600 text-xs font-bold">Normal</span>'; ?></td>
                                    <td class="p-4 align-top"><div class="mb-1"><span class="font-bold text-gray-400 text-xs uppercase">RX:</span> <span class="text-gray-400 text-xs"><?php echo $h['medications']; ?></span></div><div class="text-gray-600 italic">"<?php echo $h['findings']; ?>"</div></td>
                                    <td class="p-4 align-top"><div class="font-bold text-gray-700"><?php echo $h['next_visit'] ? date('M d, Y', strtotime($h['next_visit'])) : '-'; ?></div><div class="text-xs text-gray-400">Midwife: <?php echo $h['midwife_name']; ?></div></td>
                                </tr>
                                <?php endwhile; else: echo '<tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No prenatal records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="hist-content-ultrasound" class="hist-tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-blue-50 text-blue-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date</th><th class="p-4">Indication</th><th class="p-4">Result Summary</th><th class="p-4 rounded-r-xl">Findings</th></tr></thead>
                            <tbody class="divide-y divide-blue-50">
                                <?php
                                $h_stmt = $conn->query("SELECT ur.*, u.name AS midwife_name FROM ultrasound_records ur LEFT JOIN users u ON ur.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY checkup_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-blue-50/50 transition-colors">
                                    <td class="p-4 align-top font-bold text-primary"><?php echo date('M d, Y', strtotime($h['checkup_date'])); ?></td>
                                    <td class="p-4 align-top font-bold"><?php echo $h['indication']; ?></td>
                                    <td class="p-4 align-top"><?php echo $h['result_summary']; ?></td>
                                    <td class="p-4 align-top">
                                        <div class="italic text-gray-600 mb-1">"<?php echo $h['findings']; ?>"</div>
                                        <div class="text-[10px] font-bold text-blue-400">Next: <?php echo $h['next_visit'] ? date('M d, Y', strtotime($h['next_visit'])) : '-'; ?></div>
                                    </td>
                                </tr>
                                <?php endwhile; else: echo '<tr><td colspan="4" class="p-8 text-center text-gray-400 italic">No ultrasound records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                 <div id="hist-content-lab" class="hist-tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-purple-50 text-purple-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date</th><th class="p-4">Test Type</th><th class="p-4">Status</th><th class="p-4 rounded-r-xl">Findings</th></tr></thead>
                            <tbody class="divide-y divide-purple-50">
                                <?php
                                $h_stmt = $conn->query("SELECT lr.*, u.name AS midwife_name FROM laboratory_records lr LEFT JOIN users u ON lr.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY checkup_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-purple-50/50 transition-colors">
                                    <td class="p-4 align-top font-bold text-primary"><?php echo date('M d, Y', strtotime($h['checkup_date'])); ?></td>
                                    <td class="p-4 align-top font-bold"><?php echo $h['test_type']; ?></td>
                                    <td class="p-4 align-top"><span class="px-2 py-1 bg-gray-100 rounded text-xs"><?php echo $h['lab_status']; ?></span></td>
                                    <td class="p-4 align-top">
                                        <div class="italic text-gray-600 mb-1">"<?php echo $h['findings']; ?>"</div>
                                        <div class="text-[10px] font-bold text-purple-400">Next: <?php echo $h['next_visit'] ? date('M d, Y', strtotime($h['next_visit'])) : '-'; ?></div>
                                    </td>
                                </tr>
                                <?php endwhile; else: echo '<tr><td colspan="4" class="p-8 text-center text-gray-400 italic">No lab records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="hist-content-postnatal" class="hist-tab-content hidden">
                    <div class="overflow-x-auto">
                         <table class="w-full text-left text-sm">
                            <thead class="bg-green-50 text-green-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date</th><th class="p-4">Vitals</th><th class="p-4">Inclusions</th><th class="p-4">Uterus / Lochia</th><th class="p-4">Breastfeeding</th><th class="p-4 rounded-r-xl">Findings</th></tr></thead>
                            <tbody class="divide-y divide-green-50">
                                <?php
                                $h_stmt = $conn->query("SELECT por.*, u.name AS midwife_name FROM postnatal_records por LEFT JOIN users u ON por.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY checkup_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-green-50/50 transition-colors">
                                    <td class="p-4 align-top font-bold text-primary"><?php echo date('M d, Y', strtotime($h['checkup_date'])); ?></td>
                                    <td class="p-4 align-top text-xs">BP: <?php echo $h['blood_pressure']; ?><br>Temp: <?php echo $h['temperature']; ?></td>
                                    <td class="p-4 align-top">
                                        <?php 
                                            $inc = [];
                                            if($h['vit_a']??0) $inc[]='Vit A'; 
                                            if($h['iron_supp']??0) $inc[]='Iron';
                                            if($h['fp_counseling']??0) $inc[]='FP';
                                            if($h['perineal_care']??0) $inc[]='Perineum';
                                            if($h['breastfeeding_support']??0) $inc[]='BF Supp';
                                            echo !empty($inc) ? '<div class="flex flex-wrap gap-1">'.implode(' ', array_map(function($i){return '<span class="px-1.5 py-0.5 rounded bg-green-100 text-green-800 text-[10px] font-bold">'.$i.'</span>';}, $inc)).'</div>' : '-'; 
                                        ?>
                                    </td>
                                    <td class="p-4 align-top">
                                        <?php 
                                            $inc = [];
                                            if($h['vit_a']??0) $inc[]='Vit A'; 
                                            if($h['iron_supp']??0) $inc[]='Iron';
                                            if($h['fp_counseling']??0) $inc[]='FP';
                                            if($h['perineal_care']??0) $inc[]='Perineum';
                                            if($h['breastfeeding_support']??0) $inc[]='BF Supp';
                                            echo !empty($inc) ? '<div class="flex flex-wrap gap-1">'.implode(' ', array_map(function($i){return '<span class="px-1.5 py-0.5 rounded bg-green-100 text-green-800 text-[10px] font-bold">'.$i.'</span>';}, $inc)).'</div>' : '-'; 
                                        ?>
                                    </td>
                                    <td class="p-4 align-top text-xs"><?php echo $h['uterine_involution']; ?><br><span class="text-gray-500"><?php echo $h['lochia']; ?></span></td>
                                    <td class="p-4 align-top text-xs"><?php echo $h['breastfeeding_status']; ?></td>
                                    <td class="p-4 align-top">
                                        <div class="italic text-gray-600 mb-1">"<?php echo $h['findings']; ?>"</div>
                                        <div class="text-[10px] font-bold text-green-500">Next: <?php echo $h['next_visit'] ? date('M d, Y', strtotime($h['next_visit'])) : '-'; ?></div>
                                    </td>
                                <?php endwhile; else: echo '<tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No postnatal records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="hist-content-delivery" class="hist-tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-pink-50 text-pink-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date / Time</th><th class="p-4">Baby Details</th><th class="p-4">Apgar</th><th class="p-4">EINC Protocols</th><th class="p-4 rounded-r-xl">Findings</th></tr></thead>
                            <tbody class="divide-y divide-pink-50">
                                <?php
                                $h_stmt = $conn->query("SELECT dr.*, u.name AS midwife_name FROM delivery_records dr LEFT JOIN users u ON dr.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY delivery_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                    $einc = [];
                                    if(isset($h['einc_dry']) && $h['einc_dry']) $einc[] = 'Drying';
                                    if(isset($h['einc_ssc']) && $h['einc_ssc']) $einc[] = 'Skin-to-Skin';
                                    if(isset($h['einc_cord']) && $h['einc_cord']) $einc[] = 'Cord Care';
                                    if(isset($h['einc_breast']) && $h['einc_breast']) $einc[] = 'Breastfeeding';
                                ?>
                                <tr class="hover:bg-pink-50/50 transition-colors">
                                    <td class="p-4 align-top"><div class="font-bold text-primary"><?php echo date('M d, Y', strtotime($h['delivery_date'])); ?></div><div class="text-xs text-gray-500 mt-1"><?php echo date('h:i A', strtotime($h['delivery_time'])); ?></div></td>
                                    <td class="p-4 align-top text-xs"><span class="font-bold"><?php echo $h['sex']; ?></span><br>Wt: <?php echo $h['weight_g']??0; ?>g | L: <?php echo $h['length_cm']??0; ?>cm</td>
                                    <td class="p-4 align-top text-xs">1m: <span class="font-bold"><?php echo $h['apgar_1min']??0; ?></span><br>5m: <span class="font-bold"><?php echo $h['apgar_5min']??0; ?></span></td>
                                    <td class="p-4 align-top">
                                         <?php if(!empty($einc)): ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach($einc as $i): ?><span class="px-1.5 py-0.5 bg-pink-100 text-pink-700 rounded text-[9px] font-bold"><?php echo $i; ?></span><?php endforeach; ?>
                                            </div>
                                        <?php else: echo '<span class="text-gray-300 text-xs">-</span>'; endif; ?>
                                    </td>
                                    <td class="p-4 align-top"><div class="italic text-gray-600 mb-1">"<?php echo $h['findings']; ?>"</div><div class="text-[10px] font-bold text-pink-400">Midwife: <?php echo $h['midwife_name']??'---'; ?></div></td>
                                </tr>
                                <?php endwhile; else: echo '<tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No delivery records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="hist-content-newborn" class="hist-tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-blue-50 text-blue-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date</th><th class="p-4">Growth</th><th class="p-4">Care Provided</th><th class="p-4 rounded-r-xl">Findings</th></tr></thead>
                            <tbody class="divide-y divide-blue-50">
                                <?php
                                $h_stmt = $conn->query("SELECT nr.*, u.name AS midwife_name FROM newborn_records nr LEFT JOIN users u ON nr.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY checkup_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                    $done = [];
                                    if(($h['bcg_given']??0)) $done[] = 'BCG';
                                    if(($h['hepb_given']??0)) $done[] = 'HepB';
                                    if(($h['nbs_done']??0)) $done[] = 'NBS';
                                    if(($h['hearing_test']??0)) $done[] = 'Hearing';
                                    if(isset($h['vit_k']) && $h['vit_k']) $done[] = 'Vit K';
                                    if(isset($h['eye_prophylaxis']) && $h['eye_prophylaxis']) $done[] = 'Eye P.';
                                    if(isset($h['cord_care']) && $h['cord_care']) $done[] = 'Cord';
                                ?>
                                <tr class="hover:bg-blue-50/50 transition-colors">
                                    <td class="p-4 align-top font-bold text-primary"><?php echo date('M d, Y', strtotime($h['checkup_date'])); ?></td>
                                    <td class="p-4 align-top text-xs">Weight: <?php echo $h['weight_g']??0; ?>g</td>
                                    <td class="p-4 align-top"><?php echo !empty($done) ? '<div class="flex flex-wrap gap-1">'.implode(' ', array_map(fn($m)=>'<span class="px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded text-[9px] font-bold">'.$m.'</span>', $done)).'</div>' : '<span class="text-gray-400 text-xs">-</span>'; ?></td>
                                    <td class="p-4 align-top"><div class="italic text-gray-600 mb-1">"<?php echo $h['findings']; ?>"</div><div class="text-[10px] font-bold text-blue-400">Midwife: <?php echo $h['midwife_name']??'---'; ?></div></td>
                                </tr>
                                <?php endwhile; else: echo '<tr><td colspan="4" class="p-8 text-center text-gray-400 italic">No newborn records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="hist-content-fp" class="hist-tab-content hidden">
                    <div class="overflow-x-auto">
                         <table class="w-full text-left text-sm">
                            <thead class="bg-yellow-50 text-yellow-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date</th><th class="p-4">Method Discussed</th><th class="p-4">Method Chosen</th><th class="p-4 rounded-r-xl">Findings</th></tr></thead>
                            <tbody class="divide-y divide-yellow-50">
                                <?php
                                $h_stmt = $conn->query("SELECT fpr.*, u.name AS midwife_name FROM family_planning_records fpr LEFT JOIN users u ON fpr.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY checkup_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-yellow-50/50 transition-colors">
                                    <td class="p-4 align-top font-bold text-primary"><?php echo date('M d, Y', strtotime($h['checkup_date'])); ?></td>
                                    <td class="p-4 align-top"><?php echo $h['method_discussed']; ?></td>
                                    <td class="p-4 align-top font-bold text-green-600"><?php echo $h['method_chosen']; ?></td>
                                    <td class="p-4 align-top">
                                        <div class="italic text-gray-600 mb-1">"<?php echo $h['findings']; ?>"</div>
                                        <div class="text-[10px] font-bold text-yellow-600">Next: <?php echo $h['next_visit'] ? date('M d, Y', strtotime($h['next_visit'])) : '-'; ?></div>
                                    </td>
                                </tr>
                                <?php endwhile; else: echo '<tr><td colspan="4" class="p-8 text-center text-gray-400 italic">No family planning records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="hist-content-immuno" class="hist-tab-content hidden">
                    <div class="overflow-x-auto">
                         <table class="w-full text-left text-sm">
                            <thead class="bg-indigo-50 text-indigo-900 font-bold uppercase text-xs"><tr><th class="p-4 rounded-l-xl">Date</th><th class="p-4">Vaccine</th><th class="p-4">Dose</th><th class="p-4 rounded-r-xl">Findings</th></tr></thead>
                            <tbody class="divide-y divide-indigo-50">
                                <?php
                                $h_stmt = $conn->query("SELECT ir.*, u.name AS midwife_name FROM immunization_records ir LEFT JOIN users u ON ir.midwife_id = u.user_id WHERE patient_id = '$target_pid' ORDER BY checkup_date DESC");
                                if ($h_stmt->num_rows > 0): while ($h = $h_stmt->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-indigo-50/50 transition-colors">
                                    <td class="p-4 align-top font-bold text-primary"><?php echo date('M d, Y', strtotime($h['checkup_date'])); ?></td>
                                    <td class="p-4 align-top font-bold"><?php echo $h['vaccine_type']; ?></td>
                                    <td class="p-4 align-top"><span class="px-2 py-1 bg-indigo-100 rounded text-xs"><?php echo $h['dose_number']; ?></span></td>
                                    <td class="p-4 align-top">
                                        <div class="italic text-gray-600 mb-1">"<?php echo $h['findings']; ?>"</div>
                                        <div class="text-[10px] font-bold text-indigo-400">Next: <?php echo $h['next_visit'] ? date('M d, Y', strtotime($h['next_visit'])) : '-'; ?></div>
                                    </td>
                                </tr>
                                <?php endwhile; else: echo '<tr><td colspan="4" class="p-8 text-center text-gray-400 italic">No immunization records found.</td></tr>'; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Consultation records removed -->
            </div>

            <script>
            function showHistTab(tabName) {
                // Hide all contents
                document.querySelectorAll('.hist-tab-content').forEach(el => el.classList.add('hidden'));
                // Show specific content
                document.getElementById('hist-content-' + tabName).classList.remove('hidden');

                // Reset all button styles
                document.querySelectorAll('.hist-tab-btn').forEach(btn => {
                    btn.classList.remove('text-primary', 'border-b-2', 'border-primary');
                    btn.classList.add('hover:text-gray-600', 'hover:border-gray-300', 'border-transparent');
                });

                // Set active button style
                const btn = document.getElementById('btn-' + tabName);
                if(btn) {
                    btn.classList.add('text-primary', 'border-b-2', 'border-primary');
                    btn.classList.remove('text-gray-600', 'border-transparent');
                }
            }
            </script>

        <?php else: ?>

            <div id="dashboard" class="tab-content active">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-pink-100 flex items-center gap-4">
                        <div class="w-14 h-14 bg-pink-50 text-pink-500 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-calendar-check"></i></div>
                        <div><p class="text-xs font-bold text-gray-400 uppercase">Patients Today</p><h3 class="text-3xl font-extrabold text-gray-800"><?php echo $conn->query("SELECT COUNT(DISTINCT patient_id) FROM appointment WHERE appointment_date = CURDATE() AND status='Confirmed' AND down_payment_status != 'Rejected'")->fetch_row()[0]; ?></h3></div>
                    </div>
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-pink-100 flex items-center gap-4">
                        <div class="w-14 h-14 bg-purple-50 text-purple-500 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-clipboard-list"></i></div>
                        <div><p class="text-xs font-bold text-gray-400 uppercase">Waitlist</p><h3 class="text-3xl font-extrabold text-gray-800"><?php echo $conn->query("SELECT COUNT(*) FROM appointment WHERE appointment_date = CURDATE() AND status='Pending' AND down_payment_status != 'Rejected'")->fetch_row()[0]; ?></h3></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-3xl shadow-sm border border-pink-100 p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="font-bold text-lg text-gray-800">Today's Appointments</h3>
                            <p class="text-xs text-muted leading-relaxed">Schedule of confirmed patients ready for service.</p>
                        </div>
                        <span class="bg-pink-100 text-pink-700 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">Schedule</span>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-pink-50 text-pink-800 font-bold">
                            <tr>
                                <th class="p-4 rounded-l-xl">Time</th>
                                <th class="p-4">Patient</th>
                                <th class="p-4">Service</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 rounded-r-xl text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-50">
                            <?php 
                            // SHOW ONLY CONFIRMED APPOINTMENTS
                            $res = $conn->query("SELECT a.appointment_id, MAX(a.status) as status, a.appointment_time, a.service, u.name, p.patient_id 
                                               FROM appointment a 
                                               JOIN patient p ON a.patient_id=p.patient_id 
                                               JOIN users u ON p.user_id=u.user_id 
                                               WHERE a.appointment_date=CURDATE() 
                                               AND a.status IN ('Confirmed', 'Arrived') 
                                               GROUP BY p.patient_id, a.appointment_time 
                                               ORDER BY a.appointment_time ASC");
                            if($res->num_rows > 0):
                                while($row = $res->fetch_assoc()): 
                                    $status_label = $row['status'];
                                    $status_class = ($row['status'] == 'Confirmed') ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700';
                                ?>
                                <tr class="hover:bg-pink-50/50 transition-colors">
                                    <td class="p-4 font-semibold text-gray-600"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                    <td class="p-4 font-bold text-gray-800"><?php echo $row['name']; ?></td>
                                    <td class="p-4 text-gray-700"><?php echo $row['service']; ?></td>
                                    <td class="p-4"><span class="px-3 py-1 rounded-full text-[10px] font-bold <?php echo $status_class; ?> uppercase tracking-tighter"><?php echo $status_label; ?></span></td>
                                    <td class="p-4 text-right">
                                        <?php if(in_array($row['status'], ['Confirmed', 'Arrived'])): ?>
                                            <button onclick="servePatient(<?php echo $row['patient_id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo $row['service']; ?>')" class="bg-primary text-white px-5 py-2 rounded-xl text-xs font-bold hover:bg-pink-600 transition-all shadow-md active:scale-95">
                                                <i class="fas fa-hand-holding-medical mr-1"></i> Serve
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-[10px] italic">Finishing Payment...</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="p-8 text-center text-gray-400 text-xs italic">No appointments scheduled for today.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recently Completed / Activity Section (NEW) -->
                <div class="bg-white rounded-3xl shadow-sm border border-pink-100 p-6 opacity-80 hover:opacity-100 transition-opacity">
                    <h3 class="font-bold text-sm text-gray-500 mb-4 uppercase tracking-widest"><i class="fas fa-history mr-2"></i>Recently Seen Today</h3>
                    <div class="space-y-3">
                    <?php 
                    $completed = $conn->query("SELECT a.*, u.name FROM appointment a JOIN patient p ON a.patient_id=p.patient_id JOIN users u ON p.user_id=u.user_id WHERE a.appointment_date=CURDATE() AND a.status='Completed' ORDER BY a.appointment_time DESC LIMIT 5");
                    if($completed->num_rows > 0):
                        while($crow = $completed->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-2xl border border-gray-100 italic">
                            <div class="flex items-center gap-4 text-xs">
                                <span class="text-gray-400 font-mono"><?php echo date('h:i A', strtotime($crow['appointment_time'])); ?></span>
                                <span class="font-bold text-gray-500"><?php echo $crow['name']; ?></span>
                                <span class="bg-white px-2 py-0.5 rounded-lg border border-gray-200 text-gray-400"><?php echo $crow['service']; ?></span>
                            </div>
                            <span class="text-[9px] font-bold text-green-500 uppercase"><i class="fas fa-check-double mr-1"></i>Finished</span>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-xs text-gray-300 italic p-2 text-center">No patients served yet today.</p>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="service" class="tab-content">
                <div class="max-w-4xl bg-white p-8 rounded-3xl shadow-sm border border-pink-100">
                    <h3 class="font-bold text-xl text-gray-800 mb-2">Service Delivery Form</h3>
                    <p class="text-sm text-gray-500 mb-6">Patient details will be auto-filled from the dashboard queue.</p>
                    <?php echo $alert; ?>

                    <!-- Locked State Message (NEW) -->
                    <div id="service_locked_message" class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-3xl p-12 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6 text-gray-400">
                            <i class="fas fa-user-lock text-3xl"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-700 mb-2">No Active Patient</h4>
                        <p class="text-sm text-gray-400 max-w-xs mx-auto mb-8">Please go to the Dashboard and click the "Serve" button for a confirmed patient to unlock this form.</p>
                        <button type="button" onclick="switchTab('dashboard', document.querySelector('.nav-item'))" class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-pink-600 transition-all shadow-lg active:scale-95">
                            Go to Dashboard
                        </button>
                    </div>
                    
                    <div id="service_form_container" class="hidden">
                        <!-- Active Patient info header -->
                        <div class="bg-pink-50 border border-pink-100 rounded-2xl p-4 mb-8 flex items-center justify-between animate-pulse-subtle">
                             <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-primary shadow-sm">
                                    <i class="fas fa-notes-medical"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-primary uppercase tracking-wider">Serving Patient</p>
                                    <h4 id="serving_patient_display" class="font-extrabold text-gray-800 text-lg leading-tight">---</h4>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase mt-0.5">Booked: <span id="booked_service_display" class="text-pink-500">None</span></p>
                                </div>
                             </div>
                             <button type="button" onclick="resetServiceForm()" class="text-xs font-bold text-gray-400 hover:text-red-500 transition-colors">
                                <i class="fas fa-times-circle mr-1"></i> Cancel session
                             </button>
                        </div>

                        <form method="POST" class="space-y-6">
                            
                            <!-- Patient Selection (Read-only/Hidden) -->
                            <div class="hidden">
                                <input type="hidden" name="patient_id" id="patient_id_hidden" required>
                                <input type="hidden" name="booked_service_name" id="booked_service_name_hidden">
                                <input type="text" id="patient_search_input">
                            </div>

                            <!-- Service Type Selection -->
                            <div class="bg-gradient-to-r from-pink-50 to-purple-50 p-5 rounded-xl border border-pink-200">
                                <label class="block text-xs font-bold text-primary uppercase mb-3">
                                    <i class="fas fa-stethoscope mr-2"></i>Service Type
                                </label>
                                <select name="service_type" id="service_type" onchange="showServiceFields()" class="w-full border-pink-200 rounded-xl bg-white h-12 px-4 focus:ring-2 focus:ring-primary font-medium text-gray-700 shadow-sm pointer-events-none bg-gray-50/50">
                                    <option value="">-- Select Service Type --</option>
                                    <option value="prenatal">Prenatal Checkup (ANC01)</option>
                                    <option value="labor_watch">Pre-Labor / Labor Watch (ANC02)</option>
                                    <!-- <option value="ultrasound">Ultrasound</option> -->
                                    <!-- <option value="laboratory">Laboratory Tests</option> -->
                                    <option value="postnatal">Postnatal Checkup</option>
                                    <!-- <option value="family_planning">Family Planning Consultation</option> -->
                                    <!-- <option value="immunization">Immunization (Vaccination)</option> -->
                                    <option value="delivery">Delivery Record (NSD/MCP)</option>
                                    <option value="newborn">Newborn Care (NCP)</option>
                                    <!-- <option value="walkin">Walk-in Consultation</option> -->
                                </select>
                            </div>

                        <!-- PRENATAL FIELDS -->
                        <div id="prenatal_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-baby mr-2"></i>Prenatal Care Package
                            </h4>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Blood Pressure <span class="text-red-500">*</span></label><input type="text" name="bp" placeholder="e.g. 120/80" pattern="\d{2,3}/\d{2,3}" title="Please enter BP in format: 120/80" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Weight (kg) <span class="text-red-500">*</span></label><input type="number" step="0.01" name="weight" placeholder="e.g. 65" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Fetal Heart Rate <span class="text-red-500">*</span></label><input type="text" name="fhr" placeholder="e.g. 140 bpm" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">AOG (Weeks) <span class="text-red-500">*</span></label><input type="number" name="aog_weeks" min="1" max="45" placeholder="e.g. 24" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Fundic Height</label><input type="text" name="fundic_height" placeholder="cm" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm"></div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Fetal Presentation</label>
                                    <select name="fetal_presentation" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="">Select Presentation...</option>
                                        <option value="Cephalic">Cephalic (Head First)</option>
                                        <option value="Breech">Breech (Feet/Buttocks First)</option>
                                        <option value="Transverse">Transverse (Sideways)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-4 bg-teal-50 p-4 rounded-xl border border-teal-100">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-3">
                                    <h5 class="text-xs font-bold text-teal-700 uppercase"><i class="fas fa-clipboard-check mr-2"></i>ANC01 Package Tracking</h5>
                                    
                                    <div class="flex items-center gap-3 mt-2 md:mt-0 bg-white px-3 py-1 rounded-lg border border-teal-200">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase">Visit #</span>
                                        <div class="flex gap-2">
                                            <label class="flex items-center gap-1 text-xs cursor-pointer"><input type="radio" name="visit_number" value="1" class="accent-teal-500"> 1st</label>
                                            <label class="flex items-center gap-1 text-xs cursor-pointer"><input type="radio" name="visit_number" value="2" class="accent-teal-500"> 2nd</label>
                                            <label class="flex items-center gap-1 text-xs cursor-pointer"><input type="radio" name="visit_number" value="3" class="accent-teal-500"> 3rd</label>
                                            <label class="flex items-center gap-1 text-xs cursor-pointer"><input type="radio" name="visit_number" value="4" class="accent-teal-500"> 4th</label>
                                            <label class="flex items-center gap-1 text-xs cursor-pointer"><input type="radio" name="visit_number" value="Other" class="accent-teal-500"> Other</label>
                                            </div>
                        </div>


                                    </div>
                                </div>

                                <h6 class="text-[10px] font-bold text-teal-600 uppercase mb-2 mt-2">Essential Health Services Given</h6>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-white p-3 rounded-lg border border-teal-100/50">
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="prenatal_iron" class="text-primary rounded"> Iron/Folic Acid</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="prenatal_calcium" class="text-primary rounded"> Calcium Carb.</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="prenatal_tetanus" class="text-primary rounded"> Tetanus Toxoid</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="prenatal_deworm" class="text-primary rounded"> Deworming</label>
                                    
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="svc_bp" class="text-primary rounded" checked disabled> BP Monitoring</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="svc_weight" class="text-primary rounded" checked disabled> Weight Mgmt</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="svc_birthplan" class="text-primary rounded"> Birth Plan Formulated</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="svc_dental" class="text-primary rounded"> Dental Advice</label>
                                </div>
                            </div>

                            <h5 class="text-xs font-bold text-gray-600 mt-4 mb-2 uppercase">Risk Signs</h5>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Vaginal Bleeding</label><select name="vaginal_bleeding" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm"><option value="No">No</option><option value="Yes">Yes</option></select></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Fever (39°C+)</label><select name="fever" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm"><option value="No">No</option><option value="Yes">Yes</option></select></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Pallor</label><select name="pallor" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm"><option value="No">No</option><option value="Yes">Yes</option></select></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Edema</label><select name="edema" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm"><option value="No">No</option><option value="Yes">Yes</option></select></div>
                            </div>
                        </div>

                        <!-- LABOR WATCH FIELDS (ANC02) -->
                        <div id="labor_watch_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-stopwatch mr-2"></i>Intrapartum Monitoring (ANC02)
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Labor Phase</label>
                                    <select name="labor_phase" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="Latent">Latent Phase</option>
                                        <option value="Active">Active Phase</option>
                                    </select>
                                </div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Cervical Dilation (cm)</label><input type="number" name="cervical_dilation" min="0" max="10" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm"></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Contractions (in 10m)</label><input type="text" name="contractions" placeholder="e.g. 3 in 10mins" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm"></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Fetal Heart Rate</label><input type="text" name="labor_fhr" placeholder="bpm" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm"></div>
                            </div>
                            <div class="mt-4 bg-pink-50 p-3 rounded-lg border border-pink-100 flex items-center gap-4">
                                <label class="flex items-center gap-2 text-xs font-bold text-gray-700 uppercase"><input type="checkbox" name="partograph_started" class="w-4 h-4 text-primary rounded"> Partograph Started / Updated</label>
                                <label class="flex items-center gap-2 text-xs font-bold text-gray-700 uppercase"><input type="checkbox" name="iv_fluids" class="w-4 h-4 text-primary rounded"> IV Fluids Started</label>
                            </div>
                        </div>

                        <!-- ULTRASOUND FIELDS -->
                        <div id="ultrasound_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-wave-square mr-2"></i>Ultrasound Details
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Indication</label>
                                    <select name="indication" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="">Select Indication...</option>
                                        <option value="Pregnancy Confirmation">Pregnancy Confirmation</option>
                                        <option value="AOG Determination">AOG Determination</option>
                                        <option value="Fetal Biometry">Fetal Biometry</option>
                                        <option value="Placental Localization">Placental Localization</option>
                                        <option value="Fetal Well-being">Fetal Well-being</option>
                                        <option value="Gender Determination">Gender Determination</option>
                                        <option value="Abnormal Bleeding">Abnormal Bleeding</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Result Summary</label><input type="text" name="result_summary" placeholder="Brief findings" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm"></div>
                            </div>
                        </div>

                        <!-- LABORATORY FIELDS -->
                        <div id="laboratory_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-flask mr-2"></i>Laboratory Results
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Test Type</label>
                                    <select name="test_type" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="">Select Test...</option>
                                        <option value="Urinalysis">Urinalysis</option>
                                        <option value="CBC">CBC (Complete Blood Count)</option>
                                        <option value="Blood Typing">Blood Typing</option>
                                        <option value="Hepatitis B Screening">Hepatitis B Screening</option>
                                        <option value="HIV Screening">HIV Screening</option>
                                        <option value="Syphilis Screening">Syphilis Screening</option>
                                        <option value="Blood Glucose">Blood Glucose</option>
                                        <option value="Pregnancy Test">Pregnancy Test</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Result Status</label><select name="lab_status" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm"><option value="Normal">Normal</option><option value="Abnormal">Abnormal</option><option value="Pending">Pending</option></select></div>
                            </div>
                        </div>

                        <!-- POSTNATAL FIELDS -->
                        <div id="postnatal_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-heart mr-2"></i>Postnatal Care Package
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">No. of Babies <span class="text-red-500">*</span></label>
                                    <input type="number" name="delivery_count" value="1" min="1" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" title="For Twins, Triplets, etc." required>
                                </div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Blood Pressure <span class="text-red-500">*</span></label><input type="text" name="bp" placeholder="e.g. 120/80" pattern="\d{2,3}/\d{2,3}" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Temperature (°C) <span class="text-red-500">*</span></label><input type="number" step="0.1" name="temperature" placeholder="e.g. 36.5" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                            </div>
                             <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Uterine Involution</label>
                                    <select name="uterine_involution" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="Normal">Normal (Involuting)</option>
                                        <option value="Subinvolution">Subinvolution (Abnormal)</option>
                                    </select>
                                </div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Lochia</label><select name="lochia" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm"><option value="Normal">Normal</option><option value="Abnormal">Abnormal / Foul Smell</option></select></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Breastfeeding / Lactation</label><select name="breastfeeding" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm"><option value="Exclusive">Exclusive</option><option value="Mixed">Mixed</option><option value="Formula">Formula</option></select></div>
                            </div>
                            
                            <!-- Package Inclusions -->
                            <div class="mt-4 bg-teal-50 p-4 rounded-xl border border-teal-100">
                                <h5 class="text-xs font-bold text-teal-700 uppercase mb-2"><i class="fas fa-clipboard-check mr-2"></i>Postnatal Package Inclusions</h5>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-white p-3 rounded-lg border border-teal-100/50">
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="postnatal_vitA" class="text-primary rounded"> Vitamin A Supplement</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="postnatal_iron" class="text-primary rounded"> Iron Supplement</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="postnatal_counsel" class="text-primary rounded"> FP Counseling</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="postnatal_perineum" class="text-primary rounded"> Perineal Wound Care</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="postnatal_breast" class="text-primary rounded"> Breastfeeding Support</label>
                                </div>
                            </div>
                        </div>

                        <!-- FAMILY PLANNING FIELDS -->
                        <div id="family_planning_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-users mr-2"></i>Family Planning Details
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Method Discussed</label>
                                    <select name="fp_method" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="">Select Primary Method...</option>
                                        <option value="Pills (COC)">Pills (Combined Oral Contraceptives)</option>
                                        <option value="Pills (POP)">Pills (Progestin-Only)</option>
                                        <option value="Injectable">Injectable (DMPA)</option>
                                        <option value="Implant">Progestin Implant</option>
                                        <option value="IUD">Intrauterine Device (IUD)</option>
                                        <option value="Condom">Condom</option>
                                        <option value="NFP">Natural Family Planning</option>
                                        <option value="Sterilization">Sterilization (Ligation/Vasectomy)</option>
                                        <option value="LAM">Lactational Amenorrhea Method</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Method Chosen</label>
                                    <select name="fp_chosen" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="">Select Choice...</option>
                                        <option value="None">None / Undecided</option>
                                        <option value="Pills (COC)">Pills (Combined Oral Contraceptives)</option>
                                        <option value="Pills (POP)">Pills (Progestin-Only)</option>
                                        <option value="Injectable">Injectable (DMPA)</option>
                                        <option value="Implant">Progestin Implant</option>
                                        <option value="IUD">Intrauterine Device (IUD)</option>
                                        <option value="Condom">Condom</option>
                                        <option value="NFP">Natural Family Planning</option>
                                        <option value="Sterilization">Sterilization (Ligation/Vasectomy)</option>
                                        <option value="LAM">Lactational Amenorrhea Method</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- IMMUNIZATION FIELDS -->
                        <div id="immunization_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-syringe mr-2"></i>Immunization Details
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Vaccine Type</label>
                                    <select name="vaccine_type" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="">Select Vaccine...</option>
                                        <option value="Tetanus Toxoid">Tetanus Toxoid (TT)</option>
                                        <option value="Td">Tetanus-diphtheria (Td)</option>
                                        <option value="Hepatitis B">Hepatitis B</option>
                                        <option value="Influenza">Influenza (Flu)</option>
                                        <option value="COVID-19">COVID-19</option>
                                        <option value="MMR">Measles, Mumps, Rubella</option>
                                        <option value="BCG">BCG</option>
                                        <option value="Pentavalent">Pentavalent</option>
                                        <option value="OPV">Oral Polio Vaccine (OPV)</option>
                                        <option value="IPV">Inactivated Polio Vaccine (IPV)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Dose Number</label>
                                    <select name="dose_number" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm">
                                        <option value="1st Dose">1st Dose</option>
                                        <option value="2nd Dose">2nd Dose</option>
                                        <option value="3rd Dose">3rd Dose</option>
                                        <option value="Booster 1">Booster 1</option>
                                        <option value="Booster 2">Booster 2</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- DELIVERY FIELDS -->
                        <div id="delivery_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2">
                                <i class="fas fa-baby-carriage mr-2"></i>Maternity Care Package (NSD/MCP)
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Time of Delivery <span class="text-red-500">*</span></label><input type="time" name="delivery_time" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Sex of Baby <span class="text-red-500">*</span></label>
                                    <select name="sex" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-2 text-sm" required><option value="">Select...</option><option value="Male">Male</option><option value="Female">Female</option></select>
                                </div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Weight (grams) <span class="text-red-500">*</span></label><input type="number" name="baby_weight" placeholder="e.g. 3200" min="500" max="8000" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Length (cm) <span class="text-red-500">*</span></label><input type="number" step="0.1" name="baby_length" min="10" max="80" placeholder="e.g. 50" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" required></div>
                            </div>
                            
                            <!-- EINC & Package Protocols -->
                            <div class="mt-4 bg-teal-50 p-4 rounded-xl border border-teal-100">
                                <h5 class="text-xs font-bold text-teal-700 uppercase mb-2"><i class="fas fa-clipboard-check mr-2"></i>EINC & MCP Protocols</h5>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 bg-white p-3 rounded-lg border border-teal-100/50">
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="einc_dry" class="text-primary rounded"> Immediate Drying</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="einc_ssc" class="text-primary rounded"> Skin-to-Skin Contact</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="einc_cord" class="text-primary rounded"> Cord Clamping (Timed)</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="einc_breast" class="text-primary rounded"> Early Breastfeeding</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="placenta_intact" class="text-primary rounded"> Placenta Complete</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="perineum_intact" class="text-primary rounded"> Perineum Intact/Sutured</label>
                                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="partograph_used" class="text-primary rounded"> Partograph Used</label>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mt-4 text-xs">
                                <div class="bg-gray-50 p-3 rounded-lg"><label class="font-bold block mb-1">Apgar (1 min) <span class="text-red-500">*</span></label><input type="number" name="apgar1" min="0" max="10" placeholder="0-10" class="w-full border-gray-200 rounded h-8 px-2" required></div>
                                <div class="bg-gray-50 p-3 rounded-lg"><label class="font-bold block mb-1">Apgar (5 min) <span class="text-red-500">*</span></label><input type="number" name="apgar5" min="0" max="10" placeholder="0-10" class="w-full border-gray-200 rounded h-8 px-2" required></div>
                            </div>
                        </div>

                        <!-- NEWBORN CARE FIELDS (NCP) -->
                        <div id="newborn_fields" class="service-fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wide border-b border-gray-100 pb-2 flex justify-between items-center">
                                <span><i class="fas fa-baby mr-2"></i>Newborn Care Package</span>
                                <label class="flex items-center gap-2 cursor-pointer bg-pink-50 px-3 py-1 rounded-full border border-pink-100 hover:bg-pink-100 transition-colors">
                                    <span class="text-[10px] font-bold text-pink-600 uppercase">Twin / Multiple?</span>
                                    <input type="checkbox" name="is_twin" id="is_twin_toggle" class="accent-pink-600 w-4 h-4" onchange="toggleTwinFields(this)">
                                </label>
                            </h4>
                            
                            <!-- BABY 1 -->
                            <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100 mb-4 relative">
                                <div class="absolute top-2 right-2 text-[10px] font-bold text-blue-300 uppercase tracking-widest">Baby A</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div><label class="block text-xs font-bold text-gray-500 mb-1">Birth Weight (grams) <span class="text-red-500">*</span></label><input type="number" name="newborn_weight" placeholder="e.g. 3000" min="500" max="8000" class="w-full border-gray-200 rounded-xl bg-white h-10 px-3 text-sm" required></div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="ncp_vitk" class="text-primary rounded"> Vitamin K</label>
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="ncp_eye" class="text-primary rounded"> Eye Prophylaxis</label>
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="cord_care" class="text-primary rounded"> Cord Care</label>
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="nbs" class="text-primary rounded"> Newborn Screen</label>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="bcg" class="text-primary rounded"> BCG Vaccine</label>
                                    <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="hepb" class="text-primary rounded"> Hep B Vaccine</label>
                                    <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="hearing" class="text-primary rounded"> Hearing Test</label>
                                </div>
                            </div>

                            <!-- BABY 2 (Hidden by default) -->
                            <div id="baby2_fields" class="hidden bg-purple-50/50 p-4 rounded-xl border border-purple-100 mb-4 relative animate-fade-in">
                                <div class="absolute top-2 right-2 text-[10px] font-bold text-purple-300 uppercase tracking-widest">Baby B (Twin)</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div><label class="block text-xs font-bold text-gray-500 mb-1">Birth Weight (grams)</label><input type="number" name="newborn_weight_2" placeholder="e.g. 2800" class="w-full border-gray-200 rounded-xl bg-white h-10 px-3 text-sm"></div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="ncp_vitk_2" class="text-purple-600 rounded"> Vitamin K</label>
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="ncp_eye_2" class="text-purple-600 rounded"> Eye Prophylaxis</label>
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="cord_care_2" class="text-purple-600 rounded"> Cord Care</label>
                                        <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="nbs_2" class="text-purple-600 rounded"> Newborn Screen</label>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="bcg_2" class="text-purple-600 rounded"> BCG Vaccine</label>
                                    <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="hepb_2" class="text-purple-600 rounded"> Hep B Vaccine</label>
                                    <label class="flex items-center gap-2 text-xs bg-white p-2 rounded-lg border border-gray-100"><input type="checkbox" name="hearing_2" class="text-purple-600 rounded"> Hearing Test</label>
                                </div>
                            </div>
                        </div>

                        <!-- WALK-IN FIELDS REMOVED -->

                        <!-- COMMON FIELDS (Always shown after service selection) -->
                        <div id="common_fields" style="display:none;">
                            <h4 class="text-sm font-bold text-primary mb-3 uppercase tracking-wide border-b border-gray-100 pb-2">Management & Plan</h4>
                            <div class="space-y-4">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Medications Given</label><input type="text" name="medications" class="w-full border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm" placeholder="List meds provided..."></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Findings / Remarks</label><textarea name="findings" rows="3" class="w-full border-gray-200 rounded-xl bg-gray-50 p-3 text-sm" placeholder="Enter findings and recommendations..."></textarea></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1">Date of Next Visit</label><input type="date" name="next_visit" class="w-full md:w-1/2 border-gray-200 rounded-xl bg-gray-50 h-10 px-3 text-sm"></div>
                            </div>
                        </div>

                        <button type="submit" name="save_record" id="submit_btn" disabled class="w-full h-12 bg-gray-300 text-gray-500 font-bold rounded-xl shadow-lg cursor-not-allowed transition-all">
                            <i class="fas fa-lock mr-2"></i>Select a Service Type to Continue
                        </button>
                    </form>
                </div>
                </div>
            </div>

            <div id="charges" class="tab-content">
                <div class="max-w-5xl">
                    <div class="bg-white p-8 rounded-3xl shadow-sm border border-pink-100 mb-6">
                        <h3 class="font-bold text-xl text-gray-800 mb-2">Record Service Charges</h3>
                        <p class="text-sm text-gray-500 mb-6">Record services provided. Charges will be pending until clerk approval.</p>
                        <?php if(isset($msg)) echo $msg; ?>
                        
                        <!-- Locked State Message (NEW) -->
                        <div id="charge_locked_message" class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-3xl p-12 text-center <?php echo isset($_GET['action']) && $_GET['action'] == 'charge' ? 'hidden' : ''; ?>">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6 text-gray-400">
                                <i class="fas fa-cash-register text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-700 mb-2">No Active Service to Charge</h4>
                            <p class="text-sm text-gray-400 max-w-xs mx-auto mb-8">Service charges are automatically generated after you complete a patient service record.</p>
                            <button type="button" onclick="switchTab('dashboard', document.querySelector('.nav-item'))" class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-pink-600 transition-all shadow-lg active:scale-95">
                                Go to Dashboard
                            </button>
                        </div>

                        <!-- Selected Patient Summary Card (NEW) -->
                        <div id="patient_confirm_card" class="mb-6 hidden animate-bounce-in">
                            <div class="bg-pink-50 border border-pink-100 rounded-2xl p-4 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-primary shadow-sm border border-pink-50">
                                        <i class="fas fa-user-check text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-muted uppercase tracking-wider">Charging Patient</p>
                                        <h4 id="confirm_patient_name" class="font-extrabold text-gray-800">No Patient Selected</h4>
                                    </div>
                                </div>
                                <button type="button" onclick="clearPatientSelection()" class="text-xs font-bold text-red-400 hover:text-red-600 transition-colors">
                                    <i class="fas fa-times-circle mr-1"></i> Change
                                </button>
                            </div>
                        </div>
                        
                        <form method="POST" class="space-y-6 <?php echo isset($_GET['action']) && $_GET['action'] == 'charge' ? '' : 'hidden'; ?>" id="chargeForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Patient Selection -->
                                <div class="relative">
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Select Patient</label>
                                    <input type="hidden" name="charge_patient_id" id="charge_patient_id" required>
                                    <input type="hidden" name="is_twin_sequence" id="is_twin_sequence" value="0">
                                    <div class="relative">
                                        <input type="text" id="charge_patient_search" placeholder="Search patient..." class="w-full border-gray-200 rounded-xl bg-gray-50 h-12 px-4 focus:ring-primary focus:border-primary shadow-sm" autocomplete="off">
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400"><i class="fas fa-search"></i></div>
                                    </div>
                                    <div id="charge_patient_dropdown" class="custom-scroll hidden absolute z-50 w-full bg-white border border-gray-100 rounded-xl shadow-xl mt-1 max-h-60 overflow-y-auto"></div>
                                </div>

                                <!-- Service Selection (Auto-filled & Locked) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Service Provided</label>
                                    
                                    <!-- Hidden input for form submission (Removed 'required' to prevent silent browser block) -->
                                    <input type="hidden" name="service_id" id="service_id_hidden">
                                    
                                    <!-- Display-only field showing the service -->
                                    <div class="w-full border-2 border-blue-200 bg-blue-50 rounded-xl h-12 px-4 flex items-center justify-between shadow-sm">
                                        <div class="flex items-center gap-2">
                                            <span id="service_display_text" class="font-bold text-gray-800">Loading service...</span>
                                            <div id="service_loading_spinner" class="animate-spin h-4 w-4 border-2 border-blue-400 border-t-transparent rounded-full"></div>
                                            <input type="hidden" id="service_price_hidden">
                                        </div>
                                        <i class="fas fa-lock text-blue-400"></i>
                                    </div>
                                    <p class="text-xs text-blue-600 mt-1"><i class="fas fa-info-circle"></i> Service is auto-selected based on patient's appointment</p>
                                </div>
                            </div>

                            <!-- PhilHealth Toggle -->
                            <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl flex items-center gap-3 mb-6">
                                <input type="checkbox" name="is_philhealth" id="is_philhealth" value="1" onchange="updatePrice()" class="w-5 h-5 text-primary rounded focus:ring-primary border-gray-300">
                                <div>
                                    <label for="is_philhealth" class="text-sm font-bold text-gray-800">Apply PhilHealth Membership</label>
                                    <p class="text-xs text-blue-600">Check this for No Balance Billing / Zero Payment packages</p>
                                </div>
                            </div>


                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Quantity -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Quantity</label>
                                    <input type="number" name="quantity" id="quantity_input" value="1" min="1" required class="w-full border-gray-200 rounded-xl bg-gray-50 h-12 px-4 focus:ring-primary focus:border-primary shadow-sm" oninput="updatePrice()">
                                </div>

                                <!-- Unit Price (Read-only) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Unit Price</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-3.5 text-gray-500 font-bold">₱</span>
                                        <input type="text" id="unit_price_display" readonly class="w-full border-gray-200 rounded-xl bg-gray-100 h-12 pl-8 pr-4 font-bold text-gray-700 cursor-not-allowed">
                                    </div>
                                </div>

                                <!-- Total Amount (Read-only) -->
                                <div>
                                    <label class="block text-xs font-bold text-primary uppercase mb-2">Total Amount</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-3.5 text-primary font-bold">₱</span>
                                        <input type="text" id="total_amount_display" readonly class="w-full border-primary rounded-xl bg-white h-12 pl-8 pr-4 font-bold text-primary shadow-sm" value="0.00">
                                    </div>
                                    <p id="dp_deduction_indicator" class="text-[10px] text-green-600 font-bold mt-1 hidden"><i class="fas fa-check-circle mr-1"></i> -₱500.00 Downpayment Deducted</p>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Notes (Optional)</label>
                                <textarea name="charge_notes" rows="2" class="w-full border-gray-200 rounded-xl bg-gray-50 p-3 text-sm focus:ring-primary focus:border-primary" placeholder="Additional details about the service..."></textarea>
                            </div>

                            <button type="submit" name="record_charge" id="record_charge_btn" class="w-full h-12 bg-primary hover:bg-pink-600 text-white font-bold rounded-xl shadow-lg shadow-pink-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-plus-circle mr-2"></i> Record Charge
                            </button>
                            <?php if(isset($_GET['twin']) && $_GET['twin'] == '1'): ?>
                                <input type="hidden" name="is_twin_sequence" value="1">
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Pending Charges List -->
                    <div class="bg-white rounded-3xl shadow-sm border border-pink-100 overflow-hidden">
                        <div class="p-6 border-b border-pink-50 bg-gradient-to-r from-pink-50 to-white">
                            <h3 class="font-bold text-lg text-gray-800">My Pending Charges</h3>
                            <p class="text-xs text-gray-500">Awaiting clerk verification</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-pink-50 text-pink-900 font-bold uppercase text-xs">
                                    <tr>
                                        <th class="p-4">Date</th>
                                        <th class="p-4">Patient</th>
                                        <th class="p-4">Service</th>
                                        <th class="p-4">Qty</th>
                                        <th class="p-4">Amount</th>
                                        <th class="p-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-pink-50">
                                    <?php
                                    $pending_charges = $conn->query("
                                        SELECT pc.*, sp.service_name, u.name as patient_name 
                                        FROM pending_charges pc
                                        JOIN service_pricing sp ON pc.service_id = sp.service_id
                                        JOIN patient p ON pc.patient_id = p.patient_id
                                        JOIN users u ON p.user_id = u.user_id
                                        WHERE pc.recorded_by = $midwife_id
                                        ORDER BY pc.created_at DESC
                                        LIMIT 20
                                    ");
                                    
                                    if ($pending_charges->num_rows > 0):
                                        while ($charge = $pending_charges->fetch_assoc()):
                                            $status_class = $charge['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-700' : 
                                                           ($charge['status'] == 'Approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
                                    ?>
                                        <tr class="hover:bg-pink-50/30">
                                            <td class="p-4 text-gray-600"><?php echo date('M d, Y', strtotime($charge['created_at'])); ?></td>
                                            <td class="p-4 font-bold"><?php echo htmlspecialchars($charge['patient_name']); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($charge['service_name']); ?></td>
                                            <td class="p-4 text-center"><?php echo $charge['quantity']; ?></td>
                                            <td class="p-4 font-bold text-primary">₱<?php echo number_format($charge['total_amount'], 2); ?></td>
                                            <td class="p-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $status_class; ?>">
                                                    <?php echo $charge['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr><td colspan="6" class="p-8 text-center text-gray-400">No charges recorded yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="records" class="tab-content">
                <div class="bg-white rounded-3xl shadow-sm border border-pink-100 overflow-hidden">
                    <div class="p-6 border-b border-pink-50"><h3 class="font-bold text-lg text-gray-800">Patient Directory</h3></div>
                    <div class="overflow-x-auto p-4">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 font-bold uppercase text-xs">
                                <tr><th class="p-4">Patient Name</th><th class="p-4">Last Checkup</th><th class="p-4 text-right">Action</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php 
                                $sql = "SELECT u.name, p.patient_id, MAX(records.latest_date) as last_checkup 
                                        FROM patient p 
                                        JOIN users u ON p.user_id = u.user_id 
                                        LEFT JOIN (
                                            SELECT patient_id, checkup_date as latest_date FROM prenatal_records
                                            UNION ALL
                                            SELECT patient_id, checkup_date FROM ultrasound_records
                                            UNION ALL
                                            SELECT patient_id, checkup_date FROM laboratory_records
                                            UNION ALL
                                            SELECT patient_id, checkup_date FROM postnatal_records
                                            UNION ALL
                                            SELECT patient_id, checkup_date FROM family_planning_records
                                            UNION ALL
                                            SELECT patient_id, checkup_date FROM immunization_records
                                            UNION ALL
                                            SELECT patient_id, checkup_date FROM consultation_records
                                            UNION ALL
                                            SELECT patient_id, delivery_date FROM delivery_records
                                            UNION ALL
                                            SELECT patient_id, checkup_date FROM newborn_records
                                        ) records ON p.patient_id = records.patient_id 
                                        GROUP BY p.patient_id 
                                        ORDER BY u.name ASC";
                                
                                $recs = $conn->query($sql);
                                while($r = $recs->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-pink-50/30">
                                    <td class="p-4 font-bold text-gray-800"><?php echo $r['name']; ?></td>
                                    <td class="p-4"><?php echo $r['last_checkup'] ? date('M d, Y', strtotime($r['last_checkup'])) : '<span class="text-gray-400">No records yet</span>'; ?></td>
                                    <td class="p-4 text-right">
                                        <a href="midwife_dashboard.php?view_history=<?php echo $r['patient_id']; ?>" class="text-primary hover:underline font-bold text-xs bg-pink-50 px-3 py-2 rounded-lg hover:bg-pink-100 transition-colors">
                                            View History
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <script>
        function switchTab(tabId, el) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            el.classList.add('active');
        }

        function servePatient(patientId, patientName, serviceType) {
            // 0. Trigger Database Lock (Mark as Arrived)
            const fd = new FormData();
            fd.append('mark_arrived', '1');
            fd.append('patient_id', patientId);
            fetch('midwife_dashboard.php', { method: 'POST', body: fd }).catch(e => console.error(e));

            // 1. Switch tab to service
            const serviceBtn = document.querySelector('button[onclick*="switchTab(\'service\'"]');
            switchTab('service', serviceBtn);
            
            // 2. Unlock the form and hide message
            document.getElementById('service_locked_message').classList.add('hidden');
            document.getElementById('service_form_container').classList.remove('hidden');

            // 3. Populate display and data
            document.getElementById('patient_id_hidden').value = patientId;
            document.getElementById('booked_service_name_hidden').value = serviceType;
            document.getElementById('patient_search_input').value = patientName;
            document.getElementById('serving_patient_display').textContent = patientName;
            document.getElementById('booked_service_display').textContent = serviceType; // Set display

            // 4. Auto-select service type based on booking string
            const serviceSelect = document.getElementById('service_type');
            if (serviceSelect && serviceType) {
                const s = serviceType.toLowerCase();
                let val = "walkin"; // default
                
                // Mappings for Packages and Services (ORDER MATTERS: More specific first)
                if (s.includes('nsd') || s.includes('delivery') || s.includes('mcp')) val = "delivery";
                else if (s.includes('ncp') || s.includes('newborn')) val = "newborn";
                else if (s.includes('anc02') || s.includes('labor') || s.includes('watch') || s.includes('intrapartum')) val = "labor_watch";
                else if (s.includes('prenatal') || s.includes('anc') || s.includes('antenatal')) val = "prenatal";
                else if (s.includes('ultrasound')) val = "ultrasound";
                else if (s.includes('laboratory') || s.includes('lab')) val = "laboratory";
                else if (s.includes('postnatal')) val = "postnatal";
                else if (s.includes('family planning')) val = "family_planning";
                else if (s.includes('immuno') || s.includes('vaccine') || s.includes('immunization')) val = "immunization";
                
                serviceSelect.value = val;
                showServiceFields();
                applyPackageDefaults(serviceType);

                if(val === 'prenatal' || val === 'labor_watch') {
                    checkVisitHistory(patientId);
                }
                
                // Initialize Automation (AOG checks & Auto-Findings)
                setTimeout(setupFormAutomation, 500);
            }
            
            // 5. Scroll to top of form
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // FORM AUTOMATION LOGIC
        function setupFormAutomation() {
             // Debug
             // console.log("Initializing Form Automation...");
             
             const aogInput = document.querySelector('input[name="aog_weeks"]');
             const presInput = document.querySelector('select[name="fetal_presentation"]');
             const findings = document.querySelector('textarea[name="findings"]');
             
             // 1. AOG -> Presentation Logic (Enable only if >= 28 weeks)
             if(aogInput && presInput) {
                 function checkAog() {
                     const val = aogInput.value;
                     const w = parseInt(val) || 0;
                     // console.log("Checking AOG:", w);
                     
                     if(w >= 28) { 
                         presInput.disabled = false;
                         presInput.parentElement.classList.remove('opacity-50', 'pointer-events-none');
                         presInput.title = "Accessible (3rd Trimester)";
                     } else {
                         presInput.disabled = true;
                         presInput.value = "";
                         presInput.parentElement.classList.add('opacity-50', 'pointer-events-none');
                         presInput.title = "Assessment starts at 28 weeks";
                     }
                 }
                 aogInput.addEventListener('input', checkAog);
                 aogInput.addEventListener('change', checkAog);
                 aogInput.addEventListener('keyup', checkAog);
                 checkAog(); // Initial check
             } else {
                 // console.warn("AOG inputs not found");
             }

             // 2. Auto-Findings for Labor Watch
             const laborInputs = document.querySelectorAll('#labor_watch_fields input, #labor_watch_fields select');
             if(laborInputs.length > 0 && findings) {
                 function updateFindings() {
                     const svc = document.getElementById('service_type').value;
                     if(svc !== 'labor_watch') return;

                     const phase = document.querySelector('select[name="labor_phase"]')?.value;
                     const cm = document.querySelector('input[name="cervical_dilation"]')?.value;
                     const contr = document.querySelector('input[name="contractions"]')?.value;
                     const fhr = document.querySelector('input[name="labor_fhr"]')?.value;
                     
                     // Construct Summary
                     let parts = [];
                     if(phase) parts.push(`Phase: ${phase}`);
                     if(cm) parts.push(`Dilation: ${cm}cm`);
                     if(contr) parts.push(`Contractions: ${contr}`);
                     if(fhr) parts.push(`FHR: ${fhr}bpm`);
                     
                     if(parts.length > 0) {
                         findings.value = `[ANC02 Monitoring]\n` + parts.join(' | ');
                     }
                 }
                 
                 laborInputs.forEach(el => {
                     el.addEventListener('input', updateFindings);
                     el.addEventListener('change', updateFindings);
                 });
             }
        }
        
        // Add to global scope
        document.addEventListener('DOMContentLoaded', setupFormAutomation);


        function resetServiceForm() {
            if(!confirm("Are you sure you want to cancel the current session? Unsaved data will be lost.")) return;
            
            document.getElementById('service_locked_message').classList.remove('hidden');
            document.getElementById('service_form_container').classList.add('hidden');
            document.getElementById('patient_id_hidden').value = '';
            document.getElementById('booked_service_display').textContent = 'None';
            document.querySelectorAll('form').forEach(f => f.reset());
            showServiceFields(); // Reset field visibility
        }

        // --- SHARED DATA ---
        const patientsData = <?php echo json_encode($pat_array); ?>;

        // --- FILTER FUNCTION (COPIED FROM CLERK) ---
        function setupAutocomplete(inputId, listId, hiddenId, isCharge = false) {
            const input = document.getElementById(inputId);
            const list = document.getElementById(listId);
            const hidden = document.getElementById(hiddenId);

            function renderList(items) {
                list.innerHTML = '';
                if (items.length === 0) {
                    list.innerHTML = '<div class="p-3 text-sm text-gray-500 text-center">No patient found</div>';
                    return;
                }
                items.forEach(patient => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-pink-50 cursor-pointer border-b border-gray-50 text-sm font-medium text-gray-700';
                    div.textContent = patient.name;
                    div.onclick = () => {
                        input.value = patient.name;
                        hidden.value = patient.patient_id;
                        list.classList.add('hidden');
                        
                        if(isCharge) {
                            document.getElementById('patient_confirm_card').classList.remove('hidden');
                            document.getElementById('confirm_patient_name').textContent = patient.name;
                            input.parentElement.classList.add('opacity-40', 'pointer-events-none');
                            checkDownpayment(patient.patient_id);
                        }
                    };
                    list.appendChild(div);
                });
            }

            input.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                hidden.value = ''; // Reset ID on new typing
                if (term.length > 0) {
                    renderList(patientsData.filter(p => p.name.toLowerCase().includes(term)));
                    list.classList.remove('hidden');
                } else {
                    list.classList.add('hidden');
                }
            });

            input.addEventListener('focus', () => {
                const term = input.value.toLowerCase();
                renderList(term ? patientsData.filter(p => p.name.toLowerCase().includes(term)) : patientsData);
                list.classList.remove('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !list.contains(e.target)) {
                    list.classList.add('hidden');
                }
            });
        }

        let isDownpaymentPaid = false; 
        let paymentMode = 'DownPayment';

        function updatePrice() {
            const quantityInput = document.getElementById('quantity_input');
            const unitPriceDisplay = document.getElementById('unit_price_display');
            const totalAmountDisplay = document.getElementById('total_amount_display');
            const servicePriceHidden = document.getElementById('service_price_hidden');
            const philHealthCheckbox = document.getElementById('is_philhealth');
            const dpIndicator = document.getElementById('dp_deduction_indicator');
            
            // Get price from hidden field
            const unitPrice = parseFloat(servicePriceHidden.value) || 0;
            const quantity = parseInt(quantityInput.value) || 1;
            let total = unitPrice * quantity;
            
            // Apply Downpayment Deduction if verified
            if (isDownpaymentPaid) {
                // Get the booked service from the UI (fetched during patient selection)
                const bookedSvc = document.getElementById('booked_service_display').textContent.toLowerCase();
                const currentSvc = document.getElementById('service_display_text')?.textContent.toLowerCase() || '';
                
                // Fuzzy match: check if first 5 chars match or if one string contains the other
                const s1 = currentSvc.substring(0, 5).trim();
                const s2 = bookedSvc.substring(0, 5).trim();
                const isMatch = (s1 === s2 || currentSvc.includes(bookedSvc) || bookedSvc.includes(currentSvc));

                if (paymentMode && paymentMode.toLowerCase() === 'full') {
                    // FULL PAYMENT: Only apply if service matches
                    if (isMatch) {
                        total = 0;
                        if(dpIndicator) {
                            dpIndicator.classList.remove('hidden', 'text-green-600');
                            dpIndicator.classList.add('text-blue-600', 'bg-blue-50', 'p-1', 'rounded');
                            dpIndicator.innerHTML = '<i class="fas fa-check-double mr-1"></i> Pre-paid in Full Online';
                        }
                    } else {
                        if(dpIndicator) {
                            dpIndicator.classList.remove('hidden');
                            dpIndicator.classList.add('text-orange-600');
                            dpIndicator.innerHTML = '<i class="fas fa-info-circle mr-1"></i> Patient has Pre-paid ' + bookedSvc.toUpperCase() + '. This service (' + currentSvc.substring(0,10) + '...) is extra.';
                        }
                    }
                } else {
                    // 50% Downpayment Logic: Apply to first charge of session
                    const deduction = total * 0.5;
                    total = total - deduction;
                    if(dpIndicator) {
                        dpIndicator.classList.remove('hidden');
                        dpIndicator.innerHTML = '<i class="fas fa-check-circle mr-1"></i> -₱' + deduction.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' (50% Downpayment Absorbed)';
                    }
                }
            } else {
                if(dpIndicator) dpIndicator.classList.add('hidden');
            }

            // PhilHealth Logic
            const isPhilHealth = philHealthCheckbox ? philHealthCheckbox.checked : false;
            const finalTotal = isPhilHealth ? 0.00 : total;
            
            unitPriceDisplay.value = unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2});
            totalAmountDisplay.value = finalTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
            
            // Visual feedback
            if(isPhilHealth) {
                totalAmountDisplay.classList.remove('text-primary'); 
                totalAmountDisplay.classList.add('text-blue-600', 'bg-blue-50', 'font-extrabold'); 
            } else {
                totalAmountDisplay.classList.remove('text-blue-600', 'bg-blue-50', 'font-extrabold');
                totalAmountDisplay.classList.add('text-primary'); 
            }
        }

        function checkDownpayment(pid) {
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('nodp') === '1') {
                 console.log("Skipping DP check due to nodp=1");
                 isDownpaymentPaid = false;
                 updatePrice();
                 return;
            }

            isDownpaymentPaid = false; 
            paymentMode = 'DownPayment';
            fetch('check_downpayment.php?pid=' + pid)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_dp) {
                        isDownpaymentPaid = true;
                        paymentMode = data.payment_mode || 'DownPayment';
                    }
                    updatePrice();
                })
                .catch(err => console.error('Error checking DP:', err));
        }

        function clearPatientSelection() {
            document.getElementById('charge_patient_id').value = '';
            document.getElementById('charge_patient_search').value = '';
            document.getElementById('patient_confirm_card').classList.add('hidden');
            document.getElementById('charge_patient_search').parentElement.classList.remove('opacity-40', 'pointer-events-none');
            document.getElementById('charge_patient_search').focus();
            isDownpaymentPaid = false;
            updatePrice();
        }

        function unlockChargeForm() {
            document.getElementById('charge_locked_message').classList.add('hidden');
            document.getElementById('chargeForm').classList.remove('hidden');
        }

        // NEW: Check Visit History for Prenatal
        function checkVisitHistory(pid) {
            fetch('get_patient_history.php?pid=' + pid)
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        const count = parseInt(data.prenatal_count);
                        const nextVisit = count + 1;
                        
                        // Reset all styles
                        document.querySelectorAll('input[name="visit_number"]').forEach(rb => {
                            rb.disabled = false;
                            rb.checked = false;
                            const lbl = rb.closest('label');
                            if(lbl) {
                                lbl.className = "flex items-center gap-1 text-xs cursor-pointer";
                                const icon = lbl.querySelector('.fa-check-circle');
                                if(icon) icon.remove();
                            }
                        });

                        // Mark Previous as Done
                        for(let i=1; i<=count; i++) {
                                const rb = document.querySelector(`input[name="visit_number"][value="${i}"]`);
                                if(rb) {
                                    rb.disabled = true; 
                                    const lbl = rb.closest('label');
                                    if(lbl) {
                                        lbl.classList.add('text-green-600', 'font-bold', 'opacity-70', 'line-through'); 
                                        if(!lbl.querySelector('.fa-check-circle')) {
                                            lbl.innerHTML = `<i class="fas fa-check-circle text-green-500 mr-1"></i> ` + lbl.innerHTML;
                                        }
                                    }
                                }
                        }

                        // Select Current Visit
                        const currentRb = document.querySelector(`input[name="visit_number"][value="${nextVisit}"]`);
                        if(currentRb) {
                            currentRb.checked = true;
                            const lbl = currentRb.closest('label');
                            if(lbl) lbl.classList.add('text-blue-700', 'font-bold', 'bg-blue-100', 'rounded', 'px-2', 'py-1', 'border', 'border-blue-200');
                        }
                    }
                })
                .catch(e => console.error("History check failed", e));
        }

        // NEW: Auto-check package inclusions based on Service Name
        function applyPackageDefaults(serviceName) {
            const s = serviceName.toLowerCase();
            
            // ANC01 Inclusions
            if(s.includes('anc') || s.includes('antenatal')) {
                ['prenatal_iron', 'prenatal_calcium', 'prenatal_tetanus', 'prenatal_deworm', 'svc_birthplan', 'svc_dental'].forEach(id => {
                    const el = document.querySelector(`input[name="${id}"]`);
                    if(el) el.checked = true;
                });
            } 
            
            // NCP / Newborn Inclusions
            else if(s.includes('ncp') || s.includes('newborn') || s.includes('mcp') || s.includes('maternity')) {
                 if(s.includes('ncp') || s.includes('newborn')) {
                    ['ncp_vitk', 'ncp_eye', 'cord_care', 'bcg', 'hepb'].forEach(id => {
                        const el = document.querySelector(`input[name="${id}"]`);
                        if(el) el.checked = true;
                    });
                 }
            } 
            
            // Postnatal
            if(s.includes('postnatal') || s.includes('postpartum')) {
                 ['postnatal_vitA', 'postnatal_iron', 'postnatal_counsel', 'postnatal_breast'].forEach(id => {
                    const el = document.querySelector(`input[name="${id}"]`);
                    if(el) el.checked = true;
                });
            } 
            
            // Delivery
            if(s.includes('delivery') || s.includes('mcp') || s.includes('nsd')) {
                 ['einc_dry', 'einc_ssc', 'einc_cord', 'einc_breast'].forEach(id => {
                    const el = document.querySelector(`input[name="${id}"]`);
                    if(el) el.checked = true;
                });
            }
            
            // Trigger automation to fill text fields based on the checks above
            updateAutomatedFields();
        }

        // NEW: Toggle Twin Fields (Baby B)
        function toggleTwinFields(checkbox) {
            const baby2 = document.getElementById('baby2_fields');
            if(!baby2) return;
            
            if(checkbox.checked) {
                baby2.classList.remove('hidden');
                baby2.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
            } else {
                baby2.classList.add('hidden');
                baby2.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
            }
        }

        function showServiceFields() {
            const serviceType = document.getElementById('service_type').value;
            const submitBtn = document.getElementById('submit_btn');
            
            // Hide all service-specific fields and DISABLE their inputs
            document.querySelectorAll('.service-fields').forEach(field => {
                field.style.display = 'none';
                field.querySelectorAll('input, select, textarea').forEach(input => {
                    input.disabled = true;
                });
            });
            
            // Hide common fields initially
            document.getElementById('common_fields').style.display = 'none';
            
            if (serviceType) {
                // Show selected service fields and ENABLE inputs
                const selectedFields = document.getElementById(serviceType + '_fields');
                if (selectedFields) {
                    selectedFields.style.display = 'block';
                    selectedFields.querySelectorAll('input, select, textarea').forEach(input => {
                        input.disabled = false;
                    });
                    
                    // FIX: Re-evaluate Twin Fields after enabling
                    const twinToggle = selectedFields.querySelector('#is_twin_toggle');
                    if(twinToggle) toggleTwinFields(twinToggle);
                }

                // NEW: ANC02 (Labor Watch) includes ANC01 (Prenatal) fields
                if(serviceType === 'labor_watch') {
                    const prenatal = document.getElementById('prenatal_fields');
                    if(prenatal) {
                        prenatal.style.display = 'block';
                        prenatal.querySelectorAll('input, select, textarea').forEach(input =>input.disabled = false);
                    }
                }
                
                // NEW: Handle Combined View for MCP (Maternity Care Package) & NSD
                const booked = document.getElementById('booked_service_name_hidden').value.toLowerCase();
                if ((serviceType === 'delivery') && (booked.includes('mcp') || booked.includes('maternity') || booked.includes('nsd'))) {
                     const nb = document.getElementById('newborn_fields');
                     const pn = document.getElementById('postnatal_fields');
                     if(nb) {
                         nb.style.display = 'block';
                         nb.querySelectorAll('input, select, textarea').forEach(input => input.disabled = false);
                         
                         // Handle Twin logic immediately for Combined View
                         const isTwin = document.getElementById('is_twin_toggle');
                         if(isTwin) toggleTwinFields(isTwin);
                     }
                     if(pn) {
                         pn.style.display = 'block';
                         pn.querySelectorAll('input, select, textarea').forEach(input => input.disabled = false);
                     }
                }
                
                // Show common fields
                document.getElementById('common_fields').style.display = 'block';
                
                // Enable submit button
                submitBtn.disabled = false;
                submitBtn.className = 'w-full h-12 bg-primary hover:bg-pink-600 text-white font-bold rounded-xl shadow-lg shadow-pink-200 transition-all';
                submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Service Record';
            } else {
                // Disable submit button
                submitBtn.disabled = true;
                submitBtn.className = 'w-full h-12 bg-gray-300 text-gray-500 font-bold rounded-xl shadow-lg cursor-not-allowed transition-all';
                submitBtn.innerHTML = '<i class="fas fa-lock mr-2"></i>Select a Service Type to Continue';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Check hash for tab activation
            const hash = window.location.hash;
            if (hash === '#charges') {
                const chargeBtn = document.querySelector('button[onclick*="switchTab(\'charges\'"]');
                if(chargeBtn) switchTab('charges', chargeBtn);
            }

            // Setup Search for Service Delivery
            setupAutocomplete('patient_search_input', 'patient_dropdown', 'patient_id_hidden');
            // Setup Search for Charge Recording
            setupAutocomplete('charge_patient_search', 'charge_patient_dropdown', 'charge_patient_id', true);

            // AUTOMATION: Real-time Form Updates (Findings & Next Visit)
            const svcForm = document.getElementById('common_fields')?.closest('form');
            if(svcForm) {
                svcForm.addEventListener('change', updateAutomatedFields);
            }

            function updateAutomatedFields() {
                const sType = document.getElementById('service_type').value;
                const findingsField = document.querySelector('textarea[name="findings"]');
                const nextVisitField = document.querySelector('input[name="next_visit"]');
                const medsField = document.querySelector('textarea[name="meds"]');
                
                let notesParts = [];
                let medsParts = [];
                let nextVisitDays = 28; // Default

                if(sType === 'prenatal') {
                    const bp = document.querySelector('input[name="bp"]')?.value;
                    const wt = document.querySelector('input[name="weight"]')?.value;
                    const aog = parseInt(document.querySelector('input[name="aog_weeks"]')?.value || 0);
                    
                    if(document.querySelector('input[name="prenatal_iron"]')?.checked) medsParts.push("Ferrous Sulfate + Folic Acid");
                    if(document.querySelector('input[name="prenatal_calcium"]')?.checked) medsParts.push("Calcium Carbonate");
                    if(document.querySelector('input[name="prenatal_tetanus"]')?.checked) medsParts.push("Tetanus Toxoid");
                    
                    if(bp || wt || aog) {
                        notesParts.push(`Prenatal checkup performed.`);
                        if(aog) notesParts.push(`AOG: ${aog} weeks.`);
                        if(bp) notesParts.push(`BP: ${bp}.`);
                        
                         // Calculate Next Visit based on AOG (DOH Standard)
                        if(aog >= 36) nextVisitDays = 7;
                        else if(aog >= 28) nextVisitDays = 14;
                        else nextVisitDays = 28;
                        
                        notesParts.push("Patient advised on nutrition and danger signs.");
                    }
                }
                else if(sType === 'family_planning') {
                    const method = document.querySelector('select[name="fp_method_accepted"]')?.value;
                    if(method) {
                        notesParts.push(`Family Planning Counseling provided.`);
                        notesParts.push(`Method accepted: ${method}.`);
                        
                        if(method.includes('Pills')) { nextVisitDays = 28; medsParts.push("Oral Contraceptive Pills (OCP)"); notesParts.push("Pills supply given."); }
                        if(method.includes('Depo') || method.includes('Injection')) { nextVisitDays = 90; medsParts.push("DMPA Injection (150mg)"); notesParts.push("DMPA Injection administered."); }
                        if(method.includes('Implant')) { nextVisitDays = 7; medsParts.push("Progestin Subdermal Implant", "Lidocaine"); notesParts.push("Implant inserted."); }
                        if(method.includes('IUD')) { nextVisitDays = 30; notesParts.push("IUD string check scheduled."); }
                        if(method.includes('Condom')) { medsParts.push("Condoms"); }
                    }
                }
                else if(sType === 'postnatal') {
                     const uinv = document.querySelector('select[name="uterine_involution"]')?.value;
                     notesParts.push("Postnatal checkup.");
                     if(uinv) notesParts.push(`Uterus: ${uinv}.`);
                     
                     if(document.querySelector('input[name="postnatal_vitA"]')?.checked) medsParts.push("Vitamin A (200,000 IU)");
                     if(document.querySelector('input[name="postnatal_iron"]')?.checked) medsParts.push("Ferrous Sulfate");
                     
                     nextVisitDays = 28; // Standard monthly
                }
                else if(sType === 'newborn' || sType === 'delivery') {
                    const wt = document.querySelector('input[name="newborn_weight"]')?.value;
                    if(wt) notesParts.push(`Newborn Weight: ${wt}g.`);
                    
                    // Collect selected checkboxes & Meds
                    let services = [];
                    if(document.querySelector('input[name="bcg"]')?.checked) { services.push("BCG"); medsParts.push("BCG Vaccine"); }
                    if(document.querySelector('input[name="hepb"]')?.checked) { services.push("Hep B"); medsParts.push("Hepatitis B Vaccine"); }
                    if(document.querySelector('input[name="ncp_vitk"]')?.checked) { services.push("Vit K"); medsParts.push("Vitamin K (Phytomenadione)"); }
                    if(document.querySelector('input[name="ncp_eye"]')?.checked) { services.push("Eye Prophylaxis"); medsParts.push("Erythromycin Ointment"); }
                    
                    if(services.length > 0) notesParts.push("Services: " + services.join(", ") + ".");
                    
                    // Delivery Meds
                    if(sType === 'delivery') {
                         medsParts.push("Oxytocin 10IU (IM)");
                         if(document.querySelector('input[name="einc_cord"]')?.checked) notesParts.push("EINC protocols observed.");
                    }
                    
                    nextVisitDays = 28; // For next vaccine
                }

                // Update UI
                if(notesParts.length > 0) findingsField.value = notesParts.join(" ");
                
                // Update Meds (deduplicate)
                if(medsParts.length > 0) {
                    let uniqueMeds = [...new Set(medsParts)];
                    medsField.value = uniqueMeds.join(", ");
                }

                if(nextVisitDays) {
                    const d = new Date();
                    d.setDate(d.getDate() + nextVisitDays);
                    const yyyy = d.getFullYear();
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    if(nextVisitField) nextVisitField.value = `${yyyy}-${mm}-${dd}`;
                }
            }

            // WORKFLOW: Auto-select patient and service for charging if redirected from Service Delivery
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('action') === 'charge') {
                const pid = urlParams.get('pid');
                const pname = urlParams.get('pname');
                const svcType = urlParams.get('svc');
                const qty = urlParams.get('qty'); // Get Quantity

                    if(pid && pname) {
                        document.getElementById('charge_patient_id').value = pid;
                        document.getElementById('charge_patient_search').value = pname;
                        
                        // Trigger UI state for selected patient
                        document.getElementById('patient_confirm_card').classList.remove('hidden');
                        document.getElementById('confirm_patient_name').textContent = pname;
                        document.getElementById('charge_patient_search').parentElement.classList.add('opacity-40', 'pointer-events-none');
                        
                        // Check for today's downpayment
                        // Check for today's downpayment
                        if(urlParams.get('nodp') === '1') {
                            isDownpaymentPaid = false;
                            updatePrice();
                            // Auto-fill Note for Clerk
                            document.querySelector('textarea[name="charge_notes"]').value = "Charge for Twin / Baby 2 (Additional Baby)";
                        } else {
                            checkDownpayment(pid);
                        }
                        
                        // Auto-set Quantity if provided (e.g. for Twins)
                        if(qty && parseInt(qty) > 1) {
                            document.getElementById('quantity_input').value = qty;
                        }

                        // TWIN LOGIC: Inject Hidden Input for Sequence Handling
                        if(urlParams.get('twin') === '1') {
                            const form = document.getElementById('record_charge_btn')?.form;
                            if(form) {
                                let twinInput = document.createElement('input');
                                twinInput.type = 'hidden';
                                twinInput.name = 'is_twin_sequence';
                                twinInput.value = '1';
                                form.appendChild(twinInput);
                                
                                // Visual Indicator
                                const noteField = document.querySelector('textarea[name="charge_notes"]');
                                if(noteField) noteField.value += " [Twin Sequence: Charging Baby A/Mother first]";
                            }
                        }
                        
                        // Check for Twin Sequence Flag
                        if(urlParams.get('twin') === '1') {
                            document.getElementById('is_twin_sequence').value = '1';
                        }

                    // Auto-select Service (NEW: Populate locked field instead of dropdown)
                    if(svcType) {
                        // Normalize the incoming service type
                        let term = svcType.toLowerCase().replace('_', ' ').trim();
                        
                        // ONLY use generic mapping if it's NOT already a specific package code
                        const isSpecificPackage = (term.includes('anc0') || term.includes('mcp') || term.includes('ncp') || term.includes('nsd'));
                        
                        if (!isSpecificPackage) {
                            if (term.includes('prenatal')) term = 'anc01'; 
                            if (term.includes('postnatal')) term = 'nsd01';
                            if (term.includes('immunization')) term = 'ncp';
                            if (term.includes('consultation')) term = 'consultation';
                        }

                        // Fetch service data from database via AJAX
                        const chargeBtn = document.getElementById('record_charge_btn');
                        const spinner = document.getElementById('service_loading_spinner');
                        if(chargeBtn) chargeBtn.disabled = true;
                        if(spinner) spinner.classList.remove('hidden');

                        fetch('get_service_by_name.php?term=' + encodeURIComponent(term))
                            .then(response => response.json())
                            .then(data => {
                                if(data.success) {
                                    // PRICE OVERRIDE for Midwife Charging (Use Total Payment not Case Rate)
                                    let finalPrice = parseFloat(data.price);
                                    const sName = data.service_name.toUpperCase();
                                    
                                    if(sName.includes('MCP01')) finalPrice = 12500.00;
                                    else if(sName.includes('NSD01')) finalPrice = 11000.00;
                                    else if(sName.includes('NCP')) finalPrice = 5000.00;
                                    else if(sName.includes('ANC01')) finalPrice = 1500.00;
                                    else if(sName.includes('ANC02')) finalPrice = 2000.00;

                                    // Populate hidden fields
                                    document.getElementById('service_id_hidden').value = data.service_id;
                                    document.getElementById('service_price_hidden').value = finalPrice;
                                    
                                    // Display service name in locked field
                                    document.getElementById('service_display_text').textContent = data.service_name + ' - ₱' + finalPrice.toLocaleString('en-US', {minimumFractionDigits: 2});
                                    
                                    // Trigger price calculation
                                    updatePrice();
                                } else {
                                    // Fallback: Show original service type if not found
                                    document.getElementById('service_display_text').textContent = svcType + ' (Please verify)';
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching service:', error);
                                document.getElementById('service_display_text').textContent = svcType + ' (Error loading)';
                            })
                            .finally(() => {
                                if(chargeBtn) chargeBtn.disabled = false;
                                if(spinner) spinner.classList.add('hidden');
                            });
                    }

                    // Ensure tab is active
                    const chargeBtn = document.querySelector('button[onclick*="switchTab(\'charges\'"]');
                    if(chargeBtn) switchTab('charges', chargeBtn);
                }
            }
        });
    </script>
</body>
</html>