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
    die("Database connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['backup_request'])) {
    // Set headers for Excel file download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="UDHO_Backup_' . date('Y-m-d_H-i-s') . '.xls"');
    
    // Create Excel content
    echo "Urban Development and Housing Office - Database Backup\n";
    echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Backup survey_responses table
    echo "SURVEY RESPONSES\n";
    echo "ID\tBarangay\tCreated At\n";
    
    $surveyQuery = "SELECT id, barangay, created_at FROM survey_responses ORDER BY created_at DESC";
    $surveyResult = $conn->query($surveyQuery);
    
    if ($surveyResult && $surveyResult->num_rows > 0) {
        while($row = $surveyResult->fetch_assoc()) {
            echo "IDSAP-" . str_pad($row['id'], 3, '0', STR_PAD_LEFT) . "\t";
            echo $row['barangay'] . "\t";
            echo date('Y-m-d H:i:s', strtotime($row['created_at'])) . "\n";
        }
    }
    
    echo "\n\nHOA ASSOCIATIONS\n";
    echo "HOA ID\tName\tBarangay\tStatus\n";
    
    $hoaQuery = "SELECT hoa_id, name, barangay, hoa_status FROM hoa_associations ORDER BY created_at DESC";
    $hoaResult = $conn->query($hoaQuery);
    
    if ($hoaResult && $hoaResult->num_rows > 0) {
        while($row = $hoaResult->fetch_assoc()) {
            echo $row['hoa_id'] . "\t";
            echo $row['name'] . "\t";
            echo $row['barangay'] . "\t";
            echo $row['hoa_status'] . "\n";
        }
    }
    
    echo "\n\nPDC RECORDS\n";
    echo "Date Issued\tSubject\tCase File\tStatus\n";
    
    $pdcQuery = "SELECT date_issued, subject, case_file, status FROM pdc_records ORDER BY date_issued DESC";
    $pdcResult = $conn->query($pdcQuery);
    
    if ($pdcResult && $pdcResult->num_rows > 0) {
        while($row = $pdcResult->fetch_assoc()) {
            echo date('Y-m-d', strtotime($row['date_issued'])) . "\t";
            echo $row['subject'] . "\t";
            echo $row['case_file'] . "\t";
            echo $row['status'] . "\n";
        }
    }
    
    echo "\n\nUSERS\n";
    echo "User ID\tUsername\tRole\n";
    
    $userQuery = "SELECT id, username, role FROM users ORDER BY id DESC";
    $userResult = $conn->query($userQuery);
    
    if ($userResult && $userResult->num_rows > 0) {
        while($row = $userResult->fetch_assoc()) {
            echo strtoupper(substr($row['role'], 0, 3)) . "-" . str_pad($row['id'], 3, '0', STR_PAD_LEFT) . "\t";
            echo $row['username'] . "\t";
            echo ucfirst($row['role']) . "\n";
        }
    }
    
    exit();
}
?>