<?php
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

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

// Set last activity time for timeout (24 hours)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Get enumerator data from session
$enumerator_name = $_SESSION['username'] ?? 'Unknown Enumerator';
$user_id = $_SESSION['user_id'] ?? 0; // Assuming you store user_id in session during login

// Generate enumerator ID based on user ID
$enumerator_id = 'EN-' . str_pad($user_id, 3, '0', STR_PAD_LEFT);

// If user_id is not in session, try to get it from database using username
if ($user_id == 0 && isset($_SESSION['username'])) {
    $sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $enumerator_id = 'EN-' . str_pad($user_id, 3, '0', STR_PAD_LEFT);
        // Store in session for future use
        $_SESSION['user_id'] = $user_id;
    }
}
// Simplified table creation
$sql = "CREATE TABLE IF NOT EXISTS survey_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enumerator_name VARCHAR(255),
    enumerator_id VARCHAR(255),
    ud_code VARCHAR(50),
    tag_number VARCHAR(50) UNIQUE,
    address TEXT,
    barangay VARCHAR(255),
    city VARCHAR(255),
    region VARCHAR(255),
    location_lat DOUBLE,
    location_lng DOUBLE,
    photos LONGTEXT,
    signature LONGTEXT,
    answers LONGTEXT,
    survey_date DATE,                  
    survey_time TIME,                    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === FALSE) {
    die("Table error: " . $conn->error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    // Prepare data for database
    $ud_code = $_POST['ud_code'] ?? '';
    $tag_number = $_POST['tag_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $city = $_POST['city'] ?? 'Pasay City';
    $region = $_POST['region'] ?? 'NCR';
    $location_lat = $_POST['location_lat'] ?? 0;
    $location_lng = $_POST['location_lng'] ?? 0;
    
    // ✅ ADD THESE NEW FIELDS
    $survey_date = date('Y-m-d');  // Current date
    $survey_time = date('H:i:s');  // Current time
    
    // Handle photos (convert array to JSON)
    $photos = isset($_POST['photos']) ? json_encode($_POST['photos']) : json_encode([]);
    
    // Handle signature
    $signature = $_POST['signature'] ?? '';
    
    // Prepare answers JSON (all other form fields)
    $answers = [];
    $exclude_fields = [
        'ud_code', 'tag_number', 'address', 'barangay', 'city', 'region', 
        'location_lat', 'location_lng', 'photos', 'signature', 'submit_survey'
    ];
    
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $exclude_fields)) {
            $answers[$key] = $value;
        }
    }
    
    $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE);
    
    // ✅ UPDATE THE INSERT STATEMENT - ADD survey_date and survey_time
    $stmt = $conn->prepare("
        INSERT INTO survey_responses (
            enumerator_name, enumerator_id, ud_code, tag_number,
            address, barangay, city, region, location_lat, location_lng,
            photos, signature, answers, survey_date, survey_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // ✅ UPDATE BIND PARAMETERS - ADD 2 MORE 's' FOR DATE AND TIME
    $stmt->bind_param(
        "ssssssssddsssss",
        $enumerator_name,
        $enumerator_id,
        $ud_code,
        $tag_number,
        $address,
        $barangay,
        $city,
        $region,
        $location_lat,
        $location_lng,
        $photos,
        $signature,
        $answers_json,
        $survey_date,      // New field
        $survey_time       // New field
    );
    
    if ($stmt->execute()) {
        // Success - we'll handle the response in JavaScript
        $response = [
            'success' => true,
            'id' => $stmt->insert_id,
            'tag_number' => $tag_number
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        // Error
        $response = [
            'success' => false,
            'error' => $stmt->error
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

// Handle sync request for offline data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_offline_data'])) {
    $offline_data = json_decode($_POST['offline_data'], true);
    $results = [];
    
    foreach ($offline_data as $data) {
        $tag_number = $data['tag_number'] ?? '';
        $local_id = $data['local_id'] ?? '';
        
        // Check if this tag number already exists
        $check_stmt = $conn->prepare("SELECT id FROM survey_responses WHERE tag_number = ?");
        $check_stmt->bind_param("s", $tag_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Record already exists - this is a duplicate
            $results[] = [
                'local_id' => $local_id,
                'success' => false,
                'error' => 'Duplicate tag number: ' . $tag_number
            ];
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        // Prepare data for database
        $ud_code = $data['ud_code'] ?? '';
        $address = $data['address'] ?? '';
        $barangay = $data['barangay'] ?? '';
        $city = $data['city'] ?? 'Pasay City';
        $region = $data['region'] ?? 'NCR';
        $location_lat = $data['location_lat'] ?? 0;
        $location_lng = $data['location_lng'] ?? 0;
        $survey_date = $data['survey_date'] ?? date('Y-m-d');
        $survey_time = $data['survey_time'] ?? date('H:i:s');
        $photos = isset($data['photos']) ? json_encode($data['photos']) : json_encode([]);
        $signature = $data['signature'] ?? '';
        $answers_json = json_encode($data['answers'] ?? [], JSON_UNESCAPED_UNICODE);
        
        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO survey_responses (
                enumerator_name, enumerator_id, ud_code, tag_number,
                address, barangay, city, region, location_lat, location_lng,
                photos, signature, answers, survey_date, survey_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssssssssddsssss",
            $enumerator_name,
            $enumerator_id,
            $ud_code,
            $tag_number,
            $address,
            $barangay,
            $city,
            $region,
            $location_lat,
            $location_lng,
            $photos,
            $signature,
            $answers_json,
            $survey_date,
            $survey_time
        );
        
        if ($stmt->execute()) {
            $results[] = [
                'local_id' => $local_id,
                'success' => true,
                'server_id' => $stmt->insert_id
            ];
        } else {
            $results[] = [
                'local_id' => $local_id,
                'success' => false,
                'error' => $stmt->error
            ];
        }
        $stmt->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['results' => $results]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
 <!-- PWA Manifest -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#673ab7">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Survey App">
<link rel="apple-touch-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyIiBoZWlnaHQ9IjE5MiIgdmlld0JveD0iMCAwIDE5MiAxOTIiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxOTIiIGhlaWdodD0iMTkyIiBmaWxsPSIjNjczYWI3Ii8+Cjx0ZXh0IHg9Ijk2IiB5PSIxMDAiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIyNCIgZmlsbD0id2hpdGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiPlM8L3RleHQ+Cjwvc3ZnPgo=">

<!-- Mobile Meta Tags -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="mobile-web-app-capable" content="yes">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housing Survey Form (Offline Capable)</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Add barcode and QR code libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
    <style>
        /* All your existing CSS styles remain the same */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 2px 6px 2px rgba(60,64,67,0.15);
            width: 100%;
        }
        
        .form-header {
            border-bottom: 8px solid #673ab7;
            padding-bottom: 8px;
        }
        
        .form-title {
            color: #202124;
            font-size: clamp(24px, 3vw, 32px);
            font-weight: 400;
        }
        
        .form-description {
            color: #5f6368;
            font-size: clamp(12px, 2vw, 14px);
        }
        
        .section-title {
            color: #202124;
            font-size: clamp(18px, 2.5vw, 20px);
            font-weight: 500;
            border-bottom: 1px solid #dadce0;
            padding-bottom: 6px;
            margin-bottom: 16px;
        }
        
        .question-title {
            color: #202124;
            font-size: clamp(14px, 2vw, 16px);
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 8px 12px;
            width: 100%;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #673ab7;
            box-shadow: 0 0 0 2px rgba(103,58,183,0.2);
        }
        
        #signature-pad {
            border: 1px solid #dadce0;
            cursor: crosshair;
            background-color: white;
            touch-action: none;
            width: 100%;
            min-height: 150px;
            border-radius: 4px;
        }
        
        #map {
            height: 300px;
            width: 100%;
            border-radius: 4px;
            border: 1px solid #dadce0;
        }
        
        .location-confirm-btn {
            background-color: #f1f3f4;
            color: #3c4043;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            margin-right: 8px;
        }
        
        .location-confirm-btn.confirmed {
            background-color: #e6f4ea;
            color: #137333;
            border-color: #137333;
        }
        
        .location-confirm-btn.denied {
            background-color: #fce8e6;
            color: #d93025;
            border-color: #d93025;
        }
        
        .required-field::after {
            content: " *";
            color: #d93025;
        }
        
        .photo-container {
            position: relative;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .delete-photo-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #d93025;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            font-weight: bold;
        }
        
        .photo-thumbnail {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 2px solid #dadce0;
            border-radius: 4px;
        }
        
        .photos-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .map-instructions {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 14px;
            color: #5f6368;
        }
        
        .location-accuracy {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .location-accuracy.high {
            color: #137333;
        }
        
        .location-accuracy.medium {
            color: #E67C00;
        }
        
        .location-accuracy.low {
            color: #D93025;
        }
        
        .location-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: #4285F4;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive table styles */
        .responsive-table {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Button styles for better mobile experience */
        .form-button {
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .form-button:active {
            transform: scale(0.98);
        }
        
        /* Camera modal styles */
        .camera-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            z-index: 1000;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .camera-container {
            width: 100%;
            max-width: 600px;
            position: relative;
        }
        
        .camera-view {
            width: 100%;
            background-color: black;
        }
        
        .camera-preview {
            width: auto;
            display: none;
        }
        
        .camera-controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }
        
        .camera-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .capture-btn {
            background-color: #fff;
        }
        
        .switch-camera-btn, .retake-btn, .confirm-btn {
            background-color: rgba(255,255,255,0.2);
            color: white;
        }
        
        /* Confirmation modal */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .confirmation-box {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        /* Adjust padding for smaller screens */
        @media (max-width: 768px) {
            .form-container {
                padding: 15px;
            }
            
            .photo-thumbnail {
                width: 100px;
                height: 100px;
            }
            
            #map {
                height: 250px;
            }
            
            #signature-pad {
                min-height: 120px;
            }
        }
        
        @media (max-width: 480px) {
            .form-container {
                padding: 10px;
            }
            
            .photo-thumbnail {
                width: 80px;
                height: 80px;
            }
            
            #map {
                height: 200px;
            }
            
            .form-button {
                padding: 8px 16px;
                font-size: 14px;
            }
            
            .grid-cols-1 > div {
                margin-bottom: 10px;
            }
        }
        .member-row {
            transition: all 0.3s ease;
        }
        .member-row:hover {
            background-color: #f8f9fa;
        }
        .remove-member-btn {
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 2px 8px;
            cursor: pointer;
            font-size: 12px;
        }
        .location-details {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            border: 1px solid #e0e0e0;
        }
        
        /* Code display styles */
        .code-display-container {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .code-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .code-input-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .code-input-group {
            display: flex;
            flex-direction: column;
        }
        
        .code-label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
            color: #555;
        }
        
        .code-input {
            padding: 8px 12px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .barcode-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .barcode-display {
            text-align: center;
            padding: 10px;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .barcode-label {
            font-size: 14px;
            margin-bottom: 5px;
            color: #555;
        }
        
        .barcode-svg {
            width: 100%;
            height: 50px;
        }
        
        .qr-code-container {
            margin-top: 15px;
            text-align: center;
            padding: 10px;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .qr-code-label {
            font-size: 14px;
            margin-bottom: 5px;
            color: #555;
        }
        
        .qr-code-canvas {
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .code-input-container,
            .barcode-container {
                grid-template-columns: 1fr;
            }
        }
             .survey-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .survey-meta-item {
        display: flex;
        flex-direction: column;
        min-width: 150px;
    }

    .survey-meta-label {
        font-weight: 600;
        color: #495057;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .survey-meta-item div:not(.survey-meta-label) {
        color: #212529;
        font-size: 15px;
    }

    .logout-btn {
        align-self: flex-end;
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s ease;
        margin-top: auto;
    }

    .logout-btn:hover {
        background-color: #c82333;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .survey-meta {
            flex-direction: column;
            gap: 12px;
        }
        
        .survey-meta-item {
            min-width: 100%;
        }
        
        .logout-btn {
            align-self: flex-start;
            margin-top: 10px;
        }
    }

    /* Offline status indicator */
    .offline-status {
        position: fixed;
        top: 10px;
        right: 10px;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        z-index: 1001;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .offline-status.online {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .offline-status.offline {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .sync-status {
        margin-top: 10px;
        padding: 10px;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .sync-status.pending {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .sync-status.success {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
    
    .sync-status.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    </style>
</head>
<body class="bg-gray-100 p-2 md:p-4 lg:p-8">
    
    <!-- Offline Status Indicator -->
    <div id="offline-status" class="offline-status online">
        Online
    </div>
    
    <!-- Camera Modal -->
    <div id="camera-modal" class="camera-modal">
        <div class="camera-container">
            <video id="camera-view" class="camera-view" autoplay playsinline></video>
            <canvas id="camera-preview" class="camera-preview"></canvas>
            
            <div class="camera-controls">
                <button id="switch-camera-btn" class="camera-btn switch-camera-btn" title="Switch Camera">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h7a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7M5 16H4a2 2 0 0 1-2-2V6a2 2 0 0 1-2-2h1"></path>
                        <polyline points="16 16 12 12 16 8"></polyline>
                        <polyline points="8 8 12 12 8 16"></polyline>
                    </svg>
                </button>
                <button id="capture-btn" class="camera-btn capture-btn" title="Take Photo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                </button>
                <button id="retake-btn" class="camera-btn retake-btn" title="Retake" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <polyline points="23 20 23 14 17 14"></polyline>
                        <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                    </svg>
                </button>
                <button id="confirm-btn" class="camera-btn confirm-btn" title="Confirm" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmation-modal" class="confirmation-modal">
        <div class="confirmation-box">
            <h3 class="text-lg font-medium mb-4">Confirm Submission</h3>
            <p class="mb-4">Are you sure all the information you provided is accurate and complete?</p>
            <div class="flex justify-end gap-3">
                <button id="cancel-submission-btn" class="form-button bg-gray-400 hover:bg-gray-500 text-white font-medium">
                    Cancel
                </button>
                <button id="confirm-submission-btn" class="form-button bg-indigo-600 hover:bg-indigo-700 text-white font-medium">
                    Confirm Submission
                </button>
            </div>
        </div>
    </div>

    <div id="form-container" class="form-container bg-white rounded-lg mx-2 md:mx-auto">
        <div class="p-4 md:p-6 lg:p-8">
            <div class="form-header mb-6 md:mb-8">
                <h1 class="form-title mb-2">Housing Survey Form (Offline Capable)</h1>
                <p class="form-description">This survey collects information about housing conditions and needs for urban development planning. Works offline and syncs when online.</p>
            </div>

            <!-- Offline Sync Status -->
            <div id="sync-status" class="sync-status hidden">
                <!-- Sync status messages will appear here -->
            </div>

            <!-- Survey Meta Information -->
            <div class="survey-meta">
                <div class="survey-meta-item">
                    <div class="survey-meta-label">Enumerator:</div>
                    <div id="enumerator-name"><?php echo htmlspecialchars($enumerator_name); ?></div>
                </div>
                <div class="survey-meta-item">
                    <div class="survey-meta-label">Enumerator ID:</div>
                    <div id="enumerator-id"><?php echo htmlspecialchars($enumerator_id); ?></div>
                </div>
                <div class="survey-meta-item">
                     <div class="survey-meta-label">Survey Started:</div>
                     <div id="survey-start-time"></div>
                </div>
                <div class="survey-meta-item">
                    <div class="survey-meta-label">Survey Completed:</div>
                    <div id="survey-end-time">-</div>
                </div>
                <div class="survey-meta-item">
                    <button id="sync-offline-btn" class="form-button bg-green-500 hover:bg-green-600 text-white font-medium">
                        Sync Offline Data
                    </button>
                </div>
                <div class="survey-meta-item">
                    <button id="logout-btn" class="logout-btn">Logout</button>
                </div>
            </div>

            <!-- UD Code and TAG Number Section -->
            <div id="tag-number-section" class="mb-6">
                <div class="code-display-container">
                    <h2 class="section-title">Identification Codes</h2>
                    
                    <div class="code-input-container">
                        <div class="code-input-group">
                            <label for="ud-code" class="code-label required-field">UD Code (YEAR-BRGY-NO.)</label>
                            <input type="text" id="ud-code" class="code-input" placeholder="e.g., 2023-BRGY-001" required>
                        </div>
                        
                        <div class="code-input-group">
                            <label for="tag-number" class="code-label required-field">TAG Number</label>
                            <input type="text" id="tag-number" class="code-input" placeholder="e.g., 2025-BRGY-001" required 
                                pattern="^20[2-9][0-9]-\d{3}-\d{3}$"
                                title="Format: YYYY-BBB-NNN (e.g., 2025-147-001)">
                            <small class="text-gray-500">Format: YYYY-BBB-NNN (e.g., 2025-147-001)</small>
                        </div>
                    </div>
                    
                    <div class="barcode-container">
                        <div class="barcode-display">
                            <div class="barcode-label">UD Code Barcode</div>
                            <svg id="ud-code-barcode" class="barcode-svg"></svg>
                        </div>
                        
                        <div class="barcode-display">
                            <div class="barcode-label">TAG Number Barcode</div>
                            <svg id="tag-number-barcode" class="barcode-svg"></svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Location Section -->
            <div id="location-section" class="mb-6 md:mb-8 p-4 md:p-6 bg-white rounded-lg border border-gray-200">
                <h2 class="section-title">Location Information</h2>
                <div class="mb-4">
                    <p class="question-title">Please confirm your location:</p>
                    <div class="map-instructions">
                        <p>We'll first try to get your device's location. If it's not accurate, you can manually place a marker.</p>
                    </div>
                    <div id="map" class="mb-4"></div>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center mb-4">
                        <div class="flex-1 mb-2 sm:mb-0">
                            <p id="location-text" class="text-sm text-gray-600 mr-4">
                                <span id="location-status">Getting your location...</span>
                                <span id="location-accuracy" class="location-accuracy"></span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button id="get-location-btn" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded mr-2">
                                <span id="location-loading" class="location-loading hidden"></span>
                                Get My Location
                            </button>
                            <button id="confirm-location-btn" class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded">
                                Confirm Location
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="location-details" class="location-details hidden">
                <h3 class="font-medium mb-2">Confirmed Location Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div>
                        <p class="text-sm font-medium">Coordinates:</p>
                        <p id="confirmed-coords" class="text-sm"></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium">Address:</p>
                        <p id="confirmed-address" class="text-sm"></p>
                    </div>
                </div>
            </div>

            <!-- Form Pages -->
            <div id="form-page-1" class="form-page">
                <!-- Section I: Personal Data -->
                <div class="mb-4 md:mb-6">
                    <p class="question-title required-field">Survey Type</p>
                    <div class="relative">
                        <select id="survey-type" class="form-control" required>
                            <option value="">Select Survey Type</option>
                            <option value="IDSAP-FIRE VICTIM">IDSAP-FIRE VICTIM</option>
                            <option value="IDSAP-FLOOD">IDSAP-FLOOD</option>
                            <option value="IDSAP-EARTHQUAKE">IDSAP-EARTHQUAKE</option>
                            <option value="CENSUS-PDC">CENSUS-PDC</option>
                            <option value="CENSUS-HOA">CENSUS-HOA</option>
                            <option value="CENSUS-WATERWAYS">CENSUS-WATERWAYS</option>
                            <option value="OTHERS">OTHERS (Please specify)</option>
                        </select>
                    </div>
                    <input type="text" id="other-survey-type" class="form-control mt-2 hidden" placeholder="Please specify survey type">
                </div>        

                <!-- Personal Data Section -->
                <div class="mb-6 md:mb-8">
                    <h2 class="section-title">I. Personal Data</h2>
                    
                    <div class="mb-4 md:mb-6">
                        <p class="question-title required-field">Name of Household Head</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4">
                            <div>
                                <label for="hh-surname" class="block text-xs text-gray-600 mb-1">Surname</label>
                                <input type="text" id="hh-surname" class="form-control" required>
                            </div>
                            <div>
                                <label for="hh-firstname" class="block text-xs text-gray-600 mb-1">First Name</label>
                                <input type="text" id="hh-firstname" class="form-control" required>
                            </div>
                            <div>
                                <label for="hh-middlename" class="block text-xs text-gray-600 mb-1">Middle Name</label>
                                <input type="text" id="hh-middlename" class="form-control">
                            </div>
                            <div>
                                <label for="hh-mi" class="block text-xs text-gray-600 mb-1">MI</label>
                                <input type="text" id="hh-mi" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 md:mb-6">
                        <p class="question-title">Name of Spouse</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4">
                            <div>
                                <label for="spouse-surname" class="block text-xs text-gray-600 mb-1">Surname</label>
                                <input type="text" id="spouse-surname" class="form-control">
                            </div>
                            <div>
                                <label for="spouse-firstname" class="block text-xs text-gray-600 mb-1">First Name</label>
                                <input type="text" id="spouse-firstname" class="form-control">
                            </div>
                            <div>
                                <label for="spouse-middlename" class="block text-xs text-gray-600 mb-1">Middle Name</label>
                                <input type="text" id="spouse-middlename" class="form-control">
                            </div>
                            <div>
                                <label for="spouse-mi" class="block text-xs text-gray-600 mb-1">MI</label>
                                <input type="text" id="spouse-mi" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                        <div>
                            <p class="question-title required-field">Household Head Data</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-y-2 gap-x-3 mb-3 md:mb-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Sex</label>
                                    <select id="hh-sex" class="form-control" required>
                                        <option value="">Select</option>
                                        <option>Male</option>
                                        <option>Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Age</label>
                                    <input type="number" id="hh-age" class="form-control" required>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Birthdate</label>
                                    <input type="date" id="hh-birthdate" class="form-control" required>
                                </div>
                            </div>
                          <div class="mb-3 md:mb-4">
    <label class="block text-xs text-gray-600 mb-1">Civil Status</label>
    <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
        <label class="inline-flex items-center">
            <input type="radio" name="hh-civil-status" value="Single" class="form-radio h-4 w-4 text-indigo-600" required>
            <span class="ml-2">Single</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="hh-civil-status" value="Married" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Married</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="hh-civil-status" value="Widow/Widower" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Widow/Widower</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="hh-civil-status" value="Solo Parent" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Solo Parent</span>
        </label>
    </div>
</div>

                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Vulnerability</label>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
                                    <label class="inline-flex items-center"><input type="checkbox" id="hh-senior-citizen" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Senior Citizen</span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" id="hh-pwd" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">PWD</span></label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="question-title">Spouse Data</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-y-2 gap-x-3 mb-3 md:mb-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Sex</label>
                                    <select id="spouse-sex" class="form-control">
                                        <option value="">Select</option>
                                        <option>Male</option>
                                        <option>Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Age</label>
                                    <input type="number" id="spouse-age" class="form-control">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Birthdate</label>
                                    <input type="date" id="spouse-birthdate" class="form-control">
                                </div>
                            </div>
                            
                         <div class="mb-3 md:mb-4">
    <label class="block text-xs text-gray-600 mb-1">Civil Status</label>
    <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
        <label class="inline-flex items-center">
            <input type="radio" name="spouse-civil-status" value="Single" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Single</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="spouse-civil-status" value="Married" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Married</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="spouse-civil-status" value="Widow/Widower" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Widow/Widower</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="spouse-civil-status" value="Solo Parent" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Solo Parent</span>
        </label>
    </div>
</div>

                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Vulnerability</label>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
                                    <label class="inline-flex items-center"><input type="checkbox" id="spouse-senior-citizen" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Senior Citizen</span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" id="spouse-pwd" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">PWD</span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section II: Tenurial Status -->
                <div class="mb-6 md:mb-8">
                    <h2 class="section-title">II. Tenurial Status</h2>
                    
                    <div class="mb-4 md:mb-6">
                        <p class="question-title required-field">Residential Address</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 md:gap-4 mb-3 md:mb-4 text-sm">
                            <div>
                                <label for="house-no" class="block text-xs text-gray-600 mb-1">House No.</label>
                                <input type="text" id="house-no" class="form-control">
                            </div>
                            <div>
                                <label for="lot-no" class="block text-xs text-gray-600 mb-1">Lot No.</label>
                                <input type="text" id="lot-no" class="form-control">
                            </div>
                            <div>
                                <label for="building" class="block text-xs text-gray-600 mb-1">Building</label>
                                <input type="text" id="building" class="form-control">
                            </div>
                            <div>
                                <label for="block" class="block text-xs text-gray-600 mb-1">Block</label>
                                <input type="text" id="block" class="form-control">
                            </div>
                            <div>
                                <label for="street" class="block text-xs text-gray-600 mb-1">Street</label>
                                <input type="text" id="street" class="form-control" required>
                            </div>
                            <div>
                                <label for="barangay" class="block text-xs text-gray-600 mb-1">Barangay</label>
                                <div class="relative">
                                    <input type="text" id="barangay" list="barangayList" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required
                                        placeholder="Type or select barangay">
                                    <datalist id="barangayList">
                                        <?php for($i = 1; $i <= 201; $i++): ?>
                                            <option value="Barangay <?php echo $i; ?>">
                                        <?php endfor; ?>
                                    </datalist>
                                </div>
                            </div>
                            <div>
                                <label for="city" class="block text-xs text-gray-600 mb-1">City</label>
                                <input type="text" id="city" class="form-control" value="Pasay City" readonly>
                            </div>
                            <div>
                                <label for="region" class="block text-xs text-gray-600 mb-1">Region</label>
                                <input type="text" id="region" class="form-control" value="NCR" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 mb-4 md:mb-6">
                        <div>
                            <p class="question-title required-field">Nature of Land Occupied</p>
                            <div class="flex flex-col gap-1 md:gap-2">
                                <label class="inline-flex items-center"><input type="radio" value="Private" name="land-nature" class="form-radio h-4 w-4 text-indigo-600" required> <span class="ml-2">Private</span></label>
                                <label class="inline-flex items-center"><input type="radio" value="Government" name="land-nature" class="form-radio h-4 w-4 text-indigo-600"> <span class="ml-2">Government</span></label>
                                <label class="inline-flex items-center"><input type="radio" value="Others" name="land-nature" class="form-radio h-4 w-4 text-indigo-600"> <span class="ml-2">Others</span></label>
                            </div>
                        </div>
                        
                   <div>
    <p class="question-title required-field">Lot Status</p>
    <div class="flex flex-col gap-1 md:gap-2">
        <label class="inline-flex items-center">
            <input type="radio" name="lot-status" value="Lot Owner" class="form-radio h-4 w-4 text-indigo-600" required>
            <span class="ml-2">Lot Owner</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="lot-status" value="Lot not occupied (sqm/ha)" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Lot not occupied (sqm/ha)</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="lot-status" value="Renter (< 5 years)" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Renter (&lt; 5 years)</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="lot-status" value="Renter (> 5 years)" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Renter (&gt; 5 years)</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="lot-status" value="Rent Free Owner" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Rent Free Owner</span>
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="lot-status" value="Co-Owner" class="form-radio h-4 w-4 text-indigo-600">
            <span class="ml-2">Co-Owner</span>
        </label>
    </div>
</div>
</div>

<div class="mb-4">
    <label for="name-rfo-renter" class="question-title">Name of Owner (for RFO/Renter):</label>
    <input type="text" id="name-rfo-renter" name="name-rfo-renter" class="form-control">
</div>
</div>

<div class="mt-4 md:mt-6 flex justify-end">
    <button id="next-page-1-btn" class="form-button bg-blue-500 hover:bg-blue-600 text-white font-medium">
        Next
    </button>
</div>
</div>

            <div id="form-page-2" class="form-page hidden">
                <!-- Section III: Membership -->
                <div class="mb-6 md:mb-8">
                    <h2 class="section-title">III. Membership</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                        <div>
                            <p class="question-title">Fund</p>
                            <div class="flex flex-col gap-1 md:gap-2">
                                <label class="inline-flex items-center"><input type="checkbox" id="pagibig" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">PAG-IBIG</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="sss" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">SSS</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="gsis" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">GSIS</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="philhealth" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">PhilHealth</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="none-fund" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">None</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="other-fund" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Others</span></label>
                            </div>
                        </div>
                        <div>
                            <p class="question-title">Organization</p>
                            <div class="flex flex-col gap-1 md:gap-2">
                                <label class="inline-flex items-center"><input type="checkbox" id="cso" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">CSO</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="hoa" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">HOA</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="cooperative" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Cooperative</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="none-org" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">None</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="other-org" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Others</span></label>
                            </div>
                            <div class="mt-3 md:mt-4">
                                <label for="name-organization" class="question-title">Name of Organization</label>
                                <input type="text" id="name-organization" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section IV: Household Member Data -->
                <div class="mb-6 md:mb-8">
                    <h2 class="section-title">IV. Household Member Data</h2>
                    <p class="text-sm text-gray-600 mb-3 md:mb-4">List all household members including the head and spouse</p>

                </div>
                    
                <div class="responsive-table mb-4">
                    <table id="member-table" class="min-w-full bg-white border border-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="py-2 px-3 border-b text-left text-gray-600 font-medium">NAME</th>
                                <th class="py-2 px-3 border-b text-left text-gray-600 font-medium">RELATIONSHIP TO HEAD</th>
                                <th class="py-2 px-3 border-b text-left text-gray-600 font-medium">AGE</th>
                                <th class="py-2 px-3 border-b text-left text-gray-600 font-medium">SEX</th>
                                <th class="py-2 px-3 border-b text-left text-gray-600 font-medium">BIRTHDATE</th>
                                <th class="py-2 px-3 border-b text-left text-gray-600 font-medium">EDUCATION</th>
                                <th class="py-2 px-3 border-b text-left text-gray-600 font-medium">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="member-table-body">
                            <!-- Initial row will be added by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <button id="add-member-btn" type="button" class="form-button bg-green-500 hover:bg-green-600 text-white font-medium mb-4">
                    Add Household Member
                </button>

                <div class="mt-4 md:mt-6 flex justify-between">
                    <button id="prev-page-2-btn" class="form-button bg-gray-400 hover:bg-gray-500 text-white font-medium">
                        Previous
                    </button>
                    <button id="next-page-2-btn" class="form-button bg-blue-500 hover:bg-blue-600 text-white font-medium">
                        Next
                    </button>
                </div>
            </div>

            <div id="form-page-3" class="form-page hidden">
                <!-- Section V: Remarks -->
                <div class="mb-6 md:mb-8">
                    <h2 class="section-title">V. Remarks</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-4 md:mb-6">
                        <div>
                            <p class="question-title">SHELTER NEEDS</p>
                            <div class="flex flex-col gap-1 md:gap-2">
                                <label class="inline-flex items-center"><input type="checkbox" id="security-upgrading" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Tenurial Upgrading</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="shelter-provision" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Shelter Provision</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="structural-upgrading" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Structural Upgrading</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="infrastructure-upgrading" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Infrastructure Upgrading</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="other-remarks-checkbox" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Other Remarks</span></label>
                                <input type="text" id="other-remarks-text" class="form-control mt-1 hidden" placeholder="Specify other remarks">
                            </div>
                        </div>
                        <div>
                            <p class="question-title">HOUSEHOLD CLASSIFICATION</p>
                            <div class="flex flex-col gap-1 md:gap-2">
                                <label class="inline-flex items-center"><input type="checkbox" id="single-hh" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Single HH</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="displaced-unit" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Displaced Unit</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="doubled-up" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Doubled Up HH</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="displacement-concern" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Displacement Concern</span></label>
                            </div>
                        </div>
                        <div>
                            <p class="question-title">CENSUS REMARKS</p>
                            <div class="flex flex-col gap-1 md:gap-2">
                                <label class="inline-flex items-center"><input type="checkbox" id="odc" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Out During Census (ODC)</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="aho" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Absentee House Owner (AHO)</span></label>
                                <label class="inline-flex items-center"><input type="checkbox" id="census-others-checkbox" class="form-checkbox h-4 w-4 text-indigo-600"> <span class="ml-2">Others</span></label>
                                <input type="text" id="census-others-text" class="form-control mt-1 hidden" placeholder="Specify other census remarks">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Photo Capture Section -->
                <div class="mb-6 md:mb-8 p-4 md:p-6 border rounded-lg bg-gray-50">
                    <h2 class="section-title">Photo Capture</h2>
                    
                    <div class="mb-4">
                        <label class="question-title">Household Head Photo:</label>
                        <div class="flex flex-col items-center">
                            <div id="photos-container" class="photos-container">
                                <!-- Photos will be added here -->
                            </div>
                            <button id="open-camera-btn" class="form-button bg-blue-500 hover:bg-blue-600 text-white font-medium mt-4">
                                Open Camera to Take Photo
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Signature Pad Section -->
                <div class="mb-6 md:mb-8 p-4 md:p-6 border rounded-lg bg-gray-50">
                    <h2 class="section-title">Digital Signature</h2>
                    
                    <div class="mb-4">
                        <label class="question-title">Draw your signature below:</label>
                        <div class="border-2 border-gray-300 rounded-lg p-2 bg-white">
                            <canvas id="signature-pad" class="w-full h-40 md:h-48 lg:h-64 bg-white"></canvas>
                        </div>
                        <div class="flex flex-col sm:flex-row justify-between gap-2 mt-3 md:mt-4">
                            <button id="clear-signature-btn" class="form-button bg-red-500 hover:bg-red-600 text-white font-medium">
                                Clear Signature
                            </button>
                            <button id="save-signature-btn" class="form-button bg-blue-500 hover:bg-blue-600 text-white font-medium">
                                Save Signature
                            </button>
                        </div>
                        <div class="mt-3 md:mt-4">
                            <p class="text-sm text-gray-600 mb-1 md:mb-2">Your saved signature:</p>
                            <img id="saved-signature-img" class="max-w-xs border border-gray-300 rounded-md bg-white p-2" alt="Saved Signature">
                        </div>
                    </div>
                </div>

                <!-- Data Confirmation -->
                <div class="mb-6 md:mb-8 p-4 md:p-6 border rounded-lg bg-gray-100">
                    <h2 class="section-title">Data Confirmation</h2>
                    
                    <div class="mb-4">
                        <div class="flex items-center mb-4">
                            <input type="checkbox" id="data-accuracy-checkbox" class="form-checkbox h-5 w-5 text-indigo-600" required>
                            <label for="data-accuracy-checkbox" class="ml-2 block text-sm text-gray-700">
                                I confirm that all the information provided in this form is accurate to the best of my knowledge.
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="privacy-policy-checkbox" class="form-checkbox h-5 w-5 text-indigo-600" required>
                            <label for="privacy-policy-checkbox" class="ml-2 block text-sm text-gray-700">
                                I agree to the collection and processing of my personal data in accordance with the Data Privacy Act of 2012.
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 md:mt-6 flex justify-between">
                    <button id="prev-page-3-btn" class="form-button bg-gray-400 hover:bg-gray-500 text-white font-medium">
                        Previous
                    </button>
                    <button type="submit" name="submit" id="submit-form-btn" class="form-button bg-indigo-600 hover:bg-indigo-700 text-white font-medium">
                        Submit Form
                    </button>

                </div>
            </div>
        </div>
    </div>

    <!-- Thank You Page -->
    <div id="thank-you-page" class="form-page hidden">
        <div class="text-center p-8 md:p-12">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-4">Thank You!</h2>
            <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
                Thank you for completing the survey. Your TAG number is:
            </p>
            
            <!-- TAG Number Display -->
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 max-w-md mx-auto mb-6">
                <h3 class="text-xl font-bold mb-2" id="final-tag-number"></h3>
                <div class="mb-4">
                    <div class="barcode-label mb-2">TAG Number Barcode</div>
                    <svg id="final-tag-barcode" class="w-full h-16"></svg>
                </div>
                <div class="survey-meta-item">
                    <div class="survey-meta-label">Enumerator:</div>
                    <div id="thankyou-enumerator-name"><?php echo htmlspecialchars($enumerator_name); ?></div>
                </div>
                <div class="survey-meta-item">
                    <div class="survey-meta-label">Completed On:</div>
                    <div id="thankyou-completion-time"><?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
                <div class="survey-meta-item">
                    <div class="survey-meta-label">Duration:</div>
                    <div id="thankyou-duration">-</div>
                </div>
            </div>

            <button id="return-home-btn" class="form-button bg-indigo-600 hover:bg-indigo-700 text-white font-medium">
                Return to Home
            </button>
        </div>
    </div>

    <!-- Load Leaflet JS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <script>
        // Service Worker and PWA Constants
        const SW_VERSION = '1.3.0';
        const CACHE_NAME = 'housing-survey-' + SW_VERSION;
        const OFFLINE_STORAGE_KEY = 'offline_survey_data';
        const SYNC_TRACKER_KEY = 'last_sync_timestamp';
        const FORM_STATE_KEY = 'current_form_state';
        const CURRENT_PAGE_KEY = 'current_form_page';
        
        // Form navigation
        const formPages = [
            document.getElementById('form-page-1'),
            document.getElementById('form-page-2'),
            document.getElementById('form-page-3')
        ];
        const locationSection = document.getElementById('location-section');
        const tagNumberSection = document.getElementById('tag-number-section');
        const locationDetails = document.getElementById('location-details');
        const thankYouPage = document.getElementById('thank-you-page');
        let currentPage = 0;

        const nextPage1Btn = document.getElementById('next-page-1-btn');
        const prevPage2Btn = document.getElementById('prev-page-2-btn');
        const nextPage2Btn = document.getElementById('next-page-2-btn');
        const prevPage3Btn = document.getElementById('prev-page-3-btn');
        const submitFormBtn = document.getElementById('submit-form-btn');
        const returnHomeBtn = document.getElementById('return-home-btn');
        const syncOfflineBtn = document.getElementById('sync-offline-btn');

        // Camera elements
        const cameraModal = document.getElementById('camera-modal');
        const cameraView = document.getElementById('camera-view');
        const cameraPreview = document.getElementById('camera-preview');
        const openCameraBtn = document.getElementById('open-camera-btn');
        const captureBtn = document.getElementById('capture-btn');
        const retakeBtn = document.getElementById('retake-btn');
        const confirmBtn = document.getElementById('confirm-btn');
        const switchCameraBtn = document.getElementById('switch-camera-btn');
        const photosContainer = document.getElementById('photos-container');
        const previewContext = cameraPreview.getContext('2d');
        let cameraStream = null;
        let capturedPhotos = [];
        let currentFacingMode = 'environment'; // Default to back camera

        // Signature pad elements
        const signaturePadCanvas = document.getElementById('signature-pad');
        const clearSignatureBtn = document.getElementById('clear-signature-btn');
        const saveSignatureBtn = document.getElementById('save-signature-btn');
        const savedSignatureImg = document.getElementById('saved-signature-img');
        let signaturePadContext = null;
        let drawing = false;

        // Location elements
        const getLocationBtn = document.getElementById('get-location-btn');
        const confirmLocationBtn = document.getElementById('confirm-location-btn');
        const locationText = document.getElementById('location-text');
        const locationStatus = document.getElementById('location-status');
        const locationAccuracy = document.getElementById('location-accuracy');
        const locationLoading = document.getElementById('location-loading');
        let map;
        let marker;
        let userLocation = null;
        let locationConfirmed = false;

        // Confirmation modal elements
        const confirmationModal = document.getElementById('confirmation-modal');
        const cancelSubmissionBtn = document.getElementById('cancel-submission-btn');
        const confirmSubmissionBtn = document.getElementById('confirm-submission-btn');

        // UD Code and TAG Number elements
        const udCodeInput = document.getElementById('ud-code');
        const tagNumberInput = document.getElementById('tag-number');
        const udCodeBarcode = document.getElementById('ud-code-barcode');
        const tagNumberBarcode = document.getElementById('tag-number-barcode');
        const surveyQrCode = document.getElementById('survey-qr-code');

        // Offline status elements
        const offlineStatus = document.getElementById('offline-status');
        const syncStatus = document.getElementById('sync-status');

        // ==================== SERVICE WORKER & PWA FUNCTIONS ====================

        // Register Service Worker
        function registerServiceWorker() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('✅ Service Worker registered successfully:', registration);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            console.log('🔄 New Service Worker found...');
                            
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    console.log('🎯 New content is available; please refresh.');
                                    showUpdateNotification();
                                }
                            });
                        });
                    })
                    .catch((registrationError) => {
                        console.log('❌ Service Worker registration failed: ', registrationError);
                    });

                // Listen for claiming of service worker
                let refreshing = false;
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    if (!refreshing) {
                        console.log('🔄 Controller changed, reloading page...');
                        window.location.reload();
                        refreshing = true;
                    }
                });
            } else {
                console.log('❌ Service Worker not supported');
            }
        }

        // Install PWA
        function initializePWA() {
            // Register Service Worker
            registerServiceWorker();
            
            // Add beforeinstallprompt event listener
            let deferredPrompt;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                showInstallPromotion();
            });
            
            // Track app installed event
            window.addEventListener('appinstalled', () => {
                console.log('🎉 PWA was installed');
                deferredPrompt = null;
            });
        }

        function showInstallPromotion() {
            // You can show a custom install button here
            console.log('📱 PWA installation available');
        }

        function showUpdateNotification() {
            if (confirm('🔄 A new version of the app is available. Refresh to update?')) {
                window.location.reload();
            }
        }

        // ==================== OFFLINE FUNCTIONALITY ====================

        // Initialize offline capability
        function initOfflineCapability() {
            // Initialize PWA
            initializePWA();
            
            // Check online status
            updateOnlineStatus();
            window.addEventListener('online', handleOnline);
            window.addEventListener('offline', handleOffline);
            
            // Restore form state on page load
            restoreFormState();
            
            // Auto-save form state periodically
            setInterval(saveFormState, 15000); // Save every 15 seconds
            
            // Save form state before page unload
            window.addEventListener('beforeunload', saveFormState);
            
            // Check for pending sync on page load
            checkPendingSync();
        }

        function handleOnline() {
            updateOnlineStatus();
            console.log('🌐 App is online');
            
            // Show online notification
            showOnlineNotification();
            
            // Try to sync offline data when coming online
            setTimeout(() => {
                const offlineData = getOfflineData();
                const unsyncedCount = offlineData.filter(item => !item.synced).length;
                
                if (unsyncedCount > 0) {
                    updateSyncStatus(`Found ${unsyncedCount} unsynced records. Click "Sync Offline Data" to sync.`, 'pending');
                }
            }, 2000);
        }

        function handleOffline() {
            updateOnlineStatus();
            console.log('📴 App is offline');
            showOfflineNotification();
        }

        function updateOnlineStatus() {
            if (navigator.onLine) {
                offlineStatus.textContent = '🌐 Online';
                offlineStatus.className = 'offline-status online';
            } else {
                offlineStatus.textContent = '📴 Offline';
                offlineStatus.className = 'offline-status offline';
            }
        }

        function showOnlineNotification() {
            // You can implement a toast notification here
            console.log('✅ Back online');
        }

        function showOfflineNotification() {
            // You can implement a toast notification here
            console.log('⚠️ Working offline - data will be saved locally');
        }

        // ==================== FORM STATE MANAGEMENT ====================

        // Enhanced form state management
        function saveFormState() {
            const formState = {
                currentPage: currentPage,
                formData: gatherFormData(),
                timestamp: new Date().toISOString(),
                version: SW_VERSION
            };
            
            try {
                localStorage.setItem(FORM_STATE_KEY, JSON.stringify(formState));
                console.log('💾 Form state saved');
            } catch (error) {
                console.error('❌ Error saving form state:', error);
                // Clear some space if quota exceeded
                manageStorage();
            }
        }

        function restoreFormState() {
            try {
                const savedState = localStorage.getItem(FORM_STATE_KEY);
                if (savedState) {
                    const state = JSON.parse(savedState);
                    
                    // Check if saved state is from current version
                    if (state.version !== SW_VERSION) {
                        console.log('🔄 Different version detected, clearing old state');
                        localStorage.removeItem(FORM_STATE_KEY);
                        return;
                    }
                    
                    // Restore current page
                    if (state.currentPage !== undefined && state.currentPage !== null) {
                        setTimeout(() => {
                            showPage(state.currentPage);
                        }, 500);
                    }
                    
                    // Restore form data if user confirms
                    if (state.formData && Object.keys(state.formData).length > 0) {
                        setTimeout(() => {
                            // Check if the data is already synced (you'll need to implement this check)
                            if (isDataAlreadySynced(state.formData)) {
                                console.log('✅ Data already synced, not showing restore prompt');
                                // Optionally clear the saved state since it's already synced
                                localStorage.removeItem(FORM_STATE_KEY);
                                return;
                            }
                            
                            // Check if the form already has data (optional additional check)
                            if (isFormAlreadyPopulated()) {
                                console.log('📝 Form already has data, not showing restore prompt');
                                localStorage.removeItem(FORM_STATE_KEY);
                                return;
                            }
                            
                            if (confirm('📋 Found previously saved form data. Would you like to restore it?')) {
                                populateFormData(state.formData);
                                updateSyncStatus('Form data restored from previous session', 'success');
                            } else {
                                // Clear saved state if user doesn't want to restore
                                localStorage.removeItem(FORM_STATE_KEY);
                            }
                        }, 1000);
                    }
                }
            } catch (error) {
                console.error('❌ Error restoring form state:', error);
                localStorage.removeItem(FORM_STATE_KEY);
            }
        }

        // Helper function to check if data is already synced
        function isDataAlreadySynced(formData) {
            // Implementation depends on your sync logic
            // Here are a few approaches:
            
            // 1. Check if form data matches what's already in the form
            const currentFormData = getCurrentFormData();
            return JSON.stringify(currentFormData) === JSON.stringify(formData);
            
            // 2. Check against a server-side sync timestamp (if you have one)
            // return formData.lastSyncTimestamp && Date.now() - formData.lastSyncTimestamp < SYNC_THRESHOLD;
            
            // 3. Check if the form has been submitted/synced already
            // return localStorage.getItem('form_submitted') === 'true';
        }
        
        // Helper function to get current form data
        function getCurrentFormData() {
            // Implement based on your form structure
            const formData = {};
            // Example: document.querySelectorAll('input, select, textarea').forEach(...)
            return formData;
        }
        
        // Helper function to check if form is already populated
        function isFormAlreadyPopulated() {
            // Check if any form field has a value
            const inputs = document.querySelectorAll('input, select, textarea');
            for (const input of inputs) {
                if (input.value && input.value.trim() !== '') {
                    return true;
                }
            }
            return false;
        }
        
        // Alternative: Add a flag to track if form was already restored
        let formRestored = false;
        
        // Modified restore function with the flag
        function restoreFormStateWithFlag() {
            if (formRestored) {
                console.log('✅ Form already restored in this session');
                return;
            }
            
            // ... rest of your restoreFormState code ...
            
            // When you successfully restore the form
            if (confirm('📋 Found previously saved form data. Would you like to restore it?')) {
                populateFormData(state.formData);
                formRestored = true; // Set the flag
                updateSyncStatus('Form data restored from previous session', 'success');
            }
        }

        function gatherFormData() {
            const formData = {};
            
            // Gather all form field values
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                const name = input.name || input.id;
                if (name && !name.includes('password')) { // Skip passwords
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        if (input.checked) {
                            formData[name] = input.value || true;
                        }
                    } else {
                        if (input.value) {
                            formData[name] = input.value;
                        }
                    }
                }
            });
            
            // Save photos and signature
            if (capturedPhotos.length > 0) {
                formData.capturedPhotos = capturedPhotos;
            }
            
            if (savedSignatureImg.src && !savedSignatureImg.classList.contains('hidden')) {
                formData.signature = savedSignatureImg.src;
            }
            
            // Save location data
            if (userLocation) {
                formData.userLocation = userLocation;
            }
            
            if (locationConfirmed) {
                formData.locationConfirmed = locationConfirmed;
            }
            
            // Save member data
            const members = gatherMemberData();
            if (members.length > 0) {
                formData.members = members;
            }
            
            return formData;
        }

        function gatherMemberData() {
            const members = [];
            const memberRows = document.querySelectorAll('#member-table-body tr');
            
            memberRows.forEach((row) => {
                const inputs = row.querySelectorAll('input, select');
                const member = {};
                
                inputs.forEach(input => {
                    const name = input.name || input.id;
                    if (name && input.value) {
                        member[name] = input.value;
                    }
                });
                
                if (Object.keys(member).length > 0) {
                    members.push(member);
                }
            });
            
            return members;
        }

        function populateFormData(formData) {
            // Restore form field values
            Object.keys(formData).forEach(key => {
                if (key === 'capturedPhotos' || key === 'signature' || key === 'userLocation' || key === 'locationConfirmed' || key === 'members') {
                    return; // Handle these separately
                }
                
                const element = document.querySelector(`[name="${key}"]`) || document.getElementById(key);
                
                if (element) {
                    if (element.type === 'checkbox' || element.type === 'radio') {
                        if (formData[key] === true || formData[key] === element.value) {
                            element.checked = true;
                        }
                    } else {
                        element.value = formData[key];
                    }
                }
            });
            
            // Restore photos
            if (formData.capturedPhotos) {
                capturedPhotos = formData.capturedPhotos;
                renderPhotos();
            }
            
            // Restore signature
            if (formData.signature) {
                savedSignatureImg.src = formData.signature;
                savedSignatureImg.classList.remove('hidden');
            }
            
            // Restore location
            if (formData.userLocation) {
                userLocation = formData.userLocation;
                placeMarker(userLocation);
                updateLocationText(userLocation);
            }
            
            if (formData.locationConfirmed) {
                locationConfirmed = formData.locationConfirmed;
                confirmLocationBtn.classList.add('confirmed');
                locationDetails.classList.remove('hidden');
                if (marker) {
                    marker.dragging.disable();
                }
            }
            
            // Restore members
            if (formData.members && formData.members.length > 0) {
                restoreMemberData(formData.members);
            }
            
            // Regenerate barcodes if needed
            generateBarcodes();
        }

        function restoreMemberData(members) {
            // Clear existing members except the first row
            const memberRows = document.querySelectorAll('#member-table-body tr');
            for (let i = memberRows.length - 1; i > 0; i--) {
                memberRows[i].remove();
            }
            
            // Restore members starting from index 1 (skip household head which is already there)
            for (let i = 1; i < members.length; i++) {
                addMemberRow();
            }
            
            // Populate all member rows
            const allRows = document.querySelectorAll('#member-table-body tr');
            members.forEach((member, index) => {
                if (index < allRows.length) {
                    const row = allRows[index];
                    Object.keys(member).forEach(key => {
                        const input = row.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = member[key];
                        }
                    });
                }
            });
        }

        // ==================== OFFLINE DATA STORAGE ====================

        function saveOfflineData(formData) {
            const offlineData = getOfflineData();
            const localId = 'local_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Add local ID and timestamp
            formData.local_id = localId;
            formData.local_timestamp = new Date().toISOString();
            formData.synced = false;
            formData.version = SW_VERSION;
            
            offlineData.push(formData);
            
            try {
                localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(offlineData));
                console.log('💾 Offline data saved:', localId);
                
                // Manage storage to prevent quota exceeded
                manageStorage();
                
                return localId;
            } catch (error) {
                console.error('❌ Error saving offline data:', error);
                // Fallback: try to save with reduced data size
                const simplifiedData = simplifyDataForStorage(formData);
                const fallbackData = [simplifiedData];
                localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(fallbackData));
                return localId;
            }
        }

        function simplifyDataForStorage(formData) {
            // Create a simplified version for storage if quota is exceeded
            const simplified = { ...formData };
            
            // Reduce photo quality for storage (basic implementation)
            if (simplified.photos && simplified.photos.length > 0) {
                console.log('📸 Compressing photos for storage...');
                // In a real implementation, you would compress images here
            }
            
            return simplified;
        }

        function getOfflineData() {
            try {
                const data = localStorage.getItem(OFFLINE_STORAGE_KEY);
                if (data) {
                    const parsedData = JSON.parse(data);
                    // Filter out old version data if needed
                    return parsedData.filter(item => item.version === SW_VERSION);
                }
                return [];
            } catch (error) {
                console.error('❌ Error reading offline data:', error);
                return [];
            }
        }

        function removeOfflineData(localId) {
            const offlineData = getOfflineData();
            const updatedData = offlineData.filter(item => item.local_id !== localId);
            localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(updatedData));
        }

        // Storage management
        function manageStorage() {
            const offlineData = getOfflineData();
            const MAX_STORAGE_ITEMS = 100; // Keep last 100 items
            
            if (offlineData.length > MAX_STORAGE_ITEMS) {
                // Remove oldest items
                const sortedData = offlineData.sort((a, b) => 
                    new Date(a.local_timestamp) - new Date(b.local_timestamp)
                );
                
                const itemsToKeep = sortedData.slice(-MAX_STORAGE_ITEMS);
                localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(itemsToKeep));
                console.log(`🧹 Cleaned up ${offlineData.length - itemsToKeep.length} old items`);
            }
            
            // Also clean up old form states
            const formState = localStorage.getItem(FORM_STATE_KEY);
            if (formState) {
                try {
                    const state = JSON.parse(formState);
                    const stateAge = new Date() - new Date(state.timestamp);
                    const MAX_STATE_AGE = 24 * 60 * 60 * 1000; // 24 hours
                    
                    if (stateAge > MAX_STATE_AGE) {
                        localStorage.removeItem(FORM_STATE_KEY);
                        console.log('🧹 Cleared old form state');
                    }
                } catch (e) {
                    localStorage.removeItem(FORM_STATE_KEY);
                }
            }
        }

        // ==================== SYNC FUNCTIONALITY ====================

        function updateSyncStatus(message, type = 'pending') {
            syncStatus.textContent = message;
            syncStatus.className = `sync-status ${type}`;
            syncStatus.classList.remove('hidden');
        }

        // I-update ang syncOfflineData function
