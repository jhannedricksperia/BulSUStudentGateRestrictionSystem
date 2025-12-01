<?php
session_start();
require_once 'config.php';

// FCM v1 Configuration
define('SERVICE_ACCOUNT_FILE', __DIR__ . '/firebase-service-account.json');
define('FCM_PROJECT_ID', 'gatemanagementsystem');

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: guardLogin.php");
    exit;
}

if (!isset($_SESSION['guard'])) {
    header("Location: guardLogin.php");
    exit;
}

$guard = $_SESSION['guard'];
$campus = htmlspecialchars($guard['Campus'] ?? 'Unknown');
$firstname = htmlspecialchars($guard['FirstName'] ?? '');
$lastname = htmlspecialchars($guard['LastName'] ?? '');
$gate = htmlspecialchars($guard['Gate'] ?? 'Main Gate');

// Load campus logo dynamically
$campusesFile = 'campuses.json';
$campuses = [];
$campusLogo = 'BSUU.webp'; // Default

if (file_exists($campusesFile)) {
    $jsonContent = file_get_contents($campusesFile);
    $campuses = json_decode($jsonContent, true);
    if (is_array($campuses) && isset($campuses[$campus])) {
        $logoPath = 'uploads/' . $campuses[$campus];
        if (file_exists($logoPath)) {
            $campusLogo = $logoPath;
        } elseif (file_exists($campuses[$campus])) {
            $campusLogo = $campuses[$campus];
        }
    }
}

