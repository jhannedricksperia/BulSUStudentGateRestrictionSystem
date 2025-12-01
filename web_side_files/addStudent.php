<?php
session_start();

define('FIREBASE_API_KEY', 'AIzaSyA3iMEdOts7yHfcf0Ws1qFAZuRER2bsHvI');
define('FIREBASE_DB_URL', 'https://gatemanagementsystem-default-rtdb.asia-southeast1.firebasedatabase.app/');
define('FIREBASE_STORAGE_BUCKET', 'gatemanagementsystem.appspot.com');

if (!isset($_SESSION['admin']) && !isset($_SESSION['staff'])) {
    header("Location: staffLogin.php");
    exit;
}

$campus = isset($_GET['campus']) ? htmlspecialchars($_GET['campus']) : "Unknown";

function generateStudentKey() {
    return 'student_' . uniqid();
}

function pushToFirebase($path, $data) {
    $url = rtrim(FIREBASE_DB_URL, '/') . '/' . $path . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function createFirebaseUser($email, $password) {
    $apiKey = FIREBASE_API_KEY;
    $url = "https://identitytoolkit.googleapis.com/v1/accounts:signUp?key={$apiKey}";

    $data = [
        "email" => $email,
        "password" => $password,
        "returnSecureToken" => true
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function saveUserToDatabase($uid, $email, $role, $idToken) {
    $url = FIREBASE_DB_URL . "/users/{$uid}.json?auth={$idToken}";
    $data = [
        'email' => $email,
        'role' => $role,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// ✅ Upload to Firebase Storage (no Composer)
function uploadToFirebaseStorage($fileTmpPath, $fileName) {
    $bucketName = FIREBASE_STORAGE_BUCKET;
    $uploadUrl = "https://firebasestorage.googleapis.com/v0/b/{$bucketName}/o?name=students/" . urlencode($fileName);
    $fileData = file_get_contents($fileTmpPath);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/octet-stream"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    if (isset($json['name'])) {
        return "https://firebasestorage.googleapis.com/v0/b/{$bucketName}/o/" . rawurlencode($json['name']) . "?alt=media";
    }
    return null;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $studentNumber = $_POST['studentNumber'];
    $firstName     = $_POST['firstName'];
    $middleName    = $_POST['middleName'];
    $lastName      = $_POST['lastName'];
    $course        = $_POST['course'];
    $year          = $_POST['year'];
    $section       = $_POST['section'];
    $campus        = $_POST['campus'];
    $semester      = $_POST['semester'];
    $contact       = $_POST['contact'];
    $email         = $_POST['email'];
    $password      = $studentNumber . $lastName;
    $createdAt     = date("Y-m-d H:i:s");

    // ✅ Upload image directly to Firebase Storage
    $profileImageUrl = "https://firebasestorage.googleapis.com/v0/b/" . FIREBASE_STORAGE_BUCKET . "/o/default-profile.png?alt=media";

    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profileImage']['tmp_name'];
        $fileName = uniqid() . "_" . basename($_FILES['profileImage']['name']);
        $uploadedUrl = uploadToFirebaseStorage($fileTmpPath, $fileName);
        if ($uploadedUrl) {
            $profileImageUrl = $uploadedUrl;
        }
    }

    // Step 1: Create Firebase Authentication user
    $authResponse = createFirebaseUser($email, $password);

    if (isset($authResponse['error'])) {
        $message = "<p style='color:red; font-weight:600;'>❌ " . $authResponse['error']['message'] . "</p>";
    } else {
        $uid = $authResponse['localId'];
        $idToken = $authResponse['idToken'];

        // Step 2: Save user info in /users
        saveUserToDatabase($uid, $email, 'student', $idToken);

        // Step 3: Save student info in /Student
        $studentKey = generateStudentKey();

        $studentData = [
            "studentNumber"   => $studentNumber,
            "first_name"      => $firstName,
            "middle_name"     => $middleName,
            "last_name"       => $lastName,
            "course"          => $course,
            "year"            => $year,
            "section"         => $section,
            "campus"          => $campus,
            "semester"        => $semester,
            "contact_number"  => $contact,
            "email"           => $email,
            "auth_uid"        => $uid,
            "profileImageUrl" => $profileImageUrl,
            "created_at"      => $createdAt,
            "modified_at"     => $createdAt,
            "created_by"      => $_SESSION['staff'] ?? $_SESSION['admin'],
            "modified_by"     => $_SESSION['staff'] ?? $_SESSION['admin']
        ];

        // ✅ Add FCM token placeholder
        $studentData["fcmToken"] = ""; // Will be updated by the mobile app later

        $response = pushToFirebase("Student/$studentKey", $studentData);

        if ($response) {
            $message = "<p style='color:green; font-weight:600;'>✅ Student successfully registered and image stored!</p>";
        } else {
            $message = "<p style='color:red; font-weight:600;'>❌ Failed to save student data.</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Student | Bulacan State University</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #fff;
    margin: 0;
    color: #333;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
header {
    background: #870000;
    color: #fff;
    padding: 15px 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
header img {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    margin-right: 15px;
}
header h1 {
    font-size: 24px;
    margin: 0;
    text-align: center;
}
.back-btn {
    position: absolute;
    left: 20px;
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
}
.container {
    max-width: 550px;
    margin: 50px auto;
    padding: 30px;
    background: #fff5f5;
    border: 2px solid #870000;
    border-radius: 12px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    text-align: center;
}
h2 {
    color: #870000;
    margin-bottom: 10px;
}
label {
    display: block;
    margin-top: 15px;
    font-weight: 600;
    text-align: left;
}
input, select {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
}
button {
    margin-top: 25px;
    padding: 10px 20px;
    border: none;
    background: #870000;
    color: #fff;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.3s;
    font-weight: 600;
}
button:hover {
    background: #a00000;
}
footer {
    text-align: center;
    background: #870000;
    color: #fff;
    padding: 10px;
    font-size: 14px;
    margin-top: auto;
}
</style>
</head>
<body>

<header>
    <button class="back-btn" onclick="window.location.href='staffHome.php?campus=<?php echo urlencode($campus); ?>'">
        <i class="fas fa-arrow-left"></i>
    </button>
    <img src="BSUU.webp" alt="BSU Logo">
    <h1>
        Bulacan State University<br>
        <span style="font-size:18px;">Add Student - <?php echo htmlspecialchars($campus); ?> Campus</span>
    </h1>
</header>

<div class="container">
    <h2><i class="fas fa-user-plus"></i> Add Student</h2>
    <?php echo $message; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Student Number</label>
        <input type="text" name="studentNumber" required>

        <label>First Name</label>
        <input type="text" name="firstName" required>

        <label>Middle Name</label>
        <input type="text" name="middleName" required>

        <label>Last Name</label>
        <input type="text" name="lastName" required>

        <label>Course</label>
        <input type="text" name="course" required placeholder="e.g. BSIT">

        <label>Year</label>
        <select name="year" required>
            <option value="1">1st Year</option>
            <option value="2">2nd Year</option>
            <option value="3">3rd Year</option>
            <option value="4">4th Year</option>
        </select>

        <label>Section</label>
        <input type="text" name="section" required placeholder="e.g. 3H-G2">

        <label>Semester</label>
        <select name="semester" required>
            <option value="1st Semester">1st Semester</option>
            <option value="2nd Semester">2nd Semester</option>
        </select>

        <label>Contact Number</label>
        <input type="text" name="contact" required placeholder="e.g. 09123456789">

        <label>Email</label>
        <input type="email" name="email" required placeholder="e.g. student@gmail.com">

        <label>Profile Image</label>
        <input type="file" name="profileImage" accept="image/*">

        <input type="hidden" name="campus" value="<?php echo htmlspecialchars($campus); ?>">

        <button type="submit"><i class="fas fa-save"></i> Save Student</button>
    </form>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> Bulacan State University | Gate System
</footer>

</body>
</html>
