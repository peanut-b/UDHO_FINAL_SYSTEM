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
    die("Connection failed: " . $conn->connect_error);
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

// Set last activity time for timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$message = '';
$hoa_data = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_hoa'])) {
        // Add new HOA
        $name = $conn->real_escape_string($_POST['name']);
        $barangay = $conn->real_escape_string($_POST['barangay']);
        $status = $conn->real_escape_string($_POST['status']);
        $description = $conn->real_escape_string($_POST['description']);
        
        // Generate HOA ID
        $hoa_id = "HOA-" . date("Y") . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $sql = "INSERT INTO hoa_associations (hoa_id, name, barangay, status, description, created_at) 
                VALUES ('$hoa_id', '$name', '$barangay', '$status', '$description', NOW())";
        
        if ($conn->query($sql)) {
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>
                          <strong>Success!</strong> New HOA added successfully.
                        </div>";
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                          <strong>Error:</strong> " . $conn->error . "
                        </div>";
        }
    } elseif (isset($_POST['update_hoa'])) {
        // Update HOA
        $id = $conn->real_escape_string($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $barangay = $conn->real_escape_string($_POST['barangay']);
        $status = $conn->real_escape_string($_POST['status']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $sql = "UPDATE hoa_associations 
                SET name='$name', barangay='$barangay', status='$status', description='$description' 
                WHERE id=$id";
        
        if ($conn->query($sql)) {
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>
                          <strong>Success!</strong> HOA updated successfully.
                        </div>";
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                          <strong>Error:</strong> " . $conn->error . "
                        </div>";
        }
    } elseif (isset($_POST['delete_hoa'])) {
        // Delete HOA
        $id = $conn->real_escape_string($_POST['id']);
        
        $sql = "DELETE FROM hoa_associations WHERE id=$id";
        
        if ($conn->query($sql)) {
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>
                          <strong>Success!</strong> HOA deleted successfully.
                        </div>";
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                          <strong>Error:</strong> " . $conn->error . "
                        </div>";
        }
    } elseif (isset($_POST['export_excel'])) {
        // Export to Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="hoa_data_'.date('Y-m-d').'.xls"');
        
        $sql = "SELECT * FROM hoa_associations";
        $result = $conn->query($sql);
        
        echo "ID\tName\tBarangay\tStatus\tDescription\tCreated At\n";
        
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo $row['id']."\t".$row['name']."\t".$row['barangay']."\t".$row['status']."\t".$row['description']."\t".$row['created_at']."\n";
            }
        }
        exit();
    }
}

// Get HOA data based on filters
$where_clause = '';
if ($status_filter != 'all') {
    $where_clause = " WHERE status='$status_filter'";
}
if (!empty($search_query)) {
    $search_query = $conn->real_escape_string($search_query);
    $where_clause = ($where_clause ? $where_clause . " AND " : " WHERE ") . 
                   "(name LIKE '%$search_query%' OR barangay LIKE '%$search_query%' OR description LIKE '%$search_query%')";
}

$sql = "SELECT * FROM hoa_associations" . $where_clause . " ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $hoa_data[] = $row;
    }
}

// Get counts for dashboard
$active_count = $conn->query("SELECT COUNT(*) as count FROM hoa_associations WHERE status='Active'")->fetch_assoc()['count'];
$inactive_count = $conn->query("SELECT COUNT(*) as count FROM hoa_associations WHERE status='Inactive'")->fetch_assoc()['count'];
$abolished_count = $conn->query("SELECT COUNT(*) as count FROM hoa_associations WHERE status='Abolished'")->fetch_assoc()['count'];

