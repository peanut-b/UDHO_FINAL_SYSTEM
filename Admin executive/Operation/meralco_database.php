<?php
session_start();
// Database configuration
$servername = "localhost";
$username = "u198271324_admin";
$password = "Udhodbms01";
$dbname = "u198271324_udho_db";

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

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $action = $_POST['action'];
        $response = ['success' => false, 'message' => ''];

        if ($action === 'update') {
            // Handle update operation
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $address = $_POST['address'] ?? '';
            $control_number = $_POST['control_number'] ?? '';
            
            if (empty($id) || empty($name) || empty($address) || empty($control_number)) {
                $response['message'] = 'All fields are required';
                echo json_encode($response);
                exit;
            }
            
            // Check if control number exists for another record
            $checkStmt = $conn->prepare("SELECT id FROM certificates WHERE control_number = :control_number AND id != :id");
            $checkStmt->bindParam(':control_number', $control_number);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $response['message'] = 'Control number already exists for another record';
                echo json_encode($response);
                exit;
            }
            
            // Check for duplicate name (more than 2 certificates)
            $checkNameStmt = $conn->prepare("SELECT COUNT(*) as cert_count FROM certificates WHERE name = :name AND id != :id");
            $checkNameStmt->bindParam(':name', $name);
            $checkNameStmt->bindParam(':id', $id);
            $checkNameStmt->execute();
            $nameCount = $checkNameStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($nameCount['cert_count'] >= 2) {
                $response['message'] = 'This name already has 2 certificates issued. Cannot issue more.';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE certificates SET 
                                    name = :name, 
                                    address = :address, 
                                    control_number = :control_number, 
                                    updated_at = NOW() 
                                    WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':control_number', $control_number);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Get updated count and status
                $countStmt = $conn->prepare("SELECT 
                    (SELECT COUNT(*) FROM certificates WHERE name = :name) as cert_count,
                    CASE 
                        WHEN (SELECT COUNT(*) FROM certificates WHERE name = :name) > 2 THEN 'Duplicate'
                        ELSE 'Valid'
                    END as status");
                $countStmt->bindParam(':name', $name);
                $countStmt->execute();
                $countData = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['message'] = 'Certificate updated successfully';
                $response['cert_count'] = $countData['cert_count'];
                $response['status'] = $countData['status'];
            } else {
                $response['message'] = 'Failed to update certificate';
            }
            
        } elseif ($action === 'delete') {
            // Handle delete operation
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                $response['message'] = 'Invalid certificate ID';
                echo json_encode($response);
                exit;
            }
            
            // Get name before deleting to update counts
            $nameStmt = $conn->prepare("SELECT name FROM certificates WHERE id = :id");
            $nameStmt->bindParam(':id', $id);
            $nameStmt->execute();
            $nameData = $nameStmt->fetch(PDO::FETCH_ASSOC);
            $name = $nameData['name'] ?? '';
            
            $stmt = $conn->prepare("DELETE FROM certificates WHERE id = :id");
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Certificate deleted successfully';
                
                // If name exists, get updated counts for all certificates with this name
                if ($name) {
                    $countStmt = $conn->prepare("SELECT 
                        id,
                        (SELECT COUNT(*) FROM certificates WHERE name = :name) as cert_count,
                        CASE 
                            WHEN (SELECT COUNT(*) FROM certificates WHERE name = :name) > 2 THEN 'Duplicate'
                            ELSE 'Valid'
                        END as status
                        FROM certificates WHERE name = :name");
                    $countStmt->bindParam(':name', $name);
                    $countStmt->execute();
                    $response['updates'] = $countStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $response['message'] = 'Failed to delete certificate';
            }
            
        } elseif ($action === 'check_duplicate') {
            // Handle duplicate name check
            $name = $_POST['name'] ?? '';
            $current_id = $_POST['current_id'] ?? 0;
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates WHERE name = :name AND id != :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $current_id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['count'] = $result['count'];
            
        } elseif ($action === 'get_row_data') {
            // Handle get row data request
            $id = $_POST['id'] ?? 0;
            
            $sql = "SELECT 
                        (SELECT COUNT(*) FROM certificates WHERE name = c.name) as cert_count,
                        CASE 
                            WHEN (SELECT COUNT(*) FROM certificates WHERE name = c.name) > 2 THEN 'Duplicate'
                            ELSE 'Valid'
                        END as status
                    FROM certificates c
                    WHERE c.id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['data'] = $result;
        } else {
            $response['message'] = 'Invalid action';
        }
        
        echo json_encode($response);
        exit;
        
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        echo json_encode($response);
        exit;
    }
}

