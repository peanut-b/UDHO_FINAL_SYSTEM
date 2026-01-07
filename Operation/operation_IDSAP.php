<?php
// Database connection
$servername = "localhost";
$username = "u198271324_admin";
$password = "Udhodbms01";
$dbname = "u198271324_udho_db";

session_start();
date_default_timezone_set('Asia/Manila');
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to safely fetch data
function getData($conn, $sql) {
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Helper function to properly decode survey answers
function decodeSurveyAnswers($jsonString) {
    // First, decode the outer JSON
    $decoded = json_decode($jsonString, true);
    
    if ($decoded === null) {
        // Try to fix common JSON issues
        $jsonString = stripslashes($jsonString);
        $decoded = json_decode($jsonString, true);
    }
    
    if ($decoded === null) {
        return [];
    }
    
    // Check different possible structures
    
    // Structure 1: Direct answers (already an array)
    if (isset($decoded['ud-code']) || isset($decoded['hh-surname'])) {
        return $decoded;
    }
    
    // Structure 2: Outer wrapper with 'answers' key
    if (isset($decoded['answers'])) {
        // Check if 'answers' is already an array
        if (is_array($decoded['answers'])) {
            return $decoded['answers'];
        } else {
            // 'answers' is a JSON string, decode it again
            $innerAnswers = json_decode($decoded['answers'], true);
            return is_array($innerAnswers) ? $innerAnswers : [];
        }
    }
    
    // Structure 3: Indexed array format [0=>"date", 1=>"time", 2=>answers_array]
    if (isset($decoded[2]) && is_array($decoded[2])) {
        return $decoded[2];
    }
    
    // Structure 4: Try to decode as a string (escaped JSON)
    if (is_string($decoded)) {
        $innerDecoded = json_decode($decoded, true);
        return is_array($innerDecoded) ? $innerDecoded : [];
    }
    
    // Return whatever we have
    return $decoded;
}

// Handle Export Functionality
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    exportToExcel($conn);
    exit;
}

// Handle Summary View - Modified to be fetched via AJAX
if (isset($_GET['view']) && $_GET['view'] == 'summary') {
    displaySurveySummary($conn);
    exit;
}

// Export to Excel Function
function exportToExcel($conn) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="IDSAP_Survey_Data_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "UD Code\tTAG Number\tHousehold Head\tAge\tComplete Address\tBarangay\tSurvey Type\tEnumerator\tDate Surveyed\n";
    
    $sql = "SELECT * FROM survey_responses ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        
        // FIXED: Properly decode the nested JSON structure
        $temp = json_decode($row['answers'], true);
        
        // Handle both indexed and associative arrays
        if (isset($temp[2]) && is_array($temp[2])) {
            $answers = $temp[2];
        } elseif (isset($temp['answers'])) {
            $answers = $temp['answers'];
        } else {
            $answers = $temp;
        }
        
        // Extract household head name
        $hhSurname = $answers['hh-surname'] ?? '';
        $hhFirstname = $answers['hh-firstname'] ?? '';
        $hhMiddlename = $answers['hh-middlename'] ?? '';
        $hhMi = $answers['hh-mi'] ?? '';
        $name = trim("$hhFirstname " . ($hhMi ? "$hhMi. " : "") . "$hhMiddlename $hhSurname");
        
        if (empty(trim($name))) {
            $name = $answers['full_name'] ?? $answers['name'] ?? 'Unnamed Household';
        }
        
        // Extract age
        $age = $answers['hh-age'] ?? 'N/A';
        
        // Extract address
        $houseNo = $answers['house-no'] ?? '';
        $lotNo = $answers['lot-no'] ?? '';
        $building = $answers['building'] ?? '';
        $block = $answers['block'] ?? '';
        $street = $answers['street'] ?? '';
        $completeAddress = trim("$houseNo $lotNo $building $block $street");
        
        // Survey type
        $surveyType = $answers['survey-type'] ?? 'Not specified';
        $otherSurveyType = $answers['other-survey-type'] ?? '';
        if ($otherSurveyType) {
            $surveyType .= " ($otherSurveyType)";
        }
        
        // Date surveyed - use metadata from allData
        $surveyDate = $temp[0] ?? $temp['survey_date'] ?? '';
        $surveyTime = $temp[1] ?? $temp['survey_time'] ?? '';
        
        if (!empty($surveyDate) && !empty($surveyTime)) {
            $surveyDateTime = date('M j, Y g:i A', strtotime($surveyDate . ' ' . $surveyTime));
        } else {
            $surveyDateTime = date('M j, Y g:i A', strtotime($row['created_at']));
        }
        
        // Output tab-separated values
        echo $row['ud_code'] . "\t";
        echo $row['tag_number'] . "\t";
        echo $name . "\t";
        echo $age . "\t";
        echo $completeAddress . "\t";
        echo $row['barangay'] . "\t";
        echo $surveyType . "\t";
        echo $row['enumerator_name'] . "\t";
        echo $surveyDateTime . "\n";
    }
    exit;
}

// Survey Summary Function - Modified to return JSON for modal
function displaySurveySummary($conn) {
    // Get overall statistics
    $totalSurveys = getData($conn, "SELECT COUNT(*) as total FROM survey_responses")[0]['total'];
    $todaySurveys = getData($conn, "SELECT COUNT(*) as total FROM survey_responses WHERE DATE(created_at) = CURDATE()")[0]['total'];
    $totalBarangays = count(getData($conn, "SELECT DISTINCT barangay FROM survey_responses"));
    
    // Get surveys by type
    $surveysByType = [];
    $allSurveys = getData($conn, "SELECT answers FROM survey_responses");
    
    foreach ($allSurveys as $survey) {
        $answers = decodeSurveyAnswers($survey['answers']);
        
        $surveyType = $answers['survey-type'] ?? 'Unknown';
        $otherType = $answers['other-survey-type'] ?? '';
        
        if (!empty($otherType)) {
            $surveyType = "OTHERS ($otherType)";
        }
        
        if (!isset($surveysByType[$surveyType])) {
            $surveysByType[$surveyType] = 0;
        }
        $surveysByType[$surveyType]++;
    }
    
    // Get surveys by barangay
    $surveysByBarangay = getData($conn, 
        "SELECT barangay, COUNT(*) as count 
         FROM survey_responses 
         GROUP BY barangay 
         ORDER BY count DESC"
    );
    
    // Get membership statistics
    $membershipStats = [
        'pagibig' => 0,
        'sss' => 0,
        'philhealth' => 0,
        'none' => 0
    ];
    
    foreach ($allSurveys as $survey) {
        $answers = decodeSurveyAnswers($survey['answers']);
        
        // Check both 'on' string and 1 integer values
        if (isset($answers['pagibig']) && ($answers['pagibig'] === 'on' || $answers['pagibig'] == 1)) $membershipStats['pagibig']++;
        if (isset($answers['sss']) && ($answers['sss'] === 'on' || $answers['sss'] == 1)) $membershipStats['sss']++;
        if (isset($answers['philhealth']) && ($answers['philhealth'] === 'on' || $answers['philhealth'] == 1)) $membershipStats['philhealth']++;
        if (isset($answers['none-fund']) && ($answers['none-fund'] === 'on' || $answers['none-fund'] == 1)) $membershipStats['none']++;
    }
    
    // Get recent surveys
    $recentSurveys = getData($conn, 
        "SELECT sr.* 
         FROM survey_responses sr 
         ORDER BY created_at DESC 
         LIMIT 5"
    );
    
    $recentSurveysFormatted = [];
    foreach ($recentSurveys as $survey) {
        $answers = decodeSurveyAnswers($survey['answers']);
        
        $hhFirstname = $answers['hh-firstname'] ?? '';
        $hhSurname = $answers['hh-surname'] ?? '';
        $name = trim($hhFirstname . ' ' . $hhSurname);
        if (empty($name)) $name = 'Unnamed Household';
        
        $recentSurveysFormatted[] = [
            'name' => htmlspecialchars($name),
            'barangay' => htmlspecialchars($survey['barangay']),
            'date' => date('M j, Y', strtotime($survey['created_at']))
        ];
    }
    
    // Return data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'totalSurveys' => $totalSurveys,
        'todaySurveys' => $todaySurveys,
        'totalBarangays' => $totalBarangays,
        'surveysByType' => $surveysByType,
        'surveysByBarangay' => $surveysByBarangay,
        'membershipStats' => $membershipStats,
        'recentSurveys' => $recentSurveysFormatted
    ]);
    exit;
}

// Get unique barangays from database
$barangays = getData($conn, "SELECT DISTINCT barangay FROM survey_responses ORDER BY barangay");

// Get survey types (assuming these are in the answers or a separate field)
// For this example, I'll use the predefined types you mentioned
$surveyTypes = [
    "IDSAP - FIRE VICTIM",
    "IDSAP - FLOOD", 
    "IDSAP - EARTHQUAKE",
    "CENSUS - PDC",
    "CENSUS - HOA",
    "CENSUS - WATERWAYS",
    "OTHERS"
];