function getAccessToken() {
    if (!file_exists(SERVICE_ACCOUNT_FILE)) {
        error_log("Service account file not found: " . SERVICE_ACCOUNT_FILE);
        return null;
    }

    $serviceAccount = json_decode(file_get_contents(SERVICE_ACCOUNT_FILE), true);
    
    if (!$serviceAccount) {
        error_log("Invalid service account JSON");
        return null;
    }

    $header = json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT'
    ]);

    $now = time();
    $payload = json_encode([
        'iss' => $serviceAccount['client_email'],
        'sub' => $serviceAccount['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = '';
    $signatureData = $base64UrlHeader . "." . $base64UrlPayload;
    
    openssl_sign(
        $signatureData,
        $signature,
        $serviceAccount['private_key'],
        OPENSSL_ALGO_SHA256
    );

    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Failed to get access token. HTTP: $httpCode, Response: $response");
        return null;
    }

    $responseData = json_decode($response, true);
    return $responseData['access_token'] ?? null;
}

function sendPushNotification($studentNumber, $fullName, $status, $campus, $gate, $timestamp) {
    $studentUrl = rtrim(FIREBASE_DB_URL, '/') . '/Student.json';
    $studentsData = @file_get_contents($studentUrl);
    
    if (!$studentsData) {
        error_log("Failed to fetch student data from Firebase");
        return false;
    }
    
    $studentsData = json_decode($studentsData, true);
    
    $fcmToken = null;
    if ($studentsData) {
        foreach ($studentsData as $student) {
            if (isset($student['studentNumber']) && $student['studentNumber'] == $studentNumber) {
                $fcmToken = $student['fcmToken'] ?? null;
                error_log("Student found: $studentNumber, Has FCM Token: " . ($fcmToken ? 'YES' : 'NO'));
                break;
            }
        }
    }

    if (!$fcmToken) {
        error_log("No FCM token found for student: $studentNumber");
        return false;
    }

    $accessToken = getAccessToken();
    
    if (!$accessToken) {
        error_log("Failed to get OAuth access token");
        return false;
    }

    $statusText = ($status === 'Entry') ? 'entered' : 'left';
    $currentTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formattedTime = $currentTime->format('F j, Y \a\t g:i A');

    $fcmUrl = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';
    
    $message = [
        'message' => [
            'token' => $fcmToken,
            'notification' => [
                'title' => 'Gate Access - ' . $status,
                'body' => "You have successfully $statusText $gate Gate of $campus Campus on $formattedTime"
            ],
            'data' => [
                'studentNumber' => (string)$studentNumber,
                'fullName' => $fullName,
                'campus' => $campus,
                'gate' => $gate,
                'status' => $status,
                'timestamp' => date('c', strtotime($timestamp)),
                'type' => 'gate_access',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'channel_id' => 'gate_access_channel'
                ]
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10'
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("FCM v1 HTTP Code: $httpCode");
    error_log("FCM v1 Response: $response");
    
    if ($curlError) {
        error_log("FCM Curl Error: $curlError");
        return false;
    }

    if ($httpCode === 200) {
        error_log("✅ Push notification sent successfully to: $studentNumber");
        return true;
    } else {
        error_log("❌ Failed to send notification. HTTP: $httpCode");
        $errorData = json_decode($response, true);
        if ($errorData && isset($errorData['error'])) {
            error_log("Error details: " . json_encode($errorData['error']));
        }
        return false;
    }
}

function saveNotification($studentNumber, $fullName, $campus, $gate, $status, $timestamp) {
    $notificationsUrl = rtrim(FIREBASE_DB_URL, '/') . '/Notifications.json';
    
    $currentTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formattedTime = $currentTime->format('F j, Y \a\t g:i A');
    $statusText = ($status === 'Entry') ? 'entered' : 'left';
    
    $notificationData = [
        'studentNumber' => $studentNumber,
        'fullName' => $fullName,
        'campus' => $campus,
        'gate' => $gate,
        'status' => $status,
        'content' => "You have successfully $statusText $gate of $campus Campus on $formattedTime",
        'read' => false,
        'timestamp' => $currentTime->format(DateTime::ISO8601)
    ];

    $ch = curl_init($notificationsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response && json_decode($response, true) !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logStudent'])) {
    $studentNumber = $_POST['studentNumber'] ?? '';
    $fullName = $_POST['fullName'] ?? '';
    $violation = $_POST['violation'] ?? 'None';
    $scheduleStatus = $_POST['scheduleStatus'] ?? 'Unknown';
    $nextStatus = $_POST['nextStatus'] ?? 'Entry';
    $type = $_POST['type'] ?? 'Student';

    if ($studentNumber && $fullName) {
        $logUrl = rtrim(FIREBASE_DB_URL, '/') . '/Logs.json';
        $logsData = json_decode(@file_get_contents($logUrl), true);
        $timeIn = '';
        $timeOut = '';

        if ($logsData) {
            $studentLogs = array_filter($logsData, fn($log) => isset($log['studentNumber']) && $log['studentNumber'] == $studentNumber);
            if (!empty($studentLogs)) {
                $lastLog = end($studentLogs);
                if ($nextStatus === 'Exit') {
                    $timeIn = $lastLog['dateTime'] ?? '';
                }
            }
        }

        if ($nextStatus === 'Exit') {
            $violation = 'None';
            $timeOut = date('Y-m-d H:i:s');
        }

        $currentDateTime = date('Y-m-d H:i:s');
        $logData = [
            'studentNumber' => $studentNumber,
            'fullName' => $fullName,
            'campus' => $campus,
            'gate' => $gate,
            'violation' => $violation,
            'scheduleStatus' => $scheduleStatus,
            'status' => $nextStatus,
            'timeIn' => ($nextStatus === 'Exit') ? $timeIn : $currentDateTime,
            'timeOut' => $timeOut,
            'dateTime' => $currentDateTime,
            'type' => $type
        ];

        $ch = curl_init($logUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($logData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response && json_decode($response, true) !== null) {
            saveNotification($studentNumber, $fullName, $campus, $gate, $nextStatus, $currentDateTime);
            
            if ($type === 'student') {
                sendPushNotification($studentNumber, $fullName, $nextStatus, $campus, $gate, $currentDateTime);
            }
            
            echo 'success';
        } else {
            echo 'fail';
        }
        exit;
    } else {
        echo 'fail';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guard Home | Student Gate Restriction System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<style>
body {font-family:'Poppins',sans-serif;background-color:#fff5f5;margin:0;display:flex;flex-direction:column;min-height:100vh;}
header {background-color:#870000;color:white;padding:20px 40px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 3px 10px rgba(0,0,0,0.2);margin-bottom:30px;}
.header-left {display:flex;align-items:center;gap:20px;}
.header-left img {width:70px;height:70px;object-fit:cover;border-radius:8px;}
.header-info h1 {font-size:1.5rem;margin:0;font-weight:600;}
.header-info p {font-size:1rem;margin:4px 0 0 0;opacity:0.95;}
.profile-dropdown {position:relative;cursor:pointer;}
.profile-btn {display:flex;align-items:center;gap:8px;font-size:18px;background:none;border:none;color:white;padding:0;cursor:pointer;}
.profile-btn .fa-user-circle {font-size:40px;}
.dropdown-content {display:none;position:absolute;right:0;top:50px;background:#fff;min-width:180px;box-shadow:0 4px 10px rgba(0,0,0,0.2);border-radius:8px;overflow:hidden;z-index:100;}
.dropdown-content p, .dropdown-content form {padding:10px 15px;margin:0;font-size:14px;color:#333;}
.dropdown-content p i {margin-right:8px;color:#870000;}
.dropdown-content .logout-btn {width:100%;text-align:left;background:none;color:#870000;padding:10px 0;border:none;cursor:pointer;font-weight:bold;}
.dropdown-content .logout-btn:hover {background:#870000;color:white;}
.main-content {display:flex;justify-content:center;align-items:flex-start;gap:30px;margin-top:40px;padding:0 20px;flex-wrap:wrap;}
.left-section {flex:1;max-width:950px;}
.right-section {flex:0 0 auto;max-width:500px;}
.search-card {background:white;padding:25px;border-radius:15px;box-shadow:0 4px 10px rgba(0,0,0,0.1);margin-bottom:20px;}
.card-header {display:flex;flex-direction:column;gap:15px;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0;align-items:center;}
.search-title {color:#333;margin:0;font-size:26px;font-weight:600;text-align:center;}
.datetime-display {text-align:center;}
.time-display {color:#870000;font-size:42px;font-weight:700;line-height:1.1;}
.date-display {color:#666;font-size:15px;margin-top:6px;font-weight:500;}
.search-card input {width:100%;padding:14px 18px;border-radius:8px;border:1px solid #ccc;font-size:16px;margin-bottom:15px;box-sizing:border-box;}
.check-btn {width:100%;background-color:#f9a825;border:none;color:#870000;padding:14px 20px;border-radius:8px;cursor:pointer;font-weight:bold;transition:0.3s;font-size:16px;}
.check-btn:hover {background-color:#870000;color:white;}
.qr-scanner-container {background:white;padding:20px;border-radius:15px;box-shadow:0 4px 10px rgba(0,0,0,0.2);}
.qr-scanner-header {text-align:center;margin-bottom:15px;}
.qr-scanner-header h3 {color:#870000;margin:0;}
#qr-reader {width:100%;border:2px solid #870000;border-radius:8px;overflow:hidden;margin-bottom:10px;}
#qr-reader__dashboard_section_swaplink {display:none !important;}
.qr-status {text-align:center;color:#666;font-size:14px;font-weight:600;}
.student-card {background:#fff5f5;border:2px solid #870000;border-radius:12px;box-shadow:0 3px 8px rgba(0,0,0,0.1);display:none;flex-direction:row;gap:50px;padding:30px;margin-top:20px;}
.visitor-card {background:#fffaf0;border:2px solid #ff9800;border-radius:12px;box-shadow:0 3px 8px rgba(0,0,0,0.1);display:none;flex-direction:row;gap:50px;padding:30px;margin-top:20px;}
.vip-card {background:#fff8e1;border:2px solid #ffc107;border-radius:12px;box-shadow:0 3px 8px rgba(0,0,0,0.1);display:none;flex-direction:row;gap:50px;padding:30px;margin-top:20px;}
.student-left {flex:1;display:flex;flex-direction:column;gap:15px;}
.violation-box textarea {width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;resize:none;height:60px;}
.log-btn {background:#f9a825;color:#870000;padding:10px 15px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;transition:0.3s;}
.log-btn:hover {background:#870000;color:white;}
.schedule-status {font-weight:bold;padding:8px;border-radius:8px;text-align:center;transition:0.3s;}
.schedule-status.yes {background:#c8e6c9;color:#256029;}
.schedule-status.no {background:#ffcdd2;color:#b71c1c;}
.last-status {font-weight:bold;font-size:15px;text-align:center;padding:8px;border-radius:8px;margin-top:10px;}
.last-status.entry {background:#c8e6c9;color:#256029;}
.last-status.exit {background:#ffe082;color:#b26a00;}
.student-right {flex:2;display:flex;flex-direction:column;}
.student-top {display:flex;align-items:flex-start;gap:20px;}
.student-photo {background-color:#eee;width:200px;height:200px;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.student-photo img {width:100%;height:100%;object-fit:cover;}
.student-photo i {font-size:80px;color:#ccc;}
.student-info {flex:1;}
.student-info h3 {color:#870000;margin:0 0 15px 0;font-size:20px;}
.student-info p {margin:8px 0;color:#333;font-size:15px;line-height:1.6;display:flex;gap:5px;}
.student-info p strong {color:#870000;flex-shrink:0;}
.visitor-info {flex:2;display:flex;flex-direction:column;gap:10px;}
.visitor-info h3 {color:#ff9800;margin:0;font-size:18px;}
.visitor-info p {margin:5px 0;color:#333;font-size:14px;line-height:1.6;}
.vip-info {flex:2;display:flex;flex-direction:column;gap:10px;}
.vip-info h3 {color:#ffc107;margin:0;font-size:18px;font-weight:700;}
.vip-info p {margin:5px 0;color:#333;font-size:14px;line-height:1.6;font-weight:500;}
.visitor-badge {display:inline-block;background:#ff9800;color:white;padding:6px 12px;border-radius:4px;font-size:13px;font-weight:bold;width:fit-content;}
.vip-badge {display:inline-block;background:#ffc107;color:#000;padding:8px 14px;border-radius:4px;font-size:13px;font-weight:bold;width:fit-content;}
footer {background:#870000;color:white;text-align:center;padding:12px;font-size:14px;margin-top:auto;}
.alert {text-align:center;margin-top:10px;padding:15px;font-weight:bold;border-radius:8px;display:none;animation:slideDown 0.3s ease-out;}
.alert.success {background:transparent;color:#256029;}
.alert.error {background:#ffcdd2;color:#b71c1c;}
@keyframes slideDown {from {transform:translateY(-20px);opacity:0;} to {transform:translateY(0);opacity:1;}}
.empty-state {background:#fff5f5;border:2px solid #870000;border-radius:12px;box-shadow:0 3px 8px rgba(0,0,0,0.1);padding:30px;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:300px;}
@media (max-width:768px) {
    .main-content {flex-direction:column;align-items:center;}
    .left-section,.right-section {max-width:100%;width:100%;}
    .student-card,.visitor-card,.vip-card {flex-direction:column;gap:20px;}
    .header-left {flex-direction:column;gap:10px;align-items:flex-start;}
    .header-left img {width:50px;height:50px;}
    .header-info h1 {font-size:1.1rem;}
    .header-info p {font-size:0.85rem;}
}
</style>
</head>
<body>

<header>
<div class="header-left">
    <img src="<?= $campusLogo ?>" alt="Campus Logo">
    <div class="header-info">
        <h1>Bulacan State University - <?= $campus ?> Campus</h1>
    </div>
</div>
<div class="profile-dropdown">
    <button class="profile-btn" onclick="toggleDropdown()">
        <i class="fas fa-user-circle"></i>
        <i class="fas fa-caret-down"></i>
    </button>
    <div class="dropdown-content" id="dropdownContent">
        <p><i class="fas fa-user"></i> <?= $firstname.' '.$lastname ?></p>
        <p><i class="fas fa-university"></i> <?= $campus ?> Campus</p>
        <form method="POST">
            <button type="submit" name="logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </div>
</div>
</header>

<div class="main-content">
    <!-- LEFT SECTION: Display Area -->
    <div class="left-section">
        <div class="empty-state" id="emptyState">
            <i class="fas fa-id-card" style="font-size:80px;color:#ccc;margin-bottom:15px;"></i>
            <h3 style="color:#870000;margin:10px 0;">No Entry Selected</h3>
            <p style="color:#666;">Scan a QR code or enter an ID to view details</p>
        </div>
        
        <div id="studentCard" class="student-card">
            <div class="student-left">
                <div id="lastStatus" class="last-status">Loading...</div>
                <div class="violation-box" id="violationBox">
                    <label><b>Violation:</b></label>
                    <textarea id="violationText" placeholder="Not wearing uniform"></textarea>
                </div>
                <div id="scheduleStatus" class="schedule-status">Checking schedule...</div>
                <button class="log-btn" id="confirmBtn" onclick="confirmLog()">Confirm Log</button>
            </div>

            <div class="student-right">
                <div class="student-top">
                    <div class="student-photo" id="studentPhoto">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="student-info">
                        <h3>Student Information</h3>
                        <p><strong>Student Number:</strong> <span id="studentNumber"></span></p>
                        <p><strong>Name:</strong> <span id="studentName"></span></p>
                        <p><strong>Course:</strong> <span id="studentCourse"></span></p>
                        <p><strong>Section:</strong> <span id="studentSection"></span></p>
                        <p><strong>Year:</strong> <span id="studentYear"></span></p>
                    </div>
                </div>
            </div>
        </div>

        <div id="visitorCard" class="visitor-card">
            <div class="student-left">
                <div class="visitor-badge" id="visitorType">Visitor</div>
                <div id="visitorStatusContainer" style="margin-top:10px;">
                    <div id="visitorStatus" class="last-status entry">Entry</div>
                </div>
                <button class="log-btn" id="visitorConfirmBtn" onclick="confirmVisitorLog()">Log Visitor Entry</button>
            </div>

            <div class="visitor-info">
                <h3 id="visitorName"></h3>
                <p id="visitorReferenceNo"></p>
                <p id="visitorCollege" style="display:none;"></p>
                <p id="visitorOffice" style="display:none;"></p>
                <p id="visitorPurpose" style="display:none;"></p>
                <p id="visitorContact" style="display:none;"></p>
                <p id="visitorDateTime"></p>
            </div>
        </div>

        <div id="vipCard" class="vip-card">
            <div class="student-left">
                <div class="vip-badge">⭐ VIP VISITOR</div>
                <div id="vipStatusContainer" style="margin-top:10px;">
                    <div id="vipStatus" class="last-status entry">Entry</div>
                </div>
                <button class="log-btn" id="vipConfirmBtn" onclick="confirmVisitorLog()">Log VIP Entry</button>
            </div>

            <div class="vip-info">
                <h3 id="vipName"></h3>
                <p id="vipReferenceNo"></p>
                <p id="vipRepresentative"></p>
                <p id="vipContact"></p>
                <p id="vipDateTime"></p>
            </div>
        </div>
    </div>

    <!-- RIGHT SECTION: Scanner & Input -->
    <div class="right-section">
        <div class="search-card">
            <div class="card-header">
                <h3 class="search-title">Gate Entry/Exit System</h3>
                <div class="datetime-display">
                    <div class="time-display" id="currentTime">00:00 AM</div>
                    <div class="date-display" id="currentDate">Loading...</div>
                </div>
            </div>
            <input type="text" id="searchInput" placeholder="Enter ID (Student, Visitor, VIP)">
            <button class="check-btn" onclick="searchEntry()">
                <i class="fas fa-check"></i> Check
            </button>
        </div>

        <div class="alert" id="logAlert"></div>

        <div id="qrScannerContainer" class="qr-scanner-container">
            <div class="qr-scanner-header">
                <h3>Scan QR Code</h3>
            </div>
            <div id="qr-reader"></div>
            <p class="qr-status" id="qrStatus">Position QR code within the frame</p>
        </div>
    </div>
</div>

<footer>Logged in as <?= $firstname.' '.$lastname ?> | <?= $campus ?> Campus</footer>

<script>
let currentStudent = null;
let currentVisitor = null;
let currentScheduleStatus = "Unknown";
let nextStatus = "Entry";
let html5QrCode = null;
let autoLogTimeout = null;
let scannerLocked = false;

window.addEventListener('DOMContentLoaded', function() {
    startQRScanner();
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

function updateDateTime() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const formattedTime = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ' ' + ampm;
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = now.toLocaleDateString('en-US', options);
    document.getElementById('currentTime').textContent = formattedTime;
    document.getElementById('currentDate').textContent = formattedDate;
}

function toggleDropdown() {
    const dropdown = document.getElementById('dropdownContent');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

window.onclick = function(event) {
    if (!event.target.matches('.profile-btn') && !event.target.closest('.profile-dropdown')) {
        document.getElementById('dropdownContent').style.display = 'none';
    }
};

function clearDisplay() {
    document.getElementById('studentCard').style.display = 'none';
    document.getElementById('visitorCard').style.display = 'none';
    document.getElementById('vipCard').style.display = 'none';
    document.getElementById('emptyState').style.display = 'flex';
    document.getElementById('searchInput').value = '';
    if(autoLogTimeout) clearTimeout(autoLogTimeout);
}

function startQRScanner() {
    if (html5QrCode) return;
    html5QrCode = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    html5QrCode.start(
        { facingMode: "environment" },
        config,
        onScanSuccess,
        onScanError
    ).catch(err => {
        console.error("Unable to start scanner:", err);
        document.getElementById('qrStatus').textContent = "Camera unavailable. Please use manual entry.";
    });
}

function onScanSuccess(decodedText) {
    if (scannerLocked) return;
    lockScanner();
    document.getElementById('searchInput').value = decodedText;
    detectTypeAndSearch(decodedText, true);
}

function lockScanner() {
    scannerLocked = true;
    const qrStatus = document.getElementById('qrStatus');
    let countdown = 3;
    qrStatus.textContent = `⏳ Please wait ${countdown}s...`;
    qrStatus.style.color = '#ff9800';
    const interval = setInterval(() => {
        countdown--;
        if (countdown > 0) {
            qrStatus.textContent = `⏳ Please wait ${countdown}s...`;
        } else {
            clearInterval(interval);
            scannerLocked = false;
            qrStatus.textContent = "✅ Ready to scan";
            qrStatus.style.color = '#4caf50';
            setTimeout(() => {
                qrStatus.textContent = "Position QR code within the frame";
                qrStatus.style.color = '#666';
            }, 1500);
        }
    }, 1000);
}

function onScanError(errorMessage) {}

function detectTypeAndSearch(input, isQR = false) {
    if (input.startsWith('SV-') || input.startsWith('VP-') || input.startsWith('VIP-')) {
        checkVisitor(input, isQR);
    } else {
        checkStudent(input, isQR);
    }
}

function searchEntry() {
    const input = document.getElementById('searchInput').value.trim();
    if (!input) {
        alert('Please enter a student number or visitor ID');
        return;
    }
    detectTypeAndSearch(input);
}

async function checkStudent(sn, isQRScan = false) {
    const card = document.getElementById('studentCard');
    const emptyState = document.getElementById('emptyState');
    const photoDiv = document.getElementById('studentPhoto');
    const schedStatus = document.getElementById('scheduleStatus');
    const violationBox = document.getElementById('violationBox');
    const lastStatusDiv = document.getElementById('lastStatus');
    const confirmBtn = document.getElementById('confirmBtn');
    const alertBox = document.getElementById('logAlert');
    
    if (autoLogTimeout) clearTimeout(autoLogTimeout);
    
    card.style.display = 'none';
    document.getElementById('visitorCard').style.display = 'none';
    document.getElementById('vipCard').style.display = 'none';
    emptyState.style.display = 'flex';
    schedStatus.textContent = 'Checking schedule...';
    photoDiv.innerHTML = '<i class="fas fa-user-graduate"></i>';
    lastStatusDiv.textContent = "Loading...";
    alertBox.style.display = 'none';

    try {
        const studentRes = await fetch('<?= rtrim(FIREBASE_DB_URL,"/") ?>/Student.json');
        const students = await studentRes.json();
        if (!students) { alert('No student data found'); return; }

        let student = Object.values(students).find(s => s.studentNumber === sn);
        if (!student) { alert('Student not found'); return; }

        currentStudent = student;
        document.getElementById('studentNumber').innerText = student.studentNumber || 'N/A';
        const fullName = [student.first_name, student.last_name].filter(Boolean).join(' ').trim();
        document.getElementById('studentName').innerText = fullName || 'N/A';
        document.getElementById('studentCourse').innerText = student.course || 'N/A';
        document.getElementById('studentSection').innerText = student.section || 'N/A';
        document.getElementById('studentYear').innerText = student.year || 'N/A';

        if (student.profileImageUrl) {
            const img = document.createElement('img');
            img.src = student.profileImageUrl;
            photoDiv.innerHTML = '';
            photoDiv.appendChild(img);
        }

        const logRes = await fetch('<?= rtrim(FIREBASE_DB_URL,"/") ?>/Logs.json');
        const logs = await logRes.json();
        
        nextStatus = "Entry";
        if (logs) {
            const studentLogs = Object.values(logs).filter(l => l.studentNumber === sn);
            if (studentLogs.length > 0) {
                const lastLog = studentLogs[studentLogs.length - 1];
                nextStatus = lastLog.status === "Entry" ? "Exit" : "Entry";
            }
        }

        lastStatusDiv.textContent = nextStatus === "Entry" ? "Time In" : "Time Out";
        lastStatusDiv.className = nextStatus === "Entry" ? "last-status entry" : "last-status exit";
        violationBox.style.display = (nextStatus === "Exit") ? "none" : "block";

        const schedRes = await fetch('<?= rtrim(FIREBASE_DB_URL,"/") ?>/Schedules.json');
        const schedules = await schedRes.json();
        let hasScheduleToday = false;
        const today = new Date();
        const todayName = today.toLocaleString('en-US', { weekday: 'long' });

        if (schedules) {
            Object.values(schedules).forEach(sch => {
                if (sch.studentNumber === sn && sch.subjects) {
                    Object.values(sch.subjects).forEach(sub => {
                        if (Array.isArray(sub.days) && sub.days.includes(todayName)) {
                            hasScheduleToday = true;
                        }
                    });
                }
            });
        }

        currentScheduleStatus = hasScheduleToday ? "Has schedule today" : "No schedule today";
        
        // Hide schedule status for Exit
        if (nextStatus === "Exit") {
            schedStatus.style.display = "none";
        } else {
            schedStatus.style.display = "block";
            schedStatus.textContent = hasScheduleToday ? "✅ Entry Approved" : "⚠️ No Schedule Today";
            schedStatus.className = hasScheduleToday ? "schedule-status yes" : "schedule-status no";
        }

        emptyState.style.display = 'none';
        card.style.display = 'flex';

        // Auto-log for Exit status (both QR and manual), manual confirmation for Entry
        if (nextStatus === "Exit") {
            confirmBtn.style.display = 'none';
            alertBox.className = 'alert success';
            alertBox.textContent = "✅ Exit successful!";
            alertBox.style.display = 'block';
            await performAutoLog('student');
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 3000);
            autoLogTimeout = setTimeout(() => clearDisplay(), 3000);
        } else {
            // Show confirm button only for Entry
            confirmBtn.style.display = 'inline-block';
            alertBox.style.display = 'none';
        }

    } catch (e) {
        console.error(e);
        alert('Error connecting to Firebase');
    }
}

async function checkVisitor(visitorId, isQRScan = false, forceMode = null) {
    const emptyState = document.getElementById('emptyState');
    const alertBox = document.getElementById('logAlert');
    
    if (autoLogTimeout) clearTimeout(autoLogTimeout);
    
    emptyState.style.display = 'flex';
    document.getElementById('studentCard').style.display = 'none';
    alertBox.style.display = 'none';

    try {
        const visitorRes = await fetch('<?= rtrim(FIREBASE_DB_URL,"/") ?>/Visitor.json');
        const visitors = await visitorRes.json();
        if (!visitors) { alert('No visitor data found'); return; }

        let visitor = null;
        Object.entries(visitors).forEach(([key, v]) => {
            if (v.referenceNo === visitorId) {
                visitor = v;
            }
        });

        if (!visitor) { 
            alert('Visitor not found. ID: ' + visitorId); 
            return; 
        }

        currentVisitor = visitor;
        
        if (visitor.type === 'VIPVisitor') {
            displayVIPVisitor(visitor, visitorId, isQRScan, forceMode);
        } else {
            displayRegularVisitor(visitor, visitorId, isQRScan, forceMode);
        }

    } catch (e) {
        console.error(e);
        alert('Error connecting to Firebase');
    }
}

function displayVIPVisitor(visitor, visitorId, isQRScan, forceMode) {
    const card = document.getElementById('vipCard');
    const emptyState = document.getElementById('emptyState');
    const alertBox = document.getElementById('logAlert');
    
    document.getElementById('visitorCard').style.display = 'none';
    
    const firstName = visitor.firstName || 'N/A';
    const lastName = visitor.lastName || 'N/A';
    const fullName = [firstName, lastName].filter(n => n !== 'N/A').join(' ').trim();
    document.getElementById('vipName').innerText = fullName;
    document.getElementById('vipReferenceNo').innerText = '📍 ID: ' + (visitor.referenceNo || visitorId);
    
    const repFirstName = visitor.repFirstName || '';
    const repLastName = visitor.repLastName || '';
    const representative = [repFirstName, repLastName].filter(Boolean).join(' ').trim();
    document.getElementById('vipRepresentative').innerText = '👤 Representative: ' + (representative || 'N/A');
    
    document.getElementById('vipContact').innerText = '📱 Contact: ' + (visitor.contact || 'N/A');
    document.getElementById('vipDateTime').innerText = '📅 Registered: ' + (visitor.dateTime || 'N/A');

    const logRes = fetch('<?= rtrim(FIREBASE_DB_URL,"/") ?>/Logs.json');
    logRes.then(res => res.json()).then(logs => {
        let vipNextStatus = "Entry";
        
        if (forceMode) {
            vipNextStatus = forceMode;
        } else {
            if (logs) {
                const vipLogs = Object.values(logs).filter(l => l.studentNumber === visitorId);
                if (vipLogs.length > 0) {
                    const lastLog = vipLogs[vipLogs.length - 1];
                    vipNextStatus = lastLog.status === "Entry" ? "Exit" : "Entry";
                }
            }
        }

        document.getElementById('vipStatus').textContent = vipNextStatus === "Entry" ? "Time In" : "Time Out";
        document.getElementById('vipStatus').className = vipNextStatus === "Entry" ? "last-status entry" : "last-status exit";

        emptyState.style.display = 'none';
        card.style.display = 'flex';

        // KEY CHANGE: Only auto-log for Exit status via QR scan
        if (isQRScan && vipNextStatus === "Exit") {
            document.getElementById('vipConfirmBtn').style.display = 'none';
            alertBox.className = 'alert success';
            alertBox.textContent = '✅ Exit successful!';
            alertBox.style.display = 'block';
            performAutoLog('visitor', vipNextStatus);
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 3000);
            autoLogTimeout = setTimeout(() => clearDisplay(), 3000);
        } else {
            // Always show confirm button for Entry or manual check
            document.getElementById('vipConfirmBtn').style.display = 'inline-block';
        }
    });
}

function displayRegularVisitor(visitor, visitorId, isQRScan, forceMode) {
    const card = document.getElementById('visitorCard');
    const emptyState = document.getElementById('emptyState');
    const alertBox = document.getElementById('logAlert');
    
    document.getElementById('vipCard').style.display = 'none';
    
    const firstName = visitor.firstName || 'N/A';
    const lastName = visitor.lastName || 'N/A';
    const fullName = [firstName, lastName].filter(n => n !== 'N/A').join(' ').trim();
    document.getElementById('visitorName').innerText = fullName;
    document.getElementById('visitorReferenceNo').innerText = visitor.referenceNo || visitorId;
    
    if (visitor.type === 'StudentVisitor') {
        document.getElementById('visitorCollege').innerHTML = '<strong>College:</strong> ' + (visitor.college || 'N/A');
        document.getElementById('visitorCollege').style.display = 'block';
        document.getElementById('visitorOffice').innerHTML = '<strong>Office:</strong> ' + (visitor.office || 'N/A');
        document.getElementById('visitorOffice').style.display = 'block';
        document.getElementById('visitorPurpose').innerHTML = '<strong>Purpose:</strong> ' + (visitor.purpose || 'N/A');
        document.getElementById('visitorPurpose').style.display = 'block';
        document.getElementById('visitorContact').innerHTML = '<strong>Email:</strong> ' + (visitor.email || 'N/A');
        document.getElementById('visitorContact').style.display = 'block';
        document.getElementById('visitorType').innerText = 'Student Visitor';
    } else if (visitor.type === 'NonMemberVisitor') {
        document.getElementById('visitorCollege').innerHTML = '<strong>Address:</strong> ' + (visitor.address || 'N/A');
        document.getElementById('visitorCollege').style.display = 'block';
        document.getElementById('visitorOffice').innerHTML = '<strong>Office:</strong> ' + (visitor.office || 'N/A');
        document.getElementById('visitorOffice').style.display = 'block';
        document.getElementById('visitorPurpose').innerHTML = '<strong>Purpose:</strong> ' + (visitor.purpose || 'N/A');
        document.getElementById('visitorPurpose').style.display = 'block';
        document.getElementById('visitorContact').innerHTML = '<strong>Contact:</strong> ' + (visitor.contact || 'N/A');
        document.getElementById('visitorContact').style.display = 'block';
        document.getElementById('visitorType').innerText = 'Non-Member Visitor';
    }
    
    document.getElementById('visitorDateTime').innerHTML = '<strong>Registered:</strong> ' + (visitor.dateTime || 'N/A');

    const logRes = fetch('<?= rtrim(FIREBASE_DB_URL,"/") ?>/Logs.json');
    logRes.then(res => res.json()).then(logs => {
        let visitorNextStatus = "Entry";
        
        if (forceMode) {
            visitorNextStatus = forceMode;
        } else {
            if (logs) {
                const visitorLogs = Object.values(logs).filter(l => l.studentNumber === visitorId);
                if (visitorLogs.length > 0) {
                    const lastLog = visitorLogs[visitorLogs.length - 1];
                    visitorNextStatus = lastLog.status === "Entry" ? "Exit" : "Entry";
                }
            }
        }

        document.getElementById('visitorStatus').textContent = visitorNextStatus === "Entry" ? "Time In" : "Time Out";
        document.getElementById('visitorStatus').className = visitorNextStatus === "Entry" ? "last-status entry" : "last-status exit";

        emptyState.style.display = 'none';
        card.style.display = 'flex';

        // KEY CHANGE: Only auto-log for Exit status via QR scan
        if (isQRScan && visitorNextStatus === "Exit") {
            document.getElementById('visitorConfirmBtn').style.display = 'none';
            alertBox.className = 'alert success';
            alertBox.textContent = '✅ Exit successful!';
            alertBox.style.display = 'block';
            performAutoLog('visitor', visitorNextStatus);
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 3000);
            autoLogTimeout = setTimeout(() => clearDisplay(), 3000);
        } else {
            // Always show confirm button for Entry or manual check
            document.getElementById('visitorConfirmBtn').style.display = 'inline-block';
        }
    });
}

async function performAutoLog(type, visitorNextStatus = null) {
    if (type === 'student' && !currentStudent) return;
    if (type === 'visitor' && !currentVisitor) return;

    const formData = new FormData();
    formData.append('logStudent', 1);
    
    if (type === 'student') {
        const fullName = [currentStudent.first_name, currentStudent.last_name].filter(Boolean).join(' ').trim();
        formData.append('studentNumber', currentStudent.studentNumber);
        formData.append('fullName', fullName);
        formData.append('scheduleStatus', currentScheduleStatus);
        formData.append('nextStatus', nextStatus);
    } else {
        const fullName = [currentVisitor.firstName, currentVisitor.lastName].filter(Boolean).join(' ').trim();
        formData.append('studentNumber', currentVisitor.referenceNo);
        formData.append('fullName', fullName);
        formData.append('scheduleStatus', 'Visitor');
        formData.append('nextStatus', visitorNextStatus || 'Entry');
    }
    
    formData.append('violation', 'None');
    formData.append('type', type);

    await fetch('', { method: 'POST', body: formData });
}

async function confirmLog() {
    if (!currentStudent) return alert("Check a student first");
    const violation = document.getElementById('violationText').value.trim();
    const alertBox = document.getElementById('logAlert');
    const confirmBtn = document.getElementById('confirmBtn');

    const formData = new FormData();
    formData.append('logStudent', 1);
    const fullName = [currentStudent.first_name, currentStudent.last_name].filter(Boolean).join(' ').trim();
    formData.append('studentNumber', currentStudent.studentNumber);
    formData.append('fullName', fullName);
    formData.append('violation', nextStatus === "Exit" ? "None" : (violation || 'None'));
    formData.append('scheduleStatus', currentScheduleStatus);
    formData.append('nextStatus', nextStatus);
    formData.append('type', 'student');

    const res = await fetch('', { method: 'POST', body: formData });
    const text = (await res.text()).trim();
    
    if (text === 'success') {
        confirmBtn.style.display = 'none';
        alertBox.className = 'alert success';
        alertBox.textContent = nextStatus === "Entry" ? "✅ Entry successful!" : "✅ Exit successful!";
        alertBox.style.display = 'block';
        setTimeout(() => {
            alertBox.style.display = 'none';
        }, 3000);
        autoLogTimeout = setTimeout(() => clearDisplay(), 3000);
    } else {
        alertBox.className = 'alert error';
        alertBox.textContent = '❌ Failed to save log. Please try again.';
        alertBox.style.display = 'block';
    }
}

async function confirmVisitorLog() {
    if (!currentVisitor) return alert("Select a visitor first");
    const alertBox = document.getElementById('logAlert');
    const confirmBtn = document.getElementById('visitorConfirmBtn');
    const vipConfirmBtn = document.getElementById('vipConfirmBtn');

    const formData = new FormData();
    formData.append('logStudent', 1);
    const fullName = [currentVisitor.firstName, currentVisitor.lastName].filter(Boolean).join(' ').trim();
    formData.append('studentNumber', currentVisitor.referenceNo);
    formData.append('fullName', fullName);
    formData.append('violation', 'None');
    formData.append('scheduleStatus', 'Visitor');
    formData.append('nextStatus', 'Entry');
    formData.append('type', 'visitor');

    const res = await fetch('', { method: 'POST', body: formData });
    const text = (await res.text()).trim();
    
    if (text === 'success') {
        confirmBtn.style.display = 'none';
        vipConfirmBtn.style.display = 'none';
        alertBox.className = 'alert success';
        alertBox.textContent = '✅ Entry successful!';
        alertBox.style.display = 'block';
        autoLogTimeout = setTimeout(() => clearDisplay(), 3000);
        setTimeout(() => {
            alertBox.style.display = 'none';
        }, 3000);
    } else {
        alertBox.className = 'alert error';
        alertBox.textContent = '❌ Failed to log visitor. Please try again.';
        alertBox.style.display = 'block';
    }
}

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchEntry();
    }
});
</script>
</body>
</html>