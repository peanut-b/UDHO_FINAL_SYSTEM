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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_hoa':
                $hoa_id = $_POST['hoa_id'];
                $barangay = $_POST['barangay'];
                $district = $_POST['district'];
                $reg_date = $_POST['reg_date'];
                $status = $_POST['status'];
                $hoa_status = $_POST['hoa_status']; // New status field
                
                $stmt = $conn->prepare("UPDATE hoa_associations SET barangay=?, district=?, reg_date=?, status=?, hoa_status=? WHERE hoa_id=?");
                $stmt->bind_param("ssssss", $barangay, $district, $reg_date, $status, $hoa_status, $hoa_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'save_official':
                $official_id = $_POST['official_id'] ?? null;
                $hoa_id = $_POST['hoa_id'];
                $position = $_POST['position'];
                $name = $_POST['name'];
                $contact = $_POST['contact'];
                $term_start = $_POST['term_start'];
                $term_end = $_POST['term_end'];
                
                if ($official_id) {
                    // Update existing official
                    $stmt = $conn->prepare("UPDATE hoa_officials SET position=?, name=?, contact=?, term_start=?, term_end=? WHERE id=?");
                    $stmt->bind_param("sssssi", $position, $name, $contact, $term_start, $term_end, $official_id);
                } else {
                    // Add new official
                    $stmt = $conn->prepare("INSERT INTO hoa_officials (hoa_id, position, name, contact, term_start, term_end) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $hoa_id, $position, $name, $contact, $term_start, $term_end);
                }
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'delete_official':
                $official_id = $_POST['official_id'];
                $stmt = $conn->prepare("DELETE FROM hoa_officials WHERE id=?");
                $stmt->bind_param("i", $official_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'save_member':
                $member_id = $_POST['member_id'] ?? null;
                $hoa_id = $_POST['hoa_id'];
                $name = $_POST['name'];
                $address = $_POST['address'];
                $contact = $_POST['contact'];
                $status = $_POST['status'];
                
                if ($member_id) {
                    // Update existing member
                    $stmt = $conn->prepare("UPDATE hoa_members SET name=?, address=?, contact=?, status=? WHERE id=?");
                    $stmt->bind_param("ssssi", $name, $address, $contact, $status, $member_id);
                } else {
                    // Add new member
                    $stmt = $conn->prepare("INSERT INTO hoa_members (hoa_id, name, address, contact, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $hoa_id, $name, $address, $contact, $status);
                }
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'delete_member':
                $member_id = $_POST['member_id'];
                $stmt = $conn->prepare("DELETE FROM hoa_members WHERE id=?");
                $stmt->bind_param("i", $member_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'add_hoa':
                $hoa_id = $_POST['hoa_id'];
                $name = $_POST['name'];
                $barangay = $_POST['barangay'];
                $district = $_POST['district'];
                $reg_date = $_POST['reg_date'];
                $status = $_POST['status'];
                $hoa_status = $_POST['hoa_status']; // New status field
                
                $stmt = $conn->prepare("INSERT INTO hoa_associations (hoa_id, name, barangay, district, reg_date, status, hoa_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $hoa_id, $name, $barangay, $district, $reg_date, $status, $hoa_status);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'delete_hoa':
                $hoa_id = $_POST['hoa_id'];
                
                // First delete related records to maintain referential integrity
                $stmt = $conn->prepare("DELETE FROM hoa_officials WHERE hoa_id = ?");
                $stmt->bind_param("s", $hoa_id);
                $stmt->execute();
                $stmt->close();
                
                $stmt = $conn->prepare("DELETE FROM hoa_members WHERE hoa_id = ?");
                $stmt->bind_param("s", $hoa_id);
                $stmt->execute();
                $stmt->close();
                
                // Then delete the HOA itself
                $stmt = $conn->prepare("DELETE FROM hoa_associations WHERE hoa_id = ?");
                $stmt->bind_param("s", $hoa_id);
                $stmt->execute();
                $stmt->close();
                break;
        }
    }
}

// Get HOA data
function getHoaData($conn, $hoa_id) {
    $data = [];
    
    // Get basic HOA info
    $stmt = $conn->prepare("SELECT * FROM hoa_associations WHERE hoa_id = ?");
    $stmt->bind_param("s", $hoa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if ($data) {
        // Get officials
        $stmt = $conn->prepare("SELECT * FROM hoa_officials WHERE hoa_id = ?");
        $stmt->bind_param("s", $hoa_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data['officials'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get members
        $stmt = $conn->prepare("SELECT * FROM hoa_members WHERE hoa_id = ?");
        $stmt->bind_param("s", $hoa_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data['members'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    return $data;
}

// Get all HOAs for the main table
function getAllHoas($conn) {
    $stmt = $conn->prepare("SELECT * FROM hoa_associations");
    $stmt->execute();
    $result = $stmt->get_result();
    $hoas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $hoas;
}

// Get HOA details for AJAX request
if (isset($_GET['get_hoa']) && isset($_GET['hoa_id'])) {
    $hoa_id = $_GET['hoa_id'];
    $hoa = getHoaData($conn, $hoa_id);
    header('Content-Type: application/json');
    echo json_encode($hoa);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HOA Records</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
    
    .sidebar-fixed {
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      width: 16rem;
      z-index: 40;
      overflow-y: auto;
    }
    
    .main-content-scrollable {
      flex: 1;
      margin-left: 16rem;
      height: 100vh;
      overflow-y: auto;
      background-color: #f7fafc;
    }
    
    /* Custom styles for responsive design */
    .table-container {
      overflow-x: auto;
      width: 100%;
    }
    .table-container::-webkit-scrollbar {
      height: 8px;
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
    
    .database-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
    }
    .database-table th {
      background-color: #edf2f7;
      padding: 0.75rem;
      text-align: left;
      position: sticky;
      top: 0;
      white-space: nowrap;
    }
    .database-table td {
      padding: 0.75rem;
      border-top: 1px solid #e2e8f0;
      white-space: nowrap;
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
    .status-complete {
      background-color: #d1fae5;
      color: #065f46;
    }
    .status-pending-secretariat {
      background-color: #fef3c7;
      color: #92400e;
    }
    .status-pending-dhsud {
      background-color: #fef3c7;
      color: #92400e;
    }
    .status-shfc-verified {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .status-site-visit {
      background-color: #e0e7ff;
      color: #3730a3;
    }
    
    .hoa-details-panel {
      display: none;
      position: fixed;
      top: 0;
      right: 0;
      width: 40%;
      height: 100%;
      background: white;
      box-shadow: -2px 0 10px rgba(0,0,0,0.1);
      z-index: 1000;
      overflow-y: auto;
      padding: 20px;
    }
    .backdrop {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 999;
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
    
    #addHoaModal, #successModal, #addOfficialModal, #addMemberModal, #officialDetailsModal, #memberDetailsModal {
      transition: opacity 0.3s ease;
      z-index: 1100;
    }
    .modal-content {
      animation: modalFadeIn 0.3s ease-out;
    }
    @keyframes modalFadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Search bar styles */
    .search-container {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 1rem;
      width: 100%;
    }
    .search-input {
      padding: 0.5rem 1rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      font-size: 0.95rem;
      flex: 1;
      min-width: 200px;
    }
    .search-input:focus {
      outline: none;
      border-color: #4c51bf;
      box-shadow: 0 0 0 3px rgba(76, 81, 191, 0.1);
    }
    
    /* Responsive adjustments */
    @media (max-width: 1024px) {
      .hoa-details-panel {
        width: 60%;
      }
    }
    @media (max-width: 768px) {
      .sidebar-fixed {
        width: 100%;
        height: auto;
        position: relative;
      }
      .main-content-scrollable {
        margin-left: 0;
      }
      .main-container {
        flex-direction: column;
      }
      .hoa-details-panel {
        width: 80%;
      }
      .database-table {
        font-size: 0.875rem;
      }
      .database-table th,
      .database-table td {
        padding: 0.5rem;
      }
    }
    @media (max-width: 640px) {
      .hoa-details-panel {
        width: 100%;
      }
    }
    
    /* Sidebar styles */
    .sidebar-link {
      display: block;
      padding: 10px 14px;
      border-radius: 6px;
      transition: all 0.3s ease;
    }
    .sidebar-link:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    .active-link {
      background-color: rgba(255, 255, 255, 0.2);
    }
    
    /* Filter buttons */
    .filter-btn, .district-filter-btn {
      transition: all 0.2s;
      font-size: 0.875rem;
      padding: 0.375rem 0.75rem;
      white-space: nowrap;
    }
    .filter-btn:hover, .district-filter-btn:hover {
      transform: translateY(-1px);
    }
    
    /* Table row highlighting */
    .table-row-highlight:hover {
      background-color: #f0f9ff;
    }
    
    /* Button group container */
    .button-group-container {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
      align-items: center;
      width: 100%;
    }
    
    /* Add HOA button */
    .add-hoa-btn {
      margin-left: auto;
      font-size: 0.875rem;
      padding: 0.375rem 0.75rem;
      white-space: nowrap;
    }
    
    /* Active button state */
    .active-filter {
      background-color: #3b82f6 !important;
      color: white !important;
    }
    
    @media (max-width: 768px) {
      .button-group-container {
        flex-direction: column;
        align-items: stretch;
      }
      .add-hoa-btn {
        margin-left: 0;
        width: 100%;
      }
    }
  </style>
</head>
<body class="bg-gray-100">
  <div class="main-container">
    <!-- Fixed Sidebar -->
    <div class="sidebar-fixed bg-gray-800 text-white flex flex-col">
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
      
      <nav class="mt-6 flex-1 overflow-y-auto">
        <ul>
          <li>
            <a href="hoa_dashboard.php" class="sidebar-link flex items-center py-3 px-4">
              <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
            </a>
          </li>
          <li>
            <a href="hoa_records.php" class="sidebar-link flex items-center py-3 px-4 active-link">
              <i class="fas fa-home mr-3"></i> HOA Management
            </a>
          </li>
          <li>
            <a href="hoa_payment.php" class="sidebar-link flex items-center py-3 px-4">
              <i class="fas fa-money-bill-wave mr-3"></i> Payment Records
            </a>
          </li>
          <li>
            <a href="../settings.php" class="sidebar-link flex items-center py-3 px-4">
              <i class="fas fa-cog mr-3"></i> Settings
            </a>
          </li>
          <li class="mt-auto">
            <a href="../logout.php" class="sidebar-link flex items-center py-3 px-4">
              <i class="fas fa-sign-out-alt mr-3"></i> Logout
            </a>
          </li>
        </ul>
      </nav>
    </div>

    <!-- Scrollable Main Content -->
    <div class="main-content-scrollable">
      <div class="p-4 md:p-6">
        <header class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
          <h1 class="text-2xl font-bold text-gray-800">Homeowners Association Management</h1>
          <div class="flex items-center gap-2">
            <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
            <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
          </div>
        </header>

        <!-- Search Bar -->
        <div class="search-container">
          <input type="text" id="searchInput" class="search-input" placeholder="Search HOAs...">
          <button onclick="searchHOAs()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors whitespace-nowrap">
            <i class="fas fa-search mr-2"></i> Search
          </button>
        </div>

        <!-- All Buttons in One Line -->
        <div class="button-group-container">
          <!-- District Filter Buttons -->
          <button onclick="filterDistrict('all')" data-district="all" class="district-filter-btn px-3 py-2 rounded-md active-filter">
            All Districts
          </button>
          <button onclick="filterDistrict('1')" data-district="1" class="district-filter-btn px-3 py-2 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">
            District 1
          </button>
          <button onclick="filterDistrict('2')" data-district="2" class="district-filter-btn px-3 py-2 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">
            District 2
          </button>
          
          <!-- Divider -->
          <div class="border-l border-gray-300 h-6 mx-1"></div>
          
          <!-- Status Filter Buttons -->
          <button onclick="filterHOA('all')" data-filter="all" class="filter-btn px-3 py-2 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">
            All HOAs
          </button>
          <button onclick="filterHOA('active')" data-filter="active" class="filter-btn px-3 py-2 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">
            Active
          </button>
          <button onclick="filterHOA('inactive')" data-filter="inactive" class="filter-btn px-3 py-2 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">
            Inactive
          </button>
          <button onclick="filterHOA('abolished')" data-filter="abolished" class="filter-btn px-3 py-2 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">
            Abolished
          </button>
          
          <!-- Add HOA Button -->
          <button onclick="openAddHoaModal()" class="add-hoa-btn px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
            <i class="fas fa-plus mr-1"></i> Add HOA
          </button>
        </div>

        <!-- HOA List Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
          <div class="bg-blue-600 text-white px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
              <i class="fas fa-home mr-2"></i> 
              <span>Homeowners Associations</span>
              <span class="bg-blue-500 text-white px-2 py-1 rounded-full text-sm ml-2" id="hoaCount"><?php echo count(getAllHoas($conn)); ?> records</span>
            </div>
          </div>
          <div class="table-container">
            <table class="database-table">
              <thead>
                <tr>
                  <th>HOA ID</th>
                  <th>Association Name</th>
                  <th>Barangay</th>
                  <th>District</th>
                  <th>President</th>
                  <th>Contact</th>
                  <th>Members</th>
                  <th>Status</th>
                  <th>HOA Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="hoaTableBody">
                <?php
                $hoas = getAllHoas($conn);
                foreach ($hoas as $hoa): 
                    // Get president info
                    $stmt = $conn->prepare("SELECT name, contact FROM hoa_officials WHERE hoa_id = ? AND position = 'President' LIMIT 1");
                    $stmt->bind_param("s", $hoa['hoa_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $president = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Count members
                    $stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM hoa_members WHERE hoa_id = ?");
                    $stmt->bind_param("s", $hoa['hoa_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $member_count = $result->fetch_assoc()['member_count'];
                    $stmt->close();
                    
                    $status_class = '';
                    if ($hoa['status'] === 'Active') $status_class = 'status-active';
                    elseif ($hoa['status'] === 'Inactive') $status_class = 'status-inactive';
                    else $status_class = 'status-abolished';
                    
                    $hoa_status_class = '';
                    if ($hoa['hoa_status'] === 'Complete and Verified') $hoa_status_class = 'status-complete';
                    elseif ($hoa['hoa_status'] === 'Pending for Secretariat') $hoa_status_class = 'status-pending-secretariat';
                    elseif ($hoa['hoa_status'] === 'Pending for DHSUD') $hoa_status_class = 'status-pending-dhsud';
                    elseif ($hoa['hoa_status'] === 'SHFC CMP Verified') $hoa_status_class = 'status-shfc-verified';
                    elseif ($hoa['hoa_status'] === 'For Site Visit') $hoa_status_class = 'status-site-visit';
                ?>
                <tr class="table-row-highlight" data-district="<?php echo htmlspecialchars($hoa['district'] ?? ''); ?>" data-search="<?php echo htmlspecialchars(strtolower($hoa['hoa_id'] . ' ' . $hoa['name'] . ' ' . $hoa['barangay'] . ' ' . ($president['name'] ?? '') . ' ' . $hoa['status'] . ' ' . $hoa['hoa_status'])); ?>">
                  <td><?php echo htmlspecialchars($hoa['hoa_id']); ?></td>
                  <td><?php echo htmlspecialchars($hoa['name']); ?></td>
                  <td><?php echo htmlspecialchars($hoa['barangay']); ?></td>
                  <td><?php echo htmlspecialchars($hoa['district'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($president['name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($president['contact'] ?? 'N/A'); ?></td>
                  <td><?php echo $member_count; ?></td>
                  <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($hoa['status']); ?></span></td>
                  <td><span class="status-badge <?php echo $hoa_status_class; ?>"><?php echo htmlspecialchars($hoa['hoa_status']); ?></span></td>
                  <td>
                    <button onclick="showHOADetails('<?php echo $hoa['hoa_id']; ?>')" class="text-blue-600 hover:text-blue-800 mr-2 transition-colors">
                      <i class="fas fa-eye"></i> View
                    </button>
                    <button onclick="deleteHOA('<?php echo $hoa['hoa_id']; ?>')" class="text-red-600 hover:text-red-800 transition-colors">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Add HOA Modal -->
        <div id="addHoaModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
          <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Add New HOA</h3>
              <button onclick="closeAddHoaModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form id="addHoaForm" onsubmit="addNewHOA(event)">
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">HOA ID</label>
                  <input type="text" name="hoa_id" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Association Name</label>
                  <input type="text" name="name" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Barangay</label>
                  <input type="text" name="barangay" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">District</label>
                  <select name="district" required class="edit-select mt-1">
                    <option value="">Select District</option>
                    <option value="1">District 1</option>
                    <option value="2">District 2</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Registration Date</label>
                  <input type="date" name="reg_date" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Status</label>
                  <select name="status" required class="edit-select mt-1">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Abolished">Abolished</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">HOA Status</label>
                  <select name="hoa_status" required class="edit-select mt-1">
                    <option value="Complete and Verified">Complete and Verified</option>
                    <option value="Pending for Secretariat">Pending for Secretariat</option>
                    <option value="Pending for DHSUD">Pending for DHSUD</option>
                    <option value="SHFC CMP Verified">SHFC CMP Verified</option>
                    <option value="For Site Visit">For Site Visit</option>
                  </select>
                </div>
              </div>
              <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeAddHoaModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                  Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                  Save
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Success Modal -->
        <div id="successModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
          <div class="bg-white rounded-lg p-6 w-full max-w-sm modal-content">
            <div class="text-center">
              <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
              </div>
              <h3 class="text-lg font-medium text-gray-900 mt-3" id="successModalTitle">Success</h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500" id="successModalMessage">Operation completed successfully.</p>
              </div>
              <div class="mt-4">
                <button type="button" onclick="closeSuccessModal()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                  OK
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Official Modal -->
        <div id="addOfficialModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
          <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Add New Official</h3>
              <button onclick="closeAddOfficialModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form id="addOfficialForm">
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Position</label>
                  <select name="position" required class="edit-select mt-1">
                    <option value="President">President</option>
                    <option value="Vice President">Vice President</option>
                    <option value="Secretary">Secretary</option>
                    <option value="Treasurer">Treasurer</option>
                    <option value="Auditor">Auditor</option>
                    <option value="PRO">PRO</option>
                    <option value="Board Member">Board Member</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <input type="text" name="name" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Contact</label>
                  <input type="text" name="contact" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Term Start</label>
                  <input type="date" name="term_start" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Term End</label>
                  <input type="date" name="term_end" required class="edit-input mt-1">
                </div>
              </div>
              <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeAddOfficialModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                  Cancel
                </button>
                <button type="button" onclick="submitAddOfficialForm()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                  Save
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Add Member Modal -->
        <div id="addMemberModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
          <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Add New Member</h3>
              <button onclick="closeAddMemberModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form id="addMemberForm">
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <input type="text" name="name" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Address</label>
                  <input type="text" name="address" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Contact</label>
                  <input type="text" name="contact" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Status</label>
                  <select name="status" required class="edit-select mt-1">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>
              <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeAddMemberModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                  Cancel
                </button>
                <button type="button" onclick="submitAddMemberForm()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                  Save
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Official Details Modal -->
        <div id="officialDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
          <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Official Details</h3>
              <button onclick="closeOfficialDetailsModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form id="officialDetailsForm">
              <input type="hidden" name="official_id" id="editOfficialId">
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Position</label>
                  <select name="position" id="editOfficialPosition" required class="edit-select mt-1">
                    <option value="President">President</option>
                    <option value="Vice President">Vice President</option>
                    <option value="Secretary">Secretary</option>
                    <option value="Treasurer">Treasurer</option>
                    <option value="Auditor">Auditor</option>
                    <option value="PRO">PRO</option>
                    <option value="Board Member">Board Member</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <input type="text" name="name" id="editOfficialName" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Contact</label>
                  <input type="text" name="contact" id="editOfficialContact" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Term Start</label>
                  <input type="date" name="term_start" id="editOfficialTermStart" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Term End</label>
                  <input type="date" name="term_end" id="editOfficialTermEnd" required class="edit-input mt-1">
                </div>
              </div>
              <div class="mt-6 flex justify-between">
                <button type="button" onclick="deleteOfficial()" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
                  Delete
                </button>
                <div class="space-x-3">
                  <button type="button" onclick="closeOfficialDetailsModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                    Cancel
                  </button>
                  <button type="button" onclick="submitOfficialDetailsForm()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                    Save Changes
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Member Details Modal -->
        <div id="memberDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
          <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Member Details</h3>
              <button onclick="closeMemberDetailsModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form id="memberDetailsForm">
              <input type="hidden" name="member_id" id="editMemberId">
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <input type="text" name="name" id="editMemberName" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Address</label>
                  <input type="text" name="address" id="editMemberAddress" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Contact</label>
                  <input type="text" name="contact" id="editMemberContact" required class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Status</label>
                  <select name="status" id="editMemberStatus" required class="edit-select mt-1">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>
              <div class="mt-6 flex justify-between">
                <button type="button" onclick="deleteMember()" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
                  Delete
                </button>
                <div class="space-x-3">
                  <button type="button" onclick="closeMemberDetailsModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                    Cancel
                  </button>
                  <button type="button" onclick="submitMemberDetailsForm()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                    Save Changes
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- HOA Details Panel -->
        <div class="backdrop" onclick="closeHOADetails()"></div>
        <div id="hoaDetailsPanel" class="hoa-details-panel">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">HOA Details</h2>
            <button onclick="closeHOADetails()" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div id="hoaDetailsContent">
            <!-- Content will be loaded dynamically -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    let currentHoaId = null;
    let currentOfficialId = null;
    let currentMemberId = null;

    // Search functionality
    function searchHOAs() {
      const input = document.getElementById('searchInput');
      const filter = input.value.toLowerCase();
      const rows = document.querySelectorAll('#hoaTableBody tr');
      let visibleCount = 0;
      
      rows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        if (searchData.includes(filter)) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      // Update count
      document.getElementById('hoaCount').textContent = `${visibleCount} records`;
    }

    // Search on Enter key
    document.getElementById('searchInput').addEventListener('keyup', function(event) {
      if (event.key === 'Enter') {
        searchHOAs();
      }
    });

    // Filter by status
    function filterHOA(status) {
      const rows = document.querySelectorAll('#hoaTableBody tr');
      const buttons = document.querySelectorAll('.filter-btn');
      let visibleCount = 0;
      
      // Update button styles
      buttons.forEach(btn => {
        if (btn.getAttribute('data-filter') === status) {
          btn.classList.add('active-filter');
          btn.classList.remove('bg-gray-200', 'text-gray-700');
        } else {
          btn.classList.remove('active-filter');
          btn.classList.add('bg-gray-200', 'text-gray-700');
        }
      });
      
      rows.forEach(row => {
        if (status === 'all') {
          row.style.display = '';
          visibleCount++;
        } else {
          const statusText = row.querySelector('td:nth-child(8) .status-badge').textContent.toLowerCase();
          if (statusText === status) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        }
      });
      
      // Update count
      document.getElementById('hoaCount').textContent = `${visibleCount} records`;
    }

    // Filter by district
    function filterDistrict(district) {
      const rows = document.querySelectorAll('#hoaTableBody tr');
      const buttons = document.querySelectorAll('.district-filter-btn');
      let visibleCount = 0;
      
      // Update button styles
      buttons.forEach(btn => {
        if (btn.getAttribute('data-district') === district) {
          btn.classList.add('active-filter');
          btn.classList.remove('bg-gray-200', 'text-gray-700');
        } else {
          btn.classList.remove('active-filter');
          btn.classList.add('bg-gray-200', 'text-gray-700');
        }
      });
      
      rows.forEach(row => {
        if (district === 'all') {
          row.style.display = '';
          visibleCount++;
        } else {
          const rowDistrict = row.getAttribute('data-district');
          if (rowDistrict === district) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        }
      });
      
      // Update count
      document.getElementById('hoaCount').textContent = `${visibleCount} records`;
    }

    // Modal functions
    function openAddHoaModal() {
      document.getElementById('addHoaModal').classList.remove('hidden');
    }

    function closeAddHoaModal() {
      document.getElementById('addHoaModal').classList.add('hidden');
    }

    function openAddOfficialModal() {
      document.getElementById('addOfficialModal').classList.remove('hidden');
    }

    function closeAddOfficialModal() {
      document.getElementById('addOfficialModal').classList.add('hidden');
      document.getElementById('addOfficialForm').reset();
    }

    function openAddMemberModal() {
      document.getElementById('addMemberModal').classList.remove('hidden');
    }

    function closeAddMemberModal() {
      document.getElementById('addMemberModal').classList.add('hidden');
      document.getElementById('addMemberForm').reset();
    }

    function openOfficialDetailsModal(official) {
      document.getElementById('editOfficialId').value = official.id;
      document.getElementById('editOfficialPosition').value = official.position;
      document.getElementById('editOfficialName').value = official.name;
      document.getElementById('editOfficialContact').value = official.contact;
      document.getElementById('editOfficialTermStart').value = official.term_start;
      document.getElementById('editOfficialTermEnd').value = official.term_end;
      document.getElementById('officialDetailsModal').classList.remove('hidden');
      currentOfficialId = official.id;
    }

    function closeOfficialDetailsModal() {
      document.getElementById('officialDetailsModal').classList.add('hidden');
      currentOfficialId = null;
    }

    function openMemberDetailsModal(member) {
      document.getElementById('editMemberId').value = member.id;
      document.getElementById('editMemberName').value = member.name;
      document.getElementById('editMemberAddress').value = member.address;
      document.getElementById('editMemberContact').value = member.contact;
      document.getElementById('editMemberStatus').value = member.status;
      document.getElementById('memberDetailsModal').classList.remove('hidden');
      currentMemberId = member.id;
    }

    function closeMemberDetailsModal() {
      document.getElementById('memberDetailsModal').classList.add('hidden');
      currentMemberId = null;
    }

    function showSuccessModal(title, message) {
      document.getElementById('successModalTitle').textContent = title;
      document.getElementById('successModalMessage').textContent = message;
      document.getElementById('successModal').classList.remove('hidden');
    }

    function closeSuccessModal() {
      document.getElementById('successModal').classList.add('hidden');
      location.reload(); // Refresh the page to show updated data
    }

    // HOA Details functions
    function showHOADetails(hoaId) {
      currentHoaId = hoaId;
      fetch(`?get_hoa=1&hoa_id=${hoaId}`)
        .then(response => response.json())
        .then(data => {
          document.getElementById('hoaDetailsContent').innerHTML = createHoaDetailsHTML(data);
          document.getElementById('hoaDetailsPanel').style.display = 'block';
          document.querySelector('.backdrop').style.display = 'block';
        })
        .catch(error => console.error('Error:', error));
    }

    function closeHOADetails() {
      document.getElementById('hoaDetailsPanel').style.display = 'none';
      document.querySelector('.backdrop').style.display = 'none';
      currentHoaId = null;
    }

    function createHoaDetailsHTML(hoa) {
      return `
        <div class="space-y-6">
          <!-- Basic Information -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Basic Information</h3>
            <form id="hoaBasicForm" onsubmit="saveHoaBasicInfo(event)">
              <input type="hidden" name="hoa_id" value="${hoa.hoa_id}">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">HOA ID</label>
                  <input type="text" value="${hoa.hoa_id}" class="edit-input mt-1" disabled>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Association Name</label>
                  <input type="text" name="name" value="${hoa.name}" class="edit-input mt-1" disabled>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Barangay</label>
                  <input type="text" name="barangay" value="${hoa.barangay}" class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">District</label>
                  <select name="district" class="edit-select mt-1">
                    <option value="1" ${hoa.district === '1' ? 'selected' : ''}>District 1</option>
                    <option value="2" ${hoa.district === '2' ? 'selected' : ''}>District 2</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Registration Date</label>
                  <input type="date" name="reg_date" value="${hoa.reg_date}" class="edit-input mt-1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Status</label>
                  <select name="status" class="edit-select mt-1">
                    <option value="Active" ${hoa.status === 'Active' ? 'selected' : ''}>Active</option>
                    <option value="Inactive" ${hoa.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                    <option value="Abolished" ${hoa.status === 'Abolished' ? 'selected' : ''}>Abolished</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">HOA Status</label>
                  <select name="hoa_status" class="edit-select mt-1">
                    <option value="Complete and Verified" ${hoa.hoa_status === 'Complete and Verified' ? 'selected' : ''}>Complete and Verified</option>
                    <option value="Pending for Secretariat" ${hoa.hoa_status === 'Pending for Secretariat' ? 'selected' : ''}>Pending for Secretariat</option>
                    <option value="Pending for DHSUD" ${hoa.hoa_status === 'Pending for DHSUD' ? 'selected' : ''}>Pending for DHSUD</option>
                    <option value="SHFC CMP Verified" ${hoa.hoa_status === 'SHFC CMP Verified' ? 'selected' : ''}>SHFC CMP Verified</option>
                    <option value="For Site Visit" ${hoa.hoa_status === 'For Site Visit' ? 'selected' : ''}>For Site Visit</option>
                  </select>
                </div>
              </div>
              <div class="mt-4 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                  Save Changes
                </button>
              </div>
            </form>
          </div>

          <!-- Officials Section -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <div class="flex justify-between items-center mb-3">
              <h3 class="text-lg font-semibold">Officials</h3>
              <button onclick="openAddOfficialModal()" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600 transition-colors">
                <i class="fas fa-plus mr-1"></i> Add Official
              </button>
            </div>
            <div class="space-y-2">
              ${hoa.officials && hoa.officials.length > 0 ? hoa.officials.map(official => `
                <div class="flex justify-between items-center p-2 bg-white rounded border">
                  <div>
                    <span class="font-medium">${official.position}:</span> ${official.name}
                  </div>
                  <button onclick="openOfficialDetailsModal(${JSON.stringify(official).replace(/"/g, '&quot;')})" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-edit"></i>
                  </button>
                </div>
              `).join('') : '<p class="text-gray-500">No officials found.</p>'}
            </div>
          </div>

          <!-- Members Section -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <div class="flex justify-between items-center mb-3">
              <h3 class="text-lg font-semibold">Members (${hoa.members ? hoa.members.length : 0})</h3>
              <button onclick="openAddMemberModal()" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600 transition-colors">
                <i class="fas fa-plus mr-1"></i> Add Member
              </button>
            </div>
            <div class="space-y-2">
              ${hoa.members && hoa.members.length > 0 ? hoa.members.map(member => `
                <div class="flex justify-between items-center p-2 bg-white rounded border">
                  <div>
                    <span class="font-medium">${member.name}</span> - ${member.status}
                  </div>
                  <button onclick="openMemberDetailsModal(${JSON.stringify(member).replace(/"/g, '&quot;')})" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-edit"></i>
                  </button>
                </div>
              `).join('') : '<p class="text-gray-500">No members found.</p>'}
            </div>
          </div>
        </div>
      `;
    }

    // Form submission functions
    function addNewHOA(event) {
      event.preventDefault();
      const formData = new FormData(event.target);
      formData.append('action', 'add_hoa');
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        closeAddHoaModal();
        showSuccessModal('Success', 'HOA added successfully!');
      })
      .catch(error => console.error('Error:', error));
    }

    function saveHoaBasicInfo(event) {
      event.preventDefault();
      const formData = new FormData(event.target);
      formData.append('action', 'save_hoa');
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        showSuccessModal('Success', 'HOA information updated successfully!');
      })
      .catch(error => console.error('Error:', error));
    }

    function submitAddOfficialForm() {
      const formData = new FormData(document.getElementById('addOfficialForm'));
      formData.append('action', 'save_official');
      formData.append('hoa_id', currentHoaId);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        closeAddOfficialModal();
        showSuccessModal('Success', 'Official added successfully!');
      })
      .catch(error => console.error('Error:', error));
    }

    function submitAddMemberForm() {
      const formData = new FormData(document.getElementById('addMemberForm'));
      formData.append('action', 'save_member');
      formData.append('hoa_id', currentHoaId);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        closeAddMemberModal();
        showSuccessModal('Success', 'Member added successfully!');
      })
      .catch(error => console.error('Error:', error));
    }

    function submitOfficialDetailsForm() {
      const formData = new FormData(document.getElementById('officialDetailsForm'));
      formData.append('action', 'save_official');
      formData.append('hoa_id', currentHoaId);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        closeOfficialDetailsModal();
        showSuccessModal('Success', 'Official updated successfully!');
      })
      .catch(error => console.error('Error:', error));
    }

    function submitMemberDetailsForm() {
      const formData = new FormData(document.getElementById('memberDetailsForm'));
      formData.append('action', 'save_member');
      formData.append('hoa_id', currentHoaId);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        closeMemberDetailsModal();
        showSuccessModal('Success', 'Member updated successfully!');
      })
      .catch(error => console.error('Error:', error));
    }

    function deleteOfficial() {
      if (confirm('Are you sure you want to delete this official?')) {
        const formData = new FormData();
        formData.append('action', 'delete_official');
        formData.append('official_id', currentOfficialId);
        
        fetch('', {
          method: 'POST',
          body: formData
        })
        .then(response => {
          closeOfficialDetailsModal();
          showSuccessModal('Success', 'Official deleted successfully!');
        })
        .catch(error => console.error('Error:', error));
      }
    }

    function deleteMember() {
      if (confirm('Are you sure you want to delete this member?')) {
        const formData = new FormData();
        formData.append('action', 'delete_member');
        formData.append('member_id', currentMemberId);
        
        fetch('', {
          method: 'POST',
          body: formData
        })
        .then(response => {
          closeMemberDetailsModal();
          showSuccessModal('Success', 'Member deleted successfully!');
        })
        .catch(error => console.error('Error:', error));
      }
    }

    function deleteHOA(hoaId) {
      if (confirm('Are you sure you want to delete this HOA? This will also delete all associated officials and members.')) {
        const formData = new FormData();
        formData.append('action', 'delete_hoa');
        formData.append('hoa_id', hoaId);
        
        fetch('', {
          method: 'POST',
          body: formData
        })
        .then(response => {
          showSuccessModal('Success', 'HOA deleted successfully!');
        })
        .catch(error => console.error('Error:', error));
      }
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
      const modals = ['addHoaModal', 'addOfficialModal', 'addMemberModal', 'officialDetailsModal', 'memberDetailsModal', 'successModal'];
      
      modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && event.target === modal) {
          if (modalId === 'addHoaModal') closeAddHoaModal();
          if (modalId === 'addOfficialModal') closeAddOfficialModal();
          if (modalId === 'addMemberModal') closeAddMemberModal();
          if (modalId === 'officialDetailsModal') closeOfficialDetailsModal();
          if (modalId === 'memberDetailsModal') closeMemberDetailsModal();
          if (modalId === 'successModal') closeSuccessModal();
        }
      });
    }
  </script>
</body>
</html>