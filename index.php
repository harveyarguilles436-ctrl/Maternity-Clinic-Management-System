<?php 
include 'config.php'; 
// 1. Fetch Summary Stats
$stats_q = $conn->query("SELECT AVG(rating) as avg, COUNT(*) as count FROM ratings");
$stats = $stats_q->fetch_assoc();
$avg_rating = is_numeric($stats['avg']) ? round($stats['avg'], 1) : 0;
$total_ratings = $stats['count'];

// 2. Fetch All Ratings (Latest 15 for display)
$ratings_query = $conn->query("SELECT r.*, p.name FROM ratings r JOIN patient p ON r.patient_id = p.patient_id ORDER BY r.created_at DESC LIMIT 15");
$has_ratings = ($ratings_query && $ratings_query->num_rows > 0);

// 3. Fetch Services
$services_q = $conn->query("SELECT * FROM service_pricing WHERE is_active=1 ORDER BY service_name");
$packages = [];
$others = [];
if($services_q->num_rows > 0){
    while($s = $services_q->fetch_assoc()){
        // Categorize match Client Dashboard
        $sc = strtolower($s['service_category']);
        $sn = strtolower($s['service_name']);
        if(strpos($sc, 'package') !== false || strpos($sn, 'package') !== false || strpos($sn, 'anc') !== false || strpos($sn, 'mcp') !== false || strpos($sn, 'ncp') !== false){
            $packages[] = $s;
        } else {
            $others[] = $s;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mother Therese Maternity Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#0f766e",
                        secondary: "#134e4a",
                        accent: "#f0fdfa",
                    },
                    fontFamily: { sans: ["Manrope", "sans-serif"] }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <nav class="fixed w-full bg-white/95 backdrop-blur-md z-50 border-b border-slate-100 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-teal-500/30">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <h1 class="font-extrabold text-lg text-primary leading-tight">Mother Therese</h1>
                    <p class="text-xs text-slate-400 font-semibold tracking-widest uppercase">Maternity Clinic</p>
                </div>
            </div>
            <div class="hidden md:flex items-center gap-8 font-semibold text-sm text-slate-600">
                <a href="#home" class="hover:text-primary transition-colors">Home</a>
                <a href="#services" class="hover:text-primary transition-colors">Services</a>
                <a href="#location" class="hover:text-primary transition-colors">Location</a> </div>
            <div class="flex items-center gap-3">
                <a href="login.php" class="px-5 py-2.5 rounded-lg font-bold text-sm text-primary hover:bg-slate-50 transition-colors">Sign In</a>
                <a href="register.php" class="px-5 py-2.5 bg-primary hover:bg-teal-800 text-white rounded-lg font-bold text-sm shadow-lg shadow-teal-500/30 transition-all active:scale-95">Book Now</a>
            </div>
        </div>
    </nav>

    <section id="home" class="pt-32 pb-12 px-6 relative overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-[600px] bg-gradient-to-b from-teal-50 via-white to-transparent -z-10"></div>

        <div class="max-w-6xl mx-auto text-center relative z-10">
            <span class="inline-block py-1 px-4 rounded-full bg-teal-100 text-teal-800 text-xs font-bold uppercase tracking-wider mb-6 border border-teal-200 shadow-sm">Trusted Healthcare since 2006</span>
            
            <h1 class="text-4xl md:text-6xl font-extrabold text-slate-900 mb-6 leading-tight">
                Your Wellness is <br> 
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-teal-400">Our Nature</span>
            </h1>
            
            <p class="text-lg text-slate-500 mb-8 max-w-2xl mx-auto leading-relaxed">
                Providing compassionate, world-class maternity care for you and your baby. Experience a safe journey into motherhood with our expert team.
            </p>

            <div class="flex flex-col items-center gap-6 mb-12">
                <div class="flex gap-4">
                    <a href="register.php" class="px-8 py-3 bg-primary hover:bg-teal-800 text-white rounded-full font-bold text-base shadow-xl shadow-teal-500/30 transition-all transform hover:-translate-y-1">Book Appointment</a>
                    <a href="#location" class="px-8 py-3 bg-white text-primary border border-slate-200 rounded-full font-bold text-base shadow-sm hover:shadow-md transition-all">Visit Us</a>
                </div>
            </div>
            
            <div class="relative w-full max-w-4xl mx-auto group">
                <div class="absolute -inset-1 bg-gradient-to-r from-teal-400 to-primary rounded-[2rem] blur opacity-20"></div>
                <div class="relative rounded-[2rem] overflow-hidden shadow-2xl border-4 border-white bg-slate-100">
                    <img src="family.jpg" 
                         alt="Mother Therese Clinic Family" 
                         class="w-full h-auto object-cover transform transition duration-700 hover:scale-[1.02]"
                         style="min-height: 300px; background-color: #f0f0f0;">
                    

                </div>
            </div>
        </div>
    </section>

    <section id="services" class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-800 mb-3">Our Core Services</h2>
                <p class="text-slate-500">Comprehensive care tailored to every stage of pregnancy</p>
            </div>

            <!-- PACKAGES -->
            <div class="mb-12">
                <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2"><i class="fas fa-box-open text-primary"></i> Maternity Packages</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($packages as $svc): 
                        $icon = 'fa-gift';
                        if(stripos($svc['service_name'], 'Delivery') !== false) $icon = 'fa-baby-carriage';
                        if(stripos($svc['service_name'], 'Born') !== false) $icon = 'fa-baby';
                        
                        // Extract Code for Lookup (e.g. "ANC01")
                        $parts = explode(' - ', $svc['service_name']);
                        $code = isset($parts[1]) ? trim($parts[0]) : ''; // If format is CODE - Name
                        
                        // Fallback Regex if explode fails
                        if(empty($code)){
                             if(preg_match('/^([A-Z0-9]+)\s*-/', $svc['service_name'], $m)) $code = $m[1];
                        }

                        // Remove Code Prefix for Display
                        $display_name = preg_replace('/^[A-Z0-9]+\s*-\s*/', '', $svc['service_name']);

                        // PRICE OVERRIDE MAP (Case Rate vs Total Payment)
                        $price_overrides = [
                            'MCP01' => 12500.00,
                            'NSD01' => 11000.00,
                            'NCP'   => 5000.00,
                            'ANC01' => 1500.00,
                            'ANC02' => 2000.00
                        ];

                        // Use Override if exists, else DB price
                        $display_price = isset($price_overrides[$code]) ? $price_overrides[$code] : $svc['price'];
                    ?>
                    <div onclick="openServiceModal('<?php echo $code; ?>', '<?php echo addslashes($display_name); ?>', '<?php echo $svc['price']; ?>')" class="p-6 rounded-2xl bg-teal-50 hover:bg-white hover:shadow-xl transition-all duration-300 border border-teal-100 text-center group flex flex-col relative overflow-hidden cursor-pointer transform hover:-translate-y-1">
                        <div class="absolute top-0 right-0 p-4 opacity-5 text-teal-600"><i class="fas <?php echo $icon; ?> text-8xl"></i></div>
                        
                        <div class="w-16 h-16 mx-auto bg-white rounded-full flex items-center justify-center text-teal-600 text-2xl shadow-sm mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>

                        <h3 class="text-lg font-bold text-slate-800 mb-2 relative z-10"><?php echo htmlspecialchars($display_name); ?></h3>
                        
                        <!-- Description Hidden on Card, shown in Modal -->
                        
                        <div class="mt-auto relative z-10 pt-4">
                            <span class="inline-block bg-white text-primary font-bold px-4 py-2 rounded-lg shadow-sm border border-teal-100 group-hover:bg-primary group-hover:text-white transition-colors">
                                ₱<?php echo number_format($display_price, 2); ?>
                            </span>
                            <p class="text-[10px] text-slate-400 mt-2 uppercase tracking-wide font-semibold">Click for Details</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>


        </div>
    </section>

    <!-- NEW TESTIMONIALS SECTION -->
    <section id="reviews" class="py-16 bg-slate-50 border-t border-slate-200">
        <div class="max-w-full px-6">
            <div class="text-center mb-8">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <div class="flex text-yellow-400 text-lg">
                        <?php 
                        $full_stars = floor($avg_rating);
                        $half_star = ($avg_rating - $full_stars) >= 0.5;
                        for($i=1; $i<=5; $i++): 
                            if($i <= $full_stars) echo '<i class="fas fa-star"></i>';
                            elseif($half_star && $i == $full_stars + 1) echo '<i class="fas fa-star-half-alt"></i>';
                            else echo '<i class="far fa-star"></i>';
                        endfor; 
                        ?>
                    </div>
                    <span class="text-slate-800 font-bold text-lg"><?php echo $avg_rating; ?>/5</span>
                </div>
                <p class="text-slate-500 text-sm">Based on <?php echo $total_ratings; ?> verified reviews</p>
            </div>

            <?php if($has_ratings): ?>
            <!-- Horizontal Scroll Container -->
            <div class="flex overflow-x-auto gap-6 pb-8 px-4 md:px-20 no-scrollbar snap-x snap-mandatory">
                <?php while($row = $ratings_query->fetch_assoc()): ?>
                <div class="min-w-[300px] md:min-w-[350px] bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col relative snap-center group hover:shadow-md transition-shadow">
                    
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex text-yellow-400 text-xs">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="<?php echo ($i <= $row['rating']) ? 'fas' : 'far'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <i class="fas fa-quote-right text-teal-100 text-2xl"></i>
                    </div>
                    
                    <p class="text-slate-600 mb-4 text-xs italic leading-relaxed line-clamp-3">"<?php echo !empty($row['review_text']) ? htmlspecialchars($row['review_text']) : "Positive feedback given."; ?>"</p>
                    
                    <div class="flex items-center gap-3 mt-auto pt-4 border-t border-slate-50/50">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-teal-100 to-teal-50 flex items-center justify-center text-teal-700 font-bold text-xs ring-2 ring-white">
                            <?php echo substr($row['name'], 0, 1); ?>
                        </div>
                        <div>
                            <p class="font-bold text-slate-800 text-xs truncate max-w-[150px]"><?php echo htmlspecialchars($row['name']); ?></p>
                            <p class="text-[10px] text-slate-400 font-medium truncate max-w-[150px]"><?php echo htmlspecialchars($row['service_name']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                
                <!-- 'See All' Card acting as padding/link -->
                <div class="min-w-[150px] flex items-center justify-center snap-center">
                    <a href="register.php" class="text-center group">
                        <div class="w-12 h-12 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 group-hover:text-primary group-hover:border-primary transition-all mb-2 mx-auto">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                        <span class="text-xs font-bold text-slate-500 group-hover:text-primary">Book Now</span>
                    </a>
                </div>
            </div>
            
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-slate-400 text-sm">No reviews yet. Be the first!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="location" class="py-20 bg-white border-t border-slate-200">
        <div class="max-w-6xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-800 mb-3">Visit Our Clinic</h2>
                <p class="text-slate-500">Accessible and convenient location for all your maternity needs.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <div class="lg:col-span-4 bg-white p-8 rounded-3xl shadow-lg border border-slate-100">
                    <h3 class="text-xl font-bold text-primary mb-6 flex items-center gap-2">
                        <i class="fas fa-map-marked-alt"></i> Clinic Info
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Address</p>
                            <p class="text-slate-800 font-medium">97 B.S. Aquino Avenue,<br>Tangos, Baliwag City,<br>3006 Bulacan</p>
                        </div>
                        
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Clinic Hours</p>
                            <div class="flex justify-between text-sm text-slate-600 border-b border-slate-100 pb-2 mb-2">
                                <span>Mon - Sat</span>
                                <span class="font-bold">8:00 AM - 5:00 PM</span>
                            </div>
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>Emergencies</span>
                                <span class="font-bold text-red-500">Open 24/7</span>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Contact Us</p>
                            <p class="text-xl font-bold text-slate-800">0917 843 4589</p>
                            <p class="text-sm text-slate-500">Call or Text for inquiries</p>
                        </div>

                        <a href="https://www.google.com/maps/search/?api=1&query=97+B.S.+Aquino+Avenue,+Tangos,+Baliwag+City,+3006+Bulacan" 
                           target="_blank" 
                           class="block w-full py-3 bg-primary hover:bg-teal-800 text-white font-bold text-center rounded-xl shadow-md transition-all transform active:scale-95">
                            <i class="fas fa-directions mr-2"></i> Get Directions
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-8 h-full min-h-[400px] relative rounded-3xl overflow-hidden shadow-lg border-4 border-white">
                    <iframe 
                        width="100%" 
                        height="100%" 
                        style="border:0; min-height: 450px;" 
                        loading="lazy" 
                        allowfullscreen 
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://maps.google.com/maps?q=97%20B.S.%20Aquino%20Avenue%2C%20Tangos%2C%20Baliwag%20City%2C%203006%20Bulacan&t=&z=15&ie=UTF8&iwloc=&output=embed">
                    </iframe>
                </div>

            </div>
        </div>
    </section>

    <footer class="bg-slate-900 text-slate-400 py-8 text-center text-xs">
        <div class="mb-4 flex justify-center gap-4">
            <span><i class="fas fa-phone mr-1"></i> 0917 843 4589</span>
            <span><i class="fas fa-envelope mr-1"></i> contact@mothertherese.com</span>
        </div>
        <p>&copy; 2026 Mother Therese Mothers Clinic. All rights reserved.</p>
    </footer>

    <!-- Service Details Modal -->
    <div id="serviceModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Background backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeServiceModal()"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl border border-slate-200">
                    
                    <!-- Modal Header -->
                    <div class="bg-primary px-6 py-4 flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center gap-2" id="modal-title">
                            <i class="fas fa-file-medical-alt"></i> <span id="modal_svc_name">Service Name</span>
                        </h3>
                        <button type="button" class="text-white/80 hover:text-white transition-colors" onclick="closeServiceModal()">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
                        <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex gap-3">
                            <div class="text-yellow-600"><i class="fas fa-info-circle text-xl"></i></div>
                            <div class="flex-1">
                                <div class="text-sm text-yellow-800 font-bold mb-1" id="modal_desc_main">
                                    Full coverage description goes here.
                                </div>
                                <div class="mt-2 flex items-center gap-2 text-sm text-yellow-900 bg-yellow-100/50 p-2 rounded border border-yellow-200 w-fit">
                                    <span class="font-bold">PhilHealth Case Rate:</span>
                                    <span class="font-mono font-bold" id="modal_case_rate">₱0.00</span>
                                </div>
                                <div class="text-xs text-yellow-700 mt-2 border-t border-yellow-200 pt-2">
                                    <span class="font-bold">Understanding Our Pricing:</span>
                                    <ul class="list-disc pl-4 mt-1 space-y-1">
                                        <li><strong>Case Rate:</strong> The amount PhilHealth pays for the package (internal insurance figure).</li>
                                        <li><strong>Total Payment:</strong> The actual amount you pay out-of-pocket if you are paying cash (Without PhilHealth).</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing Table -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-0 border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                            
                            <!-- With PhilHealth -->
                            <div class="bg-teal-50/50 p-6 border-b md:border-b-0 md:border-r border-slate-200">
                                <h4 class="font-bold text-teal-800 text-lg mb-4 flex items-center gap-2">
                                    <i class="fas fa-check-circle"></i> With PhilHealth
                                </h4>
                                <div class="space-y-4">
                                    <div class="bg-white p-4 rounded-lg border border-teal-100 shadow-sm text-center">
                                        <p class="text-xs font-bold text-teal-600 uppercase tracking-wide mb-1">Total Out of Pocket</p>
                                        <p class="text-3xl font-extrabold text-teal-700">₱0.00</p>
                                        <p class="text-xs text-teal-500 font-bold mt-1">NO BALANCE BILLING</p>
                                    </div>
                                    <p class="text-xs text-slate-500 text-center italic">* Subject to PhilHealth eligibility and approval.</p>
                                </div>
                            </div>

                            <!-- Without PhilHealth -->
                            <div class="bg-slate-50 p-6">
                                <h4 class="font-bold text-slate-700 text-lg mb-4 flex items-center gap-2">
                                    <i class="fas fa-times-circle text-slate-400"></i> Without PhilHealth
                                </h4>
                                <div class="space-y-3">
                                    <div class="breakdown-list space-y-2 text-sm text-slate-600" id="modal_breakdown">
                                        <!-- Dynamic Breakdown -->
                                    </div>
                                    <div class="border-t border-slate-200 pt-3 mt-3">
                                        <div class="flex justify-between items-center font-bold text-slate-800">
                                            <span>TOTAL PAYMENT</span>
                                            <span class="text-xl text-primary" id="modal_total_non_ph">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <p class="text-xs text-slate-400 italic">Prices subject to change without prior notice.</p>
                        <a href="register.php" class="inline-flex w-full justify-center rounded-lg bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-teal-800 transition-all sm:w-auto">
                            Book Appointment Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const serviceData = {
            'MCP01': {
                desc: 'ROUTINE OBSTETRIC CARE INCLUDING PRE-NATAL, VAGINAL DELIVERY, NEWBORN SERVICES, AND POST PARTUM CARE',
                non_ph_total: '12,500.00',
                case_rate: '15,600.00',
                breakdown: [
                    { label: 'Professional Fee', amount: '4,500.00' },
                    { label: 'Birthing Room', amount: '2,000.00' },
                    { label: 'Room Accommodation', amount: '1,500.00' },
                    { label: 'Drugs / Medicine', amount: '2,500.00' },
                    { label: 'Supplies', amount: '2,000.00' }
                ]
            },
            'NSD01': {
                desc: 'ROUTINE OBSTETRIC CARE INCLUDING ANTEPARTUM CARE, VAGINAL DELIVERY, AND POST PARTUM CARE',
                non_ph_total: '11,000.00',
                case_rate: '12,675.00',
                breakdown: [
                    { label: 'Professional Fee', amount: '4,500.00' },
                    { label: 'Birthing Room', amount: '2,000.00' },
                    { label: 'Room Accommodation', amount: '1,500.00' },
                    { label: 'Drugs / Medicine', amount: '1,500.00' },
                    { label: 'Supplies', amount: '1,500.00' }
                ]
            },
            'NCP': {
                desc: 'EXPANDED NEWBORN CARE PACKAGES INCLUDING SUPPLIES FOR ESSENTIAL NEWBORN CARE (Vitamin K, Eye prophylaxis, Hepatitis B Vaccine, BCG Vaccine, Cord Care), Expanded Newborn Screening Test, Newborn Hearing Screening Test',
                non_ph_total: '5,000.00',
                case_rate: '5,752.50',
                breakdown: [
                    { label: 'Professional Fee', amount: '500.00' },
                    { label: 'Room Accommodation', amount: '1,000.00' },
                    { label: 'Drugs / Medicine & Supplies', amount: '3,500.00' }
                ]
            },
            'ANC01': {
                desc: 'ANTENATAL CARE OVER ESSENTIAL HEALTH SERVICES THAT WOMAN ABOUT TO GIVE BIRTH DURING ANTENATAL PERIOD AT LEAST 4 PRE-NATAL CHECK-UPS/VISIT WITH THE LAST ONE DURING THE LAST TRIMESTER OF PREGNANCY',
                non_ph_total: '1,500.00',
                case_rate: '2,925.00',
                breakdown: [
                    { label: 'Professional Fee', amount: '800.00' },
                    { label: 'Supplies', amount: '700.00' }
                ]
            },
            'ANC02': {
                desc: 'ANTENATAL CARE SERVICES WITH INTRAPARTUM MONITORING OR LABOR WATCH WITHOUT DELIVERY',
                non_ph_total: '2,000.00',
                case_rate: '4,192.50',
                breakdown: [
                    { label: 'Professional Fee', amount: '1,200.00' },
                    { label: 'Supplies', amount: '800.00' }
                ]
            }
        };

        function openServiceModal(code, name, price) {
            document.getElementById('serviceModal').classList.remove('hidden');
            document.getElementById('modal_svc_name').innerText = name;
            
            const data = serviceData[code];
            if(data) {
                document.getElementById('modal_desc_main').innerText = data.desc;
                document.getElementById('modal_case_rate').innerText = '₱' + data.case_rate;
                document.getElementById('modal_total_non_ph').innerText = '₱' + data.non_ph_total;
                
                // Build Breakdown
                let html = '';
                data.breakdown.forEach(item => {
                    html += `
                        <div class="flex justify-between">
                            <span>${item.label}</span>
                            <span class="font-bold">₱${item.amount}</span>
                        </div>
                    `;
                });
                document.getElementById('modal_breakdown').innerHTML = html;
            } else {
                // Fallback for unknown codes
                document.getElementById('modal_desc_main').innerText = 'Standard service rate applies.';
                document.getElementById('modal_case_rate').innerText = '₱-';
                document.getElementById('modal_total_non_ph').innerText = '₱' + parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2});
                document.getElementById('modal_breakdown').innerHTML = '<div class="text-center text-muted">See clinic for detailed breakdown.</div>';
            }
        }

        function closeServiceModal() {
            document.getElementById('serviceModal').classList.add('hidden');
        }
    </script>
</body>
</html>