function syncOfflineData() {
    if (!navigator.onLine) {
        updateSyncStatus('❌ Cannot sync while offline', 'error');
        return;
    }
    
    const offlineData = getOfflineData();
    const unsyncedData = offlineData.filter(item => !item.synced);
    
    if (unsyncedData.length === 0) {
        updateSyncStatus('✅ All data is synced with the server.', 'success');
        return;
    }
    
    // Check if we already attempted sync recently (within 30 seconds)
    const lastSync = localStorage.getItem(SYNC_TRACKER_KEY);
    const now = Date.now();
    if (lastSync && (now - parseInt(lastSync)) < 30000) {
        updateSyncStatus('⏳ Sync already in progress or recently completed.', 'pending');
        return;
    }
    
    // Set sync timestamp
    localStorage.setItem(SYNC_TRACKER_KEY, now.toString());
    
    updateSyncStatus(`🔄 Syncing ${unsyncedData.length} offline records...`, 'pending');
    
    // Prepare data for sync
    const syncData = unsyncedData.map(item => {
        const { local_id, local_timestamp, synced, version, ...cleanData } = item;
        return {
            ...cleanData,
            local_id: local_id // Keep local_id for reference
        };
    });
    
    // Send sync request
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `sync_offline_data=true&offline_data=${encodeURIComponent(JSON.stringify(syncData))}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.results) {
            let successCount = 0;
            let errorCount = 0;
            let duplicateCount = 0;
            
            // Get current offline data again to ensure we have the latest
            const currentOfflineData = getOfflineData();
            let updatedOfflineData = [...currentOfflineData];
            
            data.results.forEach(result => {
                if (result.success) {
                    // REMOVE the successfully synced record instead of just marking it
                    updatedOfflineData = updatedOfflineData.filter(item => item.local_id !== result.local_id);
                    successCount++;
                } else if (result.error && result.error.includes('Duplicate')) {
                    // Remove duplicate records as well
                    updatedOfflineData = updatedOfflineData.filter(item => item.local_id !== result.local_id);
                    duplicateCount++;
                    console.log('🗑️ Removed duplicate record:', result.local_id);
                } else {
                    errorCount++;
                    console.error('❌ Sync error for local_id:', result.local_id, result.error);
                }
            });
            
            // Save the updated offline data (with synced records REMOVED)
            localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(updatedOfflineData));
            
            // Clear sync tracker on success
            localStorage.removeItem(SYNC_TRACKER_KEY);
            
            let message = '';
            if (successCount > 0) {
                message += `✅ Successfully synced ${successCount} records. `;
            }
            if (duplicateCount > 0) {
                message += `⚠️ Removed ${duplicateCount} duplicate records. `;
            }
            if (errorCount > 0) {
                message += `❌ ${errorCount} records failed to sync.`;
            }
            
            if (message === '') {
                message = '✅ Sync completed.';
            }
            
            updateSyncStatus(message, errorCount > 0 ? 'error' : 'success');
            
            // Auto-hide success message after 5 seconds if no errors
            if (errorCount === 0) {
                setTimeout(() => {
                    syncStatus.classList.add('hidden');
                }, 5000);
            }
        } else {
            updateSyncStatus('❌ Sync failed: Invalid response from server.', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Sync error:', error);
        updateSyncStatus('❌ Sync failed: Could not connect to server.', 'error');
        // Clear sync tracker on error to allow retry
        localStorage.removeItem(SYNC_TRACKER_KEY);
    });
}

// I-update din ang checkPendingSync function
function checkPendingSync() {
    const offlineData = getOfflineData();
    const unsyncedCount = offlineData.filter(item => !item.synced).length;
    
    if (unsyncedCount > 0) {
        updateSyncStatus(`📋 You have ${unsyncedCount} unsynced records. Click "Sync Offline Data" to sync.`, 'pending');
    } else {
        // Clear sync status if no unsynced records
        syncStatus.classList.add('hidden');
    }
}

// Dagdag ng function para i-clear ang lahat ng offline data (optional, for debugging)
function clearAllOfflineData() {
    if (confirm('🗑️ Are you sure you want to clear ALL offline data? This cannot be undone.')) {
        localStorage.removeItem(OFFLINE_STORAGE_KEY);
        updateSyncStatus('✅ All offline data cleared.', 'success');
        setTimeout(() => {
            syncStatus.classList.add('hidden');
        }, 3000);
    }
}

// I-update ang getOfflineData function para mas robust
function getOfflineData() {
    try {
        const data = localStorage.getItem(OFFLINE_STORAGE_KEY);
        if (data) {
            const parsedData = JSON.parse(data);
            // Filter out old version data and ensure we have valid records
            return parsedData.filter(item => 
                item && 
                item.version === SW_VERSION && 
                item.local_id && 
                typeof item.synced === 'boolean'
            );
        }
        return [];
    } catch (error) {
        console.error('❌ Error reading offline data:', error);
        // If there's corrupted data, clear it
        localStorage.removeItem(OFFLINE_STORAGE_KEY);
        return [];
    }
}
        // ==================== SIGNATURE PAD ====================

        // Initialize signature pad
        function initializeSignaturePad() {
            signaturePadCanvas.width = signaturePadCanvas.offsetWidth;
            signaturePadCanvas.height = signaturePadCanvas.offsetHeight;
            
            signaturePadContext = signaturePadCanvas.getContext('2d');
            signaturePadContext.fillStyle = '#FFFFFF';
            signaturePadContext.fillRect(0, 0, signaturePadCanvas.width, signaturePadCanvas.height);
            signaturePadContext.strokeStyle = '#000000';
            signaturePadContext.lineWidth = 2.5;
            signaturePadContext.lineCap = 'round';
            signaturePadContext.lineJoin = 'round';
        }

        // Handle window resizing
        function resizeSignaturePad() {
            const canvas = signaturePadCanvas;
            const container = canvas.parentElement;
            const imageData = signaturePadContext.getImageData(0, 0, canvas.width, canvas.height);
            
            canvas.width = container.offsetWidth;
            canvas.height = container.offsetHeight;
            
            signaturePadContext.putImageData(imageData, 0, 0);
        }

        // ==================== MAP FUNCTIONALITY ====================

        // Initialize map centered on Pasay City
        function initMap() {
            // Default to Pasay City coordinates
            const defaultLat = 14.5378;
            const defaultLng = 121.0014;
            
            map = L.map('map').setView([defaultLat, defaultLng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Add click event to place marker
            map.on('click', function(e) {
                if (!locationConfirmed) {
                    placeMarker(e.latlng);
                    updateLocationText(e.latlng);
                } else {
                    alert('📍 Location is already confirmed. To change your location, please contact the administrator.');
                }
            });
            
            // Try to get device location automatically
            getDeviceLocation();
        }

        function placeMarker(latlng) {
            if (marker) {
                map.removeLayer(marker);
            }
            
            marker = L.marker(latlng, {
                draggable: !locationConfirmed
            }).addTo(map)
                .bindPopup('You are here.')
                .openPopup();
            
            userLocation = latlng;
            
            // Update position when marker is dragged
            marker.on('dragend', function(e) {
                if (!locationConfirmed) {
                    userLocation = e.target.getLatLng();
                    updateLocationText(userLocation);
                }
            });
        }

        function updateLocationText(latlng) {
            // Show coordinates
            locationText.innerHTML = `<span id="location-status">Selected location:</span> Latitude: ${latlng.lat.toFixed(6)}, Longitude: ${latlng.lng.toFixed(6)}`;
            
            // Reverse geocode to get address
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`)
                .then(response => response.json())
                .then(data => {
                    const address = data.display_name || 'Selected location';
                    locationText.innerHTML = `<span id="location-status">Selected location:</span> ${address}`;
                })
                .catch(error => {
                    console.error('Error fetching address:', error);
                });
        }

        function getDeviceLocation() {
            locationLoading.classList.remove('hidden');
            getLocationBtn.disabled = true;
            locationStatus.textContent = "Getting your location...";
            locationAccuracy.textContent = "";
            
            if (!navigator.geolocation) {
                locationStatus.textContent = "Geolocation is not supported by your browser";
                locationLoading.classList.add('hidden');
                getLocationBtn.disabled = false;
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const latlng = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    
                    // Place marker at device location
                    placeMarker(latlng);
                    map.setView(latlng, 18); // Zoom in closer for accuracy
                    
                    // Update status
                    locationStatus.textContent = "Device location found:";
                    
                    // Show accuracy information
                    const accuracy = position.coords.accuracy;
                    let accuracyText = "";
                    let accuracyClass = "";
                    
                    if (accuracy < 50) {
                        accuracyText = `(High accuracy: within ${Math.round(accuracy)} meters)`;
                        accuracyClass = "high";
                    } else if (accuracy < 200) {
                        accuracyText = `(Medium accuracy: within ${Math.round(accuracy)} meters)`;
                        accuracyClass = "medium";
                    } else {
                        accuracyText = `(Low accuracy: within ${Math.round(accuracy)} meters)`;
                        accuracyClass = "low";
                    }
                    
                    locationAccuracy.textContent = accuracyText;
                    locationAccuracy.className = `location-accuracy ${accuracyClass}`;
                    
                    locationLoading.classList.add('hidden');
                    getLocationBtn.disabled = false;
                    
                    // Auto-confirm if accuracy is good
                    if (accuracy < 100) {
                        confirmLocation();
                    }
                },
                function(error) {
                    let errorMessage = "Error getting location: ";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += "Permission denied. Please manually select your location.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += "Location information unavailable. Please manually select your location.";
                            break;
                        case error.TIMEOUT:
                            errorMessage += "The request to get location timed out. Please manually select your location.";
                            break;
                        case error.UNKNOWN_ERROR:
                            errorMessage += "An unknown error occurred. Please manually select your location.";
                            break;
                    }
                    
                    locationStatus.textContent = errorMessage;
                    locationLoading.classList.add('hidden');
                    getLocationBtn.disabled = false;
                    
                    // Only show GPS error message, no manual option
locationStatus.textContent = "Location access denied. Please enable location services and try again.";
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }

        function confirmLocation() {
            if (!marker) {
                alert('📍 Please select your location on the map first');
                return;
            }
            
            if (confirm('📍 Location confirmed. You will not be able to change this location after confirmation. To make changes, please contact the administrator.')) {
                confirmLocationBtn.classList.add('confirmed');
                locationConfirmed = true;
                marker.dragging.disable();
                
                // Show confirmed location details
                document.getElementById('confirmed-coords').textContent = 
                    `Latitude: ${userLocation.lat.toFixed(6)}, Longitude: ${userLocation.lng.toFixed(6)}`;
                document.getElementById('confirmed-address').textContent = locationText.textContent.replace('Selected location:', '').trim();
                locationDetails.classList.remove('hidden');
                
                // Save form state after location confirmation
                saveFormState();
            }
        }

        // ==================== UTILITY FUNCTIONS ====================

        // Calculate age from birthdate
        function calculateAge(birthdate) {
            const birthDate = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age;
        }

        // Reset form to default state
        function resetForm() {
            // Reset form fields
            document.getElementById('survey-type').value = '';
            document.getElementById('other-survey-type').value = '';
            document.getElementById('other-survey-type').classList.add('hidden');
            document.getElementById('other-survey-type').required = false;

            document.getElementById('hh-surname').value = '';
            document.getElementById('hh-firstname').value = '';
            document.getElementById('hh-middlename').value = '';
            document.getElementById('hh-mi').value = '';
            document.getElementById('spouse-surname').value = '';
            document.getElementById('spouse-firstname').value = '';
            document.getElementById('spouse-middlename').value = '';
            document.getElementById('spouse-mi').value = '';
            
            // Reset household head data
            document.getElementById('hh-sex').value = '';
            document.getElementById('hh-age').value = '';
            document.getElementById('hh-birthdate').value = '';
            document.querySelector('input[name="hh-civil-status"]').checked = true;
            document.getElementById('hh-senior-citizen').checked = false;
            document.getElementById('hh-pwd').checked = false;
            
            // Reset spouse data
            document.getElementById('spouse-sex').value = '';
            document.getElementById('spouse-age').value = '';
            document.getElementById('spouse-birthdate').value = '';
            document.querySelector('input[name="spouse-civil-status"]').checked = true;
            document.getElementById('spouse-senior-citizen').checked = false;
            document.getElementById('spouse-pwd').checked = false;
            
            // Reset address fields
            document.getElementById('house-no').value = '';
            document.getElementById('lot-no').value = '';
            document.getElementById('building').value = '';
            document.getElementById('block').value = '';
            document.getElementById('street').value = '';
            document.getElementById('barangay').value = '';
            document.getElementById('name-rfo-renter').value = '';
            
            // Reset membership checkboxes
            document.getElementById('pagibig').checked = false;
            document.getElementById('sss').checked = false;
            document.getElementById('gsis').checked = false;
            document.getElementById('philhealth').checked = false;
            document.getElementById('none-fund').checked = false;
            document.getElementById('other-fund').checked = false;
            document.getElementById('cso').checked = false;
            document.getElementById('hoa').checked = false;
            document.getElementById('cooperative').checked = false;
            document.getElementById('none-org').checked = false;
            document.getElementById('other-org').checked = false;
            document.getElementById('name-organization').value = '';
            
            // Reset remarks checkboxes
            document.getElementById('security-upgrading').checked = false;
            document.getElementById('shelter-provision').checked = false;
            document.getElementById('structural-upgrading').checked = false;
            document.getElementById('infrastructure-upgrading').checked = false;
            document.getElementById('other-remarks-checkbox').checked = false;
            document.getElementById('other-remarks-text').value = '';
            document.getElementById('other-remarks-text').classList.add('hidden');
            document.getElementById('single-hh').checked = false;
            document.getElementById('displaced-unit').checked = false;
            document.getElementById('doubled-up').checked = false;
            document.getElementById('displacement-concern').checked = false;
            document.getElementById('odc').checked = false;
            document.getElementById('aho').checked = false;
            document.getElementById('census-others-checkbox').checked = false;
            document.getElementById('census-others-text').value = '';
            document.getElementById('census-others-text').classList.add('hidden');
            
            // Reset confirmation checkboxes
            document.getElementById('data-accuracy-checkbox').checked = false;
            document.getElementById('privacy-policy-checkbox').checked = false;
            
            // Reset location
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
            locationConfirmed = false;
            confirmLocationBtn.classList.remove('confirmed');
            locationDetails.classList.add('hidden');
            locationStatus.textContent = "Getting your location...";
            locationAccuracy.textContent = "";
            manualLocationDiv.classList.add('hidden');
            
            // Reset photos and signature
            capturedPhotos = [];
            renderPhotos();
            initializeSignaturePad();
            savedSignatureImg.src = '';
            savedSignatureImg.classList.add('hidden');
            
            // Reset member table
            memberTableBody.innerHTML = '';
            addMemberRow(); // Add initial row for household head
            
            // Reset UD Code and TAG Number
            document.getElementById('ud-code').value = '';
            document.getElementById('tag-number').value = '';
            document.getElementById('ud-code-barcode').innerHTML = '';
            document.getElementById('tag-number-barcode').innerHTML = '';
            document.getElementById('survey-qr-code').innerHTML = '';
            
            // Clear saved form state
            localStorage.removeItem(FORM_STATE_KEY);
            
            // Get new location
            getDeviceLocation();
        }

        // ==================== CAMERA FUNCTIONS ====================

        // Camera functions
        async function startCamera(facingMode = 'environment') {
            try {
                if (cameraStream) {
                    stopCamera();
                }
                
                cameraStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: { ideal: 480 },
                        height: { ideal: 272 },
                        facingMode: facingMode 
                    } 
                });
                
                cameraView.srcObject = cameraStream;
                cameraModal.style.display = 'flex';
                cameraView.classList.remove('hidden');
                cameraPreview.classList.add('hidden');
                captureBtn.style.display = 'flex';
                retakeBtn.style.display = 'none';
                confirmBtn.style.display = 'none';
            } catch (err) {
                console.error("❌ Error accessing camera: ", err);
                alert("📷 Could not access camera. Please ensure camera permissions are granted.");
            }
        }

        function stopCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            cameraModal.style.display = 'none';
        }

        function capturePhoto() {
            cameraPreview.width = cameraView.videoWidth;
            cameraPreview.height = cameraView.videoHeight;
            previewContext.drawImage(cameraView, 0, 0, cameraPreview.width, cameraPreview.height);
            
            cameraView.classList.add('hidden');
            cameraPreview.classList.remove('hidden');
            captureBtn.style.display = 'none';
            retakeBtn.style.display = 'flex';
            confirmBtn.style.display = 'flex';
        }

        function retakePhoto() {
            cameraView.classList.remove('hidden');
            cameraPreview.classList.add('hidden');
            captureBtn.style.display = 'flex';
            retakeBtn.style.display = 'none';
            confirmBtn.style.display = 'none';
        }

        function confirmPhoto() {
            const dataURL = cameraPreview.toDataURL('image/png');
            capturedPhotos.push(dataURL);
            renderPhotos();
            stopCamera();
            
            // Save form state after taking photo
            saveFormState();
        }

        function switchCamera() {
            currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
            startCamera(currentFacingMode);
        }

        // Delete photo function
        function deletePhoto(index) {
            capturedPhotos.splice(index, 1);
            renderPhotos();
            
            // Save form state after deleting photo
            saveFormState();
        }

        // Render all captured photos
        function renderPhotos() {
            photosContainer.innerHTML = '';
            capturedPhotos.forEach((photo, index) => {
                const photoContainer = document.createElement('div');
                photoContainer.className = 'photo-container';
                
                const img = document.createElement('img');
                img.src = photo;
                img.className = 'photo-thumbnail';
                
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'delete-photo-btn';
                deleteBtn.innerHTML = '×';
                deleteBtn.onclick = () => deletePhoto(index);
                
                photoContainer.appendChild(img);
                photoContainer.appendChild(deleteBtn);
                photosContainer.appendChild(photoContainer);
            });
        }

        // ==================== BARCODE & QR CODE ====================

        // Generate barcode for UD Code and TAG Number
        function generateBarcodes() {
            const udCode = document.getElementById('ud-code').value;
            const tagNumber = document.getElementById('tag-number').value;
            
            if (udCode) {
                JsBarcode("#ud-code-barcode", udCode, {
                    format: "CODE128",
                    lineColor: "#000",
                    width: 2,
                    height: 50,
                    displayValue: false
                });
            }
            
            if (tagNumber) {
                JsBarcode("#tag-number-barcode", tagNumber, {
                    format: "CODE128",
                    lineColor: "#000",
                    width: 2,
                    height: 50,
                    displayValue: false
                });
            }
            
            if (udCode && tagNumber) {
                const qrCodeData = `UD Code: ${udCode}\nTAG Number: ${tagNumber}\nSurvey Date: ${new Date().toLocaleDateString()}`;
                QRCode.toCanvas(document.getElementById('survey-qr-code'), qrCodeData, {
                    width: 150,
                    margin: 1,
                    color: {
                        dark: "#000000",
                        light: "#ffffff"
                    }
                }, function(error) {
                    if (error) console.error(error);
                });
            }
        }

        // ==================== FORM NAVIGATION ====================

        // Form navigation functions
        function showPage(pageIndex) {
            // Save current state before navigating
            saveFormState();
            
            console.log(`📄 Showing page ${pageIndex}`);
            
            // Hide thank you page when showing other pages
            thankYouPage.classList.add('hidden');
            
            // Hide all pages
            formPages.forEach((page, i) => {
                page.classList.add('hidden');
            });
            
            // Show the requested page
            formPages[pageIndex].classList.remove('hidden');
            
            currentPage = pageIndex;
            
            // Show location and TAG number sections only on first page
            if (pageIndex === 0) {
                locationSection.classList.remove('hidden');
                tagNumberSection.classList.remove('hidden');
                if (locationConfirmed) {
                    locationDetails.classList.remove('hidden');
                }
            } else {
                locationSection.classList.add('hidden');
                tagNumberSection.classList.add('hidden');
                locationDetails.classList.add('hidden');
            }
            
            // Initialize signature pad when showing page 3
            if (pageIndex === 2) {
                setTimeout(() => {
                    initializeSignaturePad();
                }, 100);
            }
            
            // Save current page
            localStorage.setItem(CURRENT_PAGE_KEY, pageIndex.toString());
        }

        function nextPage() {
            if (currentPage < formPages.length - 1) {
                showPage(currentPage + 1);
            }
        }

        function prevPage() {
            if (currentPage > 0) {
                showPage(currentPage - 1);
            }
        }

        // ==================== EVENT LISTENERS ====================

        // Event listeners for form navigation
        nextPage1Btn.addEventListener('click', nextPage);
        prevPage2Btn.addEventListener('click', prevPage);
        nextPage2Btn.addEventListener('click', nextPage);
        prevPage3Btn.addEventListener('click', prevPage);

        // Camera event listeners
        openCameraBtn.addEventListener('click', () => startCamera(currentFacingMode));
        captureBtn.addEventListener('click', capturePhoto);
        retakeBtn.addEventListener('click', retakePhoto);
        confirmBtn.addEventListener('click', confirmPhoto);
        switchCameraBtn.addEventListener('click', switchCamera);

        // Signature pad functionality
        function getCanvasPoint(canvas, clientX, clientY) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        signaturePadCanvas.addEventListener('mousedown', (e) => {
            drawing = true;
            const pos = getCanvasPoint(signaturePadCanvas, e.clientX, e.clientY);
            signaturePadContext.beginPath();
            signaturePadContext.moveTo(pos.x, pos.y);
            savedSignatureImg.classList.add('hidden');
        });

        signaturePadCanvas.addEventListener('mouseup', () => {
            drawing = false;
            signaturePadContext.closePath();
            
            // Save form state after drawing signature
            saveFormState();
        });

        signaturePadCanvas.addEventListener('mousemove', (e) => {
            if (!drawing) return;
            const pos = getCanvasPoint(signaturePadCanvas, e.clientX, e.clientY);
            signaturePadContext.lineTo(pos.x, pos.y);
            signaturePadContext.stroke();
        });

        signaturePadCanvas.addEventListener('mouseleave', () => {
            if (drawing) {
                drawing = false;
                signaturePadContext.closePath();
            }
        });

        // Touch events for mobile
        signaturePadCanvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            drawing = true;
            const touch = e.touches[0];
            const pos = getCanvasPoint(signaturePadCanvas, touch.clientX, touch.clientY);
            signaturePadContext.beginPath();
            signaturePadContext.moveTo(pos.x, pos.y);
            savedSignatureImg.classList.add('hidden');
        });

        signaturePadCanvas.addEventListener('touchend', () => {
            drawing = false;
            signaturePadContext.closePath();
            
            // Save form state after drawing signature
            saveFormState();
        });

        signaturePadCanvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (!drawing) return;
            const touch = e.touches[0];
            const pos = getCanvasPoint(signaturePadCanvas, touch.clientX, touch.clientY);
            signaturePadContext.lineTo(pos.x, pos.y);
            signaturePadContext.stroke();
        });

        // Clear signature button
        clearSignatureBtn.addEventListener('click', () => {
            initializeSignaturePad();
            savedSignatureImg.classList.add('hidden');
            
            // Save form state after clearing signature
            saveFormState();
        });

        // Save signature button
        saveSignatureBtn.addEventListener('click', () => {
            if (signaturePadContext.getImageData(0, 0, signaturePadCanvas.width, signaturePadCanvas.height).data.every(channel => channel === 255)) {
                alert("✍️ Signature pad is empty! Please draw your signature.");
                return;
            }
            const dataURL = signaturePadCanvas.toDataURL('image/png');
            savedSignatureImg.src = dataURL;
            savedSignatureImg.classList.remove('hidden');
            
            // Save form state after saving signature
            saveFormState();
        });

        // Location confirmation button
        confirmLocationBtn.addEventListener('click', confirmLocation);
        
        

        // Confirmation modal
        submitFormBtn.addEventListener('click', () => {
            // Validate required fields
            const requiredFields = document.querySelectorAll('[required]');
            let isValid = true;
            let firstInvalidField = null;
            
            requiredFields.forEach(field => {
                if (!field.value) {
                    field.style.borderColor = 'red';
                    isValid = false;
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                alert('❌ Please fill in all required fields marked with *');
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
                return;
            }
            
            if (!marker) {
                alert('📍 Please select your location on the map');
                return;
            }
            
            if (capturedPhotos.length === 0) {
                alert('📷 Please capture at least one photo');
                return;
            }
            
            if (!savedSignatureImg.src || savedSignatureImg.classList.contains('hidden')) {
                alert('✍️ Please provide your signature');
                return;
            }
            
            if (!document.getElementById('data-accuracy-checkbox').checked) {
                alert('✅ Please confirm that all data is accurate');
                return;
            }
            
            if (!document.getElementById('privacy-policy-checkbox').checked) {
                alert('🔒 Please agree to the privacy policy');
                return;
            }
            
            // Show confirmation modal
            confirmationModal.style.display = 'flex';
        });

        // ==================== FORM SUBMISSION ====================

        // Submit form data (online or offline)
        function submitFormData(formData) {
            if (navigator.onLine) {
                // Submit directly to server
                submitOnline(formData);
            } else {
                // Save offline and show success message
                const localId = saveOfflineData(formData);
                showOfflineSuccess(localId, formData.tag_number);
            }
        }

        function submitOnline(formData) {
            const formDataObj = new FormData();
            for (const key in formData) {
                if (formData.hasOwnProperty(key)) {
                    if (key === 'photos' || key === 'answers') {
                        formDataObj.append(key, JSON.stringify(formData[key]));
                    } else {
                        formDataObj.append(key, formData[key]);
                    }
                }
            }
            formDataObj.append('submit_survey', 'true');
            
            updateSyncStatus('🔄 Submitting form data...', 'pending');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formDataObj
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateSyncStatus('✅ Form submitted successfully!', 'success');
                    showSuccessPage(data.tag_number);
                } else {
                    updateSyncStatus('❌ Error submitting form: ' + (data.error || 'Unknown error'), 'error');
                    alert('❌ Error submitting form: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('❌ Error:', error);
                updateSyncStatus('❌ Network error, saving offline...', 'error');
                // If online submission fails, save offline
                const localId = saveOfflineData(formData);
                showOfflineSuccess(localId, formData.tag_number);
            });
        }

        function showSuccessPage(tagNumber) {
            // Hide all form pages and show thank you page
            formPages.forEach(page => page.classList.add('hidden'));
            thankYouPage.classList.remove('hidden');
            
            // Display the final TAG number
            document.getElementById('final-tag-number').textContent = tagNumber;
            
            // Generate barcode
            JsBarcode("#final-tag-barcode", tagNumber, {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 50,
                displayValue: false
            });
            
            // Update completion time displays with proper formatting
            const now = new Date();
            const formattedEndTime = now.toLocaleString('en-PH', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            
            document.getElementById('survey-end-time').textContent = formattedEndTime;
            document.getElementById('thankyou-completion-time').textContent = formattedEndTime;
            
            // Calculate and display duration
            const startTimeStr = document.getElementById('survey-start-time').textContent;
            const duration = formatDuration(startTimeStr, now);
            document.getElementById('thankyou-duration').textContent = duration;
            
            // Clear form state after successful submission
            localStorage.removeItem(FORM_STATE_KEY);
            localStorage.removeItem(CURRENT_PAGE_KEY);
        }

        function showOfflineSuccess(localId, tagNumber) {
            // Hide all form pages and show thank you page
            formPages.forEach(page => page.classList.add('hidden'));
            thankYouPage.classList.remove('hidden');
            
            // Display the final TAG number
            document.getElementById('final-tag-number').textContent = tagNumber + ' (Offline)';
            
            // Generate barcode
            JsBarcode("#final-tag-barcode", tagNumber, {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 50,
                displayValue: false
            });
            
            // Update completion time displays with proper formatting
            const now = new Date();
            const formattedEndTime = now.toLocaleString('en-PH', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            
            document.getElementById('survey-end-time').textContent = formattedEndTime + ' (Offline)';
            document.getElementById('thankyou-completion-time').textContent = formattedEndTime + ' (Offline)';
            
            // Calculate and display duration
            const startTimeStr = document.getElementById('survey-start-time').textContent;
            const duration = formatDuration(startTimeStr, now);
            document.getElementById('thankyou-duration').textContent = duration;
            
            // Show offline message
            const successMessage = document.createElement('div');
            successMessage.className = 'sync-status pending';
            successMessage.textContent = '💾 Data saved offline. It will be synced when you are online.';
            document.querySelector('.bg-gray-50').appendChild(successMessage);
            
            // Clear form state after offline submission
            localStorage.removeItem(FORM_STATE_KEY);
            localStorage.removeItem(CURRENT_PAGE_KEY);
        }

        confirmSubmissionBtn.addEventListener('click', () => {
            confirmationModal.style.display = 'none';

            const tagNumber = document.getElementById('tag-number').value;
            const tagNumberRegex = /^\d{4}-\d{3}-\d{3}$/;
            
            if (!tagNumberRegex.test(tagNumber)) {
                alert('❌ Please enter a valid TAG number in the format YEAR-BRGY No.-000 (e.g., 2023-000-001)');
                return;
            }
            
            // Gather all form data
            const formData = {
                ud_code: document.getElementById('ud-code').value,
                tag_number: tagNumber,
                address: document.getElementById('street').value,
                barangay: document.getElementById('barangay').value,
                city: document.getElementById('city').value,
                region: document.getElementById('region').value,
                location_lat: userLocation.lat,
                location_lng: userLocation.lng,
                photos: capturedPhotos,
                signature: savedSignatureImg.src,
                survey_date: new Date().toISOString().split('T')[0],
                survey_time: new Date().toTimeString().split(' ')[0],
                answers: {}
            };
            
            // Add all form fields to answers
            const allInputs = document.querySelectorAll('input, select, textarea');
            allInputs.forEach(element => {
                const fieldName = element.name || element.id;
                
                // Skip excluded fields
                if (!fieldName || ['ud_code', 'tag_number', 'address', 'barangay', 'city', 'region', 'location_lat', 'location_lng', 'photos', 'signature', 'submit_survey'].includes(fieldName)) {
                    return;
                }
                
                // Handle different input types
                if (element.type === 'radio') {
                    if (element.checked) {
                        formData.answers[fieldName] = element.value;
                    }
                }
                else if (element.type === 'checkbox') {
                    if (element.checked) {
                        formData.answers[fieldName] = element.value || 'true';
                    }
                }
                else {
                    if (element.value) {
                        formData.answers[fieldName] = element.value;
                    }
                }
            });
            
            // Add household members data
            const memberRows = document.querySelectorAll('#member-table-body tr');
            const members = [];
            memberRows.forEach((row, index) => {
                const inputs = row.querySelectorAll('input, select');
                const member = {};
                inputs.forEach(input => {
                    const fieldName = input.name || input.id;
                    if (fieldName && input.value) {
                        member[fieldName] = input.value;
                    }
                });
                if (Object.keys(member).length > 0) {
                    members.push(member);
                }
            });
            formData.answers.members = members;
            
            // Submit the form data (online or offline)
            submitFormData(formData);
        });

        // ==================== FORM FIELD HANDLERS ====================

        // Toggle other remarks fields
        document.getElementById('other-remarks-checkbox').addEventListener('change', function() {
            document.getElementById('other-remarks-text').classList.toggle('hidden', !this.checked);
            saveFormState();
        });

        document.getElementById('census-others-checkbox').addEventListener('change', function() {
            document.getElementById('census-others-text').classList.toggle('hidden', !this.checked);
            saveFormState();
        });

        // Age calculation when birthdate changes
        document.getElementById('hh-birthdate').addEventListener('change', function() {
            if (this.value) {
                const age = calculateAge(this.value);
                document.getElementById('hh-age').value = age;
                saveFormState();
            }
        });

        document.getElementById('spouse-birthdate').addEventListener('change', function() {
            if (this.value) {
                const age = calculateAge(this.value);
                document.getElementById('spouse-age').value = age;
                saveFormState();
            }
        });

        // UD Code and TAG Number input listeners
        udCodeInput.addEventListener('input', function() {
            generateBarcodes();
            saveFormState();
        });
        
        tagNumberInput.addEventListener('input', function() {
            generateBarcodes();
            saveFormState();
        });

        // Auto-generate codes when barangay is entered
        document.getElementById('barangay').addEventListener('change', function() {
            if (!udCodeInput.value && !tagNumberInput.value) {
                const currentYear = new Date().getFullYear();
                const barangay = this.value || '000'; // Default to 000 if no barangay
                
                // Extract numbers from barangay name or use 000
                let barangayNum = '000';
                const numMatch = barangay.match(/\d+/);
                if (numMatch) {
                    barangayNum = numMatch[0].padStart(3, '0').substring(0, 3);
                }
                
                // Format: YEAR-BARANGAYNUM-001 (starting with 001)
                const sequenceNum = '001';
                udCodeInput.value = `${currentYear}-${barangayNum}-${sequenceNum}`;
                tagNumberInput.value = `${currentYear}-${barangayNum}-${sequenceNum}`;
                
                generateBarcodes();
                saveFormState();
            }
        });

        // ==================== MEMBER TABLE FUNCTIONALITY ====================

        // Member table functionality
        const memberTableBody = document.getElementById('member-table-body');
        const addMemberBtn = document.getElementById('add-member-btn');

        function addMemberRow() {
            const row = document.createElement('tr');
            row.className = 'member-row';
            
            row.innerHTML = `
                <td class="py-1 px-2 border-b"><input type="text" class="form-control border-none p-1" name="name" placeholder="Full name"></td>
                <td class="py-1 px-2 border-b"><input type="text" class="form-control border-none p-1" name="relationship" placeholder="Relationship to head"></td>
                <td class="py-1 px-2 border-b"><input type="number" class="form-control border-none p-1" name="age" placeholder="Age"></td>
                <td class="py-1 px-2 border-b">
                    <select class="form-control border-none p-1" name="sex">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </td>
                <td class="py-1 px-2 border-b"><input type="date" class="form-control border-none p-1" name="birthdate"></td>
                <td class="py-1 px-2 border-b"><input type="text" class="form-control border-none p-1" name="education" placeholder="Education level"></td>
                <td class="py-1 px-2 border-b">
                    <button type="button" class="remove-member-btn">Remove</button>
                </td>
            `;
                    
            memberTableBody.appendChild(row);
            
            // Add age calculation when birthdate changes
            const birthdateInput = row.querySelector('input[type="date"]');
            const ageInput = row.querySelector('input[type="number"]');
            
            birthdateInput.addEventListener('change', function() {
                if (this.value) {
                    const age = calculateAge(this.value);
                    ageInput.value = age;
                    saveFormState();
                }
            });
            
            // Add input listeners for auto-save
            const inputs = row.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('change', saveFormState);
                input.addEventListener('input', saveFormState);
            });
            
            // Add event listener to the remove button
            row.querySelector('.remove-member-btn').addEventListener('click', () => {
                if (memberTableBody.children.length > 1) {
                    row.remove();
                    saveFormState();
                } else {
                    alert('👨‍👩‍👧‍👦 You must have at least one household member (the head)');
                }
            });
        }

        addMemberBtn.addEventListener('click', function() {
            addMemberRow();
            saveFormState();
        });

        // Add initial row for household head
        addMemberRow();

        // ==================== MISC EVENT LISTENERS ====================

        returnHomeBtn.addEventListener('click', () => {
            // Reset form and show first page
            thankYouPage.classList.add('hidden');
            showPage(0);
        });

        // Survey type change handler
        document.getElementById('survey-type').addEventListener('change', function() {
            const otherInput = document.getElementById('other-survey-type');
            if (this.value === 'OTHERS') {
                otherInput.classList.remove('hidden');
                otherInput.required = true;
            } else {
                otherInput.classList.add('hidden');
                otherInput.required = false;
            }
            saveFormState();
        });

        // Format duration function with better time calculation
        function formatDuration(startTimeStr, endTime) {
            // Parse the start time string to Date object
            const startTime = new Date(startTimeStr);
            
            // Calculate difference in milliseconds
            const diff = Math.floor((endTime - startTime) / 1000);
            
            if (diff < 0) {
                return "Invalid time";
            }
            
            const hours = Math.floor(diff / 3600);
            const minutes = Math.floor((diff % 3600) / 60);
            const seconds = diff % 60;
            
            if (hours > 0) {
                return `${hours}h ${minutes}m ${seconds}s`;
            } else if (minutes > 0) {
                return `${minutes}m ${seconds}s`;
            } else {
                return `${seconds}s`;
            }
        }

        // Add logout functionality
        document.getElementById('logout-btn').addEventListener('click', function() {
            if (confirm('🚪 Are you sure you want to logout?')) {
                // Clear all local storage before logout
                localStorage.removeItem(FORM_STATE_KEY);
                localStorage.removeItem(CURRENT_PAGE_KEY);
                // Redirect to logout page which will destroy the session
                window.location.href = '../logout.php';
            }
        });

        // Add sync offline data functionality with confirmation
        syncOfflineBtn.addEventListener('click', function() {
            const offlineData = getOfflineData();
            const unsyncedCount = offlineData.filter(item => !item.synced).length;
            
            if (unsyncedCount === 0) {
                updateSyncStatus('✅ No offline data to sync.', 'success');
                return;
            }
            
            if (confirm(`🔄 You have ${unsyncedCount} unsynced records. Do you want to sync now?`)) {
                syncOfflineData();
            }
        });

        // Add input listeners for all form fields for auto-save
        document.addEventListener('input', function(e) {
            if (e.target.type !== 'password') {
                saveFormState();
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.type !== 'password') {
                saveFormState();
            }
        });

        // ==================== INITIALIZATION ====================

        // Initialize the form
        document.addEventListener('DOMContentLoaded', () => {
            // Restore current page from storage
            const savedPage = localStorage.getItem(CURRENT_PAGE_KEY);
            const initialPage = savedPage ? parseInt(savedPage) : 0;
            
            showPage(initialPage);
            initMap();
            initOfflineCapability();
            
            // Reset survey start time
            const now = new Date();
            const formattedStartTime = now.toLocaleString('en-PH', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            document.getElementById('survey-start-time').textContent = formattedStartTime;
            document.getElementById('survey-end-time').textContent = '-';
            
            // Update signature pad when window resizes
            window.addEventListener('resize', () => {
                if (currentPage === 2) {
                    setTimeout(() => {
                        resizeSignaturePad();
                    }, 100);
                }
            });

            // Show welcome message
            setTimeout(() => {
                if (navigator.onLine) {
                    updateSyncStatus('✅ App is ready. You can work offline and data will sync when online.', 'success');
                } else {
                    updateSyncStatus('📴 Working offline - data will be saved locally and synced later.', 'pending');
                }
            }, 2000);
        });
    </script>
</body>
</html>