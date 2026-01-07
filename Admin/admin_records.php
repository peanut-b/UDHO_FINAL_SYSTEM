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

// Handle record deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $sql = "DELETE FROM routing_slips WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting record: ' . $conn->error]);
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

// Handle record update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    $id = intval($_POST['id']);
    $control_no = $conn->real_escape_string($_POST['control_no']);
    $date_time = $conn->real_escape_string($_POST['date_time']);
    $document_type = $conn->real_escape_string($_POST['document_type']);
    $direction = $conn->real_escape_string($_POST['direction']);
    $priority = $conn->real_escape_string($_POST['priority']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $sender = $conn->real_escape_string($_POST['sender']);
    $contact_no = $conn->real_escape_string($_POST['contact_no']);

    $sql = "UPDATE routing_slips SET 
            control_no = ?,
            date_time = ?,
            document_type = ?,
            direction = ?,
            priority = ?,
            subject = ?,
            sender = ?,
            contact_no = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssi", 
        $control_no,
        $date_time,
        $document_type,
        $direction,
        $priority,
        $subject,
        $sender,
        $contact_no,
        $id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Record updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating record: ' . $conn->error]);
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

// Fetch records with search and filter
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$direction_filter = isset($_GET['direction']) ? $conn->real_escape_string($_GET['direction']) : '';

$sql = "SELECT * FROM routing_slips WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (control_no LIKE ? OR document_type LIKE ? OR sender LIKE ? OR subject LIKE ?)";
    $search_term = "%$search%";
    $params = array_fill(0, 4, $search_term);
    $types = str_repeat('s', count($params));
}

