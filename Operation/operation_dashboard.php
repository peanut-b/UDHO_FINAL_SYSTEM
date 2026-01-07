<?php
// START - SECURITY HEADERS (MUST BE AT VERY TOP - NO WHITESPACE BEFORE)
session_start();

// Force no caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if not logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

// 30-minute inactivity logout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();
// END SECURITY HEADERS
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Extra Cache Prevention -->
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>Operation Panel</title>

  <!-- Tailwind CSS -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
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
    
    /* Sidebar active item highlight */
    .sidebar-active {
      background-color: #4B5563;
      border-left: 4px solid #2563eb;
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
        ?>
        <img src="/assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>" 
            alt="Profile Picture" 
            class="w-full h-full object-cover"
            onerror="this.src='assets/PROFILE_SAMPLE.jpg'">
      </div>
    </div>
    
    <div class="px-4 py-2 text-center text-sm text-gray-300">
      Logged in as: <br>
      <span class="font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
    </div>
    
    <nav class="mt-2">
      <ul>
        <li>
          <a href="operation_dashboard.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center sidebar-active">
            <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
          </a>
        </li>
        <li>
          <a href="operation_IDSAP.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-users mr-3"></i> IDSAP Database
          </a>
        </li>
        <li>
          <a href="operation_panel.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-scale-balanced mr-3"></i> PDC Cases
          </a>
        </li>
        <li>
          <a href="meralco.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-file-alt mr-3"></i> Meralco Certificates
          </a>
        </li>
        <li>
          <a href="../settings.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center mt-4">
            <i class="fas fa-cog mr-3"></i> Settings
          </a>
        </li>
        <li>
          <a href="../logout.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center mt-6">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="flex-1 p-8">
    <header class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Operation Dashboard</h1>
        <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
      </div>
      <div class="flex items-center gap-2">
        <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
        <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
      </div>
    </header>

    <!-- Dashboard Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
      <!-- IDSAP Database -->
      <a href="operation_IDSAP.php" class="block transform transition-transform hover:scale-105">
        <div class="bg-white p-6 text-center rounded-lg shadow-sm border border-gray-200 transition-all duration-300 custom-card">
          <i class="fas fa-users icon-style mb-2"></i>
          <h6 class="mt-2 text-lg font-medium text-gray-800">IDSAP Database</h6>
          <p class="text-sm text-gray-500 mt-1">Manage beneficiary records</p>
        </div>
      </a>

      <!-- PDC Cases -->
      <a href="operation_panel.php" class="block transform transition-transform hover:scale-105">
        <div class="bg-white p-6 text-center rounded-lg shadow-sm border border-gray-200 transition-all duration-300 custom-card">
          <i class="fas fa-scale-balanced icon-style mb-2"></i>
          <h6 class="mt-2 text-lg font-medium text-gray-800">PDC Cases</h6>
          <p class="text-sm text-gray-500 mt-1">Process payment cases</p>
        </div>
      </a>

      <!-- Meralco Certificate -->
      <a href="meralco.php" class="block transform transition-transform hover:scale-105">
        <div class="bg-white p-6 text-center rounded-lg shadow-sm border border-gray-200 transition-all duration-300 custom-card">
          <i class="fas fa-file-alt icon-style mb-2"></i>
          <h6 class="mt-2 text-lg font-medium text-gray-800">Meralco Certificate</h6>
          <p class="text-sm text-gray-500 mt-1">Generate utility certificates</p>
        </div>
      </a>
    </div>
  </div>

  <!-- Back Button Prevention Script -->
  <script>
    // Nuclear option for back button
    history.pushState(null, null, location.href);
    window.onpopstate = function() {
      history.go(1);
    };

    // Auto-logout warning
    let warningTimeout;
    const warningTime = 25 * 60 * 1000; // 25 minutes
    
    function showTimeoutWarning() {
      if (confirm('Your session will expire in 5 minutes. Continue working?')) {
        // Reset activity if user confirms
        fetch('ping.php').catch(() => {});
      }
    }
    
    warningTimeout = setTimeout(showTimeoutWarning, warningTime);
    
    // Reset timer on activity
    document.addEventListener('mousemove', resetTimeout);
    document.addEventListener('keypress', resetTimeout);
    
    function resetTimeout() {
      clearTimeout(warningTimeout);
      warningTimeout = setTimeout(showTimeoutWarning, warningTime);
    }
  </script>
</body>
</html>