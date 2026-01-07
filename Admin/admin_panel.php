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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_document'])) {
    // Get form data
    $direction = $_POST['direction'];
    $control_no = "UDHO-" . date("Y") . "-" . $_POST['control_number_suffix'];
    
    // Check for duplicate control number in the same direction only
    $check_sql = "SELECT id FROM routing_slips WHERE control_no = ? AND direction = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $control_no, $direction);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        // Duplicate found - store direction for modal display
         $_SESSION['duplicate_direction'] = $direction;
        $duplicate_error = true;
    } else {
        // No duplicate - proceed with saving
        // Process document types (checkboxes)
        $doc_types = isset($_POST['doc_type']) ? $_POST['doc_type'] : [];
        $document_type = implode(", ", $doc_types);
        
        // Handle "Others" document type
        if (in_array("Others", $doc_types) && !empty($_POST['other_doc_type'])) {
            $document_type = str_replace("Others", $_POST['other_doc_type'], $document_type);
        }
        
        $copy_type = $_POST['copy_type'];
        $status = $_POST['status'];
        
        // Process priorities (checkboxes)
        $priorities = isset($_POST['priority']) ? $_POST['priority'] : [];
        $priority = implode(", ", $priorities);
        
        $sender = $_POST['sender'];
        $date_time = $_POST['date_time'];
        $contact_no = $_POST['contact_no'];
        $subject = $_POST['subject'];
        
        // Process routing table data
        $routing_data = [];
        $row_count = $_POST['routing_row_count'];
        
        for ($i = 0; $i < $row_count; $i++) {
            if (!empty($_POST["routing_date_$i"])) {
                $routing_data[] = [
                    'date' => $_POST["routing_date_$i"],
                    'from' => $_POST["routing_from_$i"],
                    'to' => $_POST["routing_to_$i"],
                    'actions' => $_POST["routing_actions_$i"],
                    'due_date' => $_POST["routing_due_date_$i"],
                    'action_taken' => $_POST["routing_action_taken_$i"]
                ];
            }
        }
        $routing_json = json_encode($routing_data);
        
        // Insert into database
        $sql = "INSERT INTO routing_slips (
            control_no, direction, document_type, copy_type, status, priority, 
            sender, date_time, contact_no, subject, routing_data, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssss", 
            $control_no, $direction, $document_type, $copy_type, $status, $priority,
            $sender, $date_time, $contact_no, $subject, $routing_json
        );
        
        if ($stmt->execute()) {
            $success_message = "Routing Slip saved successfully.";
        } else {
            $error_message = "Error saving Routing Slip: " . $conn->error;
        }
        
        $stmt->close();
    }
    
    $check_stmt->close();
}

