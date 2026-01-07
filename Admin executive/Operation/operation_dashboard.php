<?php
// START - SECURITY HEADERS (MUST BE AT VERY TOP - NO WHITESPACE BEFORE)
session_start();

// Force no caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if not logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

// 30-minute inactivity logout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();
// END SECURITY HEADERS

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
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

    

  <script>
    // Initialize dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        initializeDropdowns();
    });

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

    // Back Button Prevention Script
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