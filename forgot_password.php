<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u198271324_admin');
define('DB_PASS', 'Udhodbms01');
define('DB_NAME', 'u198271324_udho_db');

// Email configuration - UPDATE THESE WITH YOUR DETAILS
define('SMTP_HOST', 'smtp.gmail.com'); // or your SMTP server
define('SMTP_USER', 'youremail@gmail.com'); // your email
define('SMTP_PASS', 'your-app-password'); // app password for Gmail
define('SMTP_PORT', 587);
define('FROM_EMAIL', 'noreply@udho.com');
define('FROM_NAME', 'UDHO System');

error_reporting(E_ALL);
ini_set('display_errors', 1);

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Simple SMTP email function
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // HTML email template
    $html_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4f46e5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .code { 
                background: #fff; 
                border: 2px dashed #4f46e5; 
                padding: 15px; 
                font-size: 24px; 
                font-weight: bold; 
                text-align: center; 
                margin: 20px 0;
                letter-spacing: 5px;
            }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>UDHO Password Reset</h2>
            </div>
            <div class="content">
                ' . $message . '
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return mail($to, $subject, $html_message, $headers);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $conn = getDBConnection();
        
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate 6-digit reset code
            $reset_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $reset_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store reset code in database
            $update_stmt = $conn->prepare("UPDATE users SET reset_code = ?, reset_expiry = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $reset_code, $reset_expiry, $user['id']);
            
            if ($update_stmt->execute()) {
                // Prepare email
                $subject = "Password Reset Code - UDHO System";
                $email_message = "
                <h3>Hello " . htmlspecialchars($user['username']) . ",</h3>
                <p>You requested a password reset for your UDHO account.</p>
                <p>Your password reset code is:</p>
                <div class='code'>" . $reset_code . "</div>
                <p><strong>This code will expire in 15 minutes.</strong></p>
                <p>If you didn't request this password reset, please ignore this email or contact support if you're concerned about your account security.</p>
                <p>To reset your password, go to the reset page and enter this code along with your email address.</p>
                <br>
                <p>Best regards,<br>UDHO System Administrator</p>
                ";
                
                // Send email
                if (sendEmail($email, $subject, $email_message)) {
                    $message = "A password reset code has been sent to your email address.";
                    $_SESSION['reset_email'] = $email; // Store email for next step
                } else {
                    $error = "Failed to send email. Please try again or contact support.";
                    // Clear the code if email fails
                    $conn->query("UPDATE users SET reset_code = NULL, reset_expiry = NULL WHERE id = {$user['id']}");
                }
            } else {
                $error = "Error generating reset code. Please try again.";
            }
            
            $update_stmt->close();
        } else {
            $error = "Email not found in our system";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UDHO</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url('assets/BG_LOGIN.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .loader {
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden p-8">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Forgot Password</h2>
            <p class="text-gray-600 mt-2">Enter your registered email address</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            </div>
            <div class="text-center">
                <a href="reset_password.php" 
                   class="inline-flex items-center justify-center w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-key mr-2"></i>
                    Enter Reset Code
                </a>
                <p class="text-sm text-gray-600 mt-4">
                    Didn't receive the email? 
                    <a href="forgot_password.php" class="text-indigo-600 hover:text-indigo-500">Try again</a>
                </p>
            </div>
        <?php else: ?>
            <form id="resetForm" method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                           placeholder="Enter your registered email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="flex space-x-4">
                    <button type="submit" id="submitBtn"
                            class="flex-1 flex items-center justify-center px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                        <span id="btnText">Send Reset Code</span>
                        <div id="spinner" class="hidden ml-2">
                            <div class="loader"></div>
                        </div>
                    </button>
                    <a href="index.php"
                       class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 text-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-200">
                        Back to Login
                    </a>
                </div>
            </form>
            
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="flex items-center text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-2 text-indigo-500"></i>
                    <p>We'll send a 6-digit code to your email. The code expires in 15 minutes.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address');
                return;
            }
            
            // Show loading
            document.getElementById('btnText').textContent = 'Sending...';
            document.getElementById('spinner').classList.remove('hidden');
            document.getElementById('submitBtn').disabled = true;
        });
    </script>
</body>
</html>