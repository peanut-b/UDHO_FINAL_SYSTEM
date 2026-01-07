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
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['currentPassword'] ?? '';
$newPassword = $input['newPassword'] ?? '';
$reason = $input['reason'] ?? '';
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Verify current password against hashed password in database
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if password verification succeeds
if (!$user || !password_verify($currentPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

// Validate new password length
if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
    exit;
}

// Check if new password is same as current
if (password_verify($newPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'New password cannot be the same as current password']);
    exit;
}

// Hash the new password for storage
$hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Insert password change request - store BOTH plain and hashed versions
$stmt = $conn->prepare("INSERT INTO password_change_requests (user_id, reason, new_password, hashed_password, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("isss", $userId, $reason, $newPassword, $hashedNewPassword);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password change request submitted successfully. Waiting for admin approval.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit request: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>