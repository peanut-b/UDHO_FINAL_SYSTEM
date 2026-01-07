<?php
session_start();
// Database connection
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

// Session timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Handle form submissions for payments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_payment':
                addPayment($conn);
                break;
            case 'update_payment':
                updatePayment($conn);
                break;
            case 'delete_payment':
                deletePayment($conn);
                break;
        }
    }
}

function addPayment($conn) {
    try {
        $hoa_id = $_POST['hoa_id'];
        $hoa_name = $_POST['hoa_name'];
        $payment_period = $_POST['payment_period'];
        $due_date = $_POST['due_date'];
        $amount_due = $_POST['amount_due'];
        $amount_paid = $_POST['amount_paid'];
        $status = calculatePaymentStatus($due_date, $amount_due, $amount_paid);
        
        $stmt = $conn->prepare("INSERT INTO hoa_payments 
                              (hoa_id, hoa_name, payment_period, due_date, amount_due, amount_paid, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssddss", $hoa_id, $hoa_name, $payment_period, $due_date, $amount_due, $amount_paid, $status);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Payment added successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding payment: ' . $e->getMessage()]);
    }
    exit;
}

function updatePayment($conn) {
    try {
        $id = $_POST['id'];
        $hoa_id = $_POST['hoa_id'];
        $hoa_name = $_POST['hoa_name'];
        $payment_period = $_POST['payment_period'];
        $due_date = $_POST['due_date'];
        $amount_due = $_POST['amount_due'];
        $amount_paid = $_POST['amount_paid'];
        $status = calculatePaymentStatus($due_date, $amount_due, $amount_paid);
        
        $stmt = $conn->prepare("UPDATE hoa_payments SET 
                              hoa_id = ?, hoa_name = ?, payment_period = ?, due_date = ?, 
                              amount_due = ?, amount_paid = ?, status = ? 
                              WHERE id = ?");
        $stmt->bind_param("ssssddssi", $hoa_id, $hoa_name, $payment_period, $due_date, $amount_due, $amount_paid, $status, $id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating payment: ' . $e->getMessage()]);
    }
    exit;
}

function deletePayment($conn) {
    try {
        $id = $_POST['id'];
        
        $stmt = $conn->prepare("DELETE FROM hoa_payments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting payment: ' . $e->getMessage()]);
    }
    exit;
}

function calculatePaymentStatus($dueDate, $amountDue, $amountPaid) {
    $balance = $amountDue - $amountPaid;
    $today = date('Y-m-d');
    
    if ($balance <= 0) {
        return 'paid';
    } elseif ($today > $dueDate) {
        return 'overdue';
    } else {
        return 'pending';
    }
}

// Get filtered payments
function getFilteredPayments($conn, $filter = 'all', $search = '', $year = '', $month = '', $day = '', $week = '') {
    $sql = "SELECT * FROM hoa_payments WHERE 1=1";
    $params = [];
    $types = "";
    
    // Apply status filter
    if ($filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $filter;
        $types .= "s";
    }
    
    // Apply search
    if (!empty($search)) {
        $sql .= " AND (hoa_id LIKE ? OR hoa_name LIKE ? OR payment_period LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }
    
    // Apply date filters
    if (!empty($year)) {
        $sql .= " AND YEAR(due_date) = ?";
        $params[] = $year;
        $types .= "s";
    }
    
    if (!empty($month)) {
        $sql .= " AND MONTH(due_date) = ?";
        $params[] = $month;
        $types .= "s";
    }
    
    if (!empty($day)) {
        $sql .= " AND DAY(due_date) = ?";
        $params[] = $day;
        $types .= "s";
    }
    
    if (!empty($week)) {
        $sql .= " AND WEEK(due_date, 1) = ?";
        $params[] = $week;
        $types .= "s";
    }
    
    $sql .= " ORDER BY due_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $payments;
}

// Get payments for the current view
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';
$day = $_GET['day'] ?? '';
$week = $_GET['week'] ?? '';

$payments = getFilteredPayments($conn, $filter, $search, $year, $month, $day, $week);

// Get HOA list for dropdown
function getHoaList($conn) {
    $stmt = $conn->prepare("SELECT DISTINCT hoa_id, hoa_name FROM hoa_payments ORDER BY hoa_name");
    $stmt->execute();
    $result = $stmt->get_result();
    $hoas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $hoas;
}

$hoaList = getHoaList($conn);
if (empty($hoaList)) {
    // Fallback list if no payments exist yet
    $hoaList = [
        ['hoa_id' => '1', 'hoa_name' => 'Geronimo Association'],
    ];
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HOA Payment Records</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
  <style>
    /* Fixed sidebar and scrollable main content */
    html, body {
      height: 100%;
      overflow: hidden;
    }
    
    .main-container {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }
    
    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      width: 16rem;
      overflow-y: auto;
      z-index: 40;
    }
    
    .main-content {
      flex: 1;
      min-width: 0;
      margin-left: 16rem;
      width: calc(100% - 16rem);
      overflow-y: auto;
      height: 100vh;
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
    
    /* Payment Records Styles */
    .table-container {
      overflow-x: auto;
      width: 100%;
    }
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
    
    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-paid {
      background-color: #d1fae5;
      color: #065f46;
    }
    .status-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    .status-overdue {
      background-color: #fee2e2;
      color: #b91c1c;
    }
    
    .amount-paid {
      color: #065f46;
      font-weight: 600;
    }
    .amount-balance {
      color: #b91c1c;
      font-weight: 600;
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
      padding: 2rem;
      border-radius: 0.5rem;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
    }
    
    .edit-input {
      width: 100%;
      padding: 0.375rem 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      background-color: #f9fafb;
    }
    .edit-select {
      width: 100%;
      padding: 0.375rem 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      background-color: #f9fafb;
    }
    
    .search-container {
      position: relative;
      margin-bottom: 1rem;
    }
    .search-input {
      padding-left: 2.5rem;
      width: 100%;
      max-width: 300px;
    }
    .search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #6b7280;
    }
    
    .filter-dropdown {
      margin-right: 0.5rem;
      padding: 0.375rem 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      background-color: #f9fafb;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }
      .main-content {
        margin-left: 0;
      }
      .main-container {
        flex-direction: column;
      }
    }
  </style>
</head>
<body class="bg-gray-100">
  <div class="main-container">
    <!-- Fixed Sidebar -->
    <div class="sidebar bg-gray-800 text-white flex flex-col">
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
                <a href="/Admin executive/HOA/hoa_payment.php" class="submenu-link active">
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

    <!-- Scrollable Main Content -->
    <div class="main-content">
      <div class="p-4 md:p-10">
        <header class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
          <h1 class="text-2xl font-bold text-gray-800">HOA Payment Records with Balances</h1>
          <div class="flex items-center gap-2">
            <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
            <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
          </div>
        </header>

        <div class="flex flex-col md:flex-row justify-between mb-6 gap-4">
          <div class="flex flex-wrap gap-2">
            <button onclick="filterPayments('all')" data-filter="all" class="filter-btn px-4 py-2 rounded-md bg-blue-500 text-white">
              All Payments
            </button>
            <button onclick="filterPayments('paid')" data-filter="paid" class="filter-btn px-4 py-2 rounded-md bg-gray-200 text-gray-700">
              Fully Paid
            </button>
            <button onclick="filterPayments('pending')" data-filter="pending" class="filter-btn px-4 py-2 rounded-md bg-gray-200 text-gray-700">
              Pending
            </button>
            <button onclick="filterPayments('overdue')" data-filter="overdue" class="filter-btn px-4 py-2 rounded-md bg-gray-200 text-gray-700">
              Overdue
            </button>
            
            <!-- Date Filters -->
            <select id="yearFilter" class="filter-dropdown" onchange="applyDateFilters()">
              <option value="">All Years</option>
              <?php
              $currentYear = date('Y');
              for ($i = $currentYear; $i >= $currentYear - 5; $i--) {
                  echo "<option value=\"$i\"".($year == $i ? ' selected' : '').">$i</option>";
              }
              ?>
            </select>
            <select id="monthFilter" class="filter-dropdown" onchange="applyDateFilters()">
              <option value="">All Months</option>
              <?php
              $months = [
                  1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                  5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                  9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
              ];
              foreach ($months as $num => $name) {
                  echo "<option value=\"$num\"".($month == $num ? ' selected' : '').">$name</option>";
              }
              ?>
            </select>
            <select id="dayFilter" class="filter-dropdown" onchange="applyDateFilters()">
              <option value="">All Days</option>
              <?php
              if ($year && $month) {
                  $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                  for ($i = 1; $i <= $daysInMonth; $i++) {
                      echo "<option value=\"$i\"".($day == $i ? ' selected' : '').">$i</option>";
                  }
              }
              ?>
            </select>
            <select id="weekFilter" class="filter-dropdown" onchange="applyDateFilters()">
              <option value="">All Weeks</option>
              <?php
              for ($i = 1; $i <= 5; $i++) {
                  echo "<option value=\"$i\"".($week == $i ? ' selected' : '').">Week $i</option>";
              }
              ?>
            </select>
          </div>
          
          <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" class="edit-input search-input" placeholder="Search payments..." 
                  value="<?= htmlspecialchars($search) ?>" oninput="searchPayments()">
          </div>
        </div>

        <!-- Payment Records Table -->
        <div class="database-section">
          <div class="database-header">
            <div>
              <i class="fas fa-money-bill-wave mr-2"></i> Payment Records with Balances
              <span class="database-count"><?= count($payments) ?> records</span>
            </div>
            <div class="flex items-center space-x-2">
              <button onclick="openAddPaymentModal()" class="bg-green-600 text-white px-3 py-1 rounded text-sm">
                <i class="fas fa-plus mr-1"></i> Record Payment
              </button>
              <button onclick="exportToExcel()" class="bg-purple-600 text-white px-3 py-1 rounded text-sm">
                <i class="fas fa-download mr-1"></i> Export to Excel
              </button>
            </div>
          </div>
          <div class="table-container overflow-x-auto max-h-96">
            <table class="database-table" id="paymentTable">
              <thead>
                <tr>
                  <th>HOA ID</th>
                  <th>Association Name</th>
                  <th>Payment Period</th>
                  <th>Due Date</th>
                  <th>Amount Due</th>
                  <th>Amount Paid</th>
                  <th>Balance</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="paymentRecordsTable">
                <?php foreach ($payments as $payment): ?>
                <?php 
                $balance = $payment['amount_due'] - $payment['amount_paid'];
                $statusClass = $payment['status'] === 'paid' ? 'status-paid' : 
                              ($payment['status'] === 'pending' ? 'status-pending' : 'status-overdue');
                ?>
                <tr data-id="<?= $payment['id'] ?>">
                  <td><?= htmlspecialchars($payment['hoa_id']) ?></td>
                  <td><?= htmlspecialchars($payment['hoa_name']) ?></td>
                  <td><?= htmlspecialchars($payment['payment_period']) ?></td>
                  <td><?= formatDate($payment['due_date']) ?></td>
                  <td>₱<?= number_format($payment['amount_due'], 2) ?></td>
                  <td class="amount-paid">₱<?= number_format($payment['amount_paid'], 2) ?></td>
                  <td class="amount-balance">₱<?= number_format($balance, 2) ?></td>
                  <td><span class="status-badge <?= $statusClass ?>">
                    <?= ucfirst($payment['status']) ?>
                  </span></td>
                  <td>
                    <button onclick="editPayment(<?= $payment['id'] ?>)" class="text-blue-600 hover:text-blue-800 mr-2">
                      <i class="fas fa-edit"></i> Edit
                    </button>
                    <button onclick="deletePayment(<?= $payment['id'] ?>)" class="text-red-600 hover:text-red-800">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr>
                  <td colspan="9" class="text-center py-4 text-gray-500">
                    No payment records found
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Payment Modal -->
  <div id="addPaymentModal" class="modal">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Record New Payment</h2>
        <button onclick="closeAddPaymentModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form id="paymentForm" onsubmit="addPaymentRecord(event)">
        <div class="mb-4">
          <label for="hoaSelect" class="block text-sm font-medium text-gray-700 mb-1">HOA</label>
          <select id="hoaSelect" class="edit-select" required>
            <option value="">Select HOA</option>
            <?php foreach ($hoaList as $hoa): ?>
            <option value="<?= htmlspecialchars($hoa['hoa_id']) ?>">
              <?= htmlspecialchars($hoa['hoa_id']) ?> - <?= htmlspecialchars($hoa['hoa_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="mb-4">
          <label for="hoaName" class="block text-sm font-medium text-gray-700 mb-1">Association Name</label>
          <input type="text" id="hoaName" class="edit-input" required>
        </div>
        
        <div class="mb-4">
          <label for="paymentPeriod" class="block text-sm font-medium text-gray-700 mb-1">Payment Period</label>
          <input type="month" id="paymentPeriod" class="edit-input" required>
        </div>
        
        <div class="mb-4">
          <label for="dueDate" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
          <input type="date" id="dueDate" class="edit-input" required>
        </div>
        
        <div class="mb-4">
          <label for="amountDue" class="block text-sm font-medium text-gray-700 mb-1">Amount Due (₱)</label>
          <input type="number" id="amountDue" class="edit-input" step="0.01" min="0" required>
        </div>
        
        <div class="mb-4">
          <label for="amountPaid" class="block text-sm font-medium text-gray-700 mb-1">Amount Paid (₱)</label>
          <input type="number" id="amountPaid" class="edit-input" step="0.01" min="0" required>
        </div>
        
        <div class="flex justify-end space-x-2 mt-6">
          <button type="button" onclick="closeAddPaymentModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">
            Save Payment
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Payment Modal -->
  <div id="editPaymentModal" class="modal">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Edit Payment Record</h2>
        <button onclick="closeEditPaymentModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form id="editPaymentForm" onsubmit="updatePaymentRecord(event)">
        <input type="hidden" id="editRecordId">
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">HOA ID</label>
          <p id="editHoaId" class="font-medium"></p>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Association Name</label>
          <p id="editHoaName" class="font-medium"></p>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Period</label>
          <p id="editPaymentPeriod" class="font-medium"></p>
        </div>
        
        <div class="mb-4">
          <label for="editDueDate" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
          <input type="date" id="editDueDate" class="edit-input" required>
        </div>
        
        <div class="mb-4">
          <label for="editAmountDue" class="block text-sm font-medium text-gray-700 mb-1">Amount Due (₱)</label>
          <input type="number" id="editAmountDue" class="edit-input" step="0.01" min="0" required>
        </div>
        
        <div class="mb-4">
          <label for="editAmountPaid" class="block text-sm font-medium text-gray-700 mb-1">Amount Paid (₱)</label>
          <input type="number" id="editAmountPaid" class="edit-input" step="0.01" min="0" required>
        </div>
        
        <div class="flex justify-end space-x-2 mt-6">
          <button type="button" onclick="closeEditPaymentModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">
            Update Payment
          </button>
        </div>
      </form>
    </div>
  </div>

<script>
    // Initialize dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        initializeDropdowns();
        
        // Update day filter based on year and month
        document.getElementById('yearFilter').addEventListener('change', updateDayFilter);
        document.getElementById('monthFilter').addEventListener('change', updateDayFilter);
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

    // Format date for display
    function formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00') return 'N/A';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }

    // Update day filter options based on selected year and month
    function updateDayFilter() {
        const year = document.getElementById('yearFilter').value;
        const month = document.getElementById('monthFilter').value;
        const daySelect = document.getElementById('dayFilter');
        
        // Clear existing options except the first
        daySelect.innerHTML = '<option value="">All Days</option>';
        
        if (year && month) {
            // Calculate days in month
            const daysInMonth = new Date(year, month, 0).getDate();
            
            // Add day options
            for (let i = 1; i <= daysInMonth; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                daySelect.appendChild(option);
            }
        }
    }

    // Filter payments
    function filterPayments(status) {
        const url = new URL(window.location.href);
        url.searchParams.set('filter', status);
        window.location.href = url.toString();
    }

    // Apply date filters
    function applyDateFilters() {
        const url = new URL(window.location.href);
        const yearFilter = document.getElementById('yearFilter').value;
        const monthFilter = document.getElementById('monthFilter').value;
        const dayFilter = document.getElementById('dayFilter').value;
        const weekFilter = document.getElementById('weekFilter').value;
        
        if (yearFilter) url.searchParams.set('year', yearFilter);
        else url.searchParams.delete('year');
        
        if (monthFilter) url.searchParams.set('month', monthFilter);
        else url.searchParams.delete('month');
        
        if (dayFilter) url.searchParams.set('day', dayFilter);
        else url.searchParams.delete('day');
        
        if (weekFilter) url.searchParams.set('week', weekFilter);
        else url.searchParams.delete('week');
        
        window.location.href = url.toString();
    }

    // Search payments
    function searchPayments() {
        const searchTerm = document.getElementById('searchInput').value;
        const url = new URL(window.location.href);
        
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }
        
        window.location.href = url.toString();
    }

    // Export to Excel
    function exportToExcel() {
        // Prepare data for export
        const exportData = [
            ['HOA ID', 'Association Name', 'Payment Period', 'Due Date', 'Amount Due', 'Amount Paid', 'Balance', 'Status']
        ];
        
        document.querySelectorAll('#paymentRecordsTable tr[data-id]').forEach(row => {
            const cells = row.cells;
            exportData.push([
                cells[0].textContent,
                cells[1].textContent,
                cells[2].textContent,
                cells[3].textContent,
                parseFloat(cells[4].textContent.replace('₱', '').replace(',', '')),
                parseFloat(cells[5].textContent.replace('₱', '').replace(',', '')),
                parseFloat(cells[6].textContent.replace('₱', '').replace(',', '')),
                cells[7].textContent.trim()
            ]);
        });
        
        // Create worksheet
        const ws = XLSX.utils.aoa_to_sheet(exportData);
        
        // Create workbook
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Payment Records");
        
        // Generate file and download
        const date = new Date().toISOString().split('T')[0];
        XLSX.writeFile(wb, `HOA_Payment_Records_${date}.xlsx`);
    }

    // Modal functions
    function openAddPaymentModal() {
        // Set default values
        const today = new Date();
        const month = (today.getMonth() + 1).toString().padStart(2, '0');
        const year = today.getFullYear();
        document.getElementById('paymentPeriod').value = `${year}-${month}`;
        
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 7);
        document.getElementById('dueDate').value = dueDate.toISOString().split('T')[0];
        
        document.getElementById('addPaymentModal').style.display = 'flex';
    }

    function closeAddPaymentModal() {
        document.getElementById('addPaymentModal').style.display = 'none';
        document.getElementById('paymentForm').reset();
    }

    function openEditPaymentModal() {
        document.getElementById('editPaymentModal').style.display = 'flex';
    }

    function closeEditPaymentModal() {
        document.getElementById('editPaymentModal').style.display = 'none';
    }

    // Get payment details for editing
    function editPayment(paymentId) {
        const row = document.querySelector(`tr[data-id="${paymentId}"]`);
        if (!row) return;
        
        const cells = row.cells;
        document.getElementById('editRecordId').value = paymentId;
        document.getElementById('editHoaId').textContent = cells[0].textContent;
        document.getElementById('editHoaName').textContent = cells[1].textContent;
        document.getElementById('editPaymentPeriod').textContent = cells[2].textContent;
        
        // Parse the displayed date back to YYYY-MM-DD format
        const dueDateText = cells[3].textContent;
        if (dueDateText !== 'N/A') {
            // Handle different date formats
            const date = new Date(dueDateText);
            if (!isNaN(date.getTime())) {
                const formattedDate = date.toISOString().split('T')[0];
                document.getElementById('editDueDate').value = formattedDate;
            }
        }
        
        document.getElementById('editAmountDue').value = parseFloat(cells[4].textContent.replace('₱', '').replace(',', ''));
        document.getElementById('editAmountPaid').value = parseFloat(cells[5].textContent.replace('₱', '').replace(',', ''));
        
        openEditPaymentModal();
    }

    // Add new payment
    function addPaymentRecord(event) {
        event.preventDefault();
        
        const hoaSelect = document.getElementById('hoaSelect');
        const hoaName = document.getElementById('hoaName').value;
        const paymentPeriod = document.getElementById('paymentPeriod').value;
        const dueDate = document.getElementById('dueDate').value;
        const amountDue = parseFloat(document.getElementById('amountDue').value);
        const amountPaid = parseFloat(document.getElementById('amountPaid').value);
        
        // Validate inputs
        if (!hoaSelect.value || !hoaName || !paymentPeriod || !dueDate || isNaN(amountDue) || isNaN(amountPaid)) {
            showAlert('Please fill in all required fields with valid values', 'error');
            return;
        }
        
        // Format payment period (YYYY-MM to Month YYYY)
        const [year, month] = paymentPeriod.split('-');
        const date = new Date(year, month - 1);
        const formattedPeriod = date.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'add_payment');
        formData.append('hoa_id', hoaSelect.value);
        formData.append('hoa_name', hoaName);
        formData.append('payment_period', formattedPeriod);
        formData.append('due_date', dueDate);
        formData.append('amount_due', amountDue);
        formData.append('amount_paid', amountPaid);
        
        // Submit via AJAX
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'error');
        });
    }

    // Update payment
    function updatePaymentRecord(event) {
        event.preventDefault();
        
        const paymentId = document.getElementById('editRecordId').value;
        const dueDate = document.getElementById('editDueDate').value;
        const amountDue = parseFloat(document.getElementById('editAmountDue').value);
        const amountPaid = parseFloat(document.getElementById('editAmountPaid').value);
        
        // Validate inputs
        if (!dueDate || isNaN(amountDue) || isNaN(amountPaid)) {
            showAlert('Please fill in all required fields with valid values', 'error');
            return;
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'update_payment');
        formData.append('id', paymentId);
        formData.append('hoa_id', document.getElementById('editHoaId').textContent);
        formData.append('hoa_name', document.getElementById('editHoaName').textContent);
        formData.append('payment_period', document.getElementById('editPaymentPeriod').textContent);
        formData.append('due_date', dueDate);
        formData.append('amount_due', amountDue);
        formData.append('amount_paid', amountPaid);
        
        // Submit via AJAX
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'error');
        });
    }

    // Delete payment
    function deletePayment(paymentId) {
        if (!confirm('Are you sure you want to delete this payment record?')) return;
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'delete_payment');
        formData.append('id', paymentId);
        
        // Submit via AJAX
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'error');
        });
    }

    // Show alert message
    function showAlert(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `fixed top-4 right-4 p-4 rounded-md shadow-md text-white ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        }`;
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-2"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 5000);
    }

    // Update HOA name when HOA ID is selected
    document.getElementById('hoaSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const hoaName = selectedOption.text.split(' - ')[1];
            document.getElementById('hoaName').value = hoaName;
        }
    });

    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addPaymentModal');
        const editModal = document.getElementById('editPaymentModal');
        
        if (event.target == addModal) {
            closeAddPaymentModal();
        }
        if (event.target == editModal) {
            closeEditPaymentModal();
        }
    }
</script>
</body>
</html>

<?php
function formatDate($dateString) {
    if (empty($dateString) || $dateString === '0000-00-00') return 'N/A';
    $date = new DateTime($dateString);
    return $date->format('M j, Y');
}
?>