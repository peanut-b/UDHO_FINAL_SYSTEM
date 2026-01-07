<?php
session_start();

/* ========== DATABASE CONNECTION ========== */
$servername = "localhost";
$username   = "u198271324_admin";
$password   = "Udhodbms01";
$dbname     = "u198271324_udho_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// Check if we need to process a password approval/rejection
if (isset($_POST['approve_password_request'])) {
    $requestId = $_POST['request_id'] ?? 0;
    $action = $_POST['action'] ?? ''; // 'approve' or 'reject'
    $adminId = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';
    
    // Debug log
    error_log("Processing password request: ID=$requestId, Action=$action, AdminID=$adminId, Role=$role");
    
    // Only allow Admin or Admin Executive to process requests
    if ($role === 'Admin' || $role === 'Admin Executive') {
        // Get the request details
        $stmt = $conn->prepare("SELECT user_id, hashed_password, new_password FROM password_change_requests WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        
        if ($request) {
            if ($action === 'approve') {
                // Update user's password with the hashed version
                $updateUser = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
                $updateUser->bind_param("si", $request['hashed_password'], $request['user_id']);
                if ($updateUser->execute()) {
                    // Create notification for user
                    $message = "Your password has been changed. Your new password is: " . $request['new_password'];
                    $notifStmt = $conn->prepare("INSERT INTO user_notifications (user_id, type, message, created_at) VALUES (?, 'password_approved', ?, NOW())");
                    $notifStmt->bind_param("is", $request['user_id'], $message);
                    $notifStmt->execute();
                    $notifStmt->close();
                    
                    // Mark request as approved
                    $updateRequest = $conn->prepare("UPDATE password_change_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $updateRequest->bind_param("ii", $adminId, $requestId);
                    $updateRequest->execute();
                    $updateRequest->close();
                    
                    $_SESSION['approval_success'] = "Password request approved successfully.";
                    error_log("Password approved for user ID: " . $request['user_id']);
                } else {
                    $_SESSION['approval_success'] = "Error updating password: " . $conn->error;
                    error_log("Error updating password: " . $conn->error);
                }
                $updateUser->close();
            } else if ($action === 'reject') {
                // Reject action
                $rejectReason = $_POST['reject_reason'] ?? 'No reason provided';
                $message = "Your password change request was rejected. Reason: " . $rejectReason;
                $notifStmt = $conn->prepare("INSERT INTO user_notifications (user_id, type, message, created_at) VALUES (?, 'password_rejected', ?, NOW())");
                $notifStmt->bind_param("is", $request['user_id'], $message);
                $notifStmt->execute();
                $notifStmt->close();
                
                // Mark request as rejected
                $updateRequest = $conn->prepare("UPDATE password_change_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $updateRequest->bind_param("ii", $adminId, $requestId);
                $updateRequest->execute();
                $updateRequest->close();
                
                $_SESSION['approval_success'] = "Password request rejected.";
                error_log("Password rejected for user ID: " . $request['user_id']);
            }
        } else {
            $_SESSION['approval_success'] = "Request not found or already processed.";
            error_log("Request not found: ID=$requestId");
        }
        
        $stmt->close();
        // Redirect to avoid form resubmission
        header("Location: settings.php");
        exit;
    }
}

/* ========== HANDLE UPLOAD NG PROFILE PICTURE ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $uploadDir = __DIR__ . '/assets/profile_pictures/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext     = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];

    if (!in_array($ext, $allowed)) {
        $error = 'Invalid file type';
    } else {
        $filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
        $target   = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
            $stmt = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
            $stmt->bind_param("si", $filename, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();

            $_SESSION['profile_picture'] = $filename;
            $success = 'Profile picture updated!';
        } else {
            $error = 'Upload failed';
        }
    }
}

// Check for unread notifications
$has_unread_notifications = false;
$pending_notification = null;
$userId = $_SESSION['user_id'] ?? 0;

if ($userId) {
    // Check for unread password notifications
    $stmt = $conn->prepare("SELECT id, type, message FROM user_notifications WHERE user_id = ? AND is_read = 0 AND type IN ('password_approved', 'password_rejected') ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $has_unread_notifications = true;
        $pending_notification = $result->fetch_assoc();
        
        // MARK as read immediately when fetched to prevent showing again on refresh
        $updateStmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $pending_notification['id']);
        $updateStmt->execute();
        $updateStmt->close();
    }
    $stmt->close();
}

$role = $_SESSION['role'] ?? 'Guest';
$profilePicture = 'default_profile.jpg';
$user = [
    'username' => '',
    'email' => '',
    'phone' => ''
];

if ($userId) {
    $stmt = $conn->prepare("SELECT username, email, phone, profile_picture FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user['username'] = $row['username'];
        $user['email']    = $row['email'];
        $user['phone']    = $row['phone'];

        if (!empty($row['profile_picture'])) {
            $profilePicture = $row['profile_picture'];
        } else {
            $profilePicture = 'default_profile.jpg';
        }

        $_SESSION['profile_picture'] = $profilePicture;
    }
    $stmt->close();
}

// Fetch password change requests for current user
$password_requests_list = [];
if ($userId) {
    $stmt = $conn->prepare("SELECT id, status, reason, created_at FROM password_change_requests WHERE user_id=? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $password_requests_list[] = $row;
    }
    $stmt->close();
}

// Handle notification dismissal
if (isset($_POST['dismiss_notification']) && $userId) {
    $notification_id = $_POST['notification_id'];
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $userId);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid form resubmission
    header("Location: settings.php");
    exit();
}

// Fetch all pending requests for admin view
$pending_requests = [];
if ($role === 'Admin' || $role === 'Admin Executive') {
    $stmt = $conn->prepare("SELECT pcr.id, pcr.user_id, pcr.reason, pcr.created_at, pcr.new_password, u.username, u.email 
                           FROM password_change_requests pcr
                           JOIN users u ON pcr.user_id = u.id
                           WHERE pcr.status = 'pending'
                           ORDER BY pcr.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $pending_requests[] = $row;
    }
    $stmt->close();
}

$conn->close();

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($role); ?> Panel - Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
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
    
    .password-strength {
      height: 4px;
      border-radius: 2px;
      margin-top: 4px;
      transition: all 0.3s ease;
    }
    .strength-weak { background-color: #ef4444; width: 25%; }
    .strength-fair { background-color: #f59e0b; width: 50%; }
    .strength-good { background-color: #3b82f6; width: 75%; }
    .strength-strong { background-color: #10b981; width: 100%; }
    .notification-modal {
      animation: slideInDown 0.5s ease-out;
    }
    @keyframes slideInDown {
      from {
        transform: translateY(-100%);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
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
    <!-- ADMIN EXECUTIVE SIDEBAR -->
    <?php if ($role === "Admin Executive"): ?>
    <div class="sidebar-fixed bg-gray-800 text-white flex flex-col">
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
            <a href="/Admin executive/adminexecutive_dashboard.php" class="sidebar-link flex items-center py-3 px-4 <?php echo $currentPage=='adminexecutive_dashboard.php'?'active-link':''; ?>">
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
            <a href="/Admin executive/backup.php" class="sidebar-link flex items-center py-3 px-4 <?php echo $currentPage=='backup.php'?'active-link':''; ?>">
              <i class="fas fa-database mr-3"></i> Backup Data
            </a>
          </li>
          <li>
            <a href="/Admin executive/employee.php" class="sidebar-link flex items-center py-3 px-4 <?php echo $currentPage=='employee.php'?'active-link':''; ?>">
              <i class="fas fa-users mr-3"></i> Employees
            </a>
          </li>
          <li>
            <li>
          <a href="../settings.php" class="sidebar-link flex items-center py-3 px-4">
            <i class="fas fa-cog mr-3"></i> Settings
          </a>
        </li>
          </li>
          <li class="mt-auto">
            <a href="../logout.php" class="sidebar-link flex items-center py-3 px-4">
              <i class="fas fa-sign-out-alt mr-3"></i> Logout
            </a>
          </li>
        </ul>
      </nav>
    </div>
    <?php else: ?>
      <!-- ORIGINAL SIDEBAR FOR OTHER ROLES -->
      <div class="sidebar-fixed bg-gray-800 text-white flex flex-col">
        <!-- Profile Section -->
        <div class="flex items-center justify-center h-24 border-b border-gray-700">
          <div class="rounded-full bg-gray-200 w-20 h-20 flex items-center justify-center overflow-hidden border-2 border-white shadow-md">
            <img src="/assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>"
                 alt="Profile Picture"
                 class="w-full h-full object-cover"
                 onerror="this.src='/assets/DEFAULT_PROFILE.jpg'">
          </div>
        </div>
        
        <!-- Username Info -->
        <div class="px-4 py-2 text-center text-sm text-gray-300">
          Logged in as: <br>
          <span class="font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
        </div>

        <!-- Navigation -->
        <nav class="mt-6 flex-1 px-3 overflow-y-auto">
          <ul class="space-y-1">
            <?php if ($role === "Operation"): ?>
              <li>
                <a href="/Operation/operation_dashboard.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center <?php echo $currentPage==='operation_dashboard'?'active-link':''; ?>">
                  <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
              </li>
              <li>
                <a href="/Operation/operation_IDSAP.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fa-solid fa-database mr-3"></i> IDSAP Database
                </a>
              </li>
              <li>
                <a href="/Operation/operation_panel.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fa-solid fa-clipboard-list mr-3"></i> PDC Cases
                </a>
              </li>
              <li>
                <a href="/Operation/meralco.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fa-solid fa-bolt mr-3"></i> Meralco Certificates
                </a>
              </li>
              <li>
                <a href="/Operation/meralco_database.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fa-solid fa-bolt mr-3"></i> Meralco Database
                </a>
              </li>
            <?php endif; ?>

            <?php if ($role === "Admin"): ?>
              <li>
                <a href="/Admin/admin_dashboard.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
              </li>
            <?php endif; ?>

            <?php if ($role === "HOA"): ?>
              <li>
                <a href="/HOA/hoa_dashboard.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
              </li>
              <li>
                <a href="/HOA/hoa_records.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fa-solid fa-folder-open mr-3"></i> HOA Management
                </a>
              </li>
              <li>
                <a href="/HOA/hoa_payment.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center">
                  <i class="fa-solid fa-credit-card mr-3"></i> Payment Records
                </a>
              </li>
            <?php endif; ?>

            <!-- Common Links -->
            <li>
              <a href="../settings.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center <?php echo $currentPage==='settings.php'?'active-link':''; ?>">
                <i class="fas fa-cog mr-3"></i> Settings
              </a>
            </li>
            <li class="mt-auto">
              <a href="../logout.php" class="block py-2.5 px-4 hover:bg-gray-700 flex items-center ">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
              </a>
            </li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>

    <!-- Scrollable Main Content -->
    <div class="main-content-scrollable">
      <div class="p-6">
        <!-- Password Change Notification Modal -->
        <?php if ($has_unread_notifications && $pending_notification): ?>
        <div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 pt-10 notification-modal">
          <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="p-6">
              <?php if ($pending_notification['type'] === 'password_approved'): ?>
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full">
                  <i class="fas fa-check text-green-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 text-center mt-4">Password Change Approved!</h3>
                <div class="mt-4 p-4 bg-green-50 rounded-md">
                  <p class="text-green-800 text-sm"><?php echo htmlspecialchars($pending_notification['message']); ?></p>
                </div>
                <p class="text-sm text-gray-600 text-center mt-4">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  Please save this password securely. You won't be able to see it again.
                </p>
              <?php else: ?>
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                  <i class="fas fa-times text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 text-center mt-4">Password Change Rejected</h3>
                <div class="mt-4 p-4 bg-red-50 rounded-md">
                  <p class="text-red-800 text-sm"><?php echo htmlspecialchars($pending_notification['message']); ?></p>
                </div>
                <p class="text-sm text-gray-600 text-center mt-4">
                  You can submit a new password change request if needed.
                </p>
              <?php endif; ?>
              
              <form method="POST" class="mt-6">
                <input type="hidden" name="notification_id" value="<?php echo $pending_notification['id']; ?>">
                <button type="submit" name="dismiss_notification" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-md transition">
                  I Understand
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <header class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
          <h1 class="text-2xl font-bold text-gray-800">Account Settings (<?php echo htmlspecialchars($role); ?>)</h1>
          <div class="flex items-center gap-2">
            <img src="/assets/UDHOLOGO.png" alt="Logo" class="h-8">
            <span class="font-medium text-gray-700">Urban Development and Housing Office</span>
          </div>
        </header>

        <?php if(!empty($error)): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['approval_success'])): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php 
            echo htmlspecialchars($_SESSION['approval_success']); 
            unset($_SESSION['approval_success']);
            ?>
          </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md">
          <!-- Profile Picture Form (separate form for file upload) -->
          <form action="settings.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Profile Picture -->
            <div class="flex flex-col items-center">
              <div class="w-24 h-24 rounded-full overflow-hidden border mb-3">
                <img id="profileImage"
                     src="/assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>"
                     alt="Profile"
                     class="w-full h-full object-cover"
                     onerror="this.src='/assets/DEFAULT_PROFILE.jpg'">
              </div>
              <input type="file" name="profile_picture" id="profileUpload" accept="image/*" class="hidden" />
              <button type="button"
                      onclick="document.getElementById('profileUpload').click()"
                      class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md">
                <i class="fas fa-camera mr-2"></i> Change Photo
              </button>
              <p class="text-sm text-gray-500 mt-2">JPG, GIF, PNG. Max 2MB</p>
            </div>
            
            <div class="flex justify-end gap-2">
              <button type="submit" name="update_profile"
                      class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                <i class="fas fa-save mr-2"></i> Save Profile Picture
              </button>
            </div>
          </form>
          
          <div class="border-t my-6"></div>
          
          <!-- Account Settings Form -->
          <form id="settingsForm" class="space-y-6">
            <!-- Personal Info -->
            <div>
              <label class="block mb-1 font-medium">Username</label>
              <input type="text" 
                     value="<?php echo htmlspecialchars($user['username']); ?>"
                     class="w-full px-3 py-2 border border-gray-300 rounded-md"
                     disabled />
            </div>

            <div>
              <label class="block mb-1 font-medium">Email Address</label>
              <input type="email" 
                     name="email"
                     value="<?php echo htmlspecialchars($user['email']); ?>"
                     class="w-full px-3 py-2 border border-gray-300 rounded-md"
                     required />
            </div>

            <div>
              <label class="block mb-1 font-medium">Phone Number</label>
              <input type="tel" 
                     name="phone"
                     value="<?php echo htmlspecialchars($user['phone']); ?>"
                     class="w-full px-3 py-2 border border-gray-300 rounded-md"
                     required />
            </div>

            <div>
              <label class="block mb-1 font-medium">Role</label>
              <input type="text" 
                     value="<?php echo htmlspecialchars($role); ?>" 
                     class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" 
                     disabled />
            </div>
          </form>

          <!-- Security Section -->
          <div class="pt-4 border-t mt-6">
            <h2 class="text-xl font-semibold mb-4">Security</h2>
            <div class="bg-blue-50 p-4 rounded-md mb-4">
              <p class="text-blue-800 text-sm"><i class="fas fa-info-circle mr-2"></i> Only administrators can change passwords</p>
              <p class="text-blue-800 text-sm mt-1"><i class="fas fa-lock mr-2"></i> Passwords are securely hashed in our database</p>
            </div>
            
            <!-- Password Change Request History -->
            <div class="mt-4">
              <h3 class="font-medium mb-2">Your Password Change Requests</h3>
              <?php if (count($password_requests_list) > 0): ?>
                <div class="space-y-2">
                  <?php foreach ($password_requests_list as $request): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                      <div>
                        <span class="font-medium">Request on <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></span>
                        <?php if (!empty($request['reason'])): ?>
                          <p class="text-sm text-gray-600 mt-1">Reason: <?php echo htmlspecialchars($request['reason']); ?></p>
                        <?php endif; ?>
                      </div>
                      <span class="px-3 py-1 rounded-full text-xs font-medium
                        <?php echo $request['status'] === 'approved' ? 'bg-green-100 text-green-800 border border-green-200' : 
                                ($request['status'] === 'rejected' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-yellow-100 text-yellow-800 border border-yellow-200'); ?>">
                        <?php echo ucfirst($request['status']); ?>
                      </span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-gray-500 text-sm italic">No password change requests submitted yet.</p>
              <?php endif; ?>
            </div>
            
            <button type="button" onclick="openPasswordModal()" 
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md mt-4 transition duration-200">
              <i class="fas fa-key mr-2"></i> Request Password Change
            </button>
          </div>
          
          <?php if (($role === 'Admin' || $role === 'Admin Executive') && count($pending_requests) > 0): ?>
          <!-- Admin Approval Section -->
          <div class="pt-8 border-t mt-8">
              <h2 class="text-xl font-semibold mb-4 text-red-600">
                  <i class="fas fa-user-shield mr-2"></i>Admin - Pending Password Requests
              </h2>
              
              <div class="bg-gray-50 p-4 rounded-lg border">
                  <h3 class="font-medium mb-3">Pending Approval Requests (<?php echo count($pending_requests); ?>)</h3>
                  
                  <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                      <?php foreach ($pending_requests as $request): ?>
                      <div class="bg-white p-4 rounded border shadow-sm">
                          <div class="flex justify-between items-start mb-2">
                              <div>
                                  <span class="font-medium"><?php echo htmlspecialchars($request['username']); ?></span>
                                  <span class="text-sm text-gray-600 ml-2">(<?php echo htmlspecialchars($request['email']); ?>)</span>
                              </div>
                              <span class="text-xs text-gray-500">
                                  <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?>
                              </span>
                          </div>
                          
                          <?php if (!empty($request['reason'])): ?>
                          <div class="mb-3">
                              <p class="text-sm font-medium text-gray-700">Reason:</p>
                              <p class="text-sm bg-gray-100 p-2 rounded mt-1"><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                          </div>
                          <?php endif; ?>
                          
                          <div class="mb-4">
                              <div class="flex items-center">
                                  <span class="font-medium text-sm mr-2">Requested Password:</span>
                                  <input type="password" 
                                         id="password_<?php echo $request['id']; ?>" 
                                         value="<?php echo htmlspecialchars($request['new_password']); ?>" 
                                         class="text-sm border rounded px-2 py-1 w-40" 
                                         readonly>
                                  <button type="button" 
                                          class="ml-2 text-blue-600 hover:text-blue-800 text-sm"
                                          onclick="togglePasswordVisibility('password_<?php echo $request['id']; ?>', this)">
                                      <i class="fas fa-eye"></i> Show
                                  </button>
                              </div>
                          </div>
                          
                          <div class="flex gap-2">
                              <form method="POST" class="inline">
                                  <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                  <input type="hidden" name="action" value="approve">
                                  <button type="submit" name="approve_password_request" 
                                          class="bg-green-600 hover:bg-green-700 text-white py-1 px-3 rounded text-sm flex items-center"
                                          onclick="return approvePasswordConfirm('<?php echo $request['id']; ?>', '<?php echo htmlspecialchars($request['username']); ?>', '<?php echo htmlspecialchars($request['new_password']); ?>')">
                                      <i class="fas fa-check mr-1"></i> Approve
                                  </button>
                              </form>
                              
                              <button type="button" 
                                      onclick="openRejectModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['username']); ?>')" 
                                      class="bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded text-sm flex items-center">
                                  <i class="fas fa-times mr-1"></i> Reject
                              </button>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>
              </div>
          </div>
          <?php endif; ?>
          
          <?php if (($role === 'Admin' || $role === 'Admin Executive') && count($pending_requests) === 0): ?>
          <!-- Admin Section - No Pending Requests -->
          <div class="pt-8 border-t mt-8">
              <h2 class="text-xl font-semibold mb-4 text-blue-600">
                  <i class="fas fa-user-shield mr-2"></i>Admin Controls
              </h2>
              <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                  <div class="flex items-center">
                      <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                      <div>
                          <p class="font-medium">No pending password change requests</p>
                          <p class="text-sm text-gray-600">All password requests have been processed.</p>
                      </div>
                  </div>
              </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Password Change Request Modal -->
  <div id="passwordModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-lg font-bold">Request Password Change</h3>
        <button onclick="closePasswordModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="p-4">
        <form id="passwordForm" class="space-y-4">
          <div class="bg-yellow-50 p-3 rounded-md mb-4 text-sm text-yellow-800">
            <i class="fas fa-exclamation-circle mr-2"></i>
            Password changes must be approved by an administrator. Provide current password and your desired new password.
          </div>
          
          <div>
            <label class="block mb-1 font-medium">Current Password</label>
            <input type="password" id="currentPassword"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md" required />
          </div>
          
          <div>
            <label class="block mb-1 font-medium">New Password</label>
            <input type="password" id="newPassword"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                   minlength="6"
                   required />
            <div class="password-strength" id="passwordStrength"></div>
            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
          </div>
          
          <div>
            <label class="block mb-1 font-medium">Confirm New Password</label>
            <input type="password" id="confirmPassword"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md" required />
            <p class="text-xs text-red-500 mt-1 hidden" id="passwordMatchError">Passwords do not match</p>
          </div>
          
          <div>
            <label class="block mb-1 font-medium">Reason for Change</label>
            <textarea rows="3" id="changeReason"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                   placeholder="Please explain why you need to change your password..."
                   required></textarea>
          </div>
          
          <div class="flex justify-end gap-2 pt-4">
            <button type="button" onclick="closePasswordModal()" 
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">Cancel</button>
            <button type="submit" 
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reject Modal (for Admin Executive) -->
  <div id="rejectModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-lg font-bold">Reject Password Request</h3>
        <button onclick="closeRejectModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="p-4">
        <form method="POST" id="rejectForm">
          <input type="hidden" name="request_id" id="rejectRequestId">
          <input type="hidden" name="action" value="reject">
          
          <div class="mb-4">
            <p class="text-sm text-gray-600 mb-2">Rejecting password request for: <span id="rejectUsername" class="font-medium"></span></p>
            <label class="block mb-1 font-medium">Reason for rejection:</label>
            <textarea name="reject_reason" rows="3" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                    placeholder="Please provide a reason for rejection..."
                    required></textarea>
          </div>
          
          <div class="flex justify-end gap-2">
            <button type="button" onclick="closeRejectModal()" 
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">Cancel</button>
            <button type="submit" name="approve_password_request" 
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">Reject Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const profileImage = document.getElementById('profileImage');
    const profileUpload = document.getElementById('profileUpload');
    const passwordModal = document.getElementById('passwordModal');
    const rejectModal = document.getElementById('rejectModal');
    const settingsForm = document.getElementById('settingsForm');
    const passwordForm = document.getElementById('passwordForm');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordMatchError = document.getElementById('passwordMatchError');

    profileUpload.addEventListener('change', e=>{
      if (e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = ev => { profileImage.src = ev.target.result; };
        reader.readAsDataURL(e.target.files[0]);
      }
    });

    function openPasswordModal(){ 
      passwordModal.classList.remove('hidden');
      passwordForm.reset();
      passwordStrength.className = 'password-strength';
      passwordMatchError.classList.add('hidden');
    }
    
    function closePasswordModal(){ 
      passwordModal.classList.add('hidden');
    }
    
    function openRejectModal(requestId, username){ 
      document.getElementById('rejectRequestId').value = requestId;
      document.getElementById('rejectUsername').textContent = username;
      rejectModal.classList.remove('hidden');
    }
    
    function closeRejectModal(){ 
      rejectModal.classList.add('hidden');
      document.getElementById('rejectForm').reset();
    }

    function resetForm(){ 
      settingsForm.reset();
      // Reset profile image to original
      profileImage.src = '/assets/profile_pictures/<?php echo htmlspecialchars($profilePicture); ?>';
    }

    // Password strength indicator
    newPassword.addEventListener('input', function() {
      const password = this.value;
      let strength = '';
      
      if (password.length === 0) {
        strength = '';
      } else if (password.length < 6) {
        strength = 'strength-weak';
      } else if (password.length < 8) {
        strength = 'strength-fair';
      } else if (password.length < 10) {
        strength = 'strength-good';
      } else {
        strength = 'strength-strong';
      }
      
      passwordStrength.className = 'password-strength ' + strength;
    });

    // Password confirmation check
    confirmPassword.addEventListener('input', function() {
      if (newPassword.value !== this.value) {
        passwordMatchError.classList.remove('hidden');
      } else {
        passwordMatchError.classList.add('hidden');
      }
    });

    passwordForm.addEventListener('submit', e=>{
      e.preventDefault();

      const currentPassword = document.getElementById('currentPassword').value.trim();
      const newPasswordValue = document.getElementById('newPassword').value.trim();
      const confirmPasswordValue = document.getElementById('confirmPassword').value.trim();
      const reason = document.getElementById('changeReason').value.trim();

      // Validate all fields are filled
      if (!currentPassword || !newPasswordValue || !confirmPasswordValue || !reason) {
        alert('Please fill in all fields!');
        return;
      }

      // Validate passwords match
      if (newPasswordValue !== confirmPasswordValue) {
        alert('New passwords do not match!');
        document.getElementById('confirmPassword').focus();
        return;
      }

      // Validate password length
      if (newPasswordValue.length < 6) {
        alert('New password must be at least 6 characters long!');
        document.getElementById('newPassword').focus();
        return;
      }

      // Check if new password is same as current
      if (newPasswordValue === currentPassword) {
        alert('New password cannot be the same as current password!');
        return;
      }

      // Show loading state
      const submitBtn = passwordForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
      submitBtn.disabled = true;

      fetch('submit_password_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          currentPassword: currentPassword,
          newPassword: newPasswordValue,
          reason: reason
        })
      })
      .then(res => {
        if (!res.ok) {
          throw new Error('Network response was not ok');
        }
        return res.json();
      })
      .then(data => {
        if(data.success){
          // Show success message
          alert('✓ Password change request submitted successfully!\n\nAn administrator will review your request. You will receive a notification once it\'s approved or rejected.');
          closePasswordModal();
          // Reload page to show updated request list
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        } else {
          alert('✗ Error: ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('✗ Something went wrong. Please check your connection and try again.');
      })
      .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
    });

    // Auto-close notification modal after 30 seconds (safety measure)
    setTimeout(() => {
      const notificationModal = document.getElementById('notificationModal');
      if (notificationModal) {
        const form = notificationModal.querySelector('form');
        if (form) {
          form.submit();
        }
      }
    }, 30000);

    // Dropdown functionality for Admin Executive sidebar
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

    // Initialize dropdowns if Admin Executive
    <?php if ($role === "Admin Executive"): ?>
    document.addEventListener('DOMContentLoaded', function() {
      initializeDropdowns();
    });
    <?php endif; ?>
    
    // Toggle password visibility for admin
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
    
    // Custom confirmation for password approval
    function approvePasswordConfirm(requestId, username, newPassword) {
        const passwordField = document.getElementById('password_' + requestId);
        const showPassword = passwordField.type === 'text';
        
        let message = `Approve password change for ${username}?\n\n`;
        message += `New Password: ${showPassword ? newPassword : '********'}\n\n`;
        message += `IMPORTANT: The user will need to use this password to login.`;
        
        return confirm(message);
    }
    
    // Close reject modal when clicking outside
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
    
    // Close password modal when clicking outside
    document.getElementById('passwordModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePasswordModal();
        }
    });
    
  </script>
</body>
</html>