// Check if a barangay is selected
$selectedBarangay = isset($_GET['barangay']) ? $_GET['barangay'] : null;

// Check if a survey type is selected
$selectedSurveyType = isset($_GET['survey_type']) ? $_GET['survey_type'] : null;

// Check if an individual is selected
$selectedIndividualId = isset($_GET['individual_id']) ? $_GET['individual_id'] : null;

// Initialize filter variables
$searchTerm = $_GET['search'] ?? '';
$ageRange = $_GET['age_range'] ?? '';
$sexFilter = $_GET['sex'] ?? '';
$sortBy = $_GET['sort'] ?? 'name_asc';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDSAP Survey System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* FIXED SIDEBAR STYLES */
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        .sidebar-fixed {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 16rem; /* 64 * 0.25rem = 16rem */
            overflow-y: auto;
            z-index: 40;
        }
        
        .main-content-scrollable {
            margin-left: 16rem; /* Same as sidebar width */
            width: calc(100% - 16rem);
            overflow-x: hidden;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .sidebar-fixed {
                width: 4rem; /* 64px for mobile */
            }
            .sidebar-fixed .sidebar-text {
                display: none;
            }
            .sidebar-fixed .logo-text {
                display: none;
            }
            .sidebar-fixed .sidebar-toggle {
                justify-content: center;
            }
            .main-content-scrollable {
                margin-left: 4rem;
                width: calc(100% - 4rem);
            }
        }

        .custom-card {
            transition: all 0.3s ease;
        }
        .custom-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-color: #3B82F6;
        }
        .survey-btn {
            transition: all 0.2s ease;
        }
        .survey-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .responsive-table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* FIXED: Normal photo size */
        .photo-thumbnail {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 5px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .photo-thumbnail:hover {
            transform: scale(1.05);
        }
        
        /* Photo Modal Styles */
        .photo-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(5px);
        }
        
        .photo-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
        
        .photo-modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            z-index: 1001;
        }
        
        .photo-modal-close:hover {
            color: #bbb;
        }
        
        .photo-caption {
            text-align: center;
            color: #fff;
            padding: 10px 0;
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.7);
        }
        
        .photo-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 15px 20px;
            font-size: 24px;
            cursor: pointer;
            border-radius: 50%;
            transition: 0.3s;
        }
        
        .photo-nav:hover {
            background: rgba(255, 255, 255, 0.4);
        }
        
        .photo-nav.prev {
            left: 20px;
        }
        
        .photo-nav.next {
            right: 20px;
        }
        
        /* FIXED: Normal signature size */
        .signature-img {
            max-width: 300px;
            max-height: 150px;
            border: 2px solid #333;
            border-radius: 4px;
            background: white;
            padding: 10px;
        }
        
        .survey-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px;
        }
        
        .survey-type-fire {
            background-color: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        
        .survey-type-flood {
            background-color: #DBEAFE;
            color: #1D4ED8;
            border: 1px solid #BFDBFE;
        }
        
        .survey-type-earthquake {
            background-color: #FEF3C7;
            color: #D97706;
            border: 1px solid #FDE68A;
        }
        
        .survey-type-census {
            background-color: #E0E7FF;
            color: #3730A3;
            border: 1px solid #C7D2FE;
        }
        
        .survey-type-others {
            background-color: #F3E8FF;
            color: #7C3AED;
            border: 1px solid #E9D5FF;
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            /* FIXED: Mobile photo size */
            .photo-thumbnail {
                width: 150px;
                height: 120px;
            }
            
            .signature-img {
                max-width: 250px;
                max-height: 120px;
            }
        }
        
        /* Map styling */
        #location-map { 
            min-height: 300px;
            z-index: 1;
        }
        .leaflet-container {
            background: #f8f9fa;
            font-family: inherit;
        }
        .leaflet-popup-content {
            margin: 12px;
            line-height: 1.4;
        }
        
        /* Mobile-friendly modals */
        @media (max-width: 640px) {
            .responsive-modal {
                width: 95%;
                margin: 0.5rem auto;
                max-height: 95vh;
            }
            .responsive-modal-content {
                padding: 1rem;
            }
        }
        
        /* Better table responsiveness */
        .responsive-table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Larger tap targets for mobile */
        @media (max-width: 640px) {
            .mobile-tap-target {
                padding: 1rem;
                min-height: 3rem;
            }
            .mobile-tap-target i {
                font-size: 1.25rem;
            }
        }
        .filter-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex: 1;
        }
        
        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 4px;
        }

        /* Form input styling */
        .form-input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
            background-color: white;
            transition: border-color 0.2s;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Survey Summary Modal Styles */
        .summary-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            overflow-y: auto;
        }
        
        .summary-modal-content {
            background-color: #f3f4f6;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 1200px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3B82F6;
        }
        
        .progress-bar {
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            height: 20px;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #3B82F6;
            transition: width 0.3s ease;
        }
        
        .chart-container {
            height: 256px;
            position: relative;
        }
        
        .summary-modal-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            border-radius: 10px 10px 0 0;
        }
        
        .summary-modal-body {
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .summary-modal-content {
                width: 95%;
                margin: 2% auto;
                max-height: 95vh;
            }
        }
        
        /* Back button styling */
        .back-button {
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            transform: translateX(-3px);
        }

        /* Ensure content doesn't hide behind fixed sidebar */
        @media (min-width: 769px) {
            .main-content-scrollable {
                padding-left: 0.5rem;
            }
        }

        /* Add scrollbar styling for sidebar */
        .sidebar-fixed::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-fixed::-webkit-scrollbar-track {
            background: #374151;
        }
        
        .sidebar-fixed::-webkit-scrollbar-thumb {
            background: #6B7280;
            border-radius: 2px;
        }
        
        .sidebar-fixed::-webkit-scrollbar-thumb:hover {
            background: #9CA3AF;
        }
    </style>
</head>