// Get HOA for edit
$edit_hoa = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $result = $conn->query("SELECT * FROM hoa_associations WHERE id=$id");
    if ($result->num_rows > 0) {
        $edit_hoa = $result->fetch_assoc();
    }
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HOA Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    .card {
      transition: all 0.3s ease;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
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
    .status-inactive {
      background-color: #f3f4f6;
      color: #6b7280;
    }
    .status-abolished {
      background-color: #fee2e2;
      color: #b91c1c;
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
    .table-checkbox {
      transform: scale(1.2);
      margin-right: 8px;
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
          <a href="/Admin executive/backup.php" class="sidebar-link flex items-center py-3 px-4 active-link">
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
  <div class="flex-1 p-4 md:p-10">
    <header class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold text-gray-800">
        <?php 
          switch($action) {
            case 'dashboard': echo 'HOA Dashboard Overview'; break;
            case 'manage': echo 'HOA Management'; break;
            case 'add': echo 'Register New HOA'; break;
            case 'edit': echo 'Edit HOA'; break;
            case 'members': echo 'Member Management'; break;
            case 'officials': echo 'HOA Officials'; break;
            default: echo 'HOA Dashboard Overview';
          }
        ?>
      </h1>
      <div class="flex items-center gap-2">
        <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
        <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
      </div>
    </header>

    <?php echo $message; ?>

    <?php if ($action == 'dashboard'): ?>
      <!-- Dashboard View -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Active HOAs Card -->
        <div class="card bg-white p-6 rounded-lg shadow">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-gray-500">Active HOAs</p>
              <p class="text-3xl font-bold mt-2"><?php echo $active_count; ?></p>
              
            </div>
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fas fa-check-circle text-xl"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="?action=manage&status=Active" class="text-blue-600 text-sm font-medium hover:text-blue-800">
              View all active HOAs <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Inactive HOAs Card -->
        <div class="card bg-white p-6 rounded-lg shadow">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-gray-500">Inactive HOAs</p>
              <p class="text-3xl font-bold mt-2"><?php echo $inactive_count; ?></p>
              
            </div>
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
              <i class="fas fa-exclamation-circle text-xl"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="?action=manage&status=Inactive" class="text-blue-600 text-sm font-medium hover:text-blue-800">
              View all inactive HOAs <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Abolished HOAs Card -->
        <div class="card bg-white p-6 rounded-lg shadow">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-gray-500">Abolished HOAs</p>
              <p class="text-3xl font-bold mt-2"><?php echo $abolished_count; ?></p>
              
            </div>
            <div class="p-3 rounded-full bg-red-100 text-red-600">
              <i class="fas fa-times-circle text-xl"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="?action=manage&status=Abolished" class="text-blue-600 text-sm font-medium hover:text-blue-800">
              View all abolished HOAs <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>
      </div>

    <?php elseif ($action == 'manage'): ?>
      <!-- HOA Management View -->
      <div class="bg-white p-6 rounded-lg shadow mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
          <h2 class="text-xl font-bold text-gray-800">HOA Management</h2>
          <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
            <form method="get" class="flex-1">
              <input type="hidden" name="action" value="manage">
              <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                       class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                       placeholder="Search HOAs...">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
              </div>
            </form>
            <div class="flex gap-2">
              <a href="?action=add" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> Add New
              </a>
              <form method="post">
                <button type="submit" name="export_excel" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                  <i class="fas fa-file-excel mr-2"></i> Export
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Status Filters -->
        <div class="flex flex-wrap gap-2 mb-6">
          <a href="?action=manage" class="filter-btn px-4 py-2 rounded-lg bg-gray-200 text-gray-700 <?php echo $status_filter == 'all' ? 'bg-blue-500 text-white' : ''; ?>">
            All HOAs
          </a>
          <a href="?action=manage&status=Active" class="filter-btn px-4 py-2 rounded-lg bg-gray-200 text-gray-700 <?php echo $status_filter == 'Active' ? 'bg-blue-500 text-white' : ''; ?>">
            Active (<?php echo $active_count; ?>)
          </a>
          <a href="?action=manage&status=Inactive" class="filter-btn px-4 py-2 rounded-lg bg-gray-200 text-gray-700 <?php echo $status_filter == 'Inactive' ? 'bg-blue-500 text-white' : ''; ?>">
            Inactive (<?php echo $inactive_count; ?>)
          </a>
          <a href="?action=manage&status=Abolished" class="filter-btn px-4 py-2 rounded-lg bg-gray-200 text-gray-700 <?php echo $status_filter == 'Abolished' ? 'bg-blue-500 text-white' : ''; ?>">
            Abolished (<?php echo $abolished_count; ?>)
          </a>
        </div>

        <!-- HOA Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white">
            <thead>
              <tr class="bg-gray-100 text-gray-700">
                <th class="py-3 px-4 text-left">ID</th>
                <th class="py-3 px-4 text-left">Name</th>
                <th class="py-3 px-4 text-left">Barangay</th>
                <th class="py-3 px-4 text-left">Status</th>
                <th class="py-3 px-4 text-left">Created At</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($hoa_data)): ?>
                <tr>
                  <td colspan="6" class="py-4 px-4 text-center text-gray-500">No HOAs found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($hoa_data as $hoa): ?>
                  <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="py-3 px-4"><?php echo $hoa['id']; ?></td>
                    <td class="py-3 px-4"><?php echo htmlspecialchars($hoa['name']); ?></td>
                    <td class="py-3 px-4"><?php echo htmlspecialchars($hoa['barangay']); ?></td>
                    <td class="py-3 px-4">
                      <span class="status-badge 
                        <?php 
                          if ($hoa['status'] == 'Active') echo 'status-active';
                          elseif ($hoa['status'] == 'Inactive') echo 'status-inactive';
                          else echo 'status-abolished';
                        ?>">
                        <?php echo htmlspecialchars($hoa['status']); ?>
                      </span>
                    </td>
                    <td class="py-3 px-4"><?php echo date('M j, Y', strtotime($hoa['created_at'])); ?></td>
                    <td class="py-3 px-4 text-right">
                      <div class="flex justify-end gap-2">
                        
                          
                        </a>
                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this HOA?');">
                          <input type="hidden" name="id" value="<?php echo $hoa['id']; ?>">
                          <button type="submit" name="delete_hoa" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash-alt"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($action == 'add' || $action == 'edit'): ?>
      <!-- Add/Edit HOA Form -->
      <div class="bg-white p-6 rounded-lg shadow max-w-3xl mx-auto">
        <h2 class="text-xl font-bold text-gray-800 mb-6">
          <?php echo $action == 'add' ? 'Register New HOA' : 'Edit HOA'; ?>
        </h2>
        
        <form method="post">
          <?php if ($action == 'edit' && $edit_hoa): ?>
            <input type="hidden" name="id" value="<?php echo $edit_hoa['id']; ?>">
          <?php endif; ?>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 mb-1">HOA Name</label>
              <input type="text" id="name" name="name" required
                     value="<?php echo isset($edit_hoa['name']) ? htmlspecialchars($edit_hoa['name']) : ''; ?>"
                     class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
              <label for="barangay" class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
              <input type="text" id="barangay" name="barangay" required
                     value="<?php echo isset($edit_hoa['barangay']) ? htmlspecialchars($edit_hoa['barangay']) : ''; ?>"
                     class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
              <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select id="status" name="status" required
                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="Active" <?php echo (isset($edit_hoa['status']) && $edit_hoa['status'] == 'Active' ? 'selected' : ''); ?>>Active</option>
                <option value="Inactive" <?php echo (isset($edit_hoa['status']) && $edit_hoa['status'] == 'Inactive' ? 'selected' : ''); ?>>Inactive</option>
                <option value="Abolished" <?php echo (isset($edit_hoa['status']) && $edit_hoa['status'] == 'Abolished' ? 'selected' : ''); ?>>Abolished</option>
              </select>
            </div>
          </div>
          
          <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea id="description" name="description" rows="4"
                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo isset($edit_hoa['description']) ? htmlspecialchars($edit_hoa['description']) : ''; ?></textarea>
          </div>
          
          <div class="flex justify-end gap-4">
            <a href="<?php echo $action == 'edit' ? '?action=manage' : '?action=dashboard'; ?>" 
               class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">
              Cancel
            </a>
            <button type="submit" name="<?php echo $action == 'add' ? 'add_hoa' : 'update_hoa'; ?>"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              <?php echo $action == 'add' ? 'Register HOA' : 'Update HOA'; ?>
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Initialize dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
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
            toggle.classList.remove('open', 'active-link');
          });
          
          // Toggle the clicked dropdown if it wasn't open
          if (!isOpen) {
            targetMenu.classList.add('open');
            this.classList.add('open', 'active-link');
          }
        });
      });
    });

    // Function to filter HOA records by status
    function filterHOA(status) {
      // Get all HOA record elements
      const hoaRecords = document.querySelectorAll('.hoa-record');
      
      // Loop through all records
      hoaRecords.forEach(record => {
        // Get the status of the current record
        const recordStatus = record.getAttribute('data-status');
        
        // Show or hide based on the selected filter
        if (status === 'all' || recordStatus === status) {
          record.style.display = 'block';
        } else {
          record.style.display = 'none';
        }
      });
      
      // Update active button styling
      document.querySelectorAll('.filter-btn').forEach(btn => {
        if (btn.getAttribute('data-filter') === status) {
          btn.classList.add('bg-blue-500', 'text-white');
          btn.classList.remove('bg-gray-200', 'text-gray-700');
        } else {
          btn.classList.remove('bg-blue-500', 'text-white');
          btn.classList.add('bg-gray-200', 'text-gray-700');
        }
      });
    }

    // Check all checkboxes
    document.getElementById('checkAll').addEventListener('change', function() {
      const checkboxes = document.querySelectorAll('.table-checkbox');
      checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
      });
    });

    // Check URL parameters on page load to apply initial filter
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      const statusParam = urlParams.get('status');
      
      if (statusParam) {
        filterHOA(statusParam);
      } else {
        // Default to showing all if no filter specified
        filterHOA('all');
      }
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>