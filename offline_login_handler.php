<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offline_login'])) {
    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? '';
    
    $_SESSION['user_id'] = 'offline_' . uniqid();
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['logged_in'] = true;
    $_SESSION['offline_mode'] = true;
    
    // Redirect based on role (offline versions)
    switch ($role) {
        case 'Admin':
            header("Location: Admin/offline_admin_dashboard.php");
            break;
        case 'Operation':
            header("Location: Operation/offline_operation_dashboard.php");
            break;
        // ... other roles
        default:
            header("Location: offline_dashboard.php");
    }
    exit();
}
?>