// Fetch existing records
$records = [];
$sql = "SELECT * FROM routing_slips ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Document Tracking System</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    /* FIXED SIDEBAR STYLES */
    body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }
    
    .sidebar-fixed {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 16rem; /* 64 * 0.25rem = 16rem */
        overflow-y: auto;
        z-index: 40;
    }
    
    .main-content-scrollable {
        margin-left: 16rem; /* Same as sidebar width */
        width: calc(100% - 16rem);
        overflow-x: hidden;
        min-height: 100vh;
        overflow-y: auto;
    }
    
    @media (max-width: 768px) {
        .sidebar-fixed {
            width: 4rem; /* 64px for mobile */
        }
        .sidebar-fixed .sidebar-text {
            display: none;
        }
        .sidebar-fixed .logo-text {
            display: none;
        }
        .sidebar-fixed .sidebar-toggle {
            justify-content: center;
        }
        .main-content-scrollable {
            margin-left: 4rem;
            width: calc(100% - 4rem);
        }
    }

    /* Custom styles for improved UI */
    .form-card {
      background-color: white;
      border-radius: 0.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    
    .form-section-title {
      font-weight: 600;
      color: #374151;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1rem;
    }
    
    .form-group {
      margin-bottom: 1rem;
    }
    
    .form-label {
      display: block;
      font-weight: 500;
      color: #374151;
      margin-bottom: 0.5rem;
    }
    
    .form-input, .form-select, .form-textarea {
      width: 100%;
      padding: 0.5rem 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      background-color: #f9fafb;
      transition: border-color 0.15s ease;
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: #4f46e5;
      background-color: white;
    }
    
    .checkbox-group, .radio-group {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-top: 0.5rem;
    }
    
    .checkbox-item, .radio-item {
      display: flex;
      align-items: center;
    }
    
    .form-checkbox, .form-radio {
      width: 1rem;
      height: 1rem;
      margin-right: 0.5rem;
    }
    
    .other-input {
      margin-left: 0.5rem;
      padding: 0.25rem 0.5rem;
      border: 1px solid #d1d5db;
      border-radius: 0.25rem;
      width: 150px;
    }
    
    .action-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1rem;
    }
    
    .action-table th, .action-table td {
      border: 1px solid #e5e7eb;
      padding: 0.5rem;
    }
    
    .action-table th {
      background-color: #f9fafb;
      font-weight: 500;
    }
    
    .action-table input {
      width: 100%;
      border: none;
      background: transparent;
      padding: 0.25rem;
    }
    
    .reminder-section {
      font-size: 0.75rem;
      margin-top: 1rem;
      padding-top: 0.5rem;
      border-top: 1px solid #e5e7eb;
      color: #6b7280;
    }
    
    .control-number-container {
      display: flex;
      align-items: center;
    }
    
    .control-number-prefix {
      margin-right: 0.5rem;
      font-weight: 500;
    }
    
    /* Modal styles */
    .modal {
      transition: opacity 0.3s ease;
    }
    
    .add-row-btn, .remove-row-btn {
      transition: background-color 0.2s;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      color: white;
      font-size: 0.75rem;
    }
    
    .add-row-btn {
      background-color: #3b82f6;
    }
    
    .add-row-btn:hover {
      background-color: #2563eb;
    }
    
    .remove-row-btn {
      background-color: #ef4444;
    }
    
    .remove-row-btn:hover {
      background-color: #dc2626;
    }
    
    .table-action-cell {
      width: 80px;
      text-align: center;
    }
    
    /* Ensure content doesn't hide behind fixed sidebar */
    @media (min-width: 769px) {
        .main-content-scrollable {
            padding-left: 0.5rem;
        }
    }

    /* Add scrollbar styling for sidebar */
    .sidebar-fixed::-webkit-scrollbar {
        width: 4px;
    }
    
    .sidebar-fixed::-webkit-scrollbar-track {
        background: #374151;
    }
    
    .sidebar-fixed::-webkit-scrollbar-thumb {
        background: #6B7280;
        border-radius: 2px;
    }
    
    .sidebar-fixed::-webkit-scrollbar-thumb:hover {
        background: #9CA3AF;
    }
  </style>
</head>

