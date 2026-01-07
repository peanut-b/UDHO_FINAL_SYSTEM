<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u198271324_admin');
define('DB_PASS', 'Udhodbms01');
define('DB_NAME', 'u198271324_udho_db');

error_reporting(E_ALL);
ini_set('display_errors', 1);

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$message = '';
$error = '';
$step = 1; // 1 = verify code, 2 = reset password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['verify_code'])) {
        // Step 1: Verify reset code
        $email = trim($_POST['email'] ?? '');
        $reset_code = trim($_POST['reset_code'] ?? '');
        
        if (empty($email) || empty($reset_code)) {
            $error = "Please enter both email and reset code";
        } elseif (!preg_match('/^\d{6}$/', $reset_code)) {
            $error = "Reset code must be 6 digits";
        } else {
            $stmt = $conn->prepare("SELECT id, username, reset_expiry FROM users WHERE email = ? AND reset_code = ?");
            $stmt->bind_param("ss", $email, $reset_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Check if code is expired
                if (strtotime($user['reset_expiry']) > time()) {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_email'] = $email;
                    $step = 2;
                    $message = "Code verified successfully! Now set your new password.";
                } else {
                    $error = "Reset code has expired. Please request a new one.";
                    // Clear expired code
                    $conn->query("UPDATE users SET reset_code = NULL, reset_expiry = NULL WHERE email = '$email'");
                }
            } else {
                $error = "Invalid reset code or email";
            }
            
            $stmt->close();
        }
    } elseif (isset($_POST['reset_password']) && isset($_SESSION['reset_user_id'])) {
        // Step 2: Reset password
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = "Please enter both password fields";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $user_id = $_SESSION['reset_user_id'];
            
            // Update password and clear reset code
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expiry = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $message = "✅ Password reset successfully! You will be redirected to login page in 3 seconds.";
                
                // Send confirmation email
                $email = $_SESSION['reset_email'];
                $subject = "Password Changed Successfully - UDHO System";
                $email_message = "
                <h3>Password Changed Successfully</h3>
                <p>Your UDHO account password has been successfully changed.</p>
                <p>If you did not make this change, please contact support immediately.</p>
                <br>
                <p>Best regards,<br>UDHO System Administrator</p>
                ";
                
                // Send confirmation email
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: UDHO System <noreply@udho.com>" . "\r\n";
                
                mail($email, $subject, $email_message, $headers);
                
                // Clear session and redirect
                session_destroy();
                session_start();
                $_SESSION['success_message'] = "Password reset successful! Please login with your new password.";
                header("refresh:3;url=index.php");
            } else {
                $error = "Error resetting password. Please try again.";
            }
            
            $stmt->close();
        }
    }
    
    $conn->close();
}

// Pre-fill email if coming from forgot_password
$prefilled_email = $_SESSION['reset_email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - UDHO</title>
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
        .code-input {
            letter-spacing: 10px;
            font-size: 24px;
            text-align: center;
            font-weight: bold;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden p-8">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php echo $step == 1 ? 'Enter Reset Code' : 'Set New Password'; ?>
            </h2>
            <p class="text-gray-600 mt-2">
                <?php echo $step == 1 ? 'Enter the 6-digit code sent to your email' : 'Create a strong new password'; ?>
            </p>
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
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="verify_code" value="1">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           value="<?php echo htmlspecialchars($prefilled_email); ?>"
                           <?php echo $prefilled_email ? 'readonly' : ''; ?>>
                </div>
                
                <div>
                    <label for="reset_code" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-shield-alt mr-2"></i>6-Digit Reset Code
                    </label>
                    <input type="text" id="reset_code" name="reset_code" required maxlength="6" minlength="6"
                           class="code-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="000000"
                           oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(this.value.length === 6) this.form.submit();">
                    <p class="text-xs text-gray-500 mt-2">Enter the 6-digit code sent to your email</p>
                </div>
                
                <div class="flex space-x-4">
                    <button type="submit"
                            class="flex-1 px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Verify Code
                    </button>
                    <a href="forgot_password.php"
                       class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 text-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Resend Code
                    </a>
                </div>
            </form>
        <?php elseif ($step == 2): ?>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="reset_password" value="1">
                
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>New Password
                    </label>
                    <input type="password" id="new_password" name="new_password" required minlength="8"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="At least 8 characters"
                           onkeyup="checkPasswordStrength(this.value)">
                    <div id="passwordStrength" class="password-strength mt-2"></div>
                    <div id="passwordTips" class="text-xs text-gray-500 mt-2"></div>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Confirm New Password
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Re-enter your new password"
                           onkeyup="checkPasswordMatch()">
                    <div id="passwordMatch" class="text-xs mt-2"></div>
                </div>
                
                <div class="flex space-x-4">
                    <button type="submit" id="resetBtn"
                            class="flex-1 px-4 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Reset Password
                    </button>
                    <a href="index.php"
                       class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 text-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Cancel
                    </a>
                </div>
            </form>
            
            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div>
                        <p class="text-sm text-blue-700 font-medium">Password Requirements:</p>
                        <ul class="text-xs text-blue-600 mt-1 space-y-1">
                            <li>• At least 8 characters long</li>
                            <li>• Include uppercase and lowercase letters</li>
                            <li>• Include at least one number</li>
                            <li>• Consider adding special characters</li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-focus and auto-submit for code input
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('reset_code');
            if (codeInput) {
                codeInput.focus();
            }
            
            // Check if we should disable the reset button
            const resetBtn = document.getElementById('resetBtn');
            if (resetBtn) {
                resetBtn.disabled = true;
            }
        });
        
        function checkPasswordStrength(password) {
            let strength = 0;
            const tips = [];
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const strengthBar = document.getElementById('passwordStrength');
            const tipsDiv = document.getElementById('passwordTips');
            
            strengthBar.className = 'password-strength strength-' + strength;
            
            if (strength < 2) {
                strengthBar.style.backgroundColor = '#dc3545';
                tips.push('Weak password');
            } else if (strength < 4) {
                strengthBar.style.backgroundColor = '#ffc107';
                tips.push('Moderate password');
            } else {
                strengthBar.style.backgroundColor = '#28a745';
                tips.push('Strong password!');
            }
            
            if (password.length < 8) tips.push('At least 8 characters');
            if (!/[a-z]/.test(password)) tips.push('Add lowercase letters');
            if (!/[A-Z]/.test(password)) tips.push('Add uppercase letters');
            if (!/[0-9]/.test(password)) tips.push('Add numbers');
            
            tipsDiv.innerHTML = tips.join(' • ');
            
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            const resetBtn = document.getElementById('resetBtn');
            
            if (confirm.length === 0) {
                matchDiv.innerHTML = '';
                resetBtn.disabled = true;
                return;
            }
            
            if (password === confirm && password.length >= 8) {
                matchDiv.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Passwords match</span>';
                resetBtn.disabled = false;
            } else if (password !== confirm && confirm.length > 0) {
                matchDiv.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>Passwords do not match</span>';
                resetBtn.disabled = true;
            } else {
                matchDiv.innerHTML = '';
                resetBtn.disabled = true;
            }
        }
    </script>
</body>
</html>