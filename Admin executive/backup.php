<?php
session_start();
// Database configuration
$servername = "localhost";
$username = "u198271324_admin";
$password = "Udhodbms01";
$dbname = "u198271324_udho_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Session timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Handle archive actions for PDC records
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for PDC archive action
    if (isset($_POST['archive_action'])) {
        $action = $_POST['archive_action'];
        $archive_id = isset($_POST['archive_id']) ? intval($_POST['archive_id']) : 0;
        
        if ($action === 'restore' && $archive_id > 0) {
            // Get archived record
            $stmt = $conn->prepare("SELECT * FROM deleted_pdc_records WHERE id = ?");
            $stmt->bind_param("i", $archive_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $archived_record = $result->fetch_assoc();
            $stmt->close();
            
            if ($archived_record) {
                // Restore to pdc_records
                $restore_stmt = $conn->prepare("INSERT INTO pdc_records 
                    (date_issued, subject, case_file, branch, affected_barangay, 
                     household_affected, status, activities, documents) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $restore_stmt->bind_param("sssssisss",
                    $archived_record['date_issued'],
                    $archived_record['subject'],
                    $archived_record['case_file'],
                    $archived_record['branch'],
                    $archived_record['affected_barangay'],
                    $archived_record['household_affected'],
                    $archived_record['status'],
                    $archived_record['activities'],
                    $archived_record['documents']
                );
                
                if ($restore_stmt->execute()) {
                    // Delete from archives
                    $delete_stmt = $conn->prepare("DELETE FROM deleted_pdc_records WHERE id = ?");
                    $delete_stmt->bind_param("i", $archive_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    
                    $_SESSION['restore_success'] = "PDC record restored successfully!";
                }
                $restore_stmt->close();
            }
        } elseif ($action === 'permanent_delete' && $archive_id > 0) {
            // Permanently delete from archives
            $delete_stmt = $conn->prepare("DELETE FROM deleted_pdc_records WHERE id = ?");
            $delete_stmt->bind_param("i", $archive_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['delete_success'] = "PDC record permanently deleted from archives!";
            }
            $delete_stmt->close();
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "#pdc-archives");
        exit();
    }
    
    // Check for survey archive action
    if (isset($_POST['survey_archive_action'])) {
        $action = $_POST['survey_archive_action'];
        $survey_archive_id = isset($_POST['survey_archive_id']) ? intval($_POST['survey_archive_id']) : 0;
        
        if ($action === 'restore_survey' && $survey_archive_id > 0) {
            // Get archived survey
            $stmt = $conn->prepare("SELECT * FROM archived_surveys WHERE id = ?");
            $stmt->bind_param("i", $survey_archive_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $archived_survey = $result->fetch_assoc();
            $stmt->close();
            
            if ($archived_survey && !empty($archived_survey['survey_data'])) {
                // Debug: Check what's in survey_data
                error_log("Survey data to restore: " . substr($archived_survey['survey_data'], 0, 200));
                
                // Decode the JSON survey data
                $survey_data = json_decode($archived_survey['survey_data'], true);
                
                if ($survey_data) {
                    // Debug: Check decoded data
                    error_log("Decoded survey data: " . print_r($survey_data, true));
                    
                    // Prepare SQL for insertion
                    $sql = "INSERT INTO survey_responses 
                        (enumerator_name, enumerator_id, ud_code, tag_number, address, 
                         barangay, city, region, location_lat, location_lng, photos, 
                         signature, answers, survey_date, survey_time) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $restore_stmt = $conn->prepare($sql);
                    
                    if ($restore_stmt) {
                        // Bind parameters from the decoded survey data
                        $enumerator_name = $survey_data['enumerator_name'] ?? '';
                        $enumerator_id = $survey_data['enumerator_id'] ?? '';
                        $ud_code = $survey_data['ud_code'] ?? '';
                        $tag_number = $survey_data['tag_number'] ?? '';
                        $address = $survey_data['address'] ?? '';
                        $barangay = $survey_data['barangay'] ?? '';
                        $city = $survey_data['city'] ?? '';
                        $region = $survey_data['region'] ?? '';
                        $location_lat = $survey_data['location_lat'] ?? 0.0;
                        $location_lng = $survey_data['location_lng'] ?? 0.0;
                        $photos = $survey_data['photos'] ?? '';
                        $signature = $survey_data['signature'] ?? '';
                        $answers = $survey_data['answers'] ?? '';
                        $survey_date = $survey_data['survey_date'] ?? date('Y-m-d');
                        $survey_time = $survey_data['survey_time'] ?? date('H:i:s');
                        
                        // Debug: Check values
                        error_log("Binding values: $enumerator_name, $enumerator_id, $ud_code, $barangay");
                        
                        $restore_stmt->bind_param("ssssssssddsssss",
                            $enumerator_name,
                            $enumerator_id,
                            $ud_code,
                            $tag_number,
                            $address,
                            $barangay,
                            $city,
                            $region,
                            $location_lat,
                            $location_lng,
                            $photos,
                            $signature,
                            $answers,
                            $survey_date,
                            $survey_time
                        );
                        
                        if ($restore_stmt->execute()) {
                            // Delete from archives
                            $delete_stmt = $conn->prepare("DELETE FROM archived_surveys WHERE id = ?");
                            $delete_stmt->bind_param("i", $survey_archive_id);
                            $delete_stmt->execute();
                            $delete_stmt->close();
                            
                            $_SESSION['survey_restore_success'] = "Survey record restored successfully!";
                        } else {
                            error_log("Restore failed: " . $restore_stmt->error);
                            $_SESSION['survey_error'] = "Failed to restore survey: " . $restore_stmt->error;
                        }
                        $restore_stmt->close();
                    } else {
                        error_log("Prepare failed: " . $conn->error);
                        $_SESSION['survey_error'] = "Database error: " . $conn->error;
                    }
                } else {
                    error_log("JSON decode failed for survey ID: $survey_archive_id");
                    $_SESSION['survey_error'] = "Failed to decode survey data.";
                }
            } else {
                error_log("No survey data found for ID: $survey_archive_id");
                $_SESSION['survey_error'] = "Survey data not found.";
            }
        } elseif ($action === 'permanent_delete_survey' && $survey_archive_id > 0) {
            // Permanently delete survey from archives
            $delete_stmt = $conn->prepare("DELETE FROM archived_surveys WHERE id = ?");
            $delete_stmt->bind_param("i", $survey_archive_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['survey_delete_success'] = "Survey record permanently deleted from archives!";
            } else {
                $_SESSION['survey_error'] = "Failed to delete survey: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "#survey-archives");
        exit();
    }
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get actual table counts from database
$tableCounts = [];

// Get survey_responses count
$surveyQuery = "SELECT COUNT(*) as count FROM survey_responses";
$surveyResult = $conn->query($surveyQuery);
if ($surveyResult) {
    $tableCounts['survey_responses'] = $surveyResult->fetch_assoc()['count'];
}

// Get archived_surveys count
$archivedSurveyQuery = "SELECT COUNT(*) as count FROM archived_surveys";
$archivedSurveyResult = $conn->query($archivedSurveyQuery);
if ($archivedSurveyResult) {
    $tableCounts['archived_surveys'] = $archivedSurveyResult->fetch_assoc()['count'];
}

// Get hoa_associations count
$hoaQuery = "SELECT COUNT(*) as count FROM hoa_associations";
$hoaResult = $conn->query($hoaQuery);
if ($hoaResult) {
    $tableCounts['hoa_associations'] = $hoaResult->fetch_assoc()['count'];
}

// Get users count
$userQuery = "SELECT COUNT(*) as count FROM users";
$userResult = $conn->query($userQuery);
if ($userResult) {
    $tableCounts['users'] = $userResult->fetch_assoc()['count'];
}

// Get pdc_records count
$pdcQuery = "SELECT COUNT(*) as count FROM pdc_records";
$pdcResult = $conn->query($pdcQuery);
if ($pdcResult) {
    $tableCounts['pdc_records'] = $pdcResult->fetch_assoc()['count'];
}

// Get deleted_pdc_records count
$deletedQuery = "SELECT COUNT(*) as count FROM deleted_pdc_records";
$deletedResult = $conn->query($deletedQuery);
if ($deletedResult) {
    $tableCounts['deleted_pdc_records'] = $deletedResult->fetch_assoc()['count'];
}

// Get archived PDC records
$archivedRecords = [];
$archiveQuery = "SELECT * FROM deleted_pdc_records ORDER BY deleted_at DESC";
$archiveResult = $conn->query($archiveQuery);
if ($archiveResult && $archiveResult->num_rows > 0) {
    while ($row = $archiveResult->fetch_assoc()) {
        $archivedRecords[] = $row;
    }
}

// Get archived survey records
$archivedSurveys = [];
$archivedSurveyQuery = "SELECT * FROM archived_surveys ORDER BY deleted_at DESC";
$archivedSurveyResult = $conn->query($archivedSurveyQuery);
if ($archivedSurveyResult && $archivedSurveyResult->num_rows > 0) {
    while ($row = $archivedSurveyResult->fetch_assoc()) {
        // Try to decode the survey_data JSON
        $survey_data = json_decode($row['survey_data'], true);
        
        // Create a display array
        $display_row = [
            'id' => $row['id'],
            'original_id' => $row['original_id'],
            'deleted_at' => $row['deleted_at'],
            'deleted_by' => $row['deleted_by'],
            'reason' => $row['reason'],
            'survey_data_raw' => $row['survey_data'] // Keep raw data for restoration
        ];
        
        // Add fields from decoded survey data if available
        if ($survey_data && is_array($survey_data)) {
            $display_row['enumerator_name'] = $survey_data['enumerator_name'] ?? 'N/A';
            $display_row['enumerator_id'] = $survey_data['enumerator_id'] ?? 'N/A';
            $display_row['ud_code'] = $survey_data['ud_code'] ?? 'N/A';
            $display_row['tag_number'] = $survey_data['tag_number'] ?? 'N/A';
            $display_row['address'] = $survey_data['address'] ?? 'N/A';
            $display_row['barangay'] = $survey_data['barangay'] ?? 'N/A';
            $display_row['city'] = $survey_data['city'] ?? 'N/A';
            $display_row['region'] = $survey_data['region'] ?? 'N/A';
            $display_row['survey_date'] = $survey_data['survey_date'] ?? 'N/A';
            $display_row['survey_time'] = $survey_data['survey_time'] ?? 'N/A';
        } else {
            // If JSON decode fails, set default values
            $display_row['enumerator_name'] = 'N/A';
            $display_row['enumerator_id'] = 'N/A';
            $display_row['ud_code'] = 'N/A';
            $display_row['tag_number'] = 'N/A';
            $display_row['address'] = 'N/A';
            $display_row['barangay'] = 'N/A';
            $display_row['city'] = 'N/A';
            $display_row['region'] = 'N/A';
            $display_row['survey_date'] = 'N/A';
            $display_row['survey_time'] = 'N/A';
        }
        
        $archivedSurveys[] = $display_row;
    }
}

// Get data for different sections
$currentPage = basename($_SERVER['PHP_SELF']);
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/UDHOLOGO.png">
  <title>Database Management</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <style>
    /* Custom scrollbar for tables */
    .table-container::-webkit-scrollbar {
      height: 8px;
      width: 8px;
    }
    .table-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    .table-container::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 4px;
    }
    .table-container::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
    
    /* Database table styling */
    .database-section {
      margin-bottom: 2rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    .database-header {
      background-color: #4c51bf;
      color: white;
      padding: 0.75rem 1rem;
      font-weight: bold;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .database-count {
      background-color: rgba(255, 255, 255, 0.2);
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.875rem;
    }
    .database-table {
      width: 100%;
      border-collapse: collapse;
    }
    .database-table th {
      background-color: #edf2f7;
      padding: 0.75rem;
      text-align: left;
      position: sticky;
      top: 0;
    }
    .database-table td {
      padding: 0.75rem;
      border-top: 1px solid #e2e8f0;
    }
    .database-table tr:hover {
      background-color: #f8fafc;
    }
    
    /* Action buttons */
    .action-buttons {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 2rem;
    }
    .btn-backup {
      background-color: #10b981;
    }
    .btn-backup:hover {
      background-color: #059669;
    }
    .btn-delete {
      background-color: #ef4444;
    }
    .btn-delete:hover {
      background-color: #dc2626;
    }
    .btn-show-all {
      background-color: #3b82f6;
    }
    .btn-show-all:hover {
      background-color: #2563eb;
    }
    
    /* Status badges */
    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-active {
      background-color: #d1fae5;
      color: #065f46;
    }
    .status-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    .status-completed {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .status-inactive {
      background-color: #f3f4f6;
      color: #6b7280;
    }
    .status-archived {
      background-color: #fce7f3;
      color: #be185d;
    }
    
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
    
    /* Dropdown styles */
    .dropdown-menu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
    }
    .dropdown-menu.open {
      max-height: 500px;
    }
    .dropdown-toggle {
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .dropdown-toggle i.fa-chevron-down {
      transition: transform 0.3s ease;
    }
    .dropdown-toggle.open i.fa-chevron-down {
      transform: rotate(180deg);
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
    
    /* Hidden rows */
    .hidden-row {
      display: none;
    }

    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 50;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
      background-color: white;
      margin: 15% auto;
      padding: 20px;
      border-radius: 8px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    /* Archive specific styles */
    .archive-actions {
      display: flex;
      gap: 0.25rem;
      white-space: nowrap;
    }
    
    .btn-restore {
      background-color: #10b981;
      color: white;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      transition: background-color 0.2s;
    }
    
    .btn-restore:hover {
      background-color: #059669;
    }
    
    .btn-permanent-delete {
      background-color: #ef4444;
      color: white;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      transition: background-color 0.2s;
    }
    
    .btn-permanent-delete:hover {
      background-color: #dc2626;
    }
    
    /* Navigation tabs */
    .nav-tabs {
      display: flex;
      border-bottom: 2px solid #e5e7eb;
      margin-bottom: 1.5rem;
    }
    
    .nav-tab {
      padding: 0.75rem 1.5rem;
      cursor: pointer;
      font-weight: 500;
      color: #6b7280;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      transition: all 0.2s;
    }
    
    .nav-tab:hover {
      color: #374151;
    }
    
    .nav-tab.active {
      color: #4c51bf;
      border-bottom-color: #4c51bf;
    }
    
    /* Alert messages */
    .alert-success {
      background-color: #d1fae5;
      border: 1px solid #10b981;
      color: #065f46;
      padding: 0.75rem 1rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    
    .alert-error {
      background-color: #fee2e2;
      border: 1px solid #ef4444;
      color: #b91c1c;
      padding: 0.75rem 1rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    
    /* Tab content */
    .tab-content {
      display: block;
    }
    .tab-content.hidden {
      display: none;
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
        // Assuming you have a user profile picture path stored in a session or variable
        $profilePicture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default_profile.jpg';
        ?>
        <img src="../assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>" 
            alt="Profile Picture" 
            class="w-full h-full object-cover"
            onerror="this.src='../assets/PROFILE_SAMPLE.jpg'">
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
          <a href="../Admin executive/backup.php" class="sidebar-link flex items-center py-3 px-4 active-link">
            <i class="fas fa-database mr-3"></i> Backup Data
          </a>
        </li>
        <li>
          <a href="../Admin executive/employee.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-users mr-3"></i> Employees
          </a>
        </li>
        <li>
          <a href="../settings.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-cog mr-3"></i> Settings
          </a>
        </li>
        <li>
          <a href="../logout.php" class="sidebar-link flex items-center py-3 px-4 mt-10">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="flex-1 p-4 md:p-10">
    <header class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold text-gray-800">Database Management</h1>
      <div class="flex items-center gap-2">
        <img src="../assets/UDHOLOGO.png" alt="Logo" class="h-8">
        <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
      </div>
    </header>

    <!-- Success Messages -->
    <?php if (isset($_SESSION['restore_success'])): ?>
    <div class="alert-success">
      <?php echo $_SESSION['restore_success']; unset($_SESSION['restore_success']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['delete_success'])): ?>
    <div class="alert-success">
      <?php echo $_SESSION['delete_success']; unset($_SESSION['delete_success']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['survey_restore_success'])): ?>
    <div class="alert-success">
      <?php echo $_SESSION['survey_restore_success']; unset($_SESSION['survey_restore_success']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['survey_delete_success'])): ?>
    <div class="alert-success">
      <?php echo $_SESSION['survey_delete_success']; unset($_SESSION['survey_delete_success']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['survey_error'])): ?>
    <div class="alert-error">
      <?php echo $_SESSION['survey_error']; unset($_SESSION['survey_error']); ?>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
      <div class="nav-tab active" onclick="showTab('active-data')">
        <i class="fas fa-database mr-2"></i> Active Data
      </div>
      <div class="nav-tab" onclick="showTab('pdc-archives')" id="pdc-archives-tab">
        <i class="fas fa-archive mr-2"></i> PDC Archives
        <?php if ($tableCounts['deleted_pdc_records'] > 0): ?>
        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-2">
          <?php echo $tableCounts['deleted_pdc_records']; ?>
        </span>
        <?php endif; ?>
      </div>
      <div class="nav-tab" onclick="showTab('survey-archives')" id="survey-archives-tab">
        <i class="fas fa-clipboard-list mr-2"></i> Survey Archives
        <?php if ($tableCounts['archived_surveys'] > 0): ?>
        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-2">
          <?php echo $tableCounts['archived_surveys']; ?>
        </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Active Data Tab -->
    <div id="active-data" class="tab-content">
      <!-- Action Buttons -->
      <div class="action-buttons">
        <button onclick="showConfirmation('backup')" class="btn-backup text-white px-4 py-2 rounded-md transition flex items-center">
          <i class="fas fa-database mr-2"></i> Backup All Data
        </button>
        <button onclick="showConfirmation('delete')" class="btn-delete text-white px-4 py-2 rounded-md transition flex items-center">
          <i class="fas fa-trash-alt mr-2"></i> Delete All Data
        </button>
      </div>

      <!-- Database Tables -->
      <div class="database-section" id="survey-db">
        <div class="database-header">
          <div>
            <i class="fas fa-clipboard-list mr-2"></i> Survey Responses
            <span class="database-count"><?php echo $tableCounts['survey_responses'] ?? 0; ?> records</span>
          </div>
        </div>
        <div class="table-container overflow-x-auto max-h-96">
          <table class="database-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Barangay</th>
                <th>Created At</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Get actual survey data
              $surveyDataQuery = "SELECT id, barangay, created_at FROM survey_responses ORDER BY created_at DESC LIMIT 10";
              $surveyDataResult = $conn->query($surveyDataQuery);
              
              if ($surveyDataResult && $surveyDataResult->num_rows > 0) {
                  $count = 0;
                  while($row = $surveyDataResult->fetch_assoc()) {
                      $displayClass = $count >= 5 ? 'hidden-row' : '';
                      echo "<tr class='{$displayClass}'>";
                      echo "<td>IDSAP-" . str_pad($row['id'], 3, '0', STR_PAD_LEFT) . "</td>";
                      echo "<td>" . htmlspecialchars($row['barangay']) . "</td>";
                      echo "<td>" . date('M j, Y', strtotime($row['created_at'])) . "</td>";
                      echo "<td><span class='status-badge status-active'>Completed</span></td>";
                      echo "</tr>";
                      $count++;
                  }
              } else {
                  echo "<tr><td colspan='4' class='text-center py-4 text-gray-500'>No survey data available</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        <?php if (($tableCounts['survey_responses'] ?? 0) > 5): ?>
        <div class="flex justify-center p-3 bg-gray-50">
          <button onclick="toggleAllRows('survey-db')" class="btn-show-all text-white px-4 py-2 rounded-md transition flex items-center">
            <i class="fas fa-list mr-2"></i> Show All Records
          </button>
        </div>
        <?php endif; ?>
      </div>

      <div class="database-section" id="hoa-db">
        <div class="database-header">
          <div>
            <i class="fas fa-home mr-2"></i> HOA Associations
            <span class="database-count"><?php echo $tableCounts['hoa_associations'] ?? 0; ?> records</span>
          </div>
        </div>
        <div class="table-container overflow-x-auto max-h-96">
          <table class="database-table">
            <thead>
              <tr>
                <th>HOA ID</th>
                <th>Name</th>
                <th>Barangay</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Get actual HOA data
              $hoaDataQuery = "SELECT hoa_id, name, barangay, hoa_status FROM hoa_associations ORDER BY created_at DESC LIMIT 10";
              $hoaDataResult = $conn->query($hoaDataQuery);
              
              if ($hoaDataResult && $hoaDataResult->num_rows > 0) {
                  $count = 0;
                  while($row = $hoaDataResult->fetch_assoc()) {
                      $displayClass = $count >= 5 ? 'hidden-row' : '';
                      $statusClass = $row['hoa_status'] == 'Complete and Verified' ? 'status-completed' : 
                                    ($row['hoa_status'] == 'Pending for DHSUD' ? 'status-pending' : 'status-inactive');
                      
                      echo "<tr class='{$displayClass}'>";
                      echo "<td>" . htmlspecialchars($row['hoa_id']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['barangay']) . "</td>";
                      echo "<td><span class='status-badge {$statusClass}'>" . htmlspecialchars($row['hoa_status']) . "</span></td>";
                      echo "</tr>";
                      $count++;
                  }
              } else {
                  echo "<tr><td colspan='4' class='text-center py-4 text-gray-500'>No HOA data available</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        <?php if (($tableCounts['hoa_associations'] ?? 0) > 5): ?>
        <div class="flex justify-center p-3 bg-gray-50">
          <button onclick="toggleAllRows('hoa-db')" class="btn-show-all text-white px-4 py-2 rounded-md transition flex items-center">
            <i class="fas fa-list mr-2"></i> Show All Records
          </button>
        </div>
        <?php endif; ?>
      </div>

      <div class="database-section" id="pdc-db">
        <div class="database-header">
          <div>
            <i class="fas fa-gavel mr-2"></i> PDC Records
            <span class="database-count"><?php echo $tableCounts['pdc_records'] ?? 0; ?> records</span>
          </div>
        </div>
        <div class="table-container overflow-x-auto max-h-96">
          <table class="database-table">
            <thead>
              <tr>
                <th>Date Issued</th>
                <th>Subject</th>
                <th>Case File</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Get actual PDC data
              $pdcDataQuery = "SELECT date_issued, subject, case_file, status FROM pdc_records ORDER BY date_issued DESC LIMIT 10";
              $pdcDataResult = $conn->query($pdcDataQuery);
              
              if ($pdcDataResult && $pdcDataResult->num_rows > 0) {
                  $count = 0;
                  while($row = $pdcDataResult->fetch_assoc()) {
                      $displayClass = $count >= 5 ? 'hidden-row' : '';
                      $statusClass = $row['status'] == 'Resolved' ? 'status-completed' : 
                                    ($row['status'] == 'Pending' ? 'status-pending' : 'status-inactive');
                      
                      echo "<tr class='{$displayClass}'>";
                      echo "<td>" . date('M j, Y', strtotime($row['date_issued'])) . "</td>";
                      echo "<td>" . htmlspecialchars($row['subject']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['case_file']) . "</td>";
                      echo "<td><span class='status-badge {$statusClass}'>" . htmlspecialchars($row['status']) . "</span></td>";
                      echo "</tr>";
                      $count++;
                  }
              } else {
                  echo "<tr><td colspan='4' class='text-center py-4 text-gray-500'>No PDC records available</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        <?php if (($tableCounts['pdc_records'] ?? 0) > 5): ?>
        <div class="flex justify-center p-3 bg-gray-50">
          <button onclick="toggleAllRows('pdc-db')" class="btn-show-all text-white px-4 py-2 rounded-md transition flex items-center">
            <i class="fas fa-list mr-2"></i> Show All Records
          </button>
        </div>
        <?php endif; ?>
      </div>

      <div class="database-section" id="users-db">
        <div class="database-header">
          <div>
            <i class="fas fa-users mr-2"></i> Users
            <span class="database-count"><?php echo $tableCounts['users'] ?? 0; ?> records</span>
          </div>
        </div>
        <div class="table-container overflow-x-auto max-h-96">
          <table class="database-table">
            <thead>
              <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Get actual user data
              $userDataQuery = "SELECT id, username, role FROM users ORDER BY id DESC LIMIT 10";
              $userDataResult = $conn->query($userDataQuery);
              
              if ($userDataResult && $userDataResult->num_rows > 0) {
                  $count = 0;
                  while($row = $userDataResult->fetch_assoc()) {
                      $displayClass = $count >= 5 ? 'hidden-row' : '';
                      
                      echo "<tr class='{$displayClass}'>";
                      echo "<td>" . strtoupper(substr($row['role'], 0, 3)) . "-" . str_pad($row['id'], 3, '0', STR_PAD_LEFT) . "</td>";
                      echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                      echo "<td>" . ucfirst($row['role']) . "</td>";
                      echo "<td><span class='status-badge status-active'>Active</span></td>";
                      echo "</tr>";
                      $count++;
                  }
              } else {
                  echo "<tr><td colspan='4' class='text-center py-4 text-gray-500'>No user data available</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        <?php if (($tableCounts['users'] ?? 0) > 5): ?>
        <div class="flex justify-center p-3 bg-gray-50">
          <button onclick="toggleAllRows('users-db')" class="btn-show-all text-white px-4 py-2 rounded-md transition flex items-center">
            <i class="fas fa-list mr-2"></i> Show All Records
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PDC Archives Tab -->
    <div id="pdc-archives" class="tab-content hidden">
      <div class="database-section">
        <div class="database-header">
          <div>
            <i class="fas fa-archive mr-2"></i> Deleted PDC Records (Archives)
            <span class="database-count"><?php echo $tableCounts['deleted_pdc_records'] ?? 0; ?> records</span>
          </div>
          <div class="text-sm">
            <i class="fas fa-info-circle mr-1"></i> Records deleted from Operation Panel are archived here
          </div>
        </div>
        
        <?php if (empty($archivedRecords)): ?>
        <div class="text-center py-8 text-gray-500">
          <i class="fas fa-archive text-4xl mb-3"></i>
          <p class="text-lg">No archived PDC records found</p>
          <p class="text-sm mt-1">Deleted records from Operation Panel will appear here</p>
        </div>
        <?php else: ?>
        <div class="table-container overflow-x-auto max-h-96">
          <table class="database-table">
            <thead>
              <tr>
                <th>Original ID</th>
                <th>Date Issued</th>
                <th>Subject</th>
                <th>Case File</th>
                <th>Branch</th>
                <th>Barangay</th>
                <th>Households</th>
                <th>Status</th>
                <th>Deleted By</th>
                <th>Deleted At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($archivedRecords as $record): ?>
              <tr>
                <td><?php echo $record['original_id']; ?></td>
                <td><?php echo date('M j, Y', strtotime($record['date_issued'])); ?></td>
                <td><?php echo htmlspecialchars($record['subject']); ?></td>
                <td><?php echo htmlspecialchars($record['case_file']); ?></td>
                <td><?php echo htmlspecialchars($record['branch']); ?></td>
                <td><?php echo htmlspecialchars($record['affected_barangay']); ?></td>
                <td><?php echo $record['household_affected']; ?></td>
                <td>
                  <span class="status-badge status-archived">
                    <?php echo htmlspecialchars($record['status']); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($record['deleted_by']); ?></td>
                <td><?php echo date('M j, Y H:i', strtotime($record['deleted_at'])); ?></td>
                <td>
                  <div class="archive-actions">
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="archive_action" value="restore">
                      <input type="hidden" name="archive_id" value="<?php echo $record['id']; ?>">
                      <button type="submit" class="btn-restore" title="Restore to PDC Records">
                        <i class="fas fa-undo"></i> Restore
                      </button>
                    </form>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="archive_action" value="permanent_delete">
                      <input type="hidden" name="archive_id" value="<?php echo $record['id']; ?>">
                      <button type="submit" class="btn-permanent-delete" title="Permanently Delete">
                        <i class="fas fa-trash"></i> Delete
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Survey Archives Tab -->
    <div id="survey-archives" class="tab-content hidden">
      <div class="database-section">
        <div class="database-header">
          <div>
            <i class="fas fa-clipboard-list mr-2"></i> Archived Surveys
            <span class="database-count"><?php echo $tableCounts['archived_surveys'] ?? 0; ?> records</span>
          </div>
          <div class="text-sm">
            <i class="fas fa-info-circle mr-1"></i> Survey records deleted from IDSAP Database are archived here
          </div>
        </div>
        
        <?php if (empty($archivedSurveys)): ?>
        <div class="text-center py-8 text-gray-500">
          <i class="fas fa-clipboard-list text-4xl mb-3"></i>
          <p class="text-lg">No archived survey records found</p>
          <p class="text-sm mt-1">Deleted survey records from IDSAP Database will appear here</p>
        </div>
        <?php else: ?>
        <div class="table-container overflow-x-auto max-h-96">
          <table class="database-table">
            <thead>
              <tr>
                <th>Original ID</th>
                <th>Enumerator</th>
                <th>UD Code</th>
                <th>Barangay</th>
                <th>Survey Date</th>
                <th>Deleted By</th>
                <th>Deleted At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($archivedSurveys as $survey): ?>
              <tr>
                <td><?php echo $survey['original_id']; ?></td>
                <td><?php echo htmlspecialchars($survey['enumerator_name']); ?></td>
                <td><?php echo htmlspecialchars($survey['ud_code']); ?></td>
                <td><?php echo htmlspecialchars($survey['barangay']); ?></td>
                <td><?php echo $survey['survey_date'] != 'N/A' ? date('M j, Y', strtotime($survey['survey_date'])) : 'N/A'; ?></td>
                <td><?php echo htmlspecialchars($survey['deleted_by']); ?></td>
                <td><?php echo date('M j, Y H:i', strtotime($survey['deleted_at'])); ?></td>
                <td>
                  <div class="archive-actions">
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="survey_archive_action" value="restore_survey">
                      <input type="hidden" name="survey_archive_id" value="<?php echo $survey['id']; ?>">
                      <button type="submit" class="btn-restore" title="Restore to Survey Database">
                        <i class="fas fa-undo"></i> Restore
                      </button>
                    </form>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="survey_archive_action" value="permanent_delete_survey">
                      <input type="hidden" name="survey_archive_id" value="<?php echo $survey['id']; ?>">
                      <button type="submit" class="btn-permanent-delete" title="Permanently Delete">
                        <i class="fas fa-trash"></i> Delete
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmationModal" class="modal">
    <div class="modal-content">
      <div id="modalTitle" class="text-xl font-bold mb-4"></div>
      <div id="modalMessage" class="mb-6"></div>
      <div class="flex justify-end gap-3">
        <button id="cancelAction" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
          Cancel
        </button>
        <button id="confirmAction" class="px-4 py-2 text-white rounded-md transition"></button>
      </div>
    </div>
  </div>

  <script>
    // Detailed messages for each action
    const actionMessages = {
      backup: {
        title: "Confirm Data Backup",
        message: `
          <p>You are about to back up all database information. This action will:</p>
          <ul class="list-disc pl-5 mt-2 space-y-1">
            <li>Generate Excel files for each database table</li>
            <li>Include all current data in the backup</li>
            <li>Create a timestamped record of this backup</li>
          </ul>
          <p class="mt-3">Please verify this is what you intend to do before proceeding.</p>
        `,
        button: "Backup Data",
        buttonClass: "bg-green-600 hover:bg-green-700"
      },
      delete: {
        title: "Confirm Data Deletion",
        message: `
          <p class="text-red-600 font-medium">WARNING: This is a highly destructive action!</p>
          <p class="mt-2">You are about to permanently delete all data from the system. This action will:</p>
          <ul class="list-disc pl-5 mt-2 space-y-1">
            <li>Remove all records from all database tables</li>
            <li>Archive all PDC records to the deleted_pdc_records table</li>
            <li>Not be recoverable through normal means</li>
            <li>Be recorded with your user account as the responsible party</li>
            <li>Require manual intervention to restore from backup</li>
          </ul>
          <p class="mt-3 font-bold">This action cannot be undone.</p>
          <p class="mt-2">Are you sure to delete all data? All data will be erased and you will be recorded as the user who deleted this data.</p>
        `,
        button: "Delete All Data",
        buttonClass: "bg-red-600 hover:bg-red-700"
      }
    };

    // Tab management
    function showTab(tabName) {
      // Hide all tabs
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
      });
      
      // Remove active class from all tabs
      document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Show selected tab
      document.getElementById(tabName).classList.remove('hidden');
      
      // Activate selected tab
      document.querySelector(`.nav-tab[onclick="showTab('${tabName}')"]`).classList.add('active');
    }

    // Show confirmation modal for backup/delete
    let currentAction = null;
    function showConfirmation(action) {
      const modal = document.getElementById('confirmationModal');
      const modalTitle = document.getElementById('modalTitle');
      const modalMessage = document.getElementById('modalMessage');
      const confirmButton = document.getElementById('confirmAction');
      
      const message = actionMessages[action];
      
      modalTitle.textContent = message.title;
      modalMessage.innerHTML = message.message;
      confirmButton.textContent = message.button;
      confirmButton.className = `px-4 py-2 text-white rounded-md transition ${message.buttonClass}`;
      
      modal.style.display = 'block';
      currentAction = action;
    }

    // Toggle visibility of all rows in a table
    function toggleAllRows(tableId) {
      const section = document.getElementById(tableId);
      if (!section) return;
      
      const table = section.querySelector('tbody');
      const button = section.querySelector('.btn-show-all');
      const hiddenRows = table.querySelectorAll('.hidden-row');
      
      if (hiddenRows.length === 0) return;
      
      const isShowingAll = hiddenRows[0].style.display === 'table-row';
      
      hiddenRows.forEach(row => {
        row.style.display = isShowingAll ? 'none' : 'table-row';
      });
      
      button.innerHTML = isShowingAll ? 
        '<i class="fas fa-list mr-2"></i> Show All Records' : 
        '<i class="fas fa-eye-slash mr-2"></i> Show Less';
    }

    // Perform the selected action
    function performAction() {
      document.getElementById('confirmationModal').style.display = 'none';
      
      if (currentAction === 'backup') {
        backupData();
      } else {
        deleteData();
      }
    }

    // Backup data function
    function backupData() {
        // Show loading state
        const loadingMessage = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-lg max-w-md">
                    <div class="flex items-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
                        <div>
                            <h3 class="text-lg font-bold">Creating Backup</h3>
                            <p class="mt-1">Generating Excel files for all databases...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', loadingMessage);
        
        // Create and submit form to trigger download
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'backup_script.php'; // This will be the PHP script that generates Excel
        
        // Add CSRF token or any other required parameters
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'backup_request';
        tokenInput.value = '1';
        form.appendChild(tokenInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        
        // Remove loading indicator after a short delay
        setTimeout(() => {
            const loadingElement = document.querySelector('.fixed.inset-0');
            if (loadingElement) {
                loadingElement.remove();
            }
        }, 2000);
    }

    // Delete data function
    function deleteData() {
      // Show warning loading state
      const loadingMessage = `
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white p-6 rounded-lg shadow-lg max-w-md">
            <div class="flex items-center">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mr-3"></div>
              <div>
                <h3 class="text-lg font-bold text-red-600">Deleting All Data</h3>
                <p class="mt-1">This action cannot be undone. Please wait...</p>
              </div>
            </div>
          </div>
        </div>
      `;
      
      document.body.insertAdjacentHTML('beforeend', loadingMessage);
      
      // Simulate deletion process (replace with actual API call)
      setTimeout(() => {
        // Remove loading indicator
        const loadingElement = document.querySelector('.fixed.inset-0');
        if (loadingElement) {
          loadingElement.remove();
        }
        
        // Show success message
        alert('All data has been deleted. All PDC records have been archived.');
      }, 2000);
    }

    // Event listeners for confirmation modal
    document.getElementById('cancelAction').addEventListener('click', function() {
      document.getElementById('confirmationModal').style.display = 'none';
    });

    document.getElementById('confirmAction').addEventListener('click', function() {
      performAction();
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
      const modal = document.getElementById('confirmationModal');
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });

    // Initialize dropdown functionality
    function initializeDropdowns() {
      const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
      
      dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
          const targetId = this.getAttribute('data-target');
          const targetMenu = document.getElementById(targetId);
          const isOpen = targetMenu.classList.contains('open');
          
          // Close all dropdowns first
          document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('open');
          });
          document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.classList.remove('open');
          });
          
          // Toggle the clicked dropdown if it wasn't open
          if (!isOpen) {
            targetMenu.classList.add('open');
            this.classList.add('open');
          }
        });
      });
    }

    // Check URL hash for archive tabs
    function checkHash() {
      if (window.location.hash === '#pdc-archives') {
        showTab('pdc-archives');
      } else if (window.location.hash === '#survey-archives') {
        showTab('survey-archives');
      }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize dropdowns
      initializeDropdowns();
      
      // Check hash for archive tabs
      checkHash();
      
      // Listen for hash changes
      window.addEventListener('hashchange', checkHash);
      
      // Hide all rows beyond the first 5 in each table
      document.querySelectorAll('.database-section').forEach(section => {
        const table = section.querySelector('tbody');
        if (table) {
          const rows = table.querySelectorAll('tr');
          rows.forEach((row, index) => {
            if (index >= 5) {
              row.classList.add('hidden-row');
              row.style.display = 'none';
            }
          });
        }
      });
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>