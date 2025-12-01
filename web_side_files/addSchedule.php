<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['staff'])) {
    header("Location: staffLogin.php");
    exit;
}

// Handle logout
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['logout'])) {
    session_destroy();
    header("Location: staffLogin.php");
    exit;
}

$staff = $_SESSION['staff'] ?? $_SESSION['admin'];
$name = trim(($staff['FirstName'] ?? '') . ' ' . ($staff['LastName'] ?? ''));
$createdBy = $name;
$modifiedBy = $createdBy;
$campus = isset($_GET['campus']) ? htmlspecialchars($_GET['campus']) : ($staff['Campus'] ?? 'Unknown');

// 🔹 Fetch Firebase Student data
function fetchFirebaseData($node) {
    $url = rtrim(FIREBASE_DB_URL, '/') . '/' . $node . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$students = fetchFirebaseData("Student");

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['logout'])) {
    $subjects = json_decode($_POST['subject_list'] ?? '[]', true);
    $selected_students = json_decode($_POST['selected_students'] ?? '[]', true);

    // Determine academic year automatically
    $currentYear = (int)date("Y");
    $nextYear = $currentYear + 1;
    $academicYear = "$currentYear-$nextYear";

    foreach ($selected_students as $student) {
        $studentNumber = $student['studentNumber'];

        // Fetch student info from Firebase to get semester
        $studentInfo = [];
        foreach ($students as $key => $s) {
            if (($s['studentNumber'] ?? '') === $studentNumber) {
                $studentInfo = $s;
                break;
            }
        }
        $semester = $studentInfo['semester'] ?? '';

        $scheduleData = [
            "studentNumber" => $studentNumber,
            "campus" => $campus,
            "semester" => $semester,
            "academicYear" => $academicYear,
            "createdBy" => $createdBy,
            "dateTimeCreated" => date("Y-m-d H:i:s"),
            "modifiedBy" => $modifiedBy,
            "dateTimeModified" => date("Y-m-d H:i:s"),
            "subjects" => [] // all subjects will be stored here
        ];

        foreach ($subjects as $subj) {
            $scheduleData["subjects"][] = [
                "subject" => $subj['subject'],
                "days" => $subj['days'],
                "startTime" => $subj['start'],
                "endTime" => $subj['end']
            ];
        }

        // Push the record to Firebase
        $url = rtrim(FIREBASE_DB_URL, '/') . '/Schedules.json';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($scheduleData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    $message = "✅ Schedules saved successfully with semester and academic year!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Schedule | Bulacan State University</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Poppins',sans-serif; background:linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); color:#333; min-height:100vh; display:flex; flex-direction:column; }

/* HEADER */
header { background:linear-gradient(135deg, #870000 0%, #a30000 100%); color:#fff; padding:15px 40px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 4px 12px rgba(0,0,0,0.15); position:sticky; top:0; z-index:99; }
header .left-section { display:flex; align-items:center; gap:15px; }
header .left-section img { width:60px; height:60px; }
header h1 { font-size:20px; margin:0; font-weight:600; }

/* HEADER NAVIGATION */
.header-nav { display:flex; align-items:center; gap:5px; margin-left:auto; margin-right:30px; }
.header-nav a { display:flex; align-items:center; gap:8px; padding:10px 20px; color:#fff; text-decoration:none; font-weight:600; font-size:15px; border-radius:8px; transition:0.3s; }
.header-nav a:hover { background:rgba(255,255,255,0.15); }
.header-nav a.active { background:rgba(255,255,255,0.2); }
.header-nav i { font-size:16px; }

.profile-dropdown { position:relative; cursor:pointer; }
.profile-btn { display:flex; align-items:center; gap:10px; font-size:15px; background:rgba(255,255,255,0.1); border:2px solid rgba(255,255,255,0.2); color:white; padding:8px 16px; cursor:pointer; border-radius:25px; transition:0.3s; }
.profile-btn:hover { background:rgba(255,255,255,0.2); transform:translateY(-2px); }
.profile-btn .fa-user-circle { font-size:28px; }
.dropdown-content { display:none; position:absolute; right:0; top:60px; background:#fff; min-width:220px; box-shadow:0 6px 20px rgba(0,0,0,0.2); border-radius:12px; overflow:hidden; z-index:100; }
.dropdown-content p { padding:12px 18px; margin:0; font-size:14px; color:#333; border-bottom:1px solid #f0f0f0; }
.dropdown-content p i { margin-right:10px; color:#870000; }
.dropdown-content form { padding:8px; }
.dropdown-content .logout-btn { width:100%; text-align:left; background:none; color:#870000; padding:10px 12px; border:none; cursor:pointer; font-weight:600; border-radius:6px; transition:0.3s; }
.dropdown-content .logout-btn:hover { background:#870000; color:white; }

/* CONTAINER */
.container { max-width:1200px; margin:30px auto; padding:0 30px; flex:1; }

/* CARD */
.schedule-card { background:white; border-radius:16px; padding:35px; box-shadow:0 6px 16px rgba(0,0,0,0.1); }
.section-header { display:flex; align-items:center; gap:15px; margin-bottom:30px; padding-bottom:20px; border-bottom:3px solid #870000; }
.section-header i { font-size:32px; color:#870000; }
.section-header h2 { margin:0; font-size:28px; color:#870000; font-weight:700; }

.message { text-align:center; color:green; font-weight:600; margin-bottom:20px; padding:15px; background:#e8f5e9; border-radius:8px; }

label { display:block; font-weight:600; margin-top:20px; margin-bottom:8px; color:#333; font-size:15px; }
select, input[type="text"], input[type="time"] { width:100%; padding:12px 15px; border:2px solid #e0e0e0; border-radius:10px; font-size:15px; transition:0.3s; }
select:focus, input:focus { outline:none; border-color:#870000; box-shadow:0 0 0 3px rgba(135,0,0,0.1); }

#selectedStudents { display:flex; flex-wrap:wrap; gap:10px; padding:15px; background:#f8f9fa; border:2px solid #e0e0e0; border-radius:10px; margin-bottom:20px; min-height:60px; }
.selected-tag { background:#870000; color:white; padding:8px 15px; border-radius:20px; font-size:14px; display:flex; align-items:center; gap:8px; }
.selected-tag span { cursor:pointer; background:white; color:#870000; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:16px; }

.subject-input-group { display:grid; grid-template-columns:2fr 1fr 1fr; gap:15px; margin-bottom:15px; }
.days-group { display:flex; flex-wrap:wrap; gap:15px; margin:15px 0; }
.days-group label { display:flex; align-items:center; gap:6px; margin:0; font-weight:500; cursor:pointer; }
.days-group input[type="checkbox"] { width:18px; height:18px; cursor:pointer; }

table { width:100%; border-collapse:collapse; margin-top:20px; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
th, td { padding:15px; text-align:left; border-bottom:1px solid #f0f0f0; }
th { background:linear-gradient(135deg, #870000, #a30000); color:white; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
tbody tr:hover { background:#fff5f5; }

button { background:linear-gradient(135deg, #870000, #a30000); color:white; padding:12px 28px; border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; transition:0.3s; box-shadow:0 4px 12px rgba(135,0,0,0.2); }
button:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(135,0,0,0.3); }
button i { margin-right:8px; }

.btn-add-subject { background:linear-gradient(135deg, #4CAF50, #45a049); margin-top:15px; }
.btn-add-subject:hover { background:linear-gradient(135deg, #45a049, #3d8b40); }

footer { text-align:center; background:linear-gradient(135deg, #870000, #a30000); color:#fff; padding:15px; font-size:14px; box-shadow:0 -4px 12px rgba(0,0,0,0.1); margin-top:40px; }

/* RESPONSIVE */
@media(max-width:768px){
    header { padding:12px 20px; flex-wrap:wrap; }
    header h1 { font-size:16px; }
    .header-nav { margin:10px 0 0 0; width:100%; justify-content:center; }
    .header-nav a { padding:8px 15px; font-size:14px; }
    .container { padding:0 15px; }
    .subject-input-group { grid-template-columns:1fr; }
    .days-group { gap:10px; }
}
</style>
</head>
<body>

<header>
<div class="left-section">
    <img src="BSUU.webp" alt="BSU Logo">
    <h1>Bulacan State University</h1>
</div>

<nav class="header-nav">
    <a href="staffHome.php?view=students"><i class="fas fa-user-graduate"></i> Students</a>
    <a href="staffHome.php?view=guards"><i class="fas fa-user-shield"></i> Guards</a>
    <a href="addSchedule.php?campus=<?php echo urlencode($campus); ?>" class="active"><i class="fas fa-calendar-days"></i> Schedule</a>
</nav>

<div class="profile-dropdown">
    <button class="profile-btn" onclick="toggleDropdown()">
        <i class="fas fa-user-circle"></i>
        <span><?php echo htmlspecialchars($name); ?></span>
        <i class="fas fa-caret-down"></i>
    </button>
    <div class="dropdown-content" id="dropdownContent">
        <p><i class="fas fa-university"></i> <?php echo htmlspecialchars($campus); ?> Campus</p>
        <form method="post">
            <button type="submit" name="logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </div>
</div>
</header>

<div class="container">
    <div class="schedule-card">
        <div class="section-header">
            <i class="fas fa-calendar-alt"></i>
            <h2>Add Schedule - <?= htmlspecialchars($campus) ?> Campus</h2>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <form id="scheduleForm" method="POST">
            <label><i class="fas fa-users"></i> Select Students:</label>
            <select id="studentDropdown">
                <option value="">-- Choose a Student --</option>
                <?php
                if ($students) {
                    foreach ($students as $key => $student) {
                        if (strtolower($student['campus'] ?? '') !== strtolower($campus)) continue;
                        $studentName = ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '');
                        $studentNumber = $student['studentNumber'] ?? '';
                        $section = $student['section'] ?? '';
                        echo '<option value="' . htmlspecialchars($studentNumber) . '" data-name="' . htmlspecialchars($studentName) . '" data-section="' . htmlspecialchars($section) . '">' . htmlspecialchars($studentName) . ' (' . htmlspecialchars($studentNumber) . ')</option>';
                    }
                }
                ?>
            </select>

            <div id="selectedStudents"></div>

            <label><i class="fas fa-book"></i> Add Subjects:</label>
            <div class="subject-input-group">
                <input type="text" id="subjectName" placeholder="Subject Name">
                <input type="time" id="startTime">
                <input type="time" id="endTime">
            </div>

            <div class="days-group">
                <?php
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                foreach ($days as $d) {
                    echo '<label><input type="checkbox" name="days[]" value="'.$d.'"> '.$d.'</label>';
                }
                ?>
            </div>

            <button type="button" class="btn-add-subject" onclick="addSubject()">
                <i class="fas fa-plus"></i> Add Subject
            </button>

            <table id="subjectTable" style="display:none;">
                <thead><tr><th>Subject</th><th>Days</th><th>Time</th></tr></thead>
                <tbody></tbody>
            </table>

            <input type="hidden" name="selected_students" id="selectedStudentsInput">
            <input type="hidden" name="subject_list" id="subjectListInput">

            <button type="submit" style="margin-top:30px; width:100%;">
                <i class="fas fa-save"></i> Save Schedule
            </button>
        </form>
    </div>
</div>

<footer>&copy; <?= date("Y") ?> Bulacan State University | Student Gate Restriction System </footer>

<script>
function toggleDropdown() {
    const dropdown = document.getElementById('dropdownContent');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}
window.onclick = function(event) {
    if (!event.target.matches('.profile-btn') && !event.target.closest('.profile-dropdown')) {
        document.getElementById('dropdownContent').style.display = 'none';
    }
};

const studentDropdown = document.getElementById("studentDropdown");
const selectedStudentsDiv = document.getElementById("selectedStudents");
const hiddenInput = document.getElementById("selectedStudentsInput");
let selectedStudents = [];

studentDropdown.addEventListener("change", () => {
    const studentNumber = studentDropdown.value;
    const name = studentDropdown.options[studentDropdown.selectedIndex].getAttribute("data-name");
    const section = studentDropdown.options[studentDropdown.selectedIndex].getAttribute("data-section");

    if (studentNumber && !selectedStudents.some(s => s.studentNumber === studentNumber)) {
        selectedStudents.push({ studentNumber, name, section });
        updateSelectedTags();
    }
    studentDropdown.value = "";
});

function updateSelectedTags() {
    selectedStudentsDiv.innerHTML = "";
    selectedStudents.forEach(s => {
        const tag = document.createElement("div");
        tag.classList.add("selected-tag");
        tag.innerHTML = `${s.name} (${s.section}) <span data-id="${s.studentNumber}">&times;</span>`;
        tag.querySelector("span").addEventListener("click", () => {
            selectedStudents = selectedStudents.filter(stu => stu.studentNumber !== s.studentNumber);
            updateSelectedTags();
        });
        selectedStudentsDiv.appendChild(tag);
    });
    hiddenInput.value = JSON.stringify(selectedStudents);
}

// Subject logic
let subjects = [];
function addSubject() {
    const subject = document.getElementById("subjectName").value.trim();
    const start = document.getElementById("startTime").value;
    const end = document.getElementById("endTime").value;
    const checkedDays = [...document.querySelectorAll("input[name='days[]']:checked")].map(cb => cb.value);

    if (!subject || !start || !end || checkedDays.length === 0) {
        alert("Please fill in subject, days, and time");
        return;
    }

    subjects.push({ subject, start, end, days: checkedDays });
    renderSubjects();
    document.getElementById("subjectListInput").value = JSON.stringify(subjects);

    document.getElementById("subjectName").value = "";
    document.getElementById("startTime").value = "";
    document.getElementById("endTime").value = "";
    document.querySelectorAll("input[name='days[]']").forEach(cb => cb.checked = false);
}

function renderSubjects() {
    const table = document.getElementById("subjectTable");
    const tbody = table.querySelector("tbody");
    tbody.innerHTML = "";
    subjects.forEach(sub => {
        const row = `<tr><td>${sub.subject}</td><td>${sub.days.join(", ")}</td><td>${sub.start} - ${sub.end}</td></tr>`;
        tbody.innerHTML += row;
    });
    table.style.display = subjects.length > 0 ? "table" : "none";
}
</script>

</body>
</html>