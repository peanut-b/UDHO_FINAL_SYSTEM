<?php
session_start();
// Database configuration
$servername = "localhost";
$username = "u198271324_admin";
$password = "Udhodbms01";
$dbname = "u198271324_udho_db";

// Create connection with error reporting
$conn = new mysqli($servername, $username, $password, $dbname);

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

// Start session and prevent caching
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


// Set last activity time for timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();



// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . ". Please check your database credentials and make sure the database exists.");
}

// Function to get latest routing number with error handling (updated for direction)
function getLatestRoutingNumber($conn, $direction) {
    $query = "SELECT control_no, created_at FROM routing_slips 
              WHERE direction = '$direction'
              ORDER BY created_at DESC LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Database error: " . $conn->error);
        return [
            'number' => 'UDHO-'.date('Y').'-0000',
            'date' => date('M d, Y h:i A')
        ];
    }
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'number' => $row['control_no'],
            'date' => date('M d, Y h:i A', strtotime($row['created_at']))
        ];
    }
    return [
        'number' => 'UDHO-'.date('Y').'-0000',
        'date' => date('M d, Y h:i A')
    ];
}

// Function to get today's papers count with daily tracking
function getTodaysPapers($conn, $direction) {
    $today = date('Y-m-d');
    $query = "SELECT COUNT(*) as count FROM routing_slips 
              WHERE DATE(created_at) = '$today' 
              AND direction = '$direction'";
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Database error: " . $conn->error);
        return [
            'count' => 0,
            'diff' => 0
        ];
    }
    
    $row = $result->fetch_assoc();
    $currentCount = $row['count'];
    
    // Initialize session tracking if not set
    if (!isset($_SESSION['daily_counts'][$today][$direction])) {
        $_SESSION['daily_counts'][$today][$direction] = [
            'previous' => $currentCount,
            'current' => $currentCount
        ];
    }
    
    // Calculate difference from previous count
    $diff = $currentCount - $_SESSION['daily_counts'][$today][$direction]['previous'];
    
    // Update session with new count
    $_SESSION['daily_counts'][$today][$direction] = [
        'previous' => $_SESSION['daily_counts'][$today][$direction]['current'],
        'current' => $currentCount
    ];
    
    return [
        'count' => $currentCount,
        'diff' => $diff
    ];
}

