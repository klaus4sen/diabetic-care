<?php
// =======================================================
// PHP LOGIC: CUMULATIVE MEAL CALCULATOR (Sums up multiple items)
// =======================================================
session_start();

$error_message = null;
$food_item = null;
$brief_advice = "Monitor blood sugar closely."; 
$timing_info = "N/A"; 
$mgdl_impact = "N/A"; 

// -------------------------------------------------------
// 1. HELPER FUNCTIONS
// -------------------------------------------------------

// استخراج الكمية من جزء النص
function extractQuantity($input) {
    if (preg_match('/(\d+(\.\d+)?)/', $input, $matches)) {
        $val = floatval($matches[1]);
        return ($val > 0) ? $val : 1.0;
    }
    if (stripos($input, 'double') !== false || stripos($input, ' two ') !== false) return 2.0;
    if (stripos($input, 'triple') !== false || stripos($input, ' three ') !== false) return 3.0;
    if (stripos($input, 'half') !== false) return 0.5;
    return 1.0; 
}

// البحث عن كلمة مفتاحية
function matchCategory($input, $keywords) {
    foreach ($keywords as $word) {
        if (stripos($input, $word) !== false) {
            return true;
        }
    }
    return false;
}

// -------------------------------------------------------
// 2. FOOD LISTS
// -------------------------------------------------------

// HIGH IMPACT (60-90 mg/dL per serving base)
$high_keywords = [
    'DONUT', 'DOUNT', 'DOUGHNUT', 'CAKE', 'COOKIE', 'BROWNIE', 'PIE', 'PASTRY',
    'CHOCOLATE', 'CANDY', 'HONEY', 'SYRUP', 'SUGAR', 'ICE CREAM',
    'SODA', 'COKE', 'PEPSI', 'SPRITE', 'JUICE', 'SMOOTHIE', 'MILKSHAKE',
    'WHITE BREAD', 'TOAST', 'BAGEL', 'BUN', 'SANDWICH', 'BURGER', 'HAMBURGER', 'PIZZA',
    'RICE', 'PASTA', 'SPAGHETTI', 'LASAGNA', 'FRIES', 'POTATO', 'CHIPS', 'CORN'
];

// MODERATE IMPACT (25-45 mg/dL per serving base)
$moderate_keywords = [
    'MILK', 'LATTE', 'CAPPUCCINO', 'YOGURT',
    'BANANA', 'APPLE', 'ORANGE', 'GRAPE', 'PEAR', 'PEACH', 'MANGO', 'PINEAPPLE', 'FRUIT',
    'OAT', 'OATMEAL', 'GRANOLA', 'BROWN RICE', 'QUINOA', 'BEAN', 'LENTIL', 'HUMMUS'
];

// LOW IMPACT (5-15 mg/dL per serving base)
$low_keywords = [
    'LETTUCE', 'SPINACH', 'SALAD', 'CUCUMBER', 'TOMATO', 'BROCCOLI', 'CAULIFLOWER', 
    'CARROT', 'AVOCADO', 'BERRY', 'STRAWBERRY',
    'EGG', 'CHICKEN', 'MEAT', 'BEEF', 'STEAK', 'FISH', 'TUNA', 'SALMON',
    'CHEESE', 'CREAM', 'BUTTER', 'NUT', 'ALMOND', 'WALNUT', 'PEANUT'
];

// ZERO IMPACT (0 mg/dL)
$zero_keywords = [
    'WATER', 'H2O', 'ICE', 'COFFEE', 'TEA', 'DIET', 'ZERO', 'OIL', 'VINEGAR', 'SALT'
];