if (!empty($direction_filter)) {
    $sql .= " AND direction = ?";
    $params[] = $direction_filter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Records</title>
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
      width: 16rem; /* 64 * 0.25rem = 16rem */
      z-index: 40;
      overflow-y: auto;
    }
    
    .main-content-scrollable {
      flex: 1;
      margin-left: 16rem; /* Match sidebar width */
      height: 100vh;
      overflow-y: auto;
      background-color: #f7fafc;
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
    .document-section {
      margin-bottom: 2rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    .document-header {
      background-color: #4c51bf;
      color: white;
      padding: 0.75rem 1rem;
      font-weight: bold;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .document-count {
      background-color: rgba(255, 255, 255, 0.2);
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.875rem;
    }
    .document-table {
      width: 100%;
      border-collapse: collapse;
    }
    .document-table th {
      background-color: #edf2f7;
      padding: 0.75rem;
      text-align: left;
      position: sticky;
      top: 0;
    }
    .document-table td {
      padding: 0.75rem;
      border-top: 1px solid #e2e8f0;
    }
    .document-table tr:hover {
      background-color: #f8fafc;
    }
    .priority-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .priority-high {
      background-color: #fee2e2;
      color: #b91c1c;
    }
    .priority-medium {
      background-color: #fef3c7;
      color: #92400e;
    }
    .priority-low {
      background-color: #d1fae5;
      color: #065f46;
    }
    .sidebar-link {
      transition: all 0.2s ease;
    }
    .sidebar-link:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    .active-link {
      background-color: rgba(255, 255, 255, 0.2);
    }
    .action-btn {
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      margin: 0 0.1rem;
    }
    .view-btn {
      background-color: #3b82f6;
      color: white;
    }
    .edit-btn {
      background-color: #10b981;
      color: white;
    }
    .delete-btn {
      background-color: #ef4444;
      color: white;
    }
    .action-btn:hover {
      opacity: 0.8;
    }
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background-color: white;
      border-radius: 0.5rem;
      width: 90%;
      max-width: 800px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .modal-header {
      padding: 1rem;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-body {
      padding: 1rem;
    }
    .modal-footer {
      padding: 1rem;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
    }
    .close-btn {
      cursor: pointer;
      font-size: 1.5rem;
      color: #6b7280;
    }
    .close-btn:hover {
      color: #4b5563;
    }
    .detail-row {
      display: grid;
      grid-template-columns: 150px 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .detail-label {
      font-weight: 600;
      color: #4a5568;
    }
    .route-item {
      background-color: #f8fafc;
      padding: 0.75rem;
      border-radius: 0.375rem;
      margin-bottom: 0.5rem;
    }
    .search-container {
      background-color: white;
      padding: 1rem;
      border-radius: 0.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      margin-bottom: 1rem;
    }
    .search-input-container {
      position: relative;
      flex: 1;
    }
    .search-input {
      width: 100%;
      padding: 0.5rem 1rem 0.5rem 2.5rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .search-input:focus {
      border-color: #4c51bf;
      box-shadow: 0 0 0 2px rgba(76, 81, 191, 0.2);
    }
    .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
    }
    .filter-select {
      padding: 0.5rem 1rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      background-color: white;
      outline: none;
    }
    .filter-select:focus {
      border-color: #4c51bf;
    }
    .search-button {
      background-color: #4c51bf;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      transition: background-color 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
    }
    .search-button:hover {
      background-color: #434190;
    }
    .reset-button {
      background-color: #edf2f7;
      color: #4a5568;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      transition: background-color 0.2s;
    }
    .reset-button:hover {
      background-color: #e2e8f0;
    }
    .filter-button {
      background-color: #edf2f7;
      color: #4a5568;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      transition: all 0.2s;
      border: 1px solid transparent;
    }
    .filter-button:hover {
      background-color: #e2e8f0;
    }
    .filter-button.active {
      background-color: #4c51bf;
      color: white;
      border-color: #4c51bf;
    }
    .print-button {
      background-color: #6b7280;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      transition: background-color 0.2s;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .print-button:hover {
      background-color: #4b5563;
    }
    /* Print styles */
    @media print {
      body * {
        visibility: hidden;
      }
      #printSection, #printSection * {
        visibility: visible;
      }
      #printSection {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }
      .no-print {
        display: none !important;
      }
    }
    /* Responsive adjustments */
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
      <nav class="mt-6 flex-1">
        <ul>
          <li>
            <a href="admin_dashboard.php" class="sidebar-link flex items-center py-3 px-4">
              <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
            </a>
          </li>
          <li>
            <a href="admin_records.php" class="sidebar-link flex items-center py-3 px-4 active-link">
              <i class="fas fa-file-alt mr-3"></i> Records
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
      <div class="p-4 md:p-10">
          
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
          </div>
          <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
          </div>
          <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="search-container">
          <form method="GET" class="flex flex-col md:flex-row gap-3">
            <div class="flex flex-1 gap-2">
              <div class="search-input-container">
                <i class="fas fa-search search-icon"></i>
                <input 
                  type="text" 
                  name="search" 
                  placeholder="Search control no, type, sender, subject..." 
                  class="search-input"
                  value="<?php echo htmlspecialchars($search); ?>"
                >
              </div>
            </div>
            <div class="flex gap-2">
              <button type="submit" class="search-button">
                <i class="fas fa-search"></i>
              </button>
              <a href="admin_records.php" class="reset-button">
                <i class="fas fa-sync-alt mr-2"></i> Reset
              </a>
            </div>
          </form>
          
          <!-- Direction Filter Buttons -->
          <div class="mt-3 flex flex-wrap gap-2">
            <button 
              type="button" 
              class="filter-button <?php echo empty($direction_filter) ? 'active' : ''; ?>" 
              onclick="window.location.href='admin_records.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>'"
            >
              <i class="fas fa-list mr-2"></i> All Directions
            </button>
            <button 
              type="button" 
              class="filter-button <?php echo $direction_filter === 'Incoming' ? 'active' : ''; ?>" 
              onclick="filterByDirection('Incoming')"
            >
              <i class="fas fa-sign-in-alt mr-2"></i> Incoming
            </button>
            <button 
              type="button" 
              class="filter-button <?php echo $direction_filter === 'Outgoing' ? 'active' : ''; ?>" 
              onclick="filterByDirection('Outgoing')"
            >
              <i class="fas fa-sign-out-alt mr-2"></i> Outgoing
            </button>
            <button 
              type="button" 
              class="filter-button <?php echo $direction_filter === 'Internal' ? 'active' : ''; ?>" 
              onclick="filterByDirection('Internal')"
            >
              <i class="fas fa-exchange-alt mr-2"></i> Internal
            </button>
          </div>
        </div>
        
        <!-- Document Tracking Log -->
        <div class="document-section">
          <div class="document-header">
            <div>
              <i class="fas fa-file-alt mr-2"></i> Document Tracking Log
              <span class="document-count"><?php echo count($records); ?> records</span>
            </div>
            
          </div>
          <div class="table-container overflow-x-auto">
            <table class="document-table">
              <thead>
                <tr>
                  <th>Control No.</th>
                  <th>Date/Time</th>
                  <th>Document Type</th>
                  <th>Direction</th>
                  <th>Priority</th>
                  <th>Subject</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($records)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4">No records found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($records as $record): ?>
                    <tr data-id="<?php echo $record['id']; ?>">
                      <td><?php echo htmlspecialchars($record['control_no']); ?></td>
                      <td><?php echo date('Y-m-d H:i', strtotime($record['date_time'])); ?></td>
                      <td><?php echo htmlspecialchars($record['document_type']); ?></td>
                      <td><?php echo htmlspecialchars($record['direction']); ?></td>
                      <td>
                        <?php 
                        $priorities = explode(", ", $record['priority']);
                        foreach ($priorities as $p) {
                            if (strpos($p, '3 days') !== false) {
                                echo '<span class="priority-badge priority-high">3 days</span> ';
                            } elseif (strpos($p, '7 days') !== false) {
                                echo '<span class="priority-badge priority-medium">7 days</span> ';
                            } elseif (strpos($p, '15 days') !== false || strpos($p, '20 days') !== false) {
                                echo '<span class="priority-badge priority-low">' . htmlspecialchars($p) . '</span> ';
                            }
                        }
                        ?>
                      </td>
                      <td><?php echo htmlspecialchars($record['subject']); ?></td>
                      <td>
                        <?php 
                        $created_time = strtotime($record['created_at']);
                        $now = time();
                        $diff = $now - $created_time;
                        $days = floor($diff / (60 * 60 * 24));
                        
                        if ($days < 3) {
                            echo '<span class="text-yellow-600">In Progress</span>';
                        } elseif ($days < 7) {
                            echo '<span class="text-blue-600">Pending</span>';
                        } else {
                            echo '<span class="text-green-600">Completed</span>';
                        }
                        ?>
                      </td>
                      <td class="flex space-x-1">
                        <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($record)); ?>)" class="action-btn view-btn">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($record)); ?>)" class="action-btn edit-btn">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="openDeleteModal(<?php echo $record['id']; ?>)" class="action-btn delete-btn">
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Print Section (Hidden) -->
  <div id="printSection" style="display: none;">
    <div style="text-align: center; margin-bottom: 20px;">
      <h1 style="font-size: 24px; font-weight: bold;">Document Tracking Log</h1>
      <p style="font-size: 14px; color: #666;">Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
      <p style="font-size: 14px; color: #666;">
        Filter: <?php echo !empty($direction_filter) ? $direction_filter : 'All Directions'; ?>
        <?php echo !empty($search) ? ' | Search: ' . htmlspecialchars($search) : ''; ?>
      </p>
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
      <thead>
        <tr style="background-color: #f3f4f6;">
          <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Control No.</th>
          <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Date/Time</th>
          <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Document Type</th>
          <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Direction</th>
          <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Priority</th>
          <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Subject</th>
          <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($records)): ?>
          <tr>
            <td colspan="7" style="border: 1px solid #d1d5db; padding: 12px; text-align: center;">No records found</td>
          </tr>
        <?php else: ?>
          <?php foreach ($records as $record): ?>
            <tr>
              <td style="border: 1px solid #d1d5db; padding: 8px;"><?php echo htmlspecialchars($record['control_no']); ?></td>
              <td style="border: 1px solid #d1d5db; padding: 8px;"><?php echo date('Y-m-d H:i', strtotime($record['date_time'])); ?></td>
              <td style="border: 1px solid #d1d5db; padding: 8px;"><?php echo htmlspecialchars($record['document_type']); ?></td>
              <td style="border: 1px solid #d1d5db; padding: 8px;"><?php echo htmlspecialchars($record['direction']); ?></td>
              <td style="border: 1px solid #d1d5db; padding: 8px;"><?php echo htmlspecialchars($record['priority']); ?></td>
              <td style="border: 1px solid #d1d5db; padding: 8px;"><?php echo htmlspecialchars($record['subject']); ?></td>
              <td style="border: 1px solid #d1d5db; padding: 8px;">
                <?php 
                $created_time = strtotime($record['created_at']);
                $now = time();
                $diff = $now - $created_time;
                $days = floor($diff / (60 * 60 * 24));
                
                if ($days < 3) {
                    echo 'In Progress';
                } elseif ($days < 7) {
                    echo 'Pending';
                } else {
                    echo 'Completed';
                }
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- View Modal -->
  <div id="viewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="text-lg font-medium">Document Details</h3>
        <span class="close-btn" onclick="closeModal('viewModal')">&times;</span>
      </div>
      <div class="modal-body">
        <div class="space-y-4">
          <div class="detail-row">
            <span class="detail-label">Control No:</span>
            <span id="viewControlNo"></span>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Date/Time:</span>
            <span id="viewDateTime"></span>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Document Type:</span>
            <span id="viewDocumentType"></span>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Direction:</span>
            <span id="viewDirection"></span>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Priority:</span>
            <div id="viewPriority" class="flex flex-wrap gap-1"></div>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Sender:</span>
            <span id="viewSender"></span>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Contact No:</span>
            <span id="viewContactNo"></span>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Subject:</span>
            <span id="viewSubject"></span>
          </div>
          
          <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span id="viewStatus"></span>
          </div>
          
          <div id="viewRoutingData" class="hidden">
            <h4 class="font-medium text-gray-600 mb-2">Routing History:</h4>
            <div id="routingHistory" class="space-y-2"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button onclick="closeModal('viewModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="text-lg font-medium">Edit Record</h3>
        <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
      </div>
      <div class="modal-body">
        <form id="editForm">
          <input type="hidden" name="id" id="editId">
          <input type="hidden" name="update_record" value="1">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Control Number</label>
              <input type="text" name="control_no" id="editControlNo" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Date/Time</label>
              <input type="datetime-local" name="date_time" id="editDateTime" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Document Type</label>
              <select name="document_type" id="editDocumentType" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="Memo">Memo</option>
                <option value="Letter">Letter</option>
                <option value="Report">Report</option>
                <option value="Others">Others</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Direction</label>
              <select name="direction" id="editDirection" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="Incoming">Incoming</option>
                <option value="Outgoing">Outgoing</option>
                <option value="Internal">Internal</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Priority</label>
              <select name="priority" id="editPriority" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="3 days">High (3 days)</option>
                <option value="7 days">Medium (7 days)</option>
                <option value="15 days">Low (15 days)</option>
                <option value="20 days">Low (20 days)</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Sender</label>
              <input type="text" name="sender" id="editSender" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Contact No</label>
              <input type="text" name="contact_no" id="editContactNo" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4 md:col-span-2">
              <label class="block text-gray-700 font-medium mb-2">Subject</label>
              <textarea name="subject" id="editSubject" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
          Cancel
        </button>
        <button onclick="submitEditForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
          Save Changes
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="text-lg font-medium">Confirm Deletion</h3>
        <span class="close-btn" onclick="closeModal('deleteModal')">&times;</span>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this record? This action cannot be undone.</p>
        <input type="hidden" id="deleteId">
      </div>
      <div class="modal-footer">
        <button onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
          Cancel
        </button>
        <button onclick="confirmDelete()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200">
          Delete
        </button>
      </div>
    </div>
  </div>

  <!-- Message Modal -->
  <div id="messageModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
      <div class="modal-header">
        <h3 class="text-lg font-medium" id="messageTitle">Message</h3>
        <span class="close-btn" onclick="closeModal('messageModal')">&times;</span>
      </div>
      <div class="modal-body">
        <p id="messageText"></p>
      </div>
      <div class="modal-footer">
        <button onclick="closeModal('messageModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
          OK
        </button>
      </div>
    </div>
  </div>

  <script>
    // Filter functions
    function filterByDirection(direction) {
      const url = new URL(window.location.href);
      url.searchParams.set('direction', direction);
      window.location.href = url.toString();
    }

    // Print function
    function printRecords() {
      const printContents = document.getElementById('printSection').innerHTML;
      const originalContents = document.body.innerHTML;
      
      document.body.innerHTML = printContents;
      window.print();
      document.body.innerHTML = originalContents;
      location.reload(); // Reload to restore functionality
    }

    // Modal functions
    function openViewModal(record) {
      // Convert the record if it's coming from JSON
      if (typeof record === 'string') {
        record = JSON.parse(record);
      }

      // Set basic fields
      document.getElementById('viewControlNo').textContent = record.control_no || 'N/A';
      document.getElementById('viewDateTime').textContent = formatDateTime(record.date_time);
      document.getElementById('viewDocumentType').textContent = record.document_type || 'N/A';
      document.getElementById('viewDirection').textContent = record.direction || 'N/A';
      document.getElementById('viewSender').textContent = record.sender || 'N/A';
      document.getElementById('viewContactNo').textContent = record.contact_no || 'N/A';
      document.getElementById('viewSubject').textContent = record.subject || 'N/A';

      // Set priority badges
      const priorityContainer = document.getElementById('viewPriority');
      priorityContainer.innerHTML = '';
      if (record.priority) {
        const priorities = record.priority.split(', ');
        priorities.forEach(p => {
          if (p) {
            const badge = document.createElement('span');
            badge.className = getPriorityBadgeClass(p);
            badge.textContent = p;
            badge.classList.add('priority-badge');
            priorityContainer.appendChild(badge);
          }
        });
      }

      // Set status
      const statusElement = document.getElementById('viewStatus');
      if (record.created_at) {
        const createdTime = new Date(record.created_at).getTime();
        const now = new Date().getTime();
        const diffDays = Math.floor((now - createdTime) / (1000 * 60 * 60 * 24));
        
        if (diffDays < 3) {
          statusElement.textContent = 'In Progress';
          statusElement.className = 'text-yellow-600';
        } else if (diffDays < 7) {
          statusElement.textContent = 'Pending';
          statusElement.className = 'text-blue-600';
        } else {
          statusElement.textContent = 'Completed';
          statusElement.className = 'text-green-600';
        }
      } else {
        statusElement.textContent = 'N/A';
        statusElement.className = '';
      }

      // Set routing data if available
      const routingContainer = document.getElementById('viewRoutingData');
      const historyContainer = document.getElementById('routingHistory');
      historyContainer.innerHTML = '';
      
      if (record.routing_data) {
        try {
          const routingData = JSON.parse(record.routing_data);
          if (Array.isArray(routingData) && routingData.length > 0) {
            routingContainer.classList.remove('hidden');
            routingData.forEach(route => {
              if (route.date) {
                const routeDiv = document.createElement('div');
                routeDiv.className = 'route-item';
                routeDiv.innerHTML = `
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div><strong>Date:</strong> ${route.date || 'N/A'}</div>
                    <div><strong>From:</strong> ${route.from || 'N/A'}</div>
                    <div><strong>To:</strong> ${route.to || 'N/A'}</div>
                  </div>
                  ${route.actions ? `<div class="mt-1"><strong>Actions:</strong> ${route.actions}</div>` : ''}
                  ${route.action_taken ? `<div class="mt-1"><strong>Action Taken:</strong> ${route.action_taken}</div>` : ''}
                `;
                historyContainer.appendChild(routeDiv);
              }
            });
          } else {
            routingContainer.classList.add('hidden');
          }
        } catch (e) {
          routingContainer.classList.add('hidden');
        }
      } else {
        routingContainer.classList.add('hidden');
      }

      // Show the modal
      document.getElementById('viewModal').style.display = 'flex';
    }

    function openEditModal(record) {
      // Convert the record if it's coming from JSON
      if (typeof record === 'string') {
        record = JSON.parse(record);
      }

      // Set all form fields with the record data
      document.getElementById('editId').value = record.id;
      document.getElementById('editControlNo').value = record.control_no || '';
      document.getElementById('editDateTime').value = record.date_time ? record.date_time.substring(0, 16) : '';
      document.getElementById('editDocumentType').value = record.document_type || '';
      document.getElementById('editDirection').value = record.direction || '';
      document.getElementById('editPriority').value = record.priority || '';
      document.getElementById('editSender').value = record.sender || '';
      document.getElementById('editContactNo').value = record.contact_no || '';
      document.getElementById('editSubject').value = record.subject || '';
      
      // Show the modal
      document.getElementById('editModal').style.display = 'flex';
    }

    function openDeleteModal(id) {
      document.getElementById('deleteId').value = id;
      document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    function showMessage(title, message) {
      document.getElementById('messageTitle').textContent = title;
      document.getElementById('messageText').textContent = message;
      document.getElementById('messageModal').style.display = 'flex';
    }

    // Helper functions
    function formatDateTime(dateTimeString) {
      if (!dateTimeString) return 'N/A';
      const date = new Date(dateTimeString);
      return date.toLocaleString();
    }

    function getPriorityBadgeClass(priority) {
      if (priority.includes('3 days')) return 'priority-high';
      if (priority.includes('7 days')) return 'priority-medium';
      return 'priority-low';
    }

    // Form submission
    function submitEditForm() {
      const form = document.getElementById('editForm');
      const formData = new FormData(form);
      
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeModal('editModal');
          showMessage('Success', data.message);
          // Reload the page to see changes
          setTimeout(() => location.reload(), 1000);
        } else {
          showMessage('Error', data.message);
        }
      })
      .catch(error => {
        showMessage('Error', 'An error occurred while updating the record.');
      });
    }

    function confirmDelete() {
      const id = document.getElementById('deleteId').value;
      const formData = new FormData();
      formData.append('delete_id', id);
      
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeModal('deleteModal');
          showMessage('Success', data.message);
          // Reload the page to see changes
          setTimeout(() => location.reload(), 1000);
        } else {
          showMessage('Error', data.message);
        }
      })
      .catch(error => {
        showMessage('Error', 'An error occurred while deleting the record.');
      });
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
      const modals = document.getElementsByClassName('modal');
      for (let i = 0; i < modals.length; i++) {
        if (event.target == modals[i]) {
          modals[i].style.display = 'none';
        }
      }
    }

    // Add keyboard support for closing modals
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        const modals = document.getElementsByClassName('modal');
        for (let i = 0; i < modals.length; i++) {
          if (modals[i].style.display === 'flex') {
            modals[i].style.display = 'none';
          }
        }
      }
    });
  </script>
</body>
</html>