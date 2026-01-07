<?php
session_start();

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

// Create uploads directory if it doesn't exist
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Operation/uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Add or update record
        if ($action === 'save') {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $date_issued = $_POST['date_issued'];
            $subject = $_POST['subject'];
            $case_file = $_POST['case_file'];
            $branch = $_POST['branch'];
            $affected_barangay = $_POST['affected_barangay'];
            $household_affected = intval($_POST['household_affected']);
            $status = $_POST['status'];
            $activities = $_POST['activities'];
            
            // Initialize documents array with existing documents if editing
            $existing_documents = [];
            if ($id > 0) {
                // Get existing documents from database
                $stmt = $conn->prepare("SELECT documents FROM pdc_records WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->bind_result($existing_documents_json);
                $stmt->fetch();
                $stmt->close();
                
                if (!empty($existing_documents_json)) {
                    $existing_documents = json_decode($existing_documents_json, true);
                }
            }
            
            // Handle file deletions
            $documents = $existing_documents;
            if (!empty($_POST['files_to_delete'])) {
                $files_to_delete = json_decode($_POST['files_to_delete'], true);
                foreach ($files_to_delete as $file_path) {
                    // Update file path to use /Operation/uploads/
                    $full_file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
                    if (file_exists($full_file_path)) {
                        unlink($full_file_path);
                    }
                    
                    // Remove from documents array if it exists
                    $key = array_search($file_path, $documents);
                    if ($key !== false) {
                        unset($documents[$key]);
                    }
                }
                // Reindex array
                $documents = array_values($documents);
            }
            
            // Handle uploaded files
            if (!empty($_FILES['documents']['name'][0])) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Admin executive/Operation/uploads/';
                
                foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = uniqid() . '_' . basename($_FILES['documents']['name'][$key]);
                        $file_path = $upload_dir . $file_name;
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        $file_type = mime_content_type($tmp_name);
                        
                        if (in_array($file_type, $allowed_types)) {
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $documents[] = '/Admin executive/Operation/uploads/' . $file_name;
                            }
                        }
                    }
                }
            }
            
            // Handle camera photos
            if (!empty($_POST['camera_photos'])) {
                $camera_photos = json_decode($_POST['camera_photos'], true);
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Admin executive/Operation/uploads/';
                
                foreach ($camera_photos as $photo_data) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $photo_data, $type)) {
                        $data = substr($photo_data, strpos($photo_data, ',') + 1);
                        $type = strtolower($type[1]); // jpg, png, gif
                        
                        if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                            continue; // Invalid image type
                        }
                        
                        $data = base64_decode($data);
                        
                        if ($data !== false) {
                            $file_name = uniqid() . '_camera.' . $type;
                            $file_path = $upload_dir . $file_name;
                            
                            if (file_put_contents($file_path, $data)) {
                                $documents[] = '/Admin executive/Operation/uploads/' . $file_name;
                            }
                        }
                    }
                }
            }
            
            $documents_json = json_encode($documents);
            
            if ($id > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE pdc_records SET date_issued=?, subject=?, case_file=?, branch=?, affected_barangay=?, household_affected=?, status=?, activities=?, documents=? WHERE id=?");
                $stmt->bind_param("sssssisssi", $date_issued, $subject, $case_file, $branch, $affected_barangay, $household_affected, $status, $activities, $documents_json, $id);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO pdc_records (date_issued, subject, case_file, branch, affected_barangay, household_affected, status, activities, documents) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssisss", $date_issued, $subject, $case_file, $branch, $affected_barangay, $household_affected, $status, $activities, $documents_json);
            }
            
            if ($stmt->execute()) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $error = "Error saving record: " . $stmt->error;
            }
            
            $stmt->close();
        }
        
        // In the deletion section (around line 130-160), modify to:
