<?php
// Database connection
$servername = "localhost";
$username = "u198271324_admin";
$password = "Udhodbms01";
$dbname = "u198271324_udho_db";

// Now proceed with your actual connection
session_start();
date_default_timezone_set('Asia/Manila');

// Create connection for the application
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

// Handle Summary View
if (isset($_GET['view']) && $_GET['view'] == 'summary') {
    displaySurveySummary($conn);
    exit;
}

// Handle Update Action
if (isset($_POST['action']) && $_POST['action'] == 'update_record') {
    $result = updateIndividualRecord($conn, $_POST);
    if ($result['success']) {
        $selectedBarangay = isset($_POST['barangay']) ? $_POST['barangay'] : '';
        $selectedSurveyType = isset($_POST['survey_type']) ? $_POST['survey_type'] : '';
        $selectedIndividualId = isset($_POST['individual_id']) ? intval($_POST['individual_id']) : 0;
        
        header('Location: ?barangay=' . urlencode($selectedBarangay) . '&survey_type=' . urlencode($selectedSurveyType) . '&individual_id=' . $selectedIndividualId . '&updated=1');
        exit;
    } else {
        echo "Error updating record: " . $result['message'];
        exit;
    }
}

// Handle Delete Actions
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'delete_individual') {
        $individualId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $barangay = isset($_GET['barangay']) ? urldecode($_GET['barangay']) : '';
        $survey_type = isset($_GET['survey_type']) ? urldecode($_GET['survey_type']) : '';
        
        if ($individualId > 0) {
            $result = deleteIndividual($conn, $individualId);
            if ($result['success']) {
                // Redirect back to appropriate page
                if (!empty($barangay) && !empty($survey_type)) {
                    header('Location: ?barangay=' . urlencode($barangay) . '&survey_type=' . urlencode($survey_type) . '&deleted=1');
                } elseif (!empty($barangay)) {
                    header('Location: ?barangay=' . urlencode($barangay) . '&deleted=1');
                } else {
                    header('Location: ?deleted=1');
                }
                exit;
            } else {
                echo "Error deleting record: " . $result['message'];
                exit;
            }
        }
    }
    
    // Handle Barangay Delete
    if ($_GET['action'] == 'delete_barangay') {
        $barangay = isset($_GET['barangay']) ? urldecode($_GET['barangay']) : '';
        if (!empty($barangay)) {
            $result = deleteBarangaySurveys($conn, $barangay);
            if ($result['success']) {
                header('Location: ?deleted_barangay=1');
                exit;
            } else {
                echo "Error deleting barangay surveys: " . $result['message'];
                exit;
            }
        }
    }
}