// Pagination and search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50; // Number of records per page
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get certificate data for display
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Build query with search filter
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE (c.name LIKE :search OR c.address LIKE :search OR c.control_number LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM certificates c $whereClause";
    $countStmt = $conn->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Get data for current page
    $sql = "SELECT 
                c.id,
                c.name,
                c.address,
                c.control_number,
                c.date_issued,
                (SELECT COUNT(*) FROM certificates WHERE name = c.name) as cert_count,
                CASE 
                    WHEN (SELECT COUNT(*) FROM certificates WHERE name = c.name) > 2 THEN 'Duplicate'
                    ELSE 'Valid'
                END as status
            FROM 
                certificates c
            $whereClause
            ORDER BY 
                c.date_issued DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $certificates = [];
    $error = $e->getMessage();
    $totalPages = 1;
    $totalRecords = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MERALCO Certification Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            table-layout: auto;
        }
        .data-table th {
            background-color: #4CAF50;
            color: white;
            padding: 12px 12px;
            text-align: left;
            font-weight: 600;
            border-right: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .data-table td {
            padding: 12px 12px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            word-break: break-word;
        }
        .data-table td:nth-child(2),
        .data-table td:nth-child(3) {
            min-width: 200px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .data-table tr:hover {
            background-color: #f1f5f9;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1rem;
        }
        .main-content-container {
            width: 100%;
            max-width: none;
            padding: 1rem 1.5rem;
            overflow-y: auto;
            height: 100vh;
            margin-left: 0;
            box-sizing: border-box;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: white;
            margin: 0 2px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .edit-btn {
            background-color: #3b82f6;
        }
        .edit-btn:hover {
            background-color: #2563eb;
        }
        .delete-btn {
            background-color: #ef4444;
        }
        .delete-btn:hover {
            background-color: #dc2626;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .modal-header {
            padding: 16px 20px;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: #64748b;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 16px 20px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background-color: #3b82f6;
            color: white;
            border: none;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
        .btn-danger {
            background-color: #ef4444;
            color: white;
            border: none;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #334155;
            border: none;
        }
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        .success-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 100;
            display: none;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #3b82f6;
        }
        .pagination a:hover {
            background-color: #f1f5f9;
        }
        .pagination .current {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 16rem;
            height: 100vh;
            background-color: #1f2937;
            overflow-y: auto;
            z-index: 50;
        }
        .sidebar-link {
            display: block; 
            padding: 10px 14px;
            border-radius: 6px;
            transition: all 0.2s ease;
            color: #d1d5db;
        }
        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .active-link {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
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
            color: #d1d5db;
        }
        .dropdown-toggle:hover {
            color: white;
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
            color: #9ca3af;
        }
        .submenu-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .submenu-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
        }

        /* Body adjustment for fixed sidebar */
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        /* Main content adjustment */
        .main-content {
            margin-left: 16rem;
            width: calc(100% - 16rem);
            height: 100vh;
            overflow-y: auto;
            background-color: #f3f4f6;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                width: 100%;
                margin-left: 0;
                height: auto;
                overflow-y: visible;
            }
            .main-content-container {
                width: 100%;
                padding: 1rem;
                margin-left: 0;
                height: auto;
            }
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
  <!-- Sidebar -->
  <div class="sidebar">
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
    <div class="main-content">
        <div class="main-content-container">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Certificate Recipients (<?= $totalRecords ?> records)</h2>
                    <div class="flex items-center gap-2">
                       <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
                        <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
                    </div>
                </div>
                
                
            
                <div class="mb-4 relative">
                    <form method="GET" action="" class="flex items-center gap-2">
                        <div class="relative flex-grow">
                            <input type="text" name="search" id="searchRecipients" value="<?= htmlspecialchars($search) ?>" class="pl-10 pr-4 py-2 border rounded-lg w-full" placeholder="Search recipients...">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="?" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Control No.</th>
                                <th>Cert Count</th>
                                <th>Status</th>
                                <th>Date Issued</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recipientsTableBody">
                            <?php if (!empty($certificates)): ?>
                                <?php 
                                $startNumber = ($page - 1) * $limit + 1;
                                foreach ($certificates as $index => $row): 
                                ?>
                                    <?php 
                                        $isDuplicate = $row['status'] === 'Duplicate';
                                        $statusClass = $isDuplicate ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                                    ?>
                                    <tr id="row-<?= $row['id'] ?>">
                                        <td><?= $startNumber + $index ?></td>
                                        <td class="name"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="address"><?= htmlspecialchars($row['address']) ?></td>
                                        <td class="control-number"><?= htmlspecialchars($row['control_number']) ?></td>
                                        <td><?= $row['cert_count'] ?></td>
                                        <td><span class="status-badge <?= $statusClass ?>"><?= $row['status'] ?></span></td>
                                        <td><?= date('M d, Y', strtotime($row['date_issued'])) ?></td>
                                        <td class="space-x-1">
                                            <button class="action-btn edit-btn" onclick="openEditModal(<?= $row['id'] ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="action-btn delete-btn" onclick="openDeleteModal(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8"><?= isset($error) ? "Error loading data: $error" : "No certificates found" ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">First</a>
                        <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    // Show page numbers
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Next</a>
                        <a href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Last</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Certificate</h3>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editId">
                    <div class="mb-4">
                        <label for="editName" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" id="editName" class="w-full px-3 py-2 border rounded-md" required>
                    </div>
                    <div class="mb-4">
                        <label for="editAddress" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" id="editAddress" class="w-full px-3 py-2 border rounded-md" required>
                    </div>
                    <div class="mb-4">
                        <label for="editControlNumber" class="block text-sm font-medium text-gray-700 mb-1">Control Number</label>
                        <input type="text" id="editControlNumber" class="w-full px-3 py-2 border rounded-md" required>
                    </div>
                    <div id="duplicateWarning" class="hidden bg-yellow-100 text-yellow-800 p-2 rounded-md mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span id="warningText">Warning: This name already has certificates issued.</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Certificate</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this certificate? This action cannot be undone.</p>
                <input type="hidden" id="deleteId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <div id="successMessage" class="success-message">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="successText">Operation completed successfully!</span>
    </div>

    <script>
        // Current record ID being edited/deleted
        let currentRecordId = null;
        
        // Edit modal functions
        function openEditModal(id) {
            currentRecordId = id;
            
            // Get the current row data
            const row = document.getElementById('row-'+id);
            const name = row.querySelector('.name').textContent;
            const address = row.querySelector('.address').textContent;
            const controlNumber = row.querySelector('.control-number').textContent;
            
            // Populate the form
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editAddress').value = address;
            document.getElementById('editControlNumber').value = controlNumber;
            
            // Check for duplicate name
            checkDuplicateName(name, id);
            
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function checkDuplicateName(name, currentId) {
            const formData = new FormData();
            formData.append('action', 'check_duplicate');
            formData.append('name', name);
            formData.append('current_id', currentId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const warningElement = document.getElementById('duplicateWarning');
                if (data.success && data.count >= 2) {
                    warningElement.classList.remove('hidden');
                    document.getElementById('warningText').textContent = 
                        `Warning: This name already has ${data.count} certificates issued (limit is 2).`;
                } else {
                    warningElement.classList.add('hidden');
                }
            });
        }
        
        // Add event listener for name field changes
        document.getElementById('editName').addEventListener('input', function() {
            const name = this.value;
            const currentId = document.getElementById('editId').value;
            if (name.length > 3) { // Only check after some input
                checkDuplicateName(name, currentId);
            }
        });
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function saveEdit() {
            const id = document.getElementById('editId').value;
            const name = document.getElementById('editName').value;
            const address = document.getElementById('editAddress').value;
            const controlNumber = document.getElementById('editControlNumber').value;
            
            // Validate form
            if (!name || !address || !controlNumber) {
                alert('Please fill in all fields');
                return;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('id', id);
            formData.append('name', name);
            formData.append('address', address);
            formData.append('control_number', controlNumber);
            formData.append('action', 'update');
            
            // Make AJAX call to update the record
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the table row
                    const row = document.getElementById('row-'+id);
                    if (row) {
                        row.querySelector('.name').textContent = name;
                        row.querySelector('.address').textContent = address;
                        row.querySelector('.control-number').textContent = controlNumber;
                        
                        // Update cert count and status if returned
                        if (data.cert_count !== undefined) {
                            row.cells[4].textContent = data.cert_count;
                        }
                        if (data.status) {
                            const isDuplicate = data.status === 'Duplicate';
                            const statusClass = isDuplicate ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                            const statusElement = row.cells[5].querySelector('.status-badge');
                            statusElement.className = 'status-badge ' + statusClass;
                            statusElement.textContent = data.status;
                        }
                    }
                    
                    // Show success message
                    showSuccess(data.message || 'Certificate updated successfully!');
                    closeEditModal();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the certificate');
            });
        }
        
        // Delete modal functions
        function openDeleteModal(id) {
            currentRecordId = id;
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        function confirmDelete() {
            const id = document.getElementById('deleteId').value;
            
            // Create form data
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete');
            
            // Make AJAX call to delete the record
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the table row
                    const row = document.getElementById('row-'+id);
                    if (row) {
                        row.remove();
                    }
                    
                    // Update other rows if needed
                    if (data.updates && data.updates.length > 0) {
                        data.updates.forEach(update => {
                            const row = document.getElementById('row-'+update.id);
                            if (row) {
                                // Update cert count
                                row.cells[4].textContent = update.cert_count;
                                
                                // Update status
                                const isDuplicate = update.status === 'Duplicate';
                                const statusClass = isDuplicate ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                                const statusElement = row.cells[5].querySelector('.status-badge');
                                statusElement.className = 'status-badge ' + statusClass;
                                statusElement.textContent = update.status;
                            }
                        });
                    }
                    
                    // Show success message
                    showSuccess(data.message || 'Certificate deleted successfully!');
                    closeDeleteModal();
                    
                    // Reload the page to refresh the pagination
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the certificate');
            });
        }
        
        // Show success message
        function showSuccess(message) {
            const successElement = document.getElementById('successMessage');
            document.getElementById('successText').textContent = message;
            successElement.style.display = 'block';
            
            // Hide after 3 seconds
            setTimeout(() => {
                successElement.style.display = 'none';
            }, 3000);
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
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

        // Initialize dropdowns when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeDropdowns();
        });
    </script>
</body>
</html>