if ($action === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // First get the record to archive
    $stmt = $conn->prepare("SELECT * FROM pdc_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();
    
    if ($record) {
        // Archive the record to deleted_pdc_records table
        $archive_stmt = $conn->prepare("INSERT INTO deleted_pdc_records 
            (original_id, date_issued, subject, case_file, branch, affected_barangay, 
             household_affected, status, activities, documents, deleted_by, deleted_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $deleted_by = $_SESSION['username'] ?? 'Unknown';
        $archive_stmt->bind_param("isssssissss", 
            $record['id'],
            $record['date_issued'],
            $record['subject'],
            $record['case_file'],
            $record['branch'],
            $record['affected_barangay'],
            $record['household_affected'],
            $record['status'],
            $record['activities'],
            $record['documents'],
            $deleted_by
        );
        
        $archive_stmt->execute();
        $archive_stmt->close();
    }
    
    // Get the document paths to delete the files (original code)
    $stmt = $conn->prepare("SELECT documents FROM pdc_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($documents_json);
    $stmt->fetch();
    $stmt->close();
    
    // Delete the files
    if (!empty($documents_json)) {
        $documents = json_decode($documents_json, true);
        foreach ($documents as $file_path) {
            $full_file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
            if (file_exists($full_file_path)) {
                unlink($full_file_path);
            }
        }
    }
    
    // Delete the record
    $stmt = $conn->prepare("DELETE FROM pdc_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&archived=1");
        exit();
    } else {
        $error = "Error deleting record: " . $stmt->error;
    }
    
    $stmt->close();
}
    }
}

// Get filter values from GET parameters
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;

// Build SQL query with optional filters
$sql = "SELECT * FROM pdc_records";
$conditions = [];

if ($filter_month > 0 && $filter_month <= 12) {
    $conditions[] = "MONTH(date_issued) = $filter_month";
}

if ($filter_year > 0) {
    $conditions[] = "YEAR(date_issued) = $filter_year";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY date_issued DESC";

// Fetch records
$records = [];
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

// Get unique years from database for filter dropdown
$years_result = $conn->query("SELECT DISTINCT YEAR(date_issued) as year FROM pdc_records WHERE date_issued IS NOT NULL ORDER BY year DESC");
$unique_years = [];
if ($years_result && $years_result->num_rows > 0) {
    while ($row = $years_result->fetch_assoc()) {
        $unique_years[] = $row['year'];
    }
}

// If no years found, use current year as default
if (empty($unique_years)) {
    $unique_years[] = date('Y');
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Operation Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <style>
    textarea#subject {
      min-height: 80px;
      max-height: 200px;
      resize: vertical;
    }
    
    /* Custom scrollbar for modal */
    #modalContent::-webkit-scrollbar {
      width: 8px;
    }
    #modalContent::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    #modalContent::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 4px;
    }
    #modalContent::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
    
    /* Compact table styling */
    .compact-table th, .compact-table td {
      padding: 0.25rem 0.5rem;
      font-size: 0.875rem;
    }
    
    .action-buttons {
      white-space: nowrap;
    }
    
    .action-buttons button {
      padding: 0.25rem 0.5rem;
      margin: 0 0.125rem;
    }
    
    /* Landscape modal styling */
    .landscape-modal {
      width: 90%;
      max-width: 1000px;
    }
    
    /* Photo preview with delete button */
    .photo-container {
      position: relative;
      display: inline-block;
      margin: 0.25rem;
    }
    
    .delete-photo {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #ef4444;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      cursor: pointer;
      border: 1px solid white;
    }
    
    /* Confirmation modal styles */
    .confirmation-modal {
      max-width: 400px;
      width: 90%;
    }
    
    .confirmation-buttons {
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
      margin-top: 1rem;
    }
    
    /* Form and camera side by side */
    .form-camera-container {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .form-section {
      flex: 1;
      min-width: 300px;
    }
    
    .camera-section {
      flex: 1;
      min-width: 300px;
      max-width: 400px;
    }
    
    .camera-box {
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      padding: 0.75rem;
      background: #f9fafb;
    }
    
    /* Responsive document gallery */
    .document-gallery {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 0.75rem;
      margin-top: 0.5rem;
    }
    
    .document-item {
      position: relative;
      border-radius: 0.5rem;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      background: white;
      height: 180px;
      display: flex;
      flex-direction: column;
    }
    
    .document-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .document-preview {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: #f8f9fa;
    }
    
    .document-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }
    
    .document-item:hover .document-preview img {
      transform: scale(1.05);
    }
    
    .document-info {
      padding: 0.5rem;
      background: white;
      border-top: 1px solid #e9ecef;
    }
    
    .document-title {
      font-size: 0.75rem;
      font-weight: 500;
      color: #333;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: center;
    }
    
    .document-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .document-item:hover .document-overlay {
      opacity: 1;
    }
    
    .document-actions {
      display: flex;
      gap: 0.5rem;
    }
    
    .document-btn {
      padding: 0.4rem 0.8rem;
      background: white;
      color: #333;
      border: none;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .document-btn:hover {
      background: #f8f9fa;
      transform: scale(1.05);
    }
    
    /* Fullscreen image viewer */
    .image-viewer {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.95);
      z-index: 9999;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 1rem;
    }
    
    .image-viewer.hidden {
      display: none;
    }
    
    .image-container {
      max-width: 90%;
      max-height: 80vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .image-container img {
      max-width: 100%;
      max-height: 80vh;
      object-fit: contain;
      border-radius: 0.5rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    }
    
    .image-controls {
      position: absolute;
      top: 1rem;
      right: 1rem;
      display: flex;
      gap: 0.5rem;
    }
    
    .image-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: none;
      width: 3rem;
      height: 3rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      cursor: pointer;
      transition: all 0.3s ease;
      backdrop-filter: blur(5px);
    }
    
    .image-nav:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-50%) scale(1.1);
    }
    
    .image-nav.prev {
      left: 1rem;
    }
    
    .image-nav.next {
      right: 1rem;
    }
    
    .image-counter {
      position: absolute;
      bottom: 2rem;
      color: white;
      background: rgba(0, 0, 0, 0.5);
      padding: 0.5rem 1rem;
      border-radius: 2rem;
      font-size: 0.875rem;
    }
    
    .close-viewer {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: none;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      cursor: pointer;
      transition: all 0.3s ease;
      backdrop-filter: blur(5px);
    }
    
    .close-viewer:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: scale(1.1);
    }
    
    /* Responsive adjustments */
    @media (max-width: 640px) {
      .document-gallery {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.5rem;
      }
      
      .document-item {
        height: 150px;
      }
      
      .image-nav {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1.25rem;
      }
      
      .image-nav.prev {
        left: 0.5rem;
      }
      
      .image-nav.next {
        right: 0.5rem;
      }
    }
    
    @media (max-width: 480px) {
      .document-gallery {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      }
      
      .document-item {
        height: 130px;
      }
    }
    
    /* Compact Filter Styles */
    .filter-container {
      background: #f8f9fa;
      border-radius: 0.375rem;
      padding: 0.5rem;
      margin-bottom: 0.75rem;
      border: 1px solid #e5e7eb;
      font-size: 0.875rem;
    }
    
    .filter-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    
    .filter-group {
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .filter-label {
      font-weight: 500;
      color: #4b5563;
      white-space: nowrap;
    }
    
    .filter-select {
      padding: 0.375rem 0.5rem;
      border: 1px solid #d1d5db;
      border-radius: 0.25rem;
      font-size: 0.875rem;
      background: white;
      color: #374151;
      min-width: 120px;
      transition: all 0.15s ease;
    }
    
    .filter-select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }
    
    .filter-buttons {
      display: flex;
      gap: 0.25rem;
      margin-left: auto;
    }
    
    .filter-btn {
      padding: 0.375rem 0.75rem;
      border-radius: 0.25rem;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.15s ease;
      display: flex;
      align-items: center;
      gap: 0.25rem;
      white-space: nowrap;
    }
    
    .filter-btn.primary {
      background: #3b82f6;
      color: white;
      border: 1px solid #2563eb;
    }
    
    .filter-btn.primary:hover {
      background: #2563eb;
    }
    
    .filter-btn.secondary {
      background: #6b7280;
      color: white;
      border: 1px solid #4b5563;
    }
    
    .filter-btn.secondary:hover {
      background: #4b5563;
    }
    
    .filter-badge {
      background: #e0f2fe;
      color: #0369a1;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
      white-space: nowrap;
    }
    
    /* Mobile adjustments for filter */
    @media (max-width: 768px) {
      .filter-row {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
      }
      
      .filter-group {
        width: 100%;
      }
      
      .filter-select {
        flex: 1;
      }
      
      .filter-buttons {
        width: 100%;
        justify-content: flex-end;
        margin-left: 0;
      }
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
  <div class="flex-1 p-4 md:p-6">
    <header class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
      <h1 class="text-xl font-bold text-gray-800">Operation Panel</h1>
      <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
        <input type="text" placeholder="Search" class="w-full md:w-64 px-3 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" />
        <div class="flex items-center gap-2">
          <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-6">
          <span class="font-medium text-gray-700 text-sm">Urban Development and Housing Office</span>
        </div>
      </div>
    </header>

    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
      <span class="block sm:inline"><?php echo $error; ?></span>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
      <span class="block sm:inline">Operation completed successfully.</span>
    </div>
    <?php endif; ?>

    <!-- Compact Filter Section -->
    <div class="filter-container">
      <form method="GET" action="" id="filterForm">
        <div class="filter-row">
          <div class="filter-group">
            <label class="filter-label">Month:</label>
            <select name="month" id="monthFilter" class="filter-select">
              <option value="0">All Months</option>
              <option value="1" <?php echo $filter_month == 1 ? 'selected' : ''; ?>>January</option>
              <option value="2" <?php echo $filter_month == 2 ? 'selected' : ''; ?>>February</option>
              <option value="3" <?php echo $filter_month == 3 ? 'selected' : ''; ?>>March</option>
              <option value="4" <?php echo $filter_month == 4 ? 'selected' : ''; ?>>April</option>
              <option value="5" <?php echo $filter_month == 5 ? 'selected' : ''; ?>>May</option>
              <option value="6" <?php echo $filter_month == 6 ? 'selected' : ''; ?>>June</option>
              <option value="7" <?php echo $filter_month == 7 ? 'selected' : ''; ?>>July</option>
              <option value="8" <?php echo $filter_month == 8 ? 'selected' : ''; ?>>August</option>
              <option value="9" <?php echo $filter_month == 9 ? 'selected' : ''; ?>>September</option>
              <option value="10" <?php echo $filter_month == 10 ? 'selected' : ''; ?>>October</option>
              <option value="11" <?php echo $filter_month == 11 ? 'selected' : ''; ?>>November</option>
              <option value="12" <?php echo $filter_month == 12 ? 'selected' : ''; ?>>December</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Year:</label>
            <select name="year" id="yearFilter" class="filter-select">
              <option value="0">All Years</option>
              <?php foreach ($unique_years as $year): ?>
                <option value="<?php echo $year; ?>" <?php echo $filter_year == $year ? 'selected' : ''; ?>>
                  <?php echo $year; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="filter-buttons">
            <button type="submit" class="filter-btn primary">
              <i class="fas fa-filter"></i> Filter
            </button>
            <button type="button" onclick="resetFilter()" class="filter-btn secondary">
              <i class="fas fa-times"></i> Clear
            </button>
            
            <?php if ($filter_month > 0 || $filter_year > 0): ?>
            <div class="filter-badge">
              <i class="fas fa-filter"></i>
              <span>
                <?php 
                if ($filter_month > 0) {
                  $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                  echo $months[$filter_month - 1];
                }
                if ($filter_month > 0 && $filter_year > 0) echo ' ';
                if ($filter_year > 0) echo $filter_year;
                echo ' (' . count($records) . ')';
                ?>
              </span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>

    <div class="bg-white p-3 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-3">
        <h2 class="text-lg border-b-2 border-purple-600 pb-1">PDC Database</h2>
        <div class="flex gap-2">
          <button onclick="exportToExcel()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md transition flex items-center text-sm">
            <i class="fas fa-file-excel mr-1"></i> Export
          </button>
          <button onclick="openModal()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md transition flex items-center text-sm">
            <i class="fas fa-plus mr-1"></i> Add Data
          </button>
        </div>
      </div>

      <?php if (empty($records)): ?>
      <div class="text-center py-6 text-gray-500">
        <i class="fas fa-database text-3xl mb-2"></i>
        <p class="text-sm">No records found<?php echo ($filter_month > 0 || $filter_year > 0) ? ' for the selected filter' : ''; ?>.</p>
        <?php if ($filter_month > 0 || $filter_year > 0): ?>
        <p class="text-xs mt-1">
          <button onclick="resetFilter()" class="text-blue-600 hover:text-blue-800 underline">
            Clear filters to view all records
          </button>
        </p>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table id="dataTable" class="w-full border compact-table">
          <thead>
            <tr class="bg-purple-600 text-white">
              <th class="border px-2 py-1 text-left">Date Issued</th>
              <th class="border px-2 py-1 text-left">Subject</th>
              <th class="border px-2 py-1 text-left">Case File No.</th>
              <th class="border px-2 py-1 text-left">Branch</th>
              <th class="border px-2 py-1 text-left">Barangay</th>
              <th class="border px-2 py-1 text-left">Households</th>
              <th class="border px-2 py-1 text-left">Status</th>
              <th class="border px-2 py-1 text-left">Activities</th>
              <th class="border px-2 py-1 text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="dataBody">
            <?php foreach ($records as $record): 
              // Decode documents JSON for each record
              $documents = json_decode($record['documents'] ?? '[]', true);
            ?>
            <tr data-id="<?php echo $record['id']; ?>" 
                data-docs="<?php echo htmlspecialchars($record['documents'] ?? '[]'); ?>"
                data-subject="<?php echo htmlspecialchars($record['subject']); ?>"
                data-case-file="<?php echo htmlspecialchars($record['case_file']); ?>">
              <td class="border px-2 py-1"><?php echo $record['date_issued']; ?></td>
              <td class="border px-2 py-1"><?php echo $record['subject']; ?></td>
              <td class="border px-2 py-1"><?php echo $record['case_file']; ?></td>
              <td class="border px-2 py-1"><?php echo $record['branch']; ?></td>
              <td class="border px-2 py-1"><?php echo $record['affected_barangay']; ?></td>
              <td class="border px-2 py-1"><?php echo $record['household_affected']; ?></td>
              <td class="border px-2 py-1"><?php echo $record['status']; ?></td>
              <td class="border px-2 py-1"><?php echo $record['activities']; ?></td>
              <td class="border px-2 py-1 action-buttons">
                <button class="bg-blue-500 hover:bg-blue-600 text-white rounded-md transition" onclick="viewRow(this)">
                  <i class="fas fa-eye"></i>
                </button>
                <button class="bg-yellow-500 hover:bg-yellow-600 text-white rounded-md transition" onclick="editRow(this)">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="bg-red-500 hover:bg-red-600 text-white rounded-md transition" onclick="deleteRow(this)">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add/Edit Modal -->
  <div id="addDataModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-lg landscape-modal max-h-[90vh] flex flex-col border border-gray-300">
      <!-- Modal Header -->
      <div class="p-3 border-b bg-gray-50">
        <div class="flex justify-between items-center">
          <h3 class="text-md font-bold" id="modalTitle">Add New Record</h3>
          <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      
      <!-- Scrollable Content Area -->
      <div id="modalContent" class="overflow-y-auto flex-1 p-4">
        <form id="addDataForm" method="POST" enctype="multipart/form-data" class="space-y-3">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" id="recordId" value="0">
          <input type="hidden" name="camera_photos" id="cameraPhotosInput" value="">
          <input type="hidden" name="files_to_delete" id="filesToDelete" value="">
          
          <div class="form-camera-container">
            <!-- Form Section -->
            <div class="form-section">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block mb-1 font-medium text-sm">Date Issued</label>
                  <input type="date" name="date_issued" id="dateIssued" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required />
                </div>
                <div>
                  <label class="block mb-1 font-medium text-sm">Case File No.</label>
                  <input type="text" name="case_file" id="caseFile" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required />
                </div>
                <div>
                  <label class="block mb-1 font-medium text-sm">Branch</label>
                  <input type="text" name="branch" id="branch" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required />
                </div>
                <div>
                  <label class="block mb-1 font-medium text-sm">Affected Barangay</label>
                  <input type="text" name="affected_barangay" id="affectedBarangay" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required />
                </div>
                <div>
                  <label class="block mb-1 font-medium text-sm">No. of Household Affected</label>
                  <input type="number" name="household_affected" id="householdAffected" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required />
                </div>
                <div>
                  <label class="block mb-1 font-medium text-sm">Status</label>
                  <select name="status" id="status" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required>
                    <option value="">Select Status</option>
                    <option value="CENSUS">CENSUS</option>
                    <option value="DEMOLISHED">DEMOLISHED</option>
                    <option value="EVICTED">EVICTED</option>
                    <option value="DEMOLISHED AND EVICTED">DEMOLISHED AND EVICTED</option>
                  </select>
                </div>
                <div>
                  <label class="block mb-1 font-medium text-sm">Activities</label>
                  <select name="activities" id="activities" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required>
                    <option value="">Select Activity</option>
                    <option value="PENDING">PENDING</option>
                    <option value="ONGOING">ONGOING</option>
                    <option value="CANCELLED">CANCELLED</option>
                    <option value="EXECUTED">EXECUTED</option>
                  </select>
                </div>
              </div>
              
              <div class="mt-3">
                <label class="block mb-1 font-medium text-sm">Subject</label>
                <textarea name="subject" id="subject" rows="3" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" required></textarea>
              </div>
              
              <div class="mt-3">
                <label class="block mb-1 font-medium text-sm">Attach Documents</label>
                <input type="file" name="documents[]" id="docScanner" accept="image/*" multiple class="block mb-2 w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                <div id="docPreview" class="flex flex-wrap gap-2 mt-2"></div>
              </div>
            </div>
            
            <!-- Camera Section -->
            <div class="camera-section">
              <div class="camera-box">
                <label class="block mb-2 font-medium text-sm">Document Scanner</label>
                <div class="mb-2">
                  <button type="button" onclick="startCamera()" class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded-md transition flex items-center text-xs w-full justify-center">
                    <i class="fas fa-camera mr-1"></i> Start Camera
                  </button>
                  <video id="cameraStream" autoplay class="w-full h-48 mt-2 rounded-md hidden object-cover"></video>
                  <button type="button" onclick="capturePhoto()" class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded-md mt-2 hidden transition flex items-center text-xs w-full justify-center" id="captureBtn">
                    <i class="fas fa-camera-retro mr-1"></i> Capture Photo
                  </button>
                  <button type="button" onclick="stopCamera()" class="bg-gray-600 hover:bg-gray-700 text-white px-2 py-1 rounded-md mt-2 hidden transition flex items-center text-xs w-full justify-center" id="stopCameraBtn">
                    <i class="fas fa-stop-circle mr-1"></i> Stop Camera
                  </button>
                </div>
                
                <div class="mt-4">
                  <label class="block mb-1 font-medium text-sm">Preview</label>
                  <div class="border rounded p-2 bg-gray-50 min-h-32">
                    <p class="text-gray-500 text-xs text-center" id="noPreviewText">No photos captured yet</p>
                    <div id="cameraPreview" class="flex flex-wrap gap-2 hidden"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      
      <!-- Modal Footer -->
      <div class="p-3 border-t bg-gray-50 flex justify-end gap-2">
        <button type="button" onclick="closeModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-md transition text-sm">Cancel</button>
        <button type="submit" form="addDataForm" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md transition flex items-center text-sm">
          <i class="fas fa-save mr-1"></i> Save
        </button>
      </div>
    </div>
  </div>

  <!-- View Modal -->
  <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white max-w-4xl w-full p-4 rounded-lg shadow-lg max-h-[90vh] flex flex-col border border-gray-300">
      <div class="flex justify-between items-center border-b pb-2 bg-gray-50">
        <h3 class="text-md font-bold">Record Details</h3>
        <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div id="viewDetails" class="flex-1 overflow-y-auto p-3 space-y-4 text-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="font-medium text-gray-700">Date Issued:</label>
            <p id="viewDateIssued" class="mt-1 p-2 bg-gray-50 rounded"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700">Case File No.:</label>
            <p id="viewCaseFile" class="mt-1 p-2 bg-gray-50 rounded"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700">Branch:</label>
            <p id="viewBranch" class="mt-1 p-2 bg-gray-50 rounded"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700">Affected Barangay:</label>
            <p id="viewAffectedBarangay" class="mt-1 p-2 bg-gray-50 rounded"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700">No. of Household Affected:</label>
            <p id="viewHouseholdAffected" class="mt-1 p-2 bg-gray-50 rounded"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700">Status:</label>
            <p id="viewStatus" class="mt-1 p-2 bg-gray-50 rounded"></p>
          </div>
          <div>
            <label class="font-medium text-gray-700">Activities:</label>
            <p id="viewActivities" class="mt-1 p-2 bg-gray-50 rounded"></p>
          </div>
        </div>
        <div>
          <label class="font-medium text-gray-700">Subject:</label>
          <p id="viewSubject" class="mt-1 p-2 bg-gray-50 rounded whitespace-pre-line"></p>
        </div>
        <div class="mt-4">
          <label class="font-medium text-gray-700 block mb-2">Attached Documents</label>
          <div id="viewDocuments" class="document-gallery"></div>
          <p id="noDocuments" class="text-gray-500 text-sm mt-2 text-center hidden">No documents attached</p>
        </div>
      </div>
      <div class="p-3 border-t bg-gray-50">
        <button onclick="closeViewModal()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md transition text-sm float-right">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Image Viewer Modal -->
  <div id="imageViewer" class="image-viewer hidden">
    <button class="close-viewer" onclick="closeImageViewer()">
      <i class="fas fa-times"></i>
    </button>
    <button class="image-nav prev" onclick="prevImage()">
      <i class="fas fa-chevron-left"></i>
    </button>
    <button class="image-nav next" onclick="nextImage()">
      <i class="fas fa-chevron-right"></i>
    </button>
    <div class="image-container">
      <img id="viewerImage" alt="Document" />
    </div>
    <div class="image-counter" id="imageCounter"></div>
    <div class="image-controls">
      <button onclick="downloadCurrentImage()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md transition flex items-center gap-1 text-sm">
        <i class="fas fa-download"></i> Save
      </button>
    </div>
  </div>

  <!-- Delete Record Confirmation Modal -->
  <div id="deleteRecordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-lg p-4 confirmation-modal">
      <div class="flex justify-between items-center border-b pb-2 mb-3">
        <h3 class="text-md font-bold">Confirm Deletion</h3>
        <button onclick="closeDeleteRecordModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <p>Are you sure you want to delete this record? This action cannot be undone.</p>
      <form id="deleteForm" method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId" value="">
      </form>
      <div class="confirmation-buttons">
        <button onclick="closeDeleteRecordModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-md transition text-sm">
          Cancel
        </button>
        <button onclick="confirmDeleteRecord()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md transition text-sm">
          Delete
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Photo Confirmation Modal -->
  <div id="deletePhotoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-lg p-4 confirmation-modal">
      <div class="flex justify-between items-center border-b pb-2 mb-3">
        <h3 class="text-md font-bold">Confirm Photo Deletion</h3>
        <button onclick="closeDeletePhotoModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <p>Are you sure you want to delete this photo? This action cannot be undone.</p>
      <div class="confirmation-buttons">
        <button onclick="closeDeletePhotoModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-md transition text-sm">
          Cancel
        </button>
        <button id="confirmDeletePhoto" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md transition text-sm">
          Delete
        </button>
      </div>
    </div>
  </div>

  <script>
    // DOM Elements
    const modal = document.getElementById("addDataModal");
    const form = document.getElementById("addDataForm");
    const modalTitle = document.getElementById("modalTitle");
    const docScanner = document.getElementById("docScanner");
    const docPreview = document.getElementById("docPreview");
    const dataBody = document.getElementById("dataBody");
    const viewModal = document.getElementById("viewModal");
    const viewDetails = document.getElementById("viewDetails");
    const viewDocuments = document.getElementById("viewDocuments");
    const noDocuments = document.getElementById("noDocuments");
    const imageViewer = document.getElementById("imageViewer");
    const viewerImage = document.getElementById("viewerImage");
    const imageCounter = document.getElementById("imageCounter");
    const video = document.getElementById("cameraStream");
    const captureBtn = document.getElementById("captureBtn");
    const stopCameraBtn = document.getElementById("stopCameraBtn");
    const cameraPreview = document.getElementById("cameraPreview");
    const noPreviewText = document.getElementById("noPreviewText");
    const deleteRecordModal = document.getElementById("deleteRecordModal");
    const deletePhotoModal = document.getElementById("deletePhotoModal");
    const confirmDeletePhotoBtn = document.getElementById("confirmDeletePhoto");
    const recordIdInput = document.getElementById("recordId");
    const cameraPhotosInput = document.getElementById("cameraPhotosInput");
    const filesToDeleteInput = document.getElementById("filesToDelete");
    const deleteForm = document.getElementById("deleteForm");
    const deleteIdInput = document.getElementById("deleteId");
    const filterForm = document.getElementById("filterForm");
    const monthFilter = document.getElementById("monthFilter");
    const yearFilter = document.getElementById("yearFilter");

    // Variables
    let editTargetRow = null;
    let attachedDocs = [];
    let originalDocs = [];
    let filesToDelete = [];
    let stream = null;
    let deleteTargetRow = null;
    let deletePhotoIndex = null;
    let cameraPhotos = [];
    let currentDocuments = [];
    let currentImageIndex = 0;

    // Filter Functions
    function resetFilter() {
      monthFilter.value = "0";
      yearFilter.value = "0";
      filterForm.submit();
    }

    function exportToExcel() {
      const table = document.getElementById("dataTable");
      const rows = table.querySelectorAll("tr");
      let csvContent = "data:text/csv;charset=utf-8,";
      
      rows.forEach(row => {
        const cells = row.querySelectorAll("th, td");
        const rowData = Array.from(cells).map(cell => {
          // Remove action buttons from export
          if (cell.classList.contains("action-buttons")) return "";
          return `"${cell.textContent.replace(/"/g, '""')}"`;
        }).join(",");
        csvContent += rowData + "\r\n";
      });
      
      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", `pdc_records_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Modal Functions
    function openModal(editing = false) {
      modal.classList.remove("hidden");
      document.body.style.overflow = "hidden";
      if (!editing) {
        form.reset();
        recordIdInput.value = "0";
        docPreview.innerHTML = "";
        cameraPreview.innerHTML = "";
        cameraPreview.classList.add("hidden");
        noPreviewText.classList.remove("hidden");
        attachedDocs = [];
        originalDocs = [];
        filesToDelete = [];
        filesToDeleteInput.value = "";
        cameraPhotos = [];
        cameraPhotosInput.value = "";
        modalTitle.textContent = "Add New Record";
        stopCamera();
      }
    }

    function closeModal() {
      modal.classList.add("hidden");
      document.body.style.overflow = "auto";
      stopCamera();
    }

    function closeViewModal() {
      viewModal.classList.add("hidden");
      document.body.style.overflow = "auto";
    }

    function openImageViewer() {
      imageViewer.classList.remove("hidden");
      document.body.style.overflow = "hidden";
      updateImageViewer();
    }

    function closeImageViewer() {
      imageViewer.classList.add("hidden");
      document.body.style.overflow = "auto";
    }

    function updateImageViewer() {
      if (currentDocuments.length > 0 && currentImageIndex < currentDocuments.length) {
        viewerImage.src = currentDocuments[currentImageIndex];
        imageCounter.textContent = `${currentImageIndex + 1} / ${currentDocuments.length}`;
      }
    }

    function nextImage() {
      if (currentDocuments.length > 0) {
        currentImageIndex = (currentImageIndex + 1) % currentDocuments.length;
        updateImageViewer();
      }
    }

    function prevImage() {
      if (currentDocuments.length > 0) {
        currentImageIndex = (currentImageIndex - 1 + currentDocuments.length) % currentDocuments.length;
        updateImageViewer();
      }
    }

    function downloadCurrentImage() {
      if (currentDocuments.length > 0 && currentImageIndex < currentDocuments.length) {
        const link = document.createElement('a');
        link.href = currentDocuments[currentImageIndex];
        const fileName = `document_${currentImageIndex + 1}_${new Date().getTime()}.jpg`;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      }
    }

    function openDeleteRecordModal(row) {
      deleteTargetRow = row;
      deleteIdInput.value = row.dataset.id;
      deleteRecordModal.classList.remove("hidden");
      document.body.style.overflow = "hidden";
    }

    function closeDeleteRecordModal() {
      deleteRecordModal.classList.add("hidden");
      document.body.style.overflow = "auto";
      deleteTargetRow = null;
    }

    function confirmDeleteRecord() {
      deleteForm.submit();
    }

    function openDeletePhotoModal(index) {
      deletePhotoIndex = index;
      deletePhotoModal.classList.remove("hidden");
      document.body.style.overflow = "hidden";
    }

    function closeDeletePhotoModal() {
      deletePhotoModal.classList.add("hidden");
      document.body.style.overflow = "auto";
      deletePhotoIndex = null;
    }

    // Camera Functions
    async function startCamera() {
      try {
        stream = await navigator.mediaDevices.getUserMedia({ 
          video: { facingMode: 'environment' }, 
          audio: false 
        });
        video.srcObject = stream;
        video.classList.remove("hidden");
        captureBtn.classList.remove("hidden");
        stopCameraBtn.classList.remove("hidden");
      } catch (err) {
        console.error("Error accessing camera:", err);
        alert("Could not access camera. Please check permissions.");
      }
    }

    function stopCamera() {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }
      video.classList.add("hidden");
      captureBtn.classList.add("hidden");
      stopCameraBtn.classList.add("hidden");
    }

    function capturePhoto() {
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      
      const imageData = canvas.toDataURL('image/jpeg');
      cameraPhotos.push(imageData);
      cameraPhotosInput.value = JSON.stringify(cameraPhotos);
      
      updateCameraPreview();
    }

    function updateCameraPreview() {
      if (cameraPhotos.length > 0) {
        cameraPreview.innerHTML = '';
        cameraPhotos.forEach((photo, index) => {
          const photoContainer = document.createElement('div');
          photoContainer.className = 'photo-container';
          
          const img = document.createElement('img');
          img.src = photo;
          img.className = 'w-16 h-16 object-cover rounded';
          
          const deleteBtn = document.createElement('span');
          deleteBtn.className = 'delete-photo';
          deleteBtn.innerHTML = '×';
          deleteBtn.onclick = () => removeCameraPhoto(index);
          
          photoContainer.appendChild(img);
          photoContainer.appendChild(deleteBtn);
          cameraPreview.appendChild(photoContainer);
        });
        
        cameraPreview.classList.remove("hidden");
        noPreviewText.classList.add("hidden");
      } else {
        cameraPreview.classList.add("hidden");
        noPreviewText.classList.remove("hidden");
      }
    }

    function removeCameraPhoto(index) {
      cameraPhotos.splice(index, 1);
      cameraPhotosInput.value = JSON.stringify(cameraPhotos);
      updateCameraPreview();
    }

    // Document Preview Functions
    docScanner.addEventListener('change', function(e) {
      const files = e.target.files;
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file.type.match('image.*')) {
          const reader = new FileReader();
          reader.onload = function(e) {
            const preview = createPreviewElement(e.target.result, file.name);
            docPreview.appendChild(preview);
            attachedDocs.push({file: file, previewUrl: e.target.result});
          };
          reader.readAsDataURL(file);
        }
      }
    });

    function createPreviewElement(url, filename) {
      const container = document.createElement('div');
      container.className = 'photo-container';
      
      const img = document.createElement('img');
      img.src = url;
      img.className = 'w-16 h-16 object-cover rounded';
      img.alt = filename;
      
      const deleteBtn = document.createElement('span');
      deleteBtn.className = 'delete-photo';
      deleteBtn.innerHTML = '×';
      deleteBtn.onclick = function() {
        container.remove();
        // Remove from attachedDocs array
        const index = attachedDocs.findIndex(doc => doc.previewUrl === url);
        if (index !== -1) {
          attachedDocs.splice(index, 1);
        }
      };
      
      container.appendChild(img);
      container.appendChild(deleteBtn);
      return container;
    }

    // CRUD Functions
    function viewRow(button) {
      const row = button.closest('tr');
      const cells = row.querySelectorAll('td');
      
      document.getElementById('viewDateIssued').textContent = cells[0].textContent;
      document.getElementById('viewSubject').textContent = cells[1].textContent;
      document.getElementById('viewCaseFile').textContent = cells[2].textContent;
      document.getElementById('viewBranch').textContent = cells[3].textContent;
      document.getElementById('viewAffectedBarangay').textContent = cells[4].textContent;
      document.getElementById('viewHouseholdAffected').textContent = cells[5].textContent;
      document.getElementById('viewStatus').textContent = cells[6].textContent;
      document.getElementById('viewActivities').textContent = cells[7].textContent;
      
      // Display attached documents
      viewDocuments.innerHTML = '';
      const documentsJson = row.dataset.docs;
      currentDocuments = [];
      
      try {
        if (documentsJson && documentsJson !== '[]') {
          currentDocuments = JSON.parse(documentsJson);
        }
      } catch (e) {
        console.error('Error parsing documents:', e);
      }
      
      if (currentDocuments && currentDocuments.length > 0) {
        noDocuments.classList.add('hidden');
        currentDocuments.forEach((doc, index) => {
          // Create document item
          const docItem = document.createElement('div');
          docItem.className = 'document-item';
          
          // Create preview container
          const previewDiv = document.createElement('div');
          previewDiv.className = 'document-preview';
          
          // Create image element
          const img = document.createElement('img');
          img.src = doc;
          img.alt = `Document ${index + 1}`;
          img.loading = 'lazy';
          
          // Handle image load error
          img.onerror = function() {
            this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY3Ii8+CjxwYXRoIGQ9Ik0zNSAyNVYzNUg0NVYyNUgzNVpNMzAgMjBDMzAgMTguODk1NCAzMC44OTU0IDE4IDMyIDE4SDQ4QzQ5LjEwNDYgMTggNTAgMTguODk1NCA1MCAyMFY0MEM1MCA0MS4xMDQ2IDQ5LjEwNDYgNDIgNDggNDJIMzJDMzAuODk1NCA0MiAzMCA0MS4xMDQ2IDMwIDQwVjIwWk0yMiA0MkgyMEMxOC44OTU0IDQyIDE4IDQxLjEwNDYgMTggNDBWMjBDMTggMTguODk1NCAxOC44OTU0IDE4IDIwIDE4SDIyVjQyWk0yMiAxNkgyMEMxNy43OTEgMTYgMTYgMTcuNzkxIDE2IDIwVjQwQzE2IDQyLjIwOSAxNy43OTEgNDQgMjAgNDRIMjJIMzJIMzRINDhDNTUuMTc5NyA0NCA2MSAzOC4xNzk3IDYxIDMxVjIwQzYxIDE3Ljc5MSA1OS4yMDkgMTYgNTcgMTZINTVWMTNDNTUgMTAuNzkwOSA1My4yMDkgOSA1MSA5SDRDMi4wMDAxIDkgMCAxMSAwIDEzVjQ3QzAgNDkgMi4wMDAxIDUxIDQgNTFINTZINTdWNDRINTZINTdWNTFINTdINSIgZmlsbD0iI0QxRDFEMSIvPgo8L3N2Zz4K';
            this.style.objectFit = 'contain';
            this.style.padding = '1rem';
          };
          
          // Create document info
          const infoDiv = document.createElement('div');
          infoDiv.className = 'document-info';
          
          const title = document.createElement('div');
          title.className = 'document-title';
          title.textContent = `Document ${index + 1}`;
          
          // Create overlay with actions
          const overlayDiv = document.createElement('div');
          overlayDiv.className = 'document-overlay';
          
          const actionsDiv = document.createElement('div');
          actionsDiv.className = 'document-actions';
          
          const viewBtn = document.createElement('button');
          viewBtn.className = 'document-btn';
          viewBtn.innerHTML = '<i class="fas fa-eye"></i> View';
          viewBtn.onclick = (e) => {
            e.stopPropagation();
            currentImageIndex = index;
            openImageViewer();
          };
          
          const downloadBtn = document.createElement('button');
          downloadBtn.className = 'document-btn';
          downloadBtn.innerHTML = '<i class="fas fa-download"></i> Save';
          downloadBtn.onclick = (e) => {
            e.stopPropagation();
            downloadDocument(doc, `Document_${row.dataset.caseFile}_${index + 1}.jpg`);
          };
          
          // Assemble the document item
          actionsDiv.appendChild(viewBtn);
          actionsDiv.appendChild(downloadBtn);
          overlayDiv.appendChild(actionsDiv);
          infoDiv.appendChild(title);
          previewDiv.appendChild(img);
          docItem.appendChild(previewDiv);
          docItem.appendChild(infoDiv);
          docItem.appendChild(overlayDiv);
          viewDocuments.appendChild(docItem);
        });
      } else {
        noDocuments.classList.remove('hidden');
      }
      
      viewModal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    function downloadDocument(url, filename) {
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    function editRow(button) {
      const row = button.closest('tr');
      const cells = row.querySelectorAll('td');
      
      // Populate form fields
      document.getElementById('dateIssued').value = cells[0].textContent;
      document.getElementById('subject').value = cells[1].textContent;
      document.getElementById('caseFile').value = cells[2].textContent;
      document.getElementById('branch').value = cells[3].textContent;
      document.getElementById('affectedBarangay').value = cells[4].textContent;
      document.getElementById('householdAffected').value = cells[5].textContent;
      document.getElementById('status').value = cells[6].textContent;
      document.getElementById('activities').value = cells[7].textContent;
      
      // Set record ID
      recordIdInput.value = row.dataset.id;
      
      // Load existing documents
      docPreview.innerHTML = '';
      const documentsJson = row.dataset.docs;
      let documents = [];
      
      try {
        if (documentsJson && documentsJson !== '[]') {
          documents = JSON.parse(documentsJson);
        }
      } catch (e) {
        console.error('Error parsing documents:', e);
      }
      
      originalDocs = documents || [];
      filesToDelete = [];
      filesToDeleteInput.value = JSON.stringify(filesToDelete);
      
      if (documents && documents.length > 0) {
        documents.forEach((doc, index) => {
          const container = document.createElement('div');
          container.className = 'photo-container';
          
          const img = document.createElement('img');
          img.src = doc;
          img.className = 'w-16 h-16 object-cover rounded';
          img.alt = 'Document ' + (index + 1);
          
          const deleteBtn = document.createElement('span');
          deleteBtn.className = 'delete-photo';
          deleteBtn.innerHTML = '×';
          deleteBtn.onclick = () => {
            filesToDelete.push(doc);
            filesToDeleteInput.value = JSON.stringify(filesToDelete);
            container.remove();
          };
          
          container.appendChild(img);
          container.appendChild(deleteBtn);
          docPreview.appendChild(container);
        });
      }
      
      // Reset camera photos
      cameraPhotos = [];
      cameraPhotosInput.value = '';
      cameraPreview.innerHTML = '';
      cameraPreview.classList.add('hidden');
      noPreviewText.classList.remove('hidden');
      
      // Set modal title and open
      modalTitle.textContent = "Edit Record";
      openModal(true);
      editTargetRow = row;
    }

    function deleteRow(button) {
      const row = button.closest('tr');
      openDeleteRecordModal(row);
    }

    // Keyboard navigation for image viewer
    document.addEventListener('keydown', function(e) {
      if (!imageViewer.classList.contains('hidden')) {
        if (e.key === 'Escape') {
          closeImageViewer();
        } else if (e.key === 'ArrowRight') {
          nextImage();
        } else if (e.key === 'ArrowLeft') {
          prevImage();
        }
      }
    });

    // Swipe support for mobile
    let touchStartX = 0;
    let touchEndX = 0;

    viewerImage.addEventListener('touchstart', function(e) {
      touchStartX = e.changedTouches[0].screenX;
    });

    viewerImage.addEventListener('touchend', function(e) {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
    });

    function handleSwipe() {
      const swipeThreshold = 50;
      const diff = touchStartX - touchEndX;
      
      if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
          nextImage(); // Swipe left
        } else {
          prevImage(); // Swipe right
        }
      }
    }

    // Initialize event listeners
    confirmDeletePhotoBtn.addEventListener('click', function() {
      if (deletePhotoIndex !== null) {
        cameraPhotos.splice(deletePhotoIndex, 1);
        cameraPhotosInput.value = JSON.stringify(cameraPhotos);
        updateCameraPreview();
        closeDeletePhotoModal();
      }
    });

    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
      if (modal.classList.contains('hidden') === false && e.target === modal) {
        closeModal();
      }
      if (viewModal.classList.contains('hidden') === false && e.target === viewModal) {
        closeViewModal();
      }
      if (imageViewer.classList.contains('hidden') === false && e.target === imageViewer) {
        closeImageViewer();
      }
      if (deleteRecordModal.classList.contains('hidden') === false && e.target === deleteRecordModal) {
        closeDeleteRecordModal();
      }
      if (deletePhotoModal.classList.contains('hidden') === false && e.target === deletePhotoModal) {
        closeDeletePhotoModal();
      }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (modal.classList.contains('hidden') === false) {
          closeModal();
        } else if (viewModal.classList.contains('hidden') === false) {
          closeViewModal();
        } else if (imageViewer.classList.contains('hidden') === false) {
          closeImageViewer();
        } else if (deleteRecordModal.classList.contains('hidden') === false) {
          closeDeleteRecordModal();
        } else if (deletePhotoModal.classList.contains('hidden') === false) {
          closeDeletePhotoModal();
        }
      }
    });
  </script>
</body>
</html>