// Update Individual Function - FIXED: Removed updated_at column
function updateIndividualRecord($conn, $postData) {
    try {
        $individualId = intval($postData['individual_id']);
        
        // Get existing data first
        $stmt = $conn->prepare("SELECT * FROM survey_responses WHERE id = ?");
        $stmt->bind_param("i", $individualId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingRecord = $result->fetch_assoc();
        
        if (!$existingRecord) {
            return ['success' => false, 'message' => "Record not found"];
        }
        
        // Decode existing answers
        $existingAnswers = decodeSurveyAnswers($existingRecord['answers']);
        
        // Update answers with new data
        $updatedAnswers = $existingAnswers;
        
        // Update Personal Data
        $updatedAnswers['hh-surname'] = $postData['hh-surname'] ?? '';
        $updatedAnswers['hh-firstname'] = $postData['hh-firstname'] ?? '';
        $updatedAnswers['hh-middlename'] = $postData['hh-middlename'] ?? '';
        $updatedAnswers['hh-mi'] = $postData['hh-mi'] ?? '';
        $updatedAnswers['hh-age'] = $postData['hh-age'] ?? '';
        $updatedAnswers['hh-sex'] = $postData['hh-sex'] ?? '';
        $updatedAnswers['hh-birthdate'] = $postData['hh-birthdate'] ?? '';
        $updatedAnswers['hh-civil-status'] = $postData['hh-civil-status'] ?? '';
        $updatedAnswers['hh-senior-citizen'] = isset($postData['hh-senior-citizen']) ? 'on' : '';
        $updatedAnswers['hh-pwd'] = isset($postData['hh-pwd']) ? 'on' : '';
        
        // Update Spouse Data
        $updatedAnswers['spouse-surname'] = $postData['spouse-surname'] ?? '';
        $updatedAnswers['spouse-firstname'] = $postData['spouse-firstname'] ?? '';
        $updatedAnswers['spouse-middlename'] = $postData['spouse-middlename'] ?? '';
        $updatedAnswers['spouse-mi'] = $postData['spouse-mi'] ?? '';
        $updatedAnswers['spouse-age'] = $postData['spouse-age'] ?? '';
        $updatedAnswers['spouse-sex'] = $postData['spouse-sex'] ?? '';
        $updatedAnswers['spouse-birthdate'] = $postData['spouse-birthdate'] ?? '';
        $updatedAnswers['spouse-civil-status'] = $postData['spouse-civil-status'] ?? '';
        $updatedAnswers['spouse-senior-citizen'] = isset($postData['spouse-senior-citizen']) ? 'on' : '';
        $updatedAnswers['spouse-pwd'] = isset($postData['spouse-pwd']) ? 'on' : '';
        
        // Update Address
        $updatedAnswers['house-no'] = $postData['house-no'] ?? '';
        $updatedAnswers['lot-no'] = $postData['lot-no'] ?? '';
        $updatedAnswers['building'] = $postData['building'] ?? '';
        $updatedAnswers['block'] = $postData['block'] ?? '';
        $updatedAnswers['street'] = $postData['street'] ?? '';
        
        // Update Tenurial Status
        $updatedAnswers['land-nature'] = $postData['land-nature'] ?? '';
        $updatedAnswers['lot-status'] = $postData['lot-status'] ?? '';
        $updatedAnswers['name-rfo-renter'] = $postData['name-rfo-renter'] ?? '';
        
        // Update Membership
        $updatedAnswers['pagibig'] = isset($postData['pagibig']) ? 'on' : '';
        $updatedAnswers['sss'] = isset($postData['sss']) ? 'on' : '';
        $updatedAnswers['gsis'] = isset($postData['gsis']) ? 'on' : '';
        $updatedAnswers['philhealth'] = isset($postData['philhealth']) ? 'on' : '';
        $updatedAnswers['none-fund'] = isset($postData['none-fund']) ? 'on' : '';
        $updatedAnswers['other-fund'] = isset($postData['other-fund']) ? 'on' : '';
        
        // Update Organization
        $updatedAnswers['cso'] = isset($postData['cso']) ? 'on' : '';
        $updatedAnswers['hoa'] = isset($postData['hoa']) ? 'on' : '';
        $updatedAnswers['cooperative'] = isset($postData['cooperative']) ? 'on' : '';
        $updatedAnswers['none-org'] = isset($postData['none-org']) ? 'on' : '';
        $updatedAnswers['other-org'] = isset($postData['other-org']) ? 'on' : '';
        $updatedAnswers['name-organization'] = $postData['name-organization'] ?? '';
        
        // Update Survey Type
        $updatedAnswers['survey-type'] = $postData['survey-type'] ?? '';
        $updatedAnswers['other-survey-type'] = $postData['other-survey-type'] ?? '';
        
        // Update Remarks
        $updatedAnswers['security-upgrading'] = isset($postData['security-upgrading']) ? 'on' : '';
        $updatedAnswers['shelter-provision'] = isset($postData['shelter-provision']) ? 'on' : '';
        $updatedAnswers['structural-upgrading'] = isset($postData['structural-upgrading']) ? 'on' : '';
        $updatedAnswers['infrastructure-upgrading'] = isset($postData['infrastructure-upgrading']) ? 'on' : '';
        $updatedAnswers['other-remarks-text'] = $postData['other-remarks-text'] ?? '';
        
        $updatedAnswers['single-hh'] = isset($postData['single-hh']) ? 'on' : '';
        $updatedAnswers['displaced-unit'] = isset($postData['displaced-unit']) ? 'on' : '';
        $updatedAnswers['doubled-up'] = isset($postData['doubled-up']) ? 'on' : '';
        $updatedAnswers['displacement-concern'] = isset($postData['displacement-concern']) ? 'on' : '';
        
        $updatedAnswers['odc'] = isset($postData['odc']) ? 'on' : '';
        $updatedAnswers['aho'] = isset($postData['aho']) ? 'on' : '';
        $updatedAnswers['census-others-text'] = $postData['census-others-text'] ?? '';
        
        // Update Household Members
        // Remove existing household members
        foreach ($updatedAnswers as $key => $value) {
            if (preg_match('/^member_\d+_/', $key)) {
                unset($updatedAnswers[$key]);
            }
        }
        
        // Add updated household members
        if (isset($postData['member_firstname']) && is_array($postData['member_firstname'])) {
            foreach ($postData['member_firstname'] as $index => $firstName) {
                if (!empty($firstName) || !empty($postData['member_surname'][$index])) {
                    $memberNum = $index + 1;
                    $updatedAnswers["member_{$memberNum}_firstname"] = $firstName;
                    $updatedAnswers["member_{$memberNum}_surname"] = $postData['member_surname'][$index] ?? '';
                    $updatedAnswers["member_{$memberNum}_middlename"] = $postData['member_middlename'][$index] ?? '';
                    $updatedAnswers["member_{$memberNum}_mi"] = $postData['member_mi'][$index] ?? '';
                    $updatedAnswers["member_{$memberNum}_relationship"] = $postData['member_relationship'][$index] ?? '';
                    $updatedAnswers["member_{$memberNum}_age"] = $postData['member_age'][$index] ?? '';
                    $updatedAnswers["member_{$memberNum}_sex"] = $postData['member_sex'][$index] ?? '';
                    $updatedAnswers["member_{$memberNum}_birthdate"] = $postData['member_birthdate'][$index] ?? '';
                    $updatedAnswers["member_{$memberNum}_education"] = $postData['member_education'][$index] ?? '';
                }
            }
        }
        
        // Encode back to JSON
        $updatedAnswersJson = json_encode($updatedAnswers);
        
        // Update main fields
        $barangay = $postData['barangay'] ?? $existingRecord['barangay'];
        $address = $postData['address'] ?? $existingRecord['address'];
        
        // FIXED: Removed updated_at from the query
        $stmt = $conn->prepare("UPDATE survey_responses SET 
                                barangay = ?, 
                                address = ?, 
                                answers = ?
                                WHERE id = ?");
        
        $stmt->bind_param("sssi", $barangay, $address, $updatedAnswersJson, $individualId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Record updated successfully'];
        } else {
            return ['success' => false, 'message' => "Database error: " . $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Delete Individual Function WITH ARCHIVING
function deleteIndividual($conn, $individualId) {
    try {
        // Get individual data first for archiving
        $stmt = $conn->prepare("SELECT * FROM survey_responses WHERE id = ?");
        $stmt->bind_param("i", $individualId);
        $stmt->execute();
        $result = $stmt->get_result();
        $individual = $result->fetch_assoc();
        
        if (!$individual) {
            return ['success' => false, 'message' => "Record not found"];
        }
        
        // Archive the record before deletion
        $stmt = $conn->prepare("INSERT INTO archived_surveys 
                                (original_id, survey_data, deleted_by, reason) 
                                VALUES (?, ?, ?, ?)");
        
        // Prepare survey data for archiving
        $surveyData = json_encode($individual);
        $deletedBy = $_SESSION['username'] ?? 'System';
        $reason = "Manual deletion from IDSAP system";
        
        $stmt->bind_param("isss", $individualId, $surveyData, $deletedBy, $reason);
        $archiveSuccess = $stmt->execute();
        
        if (!$archiveSuccess) {
            error_log("Failed to archive record $individualId: " . $stmt->error);
            // Continue with deletion even if archiving fails
        }
        
        // Delete the record
        $stmt = $conn->prepare("DELETE FROM survey_responses WHERE id = ?");
        $stmt->bind_param("i", $individualId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Record deleted and archived successfully'];
        } else {
            return ['success' => false, 'message' => "Database error: " . $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Delete Barangay Surveys Function WITH ARCHIVING
function deleteBarangaySurveys($conn, $barangay) {
    try {
        // Get all surveys from the barangay for archiving
        $stmt = $conn->prepare("SELECT * FROM survey_responses WHERE barangay = ?");
        $stmt->bind_param("s", $barangay);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $deletedBy = $_SESSION['username'] ?? 'System';
        $count = 0;
        
        // Archive each record before deletion
        while ($individual = $result->fetch_assoc()) {
            $archiveStmt = $conn->prepare("INSERT INTO archived_surveys 
                                          (original_id, survey_data, deleted_by, reason) 
                                          VALUES (?, ?, ?, ?)");
            
            $surveyData = json_encode($individual);
            $reason = "Bulk deletion - Barangay: $barangay";
            
            $archiveStmt->bind_param("isss", $individual['id'], $surveyData, $deletedBy, $reason);
            $archiveStmt->execute();
            $archiveStmt->close();
            $count++;
        }
        
        // Delete all surveys from the barangay
        $stmt = $conn->prepare("DELETE FROM survey_responses WHERE barangay = ?");
        $stmt->bind_param("s", $barangay);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => "$count records deleted and archived from $barangay"];
        } else {
            return ['success' => false, 'message' => "Database error: " . $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
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

// Survey Summary Function
function displaySurveySummary($conn) {
    ?>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey Summary - IDSAP System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #1f2937;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 50;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            background-color: #f3f4f6;
            overflow-y: auto;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #d1d5db;
            transition: all 0.3s;
            text-decoration: none;
            font-weight: 600;
        }
        
        .sidebar-link:hover {
            background-color: #374151;
            color: white;
        }
        
        .sidebar-link.active {
            background-color: #3B82F6;
            color: white;
        }
        
        .dropdown-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            color: #d1d5db;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .dropdown-toggle:hover {
            background-color: #374151;
            color: white;
        }
        
        .dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background-color: #374151;
        }
        
        .dropdown-menu.show {
            max-height: 500px;
        }
        
        .submenu-link {
            display: flex;
            align-items: center;
            padding: 10px 16px 10px 40px;
            color: #d1d5db;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .submenu-link:hover {
            background-color: #4B5563;
            color: white;
        }
        
        .submenu-link.active {
            background-color: #3B82F6;
            color: white;
        }
        
        .fa-chevron-down {
            transition: transform 0.3s;
        }
        
        .fa-chevron-down.rotate-180 {
            transform: rotate(180deg);
        }
        
        .sidebar span, .dropdown-toggle span, .submenu-link span {
            font-weight: 600;
        }
        
        .profile-info {
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar span, .dropdown-toggle span {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="flex items-center justify-center h-24">
            <div class="rounded-full bg-gray-200 w-20 h-20 flex items-center justify-center overflow-hidden border-2 border-white shadow-md">
                <?php
                $profilePicture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default_profile.jpg';
                ?>
                <img src="/assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>"
                     alt="Profile Picture"
                     class="w-full h-full object-cover"
                     onerror="this.src='/assets/DEFAULT_PROFILE.jpg'">
            </div>
        </div>
        <div class="px-4 py-2 text-center text-sm text-gray-300 profile-info">
            Logged in as: <br>
            <span class="font-bold text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
        </div>

        <!-- Navigation -->
        <nav class="mt-6">
            <ul class="space-y-1">
                <!-- Executive Dashboard -->
                <li>
                    <a href="/Admin executive/adminexecutive_dashboard.php" class="sidebar-link">
                        <i class="fas fa-chart-bar mr-3"></i> <span>Executive Dashboard</span>
                    </a>
                </li>
                
                <!-- HOA Dropdown -->
                <li class="dropdown-item">
                    <div class="dropdown-toggle" data-target="hoa-menu">
                        <div class="flex items-center">
                            <i class="fas fa-home mr-3"></i>
                            <span>HOA Management</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                    <ul id="hoa-menu" class="dropdown-menu">
                        <li>
                            <a href="/Admin executive/HOA/hoa_dashboard.php" class="submenu-link">
                                <i class="fas fa-tachometer-alt mr-2"></i> <span>HOA Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/HOA/hoa_payment.php" class="submenu-link">
                                <i class="fas fa-money-bill-wave mr-2"></i> <span>Payment Records</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/HOA/hoa_records.php" class="submenu-link">
                                <i class="fas fa-file-alt mr-2"></i> <span>HOA Records</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Operation Dropdown -->
                <li class="dropdown-item">
                    <div class="dropdown-toggle" data-target="operation-menu">
                        <div class="flex items-center">
                            <i class="fas fa-cogs mr-3"></i>
                            <span>Operation</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                    <ul id="operation-menu" class="dropdown-menu">
                        <li>
                            <a href="/Admin executive/Operation/operation_dashboard.php" class="submenu-link">
                                <i class="fas fa-tachometer-alt mr-2"></i> <span>Operation Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/Operation/operation_IDSAP.php" class="submenu-link">
                                <i class="fas fa-database mr-2"></i> <span>IDSAP Database</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/Operation/operation_panel.php" class="submenu-link">
                                <i class="fas fa-gavel mr-2"></i> <span>PDC Cases</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/Operation/meralco.php" class="submenu-link">
                                <i class="fas fa-bolt mr-2"></i> <span>Meralco Certificate</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/Operation/meralco_database.php" class="submenu-link">
                                <i class="fas fa-server mr-2"></i> <span>Meralco Database</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Admin Dropdown -->
                <li class="dropdown-item">
                    <div class="dropdown-toggle" data-target="admin-menu">
                        <div class="flex items-center">
                            <i class="fas fa-user-shield mr-3"></i>
                            <span>Admin</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                    <ul id="admin-menu" class="dropdown-menu">
                        <li>
                            <a href="/Admin executive/Admin/admin_dashboard.php" class="submenu-link">
                                <i class="fas fa-tachometer-alt mr-2"></i> <span>Admin Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/Admin/admin_panel.php" class="submenu-link">
                                <i class="fas fa-route mr-2"></i> <span>Routing Slip</span>
                            </a>
                        </li>
                        <li>
                            <a href="/Admin executive/Admin/admin_records.php" class="submenu-link">
                                <i class="fas fa-archive mr-2"></i> <span>Records</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Additional Links -->
                <li>
                    <a href="/Admin executive/backup.php" class="sidebar-link">
                        <i class="fas fa-database mr-3"></i> <span>Backup Data</span>
                    </a>
                </li>
                <li>
                    <a href="/Admin executive/employee.php" class="sidebar-link">
                        <i class="fas fa-users mr-3"></i> <span>Employees</span>
                    </a>
                </li>
                <li>
                    <a href="../../settings.php" class="sidebar-link">
                        <i class="fas fa-cog mr-3"></i> <span>Settings</span>
                    </a>
                </li>
                <li class="mt-10">
                    <a href="/logout.php" class="sidebar-link">
                        <i class="fas fa-sign-out-alt mr-3"></i> <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container mx-auto px-4 py-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Survey Summary Report</h1>
                <a href="operation_IDSAP.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Main
                </a>
            </div>

            <?php
            // Get overall statistics
            $totalSurveys = getData($conn, "SELECT COUNT(*) as total FROM survey_responses")[0]['total'];
            $todaySurveys = getData($conn, "SELECT COUNT(*) as total FROM survey_responses WHERE DATE(created_at) = CURDATE()")[0]['total'];
            $totalBarangays = count(getData($conn, "SELECT DISTINCT barangay FROM survey_responses"));
            
            // Get surveys by type
            $surveysByType = [];
            $allSurveys = getData($conn, "SELECT answers FROM survey_responses");
            foreach ($allSurveys as $survey) {
                // Properly decode the JSON structure
                $temp = json_decode($survey['answers'], true);
                
                // Handle different JSON structures
                if (isset($temp[2]) && is_array($temp[2])) {
                    $answers = $temp[2];
                } elseif (isset($temp['answers'])) {
                    $answers = $temp['answers'];
                } else {
                    $answers = $temp;
                }
                
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
                // Properly decode the JSON structure
                $temp = json_decode($survey['answers'], true);
                
                // Handle different JSON structures
                if (isset($temp[2]) && is_array($temp[2])) {
                    $answers = $temp[2];
                } elseif (isset($temp['answers'])) {
                    $answers = $temp['answers'];
                } else {
                    $answers = $temp;
                }
                
                // Check both 'on' string and 1 integer values
                if (isset($answers['pagibig']) && ($answers['pagibig'] === 'on' || $answers['pagibig'] == 1)) $membershipStats['pagibig']++;
                if (isset($answers['sss']) && ($answers['sss'] === 'on' || $answers['sss'] == 1)) $membershipStats['sss']++;
                if (isset($answers['philhealth']) && ($answers['philhealth'] === 'on' || $answers['philhealth'] == 1)) $membershipStats['philhealth']++;
                if (isset($answers['none-fund']) && ($answers['none-fund'] === 'on' || $answers['none-fund'] == 1)) $membershipStats['none']++;
            }
            ?>

            <!-- Overview Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="summary-card text-center">
                    <div class="stat-number"><?php echo $totalSurveys; ?></div>
                    <div class="text-gray-600">Total Surveys</div>
                </div>
                <div class="summary-card text-center">
                    <div class="stat-number"><?php echo $todaySurveys; ?></div>
                    <div class="text-gray-600">Today's Surveys</div>
                </div>
                <div class="summary-card text-center">
                    <div class="stat-number"><?php echo $totalBarangays; ?></div>
                    <div class="text-gray-600">Barangays Covered</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Surveys by Type -->
                <div class="summary-card">
                    <h3 class="text-xl font-semibold mb-4">Surveys by Type</h3>
                    <div class="h-64">
                        <canvas id="surveyTypeChart"></canvas>
                    </div>
                </div>

                <!-- Surveys by Barangay -->
                <div class="summary-card">
                    <h3 class="text-xl font-semibold mb-4">Surveys by Barangay</h3>
                    <div class="max-h-64 overflow-y-auto">
                        <?php foreach ($surveysByBarangay as $barangay): ?>
                            <div class="mb-3">
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium"><?php echo htmlspecialchars($barangay['barangay']); ?></span>
                                    <span><?php echo $barangay['count']; ?> surveys</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($barangay['count'] / $totalSurveys) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Membership Statistics -->
                <div class="summary-card">
                    <h3 class="text-xl font-semibold mb-4">Fund Membership</h3>
                    <div class="h-64">
                        <canvas id="membershipChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="summary-card">
                    <h3 class="text-xl font-semibold mb-4">Recent Surveys</h3>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        <?php 
                        $recentSurveys = getData($conn, 
                            "SELECT sr.* 
                             FROM survey_responses sr 
                             ORDER BY created_at DESC 
                             LIMIT 5"
                        );
                        
                        foreach ($recentSurveys as $survey): 
                            // Properly decode the JSON structure
                            $temp = json_decode($survey['answers'], true);
                            
                            // Handle different JSON structures
                            if (isset($temp[2]) && is_array($temp[2])) {
                                $answers = $temp[2];
                            } elseif (isset($temp['answers'])) {
                                $answers = $temp['answers'];
                            } else {
                                $answers = $temp;
                            }
                            
                            $hhFirstname = $answers['hh-firstname'] ?? '';
                            $hhSurname = $answers['hh-surname'] ?? '';
                            $name = trim($hhFirstname . ' ' . $hhSurname);
                            if (empty($name)) $name = 'Unnamed Household';
                        ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                            <div class="flex-1">
                                <div class="font-medium"><?php echo htmlspecialchars($name); ?></div>
                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($survey['barangay']); ?></div>
                            </div>
                            <div class="text-sm text-gray-500 whitespace-nowrap">
                                <?php echo date('M j, Y', strtotime($survey['created_at'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dropdown functionality
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetMenu = document.getElementById(targetId);
                const chevron = this.querySelector('.fa-chevron-down');
                
                // Toggle current menu
                targetMenu.classList.toggle('show');
                chevron.classList.toggle('rotate-180');
                
                // Close other open dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu.id !== targetId && menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        const otherChevron = menu.parentElement.querySelector('.fa-chevron-down');
                        if (otherChevron) {
                            otherChevron.classList.remove('rotate-180');
                        }
                    }
                });
            });
        });
        
        // Highlight current page in sidebar
        const currentPath = window.location.pathname;
        document.querySelectorAll('.sidebar-link, .submenu-link').forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
                // If it's a submenu link, open its parent dropdown
                if (link.classList.contains('submenu-link')) {
                    const dropdownItem = link.closest('.dropdown-item');
                    if (dropdownItem) {
                        const toggle = dropdownItem.querySelector('.dropdown-toggle');
                        const menu = dropdownItem.querySelector('.dropdown-menu');
                        const chevron = dropdownItem.querySelector('.fa-chevron-down');
                        if (menu && chevron) {
                            menu.classList.add('show');
                            chevron.classList.add('rotate-180');
                        }
                    }
                }
            }
        });

        // Survey Type Chart
        const surveyTypeCtx = document.getElementById('surveyTypeChart').getContext('2d');
        const surveyTypeChart = new Chart(surveyTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($surveysByType)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($surveysByType)); ?>,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                    ]
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
                            }
                        }
                    }
                }
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
                    data: <?php echo json_encode(array_values($membershipStats)); ?>,
                    backgroundColor: '#3B82F6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 10
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                size: 10
                            }
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
    </script>
</body>
</html>
    <?php
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

// Check for success messages
$deletedSuccess = isset($_GET['deleted']) && $_GET['deleted'] == 1;
$deletedBarangaySuccess = isset($_GET['deleted_barangay']) && $_GET['deleted_barangay'] == 1;
$updatedSuccess = isset($_GET['updated']) && $_GET['updated'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDSAP Survey System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
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
        
        /* Edit Mode Styles */
        .edit-mode {
            background: #f0f9ff;
            border: 2px solid #3b82f6;
            border-radius: 8px;
        }
        
        .view-mode {
            background: white;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .checkbox-input {
            width: 18px;
            height: 18px;
            border-radius: 4px;
        }
        
        /* Responsive sidebar */
        @media (max-width: 768px) {
            .sidebar-collapsed {
                width: 4rem;
            }
            .sidebar-collapsed .sidebar-text {
                display: none;
            }
            .sidebar-collapsed .logo-text {
                display: none;
            }
            .sidebar-collapsed .sidebar-toggle {
                justify-content: center;
            }
            .main-content {
                margin-left: 4rem;
            }
            
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }
        .modal-large {
            max-width: 800px;
        }

        /* FIXED: Sidebar dropdown styles */
        .dropdown-item {
            position: relative;
        }

        .dropdown-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
        }

        .dropdown-toggle i.fa-chevron-down {
            transition: transform 0.3s ease;
        }

        .dropdown-toggle.open i.fa-chevron-down {
            transform: rotate(180deg);
        }

        /* FIXED: Dropdown menu animation */
        .dropdown-menu {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: max-height 0.4s ease-out, opacity 0.3s ease-out;
        }

        .dropdown-menu.open {
            max-height: 500px;
            opacity: 1;
        }

        .submenu-link {
            padding: 8px 14px 8px 32px;
            display: block;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        .submenu-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .submenu-link.active {
            background-color: rgba(255, 255, 255, 0.15);
        }

        /* Sidebar link styles */
        .sidebar-link {
            display: block; 
            padding: 10px 14px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .active-link {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        /* Ensure dropdowns are clickable */
        nav ul {
            position: static;
        }
        
        /* Prevent text wrapping */
        .sidebar-link, .dropdown-toggle > div {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Filter section styling */
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
        
        /* Household members table in edit mode */
        .household-members-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .household-members-table th {
            background: #f3f4f6;
            padding: 8px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #e5e7eb;
        }
        
        .household-members-table td {
            padding: 6px;
            border: 1px solid #e5e7eb;
        }
        
        .household-members-table input,
        .household-members-table select {
            width: 100%;
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            border-radius: 3px;
            font-size: 12px;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
  <!-- Sidebar -->
  <div class="w-64 bg-gray-800 text-white flex flex-col">
    <div class="flex items-center justify-center h-24">
      <!-- Profile Picture Container -->
      <div class="rounded-full bg-gray-200 w-20 h-20 flex items-center justify-center overflow-hidden border-2 border-white shadow-md">
        <?php
        $profilePicture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default_profile.jpg';
        ?>
        <img src="/assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>"
             alt="Profile Picture"
             class="w-full h-full object-cover"
             onerror="this.src='/assets/DEFAULT_PROFILE.jpg'">
      </div>
    </div>
    <div class="px-4 py-2 text-center text-sm text-gray-300">
      Logged in as: <br>
      <span class="font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
    </div>
    
    <nav class="mt-6 flex-1 overflow-y-auto">
      <ul class="space-y-1">
        <!-- Executive Dashboard -->
        <li>
          <a href="/Admin executive/adminexecutive_dashboard.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-chart-bar mr-3"></i> Executive Dashboard
          </a>
        </li>
        
       <!-- HOA Dropdown -->
        <li class="dropdown-item">
          <div class="dropdown-toggle flex items-center justify-between py-3 px-4 cursor-pointer" data-target="hoa-menu">
            <div class="flex items-center">
              <i class="fas fa-home mr-3"></i>
              <span>HOA Management</span>
            </div>
            <i class="fas fa-chevron-down text-xs"></i>
          </div>
          <ul id="hoa-menu" class="dropdown-menu bg-gray-700">
            <li>
              <a href="/Admin executive/HOA/hoa_dashboard.php" class="submenu-link">
                <i class="fas fa-tachometer-alt mr-2"></i> HOA Dashboard
              </a>
            </li>
            <li>
              <a href="/Admin executive/HOA/hoa_payment.php" class="submenu-link">
                <i class="fas fa-money-bill-wave mr-2"></i> Payment Records
              </a>
            </li>
            <li>
              <a href="/Admin executive/HOA/hoa_records.php" class="submenu-link">
                <i class="fas fa-file-alt mr-2"></i> HOA Records
              </a>
            </li>
          </ul>
        </li>
        
        <!-- Operation Dropdown -->
        <li class="dropdown-item">
          <div class="dropdown-toggle flex items-center justify-between py-3 px-4 cursor-pointer" data-target="operation-menu">
            <div class="flex items-center">
              <i class="fas fa-cogs mr-3"></i>
              <span>Operation</span>
            </div>
            <i class="fas fa-chevron-down text-xs"></i>
          </div>
          <ul id="operation-menu" class="dropdown-menu bg-gray-700">
            <li>
              <a href="/Admin executive/Operation/operation_dashboard.php" class="submenu-link">
                <i class="fas fa-tachometer-alt mr-2"></i> Operation Dashboard
              </a>
            </li>
            <li>
              <a href="/Admin executive/Operation/operation_IDSAP.php" class="submenu-link">
                <i class="fas fa-database mr-2"></i> IDSAP Database
              </a>
            </li>
            <li>
              <a href="/Admin executive/Operation/operation_panel.php" class="submenu-link">
                <i class="fas fa-gavel mr-2"></i> PDC Cases
              </a>
            </li>
            <li>
              <a href="/Admin executive/Operation/meralco.php" class="submenu-link">
                <i class="fas fa-bolt mr-2"></i> Meralco Certificate
              </a>
            </li>
            <li>
              <a href="/Admin executive/Operation/meralco_database.php" class="submenu-link">
                <i class="fas fa-server mr-2"></i> Meralco Database
              </a>
            </li>
          </ul>
        </li>
        
        <!-- Admin Dropdown -->
        <li class="dropdown-item">
          <div class="dropdown-toggle flex items-center justify-between py-3 px-4 cursor-pointer" data-target="admin-menu">
            <div class="flex items-center">
              <i class="fas fa-user-shield mr-3"></i>
              <span>Admin</span>
            </div>
            <i class="fas fa-chevron-down text-xs"></i>
          </div>
          <ul id="admin-menu" class="dropdown-menu bg-gray-700">
            <li>
              <a href="/Admin executive/Admin/admin_dashboard.php" class="submenu-link">
                <i class="fas fa-tachometer-alt mr-2"></i> Admin Dashboard
              </a>
            </li>
            <li>
              <a href="/Admin executive/Admin/admin_panel.php" class="submenu-link">
                <i class="fas fa-route mr-2"></i> Routing Slip
              </a>
            </li>
            <li>
              <a href="/Admin executive/Admin/admin_records.php" class="submenu-link">
                <i class="fas fa-archive mr-2"></i> Records
              </a>
            </li>
          </ul>
        </li>
        
          <!-- Additional Links -->
        <li>
          <a href="/Admin executive/backup.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-database mr-3"></i> Backup Data
          </a>
        </li>
        <li>
          <a href="/Admin executive/employee.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-users mr-3"></i> Employees
          </a>
        </li>
        <li>
  <a href="../../settings.php" class="sidebar-link flex items-center py-3 px-4">
    <i class="fas fa-cog mr-3"></i> Settings
  </a>
</li>
        <li>
          <a href="/logout.php" class="sidebar-link flex items-center py-3 px-4 mt-10">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </li>
      </ul>
    </nav>
  </div>
    <!-- Main Content -->
    <div class="flex-1 p-4 md:p-6 overflow-auto main-content">
        <!-- Success Messages -->
        <?php if ($deletedSuccess): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Record deleted successfully.
        </div>
        <?php endif; ?>
        
        <?php if ($deletedBarangaySuccess): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Barangay surveys deleted successfully.
        </div>
        <?php endif; ?>
        
        <?php if ($updatedSuccess): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Record updated successfully.
        </div>
        <?php endif; ?>

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
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400"></i>
                            <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($selectedSurveyType); ?>" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">
                                <?php echo htmlspecialchars($selectedSurveyType); ?>
                            </a>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if ($selectedIndividualId): ?>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400"></i>
                            <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">
                                <?php 
                                if ($selectedIndividualId) {
                                    $individual = getData($conn, "SELECT * FROM survey_responses WHERE id = " . intval($selectedIndividualId));
                                    if (count($individual) > 0) {
                                        // FIXED: Use the helper function
                                        $answers = decodeSurveyAnswers($individual[0]['answers']);
                                        $name = trim(($answers['hh-firstname'] ?? '') . ' ' . ($answers['hh-surname'] ?? ''));
                                        echo htmlspecialchars($name ?: 'Details');
                                    } else {
                                        echo 'Details';
                                    }
                                }
                                ?>
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
                    <button onclick="showDeleteBarangayModal('<?php echo urlencode($selectedBarangay); ?>')" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center">
                        <i class="fas fa-trash mr-2"></i> Delete All Surveys
                    </button>
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
        // BAGONG COMPLETE FILTER FUNCTION
        function filterIndividualsBySurveyType($allIndividuals, $selectedSurveyType) {
            $filtered = [];
            
            foreach ($allIndividuals as $individual) {
                // USE THE EXISTING decodeSurveyAnswers function
                $answers = decodeSurveyAnswers($individual['answers']);
                
                // Get survey type and other type fields - check multiple possible keys
                $storedSurveyType = $answers['survey-type'] ?? $answers['survey_type'] ?? '';
                $storedOtherType = $answers['other-survey-type'] ?? $answers['other_survey_type'] ?? '';
                
                // Clean the data
                $storedSurveyType = trim($storedSurveyType);
                $storedOtherType = trim($storedOtherType);
                
                // CASE 1: OTHERS Survey Type
                if ($selectedSurveyType === "OTHERS") {
                    // For OTHERS, show only those with filled other-survey-type field
                    if (!empty($storedOtherType)) {
                        $filtered[] = $individual;
                    }
                    continue;
                }
                
                // CASE 2: Exact match
                if ($storedSurveyType === $selectedSurveyType) {
                    $filtered[] = $individual;
                    continue;
                }
                
                // CASE 3: Case-insensitive match
                if (strcasecmp($storedSurveyType, $selectedSurveyType) === 0) {
                    $filtered[] = $individual;
                    continue;
                }
                
                // CASE 4: For "CENSUS" types - check if it's a census type
                if (strpos($selectedSurveyType, 'CENSUS') !== false) {
                    // Check if stored type contains "CENSUS" 
                    if (stripos($storedSurveyType, 'CENSUS') !== false) {
                        // Now check for specific census subtypes
                        if ($selectedSurveyType === 'CENSUS - PDC') {
                            if (stripos($storedSurveyType, 'PDC') !== false || 
                                stripos($storedSurveyType, 'CENSUS - PDC') !== false) {
                                $filtered[] = $individual;
                                continue;
                            }
                        } 
                        elseif ($selectedSurveyType === 'CENSUS - HOA') {
                            if (stripos($storedSurveyType, 'HOA') !== false || 
                                stripos($storedSurveyType, 'CENSUS - HOA') !== false) {
                                $filtered[] = $individual;
                                continue;
                            }
                        }
                        elseif ($selectedSurveyType === 'CENSUS - WATERWAYS') {
                            if (stripos($storedSurveyType, 'WATERWAYS') !== false || 
                                stripos($storedSurveyType, 'CENSUS - WATERWAYS') !== false) {
                                $filtered[] = $individual;
                                continue;
                            }
                        }
                    }
                }
                
                // CASE 5: For "IDSAP" types - check if it's an IDSAP type
                if (strpos($selectedSurveyType, 'IDSAP') !== false) {
                    // Check if stored type contains "IDSAP" 
                    if (stripos($storedSurveyType, 'IDSAP') !== false) {
                        // Now check for specific IDSAP subtypes
                        if ($selectedSurveyType === 'IDSAP - FIRE VICTIM') {
                            if (stripos($storedSurveyType, 'FIRE') !== false || 
                                stripos($storedSurveyType, 'FIRE VICTIM') !== false) {
                                $filtered[] = $individual;
                                continue;
                            }
                        } 
                        elseif ($selectedSurveyType === 'IDSAP - FLOOD') {
                            if (stripos($storedSurveyType, 'FLOOD') !== false) {
                                $filtered[] = $individual;
                                continue;
                            }
                        }
                        elseif ($selectedSurveyType === 'IDSAP - EARTHQUAKE') {
                            if (stripos($storedSurveyType, 'EARTHQUAKE') !== false) {
                                $filtered[] = $individual;
                                continue;
                            }
                        }
                    }
                }
                
                // CASE 6: Check for partial string match (as fallback)
                if (stripos($storedSurveyType, $selectedSurveyType) !== false) {
                    $filtered[] = $individual;
                    continue;
                }
            }
            
            return $filtered;
        }
        
        // Get all individuals from the barangay
        $allIndividuals = getData($conn, 
            "SELECT * FROM survey_responses 
             WHERE barangay = '" . $conn->real_escape_string($selectedBarangay) . "' 
             ORDER BY created_at DESC");
        
        // Filter individuals using the improved function
        $individuals = filterIndividualsBySurveyType($allIndividuals, $selectedSurveyType);
        ?>
        <div id="individualsPanel" class="bg-white p-4 md:p-6 rounded-lg shadow-md mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                <h2 class="text-lg md:text-xl font-semibold">
                    Individuals - <?php echo htmlspecialchars($selectedBarangay); ?> - <?php echo htmlspecialchars($selectedSurveyType); ?>
                    <span class="text-sm font-normal text-gray-600">(<?php echo count($individuals); ?> found)</span>
                </h2>
                <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center w-full md:w-auto justify-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Survey Types
                </a>
            </div>
            
            <!-- ADDED FILTER SECTION -->
            <div class="filter-section mb-4">
                <h3 class="font-medium text-gray-700 mb-2">Filter Individuals</h3>
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Search by Name</label>
                        <input type="text" id="searchIndividual" placeholder="Search individuals..." class="form-input">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filter by Age</label>
                        <select id="filterAge" class="form-select">
                            <option value="">All Ages</option>
                            <option value="0-18">0-18 years</option>
                            <option value="19-30">19-30 years</option>
                            <option value="31-50">31-50 years</option>
                            <option value="51-65">51-65 years</option>
                            <option value="66+">66+ years</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filter by Sex</label>
                        <select id="filterSex" class="form-select">
                            <option value="">All Genders</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Sort by</label>
                        <select id="sortBy" class="form-select">
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                            <option value="age_asc">Age (Low to High)</option>
                            <option value="age_desc">Age (High to Low)</option>
                            <option value="date_desc">Date (Newest First)</option>
                            <option value="date_asc">Date (Oldest First)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button id="applyFilters" class="mt-6 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
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
                    <tbody class="bg-white divide-y divide-gray-200" id="individualsTableBody">
                        <?php if (count($individuals) > 0): ?>
                            <?php foreach ($individuals as $individual): ?>
                            <?php 
                            // DECODE USING THE SAME FUNCTION AS IN DETAILS VIEW
                            $answers = decodeSurveyAnswers($individual['answers']);
                            
                            // Extract HOUSEHOLD HEAD name using the correct field names
                            $hhSurname = $answers['hh-surname'] ?? '';
                            $hhFirstname = $answers['hh-firstname'] ?? '';
                            $hhMiddlename = $answers['hh-middlename'] ?? '';
                            $hhMi = $answers['hh-mi'] ?? '';
                            
                            // Construct household head name
                            $name = trim("$hhFirstname " . ($hhMi ? "$hhMi. " : "") . "$hhMiddlename $hhSurname");
                            if (empty(trim($name))) {
                                // Fallback to other name fields if household head not found
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
                                // Fallback to other age fields
                                if (isset($answers['age'])) {
                                    $age = $answers['age'];
                                } elseif (isset($answers['member_age'])) {
                                    $age = $answers['member_age'];
                                }
                            }
                            
                            // Extract household head sex
                            $sex = $answers['hh-sex'] ?? 'N/A';
                            
                            // Construct COMPLETE ADDRESS from address components
                            $houseNo = $answers['house-no'] ?? '';
                            $lotNo = $answers['lot-no'] ?? '';
                            $building = $answers['building'] ?? '';
                            $block = $answers['block'] ?? '';
                            $street = $answers['street'] ?? '';
                            
                            $completeAddress = trim("$houseNo $lotNo $building $block $street");
                            $trimmedAddress = trim($completeAddress);
                            
                            // Check if address is empty after trimming
                            if (empty($trimmedAddress)) {
                                // Fallback to the general address field
                                $completeAddress = $individual['address'] ?? 'No address provided';
                                $trimmedAddress = trim($completeAddress);
                            }
                            
                            // Add barangay to complete address
                            $barangayName = $individual['barangay'] ?? '';
                            if (!empty($barangayName) && !empty($trimmedAddress)) {
                                $completeAddress .= ", " . $barangayName;
                            } elseif (!empty($barangayName)) {
                                $completeAddress = $barangayName;
                            }
                            
                            // Get survey date from metadata
                            $temp = json_decode($individual['answers'], true);
                            $surveyDate = $temp[0] ?? $temp['survey_date'] ?? '';
                            $surveyTime = $temp[1] ?? $temp['survey_time'] ?? '';
                            ?>
                            <tr data-name="<?php echo htmlspecialchars(strtolower($name)); ?>" 
                                data-age="<?php echo is_numeric($age) ? $age : 0; ?>" 
                                data-sex="<?php echo htmlspecialchars(strtolower($sex)); ?>"
                                data-date="<?php echo !empty($surveyDate) ? strtotime($surveyDate . ' ' . $surveyTime) : strtotime($individual['created_at']); ?>">
                                <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php echo htmlspecialchars($name); ?>
                                </td>
                                <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo htmlspecialchars($age); ?>
                                </td>
                                <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo htmlspecialchars($sex); ?>
                                </td>
                                <td class="px-4 md:px-6 py-4 text-sm">
                                    <?php echo htmlspecialchars($completeAddress); ?>
                                </td>
                                <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm">
                                    <?php 
                                    if (!empty($surveyDate) && !empty($surveyTime)) {
                                        $surveyDateTime = new DateTime($surveyDate . ' ' . $surveyTime);
                                        echo $surveyDateTime->format('M j, Y g:i A');
                                    } else {
                                        $surveyDate = new DateTime($individual['created_at']);
                                        echo $surveyDate->format('M j, Y g:i A');
                                    }
                                    ?>
                                </td>
                                <td class="px-4 md:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($selectedSurveyType); ?>&individual_id=<?php echo $individual['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 mr-3">
                                        View
                                    </a>
                                    <button onclick="showDeleteModal(<?php echo $individual['id']; ?>, '<?php echo htmlspecialchars(addslashes($name)); ?>', '<?php echo urlencode($selectedBarangay); ?>', '<?php echo urlencode($selectedSurveyType); ?>')" 
                                            class="text-red-600 hover:text-red-900">
                                        Delete
                                    </button>
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
        
        $editMode = isset($_GET['edit']) && $_GET['edit'] == 'true';

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
        <div id="individualDetailsPanel" class="<?php echo $editMode ? 'edit-mode' : 'view-mode'; ?> p-4 md:p-6 rounded-lg shadow-md mb-6">
            <!-- Action buttons -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h2 class="text-lg md:text-xl font-semibold">
                    <?php echo $editMode ? 'Edit Record - ' : 'Survey Details - '; ?><?php echo htmlspecialchars($name); ?>
                </h2>
                <div class="flex gap-2">
                    <?php if ($editMode): ?>
                        <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($selectedSurveyType); ?>&individual_id=<?php echo $selectedIndividualId; ?>" 
                           class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center">
                            <i class="fas fa-times mr-2"></i> Cancel Edit
                        </a>
                        <button type="submit" form="editForm" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    <?php else: ?>
                        <!-- Delete Button -->
                        <button onclick="showDeleteModal(<?php echo $individual['id']; ?>, '<?php echo htmlspecialchars(addslashes($name)); ?>', '<?php echo urlencode($selectedBarangay); ?>', '<?php echo urlencode($selectedSurveyType); ?>')" 
                                class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 flex items-center">
                            <i class="fas fa-trash mr-2"></i> Delete Record
                        </button>
                        
                        <!-- Edit Button -->
                        <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($selectedSurveyType); ?>&individual_id=<?php echo $selectedIndividualId; ?>&edit=true" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center">
                            <i class="fas fa-edit mr-2"></i> Edit Record
                        </a>
                        
                        <!-- Back Button -->
                        <a href="?barangay=<?php echo urlencode($selectedBarangay); ?>&survey_type=<?php echo urlencode($selectedSurveyType); ?>" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to List
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($editMode): ?>
            <!-- EDIT FORM -->
            <form id="editForm" method="POST" action="" class="space-y-6">
                <input type="hidden" name="action" value="update_record">
                <input type="hidden" name="individual_id" value="<?php echo $selectedIndividualId; ?>">
                <input type="hidden" name="barangay" value="<?php echo htmlspecialchars($selectedBarangay); ?>">
                <input type="hidden" name="survey_type" value="<?php echo htmlspecialchars($selectedSurveyType); ?>">
                
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
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Survey Type</label>
                            <select name="survey-type" class="form-select">
                                <option value="">Select Type</option>
                                <?php foreach ($surveyTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($surveyType === $type) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Other Survey Type</label>
                            <input type="text" name="other-survey-type" value="<?php echo htmlspecialchars($otherSurveyType); ?>" class="form-input" placeholder="Specify if other">
                        </div>
                        <div>
                            <span class="font-medium">Enumerator:</span>
                            <span><?php echo htmlspecialchars($individual['enumerator_name'] ?? 'N/A'); ?></span>
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
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">First Name</label>
                                        <input type="text" name="hh-firstname" value="<?php echo htmlspecialchars($hhFirstname); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Surname</label>
                                        <input type="text" name="hh-surname" value="<?php echo htmlspecialchars($hhSurname); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Middle Name</label>
                                        <input type="text" name="hh-middlename" value="<?php echo htmlspecialchars($hhMiddlename); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">M.I.</label>
                                        <input type="text" name="hh-mi" value="<?php echo htmlspecialchars($hhMi); ?>" class="form-input" maxlength="2">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Age</label>
                                        <input type="number" name="hh-age" value="<?php echo htmlspecialchars($hhAge); ?>" class="form-input" min="0" max="120">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Sex</label>
                                        <select name="hh-sex" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Male" <?php echo ($hhSex === 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($hhSex === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Birthdate</label>
                                        <input type="date" name="hh-birthdate" value="<?php echo htmlspecialchars($hhBirthdate); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Civil Status</label>
                                        <select name="hh-civil-status" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Single" <?php echo ($hhCivilStatus === 'Single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo ($hhCivilStatus === 'Married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="Widowed" <?php echo ($hhCivilStatus === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                            <option value="Separated" <?php echo ($hhCivilStatus === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="hh-senior-citizen" class="checkbox-input" <?php echo ($hhSeniorCitizen === 'Yes') ? 'checked' : ''; ?>>
                                            <span class="text-xs">Senior Citizen</span>
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="hh-pwd" class="checkbox-input" <?php echo ($hhPwd === 'Yes') ? 'checked' : ''; ?>>
                                            <span class="text-xs">PWD</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Spouse Information -->
                            <div>
                                <h4 class="font-medium mb-2">Spouse Information</h4>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">First Name</label>
                                        <input type="text" name="spouse-firstname" value="<?php echo htmlspecialchars($spouseFirstname); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Surname</label>
                                        <input type="text" name="spouse-surname" value="<?php echo htmlspecialchars($spouseSurname); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Middle Name</label>
                                        <input type="text" name="spouse-middlename" value="<?php echo htmlspecialchars($spouseMiddlename); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">M.I.</label>
                                        <input type="text" name="spouse-mi" value="<?php echo htmlspecialchars($spouseMi); ?>" class="form-input" maxlength="2">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Age</label>
                                        <input type="number" name="spouse-age" value="<?php echo htmlspecialchars($spouseAge); ?>" class="form-input" min="0" max="120">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Sex</label>
                                        <select name="spouse-sex" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Male" <?php echo ($spouseSex === 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($spouseSex === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Birthdate</label>
                                        <input type="date" name="spouse-birthdate" value="<?php echo htmlspecialchars($spouseBirthdate); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Civil Status</label>
                                        <select name="spouse-civil-status" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Single" <?php echo ($spouseCivilStatus === 'Single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo ($spouseCivilStatus === 'Married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="Widowed" <?php echo ($spouseCivilStatus === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                            <option value="Separated" <?php echo ($spouseCivilStatus === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="spouse-senior-citizen" class="checkbox-input" <?php echo ($spouseSeniorCitizen === 'Yes') ? 'checked' : ''; ?>>
                                            <span class="text-xs">Senior Citizen</span>
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="spouse-pwd" class="checkbox-input" <?php echo ($spousePwd === 'Yes') ? 'checked' : ''; ?>>
                                            <span class="text-xs">PWD</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tenurial Status -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium mb-4 border-b pb-2">II. Tenurial Status</h3>
                        
                        <div class="space-y-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">House No.</label>
                                <input type="text" name="house-no" value="<?php echo htmlspecialchars($houseNo); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lot No.</label>
                                <input type="text" name="lot-no" value="<?php echo htmlspecialchars($lotNo); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Building</label>
                                <input type="text" name="building" value="<?php echo htmlspecialchars($building); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Block</label>
                                <input type="text" name="block" value="<?php echo htmlspecialchars($block); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                                <input type="text" name="street" value="<?php echo htmlspecialchars($street); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                                <input type="text" name="address" value="<?php echo htmlspecialchars($individual['barangay'] ?? ''); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nature of Land</label>
                                <select name="land-nature" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Public" <?php echo ($landNature === 'Public') ? 'selected' : ''; ?>>Public</option>
                                    <option value="Private" <?php echo ($landNature === 'Private') ? 'selected' : ''; ?>>Private</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lot Status</label>
                                <select name="lot-status" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Owned" <?php echo ($lotStatus === 'Owned') ? 'selected' : ''; ?>>Owned</option>
                                    <option value="Rented" <?php echo ($lotStatus === 'Rented') ? 'selected' : ''; ?>>Rented</option>
                                    <option value="Informal Settler" <?php echo ($lotStatus === 'Informal Settler') ? 'selected' : ''; ?>>Informal Settler</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Owner (RFO/Renter)</label>
                                <input type="text" name="name-rfo-renter" value="<?php echo htmlspecialchars($nameRfoRenter); ?>" class="form-input">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Membership -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">III. Membership</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium mb-3">Fund Membership</h4>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="pagibig" class="checkbox-input" <?php echo ($pagibig === 'Yes') ? 'checked' : ''; ?>>
                                        <span>PAG-IBIG</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sss" class="checkbox-input" <?php echo ($sss === 'Yes') ? 'checked' : ''; ?>>
                                        <span>SSS</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="gsis" class="checkbox-input" <?php echo ($gsis === 'Yes') ? 'checked' : ''; ?>>
                                        <span>GSIS</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="philhealth" class="checkbox-input" <?php echo ($philhealth === 'Yes') ? 'checked' : ''; ?>>
                                        <span>PhilHealth</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="none-fund" class="checkbox-input" <?php echo ($noneFund === 'Yes') ? 'checked' : ''; ?>>
                                        <span>None</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="other-fund" class="checkbox-input" <?php echo ($otherFund === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Other</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium mb-3">Organization</h4>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="cso" class="checkbox-input" <?php echo ($cso === 'Yes') ? 'checked' : ''; ?>>
                                        <span>CSO</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="hoa" class="checkbox-input" <?php echo ($hoa === 'Yes') ? 'checked' : ''; ?>>
                                        <span>HOA</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="cooperative" class="checkbox-input" <?php echo ($cooperative === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Cooperative</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="none-org" class="checkbox-input" <?php echo ($noneOrg === 'Yes') ? 'checked' : ''; ?>>
                                        <span>None</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="other-org" class="checkbox-input" <?php echo ($otherOrg === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Other</span>
                                    </label>
                                </div>
                                <div class="form-group col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Organization Name</label>
                                    <input type="text" name="name-organization" value="<?php echo htmlspecialchars($nameOrganization); ?>" class="form-input">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Household Members -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2 flex justify-between items-center">
                        <span>IV. Household Member Data</span>
                        <button type="button" onclick="addHouseholdMember()" class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                            <i class="fas fa-plus mr-1"></i> Add Member
                        </button>
                    </h3>
                    
                    <div id="householdMembersContainer" class="space-y-4">
                        <?php if (!empty($householdMembers)): ?>
                            <?php foreach ($householdMembers as $index => $member): 
                                $memberNum = $index + 1;
                            ?>
                            <div class="member-entry bg-white p-4 rounded border">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-medium">Member #<?php echo $memberNum; ?></h5>
                                    <button type="button" onclick="removeHouseholdMember(this)" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">First Name</label>
                                        <input type="text" name="member_firstname[]" value="<?php echo htmlspecialchars($member['firstname'] ?? $member['member_firstname'] ?? ''); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Surname</label>
                                        <input type="text" name="member_surname[]" value="<?php echo htmlspecialchars($member['surname'] ?? $member['member_surname'] ?? ''); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Middle Name</label>
                                        <input type="text" name="member_middlename[]" value="<?php echo htmlspecialchars($member['middlename'] ?? $member['member_middlename'] ?? ''); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">M.I.</label>
                                        <input type="text" name="member_mi[]" value="<?php echo htmlspecialchars($member['mi'] ?? $member['member_mi'] ?? ''); ?>" class="form-input" maxlength="2">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Relationship</label>
                                        <input type="text" name="member_relationship[]" value="<?php echo htmlspecialchars($member['relationship'] ?? $member['member_relationship'] ?? ''); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Age</label>
                                        <input type="number" name="member_age[]" value="<?php echo htmlspecialchars($member['age'] ?? $member['member_age'] ?? ''); ?>" class="form-input" min="0" max="120">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Sex</label>
                                        <select name="member_sex[]" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Male" <?php echo (($member['sex'] ?? $member['member_sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo (($member['sex'] ?? $member['member_sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Birthdate</label>
                                        <input type="date" name="member_birthdate[]" value="<?php echo htmlspecialchars($member['birthdate'] ?? $member['member_birthdate'] ?? ''); ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Education</label>
                                        <input type="text" name="member_education[]" value="<?php echo htmlspecialchars($member['education'] ?? $member['member_education'] ?? ''); ?>" class="form-input">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-gray-500">
                                No household members added yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Remarks -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-4 border-b pb-2">V. Remarks</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <h4 class="font-medium mb-2">Shelter Needs</h4>
                            <div class="space-y-2 text-sm">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="security-upgrading" class="checkbox-input" <?php echo ($securityUpgrading === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Tenurial Upgrading</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="shelter-provision" class="checkbox-input" <?php echo ($shelterProvision === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Shelter Provision</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="structural-upgrading" class="checkbox-input" <?php echo ($structuralUpgrading === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Structural Upgrading</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="infrastructure-upgrading" class="checkbox-input" <?php echo ($infrastructureUpgrading === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Infrastructure Upgrading</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Other Remarks</label>
                                    <input type="text" name="other-remarks-text" value="<?php echo htmlspecialchars($otherRemarks); ?>" class="form-input">
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium mb-2">Household Classification</h4>
                            <div class="space-y-2 text-sm">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="single-hh" class="checkbox-input" <?php echo ($singleHh === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Single HH</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="displaced-unit" class="checkbox-input" <?php echo ($displacedUnit === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Displaced Unit</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="doubled-up" class="checkbox-input" <?php echo ($doubledUp === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Doubled Up HH</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="displacement-concern" class="checkbox-input" <?php echo ($displacementConcern === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Displacement Concern</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium mb-2">Census Remarks</h4>
                            <div class="space-y-2 text-sm">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="odc" class="checkbox-input" <?php echo ($odc === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Out During Census</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="aho" class="checkbox-input" <?php echo ($aho === 'Yes') ? 'checked' : ''; ?>>
                                        <span>Absentee House Owner</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Other Census Remarks</label>
                                    <input type="text" name="census-others-text" value="<?php echo htmlspecialchars($censusOthers); ?>" class="form-input">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            
            <?php else: ?>
            <!-- VIEW MODE (Existing code) -->
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

                <!-- Map Controls -->
                <div class="mt-3 flex gap-2">
                    <button onclick="zoomToLocation()" class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600">
                        <i class="fas fa-search-plus mr-1"></i> Zoom to Location
                    </button>
                    <button onclick="openInOSM()" class="px-3 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600">
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
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Barangay Panel (shown when no specific selection is made) -->
        <?php if (!$selectedBarangay && !$selectedSurveyType && !$selectedIndividualId): ?>
        <div id="barangayPanel" class="bg-white p-4 md:p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-lg md:text-xl font-semibold mb-4 md:mb-6 border-b pb-2">Barangay Survey Selection</h2>
            
            <!-- Add Barangay Management Section -->
            <div class="mb-6 bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <h3 class="text-lg font-medium mb-3 text-yellow-800">Barangay Management</h3>
                <div class="flex flex-col md:flex-row gap-2">
                    <button onclick="showDeleteBarangayModal()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center">
                        <i class="fas fa-trash mr-2"></i> Delete Barangay Surveys
                    </button>
                    <a href="?export=excel" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center">
                        <i class="fas fa-download mr-2"></i> Export All Data
                    </a>
                </div>
            </div>
            
            <div class="mb-4 flex flex-col md:flex-row items-start md:items-center gap-2">
                <input type="text" id="searchBarangay" placeholder="Search barangay..." class="px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition w-full">
                <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 w-full md:w-auto flex items-center justify-center">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                <?php foreach ($barangays as $barangay): 
                    // Get survey count for this barangay
                    $surveyCount = getData($conn, 
                        "SELECT COUNT(*) as count FROM survey_responses WHERE barangay = '" . $conn->real_escape_string($barangay['barangay']) . "'"
                    )[0]['count'];
                ?>
                    <div class="survey-btn bg-white p-3 md:p-4 text-center rounded-lg border border-gray-200 hover:border-blue-400 relative group">
                        <a href="?barangay=<?php echo urlencode($barangay['barangay']); ?>" class="block">
                            <i class="fas fa-map-marker-alt text-blue-500 text-lg md:text-xl mb-1 md:mb-2"></i>
                            <h6 class="text-xs md:text-sm font-medium text-gray-800"><?php echo htmlspecialchars($barangay['barangay']); ?></h6>
                            <span class="absolute top-1 right-1 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo $surveyCount; ?>
                            </span>
                        </a>
                        <!-- Delete button appears on hover -->
                        <button type="button" 
                                onclick="showDeleteBarangayModal('<?php echo urlencode($barangay['barangay']); ?>')"
                                class="absolute top-1 left-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
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
                    <a href="?view=summary" class="w-full text-left block">
                        <div class="bg-white p-3 md:p-4 rounded shadow-sm border border-gray-200 custom-card flex items-center mobile-tap-target">
                            <i class="fas fa-chart-pie text-purple-500 text-lg md:text-xl mr-3"></i>
                            <span class="font-medium text-sm md:text-base">View Survey Summary</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Individual Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 class="text-lg font-semibold mb-4">Confirm Deletion</h3>
            <p id="deleteMessage" class="mb-6">Are you sure you want to delete this record?</p>
            <div class="flex justify-end gap-3">
                <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</button>
                <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Delete</button>
            </div>
        </div>
    </div>

    <!-- Delete Barangay Confirmation Modal -->
    <div id="deleteBarangayModal" class="modal">
        <div class="modal-content">
            <h3 class="text-lg font-semibold mb-4">Delete Barangay Surveys</h3>
            <div class="mb-4">
                <label for="barangaySelect" class="block text-sm font-medium text-gray-700 mb-2">Select Barangay to Delete:</label>
                <select id="barangaySelect" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Barangay --</option>
                    <?php foreach ($barangays as $barangay): ?>
                        <option value="<?php echo urlencode($barangay['barangay']); ?>">
                            <?php echo htmlspecialchars($barangay['barangay']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="text-red-600 text-sm mb-4">Warning: This will delete ALL surveys from the selected barangay. This action cannot be undone.</p>
            <div class="flex justify-end gap-3">
                <button onclick="hideDeleteBarangayModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</button>
                <button onclick="deleteBarangaySurveys()" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Delete All Surveys</button>
            </div>
        </div>
    </div>

    <script>
    // FIXED: Improved dropdown functionality
    function initializeDropdowns() {
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        
        dropdownToggles.forEach(toggle => {
            // Remove any existing event listeners
            const newToggle = toggle.cloneNode(true);
            toggle.parentNode.replaceChild(newToggle, toggle);
            
            // Add click event to the new toggle
            newToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const targetId = this.getAttribute('data-target');
                const targetMenu = document.getElementById(targetId);
                
                if (!targetMenu) return;
                
                const isOpen = targetMenu.classList.contains('open');
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu.id !== targetId) {
                        menu.classList.remove('open');
                    }
                });
                
                document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                    if (toggle !== this) {
                        toggle.classList.remove('open');
                    }
                });
                
                // Toggle the clicked dropdown
                if (isOpen) {
                    targetMenu.classList.remove('open');
                    this.classList.remove('open');
                } else {
                    targetMenu.classList.add('open');
                    this.classList.add('open');
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-item')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('open');
                });
                document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                    toggle.classList.remove('open');
                });
            }
        });
    }
    
    // Global variables for delete functionality
    window.currentDeleteId = null;
    window.currentDeleteBarangay = null;
    window.currentDeleteSurveyType = null;

    // Delete Individual Functions - SIMPLIFIED VERSION
    window.showDeleteModal = function(individualId, individualName, barangay = null, surveyType = null) {
        window.currentDeleteId = individualId;
        window.currentDeleteBarangay = barangay;
        window.currentDeleteSurveyType = surveyType;
        
        const modal = document.getElementById('deleteModal');
        const message = document.getElementById('deleteMessage');
        
        message.textContent = `Are you sure you want to delete the record for "${individualName}"? This action cannot be undone.`;
        modal.style.display = 'block';
    }

    window.hideDeleteModal = function() {
        const modal = document.getElementById('deleteModal');
        modal.style.display = 'none';
        window.currentDeleteId = null;
        window.currentDeleteBarangay = null;
        window.currentDeleteSurveyType = null;
    }

    window.confirmDelete = function() {
        if (!window.currentDeleteId) {
            alert('Error: No record selected for deletion.');
            return;
        }
        
        // Build the delete URL
        let deleteUrl = `?action=delete_individual&id=${window.currentDeleteId}`;
        if (window.currentDeleteBarangay) {
            deleteUrl += `&barangay=${encodeURIComponent(window.currentDeleteBarangay)}`;
        }
        if (window.currentDeleteSurveyType) {
            deleteUrl += `&survey_type=${encodeURIComponent(window.currentDeleteSurveyType)}`;
        }
        
        window.location.href = deleteUrl;
    }

    // Barangay Delete Functions
    window.showDeleteBarangayModal = function(preSelectedBarangay = '') {
        const modal = document.getElementById('deleteBarangayModal');
        const select = document.getElementById('barangaySelect');
        
        if (preSelectedBarangay) {
            select.value = preSelectedBarangay;
        }
        
        modal.style.display = 'block';
    }

    window.hideDeleteBarangayModal = function() {
        const modal = document.getElementById('deleteBarangayModal');
        modal.style.display = 'none';
    }

    window.deleteBarangaySurveys = function() {
        const barangay = document.getElementById('barangaySelect').value;
        
        if (!barangay) {
            alert('Please select a barangay to delete.');
            return;
        }
        
        if (!confirm(`WARNING: This will delete ALL surveys from ${decodeURIComponent(barangay)}. This action cannot be undone. Are you absolutely sure?`)) {
            return;
        }
        
        // Redirect to delete URL
        window.location.href = `?action=delete_barangay&barangay=${barangay}`;
    }

    // Search functionality
    function initializeSearch() {
        // Barangay search
        const searchBarangay = document.getElementById('searchBarangay');
        if (searchBarangay) {
            searchBarangay.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const buttons = document.querySelectorAll('#barangayPanel .survey-btn');
                
                buttons.forEach(button => {
                    const textElement = button.querySelector('h6');
                    if (textElement) {
                        const barangayName = textElement.textContent.toLowerCase();
                        button.style.display = barangayName.includes(searchTerm) ? 'block' : 'none';
                    }
                });
            });
        }

        // Individual search and filter
        const searchIndividual = document.getElementById('searchIndividual');
        const filterAge = document.getElementById('filterAge');
        const filterSex = document.getElementById('filterSex');
        const sortBy = document.getElementById('sortBy');
        const applyFilters = document.getElementById('applyFilters');
        
        if (applyFilters) {
            applyFilters.addEventListener('click', filterIndividuals);
        }
        
        if (searchIndividual) {
            searchIndividual.addEventListener('input', filterIndividuals);
        }
        
        if (filterAge) {
            filterAge.addEventListener('change', filterIndividuals);
        }
        
        if (filterSex) {
            filterSex.addEventListener('change', filterIndividuals);
        }
        
        if (sortBy) {
            sortBy.addEventListener('change', filterIndividuals);
        }
        
        function filterIndividuals() {
            const searchTerm = searchIndividual ? searchIndividual.value.toLowerCase() : '';
            const ageFilter = filterAge ? filterAge.value : '';
            const sexFilter = filterSex ? filterSex.value.toLowerCase() : '';
            const sortOption = sortBy ? sortBy.value : 'name_asc';
            
            const rows = document.querySelectorAll('#individualsTableBody tr');
            
            rows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const age = parseInt(row.dataset.age) || 0;
                const sex = row.dataset.sex || '';
                const address = row.cells[3].textContent.toLowerCase();
                
                let show = true;
                
                // Search filter
                if (searchTerm && !name.includes(searchTerm) && !address.includes(searchTerm)) {
                    show = false;
                }
                
                // Age filter
                if (ageFilter && show) {
                    const [min, max] = ageFilter.split('-').map(Number);
                    if (max) {
                        // Range like "0-18"
                        show = age >= min && age <= max;
                    } else if (ageFilter === '66+') {
                        // 66+ range
                        show = age >= 66;
                    }
                }
                
                // Sex filter
                if (sexFilter && show) {
                    show = sex === sexFilter;
                }
                
                row.style.display = show ? '' : 'none';
            });
            
            // Sort functionality
            sortTable(sortOption);
        }
        
        function sortTable(sortOption) {
            const tbody = document.getElementById('individualsTableBody');
            const rows = Array.from(tbody.querySelectorAll('tr:not([style*="none"])'));
            
            rows.sort((a, b) => {
                switch(sortOption) {
                    case 'name_asc':
                        return a.cells[0].textContent.localeCompare(b.cells[0].textContent);
                    case 'name_desc':
                        return b.cells[0].textContent.localeCompare(a.cells[0].textContent);
                    case 'age_asc':
                        return (parseInt(a.dataset.age) || 0) - (parseInt(b.dataset.age) || 0);
                    case 'age_desc':
                        return (parseInt(b.dataset.age) || 0) - (parseInt(a.dataset.age) || 0);
                    case 'date_desc':
                        return (parseInt(b.dataset.date) || 0) - (parseInt(a.dataset.date) || 0);
                    case 'date_asc':
                        return (parseInt(a.dataset.date) || 0) - (parseInt(b.dataset.date) || 0);
                    default:
                        return 0;
                }
            });
            
            // Reorder rows in the table
            rows.forEach(row => tbody.appendChild(row));
        }
    }
    
    // Global map variables
    let locationMap = null;
    let locationMarker = null;

    // Initialize map when page loads
    function initializeMap() {
        // Get coordinates from PHP
        const rawLat = <?php echo isset($individual['location_lat']) ? $individual['location_lat'] : 'null'; ?>;
        const rawLng = <?php echo isset($individual['location_lng']) ? $individual['location_lng'] : 'null'; ?>;
        
        // Convert to numbers and validate
        const lat = parseFloat(rawLat);
        const lng = parseFloat(rawLng);
        
        // Check if coordinates are valid
        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            createMap(lat, lng);
        } else {
            showNoLocationMessage();
        }
    }

    function createMap(lat, lng) {
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

    // Photo Modal functionality
    let currentPhotoIndex = 0;
    let photosArray = [];

    window.openPhotoModal = function(index) {
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

    window.closePhotoModal = function() {
        const modal = document.getElementById('photoModal');
        modal.style.display = 'none';
        
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }

    window.changePhoto = function(direction) {
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
    document.getElementById('photoModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closePhotoModal();
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('photoModal');
        if (modal && modal.style.display === 'block') {
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

    // Household Members Functions for Edit Mode
    let memberCounter = <?php echo !empty($householdMembers) ? count($householdMembers) : 0; ?>;
    
    window.addHouseholdMember = function() {
        memberCounter++;
        const container = document.getElementById('householdMembersContainer');
        
        const memberDiv = document.createElement('div');
        memberDiv.className = 'member-entry bg-white p-4 rounded border';
        memberDiv.innerHTML = `
            <div class="flex justify-between items-center mb-3">
                <h5 class="font-medium">Member #${memberCounter}</h5>
                <button type="button" onclick="removeHouseholdMember(this)" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" name="member_firstname[]" class="form-input">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Surname</label>
                    <input type="text" name="member_surname[]" class="form-input">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Middle Name</label>
                    <input type="text" name="member_middlename[]" class="form-input">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">M.I.</label>
                    <input type="text" name="member_mi[]" class="form-input" maxlength="2">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Relationship</label>
                    <input type="text" name="member_relationship[]" class="form-input">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Age</label>
                    <input type="number" name="member_age[]" class="form-input" min="0" max="120">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Sex</label>
                    <select name="member_sex[]" class="form-select">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Birthdate</label>
                    <input type="date" name="member_birthdate[]" class="form-input">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Education</label>
                    <input type="text" name="member_education[]" class="form-input">
                </div>
            </div>
        `;
        
        container.appendChild(memberDiv);
        
        // Scroll to the new member
        memberDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    window.removeHouseholdMember = function(button) {
        const memberDiv = button.closest('.member-entry');
        if (memberDiv) {
            memberDiv.remove();
            memberCounter--;
            
            // Update member numbers
            const members = document.querySelectorAll('.member-entry');
            members.forEach((member, index) => {
                const title = member.querySelector('h5');
                if (title) {
                    title.textContent = `Member #${index + 1}`;
                }
            });
        }
    }

    // Main initialization
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all components
        initializeDropdowns();
        initializeSearch();
        
        // Initialize map if needed
        if (document.getElementById('location-map')) {
            initializeMap();
        }
        
        // Set active links
        const currentPath = window.location.pathname;
        const sidebarLinks = document.querySelectorAll('.sidebar-link, .submenu-link');
        
        sidebarLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath || 
                currentPath.includes(link.getAttribute('href'))) {
                link.classList.add('active');
                
                const dropdownItem = link.closest('.dropdown-item');
                if (dropdownItem) {
                    const toggle = dropdownItem.querySelector('.dropdown-toggle');
                    const menu = dropdownItem.querySelector('.dropdown-menu');
                    if (toggle && menu) {
                        toggle.classList.add('open');
                        menu.classList.add('open');
                    }
                }
            }
        });
        
        // Setup delete confirmation button
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                confirmDelete();
            });
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const deleteBarangayModal = document.getElementById('deleteBarangayModal');
            
            if (event.target === deleteModal) {
                hideDeleteModal();
            }
            if (event.target === deleteBarangayModal) {
                hideDeleteBarangayModal();
            }
        });
        
        // ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideDeleteModal();
                hideDeleteBarangayModal();
            }
        });
        
        // Form validation for edit mode
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                // Basic validation
                const requiredFields = editForm.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#ef4444';
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
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