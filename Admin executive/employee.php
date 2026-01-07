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

// Session timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Get current user data from session
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_role = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch password change requests for current user
$password_requests_list = [];
if ($current_user_id) {
    $stmt = $conn->prepare("SELECT id, status, created_at FROM password_change_requests WHERE user_id=? ORDER BY created_at DESC");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $password_requests_list[] = $row;
    }
    $stmt->close();
}

// Initialize messages
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

// Handle employee details view
if (isset($_GET['view_employee'])) {
    $employee_id = intval($_GET['view_employee']);
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_details = $result->fetch_assoc();
    
    if (!$employee_details) {
        $_SESSION['error'] = "Employee not found";
        header("Location: employee.php");
        exit();
    }
    
    // Display employee details page
    displayEmployeeDetails($employee_details);
    exit();
}

// Fetch all employees from database
$employees = [];
$password_requests = 0;

$query = "SELECT id, username, password, role, created_at, profile_picture, email, phone, password_changed_at 
          FROM users 
          WHERE role IN ('Admin', 'Operation', 'Admin Executive', 'HOA', 'Enumerator')
          ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
        
        // Count password reset requests (users who haven't changed their password yet)
        if ($row['password_changed_at'] === null) {
            $password_requests++;
        }
    }
}

// Function to maintain only 15 most recent password change requests
function maintainPasswordRequestsLimit($conn) {
    // Count total requests
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM password_change_requests");
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_requests = $count_row['total'];
    $count_stmt->close();
    
    // If more than 15 requests, delete the oldest ones
    if ($total_requests > 15) {
        $delete_count = $total_requests - 15;
        $delete_stmt = $conn->prepare("DELETE FROM password_change_requests ORDER BY created_at ASC LIMIT ?");
        $delete_stmt->bind_param("i", $delete_count);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
}

// Fetch all password change requests (for Admin) - only show 15 most recent
$all_password_requests = [];
$query_requests = "SELECT r.id, u.id as user_id, u.username, r.reason, r.status, r.created_at, r.new_password 
                   FROM password_change_requests r
                   JOIN users u ON u.id = r.user_id
                   ORDER BY r.created_at DESC
                   LIMIT 15";

$result_requests = $conn->query($query_requests);
if ($result_requests) {
    while ($row = $result_requests->fetch_assoc()) {
        $all_password_requests[] = $row;
    }
}

// Count active/inactive employees
$active_employees = count($employees);
$inactive_employees = 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Reset password
    if (isset($_POST['reset_password'])) {
        $employee_id = $_POST['employee_id'];
        
        // Generate a strong temporary password
        $temp_password = generateStrongPassword();
        
        // Hash the temporary password
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $employee_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Password reset successfully for user ID $employee_id. Temporary password: <strong>$temp_password</strong>";
        } else {
            $_SESSION['error'] = "Error resetting password: " . $conn->error;
        }
        $stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    // Function for generating strong passwords
    function generateStrongPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    // Add new employee
    if (isset($_POST['add_employee'])) {
        $id = intval($_POST['id']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $profile_picture = 'PROFILE_SAMPLE.jpg';
        
        // Validate inputs
        $errors = [];
        
        if (empty($id) || $id <= 0) {
            $errors[] = "Valid Employee ID is required";
        }
        
        if (empty($username)) {
            $errors[] = "Username is required";
        }
        
        // ==================== STRONG PASSWORD VALIDATION ==================== //
        if (empty($password)) {
            $errors[] = "Password is required";
        } else {
            // Minimum 8 characters
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long";
            }
            
            // At least one uppercase letter
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = "Password must contain at least one uppercase letter";
            }
            
            // At least one lowercase letter
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = "Password must contain at least one lowercase letter";
            }
            
            // At least one number
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = "Password must contain at least one number";
            }
            
            // At least one special character
            if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
                $errors[] = "Password must contain at least one special character (!@#$%^&* etc.)";
            }
        }
        // ==================== END OF PASSWORD VALIDATION ==================== //
        
        if (empty($role)) {
            $errors[] = "Role is required";
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // Check if ID exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error'] = "Employee ID already exists. Please choose a different ID.";
            $check_stmt->close();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        $check_stmt->close();
        
        // ==================== PASSWORD HASHING ==================== //
        // Hash the password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new employee with HASHED password
        $stmt = $conn->prepare("INSERT INTO users (id, username, password, role, email, phone, profile_picture, created_at, password_changed_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("issssss", $id, $username, $hashed_password, $role, $email, $phone, $profile_picture);
        // ==================== END OF PASSWORD HASHING ==================== //
        
        if ($stmt->execute()) {
            // Don't show the plain text password in message
            $_SESSION['message'] = "Employee added successfully! The employee must set their own password on first login.";
        } else {
            $_SESSION['error'] = "Error adding employee: " . $conn->error;
        }
        $stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    // Update employee
    if (isset($_POST['update_employee'])) {
        $id = intval($_POST['id']);
        $username = trim($_POST['username']);
        $role = trim($_POST['role']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($username) || empty($role)) {
            $_SESSION['error'] = "Username and role are required fields.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $role, $email, $phone, $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Employee updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating employee: " . $conn->error;
        }
        $stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    // Delete employee
    if (isset($_POST['delete_employee'])) {
        $employee_id = intval($_POST['employee_id']);
        
        // Prevent deleting own account
        if ($employee_id == $current_user_id) {
            $_SESSION['error'] = "You cannot delete your own account!";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        
        // First, delete related records to maintain referential integrity
        // Delete password change requests
        $stmt1 = $conn->prepare("DELETE FROM password_change_requests WHERE user_id = ?");
        $stmt1->bind_param("i", $employee_id);
        $stmt1->execute();
        $stmt1->close();
        
        // Delete user notifications
        $stmt2 = $conn->prepare("DELETE FROM user_notifications WHERE user_id = ?");
        $stmt2->bind_param("i", $employee_id);
        $stmt2->execute();
        $stmt2->close();
        
        // Now delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $employee_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Employee deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting employee: " . $conn->error;
        }
        $stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    // Handle password change request approval/rejection
    if (isset($_POST['approve_request']) || isset($_POST['reject_request'])) {
        $request_id = $_POST['request_id'];
        $status = isset($_POST['approve_request']) ? 'approved' : 'rejected';
        $adminId = $_SESSION['user_id'] ?? 0;
        
        // Get the request details including user_id and hashed password
        $stmt = $conn->prepare("SELECT pcr.user_id, pcr.hashed_password, pcr.new_password, u.username 
                               FROM password_change_requests pcr
                               JOIN users u ON pcr.user_id = u.id
                               WHERE pcr.id = ? AND pcr.status = 'pending'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        
        if ($request) {
            if ($status === 'approved') {
                // Update user's password with the hashed version from the request
                if (!empty($request['hashed_password'])) {
                    $updateUser = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
                    $updateUser->bind_param("si", $request['hashed_password'], $request['user_id']);
                    
                    if ($updateUser->execute()) {
                        // Create notification for user
                        $message = "Your password has been changed. Your new password is: " . $request['new_password'];
                        $notifStmt = $conn->prepare("INSERT INTO user_notifications (user_id, type, message, is_read, created_at) VALUES (?, 'password_approved', ?, 0, NOW())");
                        $notifStmt->bind_param("is", $request['user_id'], $message);
                        $notifStmt->execute();
                        $notifStmt->close();
                        
                        $_SESSION['message'] = "Password change approved for user '" . $request['username'] . "'!";
                        error_log("Password approved for user: " . $request['username']);
                    } else {
                        $_SESSION['error'] = "Error updating password: " . $conn->error;
                        error_log("Error updating password: " . $conn->error);
                    }
                    $updateUser->close();
                } else {
                    $_SESSION['error'] = "No hashed password found in the request.";
                    error_log("No hashed password for request ID: " . $request_id);
                }
            } else {
                // Reject action
                $notification_message = "Your password change request has been rejected.";
                $notifStmt = $conn->prepare("INSERT INTO user_notifications (user_id, type, message, is_read, created_at) VALUES (?, 'password_rejected', ?, 0, NOW())");
                $notifStmt->bind_param("is", $request['user_id'], $notification_message);
                $notifStmt->execute();
                $notifStmt->close();
                
                $_SESSION['message'] = "Password change request rejected for user '" . $request['username'] . "'!";
                error_log("Password rejected for user: " . $request['username']);
            }
            
            // Update request status
            $updateRequest = $conn->prepare("UPDATE password_change_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $updateRequest->bind_param("sii", $status, $adminId, $request_id);
            $updateRequest->execute();
            $updateRequest->close();
        } else {
            $_SESSION['error'] = "Request not found or already processed.";
            error_log("Request not found: ID=" . $request_id);
        }
        
        // Maintain the 15-request limit after any action
        maintainPasswordRequestsLimit($conn);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Maintain the 15-request limit on page load
maintainPasswordRequestsLimit($conn);

// Handle AJAX request to get employee info
if (isset($_GET['get_employee'])) {
    $id = intval($_GET['get_employee']);

    $stmt = $conn->prepare("SELECT id, username, role, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    echo json_encode($result->fetch_assoc());
    exit();
}

$conn->close();

// Function to display employee details page
function displayEmployeeDetails($employee) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Employee Details</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            .profile-picture {
                width: 150px;
                height: 150px;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid #ddd;
            }
            .detail-card {
                background-color: white;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body class="bg-gray-100">
        <div class="container mx-auto px-4 py-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Employee Details</h1>
                <a href="employee.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Employees
                </a>
            </div>
            
            <div class="max-w-4xl mx-auto">
                <div class="flex flex-col md:flex-row gap-8">
                    <!-- Profile Picture and Basic Info -->
                    <div class="w-full md:w-1/3">
                        <div class="detail-card text-center">
                            <img src="../assets/profile_pictures/<?php echo htmlspecialchars($employee['profile_picture']); ?>"
                                alt="Profile"
                                class="profile-picture mx-auto"
                                onerror="this.src='../assets/PROFILE_SAMPLE.jpg'">
                            <h2 class="text-2xl font-semibold mt-4"><?php echo htmlspecialchars($employee['username']); ?></h2>
                            <p class="text-gray-600"><?php echo htmlspecialchars($employee['role']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Detailed Information -->
                    <div class="w-full md:w-2/3">
                        <div class="detail-card">
                            <h3 class="text-xl font-semibold mb-4">Personal Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-gray-600">Employee ID</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($employee['id']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Username</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($employee['username']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Role</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($employee['role']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Account Created</p>
                                    <p class="font-medium"><?php echo date('M d, Y h:i A', strtotime($employee['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <h3 class="text-xl font-semibold mb-4">Contact Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-gray-600">Email</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($employee['email'] ?? 'Not provided'); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Phone</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($employee['phone'] ?? 'Not provided'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <h3 class="text-xl font-semibold mb-4">Account Status</h3>
                            <div class="flex items-center">
                                <?php if ($employee['password_changed_at'] === null): ?>
                                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm mr-2">Initial Setup Required</span>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                        <button type="submit" name="reset_password" class="text-blue-600 hover:text-blue-800 text-sm">
                                            <i class="fas fa-key mr-1"></i> Set Password
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">Active</span>
                                    <span class="text-gray-600 text-sm ml-2">Password last changed: <?php echo date('M d, Y', strtotime($employee['password_changed_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Employee Management</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <style>
    .custom-card {
      transition: all 0.3s ease;
    }
    .custom-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
    
    /* Dropdown styles - FIXED VERSION */
    .dropdown-menu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
    }
    .dropdown-menu.open {
      max-height: 300px;
      overflow-y: auto;
    }
    .dropdown-toggle {
      cursor: pointer;
      transition: all 0.3s ease;
      user-select: none;
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
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .submenu-link:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    .submenu-link.active {
      background-color: rgba(255, 255, 255, 0.15);
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
      .dropdown-menu.open {
        max-height: 400px;
      }
      
      .sidebar-link, .dropdown-toggle {
        padding: 12px 14px;
      }
      
      .submenu-link {
        padding-left: 40px;
      }
      
      /* Mobile sidebar toggle */
      #mobileSidebarToggle {
        display: block;
      }
      
      .w-64 {
        width: 0;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        position: fixed;
        height: 100vh;
        z-index: 40;
      }
      
      .w-64.mobile-open {
        width: 250px;
        transform: translateX(0);
      }
      
      .flex-1 {
        margin-left: 0;
        width: 100%;
      }
      
      /* Overlay for mobile sidebar */
      .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 35;
      }
      
      .sidebar-overlay.active {
        display: block;
      }
    }
    
    /* Ensure sidebar is scrollable on mobile */
    @media (max-height: 600px) {
      nav {
        max-height: calc(100vh - 180px);
        overflow-y: auto;
      }
    }
    
    /* Desktop styles */
    @media (min-width: 769px) {
      #mobileSidebarToggle {
        display: none;
      }
      
      .sidebar-overlay {
        display: none !important;
      }
    }
    
    .badge {
      display: inline-block;
      padding: 0.25em 0.4em;
      font-size: 75%;
      font-weight: 700;
      line-height: 1;
      text-align: center;
      white-space: nowrap;
      vertical-align: baseline;
      border-radius: 0.25rem;
    }
    .badge-danger {
      color: #fff;
      background-color: #dc3545;
    }
    .profile-picture {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #ddd;
    }
    .modal {
      display: none;
      position: fixed;
      z-index: 50;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    .modal-content {
      background-color: #fefefe;
      margin: 5% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 800px;
      border-radius: 8px;
    }
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      animation: slideIn 0.5s, fadeOut 0.5s 2.5s forwards;
    }
    @keyframes slideIn {
      from { transform: translateX(100%); }
      to { transform: translateX(0); }
    }
    @keyframes fadeOut {
      from { opacity: 1; }
      to { opacity: 0; }
    }
    .confirmation-modal {
      max-width: 500px;
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
  <!-- Mobile sidebar toggle -->
  <div id="mobileSidebarToggle" class="md:hidden fixed top-4 left-4 z-50">
    <button class="bg-gray-800 text-white p-2 rounded-md">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <!-- Sidebar -->
  <div class="w-64 bg-gray-800 text-white flex flex-col">
    <div class="flex items-center justify-center h-24">
      <!-- Profile Picture Container -->
      <div class="rounded-full bg-gray-200 w-20 h-20 flex items-center justify-center overflow-hidden border-2 border-white shadow-md">
        <?php
        // Assuming you have a user profile picture path stored in a session or variable
        $profilePicture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default_profile.jpg';
        ?>
        <img src="../assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>" 
            alt="Profile Picture" 
            class="w-full h-full object-cover"
            onerror="this.src='../assets/PROFILE_SAMPLE.jpg'">
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
          <a href="/Admin executive/employee.php" class="sidebar-link flex items-center py-3 px-4 active-link">
            <i class="fas fa-users mr-3"></i> Employees
          </a>
        </li>
        <li>
          <a href="../settings.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-cog mr-3"></i> Settings
          </a>
        </li>
        <li>
          <a href="../logout.php" class="sidebar-link flex items-center py-3 px-4 mt-10">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="flex-1 p-4 md:p-6 overflow-auto w-full">
    <!-- Notification messages -->
    <?php if ($message): ?>
      <div class="notification">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?php echo $message; ?></span>
          <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
              <title>Close</title>
              <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
          </span>
        </div>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="notification">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?php echo $error; ?></span>
          <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
              <title>Close</title>
              <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
          </span>
        </div>
      </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold text-gray-800">Employee Management</h1>
      <div class="flex items-center gap-2">
        <img src="../assets/UDHOLOGO.png" alt="Logo" class="h-8">
        <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
      </div>
    </div>

    <!-- Notification and Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- Password Reset Requests -->
      <div class="bg-white p-6 rounded-lg shadow-md custom-card relative">
        <div class="flex justify-between items-center">
          <div>
            <p class="text-gray-500">Initial Password Setup</p>
            <h3 class="text-2xl font-bold"><?php echo $password_requests; ?></h3>
          </div>
          <div class="bg-red-100 p-3 rounded-full">
            <i class="fas fa-key text-red-600 text-xl"></i>
          </div>
        </div>
        <div class="mt-4">
          <button onclick="showPasswordRequests()" class="w-full bg-red-100 hover:bg-red-200 text-red-800 py-2 rounded-md transition">
            View Requests
          </button>
        </div>
        <?php if ($password_requests > 0): ?>
          <span class="badge badge-danger absolute -top-2 -right-2"><?php echo $password_requests; ?> New</span>
        <?php endif; ?>
      </div>
      
      <!-- Active Employees -->
      <div class="bg-white p-6 rounded-lg shadow-md custom-card">
        <div class="flex justify-between items-center">
          <div>
            <p class="text-gray-500">Active Employees</p>
            <h3 class="text-2xl font-bold"><?php echo $active_employees; ?></h3>
          </div>
          <div class="bg-green-100 p-3 rounded-full">
            <i class="fas fa-user-check text-green-600 text-xl"></i>
          </div>
        </div>
      </div>
      
      <!-- Total Employees -->
      <div class="bg-white p-6 rounded-lg shadow-md custom-card">
        <div class="flex justify-between items-center">
          <div>
            <p class="text-gray-500">Total Employees</p>
            <h3 class="text-2xl font-bold"><?php echo count($employees); ?></h3>
          </div>
          <div class="bg-blue-100 p-3 rounded-full">
            <i class="fas fa-users text-blue-600 text-xl"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Password Change Requests Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Password Change Requests</h2>
        <div class="flex items-center gap-2">
          <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm">
            <?php echo count(array_filter($all_password_requests, function($req) { return $req['status'] === 'pending'; })); ?> Pending
          </span>
          <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
            Showing <?php echo count($all_password_requests); ?>/15 Most Recent
          </span>
        </div>
      </div>
      
      <?php if (count($all_password_requests) > 0): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Password</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($all_password_requests as $request): ?>
            <tr>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['username']); ?></div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <?php if ($request['status'] === 'pending'): ?>
                  <div class="flex items-center">
                    <input type="password" 
                           id="password_emp_<?php echo $request['id']; ?>" 
                           value="<?php echo htmlspecialchars($request['new_password']); ?>" 
                           class="text-sm border rounded px-2 py-1 w-40" 
                           readonly>
                    <button type="button" 
                            class="ml-2 text-blue-600 hover:text-blue-800 text-sm"
                            onclick="togglePasswordVisibility('password_emp_<?php echo $request['id']; ?>', this)">
                        <i class="fas fa-eye"></i> Show
                    </button>
                  </div>
                <?php else: ?>
                  <div class="text-sm text-gray-500">********</div>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['reason']); ?></div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-500"><?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <?php if ($request['status'] === 'pending'): ?>
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                <?php elseif ($request['status'] === 'approved'): ?>
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                <?php else: ?>
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <?php if ($request['status'] === 'pending'): ?>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <button type="submit" name="approve_request" 
                            class="text-green-600 hover:text-green-900 mr-3"
                            onclick="return confirm('Approve password change for <?php echo htmlspecialchars($request['username']); ?>?')">
                      Approve
                    </button>
                    <button type="submit" name="reject_request" 
                            class="text-red-600 hover:text-red-900"
                            onclick="return confirm('Reject password change request for <?php echo htmlspecialchars($request['username']); ?>?')">
                      Reject
                    </button>
                  </form>
                <?php else: ?>
                  <span class="text-gray-400">Action taken</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4">
        <p class="text-gray-500">No password change requests</p>
      </div>
      <?php endif; ?>
      
      <!-- Information about the limit -->
      <div class="mt-4 p-3 bg-blue-50 rounded-md">
        <p class="text-sm text-blue-700">
          <i class="fas fa-info-circle mr-2"></i>
          Only the 15 most recent password change requests are shown. Older requests are automatically removed.
        </p>
      </div>
    </div>

    <!-- Employee Management Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
        <h2 class="text-xl font-semibold">Employee Records</h2>
        <div class="flex flex-wrap gap-2">
          <button onclick="showAddEmployeeModal()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Employee
          </button>
          <button onclick="exportEmployeeData()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md transition flex items-center">
            <i class="fas fa-file-export mr-2"></i> Export Data
          </button>
        </div>
      </div>
      
      <!-- Employee Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200" id="employeeTableBody">
            <?php foreach ($employees as $employee): ?>
              <tr>
                <!-- ID -->
                <td class="px-6 py-4 whitespace-nowrap">
                  <?php echo htmlspecialchars($employee['id']); ?>
                </td>

                <!-- Profile Picture -->
                <td class="px-6 py-4 whitespace-nowrap">
                  <img src="../assets/profile_pictures/<?php echo htmlspecialchars($employee['profile_picture']); ?>"
                      alt="Profile" class="profile-picture"
                      onerror="this.src='../assets/PROFILE_SAMPLE.jpg'">
                </td>

                <!-- Username -->
                <td class="px-6 py-4 whitespace-nowrap">
                  <?php echo htmlspecialchars($employee['username']); ?>
                </td>

                <!-- Role -->
                <td class="px-6 py-4 whitespace-nowrap">
                  <?php echo htmlspecialchars($employee['role']); ?>
                </td>

                <!-- Created At -->
                <td class="px-6 py-4 whitespace-nowrap">
                  <?php echo date('M d, Y h:i A', strtotime($employee['created_at'])); ?>
                </td>

                <!-- Status -->
                <td class="px-6 py-4 whitespace-nowrap">
                  <?php if ($employee['password_changed_at'] === null): ?>
                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Initial Setup</span>
                  <?php else: ?>
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Active</span>
                  <?php endif; ?>
                </td>

                <!-- Actions -->
                <td class="px-6 py-4 whitespace-nowrap flex gap-2">
                  <button onclick="viewEmployeeDetails(<?php echo $employee['id']; ?>)"
                          class="text-blue-600 hover:text-blue-900"
                          title="View">
                    <i class="fas fa-eye"></i>
                  </button>

                  <button onclick="editEmployee(<?php echo $employee['id']; ?>)"
                          class="text-yellow-600 hover:text-yellow-900"
                          title="Edit">
                    <i class="fas fa-edit"></i>
                  </button>

                  <button onclick="confirmDeleteEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['username']); ?>')"
                          class="text-red-600 hover:text-red-900"
                          title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>

                  <?php if ($employee['password_changed_at'] === null): ?>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                      <button type="submit"
                              name="reset_password"
                              class="text-blue-600 hover:text-blue-900"
                              title="Set Initial Password">
                        <i class="fas fa-key"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-500">
          Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($employees); ?></span> of <span class="font-medium"><?php echo count($employees); ?></span> employees
        </div>
      </div>
    </div>

    <!-- Password Reset Requests Modal -->
    <div id="passwordRequestsModal" class="modal">
      <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold">Initial Password Setup (<?php echo $password_requests; ?>)</h3>
          <button onclick="closeModal('passwordRequestsModal')" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="overflow-y-auto max-h-96">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($employees as $employee): ?>
                <?php if ($employee['password_changed_at'] === null): ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($employee['id']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <img src="../assets/profile_pictures/<?php echo htmlspecialchars($employee['profile_picture']); ?>"
                          alt="Profile" class="profile-picture"
                          onerror="this.src='../assets/PROFILE_SAMPLE.jpg'">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($employee['username']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($employee['role']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Initial Setup</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                        <button type="submit" name="reset_password" class="text-blue-600 hover:text-blue-900 mr-2" title="Set Initial Password">
                          <i class="fas fa-key"></i> Set Password
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="mt-4 flex justify-end">
          <button onclick="closeModal('passwordRequestsModal')" class="px-4 py-2 bg-gray-200 rounded-md mr-2">Close</button>
        </div>
      </div>
    </div>

    <!-- Add Employee Modal -->
    <div id="addEmployeeModal" class="modal">
      <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold">Add New Employee</h3>
          <button onclick="closeModal('addEmployeeModal')" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="addEmployeeForm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Employee ID *</label>
              <input type="number" name="id" required min="1" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
              <p class="text-xs text-gray-500 mt-1">Must be a positive number</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Username *</label>
              <input type="text" name="username" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Initial Password *</label>
              <input type="text" name="password" required minlength="6" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
              <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Role *</label>
              <select name="role" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Role</option>
                <option value="Admin">Admin</option>
                <option value="Operation">Operation</option>
                <option value="Admin Executive">Admin Executive</option>
                <option value="HOA">HOA</option>
                <option value="Enumerator">Enumerator</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Email</label>
              <input type="email" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Phone</label>
              <input type="text" name="phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
          </div>
          
          <div class="mt-6">
            <p class="text-sm text-gray-500">* Required fields</p>
          </div>
          
          <div class="mt-6 flex justify-end">
            <button type="button" onclick="closeModal('addEmployeeModal')" class="px-4 py-2 bg-gray-200 rounded-md mr-2">Cancel</button>
            <button type="submit" name="add_employee" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Add Employee</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editEmployeeModal" class="modal">
      <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold">Edit Employee</h3>
          <button onclick="closeModal('editEmployeeModal')" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="editEmployeeForm">
          <input type="hidden" name="id" id="edit_id">
          <input type="hidden" name="update_employee" value="1">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Username *</label>
              <input type="text" name="username" id="edit_username" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Role *</label>
              <select name="role" id="edit_role" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Role</option>
                <option value="Admin">Admin</option>
                <option value="Operation">Operation</option>
                <option value="Admin Executive">Admin Executive</option>
                <option value="HOA">HOA</option>
                <option value="Enumerator">Enumerator</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Email</label>
              <input type="email" name="email" id="edit_email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Phone</label>
              <input type="text" name="phone" id="edit_phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
          </div>
          
          <div class="mt-6 flex justify-end">
            <button type="button" onclick="closeModal('editEmployeeModal')" class="px-4 py-2 bg-gray-200 rounded-md mr-2">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="modal">
      <div class="modal-content confirmation-modal">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-red-600">Confirm Delete</h3>
          <button onclick="closeModal('deleteConfirmationModal')" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="mb-6">
          <p class="text-gray-700">Are you sure you want to delete employee <span id="deleteEmployeeName" class="font-semibold"></span>?</p>
          <p class="text-sm text-red-600 mt-2">This action cannot be undone. All associated data will be permanently removed.</p>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="deleteEmployeeForm">
          <input type="hidden" name="employee_id" id="delete_employee_id">
          <input type="hidden" name="delete_employee" value="1">
          <div class="flex justify-end gap-2">
            <button type="button" onclick="closeModal('deleteConfirmationModal')" class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete Employee</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Employee Management Functions
    function showPasswordRequests() {
      document.getElementById('passwordRequestsModal').style.display = 'block';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    function viewEmployeeDetails(employeeId) {
      window.location.href = "<?php echo $_SERVER['PHP_SELF']; ?>?view_employee=" + employeeId;
    }

    function editEmployee(employeeId) {
      fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?get_employee=${employeeId}`)
        .then(response => response.json())
        .then(data => {
          if (data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_email').value = data.email || '';
            document.getElementById('edit_phone').value = data.phone || '';
            document.getElementById('editEmployeeModal').style.display = 'block';
          } else {
            alert('Employee not found');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error loading employee data');
        });
    }

    function confirmDeleteEmployee(employeeId, employeeName) {
      document.getElementById('delete_employee_id').value = employeeId;
      document.getElementById('deleteEmployeeName').textContent = employeeName;
      document.getElementById('deleteConfirmationModal').style.display = 'block';
    }

    function exportEmployeeData() {
      // Create a simple CSV export
      let csv = 'ID,Username,Role,Email,Phone,Created At,Status\n';
      <?php foreach ($employees as $employee): ?>
        csv += '<?php echo $employee['id']; ?>,';
        csv += '"<?php echo $employee['username']; ?>",';
        csv += '"<?php echo $employee['role']; ?>",';
        csv += '"<?php echo $employee['email'] ?? ''; ?>",';
        csv += '"<?php echo $employee['phone'] ?? ''; ?>",';
        csv += '"<?php echo $employee['created_at']; ?>",';
        csv += '"<?php echo $employee['password_changed_at'] === null ? 'Initial Setup' : 'Active'; ?>"\n';
      <?php endforeach; ?>
      
      // Download the CSV file
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.setAttribute('hidden', '');
      a.setAttribute('href', url);
      a.setAttribute('download', 'employees_export.csv');
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    function showAddEmployeeModal() {
      document.getElementById('addEmployeeForm').reset();
      document.getElementById('addEmployeeModal').style.display = 'block';
    }

    // Add form validation
    document.getElementById('addEmployeeForm')?.addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]').value;
        
        if (password.length < 6) {
            alert('Password must be at least 6 characters long');
            e.preventDefault();
            return false;
        }
        
        return true;
    });

    // Improved dropdown functionality
    function initializeDropdowns() {
      const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
      
      dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const targetId = this.getAttribute('data-target');
          const targetMenu = document.getElementById(targetId);
          const isOpen = targetMenu.classList.contains('open');
          
          // Close all other dropdowns
          document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu.id !== targetId) {
              menu.classList.remove('open');
            }
          });
          document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            if (toggle !== this) {
              toggle.classList.remove('open');
            }
          });
          
          // Toggle current dropdown
          if (!isOpen) {
            targetMenu.classList.add('open');
            this.classList.add('open');
          } else {
            targetMenu.classList.remove('open');
            this.classList.remove('open');
          }
        });
      });
      
      // Close dropdowns when clicking outside
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-item')) {
          document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('open');
          });
          document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.classList.remove('open');
          });
        }
      });
      
      // Handle touch events for mobile
      if ('ontouchstart' in window) {
        dropdownToggles.forEach(toggle => {
          toggle.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
              const targetId = this.getAttribute('data-target');
              const targetMenu = document.getElementById(targetId);
              const isOpen = targetMenu.classList.contains('open');
              
              if (!isOpen) {
                // Close all other dropdowns first
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                  menu.classList.remove('open');
                });
                document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                  toggle.classList.remove('open');
                });
                
                // Open this one
                targetMenu.classList.add('open');
                this.classList.add('open');
              }
            }
          });
        });
      }
    }

    // Mobile sidebar functionality
    function initializeMobileSidebar() {
      const sidebar = document.querySelector('.w-64');
      const mainContent = document.querySelector('.flex-1');
      const toggleButton = document.getElementById('mobileSidebarToggle');
      
      if (toggleButton && window.innerWidth <= 768) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        toggleButton.addEventListener('click', function() {
          sidebar.classList.toggle('mobile-open');
          overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', function() {
          sidebar.classList.remove('mobile-open');
          overlay.classList.remove('active');
        });
        
        // Close sidebar when clicking a link
        document.querySelectorAll('.sidebar-link, .submenu-link').forEach(link => {
          link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
              sidebar.classList.remove('mobile-open');
              overlay.classList.remove('active');
            }
          });
        });
      }
    }

    // Initialize dropdowns when page loads
    document.addEventListener('DOMContentLoaded', function() {
      initializeDropdowns();
      initializePasswordValidation();
      initializeMobileSidebar();
    });

    // Close modal when clicking outside of it
    window.onclick = function(event) {
      if (event.target.className === 'modal') {
        event.target.style.display = 'none';
      }
    }

    // Auto-hide notifications after 3 seconds
    setTimeout(() => {
      const notifications = document.querySelectorAll('.notification');
      notifications.forEach(notification => {
        if (notification) {
          notification.style.display = 'none';
        }
      });
    }, 3000);
    
    // Toggle password visibility for employee password requests
    function togglePasswordVisibility(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            button.innerHTML = '<i class="fas fa-eye"></i> Show';
        }
    }
    
    // Add form validation with password strength meter
    function initializePasswordValidation() {
        const passwordInput = document.querySelector('input[name="password"]');
        if (passwordInput) {
            const passwordContainer = passwordInput.parentElement;
            
            // Add password strength meter
            const strengthMeter = document.createElement('div');
            strengthMeter.innerHTML = `
                <div class="mt-1">
                    <div class="flex justify-between text-xs mb-1">
                        <span>Password Strength:</span>
                        <span id="strengthText" class="font-medium">Very Weak</span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div id="strengthBar" class="h-full w-0 bg-red-500 transition-all duration-300"></div>
                    </div>
                    <div class="mt-2 text-xs text-gray-600">
                        <div class="flex items-center mb-1">
                            <span id="lengthCheck" class="text-red-500 mr-2">✗</span>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="flex items-center mb-1">
                            <span id="upperCheck" class="text-red-500 mr-2">✗</span>
                            <span>At least one uppercase letter</span>
                        </div>
                        <div class="flex items-center mb-1">
                            <span id="lowerCheck" class="text-red-500 mr-2">✗</span>
                            <span>At least one lowercase letter</span>
                        </div>
                        <div class="flex items-center mb-1">
                            <span id="numberCheck" class="text-red-500 mr-2">✗</span>
                            <span>At least one number</span>
                        </div>
                        <div class="flex items-center">
                            <span id="specialCheck" class="text-red-500 mr-2">✗</span>
                            <span>At least one special character (!@#$%^&* etc.)</span>
                        </div>
                    </div>
                </div>
            `;
            passwordContainer.appendChild(strengthMeter);
            
            passwordInput.addEventListener('input', function() {
                validatePasswordStrength(this.value);
            });
        }
    }

    function validatePasswordStrength(password) {
        let strength = 0;
        const checks = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
        };
        
        // Update checkmarks
        Object.keys(checks).forEach(key => {
            const checkElement = document.getElementById(key + 'Check');
            if (checkElement) {
                if (checks[key]) {
                    checkElement.textContent = '✓';
                    checkElement.className = 'text-green-500 mr-2';
                    strength++;
                } else {
                    checkElement.textContent = '✗';
                    checkElement.className = 'text-red-500 mr-2';
                }
            }
        });
        
        // Update strength bar
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const percentage = (strength / 5) * 100;
        
        if (strengthBar) {
            strengthBar.style.width = percentage + '%';
            
            // Set color based on strength
            if (strength <= 1) {
                strengthBar.style.backgroundColor = '#ef4444'; // red
                strengthText.textContent = 'Very Weak';
            } else if (strength <= 2) {
                strengthBar.style.backgroundColor = '#f59e0b'; // yellow
                strengthText.textContent = 'Weak';
            } else if (strength <= 3) {
                strengthBar.style.backgroundColor = '#3b82f6'; // blue
                strengthText.textContent = 'Fair';
            } else if (strength === 4) {
                strengthBar.style.backgroundColor = '#10b981'; // green
                strengthText.textContent = 'Good';
            } else {
                strengthBar.style.backgroundColor = '#059669'; // dark green
                strengthText.textContent = 'Strong';
            }
        }
    }

    // Update form validation
    document.getElementById('addEmployeeForm')?.addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]')?.value || '';
        
        // Validate password strength
        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
        
        if (!hasLength || !hasUpper || !hasLower || !hasNumber || !hasSpecial) {
            alert('Password must meet all requirements:\n' +
                  '• At least 8 characters\n' +
                  '• At least one uppercase letter\n' +
                  '• At least one lowercase letter\n' +
                  '• At least one number\n' +
                  '• At least one special character (!@#$%^&* etc.)');
            e.preventDefault();
            return false;
        }
        
        return true;
    });

    // Handle window resize for mobile sidebar
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768) {
        const sidebar = document.querySelector('.w-64');
        const overlay = document.querySelector('.sidebar-overlay');
        if (sidebar) {
          sidebar.classList.remove('mobile-open');
        }
        if (overlay) {
          overlay.classList.remove('active');
        }
      }
    });
  </script>
</body>
</html>