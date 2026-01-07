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

// ================== LIVE DATABASE COUNTS ================== //
$surveyCount = 0;
$hoaCount    = 0;
$userCount   = 0;
$pdcCount    = 0;

// Get total surveys count
$surveyQuery = "SELECT COUNT(*) AS total FROM survey_responses";
$surveyResult = $conn->query($surveyQuery);
if ($surveyResult) {
    $surveyCount = $surveyResult->fetch_assoc()['total'];
}

// Get total HOA count
$hoaQuery = "SELECT COUNT(*) AS total FROM hoa_associations";
$hoaResult = $conn->query($hoaQuery);
if ($hoaResult) {
    $hoaCount = $hoaResult->fetch_assoc()['total'];
}

// Get total users count
$userQuery = "SELECT COUNT(*) AS total FROM users";
$userResult = $conn->query($userQuery);
if ($userResult) {
    $userCount = $userResult->fetch_assoc()['total'];
}

// Get PDC records count
$pdcQuery = "SELECT COUNT(*) AS total FROM pdc_records";
$pdcResult = $conn->query($pdcQuery);
if ($pdcResult) {
    $pdcCount = $pdcResult->fetch_assoc()['total'];
}

// Get recent surveys
$recentSurveys = [];
$surveyQuery = "SELECT id, barangay, created_at FROM survey_responses ORDER BY created_at DESC LIMIT 5";
$surveyResult = $conn->query($surveyQuery);
if ($surveyResult && $surveyResult->num_rows > 0) {
    while($row = $surveyResult->fetch_assoc()) {
        $recentSurveys[] = $row;
    }
}

// Get recent HOAs
$recentHOAs = [];
$hoaQuery = "SELECT hoa_id, name, barangay, hoa_status FROM hoa_associations ORDER BY created_at DESC LIMIT 5";
$hoaResult = $conn->query($hoaQuery);
if ($hoaResult && $hoaResult->num_rows > 0) {
    while($row = $hoaResult->fetch_assoc()) {
        $recentHOAs[] = $row;
    }
}