// -------------------------------------------------------
// 3. REQUEST PROCESSING
// -------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_input = $_POST['food'] ?? '';
    $food_item = htmlspecialchars($food_input); 

    if (empty($food_input)) {
        $error_message = 'Please enter a food item.';
    } else {
        
        // A. SPLIT THE INPUT (by "and", ",", "+", "&")
        $food_segments = preg_split('/(\band\b|,|\+|&)/i', $food_input);
        
        $total_min_rise = 0;
        $total_max_rise = 0;
        $items_found = 0;
        $highest_category_found = 0; 

        foreach ($food_segments as $segment) {
            $segment = trim($segment);
            if (empty($segment)) continue;

            $qty = extractQuantity($segment);
            $found_in_segment = false;

            if (matchCategory($segment, $high_keywords)) {
                $total_min_rise += (60 * $qty);
                $total_max_rise += (90 * $qty);
                $highest_category_found = max($highest_category_found, 3);
                $found_in_segment = true;
            } 
            elseif (matchCategory($segment, $moderate_keywords)) {
                $total_min_rise += (25 * $qty);
                $total_max_rise += (45 * $qty);
                $highest_category_found = max($highest_category_found, 2);
                $found_in_segment = true;
            } 
            elseif (matchCategory($segment, $low_keywords)) {
                $total_min_rise += (5 * $qty);
                $total_max_rise += (15 * $qty);
                $highest_category_found = max($highest_category_found, 1);
                $found_in_segment = true;
            } 
            elseif (matchCategory($segment, $zero_keywords)) {
                $found_in_segment = true;
            }

            if ($found_in_segment) {
                $items_found++;
            }
        }

        // B. DECISION
        if ($items_found > 0) {
            $mgdl_impact = "+{$total_min_rise} to +{$total_max_rise} mg/dL";
            
            if ($highest_category_found == 3) {
                $timing_info = "30 - 60 mins (Fast Spike)";
                $brief_advice = "High carb load detected. Consider insulin timing or activity.";
            } elseif ($highest_category_found == 2) {
                $timing_info = "1 - 2 hours";
                $brief_advice = "Moderate rise. Good balance.";
            } elseif ($highest_category_found == 1) {
                $timing_info = "2 - 3 hours (Slow)";
                $brief_advice = "Minimal impact. Healthy choice.";
            } else {
                $timing_info = "N/A";
                $brief_advice = "No impact on blood sugar.";
            }

        } else {
            // C. AI FALLBACK
            $username = 'Mahmoud';
            $password = 'Mahmoud1424';
            $ollama_url = 'http://192.168.100.3:9000/api/generate'; 
            $model_name = 'llama3:8b-instruct-q2_K'; 

            $prompt = "Food: '{$food_input}'. Estimate total glucose rise mg/dL. Format: Range mg/dL##Time##Advice";

            $ollama_payload = json_encode([
                'model' => $model_name,
                'prompt' => $prompt,
                "options" => ["temperature" => 0.0],
                'stream' => false
            ]);

            $ch = curl_init($ollama_url);
            $auth_header = "Authorization: Basic " . base64_encode("{$username}:{$password}");

            curl_setopt_array($ch, [
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', $auth_header],
                CURLOPT_POSTFIELDS => $ollama_payload,
                CURLOPT_TIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $ollama_response = json_decode($response, true);
                $raw_text = $ollama_response['response'] ?? 'N/A##N/A##N/A';
                $parts = explode('##', $raw_text, 3);
                $mgdl_impact = trim($parts[0] ?? 'Unknown');
                $timing_info = trim($parts[1] ?? 'Unknown');
                $brief_advice = trim($parts[2] ?? 'Monitor sugar.');
            } else {
                $mgdl_impact = "Unrecognized Food";
                $timing_info = "N/A";
                $brief_advice = "Could not calculate total. Please check spelling.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" href="images/icon.png" type="image/png">
<title>DiabetesCare - Cumulative Calculator</title>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=Edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self';">
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/font-awesome.min.css">
<link rel="stylesheet" href="css/aos.css">
<link rel="stylesheet" href="css/owl.carousel.min.css">
<link rel="stylesheet" href="css/owl.theme.default.min.css">
<link rel="stylesheet" href="css/templatemo-digital-trend.css">
</head>
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
          <i class="fa fa-heartbeat"></i>
          DiabetesCare
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item"><a href="#about" class="nav-link smoothScroll">About</a></li>
                <li class="nav-item"><a href="#project" class="nav-link smoothScroll">Food Impact</a></li>
                
                <li class="nav-item"><a href="project-detail.html" class="nav-link">Explorer</a></li>
                
                <li class="nav-item"><a href="blog.html" class="nav-link">Tips</a></li>
                <li class="nav-item"><a href="contact.html" class="nav-link contact">Ask AI</a></li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero hero-bg d-flex justify-content-center align-items-center">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 col-md-10 col-12 d-flex flex-column justify-content-center align-items-center">
                  <div class="hero-text">
                       <h1 class="text-white" data-aos="fade-up">
                         Meal Calculator & Predictor
                       </h1>
                       <div class="mt-4 w-100" data-aos="fade-up" data-aos-delay="200">
                         <label class="text-white">Enter your full meal (e.g. "1 banana and 1 hamburger")</label>
                         <form action="" method="POST" id="food-prediction-form"> 
                            <input type="text" id="food-input" name="food" class="form-control" placeholder="Type meal here..." oninput="sanitizeInput(this)" required>
                            <button type="submit" class="custom-btn btn-bg btn mt-3" data-aos="fade-up" data-aos-delay="100">
                                Calculate Total Impact
                            </button>
                         </form>
                         
                         <div id="prediction-result" class="mt-4">
                         <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                             <?php if ($error_message): ?>
                                 <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                             <?php else: ?>
                                 <?php
                                     // Smart Color Logic based on Total Rise
                                     $card_color = '#28a745'; // Green
                                     
                                     if (preg_match('/(\d+)/', $mgdl_impact, $m)) {
                                         $val = intval($m[1]);
                                         if ($val >= 100) { 
                                             $card_color = '#8b0000'; // Dark Red (Very High)
                                         } elseif ($val >= 60) {
                                             $card_color = '#dc3545'; // Red
                                         } elseif ($val >= 30) {
                                             $card_color = '#ffc107'; // Yellow
                                         }
                                     }
                                     if (strpos($mgdl_impact, '+0') !== false) $card_color = '#28a745';
                                     if (strpos($mgdl_impact, 'Unrecognized') !== false) $card_color = '#6c757d';
                                 ?>
                                 
                                 <div class="card shadow-lg border-0" style="border-left: 10px solid <?php echo $card_color; ?> !important;">
                                     <div class="card-body text-left">
                                         <h5 class="card-title text-dark">
                                             Total Analysis for: <strong><?php echo $food_item; ?></strong>
                                         </h5>
                                         <hr>
                                         <div class="row">
                                             <div class="col-md-6">
                                                 <p class="mb-1 text-muted"><small>TOTAL PREDICTED RISE</small></p>
                                                 <h3 style="color: <?php echo $card_color; ?>;">
                                                     <i class="fa fa-line-chart"></i> <?php echo htmlspecialchars($mgdl_impact); ?>
                                                 </h3>
                                             </div>
                                             <div class="col-md-6">
                                                 <p class="mb-1 text-muted"><small>ESTIMATED PEAK</small></p>
                                                 <h4 class="text-dark">
                                                     <i class="fa fa-clock-o"></i> <?php echo htmlspecialchars($timing_info); ?>
                                                 </h4>
                                             </div>
                                         </div>
                                         <div class="mt-3 bg-light p-3 rounded">
                                             <p class="mb-0 text-dark">
                                                 <i class="fa fa-medkit text-info"></i> 
                                                 <strong>Advice:</strong> <?php echo htmlspecialchars($brief_advice); ?>
                                             </p>
                                         </div>
                                         <p class="mt-2 text-muted" style="font-size: 0.75rem;">
                                             *Cumulative calculation based on detected items. Consult a doctor.
                                         </p>
                                     </div>
                                 </div>
                             <?php endif; ?>
                         <?php endif; ?>
                         </div>
                         </div>
                  </div>
            </div>
            <div class="col-lg-6 col-12">
              <div class="hero-image" data-aos="fade-up" data-aos-delay="300">
                <img src="images/working-girl.png" class="img-fluid" alt="AI Health Assistant">
              </div>
            </div>
        </div>
    </div>
</section>

<section class="about section-padding pb-0" id="about">
  <div class="container">
       <div class="row">
            <div class="col-lg-7 mx-auto col-md-10 col-12">
                 <div class="about-info">
                      <h2 class="mb-4" data-aos="fade-up">The smart way to <strong>predict blood sugar</strong></h2>
                      <p class="mb-0" data-aos="fade-up">GlucoPredict calculates cumulative meal impact. Example: "1 burger and 1 soda".</p>
                 </div>
                 <div class="about-image" data-aos="fade-up" data-aos-delay="200"><img src="images/office.png" class="img-fluid" alt="AI health lab"></div>
            </div>
       </div>
  </div>
</section>

<footer class="site-footer">
  <div class="container">
    <div class="row">
      <div class="col-lg-5 mx-lg-auto col-md-8 col-10"><h1 class="text-white">Predict. Prevent. <strong>Protect.</strong></h1></div>
      <div class="col-lg-4 mx-lg-auto text-center col-md-8 col-12"><p class="copyright-text">© 2026 DiabetesCare</p></div>
    </div>
  </div>
</footer>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/aos.js"></script>
<script src="js/owl.carousel.min.js"></script>
<script src="js/smoothscroll.js"></script>
<script src="js/custom.js"></script>
<script>
function sanitizeInput(input){
    input.value = input.value.replace(/[^a-zA-Z0-9\u0600-\u06FF ,.+\-&]/g, '');
}
</script>
</body>
</html>