<body class="bg-gray-100">
  <!-- Fixed Sidebar -->
  <div class="sidebar-fixed bg-gray-800 text-white flex flex-col">
    <div class="flex items-center justify-center h-24">
      <!-- Profile Picture Container -->
      <div class="rounded-full bg-gray-200 w-20 h-20 flex items-center justify-center overflow-hidden border-2 border-white shadow-md">
        <?php
        $profilePicture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default_profile.jpg';
        ?>
        <img src="../assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>"
             alt="Profile Picture"
             class="w-full h-full object-cover"
             onerror="this.src='../assets/DEFAULT_PROFILE.jpg'">
      </div>
    </div>
    <div class="px-4 py-2 text-center text-sm text-gray-300">
      Logged in as: <br>
      <span class="font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
    </div>
    
    <nav class="mt-2 flex-1">
      <ul>
        <li>
          <a href="operation_dashboard.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-tachometer-alt mr-3"></i> <span class="sidebar-text">Dashboard</span>
          </a>
        </li>
        <li>
          <a href="operation_IDSAP.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center sidebar-active">
            <i class="fas fa-users mr-3"></i> <span class="sidebar-text">IDSAP Database</span>
          </a>
        </li>
        <li>
          <a href="operation_panel.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-scale-balanced mr-3"></i> <span class="sidebar-text">PDC Cases</span>
          </a>
        </li>
        <li>
          <a href="meralco.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-file-alt mr-3"></i> <span class="sidebar-text">Meralco Certificates</span>
          </a>
        </li>
        <li>
          <a href="meralco_database.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-server mr-3"></i> <span class="sidebar-text">Meralco Database</span>
          </a>
        </li>
        <li>
          <a href="../settings.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center mt-4">
            <i class="fas fa-cog mr-3"></i> <span class="sidebar-text">Settings</span>
          </a>
        </li>
        <li>
          <a href="../logout.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center mt-6">
            <i class="fas fa-sign-out-alt mr-3"></i> <span class="sidebar-text">Logout</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>

    <!-- Scrollable Main Content -->
    <div class="main-content-scrollable">
        <div class="p-4 md:p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">IDSAP Survey System</h1>
                <div class="flex items-center gap-2">
                    <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-6 md:h-8">
                    <span class="font-medium text-gray-700 text-sm md:text-base">Urban Development and Housing Office</span>
                </div>
            </div>

            <!-- Breadcrumb Navigation -->
            <div class="bg-white p-3 rounded-lg shadow-md mb-4">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="?" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <i class="fas fa-home mr-2"></i>
                                Barangays
                            </a>
                        </li>
                        <?php if ($selectedBarangay): ?>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400"></i>
                                <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">
                                    <?php echo htmlspecialchars($selectedBarangay); ?>
                                </a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($selectedSurveyType): ?>
                        <li aria-current="page">
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400"></i>
                                <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">
                                    <?php echo htmlspecialchars($selectedSurveyType); ?>
                                </span>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>

            <!-- Survey Type Panel (shown when barangay is selected) -->
            <?php if ($selectedBarangay && !$selectedSurveyType && !$selectedIndividualId): ?>
            <div id="surveyTypePanel" class="bg-white p-4 md:p-6 rounded-lg shadow-md mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                    <h2 class="text-lg md:text-xl font-semibold">Survey Types - <?php echo htmlspecialchars($selectedBarangay); ?></h2>
                    <div class="flex gap-2">
                        <a href="?" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center w-full md:w-auto justify-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Barangays
                        </a>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($surveyTypes as $type): 
                        // Determine badge class based on survey type
                        $badgeClass = 'survey-type-others';
                        if (strpos($type, 'FIRE') !== false) {
                            $badgeClass = 'survey-type-fire';
                        } elseif (strpos($type, 'FLOOD') !== false) {
                            $badgeClass = 'survey-type-flood';
                        } elseif (strpos($type, 'EARTHQUAKE') !== false) {
                            $badgeClass = 'survey-type-earthquake';
                        } elseif (strpos($type, 'CENSUS') !== false) {
                            $badgeClass = 'survey-type-census';
                        }
                    ?>
                    <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($type); ?>" 
                       class="survey-btn bg-white p-4 text-center rounded-lg border border-gray-200 hover:border-blue-400 hover:shadow-md transition">
                        <div class="<?php echo $badgeClass; ?> survey-type-badge mb-2">
                            <?php 
                            $icon = 'fa-clipboard-list';
                            if (strpos($type, 'FIRE') !== false) $icon = 'fa-fire';
                            elseif (strpos($type, 'FLOOD') !== false) $icon = 'fa-water';
                            elseif (strpos($type, 'EARTHQUAKE') !== false) $icon = 'fa-mountain';
                            elseif (strpos($type, 'CENSUS') !== false) $icon = 'fa-users';
                            ?>
                            <i class="fas <?php echo $icon; ?> mr-1"></i>
                            <?php echo htmlspecialchars($type); ?>
                        </div>
                        <h6 class="text-sm font-medium text-gray-800">View Surveys</h6>
                        <p class="text-xs text-gray-500 mt-1">Click to view all <?php echo htmlspecialchars($type); ?> surveys</p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Individuals Panel (shown when survey type is selected) -->
            <?php if ($selectedBarangay && $selectedSurveyType && !$selectedIndividualId): ?>
            <?php
            // Get all individuals for this barangay
            $sql = "SELECT * FROM survey_responses 
                    WHERE barangay = '" . $conn->real_escape_string($selectedBarangay) . "'";
            $allIndividuals = getData($conn, $sql);

            // Filter by survey type
            $filteredIndividuals = [];
            foreach ($allIndividuals as $individual) {
                $answers = decodeSurveyAnswers($individual['answers']);
                
                // Extract survey type from answers
                $storedSurveyType = trim($answers['survey-type'] ?? '');
                $storedOtherType = trim($answers['other-survey-type'] ?? '');
                
                // Clean and normalize survey type
                $normalizedType = '';
                if (!empty($storedSurveyType)) {
                    $normalizedType = strtoupper(trim($storedSurveyType));
                }
                
                // For "OTHERS" category
                if ($selectedSurveyType === "OTHERS") {
                    if (!empty($storedOtherType)) {
                        $filteredIndividuals[] = $individual;
                    }
                    continue;
                }
                
                // Exact match check for specific survey types
                $isMatch = false;
                
                // Map survey types to keywords
                $typeKeywords = [
                    "IDSAP - FIRE VICTIM" => ["FIRE VICTIM", "FIRE", "FIREVICTIM", "IDSAP FIRE", "IDSAP-FIRE"],
                    "IDSAP - FLOOD" => ["FLOOD", "IDSAP FLOOD", "IDSAP-FLOOD"],
                    "IDSAP - EARTHQUAKE" => ["EARTHQUAKE", "IDSAP EARTHQUAKE", "IDSAP-EARTHQUAKE"],
                    "CENSUS - PDC" => ["PDC", "CENSUS PDC", "CENSUS-PDC"],
                    "CENSUS - HOA" => ["HOA", "CENSUS HOA", "CENSUS-HOA"],
                    "CENSUS - WATERWAYS" => ["WATERWAYS", "CENSUS WATERWAYS", "CENSUS-WATERWAYS"]
                ];
                
                // Check if selected type is in our mapping
                if (isset($typeKeywords[$selectedSurveyType])) {
                    foreach ($typeKeywords[$selectedSurveyType] as $keyword) {
                        if (stripos($normalizedType, strtoupper($keyword)) !== false) {
                            $isMatch = true;
                            break;
                        }
                    }
                } else {
                    // Direct comparison for exact match
                    if ($normalizedType === strtoupper($selectedSurveyType)) {
                        $isMatch = true;
                    }
                }
                
                if ($isMatch) {
                    $filteredIndividuals[] = $individual;
                }
            }

            // Process individuals data for filtering
            $individualsData = [];
            foreach ($filteredIndividuals as $individual) {
                $answers = decodeSurveyAnswers($individual['answers']);
                
                // Extract HOUSEHOLD HEAD information
                $hhSurname = $answers['hh-surname'] ?? '';
                $hhFirstname = $answers['hh-firstname'] ?? '';
                $hhMiddlename = $answers['hh-middlename'] ?? '';
                $hhMi = $answers['hh-mi'] ?? '';
                $name = trim("$hhFirstname " . ($hhMi ? "$hhMi. " : "") . "$hhMiddlename $hhSurname");
                
                if (empty(trim($name))) {
                    if (isset($answers['full_name'])) {
                        $name = $answers['full_name'];
                    } elseif (isset($answers['name'])) {
                        $name = $answers['name'];
                    } else {
                        $name = 'Unnamed Household';
                    }
                }
                
                // Extract household head age
                $age = $answers['hh-age'] ?? 'N/A';
                if ($age === 'N/A') {
                    if (isset($answers['age'])) {
                        $age = $answers['age'];
                    } elseif (isset($answers['member_age'])) {
                        $age = $answers['member_age'];
                    }
                }
                
                // Extract household head sex
                $sex = $answers['hh-sex'] ?? '';
                if (empty($sex)) {
                    $sex = $answers['sex'] ?? '';
                }
                
                // Extract date surveyed
                $temp = json_decode($individual['answers'], true);
                $surveyDate = $temp[0] ?? $temp['survey_date'] ?? '';
                $surveyTime = $temp[1] ?? $temp['survey_time'] ?? '';
                
                if (!empty($surveyDate) && !empty($surveyTime)) {
                    $surveyDateTime = new DateTime($surveyDate . ' ' . $surveyTime);
                    $surveyDateFormatted = $surveyDateTime->format('M j, Y g:i A');
                    $surveyTimestamp = $surveyDateTime->getTimestamp();
                } else {
                    $surveyDateTime = new DateTime($individual['created_at']);
                    $surveyDateFormatted = $surveyDateTime->format('M j, Y g:i A');
                    $surveyTimestamp = $surveyDateTime->getTimestamp();
                }
                
                // Construct COMPLETE ADDRESS
                $houseNo = $answers['house-no'] ?? '';
                $lotNo = $answers['lot-no'] ?? '';
                $building = $answers['building'] ?? '';
                $block = $answers['block'] ?? '';
                $street = $answers['street'] ?? '';
                
                $completeAddress = trim("$houseNo $lotNo $building $block $street");
                $trimmedAddress = trim($completeAddress);
                
                if (empty($trimmedAddress)) {
                    $completeAddress = $individual['address'] ?? 'No address provided';
                    $trimmedAddress = trim($completeAddress);
                }
                
                $barangayName = $individual['barangay'] ?? '';
                if (!empty($barangayName) && !empty($trimmedAddress)) {
                    $completeAddress .= ", " . $barangayName;
                } elseif (!empty($barangayName)) {
                    $completeAddress = $barangayName;
                }
                
                // Store individual data
                $individualsData[] = [
                    'id' => $individual['id'],
                    'name' => $name,
                    'age' => $age,
                    'sex' => $sex,
                    'address' => $completeAddress,
                    'survey_date' => $surveyDateFormatted,
                    'survey_timestamp' => $surveyTimestamp,
                    'raw_data' => $individual
                ];
            }

            // Apply filters
            $filteredData = $individualsData;
            
            if (!empty($searchTerm)) {
                $searchTermLower = strtolower($searchTerm);
                $filteredData = array_filter($filteredData, function($item) use ($searchTermLower) {
                    return stripos(strtolower($item['name']), $searchTermLower) !== false || 
                           stripos(strtolower($item['address']), $searchTermLower) !== false;
                });
            }
            
            if (!empty($ageRange)) {
                $filteredData = array_filter($filteredData, function($item) use ($ageRange) {
                    $age = intval($item['age']);
                    switch($ageRange) {
                        case '0-18': return $age >= 0 && $age <= 18;
                        case '19-30': return $age >= 19 && $age <= 30;
                        case '31-50': return $age >= 31 && $age <= 50;
                        case '51-65': return $age >= 51 && $age <= 65;
                        case '66+': return $age >= 66;
                        default: return true;
                    }
                });
            }
            
            if (!empty($sexFilter)) {
                $filteredData = array_filter($filteredData, function($item) use ($sexFilter) {
                    return strcasecmp($item['sex'], $sexFilter) === 0;
                });
            }
            
            // Apply sorting
            usort($filteredData, function($a, $b) use ($sortBy) {
                switch($sortBy) {
                    case 'name_desc':
                        return strcasecmp($b['name'], $a['name']);
                    case 'age_asc':
                        return intval($a['age']) - intval($b['age']);
                    case 'age_desc':
                        return intval($b['age']) - intval($a['age']);
                    case 'date_desc':
                        return $b['survey_timestamp'] - $a['survey_timestamp'];
                    case 'date_asc':
                        return $a['survey_timestamp'] - $b['survey_timestamp'];
                    case 'name_asc':
                    default:
                        return strcasecmp($a['name'], $b['name']);
                }
            });
            ?>
            
            <div id="individualsPanel" class="bg-white p-4 md:p-6 rounded-lg shadow-md mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                    <h2 class="text-lg md:text-xl font-semibold">
                        Individuals - <?php echo htmlspecialchars($selectedBarangay); ?> - <?php echo htmlspecialchars($selectedSurveyType); ?>
                        <span class="text-sm font-normal text-gray-600">(<?php echo count($filteredData); ?> found)</span>
                    </h2>
                    <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center w-full md:w-auto justify-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Survey Types
                    </a>
                </div>
                
                <!-- AUTO-FILTER SECTION -->
                <div class="filter-section mb-4">
                    <h3 class="font-medium text-gray-700 mb-2">Filter Individuals</h3>
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label">Search by Name/Address</label>
                            <input type="text" id="searchInput" placeholder="Search individuals..." 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>"
                                   class="form-input" 
                                   data-filter="search">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Filter by Age</label>
                            <select id="ageFilter" class="form-select" data-filter="age_range">
                                <option value="">All Ages</option>
                                <option value="0-18" <?php echo $ageRange == '0-18' ? 'selected' : ''; ?>>0-18 years</option>
                                <option value="19-30" <?php echo $ageRange == '19-30' ? 'selected' : ''; ?>>19-30 years</option>
                                <option value="31-50" <?php echo $ageRange == '31-50' ? 'selected' : ''; ?>>31-50 years</option>
                                <option value="51-65" <?php echo $ageRange == '51-65' ? 'selected' : ''; ?>>51-65 years</option>
                                <option value="66+" <?php echo $ageRange == '66+' ? 'selected' : ''; ?>>66+ years</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Filter by Sex</label>
                            <select id="sexFilter" class="form-select" data-filter="sex">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $sexFilter == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $sexFilter == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Sort by</label>
                            <select id="sortFilter" class="form-select" data-filter="sort">
                                <option value="name_asc" <?php echo $sortBy == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo $sortBy == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <option value="age_asc" <?php echo $sortBy == 'age_asc' ? 'selected' : ''; ?>>Age (Low to High)</option>
                                <option value="age_desc" <?php echo $sortBy == 'age_desc' ? 'selected' : ''; ?>>Age (High to Low)</option>
                                <option value="date_desc" <?php echo $sortBy == 'date_desc' ? 'selected' : ''; ?>>Date (Newest First)</option>
                                <option value="date_asc" <?php echo $sortBy == 'date_asc' ? 'selected' : ''; ?>>Date (Oldest First)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="responsive-table-container">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Household Head</th>
                                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sex</th>
                                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Complete Address</th>
                                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Surveyed</th>
                                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($filteredData) > 0): ?>
                                <?php foreach ($filteredData as $data): ?>
                                <tr>
                                    <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php echo htmlspecialchars($data['name']); ?>
                                    </td>
                                    <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo htmlspecialchars($data['age']); ?>
                                    </td>
                                    <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo htmlspecialchars($data['sex']); ?>
                                    </td>
                                    <td class="px-4 md:px-6 py-4 text-sm">
                                        <?php echo htmlspecialchars($data['address']); ?>
                                    </td>
                                    <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $data['survey_date']; ?>
                                    </td>
                                    <td class="px-4 md:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($selectedSurveyType); ?>&individual_id=<?php echo $data['raw_data']['id']; ?>&search=<?php echo urlencode($searchTerm); ?>&age_range=<?php echo urlencode($ageRange); ?>&sex=<?php echo urlencode($sexFilter); ?>&sort=<?php echo urlencode($sortBy); ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-4 md:px-6 py-4 text-center text-gray-500">
                                        No individuals found for <?php echo htmlspecialchars($selectedSurveyType); ?> in <?php echo htmlspecialchars($selectedBarangay); ?>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Individual Details Panel (shown when individual is selected) -->
            <?php if ($selectedIndividualId): ?>
            <?php
            // Get individual details
            $individual = getData($conn, 
                "SELECT * FROM survey_responses 
                 WHERE id = " . intval($selectedIndividualId));
            $individual = count($individual) > 0 ? $individual[0] : null;

            if ($individual) {
                // USE THE HELPER FUNCTION
                $answers = decodeSurveyAnswers($individual['answers']);
                
                // Extract household head information - CORRECTED FIELD NAMES
                $hhSurname = $answers['hh-surname'] ?? '';
                $hhFirstname = $answers['hh-firstname'] ?? '';
                $hhMiddlename = $answers['hh-middlename'] ?? '';
                $hhMi = $answers['hh-mi'] ?? '';
                $name = trim("$hhFirstname " . ($hhMi ? "$hhMi. " : "") . "$hhMiddlename $hhSurname");
                if (empty(trim($name))) {
                    $name = 'Unnamed Household';
                }
                
                // Extract spouse information
                $spouseSurname = $answers['spouse-surname'] ?? '';
                $spouseFirstname = $answers['spouse-firstname'] ?? '';
                $spouseMiddlename = $answers['spouse-middlename'] ?? '';
                $spouseMi = $answers['spouse-mi'] ?? '';
                $spouseName = trim("$spouseFirstname " . ($spouseMi ? "$spouseMi. " : "") . "$spouseMiddlename $spouseSurname");
                
                // Extract other details - CORRECTED FIELD NAMES
                $hhAge = $answers['hh-age'] ?? '';
                $hhSex = $answers['hh-sex'] ?? '';
                $hhBirthdate = $answers['hh-birthdate'] ?? '';
                $hhCivilStatus = $answers['hh-civil-status'] ?? '';
                $hhSeniorCitizen = (isset($answers['hh-senior-citizen']) && ($answers['hh-senior-citizen'] === 'on' || $answers['hh-senior-citizen'] == 1)) ? 'Yes' : 'No';
                $hhPwd = (isset($answers['hh-pwd']) && ($answers['hh-pwd'] === 'on' || $answers['hh-pwd'] == 1)) ? 'Yes' : 'No';
                
                $spouseAge = $answers['spouse-age'] ?? '';
                $spouseSex = $answers['spouse-sex'] ?? '';
                $spouseBirthdate = $answers['spouse-birthdate'] ?? '';
                $spouseCivilStatus = $answers['spouse-civil-status'] ?? '';
                $spouseSeniorCitizen = (isset($answers['spouse-senior-citizen']) && ($answers['spouse-senior-citizen'] === 'on' || $answers['spouse-senior-citizen'] == 1)) ? 'Yes' : 'No';
                $spousePwd = (isset($answers['spouse-pwd']) && ($answers['spouse-pwd'] === 'on' || $answers['spouse-pwd'] == 1)) ? 'Yes' : 'No';
                
                // Address details
                $houseNo = $answers['house-no'] ?? '';
                $lotNo = $answers['lot-no'] ?? '';
                $building = $answers['building'] ?? '';
                $block = $answers['block'] ?? '';
                $street = $answers['street'] ?? '';
                
                // Tenurial status
                $landNature = $answers['land-nature'] ?? '';
                $lotStatus = $answers['lot-status'] ?? '';
                $nameRfoRenter = $answers['name-rfo-renter'] ?? '';
                
                // Membership - Check for both 'on' string and 1 integer values
                $pagibig = (isset($answers['pagibig']) && ($answers['pagibig'] === 'on' || $answers['pagibig'] == 1)) ? 'Yes' : 'No';
                $sss = (isset($answers['sss']) && ($answers['sss'] === 'on' || $answers['sss'] == 1)) ? 'Yes' : 'No';
                $gsis = (isset($answers['gsis']) && ($answers['gsis'] === 'on' || $answers['gsis'] == 1)) ? 'Yes' : 'No';
                $philhealth = (isset($answers['philhealth']) && ($answers['philhealth'] === 'on' || $answers['philhealth'] == 1)) ? 'Yes' : 'No';
                $noneFund = (isset($answers['none-fund']) && ($answers['none-fund'] === 'on' || $answers['none-fund'] == 1)) ? 'Yes' : 'No';
                $otherFund = (isset($answers['other-fund']) && ($answers['other-fund'] === 'on' || $answers['other-fund'] == 1)) ? 'Yes' : 'No';
                
                $cso = (isset($answers['cso']) && ($answers['cso'] === 'on' || $answers['cso'] == 1)) ? 'Yes' : 'No';
                $hoa = (isset($answers['hoa']) && ($answers['hoa'] === 'on' || $answers['hoa'] == 1)) ? 'Yes' : 'No';
                $cooperative = (isset($answers['cooperative']) && ($answers['cooperative'] === 'on' || $answers['cooperative'] == 1)) ? 'Yes' : 'No';
                $noneOrg = (isset($answers['none-org']) && ($answers['none-org'] === 'on' || $answers['none-org'] == 1)) ? 'Yes' : 'No';
                $otherOrg = (isset($answers['other-org']) && ($answers['other-org'] === 'on' || $answers['other-org'] == 1)) ? 'Yes' : 'No';
                $nameOrganization = $answers['name-organization'] ?? '';
                
                // Survey type
                $surveyType = $answers['survey-type'] ?? 'Not specified';
                $otherSurveyType = $answers['other-survey-type'] ?? '';
                
                // Remarks - Check for both 'on' string and 1 integer values
                $securityUpgrading = (isset($answers['security-upgrading']) && ($answers['security-upgrading'] === 'on' || $answers['security-upgrading'] == 1)) ? 'Yes' : 'No';
                $shelterProvision = (isset($answers['shelter-provision']) && ($answers['shelter-provision'] === 'on' || $answers['shelter-provision'] == 1)) ? 'Yes' : 'No';
                $structuralUpgrading = (isset($answers['structural-upgrading']) && ($answers['structural-upgrading'] === 'on' || $answers['structural-upgrading'] == 1)) ? 'Yes' : 'No';
                $infrastructureUpgrading = (isset($answers['infrastructure-upgrading']) && ($answers['infrastructure-upgrading'] === 'on' || $answers['infrastructure-upgrading'] == 1)) ? 'Yes' : 'No';
                $otherRemarks = $answers['other-remarks-text'] ?? '';
                
                $singleHh = (isset($answers['single-hh']) && ($answers['single-hh'] === 'on' || $answers['single-hh'] == 1)) ? 'Yes' : 'No';
                $displacedUnit = (isset($answers['displaced-unit']) && ($answers['displaced-unit'] === 'on' || $answers['displaced-unit'] == 1)) ? 'Yes' : 'No';
                $doubledUp = (isset($answers['doubled-up']) && ($answers['doubled-up'] === 'on' || $answers['doubled-up'] == 1)) ? 'Yes' : 'No';
                $displacementConcern = (isset($answers['displacement-concern']) && ($answers['displacement-concern'] === 'on' || $answers['displacement-concern'] == 1)) ? 'Yes' : 'No';
                
                $odc = (isset($answers['odc']) && ($answers['odc'] === 'on' || $answers['odc'] == 1)) ? 'Yes' : 'No';
                $aho = (isset($answers['aho']) && ($answers['aho'] === 'on' || $answers['aho'] == 1)) ? 'Yes' : 'No';
                $censusOthers = $answers['census-others-text'] ?? '';
                
                // Photos
                $photos = json_decode($individual['photos'] ?? '[]', true);
                if (!is_array($photos)) {
                    $photos = [];
                }
                
                // Household Members - CORRECTED PARSING
                $householdMembers = [];
                foreach ($answers as $key => $value) {
                    if (preg_match('/^member_(\d+)_(.*)$/', $key, $matches)) {
                        $memberIndex = $matches[1];
                        $fieldName = $matches[2];
                        $householdMembers[$memberIndex][$fieldName] = $value;
                    }
                }

                // Alternative parsing for different naming patterns
                foreach ($answers as $key => $value) {
                    if (preg_match('/^member_(\d+)_member_(.+)$/', $key, $matches)) {
                        $memberIndex = $matches[1];
                        $fieldName = $matches[2];
                        $householdMembers[$memberIndex][$fieldName] = $value;
                    }
                }
                // Also check for alternative naming patterns
                foreach ($answers as $key => $value) {
                    if (preg_match('/^member_(\d+)_(.*)$/', $key, $matches)) {
                        $memberIndex = $matches[1];
                        $fieldName = $matches[2];
                        $householdMembers[$memberIndex][$fieldName] = $value;
                    }
                }
            }
            ?>
            <div id="individualDetailsPanel" class="bg-white p-4 md:p-6 rounded-lg shadow-md mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h2 class="text-lg md:text-xl font-semibold">
                        Survey Details - <?php echo htmlspecialchars($name); ?>
                    </h2>
                    <div class="flex gap-2">
                        <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($selectedSurveyType); ?>&search=<?php echo urlencode($searchTerm); ?>&age_range=<?php echo urlencode($ageRange); ?>&sex=<?php echo urlencode($sexFilter); ?>&sort=<?php echo urlencode($sortBy); ?>" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to List
                        </a>
                    </div>
                </div>
                
                <!-- Identification Codes -->
                <div class="mb-6 bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-3 border-b pb-2">Identification Codes</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="font-medium">UD Code:</span>
                            <span class="ml-2 font-semibold"><?php echo htmlspecialchars($individual['ud_code'] ?? 'N/A'); ?></span>
                        </div>
                        <div>
                            <span class="font-medium">TAG Number:</span>
                            <span class="ml-2 font-semibold"><?php echo htmlspecialchars($individual['tag_number'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Survey Information -->
                <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">Survey Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="font-medium">Survey Type:</span>
                            <span><?php echo htmlspecialchars($surveyType); ?></span>
                            <?php if ($otherSurveyType): ?>
                            <br><span class="text-xs text-gray-600">(<?php echo htmlspecialchars($otherSurveyType); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="font-medium">Enumerator:</span>
                            <span><?php echo htmlspecialchars($individual['enumerator_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div>
                            <span class="font-medium">Enumerator ID:</span>
                            <span><?php echo htmlspecialchars($individual['enumerator_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div>
                            <span class="font-medium">Date Surveyed:</span>
                            <span>
                                <?php 
                                $temp = json_decode($individual['answers'], true);
                                $surveyDate = $temp[0] ?? $temp['survey_date'] ?? '';
                                $surveyTime = $temp[1] ?? $temp['survey_time'] ?? '';
                                
                                if (!empty($surveyDate) && !empty($surveyTime)) {
                                    $surveyDateTime = new DateTime($surveyDate . ' ' . $surveyTime);
                                    echo $surveyDateTime->format('M j, Y g:i A');
                                } else {
                                    $surveyDate = new DateTime($individual['created_at']);
                                    echo $surveyDate->format('M j, Y g:i A');
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Personal Data -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium mb-4 border-b pb-2">I. Personal Data</h3>
                        
                        <div class="space-y-4">
                            <!-- Household Head -->
                            <div>
                                <h4 class="font-medium mb-2">Household Head</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div><span class="font-medium">Name:</span> <?php echo htmlspecialchars($name); ?></div>
                                    <div><span class="font-medium">Age:</span> <?php echo htmlspecialchars($hhAge); ?></div>
                                    <div><span class="font-medium">Sex:</span> <?php echo htmlspecialchars($hhSex); ?></div>
                                    <div><span class="font-medium">Birthdate:</span> <?php echo htmlspecialchars($hhBirthdate); ?></div>
                                    <div><span class="font-medium">Civil Status:</span> <?php echo htmlspecialchars($hhCivilStatus); ?></div>
                                    <div><span class="font-medium">Senior Citizen:</span> <?php echo $hhSeniorCitizen; ?></div>
                                    <div><span class="font-medium">PWD:</span> <?php echo $hhPwd; ?></div>
                                </div>
                            </div>
                            
                            <!-- Spouse Information Display -->
                            <?php 
                            // Check if spouse data exists
                            $hasSpouseData = !empty($spouseName) || !empty($spouseAge) || !empty($spouseSex);
                            ?>

                            <?php if ($hasSpouseData): ?>
                            <div>
                                <h4 class="font-medium mb-2">Spouse Information</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div><span class="font-medium">Name:</span> <?php echo htmlspecialchars($spouseName); ?></div>
                                    <div><span class="font-medium">Age:</span> <?php echo htmlspecialchars($spouseAge); ?></div>
                                    <div><span class="font-medium">Sex:</span> <?php echo htmlspecialchars($spouseSex); ?></div>
                                    <div><span class="font-medium">Birthdate:</span> <?php echo htmlspecialchars($spouseBirthdate); ?></div>
                                    <div><span class="font-medium">Civil Status:</span> <?php echo htmlspecialchars($spouseCivilStatus); ?></div>
                                    <div><span class="font-medium">Senior Citizen:</span> <?php echo $spouseSeniorCitizen; ?></div>
                                    <div><span class="font-medium">PWD:</span> <?php echo $spousePwd; ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div>
                                <h4 class="font-medium mb-2">Spouse Information</h4>
                                <div class="text-sm text-gray-500">
                                    No spouse information provided.
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tenurial Status -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium mb-4 border-b pb-2">II. Tenurial Status</h3>
                        
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="font-medium">Complete Address:</span>
                                <span><?php echo htmlspecialchars(trim("$houseNo $lotNo $building $block $street")); ?></span>
                            </div>
                            <div>
                                <span class="font-medium">Barangay:</span>
                                <span><?php echo htmlspecialchars($individual['barangay'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span class="font-medium">Nature of Land:</span>
                                <span><?php echo htmlspecialchars($landNature); ?></span>
                            </div>
                            <div>
                                <span class="font-medium">Lot Status:</span>
                                <span><?php echo htmlspecialchars($lotStatus); ?></span>
                            </div>
                            <?php if ($nameRfoRenter): ?>
                            <div>
                                <span class="font-medium">Owner (RFO/Renter):</span>
                                <span><?php echo htmlspecialchars($nameRfoRenter); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Membership -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">III. Membership</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="font-medium mb-2">Fund Membership</h4>
                            <div class="space-y-1 text-sm">
                                <div>PAG-IBIG: <?php echo $pagibig; ?></div>
                                <div>SSS: <?php echo $sss; ?></div>
                                <div>GSIS: <?php echo $gsis; ?></div>
                                <div>PhilHealth: <?php echo $philhealth; ?></div>
                                <div>None: <?php echo $noneFund; ?></div>
                                <div>Other: <?php echo $otherFund; ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium mb-2">Organization</h4>
                            <div class="space-y-1 text-sm">
                                <div>CSO: <?php echo $cso; ?></div>
                                <div>HOA: <?php echo $hoa; ?></div>
                                <div>Cooperative: <?php echo $cooperative; ?></div>
                                <div>None: <?php echo $noneOrg; ?></div>
                                <div>Other: <?php echo $otherOrg; ?></div>
                                <?php if ($nameOrganization): ?>
                                <div>Organization Name: <?php echo htmlspecialchars($nameOrganization); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Household Members -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">IV. Household Member Data</h3>
                    
                    <?php if (!empty($householdMembers)): ?>
                        <div class="responsive-table-container">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Relationship</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Age</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sex</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Birthdate</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Education</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    $memberCount = 0;
                                    foreach ($householdMembers as $index => $member): 
                                        // Skip empty members
                                        $memberName = $member['name'] ?? $member['member_name'] ?? '';
                                        if (empty(trim($memberName))) continue;
                                        
                                        $memberCount++;
                                    ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm"><?php echo $memberCount; ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($memberName); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($member['relationship'] ?? $member['member_relationship'] ?? ''); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($member['age'] ?? $member['member_age'] ?? ''); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($member['sex'] ?? $member['member_sex'] ?? ''); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($member['birthdate'] ?? $member['member_birthdate'] ?? ''); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($member['education'] ?? $member['member_education'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($memberCount === 0): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-4 text-center text-gray-500">
                                            No household members found in the data.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-500">
                            No household member data available.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Remarks -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">V. Remarks</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <h4 class="font-medium mb-2">Shelter Needs</h4>
                            <div class="space-y-1">
                                <div>Tenurial Upgrading: <?php echo $securityUpgrading; ?></div>
                                <div>Shelter Provision: <?php echo $shelterProvision; ?></div>
                                <div>Structural Upgrading: <?php echo $structuralUpgrading; ?></div>
                                <div>Infrastructure Upgrading: <?php echo $infrastructureUpgrading; ?></div>
                                <?php if ($otherRemarks): ?>
                                <div>Other: <?php echo htmlspecialchars($otherRemarks); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium mb-2">Household Classification</h4>
                            <div class="space-y-1">
                                <div>Single HH: <?php echo $singleHh; ?></div>
                                <div>Displaced Unit: <?php echo $displacedUnit; ?></div>
                                <div>Doubled Up HH: <?php echo $doubledUp; ?></div>
                                <div>Displacement Concern: <?php echo $displacementConcern; ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium mb-2">Census Remarks</h4>
                            <div class="space-y-1">
                                <div>Out During Census: <?php echo $odc; ?></div>
                                <div>Absentee House Owner: <?php echo $aho; ?></div>
                                <?php if ($censusOthers): ?>
                                <div>Other: <?php echo htmlspecialchars($censusOthers); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Photos -->
                <?php if (!empty($photos)): ?>
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">Photos</h3>
                    <div class="photo-container">
                        <?php foreach ($photos as $index => $photo): ?>
                            <div class="photo-wrapper">
                                <img src="<?php echo htmlspecialchars($photo); ?>" 
                                     alt="Survey Photo <?php echo $index + 1; ?>" 
                                     class="photo-thumbnail"
                                     onclick="openPhotoModal(<?php echo $index; ?>)">
                                <div class="text-xs text-gray-500 mt-1">Photo <?php echo $index + 1; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Photo Modal -->
                <div id="photoModal" class="photo-modal">
                    <span class="photo-modal-close" onclick="closePhotoModal()">&times;</span>
                    
                    <button class="photo-nav prev" onclick="changePhoto(-1)">&#10094;</button>
                    <button class="photo-nav next" onclick="changePhoto(1)">&#10095;</button>
                    
                    <img class="photo-modal-content" id="modalImage">
                    <div id="photoCaption" class="photo-caption"></div>
                </div>
                <?php endif; ?>
                
                <!-- Signature -->
                <?php if (!empty($individual['signature'])): ?>
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">Signature</h3>
                    <div class="flex justify-center">
                        <img src="<?php echo htmlspecialchars($individual['signature']); ?>" alt="Signature" class="max-w-xs border border-gray-300 rounded bg-white p-2">
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Location -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2 flex items-center">
                        <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                        Location Information
                    </h3>
                    
                    <div class="mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div class="bg-white p-3 rounded border">
                                <div class="font-medium text-gray-700">Coordinates</div>
                                <div class="text-xs text-gray-600 mt-1">
                                    Lat: <?php echo !empty($individual['location_lat']) ? number_format($individual['location_lat'], 6) : 'N/A'; ?><br>
                                    Lng: <?php echo !empty($individual['location_lng']) ? number_format($individual['location_lng'], 6) : 'N/A'; ?>
                                </div>
                            </div>
                            
                            <div class="bg-white p-3 rounded border">
                                <div class="font-medium text-gray-700">Location Accuracy</div>
                                <div class="text-xs text-gray-600 mt-1">
                                    <?php 
                                    $accuracy = $individual['location_accuracy'] ?? 0;
                                    if ($accuracy > 0) {
                                        echo "Within " . number_format($accuracy, 0) . " meters";
                                    } else {
                                        echo "Not specified";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map Container -->
                    <div id="location-map" class="h-64 w-full rounded-lg border border-gray-300 bg-gray-100">
                        <div class="h-full flex items-center justify-center text-gray-500">
                            Loading map...
                        </div>
                    </div>

                    <!-- Map Controls - RESTORED TO ORIGINAL STYLE -->
                    <div class="mt-3 flex gap-2">
                        <button onclick="zoomToLocation()" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                            <i class="fas fa-search-plus mr-1"></i> Zoom to Location
                        </button>
                        <button onclick="openInOSM()" class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i> Open in OpenStreetMap
                        </button>
                    </div>

                    <!-- Address Details -->
                    <div class="mt-4 bg-white p-3 rounded border">
                        <div class="font-medium text-gray-700 mb-2">Address Details</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                            <div><span class="font-medium">Street:</span> <?php echo htmlspecialchars($individual['address'] ?? 'N/A'); ?></div>
                            <div><span class="font-medium">Barangay:</span> <?php echo htmlspecialchars($individual['barangay'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Barangay Panel (shown when no specific selection is made) -->
            <?php if (!$selectedBarangay && !$selectedSurveyType && !$selectedIndividualId): ?>
            <div id="barangayPanel" class="bg-white p-4 md:p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-lg md:text-xl font-semibold mb-4 md:mb-6 border-b pb-2">Barangay Survey Selection</h2>
                
                <div class="mb-4 flex flex-col md:flex-row items-start md:items-center gap-2">
                    <input type="text" id="searchBarangay" placeholder="Search barangay..." class="px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition w-full">
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 w-full md:w-auto flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <?php foreach ($barangays as $barangay): ?>
                        <a href="?barangay=<?php echo urlencode($barangay['barangay']); ?>" class="survey-btn bg-white p-3 md:p-4 text-center rounded-lg border border-gray-200 hover:border-blue-400">
                            <i class="fas fa-map-marker-alt text-blue-500 text-lg md:text-xl mb-1 md:mb-2"></i>
                            <h6 class="text-xs md:text-sm font-medium text-gray-800"><?php echo htmlspecialchars($barangay['barangay']); ?></h6>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Stats and Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <!-- System Statistics -->
                <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
                    <h3 class="text-base md:text-lg font-semibold mb-3 md:mb-4">Survey Statistics</h3>
                    <div class="grid grid-cols-2 gap-3 md:gap-4">
                        <?php
                        // Get statistics from database
                        $totalSurveys = getData($conn, "SELECT COUNT(*) as count FROM survey_responses")[0]['count'];
                        $todaySurveys = getData($conn, "SELECT COUNT(*) as count FROM survey_responses WHERE DATE(created_at) = CURDATE()")[0]['count'];
                        $totalBarangays = count($barangays);
                        ?>
                        <div class="bg-blue-50 p-3 md:p-4 rounded-lg">
                            <div class="text-blue-600 font-bold text-lg md:text-xl"><?php echo $totalSurveys; ?></div>
                            <div class="text-gray-600 text-xs md:text-sm">Total Surveys</div>
                        </div>
                        <div class="bg-green-50 p-3 md:p-4 rounded-lg">
                            <div class="text-green-600 font-bold text-lg md:text-xl"><?php echo $totalBarangays; ?></div>
                            <div class="text-gray-600 text-xs md:text-sm">Barangays Covered</div>
                        </div>
                        <div class="bg-yellow-50 p-3 md:p-4 rounded-lg">
                            <div class="text-yellow-600 font-bold text-lg md:text-xl"><?php echo $todaySurveys; ?></div>
                            <div class="text-gray-600 text-xs md:text-sm">Today's Surveys</div>
                        </div>
                        <div class="bg-purple-50 p-3 md:p-4 rounded-lg">
                            <div class="text-purple-600 font-bold text-lg md:text-xl"><?php echo count($surveyTypes); ?></div>
                            <div class="text-gray-600 text-xs md:text-sm">Survey Types</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
                    <h3 class="text-base md:text-lg font-semibold mb-3 md:mb-4">Quick Actions</h3>
                    <div class="space-y-3 md:space-y-4">
                        <a href="?export=excel" class="w-full text-left block">
                            <div class="bg-white p-3 md:p-4 rounded shadow-sm border border-gray-200 custom-card flex items-center mobile-tap-target">
                                <i class="fas fa-file-export text-green-500 text-lg md:text-xl mr-3"></i>
                                <span class="font-medium text-sm md:text-base">Export Survey Data</span>
                            </div>
                        </a>
                        <a href="javascript:void(0)" onclick="openSurveySummary()" class="w-full text-left block">
                            <div class="bg-white p-3 md:p-4 rounded shadow-sm border border-gray-200 custom-card flex items-center mobile-tap-target">
                                <i class="fas fa-chart-pie text-purple-500 text-lg md:text-xl mr-3"></i>
                                <span class="font-medium text-sm md:text-base">View Survey Summary</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Survey Summary Modal -->
    <div id="surveySummaryModal" class="summary-modal">
        <div class="summary-modal-content">
            <div class="summary-modal-header">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Survey Summary Report</h1>
                    <button onclick="closeSurveySummary()" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="summary-modal-body">
                <!-- Loading indicator -->
                <div id="summaryLoading" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-blue-500 text-2xl mb-2"></i>
                    <p class="text-gray-600">Loading survey summary...</p>
                </div>
                
                <!-- Summary content will be loaded here -->
                <div id="summaryContent" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality for barangay panel
        document.addEventListener('DOMContentLoaded', function() {
            // Barangay search
            const searchBarangay = document.getElementById('searchBarangay');
            if (searchBarangay) {
                searchBarangay.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const buttons = document.querySelectorAll('#barangayPanel .survey-btn');
                    
                    buttons.forEach(button => {
                        const barangayName = button.querySelector('h6').textContent.toLowerCase();
                        if (barangayName.includes(searchTerm)) {
                            button.style.display = 'block';
                        } else {
                            button.style.display = 'none';
                        }
                    });
                });
            }

            // Auto-filter functionality
            const searchInput = document.getElementById('searchInput');
            const ageFilter = document.getElementById('ageFilter');
            const sexFilter = document.getElementById('sexFilter');
            const sortFilter = document.getElementById('sortFilter');
            
            // Get current URL parameters
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            const barangay = params.get('barangay');
            const surveyType = params.get('survey_type');
            
            // Function to update URL with filters
            function updateFilters() {
                const newParams = new URLSearchParams();
                newParams.set('barangay', barangay);
                newParams.set('survey_type', surveyType);
                
                if (searchInput && searchInput.value.trim()) {
                    newParams.set('search', searchInput.value.trim());
                }
                
                if (ageFilter && ageFilter.value) {
                    newParams.set('age_range', ageFilter.value);
                }
                
                if (sexFilter && sexFilter.value) {
                    newParams.set('sex', sexFilter.value);
                }
                
                if (sortFilter && sortFilter.value) {
                    newParams.set('sort', sortFilter.value);
                }
                
                // Update URL without reloading the page
                const newUrl = `${window.location.pathname}?${newParams.toString()}`;
                window.history.replaceState({}, '', newUrl);
                
                // Reload the page to apply filters
                window.location.href = newUrl;
            }
            
            // Set up event listeners for auto-filtering
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(updateFilters, 500); // 500ms delay
                });
            }
            
            if (ageFilter) {
                ageFilter.addEventListener('change', updateFilters);
            }
            
            if (sexFilter) {
                sexFilter.addEventListener('change', updateFilters);
            }
            
            if (sortFilter) {
                sortFilter.addEventListener('change', updateFilters);
            }

            // Global map variables
            let locationMap = null;
            let locationMarker = null;

            // Initialize map when page loads
            const rawLat = <?php echo isset($individual['location_lat']) ? $individual['location_lat'] : 'null'; ?>;
            const rawLng = <?php echo isset($individual['location_lng']) ? $individual['location_lng'] : 'null'; ?>;
            
            // Convert to numbers and validate
            const lat = parseFloat(rawLat);
            const lng = parseFloat(rawLng);
            
            // Check if coordinates are valid
            if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                initializeMap(lat, lng);
            } else {
                showNoLocationMessage();
            }

            function initializeMap(lat, lng) {
                try {
                    // Initialize the map
                    locationMap = L.map('location-map').setView([lat, lng], 16);
                    
                    // Add OpenStreetMap tiles
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        maxZoom: 19
                    }).addTo(locationMap);
                    
                    // Create and add marker
                    locationMarker = L.marker([lat, lng]).addTo(locationMap)
                        .bindPopup(`
                            <div class="text-sm">
                                <strong>Survey Location</strong><br>
                                Lat: ${lat.toFixed(6)}<br>
                                Lng: ${lng.toFixed(6)}
                            </div>
                        `)
                        .openPopup();
                    
                    // Add some padding around the marker
                    locationMap.fitBounds([
                        [lat - 0.001, lng - 0.001],
                        [lat + 0.001, lng + 0.001]
                    ]);
                    
                } catch (error) {
                    showErrorMessage('Failed to load map: ' + error.message);
                }
            }

            function showNoLocationMessage() {
                const mapContainer = document.getElementById('location-map');
                if (mapContainer) {
                    mapContainer.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-gray-500 p-4">
                            <i class="fas fa-map-marker-alt text-3xl mb-2 text-gray-300"></i>
                            <div class="text-center">
                                <div class="font-medium">No Location Data Available</div>
                                <div class="text-xs mt-1">Location coordinates were not recorded for this survey</div>
                            </div>
                        </div>
                    `;
                }
            }

            function showErrorMessage(message) {
                const mapContainer = document.getElementById('location-map');
                if (mapContainer) {
                    mapContainer.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-red-500 p-4">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                            <div class="text-center">
                                <div class="font-medium">Map Error</div>
                                <div class="text-xs mt-1">${message}</div>
                            </div>
                        </div>
                    `;
                }
            }

            window.zoomToLocation = function() {
                if (locationMap && locationMarker) {
                    locationMap.setView(locationMarker.getLatLng(), 18);
                    locationMarker.openPopup();
                } else {
                    alert('Map is not available');
                }
            }

            window.openInOSM = function() {
                const rawLat = <?php echo isset($individual['location_lat']) ? $individual['location_lat'] : 'null'; ?>;
                const rawLng = <?php echo isset($individual['location_lng']) ? $individual['location_lng'] : 'null'; ?>;
                
                const lat = parseFloat(rawLat);
                const lng = parseFloat(rawLng);
                
                if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                    window.open(`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}&zoom=17`, '_blank');
                } else {
                    alert('No valid coordinates available');
                }
            }
        });

        // Photo Modal functionality
        let currentPhotoIndex = 0;
        let photosArray = [];

        function openPhotoModal(index) {
            currentPhotoIndex = index;
            photosArray = <?php echo json_encode($photos ?? []); ?>;
            
            if (photosArray.length > 0) {
                const modal = document.getElementById('photoModal');
                const modalImg = document.getElementById('modalImage');
                const caption = document.getElementById('photoCaption');
                
                modal.style.display = 'block';
                modalImg.src = photosArray[currentPhotoIndex];
                caption.innerHTML = `Photo ${currentPhotoIndex + 1} of ${photosArray.length}`;
                
                // Prevent body scroll when modal is open
                document.body.style.overflow = 'hidden';
            }
        }

        function closePhotoModal() {
            const modal = document.getElementById('photoModal');
            modal.style.display = 'none';
            
            // Restore body scroll
            document.body.style.overflow = 'auto';
        }

        function changePhoto(direction) {
            currentPhotoIndex += direction;
            
            // Loop around if at ends
            if (currentPhotoIndex >= photosArray.length) {
                currentPhotoIndex = 0;
            } else if (currentPhotoIndex < 0) {
                currentPhotoIndex = photosArray.length - 1;
            }
            
            const modalImg = document.getElementById('modalImage');
            const caption = document.getElementById('photoCaption');
            
            modalImg.src = photosArray[currentPhotoIndex];
            caption.innerHTML = `Photo ${currentPhotoIndex + 1} of ${photosArray.length}`;
        }

        // Close modal when clicking outside the image
        document.getElementById('photoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePhotoModal();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('photoModal');
            if (modal.style.display === 'block') {
                switch(e.key) {
                    case 'Escape':
                        closePhotoModal();
                        break;
                    case 'ArrowLeft':
                        changePhoto(-1);
                        break;
                    case 'ArrowRight':
                        changePhoto(1);
                        break;
                }
            }
        });

        // Survey Summary Modal Functions
        function openSurveySummary() {
            const modal = document.getElementById('surveySummaryModal');
            const loading = document.getElementById('summaryLoading');
            const content = document.getElementById('summaryContent');
            
            // Show modal and loading indicator
            modal.style.display = 'block';
            loading.style.display = 'block';
            content.style.display = 'none';
            document.body.style.overflow = 'hidden';
            
            // Fetch summary data via AJAX
            fetch('?view=summary')
                .then(response => response.json())
                .then(data => {
                    // Hide loading indicator
                    loading.style.display = 'none';
                    
                    // Render the summary content
                    renderSummaryContent(data);
                    
                    // Show content
                    content.style.display = 'block';
                    
                    // Initialize charts
                    initializeCharts(data);
                })
                .catch(error => {
                    console.error('Error loading summary:', error);
                    loading.innerHTML = `
                        <div class="text-red-500">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                            <p>Failed to load survey summary. Please try again.</p>
                        </div>
                    `;
                });
        }

        function closeSurveySummary() {
            const modal = document.getElementById('surveySummaryModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function renderSummaryContent(data) {
            const content = document.getElementById('summaryContent');
            
            // Create progress bars for barangays
            let barangayProgress = '';
            if (data.surveysByBarangay && data.surveysByBarangay.length > 0) {
                data.surveysByBarangay.forEach(barangay => {
                    const percentage = (barangay.count / Math.max(data.totalSurveys, 1)) * 100;
                    barangayProgress += `
                        <div class="mb-3">
                            <div class="flex justify-between mb-1">
                                <span class="font-medium text-gray-700">${barangay.barangay}</span>
                                <span class="font-semibold text-blue-600">${barangay.count} surveys</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${percentage}%"></div>
                            </div>
                        </div>
                    `;
                });
            }
            
            // Create recent surveys list
            let recentSurveys = '';
            if (data.recentSurveys && data.recentSurveys.length > 0) {
                data.recentSurveys.forEach(survey => {
                    recentSurveys += `
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200 border border-gray-200">
                            <div class="flex-1">
                                <div class="font-medium text-gray-800">${survey.name}</div>
                                <div class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    ${survey.barangay}
                                </div>
                            </div>
                            <div class="text-sm text-gray-500 whitespace-nowrap ml-4">
                                <i class="far fa-calendar-alt mr-1"></i>
                                ${survey.date}
                            </div>
                        </div>
                    `;
                });
            }
            
            content.innerHTML = `
                <!-- Overview Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div class="summary-card text-center hover:shadow-lg transition-shadow duration-300">
                        <div class="stat-number">${data.totalSurveys}</div>
                        <div class="text-gray-600 font-medium">Total Surveys</div>
                    </div>
                    <div class="summary-card text-center hover:shadow-lg transition-shadow duration-300">
                        <div class="stat-number">${data.todaySurveys}</div>
                        <div class="text-gray-600 font-medium">Today's Surveys</div>
                    </div>
                    <div class="summary-card text-center hover:shadow-lg transition-shadow duration-300">
                        <div class="stat-number">${data.totalBarangays}</div>
                        <div class="text-gray-600 font-medium">Barangays Covered</div>
                    </div>
                </div>

                <!-- Charts and Data Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Surveys by Type -->
                    <div class="summary-card hover:shadow-lg transition-shadow duration-300">
                        <h3 class="text-xl font-semibold mb-4 text-gray-800">Surveys by Type</h3>
                        <div class="chart-container">
                            <canvas id="surveyTypeChart"></canvas>
                        </div>
                    </div>

                    <!-- Surveys by Barangay -->
                    <div class="summary-card hover:shadow-lg transition-shadow duration-300">
                        <h3 class="text-xl font-semibold mb-4 text-gray-800">Surveys by Barangay</h3>
                        <div class="max-h-64 overflow-y-auto pr-2">
                            ${barangayProgress || '<p class="text-gray-500 text-center py-4">No barangay data available</p>'}
                        </div>
                    </div>

                    <!-- Membership Statistics -->
                    <div class="summary-card hover:shadow-lg transition-shadow duration-300">
                        <h3 class="text-xl font-semibold mb-4 text-gray-800">Fund Membership</h3>
                        <div class="chart-container">
                            <canvas id="membershipChart"></canvas>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="summary-card hover:shadow-lg transition-shadow duration-300">
                        <h3 class="text-xl font-semibold mb-4 text-gray-800">Recent Surveys</h3>
                        <div class="space-y-3 max-h-64 overflow-y-auto pr-2">
                            ${recentSurveys || '<p class="text-gray-500 text-center py-4">No recent surveys</p>'}
                        </div>
                    </div>
                </div>
            `;
        }

        function initializeCharts(data) {
            // Survey Type Chart
            const surveyTypeCtx = document.getElementById('surveyTypeChart').getContext('2d');
            const surveyTypeChart = new Chart(surveyTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data.surveysByType),
                    datasets: [{
                        data: Object.values(data.surveysByType),
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                            '#9966FF', '#FF9F40', '#8AC926', '#C9CBCF'
                        ],
                        borderWidth: 1,
                        borderColor: '#f3f4f6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 10
                                },
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            titleFont: {
                                size: 12
                            },
                            bodyFont: {
                                size: 11
                            }
                        }
                    },
                    cutout: '60%'
                }
            });

            // Membership Chart
            const membershipCtx = document.getElementById('membershipChart').getContext('2d');
            const membershipChart = new Chart(membershipCtx, {
                type: 'bar',
                data: {
                    labels: ['PAG-IBIG', 'SSS', 'PhilHealth', 'None'],
                    datasets: [{
                        label: 'Members Count',
                        data: Object.values(data.membershipStats),
                        backgroundColor: '#3B82F6',
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            titleFont: {
                                size: 12
                            },
                            bodyFont: {
                                size: 11
                            }
                        }
                    }
                }
            });
            
            // Adjust charts on resize
            window.addEventListener('resize', function() {
                surveyTypeChart.resize();
                membershipChart.resize();
            });
            
            // Add hover effects to progress bars
            document.querySelectorAll('.progress-fill').forEach(progress => {
                progress.parentElement.addEventListener('mouseenter', function() {
                    progress.style.transition = 'width 0.5s ease';
                });
            });
        }

        // Close survey summary modal when clicking outside
        document.getElementById('surveySummaryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSurveySummary();
            }
        });

        // Close survey summary modal with Escape key
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('surveySummaryModal');
            if (e.key === 'Escape' && modal.style.display === 'block') {
                closeSurveySummary();
            }
        });

        // Add CSS for map
        const mapStyles = `
            #location-map { 
                min-height: 256px;
                z-index: 1;
            }
            .leaflet-container {
                background: #f8f9fa;
                font-family: inherit;
            }
            .leaflet-popup-content {
                margin: 12px;
                line-height: 1.4;
            }
        `;

        // Inject styles
        if (!document.querySelector('#map-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'map-styles';
            styleSheet.textContent = mapStyles;
            document.head.appendChild(styleSheet);
        }
    </script>

    <!-- Load Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>