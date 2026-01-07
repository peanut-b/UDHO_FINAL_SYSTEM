<?php
session_start();

// Show success message if password was reset
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u198271324_admin');
define('DB_PASS', 'Udhodbms01');
define('DB_NAME', 'u198271324_udho_db');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to establish database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $rememberMe = isset($_POST['remember-me']);
    
    // Validate inputs
    if (empty($username) || empty($password) || empty($role)) {
        $error = "Please fill in all fields";
    } else {
        // Authenticate user
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT id, username, password, role, profile_picture FROM users WHERE username = ? AND role = ?");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // ==================== SECURE PASSWORD VERIFICATION ==================== //
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_picture'] = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default_profile.jpg';
                $_SESSION['logged_in'] = true;

                // Remember me cookie - Generate secure token
                if ($rememberMe) {
                    $token = bin2hex(random_bytes(32)); // Secure random token
                    $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                    
                    // Store token in database
                    $update_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $hashed_token, $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Set cookie with user_id and plain token
                    $cookie_value = base64_encode($user['id'] . ':' . $token);
                    setcookie('remember_me', $cookie_value, time() + (86400 * 30), "/", "", true, true); // Secure and HttpOnly
                }
                // ==================== END SECURE PASSWORD VERIFICATION ==================== //
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'Admin':
                        header("Location: Admin/admin_dashboard.php");
                        break;
                    case 'Operation':
                        header("Location: Operation/operation_dashboard.php");
                        break;
                    case 'Admin Executive':
                        header("Location: Admin executive/adminexecutive_dashboard.php");
                        break;
                    case 'HOA':
                        header("Location: HOA/hoa_dashboard.php");
                        break;
                    case 'Enumerator':
                        header("Location: Operation/survey.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password"; // Don't reveal if user exists
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Check for remember me cookie (IMPROVED SECURITY)
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_me'])) {
    $cookie_data = base64_decode($_COOKIE['remember_me']);
    $parts = explode(':', $cookie_data);
    
    if (count($parts) === 2) {
        list($user_id, $token) = $parts;
        
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, role, remember_token FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify the remember token
            if (!empty($user['remember_token']) && password_verify($token, $user['remember_token'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'Admin':
                        header("Location: Admin/admin_dashboard.php");
                        break;
                    case 'Operation':
                        header("Location: Operation/operation_dashboard.php");
                        break;
                    case 'Admin Executive':
                        header("Location: Admin executive/adminexecutive_dashboard.php");
                        break;
                    case 'HOA':
                        header("Location: HOA/hoa_dashboard.php");
                        break;
                    case 'Enumerator':
                        header("Location: Operation/survey.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit();
            }
        }
        
        $stmt->close();
        $conn->close();
    }
    
    // If token is invalid, delete the cookie
    setcookie('remember_me', '', time() - 3600, "/");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UDHO Login</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
     /* Background Image Styles */
    body {
      background-image: url('assets/BG_LOGIN.png');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
    }
    .login-container {
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    }
    .form-input:focus {
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
    }
    .survey-btn {
      transition: all 0.3s ease;
    }
    .survey-btn:hover {
      transform: translateY(-2px);
    }
    .logo-box {
      background: white;
      border-radius: 1rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.0);
      padding: 2rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    /* Loader Styles */
    .loader {
      width: 50px;
      aspect-ratio: 1;
      border-radius: 50%;
      border: 8px solid lightblue;
      border-right-color: orange;
      animation: l2 1s infinite linear;
    }
    .loader:before,
    .loader:after {
      content: "";
      width: 50%;
      background: #514b82;
      clip-path: polygon(0 0,100% 50%,0% 100%);
      animation: inherit;
      animation-name: l10-1;
      transform-origin: bottom left;
    }
    .loader:before {
      clip-path: polygon(0 50%,100% 0,100% 100%);
      transform-origin: bottom right;
      --s:-1;
    }
    @keyframes l10-0 {
      0%,34.99% {transform: scaley(1)}
      35%,70%   {transform: scaley(-1)}
      90%,100%  {transform: scaley(-1) rotate(180deg)}
    }
    @keyframes l10-1 {
      0%,10%,70%,100%{transform:translateY(-100%) rotate(calc(var(--s,1)*135deg))}
      35%            {transform:translateY(0%)    rotate(0deg)}
    }
    @keyframes l2 {
      to {
        transform: rotate(1turn);
      }
    }

    /* Full page loader overlay */
    .loader-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(255, 255, 255, 0.8);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    
    /* Password strength indicator */
    .password-strength {
      height: 4px;
      border-radius: 2px;
      margin-top: 5px;
      transition: width 0.3s ease;
    }
    .strength-0 { width: 20%; background: #dc3545; }
    .strength-1 { width: 40%; background: #fd7e14; }
    .strength-2 { width: 60%; background: #ffc107; }
    .strength-3 { width: 80%; background: #17a2b8; }
    .strength-4 { width: 100%; background: #28a745; }
  </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <!-- Page Loader -->
  <div id="pageLoader" class="loader-overlay">
    <div class="loader"></div>
  </div>

  <!-- Main Content -->
  <div class="flex w-full max-w-4xl bg-white rounded-xl shadow-xl overflow-hidden">
    <!-- Logo Box on Left -->
    <div class="w-1/2 p-8 flex items-center justify-center">
      <div class="logo-box">
        <img src="assets/bg_1.png" alt="UDHO Logo" class="h-100 w-100 object-cover">
      </div>
    </div>
    
    <!-- Login Form on Right -->
    <div class="w-1/2 p-8">
      <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Welcome Back</h2>
      <p class="text-gray-600 text-center mb-8">Please login to your account</p>
      
      <?php if (isset($error)): ?>
        <div id="errorMessage" class="text-center text-sm text-red-600 mb-4">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      <?php if (isset($success_message)): ?>
    <div id="successMessage" class="text-center text-sm text-green-600 mb-4">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>
      
      <form id="loginForm" method="POST" class="space-y-4">
        <div>
          <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-user text-gray-400"></i>
            </div>
            <input type="text" id="username" name="username" required
                   class="pl-10 form-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2 px-4 border"
                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
          </div>
        </div>
        
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-400"></i>
            </div>
            <input type="password" id="password" name="password" required
                   class="pl-10 pr-10 form-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2 px-4 border">
            
            <!-- Eye Icon Button -->
            <button type="button" id="togglePassword" 
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        
        <div>
          <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
          <select id="role" name="role" required
                  class="form-select block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2 px-4 border">
            <option value="" disabled selected>Select your role</option>
            <option value="Admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Admin') ? 'selected' : ''; ?>>Admin</option>
            <option value="Operation" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Operation') ? 'selected' : ''; ?>>Operation</option>
            <option value="Admin Executive" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Admin Executive') ? 'selected' : ''; ?>>Admin Executive</option>
            <option value="HOA" <?php echo (isset($_POST['role']) && $_POST['role'] === 'HOA') ? 'selected' : ''; ?>>HOA</option>
            <option value="Enumerator" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Enumerator') ? 'selected' : ''; ?>>Enumerator</option>
          </select>
        </div>
        
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input id="remember-me" name="remember-me" type="checkbox"
                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                   <?php echo (isset($_POST['remember-me']) && $_POST['remember-me'] === 'on') ? 'checked' : ''; ?>>
            <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
          </div>
          <div class="text-sm">
            <a href="forgot_password.php" class="font-medium text-indigo-600 hover:text-indigo-500">Forgot password?</a>
          </div>
        </div>
        
        <div>
          <button type="submit" id="loginButton"
                  class="w-full flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <span id="buttonText">Sign in</span>
            <span id="spinner" class="ml-2 hidden">
              <div class="loader" style="width: 20px; height: 20px;"></div>
            </span>
          </button>
        </div>
      </form>
      
      <!-- Security Information -->
      <div class="mt-6 p-3 bg-blue-50 rounded-lg">
        <div class="flex items-center text-sm text-blue-700">
          <i class="fas fa-shield-alt mr-2"></i>
          <span>Your login is secured with password hashing and encryption</span>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Hide page loader when DOM is fully loaded
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => {
        document.getElementById('pageLoader').style.display = 'none';
      }, 1000);
    });

    // Handle login form submission
    document.getElementById('loginForm').addEventListener('submit', (e) => {
      // Client-side validation
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      const role = document.getElementById('role').value;
      
      if (!username || !password || !role) {
        e.preventDefault();
        showError('Please fill in all fields');
        return;
      }
      
      // Show loading state
      const loginButton = document.getElementById('loginButton');
      const buttonText = document.getElementById('buttonText');
      const spinner = document.getElementById('spinner');
      
      loginButton.disabled = true;
      buttonText.textContent = 'Signing in...';
      spinner.classList.remove('hidden');
    });

    // Show error message
    function showError(message) {
      const errorElement = document.createElement('div');
      errorElement.id = 'errorMessage';
      errorElement.className = 'text-center text-sm text-red-600 mt-2';
      errorElement.textContent = message;
      
      const form = document.getElementById('loginForm');
      const existingError = document.getElementById('errorMessage');
      
      if (existingError) {
        existingError.remove();
      }
      
      form.insertBefore(errorElement, form.firstChild);
      
      // Hide error after 5 seconds
      setTimeout(() => {
        errorElement.remove();
      }, 5000);
    }
    
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function () {
      const passwordField = document.getElementById('password');
      const icon = this.querySelector('i');
  
      if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        passwordField.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    });
    
    // Security: Prevent form re-submission on refresh
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
  </script>
</body>
</html>