// Get data from database
try {
    $latestIncoming = getLatestRoutingNumber($conn, 'Incoming');
    $latestOutgoing = getLatestRoutingNumber($conn, 'Outgoing');
    
    // Get today's counts with daily tracking
    $incomingData = getTodaysPapers($conn, 'Incoming');
    $outgoingData = getTodaysPapers($conn, 'Outgoing');
    
    $incomingToday = $incomingData['count'];
    $outgoingToday = $outgoingData['count'];
    $incomingDiff = $incomingData['diff'];
    $outgoingDiff = $outgoingData['diff'];
    
    // Get today's date for display
    $todayDisplay = date('M d, Y');
    
} catch (Exception $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    // Set default values if there's an error
    $latestIncoming = ['number' => 'UDHO-'.date('Y').'-0000', 'date' => date('M d, Y h:i A')];
    $latestOutgoing = ['number' => 'UDHO-'.date('Y').'-0000', 'date' => date('M d, Y h:i A')];
    $incomingToday = 0;
    $outgoingToday = 0;
    $incomingDiff = 0;
    $outgoingDiff = 0;
    $todayDisplay = date('M d, Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <style>
    .custom-card:hover {
      border-color: #2563eb !important;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3);
    }
    .icon-style {
      font-size: 2.2rem;
      color: #111827;
    }
    .metric-card {
      transition: all 0.3s ease;
    }
    .metric-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .main-content {
      flex: 1;
      padding: 2.5rem;
    }
    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
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
        $profilePicture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'PROFILE_SAMPLE.jpg';
        $profilePath = '../assets/profile_pictures/' . htmlspecialchars($profilePicture);
        $defaultPath = '../assets/PROFILE_SAMPLE.jpg';
        ?>
        <img src="<?php echo file_exists($profilePath) ? $profilePath : $defaultPath; ?>" 
            alt="Profile Picture" 
            class="w-full h-full object-cover"
            onerror="this.src='<?php echo $defaultPath; ?>'">
      </div>
    </div>
    
    <div class="px-4 py-2 text-center text-sm text-gray-300">
      Logged in as: <br>
      <span class="font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
    </div>
    
    <nav class="mt-6">
      <ul>
        <li>
          <a href="admin_dashboard.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
          </a>
        </li>
        <li>
          <a href="../settings.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-cog mr-3"></i> Settings
          </a>
        </li>
        <li>
          <a href="../logout.php" class="block py-2.5 px-4 hover:bg-gray-700 mt-10 flex items-center">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <header class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Admin Dashboard</h1>
      <div class="flex items-center gap-2">
        <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
        <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
      </div>
    </header>

    <!-- Dashboard Metrics -->
    <div class="metrics-grid">
      <!-- Latest Incoming Routing Number -->
      <div class="bg-white rounded-lg shadow p-6 metric-card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Latest Incoming Routing</p>
            <p class="mt-1 text-3xl font-semibold text-gray-900"><?php echo htmlspecialchars($latestIncoming['number']); ?></p>
            <p class="mt-2 text-sm text-gray-500">Created: <?php echo htmlspecialchars($latestIncoming['date']); ?></p>
          </div>
          <div class="bg-green-100 p-3 rounded-full">
            <i class="fas fa-inbox text-green-600 text-2xl"></i>
          </div>
        </div>
      </div>
      
      <!-- Latest Outgoing Routing Number -->
      <div class="bg-white rounded-lg shadow p-6 metric-card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Latest Outgoing Routing</p>
            <p class="mt-1 text-3xl font-semibold text-gray-900"><?php echo htmlspecialchars($latestOutgoing['number']); ?></p>
            <p class="mt-2 text-sm text-gray-500">Created: <?php echo htmlspecialchars($latestOutgoing['date']); ?></p>
          </div>
          <div class="bg-purple-100 p-3 rounded-full">
            <i class="fas fa-paper-plane text-purple-600 text-2xl"></i>
          </div>
        </div>
      </div>
      
      <!-- Incoming Papers Today -->
      <div class="bg-white rounded-lg shadow p-6 metric-card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Incoming Papers Today (<?php echo $todayDisplay; ?>)</p>
            <p class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $incomingToday; ?></p>
            <p class="mt-2 text-sm <?php echo $incomingDiff > 0 ? 'text-green-600' : ($incomingDiff < 0 ? 'text-red-600' : 'text-gray-500'); ?>">
              <?php 
              if ($incomingDiff > 0) {
                  echo "<i class='fas fa-arrow-up'></i> +$incomingDiff new since last view";
              } elseif ($incomingDiff < 0) {
                  echo "<i class='fas fa-arrow-down'></i> $incomingDiff removed since last view";
              } else {
                  echo "No change since last view";
              }
              ?>
            </p>
          </div>
          <div class="bg-blue-100 p-3 rounded-full">
            <i class="fas fa-file-import text-blue-600 text-2xl"></i>
          </div>
        </div>
      </div>
      
      <!-- Outgoing Papers Today -->
      <div class="bg-white rounded-lg shadow p-6 metric-card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Outgoing Papers Today (<?php echo $todayDisplay; ?>)</p>
            <p class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $outgoingToday; ?></p>
            <p class="mt-2 text-sm <?php echo $outgoingDiff > 0 ? 'text-green-600' : ($outgoingDiff < 0 ? 'text-red-600' : 'text-gray-500'); ?>">
              <?php 
              if ($outgoingDiff > 0) {
                  echo "<i class='fas fa-arrow-up'></i> +$outgoingDiff new since last view";
              } elseif ($outgoingDiff < 0) {
                  echo "<i class='fas fa-arrow-down'></i> $outgoingDiff removed since last view";
              } else {
                  echo "No change since last view";
              }
              ?>
            </p>
          </div>
          <div class="bg-orange-100 p-3 rounded-full">
            <i class="fas fa-file-export text-orange-600 text-2xl"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-8">
      <a href="admin_panel.php" class="block">
        <div class="bg-white p-6 text-center rounded shadow-sm border transition-all duration-300 custom-card">
          <i class="fas fa-file-alt icon-style mb-2"></i>
          <h6 class="mt-2 text-lg font-medium text-gray-800">Routing Slip</h6>
        </div>
      </a>
      <a href="admin_records.php" class="block">
        <div class="bg-white p-6 text-center rounded shadow-sm border transition-all duration-300 custom-card">
          <i class="fas fa-archive icon-style mb-2"></i>
          <h6 class="mt-2 text-lg font-medium text-gray-800">Records</h6>
        </div>
      </a>
    </div>
  </div>
<script>
// Nuclear option for back button
history.pushState(null, null, location.href);
window.onpopstate = function() {
    history.go(1);
};
</script>
</body>
</html>
<?php
$conn->close();
?>