<body class="bg-gray-100">
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
          <a href="admin_dashboard.php" class="block py-3 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-tachometer-alt mr-3"></i> <span class="sidebar-text">Dashboard</span>
          </a>
        </li>
        <li>
          <a href="admin_records.php" class="block py-3 px-4 bg-gray-700 hover:bg-gray-600 flex items-center">
            <i class="fas fa-file-alt mr-3"></i> <span class="sidebar-text">Routing Slip</span>
          </a>
        </li>
        <li>
          <a href="../settings.php" class="block py-3 px-4 hover:bg-gray-700 flex items-center">
            <i class="fas fa-cog mr-3"></i> <span class="sidebar-text">Settings</span>
          </a>
        </li>
        <li>
          <a href="#" class="block py-3 px-4 mt-10 hover:bg-gray-700 flex items-center">
            <i class="fas fa-sign-out-alt mr-3"></i> <span class="sidebar-text">Logout</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Scrollable Main Content -->
  <div class="main-content-scrollable">
    <div class="p-4 md:p-6">
      <header class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h1 class="text-2xl font-bold text-gray-800">Document Tracking System</h1>
        <div class="flex items-center gap-2">
          <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
          <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
        </div>
      </header>
      
      <!-- Display success/error messages -->
      <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?php echo $success_message; ?></span>
        </div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?php echo $error_message; ?></span>
        </div>
      <?php endif; ?>
      
      <!-- Navigation Buttons -->
      <div class="flex gap-4 mb-6">
        <button class="px-4 py-2 bg-indigo-600 text-white rounded-md flex items-center">
          <i class="fas fa-file-alt mr-2"></i> Routing Slip
        </button>
      </div>
      
      <!-- Routing Slip Form -->
      <form method="POST" action="" id="routingForm" class="space-y-6">
        <!-- Control Number & Direction Card -->
        <div class="form-card">
          <h2 class="form-section-title">Document Information</h2>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Control Number</label>
              <div class="control-number-container">
                <span class="control-number-prefix">UDHO-<?php echo date("Y"); ?>-</span>
                <input 
                  type="text" 
                  name="control_number_suffix" 
                  id="controlNumberSuffix" 
                  class="form-input w-24"
                  placeholder="0001"
                  maxlength="4"
                  pattern="[0-9]{4}"
                  title="Please enter 4 digits"
                  oninput="validateControlNumber()"
                  required
                >
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Direction</label>
              <div class="radio-group">
                <label class="radio-item">
                  <input type="radio" name="direction" value="Incoming" class="form-radio" checked>
                  <span>Incoming</span>
                </label>
                <label class="radio-item">
                  <input type="radio" name="direction" value="Outgoing" class="form-radio">
                  <span>Outgoing</span>
                </label>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Document Details Card -->
        <div class="form-card">
          <h2 class="form-section-title">Document Details</h2>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Document Type (Check all that apply)</label>
              <div class="checkbox-group">
                <label class="checkbox-item">
                  <input type="checkbox" name="doc_type[]" value="Memo Letter" class="form-checkbox" checked>
                  <span>Memo Letter</span>
                </label>
                <label class="checkbox-item">
                  <input type="checkbox" name="doc_type[]" value="Referral Request" class="form-checkbox">
                  <span>Referral Request</span>
                </label>
                <label class="checkbox-item">
                  <input type="checkbox" name="doc_type[]" value="Report Proposal" class="form-checkbox">
                  <span>Report Proposal</span>
                </label>
                <label class="checkbox-item">
                  <input type="checkbox" name="doc_type[]" value="Invitation" class="form-checkbox">
                  <span>Invitation</span>
                </label>
                <label class="checkbox-item">
                  <input type="checkbox" name="doc_type[]" value="Others" class="form-checkbox" id="othersCheckbox">
                  <span>Others:</span>
                  <input type="text" name="other_doc_type" class="other-input" placeholder="Specify" id="otherDocType" disabled>
                </label>
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Type of Copy Sent</label>
              <div class="radio-group">
                <label class="radio-item">
                  <input type="radio" name="copy_type" value="Original" class="form-radio" checked>
                  <span>Original</span>
                </label>
                <label class="radio-item">
                  <input type="radio" name="copy_type" value="Photocopy" class="form-radio">
                  <span>Photocopy</span>
                </label>
                <label class="radio-item">
                  <input type="radio" name="copy_type" value="Scanned" class="form-radio">
                  <span>Scanned</span>
                </label>
              </div>
            </div>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-select" required>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="For Review">For Review</option>
                <option value="Completed">Completed</option>
                <option value="On Hold">On Hold</option>
                <option value="Cancelled">Cancelled</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Priority (Check all that apply)</label>
              <div class="checkbox-group">
                <label class="checkbox-item">
                  <input type="checkbox" name="priority[]" value="3 days" class="form-checkbox">
                  <span>3 days</span>
                </label>
                <label class="checkbox-item">
                  <input type="checkbox" name="priority[]" value="7 days" class="form-checkbox">
                  <span>7 days</span>
                </label>
                <label class="checkbox-item">
                  <input type="checkbox" name="priority[]" value="15 days" class="form-checkbox">
                  <span>15 days</span>
                </label>
                <label class="checkbox-item">
                  <input type="checkbox" name="priority[]" value="20 days" class="form-checkbox">
                  <span>20 days</span>
                </label>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Sender Information Card -->
        <div class="form-card">
          <h2 class="form-section-title">Sender Information</h2>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Sender Name</label>
              <input type="text" name="sender" class="form-input" placeholder="Sender Name" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Date/Time</label>
              <input type="datetime-local" name="date_time" class="form-input" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Contact Number</label>
              <input type="text" name="contact_no" class="form-input" placeholder="Contact number" required>
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label">Subject</label>
            <textarea name="subject" class="form-textarea" rows="3" placeholder="Enter subject details" required></textarea>
          </div>
        </div>
        
        <!-- Document Routing Card -->
        <div class="form-card">
          <h2 class="form-section-title">Document Routing</h2>
          <table class="action-table" id="routingTable">
            <thead>
              <tr>
                <th>Date</th>
                <th>From</th>
                <th>To</th>
                <th>Required Actions/ Instructions</th>
                <th>Due Date</th>
                <th>Action Taken</th>
                <th class="table-action-cell">Action</th>
              </tr>
            </thead>
            <tbody id="routingTableBody">
              <?php for ($i = 0; $i < 5; $i++): ?>
              <tr>
                <td><input type="date" name="routing_date_<?php echo $i; ?>"></td>
                <td><input type="text" name="routing_from_<?php echo $i; ?>"></td>
                <td><input type="text" name="routing_to_<?php echo $i; ?>"></td>
                <td><input type="text" name="routing_actions_<?php echo $i; ?>"></td>
                <td><input type="date" name="routing_due_date_<?php echo $i; ?>"></td>
                <td><input type="text" name="routing_action_taken_<?php echo $i; ?>"></td>
                <td class="table-action-cell">
                  <?php if ($i === 0): ?>
                    <button type="button" class="add-row-btn" onclick="addRow()">
                      <i class="fas fa-plus"></i>
                    </button>
                  <?php else: ?>
                    <button type="button" class="remove-row-btn" onclick="removeRow(this)">
                      <i class="fas fa-minus"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endfor; ?>
            </tbody>
          </table>
          
          <input type="hidden" name="routing_row_count" id="routingRowCount" value="5">
          
          <div class="mt-4">
            <button type="button" id="addRoutingRow" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition flex items-center" onclick="addRow()">
              <i class="fas fa-plus mr-2"></i> Add Row
            </button>
          </div>
          
          <div class="reminder-section">
            <p><strong>Reminders:</strong> Under Sec. 5 of RA 6713, otherwise known as the <em>Code of Conduct and Ethical Standards for Public Officials and Employees</em>, enjoins all public servants to respond to letters, telegrams, and other means of communication sent by the public within fifteen (15) working days from the receipt thereof. The reply must contain the action taken on the request. Likewise, all official papers and documents must be processed and completed within a reasonable time.</p>
          </div>
        </div>
        
        <!-- Save Button -->
        <div class="flex justify-end">
          <button type="submit" name="save_document" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition flex items-center">
            <i class="fas fa-save mr-2"></i> Save Document
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Duplicate Control Number Modal -->
  <div id="duplicateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-md w-full">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold text-red-600">Duplicate Control Number</h3>
        <button id="closeDuplicateModal" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <p class="mb-4" id="duplicateMessage">This control number already exists for <?php echo isset($_SESSION['duplicate_direction']) ? $_SESSION['duplicate_direction'] : ''; ?> documents. Please use a different control number suffix.</p>
      <div class="flex justify-end">
        <button id="confirmDuplicateModal" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
          OK
        </button>
      </div>
    </div>
  </div>

  <script>
    function validateControlNumber() {
      const input = document.getElementById('controlNumberSuffix');
      input.value = input.value.replace(/[^0-9]/g, '').slice(0, 4);
    }

    document.getElementById('othersCheckbox').addEventListener('change', function() {
      document.getElementById('otherDocType').disabled = !this.checked;
      if (!this.checked) {
        document.getElementById('otherDocType').value = '';
      }
    });

    document.addEventListener('DOMContentLoaded', () => {
      const now = new Date();
      const dateTimeInput = document.querySelector('input[type="datetime-local"]');
      dateTimeInput.value = now.toISOString().slice(0, 16);
    });

    function showDuplicateModal() {
      document.getElementById('duplicateModal').classList.remove('hidden');
    }

    function hideDuplicateModal() {
      document.getElementById('duplicateModal').classList.add('hidden');
    }

    document.getElementById('closeDuplicateModal').addEventListener('click', hideDuplicateModal);
    document.getElementById('confirmDuplicateModal').addEventListener('click', hideDuplicateModal);

    <?php if (isset($duplicate_error) && $duplicate_error): ?>
      document.addEventListener('DOMContentLoaded', function() {
        showDuplicateModal();
      });
    <?php endif; ?>

    // Real-time duplicate checking with direction
    document.getElementById('controlNumberSuffix').addEventListener('change', function() {
      const direction = document.querySelector('input[name="direction"]:checked').value;
      const controlNumber = 'UDHO-' + new Date().getFullYear() + '-' + this.value;
      
      if (this.value.length === 4) {
        fetch('check_control_number.php?control_no=' + encodeURIComponent(controlNumber) + '&direction=' + direction)
          .then(response => response.json())
          .then(data => {
            if (data.exists) {
              document.getElementById('duplicateMessage').textContent = 
                'This control number already exists for ' + direction + ' documents. Please use a different control number suffix.';
              showDuplicateModal();
            }
          });
      }
    });

    // Routing table functionality
    let rowCount = <?php echo $i; ?>;
    
    function addRow() {
      const tableBody = document.getElementById('routingTableBody');
      const newRow = document.createElement('tr');
      
      newRow.innerHTML = `
        <td><input type="date" name="routing_date_${rowCount}"></td>
        <td><input type="text" name="routing_from_${rowCount}"></td>
        <td><input type="text" name="routing_to_${rowCount}"></td>
        <td><input type="text" name="routing_actions_${rowCount}"></td>
        <td><input type="date" name="routing_due_date_${rowCount}"></td>
        <td><input type="text" name="routing_action_taken_${rowCount}"></td>
        <td class="table-action-cell">
          <button type="button" class="remove-row-btn" onclick="removeRow(this)">
            <i class="fas fa-minus"></i>
          </button>
        </td>
      `;
      
      tableBody.appendChild(newRow);
      rowCount++;
      document.getElementById('routingRowCount').value = rowCount;
    }
    
    function removeRow(button) {
      const row = button.closest('tr');
      const tableBody = document.getElementById('routingTableBody');
      
      if (tableBody.children.length > 1) {
        row.remove();
        // Update row count but don't decrement as we want to keep unique names
        // We'll recount the rows when submitting
        recountRows();
      } else {
        alert('You must have at least one routing entry.');
      }
    }
    
    function recountRows() {
      const tableBody = document.getElementById('routingTableBody');
      const rows = tableBody.querySelectorAll('tr');
      let count = 0;
      
      rows.forEach((row, index) => {
        const inputs = row.querySelectorAll('input');
        inputs.forEach(input => {
          const name = input.name.replace(/_\d+$/, '_' + count);
          input.name = name;
        });
        count++;
      });
      
      rowCount = count;
      document.getElementById('routingRowCount').value = rowCount;
    }
  </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>