// Get recent users
$recentUsers = [];
$userQuery = "SELECT id, username, role FROM users ORDER BY id DESC LIMIT 5";
$userResult = $conn->query($userQuery);
if ($userResult && $userResult->num_rows > 0) {
    while($row = $userResult->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

// Get recent PDC records
$recentPdc = [];
$pdcQuery = "SELECT date_issued, subject, case_file, branch, household_affected, status, affected_barangay, activities FROM pdc_records ORDER BY date_issued DESC LIMIT 5";
$pdcResult = $conn->query($pdcQuery);
if ($pdcResult && $pdcResult->num_rows > 0) {
    while($row = $pdcResult->fetch_assoc()) {
        $recentPdc[] = $row;
    }
}

// ================== CHART DATA QUERIES ================== //

// Survey Trend Data (Last 6 months)
$surveyTrendData = [];
$surveyTrendQuery = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count 
    FROM survey_responses 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$surveyTrendResult = $conn->query($surveyTrendQuery);
if ($surveyTrendResult && $surveyTrendResult->num_rows > 0) {
    while($row = $surveyTrendResult->fetch_assoc()) {
        $surveyTrendData[] = $row;
    }
}

// HOA Status Distribution
$hoaStatusData = [];
$hoaStatusQuery = "SELECT hoa_status, COUNT(*) as count FROM hoa_associations GROUP BY hoa_status";
$hoaStatusResult = $conn->query($hoaStatusQuery);
if ($hoaStatusResult && $hoaStatusResult->num_rows > 0) {
    while($row = $hoaStatusResult->fetch_assoc()) {
        $hoaStatusData[$row['hoa_status']] = $row['count'];
    }
}

// Surveys by Barangay
$surveysByBarangay = [];
$barangayQuery = "SELECT barangay, COUNT(*) as count FROM survey_responses GROUP BY barangay ORDER BY count DESC LIMIT 5";
$barangayResult = $conn->query($barangayQuery);
if ($barangayResult && $barangayResult->num_rows > 0) {
    while($row = $barangayResult->fetch_assoc()) {
        $surveysByBarangay[] = $row;
    }
}

// HOA by Barangay
$hoaByBarangay = [];
$hoaBarangayQuery = "SELECT barangay, COUNT(*) as count FROM hoa_associations GROUP BY barangay ORDER BY count DESC LIMIT 5";
$hoaBarangayResult = $conn->query($hoaBarangayQuery);
if ($hoaBarangayResult && $hoaBarangayResult->num_rows > 0) {
    while($row = $hoaBarangayResult->fetch_assoc()) {
        $hoaByBarangay[] = $row;
    }
}

// User Role Distribution
$userRoleData = [];
$userRoleQuery = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$userRoleResult = $conn->query($userRoleQuery);
if ($userRoleResult && $userRoleResult->num_rows > 0) {
    while($row = $userRoleResult->fetch_assoc()) {
        $userRoleData[] = $row;
    }
}

// PDC Status Distribution
$pdcStatusData = [];
$pdcStatusQuery = "SELECT status, COUNT(*) as count FROM pdc_records GROUP BY status";
$pdcStatusResult = $conn->query($pdcStatusQuery);
if ($pdcStatusResult && $pdcStatusResult->num_rows > 0) {
    while($row = $pdcStatusResult->fetch_assoc()) {
        $pdcStatusData[] = $row;
    }
}

// PDC by Barangay
$pdcByBarangay = [];
$pdcBarangayQuery = "SELECT affected_barangay, COUNT(*) as count FROM pdc_records GROUP BY affected_barangay ORDER BY count DESC LIMIT 5";
$pdcBarangayResult = $conn->query($pdcBarangayQuery);
if ($pdcBarangayResult && $pdcBarangayResult->num_rows > 0) {
    while($row = $pdcBarangayResult->fetch_assoc()) {
        $pdcByBarangay[] = $row;
    }
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/UDHOLOGO.png">

  <title>Admin Executive Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
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
    
    /* Refresh button */
    .refresh-btn {
      transition: transform 0.5s ease;
    }
    .refresh-btn:hover {
      transform: rotate(180deg);
    }
    .refreshing {
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    
    /* Table styles */
    .data-table {
      width: 100%;
      border-collapse: collapse;
    }
    .data-table th, .data-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    .data-table th {
      background-color: #f9fafb;
      font-weight: 600;
      color: #374151;
    }
    .data-table tr:hover {
      background-color: #f3f4f6;
    }
    
    /* Status badges */
    .status-badge {
      padding: 4px 8px;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-active {
      background-color: #D1FAE5;
      color: #065F46;
    }
    .status-inactive {
      background-color: #FEE2E2;
      color: #991B1B;
    }
    .status-pending {
      background-color: #FEF3C7;
      color: #92400E;
    }
    .status-complete {
      background-color: #DBEAFE;
      color: #1E40AF;
    }
    
    /* Card styles */
    .stat-card {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    /* Tab styles */
    .tab-button {
      padding: 10px 20px;
      border-radius: 6px;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    .tab-button.active {
      background-color: #3b82f6;
      color: white;
    }
    .tab-button:hover:not(.active) {
      background-color: #f3f4f6;
    }
    
    /* Dashboard grid */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
    }
    
    .tab-content {
      display: none;
    }
    .tab-content.active {
      display: block;
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
  </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
  <!-- Sidebar -->
  <div class="w-64 bg-gray-800 text-white flex flex-col">
    <div class="flex items-center justify-center h-24">
      <!-- Profile Picture Container -->
     <div class="rounded-full bg-gray-200 w-20 h-20 flex items-center justify-center overflow-hidden border-2 border-white shadow-md">
  <?php
  // Use the same method as in settings.php
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
          <a href="../Admin executive/backup.php" class="sidebar-link flex items-center py-3 px-4">
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
  <div class="flex-1 p-6 overflow-auto">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold text-gray-800">Executive Dashboard</h1>
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
          <img src="../assets/UDHOLOGO.png" alt="Logo" class="h-8">
          <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
        </div>
        <button onclick="refreshData()" class="refresh-btn p-2 bg-blue-100 text-blue-600 rounded-full hover:bg-blue-200 transition">
          <i class="fas fa-sync-alt"></i>
        </button>
      </div>
    </div>

    <!-- Executive Dashboard -->
    <div class="dashboard-content">
      <!-- Dashboard Tabs -->
      <div class="flex flex-wrap gap-2 mb-6 bg-white p-2 rounded-lg shadow-sm">
        <div class="tab-button active" onclick="changeTab('overview')">
          <i class="fas fa-chart-pie mr-2"></i> Overview Reports
        </div>
        <div class="tab-button" onclick="changeTab('surveys')">
          <i class="fas fa-clipboard-list mr-2"></i> Survey Reports
        </div>
        <div class="tab-button" onclick="changeTab('hoa')">
          <i class="fas fa-home mr-2"></i> HOA Reports
        </div>
        <div class="tab-button" onclick="changeTab('users')">
          <i class="fas fa-users mr-2"></i> User Reports
        </div>
        <div class="tab-button" onclick="changeTab('pdc')">
          <i class="fas fa-gavel mr-2"></i> PDC Reports
        </div>
      </div>

      <!-- Overview Tab -->
      <div id="overview-tab" class="tab-content active">
        <!-- Key Metrics Report -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
          <h2 class="text-xl font-semibold mb-4">Key Metrics Report</h2>
          <div class="dashboard-grid mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md stat-card">
              <div class="flex justify-between items-center">
                <div>
                  <p class="text-gray-500">Total Surveys</p>
                  <h3 class="text-2xl font-bold"><?php echo $surveyCount; ?></h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                  <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                </div>
              </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md stat-card">
              <div class="flex justify-between items-center">
                <div>
                  <p class="text-gray-500">HOA Groups</p>
                  <h3 class="text-2xl font-bold"><?php echo $hoaCount; ?></h3>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                  <i class="fas fa-home text-yellow-600 text-xl"></i>
                </div>
              </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md stat-card">
              <div class="flex justify-between items-center">
                <div>
                  <p class="text-gray-500">Active Users</p>
                  <h3 class="text-2xl font-bold"><?php echo $userCount; ?></h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                  <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
              </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md stat-card">
              <div class="flex justify-between items-center">
                <div>
                  <p class="text-gray-500">PDC Records</p>
                  <h3 class="text-2xl font-bold"><?php echo $pdcCount; ?></h3>
                </div>
                <div class="bg-red-100 p-3 rounded-full">
                  <i class="fas fa-gavel text-red-600 text-xl"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Section -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
              <h3 class="text-lg font-medium mb-4">Survey Trend (Last 6 Months)</h3>
              <canvas id="surveyTrendChart" height="250"></canvas>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
              <h3 class="text-lg font-medium mb-4">HOA Status Overview</h3>
              <canvas id="hoaStatusChart" height="250"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Surveys Tab -->
      <div id="surveys-tab" class="tab-content">
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
          <h2 class="text-xl font-semibold mb-4">Survey Data Report</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">Surveys by Barangay</h3>
              <canvas id="surveyOverviewChart" height="250"></canvas>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">Monthly Survey Activity</h3>
              <canvas id="surveyActivityChart" height="250"></canvas>
            </div>
          </div>

          <h3 class="text-lg font-medium mb-4">Recent Survey Responses</h3>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Response ID</th>
                  <th>Barangay</th>
                  <th>Date Created</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentSurveys)): ?>
                  <?php foreach($recentSurveys as $survey): ?>
                  <tr>
                    <td>IDSAP-<?php echo str_pad($survey['id'], 3, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($survey['barangay']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($survey['created_at'])); ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="3" class="text-center py-4 text-gray-500">No survey data available</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- HOA Tab -->
      <div id="hoa-tab" class="tab-content">
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
          <h2 class="text-xl font-semibold mb-4">HOA Associations Report</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">HOA Distribution by Barangay</h3>
              <canvas id="hoaDistributionChart" height="250"></canvas>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">HOA Status Overview</h3>
              <canvas id="hoaStatusOverviewChart" height="250"></canvas>
            </div>
          </div>

          <h3 class="text-lg font-medium mb-4">Recent HOA Registrations</h3>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>HOA ID</th>
                  <th>Name</th>
                  <th>Barangay</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentHOAs)): ?>
                  <?php foreach($recentHOAs as $hoa): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($hoa['hoa_id']); ?></td>
                    <td><?php echo htmlspecialchars($hoa['name']); ?></td>
                    <td><?php echo htmlspecialchars($hoa['barangay']); ?></td>
                    <td>
                      <span class="status-badge <?php 
                        echo $hoa['hoa_status'] == 'Complete and Verified' ? 'status-complete' : 
                             ($hoa['hoa_status'] == 'Pending for DHSUD' ? 'status-pending' : 'status-inactive'); 
                      ?>">
                        <?php echo htmlspecialchars($hoa['hoa_status']); ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="text-center py-4 text-gray-500">No HOA data available</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Users Tab -->
      <div id="users-tab" class="tab-content">
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
          <h2 class="text-xl font-semibold mb-4">User Activity Report</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">User Role Distribution</h3>
              <canvas id="userDistributionChart" height="250"></canvas>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">User Registration Trend</h3>
              <canvas id="userActivityChart" height="250"></canvas>
            </div>
          </div>

          <h3 class="text-lg font-medium mb-4">Recent Users</h3>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>User ID</th>
                  <th>Username</th>
                  <th>Role</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentUsers)): ?>
                  <?php foreach($recentUsers as $user): ?>
                  <tr>
                    <td><?php echo strtoupper(substr($user['role'], 0, 3)); ?>-<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="3" class="text-center py-4 text-gray-500">No user data available</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- PDC Records Tab -->
      <div id="pdc-tab" class="tab-content">
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
          <h2 class="text-xl font-semibold mb-4">PDC Records Report</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">PDC Cases by Status</h3>
              <canvas id="pdcStatusChart" height="250"></canvas>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="text-lg font-medium mb-3">PDC Cases by Barangay</h3>
              <canvas id="pdcBarangayChart" height="250"></canvas>
            </div>
          </div>

          <h3 class="text-lg font-medium mb-4">Recent PDC Records</h3>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date Issued</th>
                  <th>Subject</th>
                  <th>Case File</th>
                  <th>Branch</th>
                  <th>Household Affected</th>
                  <th>Affected Barangay</th>
                  <th>Activities</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentPdc)): ?>
                  <?php foreach($recentPdc as $pdc): ?>
                  <tr>
                    <td><?php echo date('M j, Y', strtotime($pdc['date_issued'])); ?></td>
                    <td><?php echo htmlspecialchars($pdc['subject']); ?></td>
                    <td><?php echo htmlspecialchars($pdc['case_file']); ?></td>
                    <td><?php echo htmlspecialchars($pdc['branch']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($pdc['household_affected']); ?></td>
                    <td><?php echo htmlspecialchars($pdc['affected_barangay']); ?></td>
                    <td><?php echo htmlspecialchars($pdc['activities']); ?></td>
                    <td>
                      <span class="status-badge <?php 
                        echo $pdc['status'] == 'Resolved' ? 'status-active' : 
                             ($pdc['status'] == 'Pending' ? 'status-pending' : 
                             ($pdc['status'] == 'In Progress' ? 'status-complete' : 'status-inactive')); 
                      ?>">
                        <?php echo htmlspecialchars($pdc['status']); ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center py-4 text-gray-500">No PDC records available</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Initialize charts with REAL data from PHP
    document.addEventListener('DOMContentLoaded', function() {
      // Survey Trend Chart - Real Data
      const surveyTrendCtx = document.getElementById('surveyTrendChart').getContext('2d');
      new Chart(surveyTrendCtx, {
        type: 'line',
        data: {
          labels: <?php echo json_encode(array_column($surveyTrendData, 'month')); ?>,
          datasets: [{
            label: 'Surveys Completed',
            data: <?php echo json_encode(array_column($surveyTrendData, 'count')); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.3,
            fill: true
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: { beginAtZero: true }
          }
        }
      });

      // HOA Status Chart - Real Data
      const hoaStatusCtx = document.getElementById('hoaStatusChart').getContext('2d');
      new Chart(hoaStatusCtx, {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode(array_keys($hoaStatusData)); ?>,
          datasets: [{
            data: <?php echo json_encode(array_values($hoaStatusData)); ?>,
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });

      // Survey Overview Chart - Real Data
      const surveyOverviewCtx = document.getElementById('surveyOverviewChart').getContext('2d');
      new Chart(surveyOverviewCtx, {
        type: 'bar',
        data: {
          labels: <?php echo json_encode(array_column($surveysByBarangay, 'barangay')); ?>,
          datasets: [{
            label: 'Surveys',
            data: <?php echo json_encode(array_column($surveysByBarangay, 'count')); ?>,
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: { beginAtZero: true }
          }
        }
      });

      // Survey Activity Chart - Using trend data
      const surveyActivityCtx = document.getElementById('surveyActivityChart').getContext('2d');
      new Chart(surveyActivityCtx, {
        type: 'line',
        data: {
          labels: <?php echo json_encode(array_column($surveyTrendData, 'month')); ?>,
          datasets: [{
            label: 'Survey Activity',
            data: <?php echo json_encode(array_column($surveyTrendData, 'count')); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.3,
            fill: true
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });

      // HOA Distribution Chart - Real Data
      const hoaDistributionCtx = document.getElementById('hoaDistributionChart').getContext('2d');
      new Chart(hoaDistributionCtx, {
        type: 'bar',
        data: {
          labels: <?php echo json_encode(array_column($hoaByBarangay, 'barangay')); ?>,
          datasets: [{
            label: 'Number of HOAs',
            data: <?php echo json_encode(array_column($hoaByBarangay, 'count')); ?>,
            backgroundColor: '#0d9488'
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });

      // HOA Status Overview Chart - Real Data
      const hoaStatusOverviewCtx = document.getElementById('hoaStatusOverviewChart').getContext('2d');
      new Chart(hoaStatusOverviewCtx, {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode(array_keys($hoaStatusData)); ?>,
          datasets: [{
            data: <?php echo json_encode(array_values($hoaStatusData)); ?>,
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });

      // User Distribution Chart - Real Data
      const userDistributionCtx = document.getElementById('userDistributionChart').getContext('2d');
      new Chart(userDistributionCtx, {
        type: 'pie',
        data: {
          labels: <?php echo json_encode(array_column($userRoleData, 'role')); ?>,
          datasets: [{
            data: <?php echo json_encode(array_column($userRoleData, 'count')); ?>,
            backgroundColor: ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b']
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });

      // User Activity Chart - Simple trend (you can add user registration date queries if needed)
      const userActivityCtx = document.getElementById('userActivityChart').getContext('2d');
      new Chart(userActivityCtx, {
        type: 'line',
        data: {
          labels: ['Total Users'],
          datasets: [{
            label: 'User Count',
            data: [<?php echo $userCount; ?>],
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            tension: 0.3,
            fill: true
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });

      // PDC Status Chart - Real Data
      const pdcStatusCtx = document.getElementById('pdcStatusChart').getContext('2d');
      new Chart(pdcStatusCtx, {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode(array_column($pdcStatusData, 'status')); ?>,
          datasets: [{
            data: <?php echo json_encode(array_column($pdcStatusData, 'count')); ?>,
            backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444']
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });

      // PDC Barangay Chart - Real Data
      const pdcBarangayCtx = document.getElementById('pdcBarangayChart').getContext('2d');
      new Chart(pdcBarangayCtx, {
        type: 'bar',
        data: {
          labels: <?php echo json_encode(array_column($pdcByBarangay, 'affected_barangay')); ?>,
          datasets: [{
            label: 'PDC Cases',
            data: <?php echo json_encode(array_column($pdcByBarangay, 'count')); ?>,
            backgroundColor: '#ef4444'
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });

      // Initialize dropdown functionality
      initializeDropdowns();
    });

    // Refresh data function
    function refreshData() {
      const refreshBtn = document.querySelector('.refresh-btn i');
      refreshBtn.classList.add('refreshing');
      
      // Reload the page after a short delay to show the animation
      setTimeout(() => {
        window.location.reload();
      }, 800);
    }

    // Tab switching function
    function changeTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Show selected tab content
      document.getElementById(tabName + '-tab').classList.add('active');
      
      // Update active tab button
      document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
      });
      event.target.classList.add('active');
    }

    // Dropdown functionality
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
  </script>
</body>

</html>