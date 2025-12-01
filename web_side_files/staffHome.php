<?php
session_start();

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: staffLogin.php');
    exit;
}

if (!isset($_SESSION['staff'])) {
    header('Location: staffLogin.php');
    exit;
}

require_once 'config.php';

$staff = $_SESSION['staff'];
$campus = $staff['Campus'] ?? 'Unknown';
$name = trim(($staff['FirstName'] ?? '') . ' ' . ($staff['LastName'] ?? ''));

$campusLogo = 'BSUU.webp';
switch (strtolower($campus)) {
    case 'main': $campusLogo = 'BSUU.webp'; break;
    case 'meneses': $campusLogo = 'Meneses.png'; break;
    case 'sarmiento': $campusLogo = 'Sarmiento.png'; break;
    case 'bustos': $campusLogo = 'Bustos.png'; break;
    case 'sanrafael':
    case 'san rafael': $campusLogo = 'San Rafael.png'; break;
    case 'hagonoy': $campusLogo = 'Hagonoy.png'; break;
    default: $campusLogo = 'BSUU.webp';
}

function fetchFirebaseData($node) {
    $url = rtrim(FIREBASE_DB_URL, '/') . '/' . $node . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    return is_array($data) ? $data : [];
}

$studentData = fetchFirebaseData('Student');
$guardData = fetchFirebaseData('Guard');

$currentView = isset($_GET['view']) ? $_GET['view'] : 'students';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Staff Home | <?php echo htmlspecialchars($campus); ?> Campus</title>
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
.container { max-width:1400px; margin:30px auto; padding:0 30px; flex:1; }

/* RECORDS SECTION */
.records-section { background:white; border-radius:16px; padding:35px; box-shadow:0 6px 16px rgba(0,0,0,0.1); }
.section-header { display:flex; align-items:center; gap:15px; margin-bottom:30px; padding-bottom:20px; border-bottom:3px solid #870000; }
.section-header i { font-size:32px; color:#870000; }
.section-header h2 { margin:0; font-size:28px; color:#870000; font-weight:700; }

.search-bar { display:flex; gap:15px; justify-content:space-between; align-items:center; margin-bottom:30px; padding:25px; background:#f8f9fa; border-radius:12px; flex-wrap:wrap; }
.search-bar .search-group { display:flex; gap:15px; flex:1; }
.search-bar input { padding:16px 20px; flex:1; max-width:500px; border:2px solid #e0e0e0; border-radius:10px; font-size:16px; transition:0.3s; }
.search-bar input:focus { outline:none; border-color:#870000; box-shadow:0 0 0 3px rgba(135,0,0,0.1); }
.search-bar button { background:linear-gradient(135deg, #870000, #a30000); color:#fff; padding:16px 35px; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:16px; transition:0.3s; box-shadow:0 4px 10px rgba(135,0,0,0.2); }
.search-bar button:hover { transform:translateY(-2px); box-shadow:0 6px 14px rgba(135,0,0,0.3); }
.search-bar button i { margin-right:8px; }
.btn-add-student { background:linear-gradient(135deg, #4CAF50, #45a049); }
.btn-add-student:hover { background:linear-gradient(135deg, #45a049, #3d8b40); }

/* TABLE */
.table-wrapper { overflow-x:auto; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
table { width:100%; border-collapse:collapse; background:white; }
th, td { padding:18px 16px; text-align:left; vertical-align:middle; border-bottom:1px solid #f0f0f0; font-size:15px; }
th { background:linear-gradient(135deg, #870000, #a30000); color:#fff; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; position:sticky; top:0; }
tbody tr { transition:0.2s; }
tbody tr:hover { background:#fff5f5; }
.empty { padding:80px 20px; text-align:center; color:#999; }
.empty i { font-size:80px; color:#ddd; margin-bottom:20px; display:block; }
.empty p { font-size:18px; margin-top:10px; }

.actions { text-align:center; white-space:nowrap; }
.actions i { cursor:pointer; font-size:20px; margin:0 10px; transition:0.3s; padding:10px; border-radius:8px; color:#870000; }
.actions i:hover { transform:scale(1.3); }

/* MODAL - FIXED FOR SCROLLING */
.modal { 
    display:none; 
    position:fixed; 
    top:0; 
    left:0; 
    width:100%; 
    height:100%; 
    background:rgba(0,0,0,0.7); 
    justify-content:center; 
    align-items:center; 
    z-index:200; 
    backdrop-filter:blur(4px); 
    padding:20px; 
    overflow-y:auto; 
}

.modal-content { 
    background:#fff; 
    border-radius:20px; 
    width:90%; 
    max-width:600px; 
    max-height:90vh; 
    position:relative; 
    box-shadow:0 10px 40px rgba(0,0,0,0.3); 
    animation:modalSlideIn 0.3s ease; 
    margin:auto; 
    display:flex; 
    flex-direction:column;
    overflow:hidden;
}

@keyframes modalSlideIn { from { transform:translateY(-50px); opacity:0; } to { transform:translateY(0); opacity:1; } }

.modal-header { 
    position:relative;
    background:#fff; 
    padding:20px 35px;
    border-bottom:2px solid #f0f0f0;
    flex-shrink:0;
}

.modal-content h3 { 
    margin:0; 
    color:#870000; 
    text-align:center; 
    font-size:24px; 
    padding-right:30px;
}

.modal-body { 
    padding:20px 35px;
    overflow-y:auto;
    flex:1;
    min-height:0;
}

.modal-content label { 
    font-weight:600; 
    margin-top:12px; 
    display:block; 
    color:#333; 
    font-size:14px; 
}

.modal-content input, .modal-content select { 
    width:100%; 
    padding:10px 12px; 
    margin-top:6px; 
    border:2px solid #e0e0e0; 
    border-radius:8px; 
    font-size:14px; 
    box-sizing:border-box; 
    transition:0.3s; 
}

.modal-content input:focus, .modal-content select:focus { 
    outline:none; 
    border-color:#870000; 
    box-shadow:0 0 0 3px rgba(135,0,0,0.1); 
}

.modal-content input:read-only { 
    background:#f5f5f5; 
    cursor:not-allowed; 
}

.close-btn { 
    position:absolute; 
    top:20px; 
    right:20px; 
    font-size:28px; 
    color:#870000; 
    background:none; 
    border:none; 
    cursor:pointer; 
    transition:0.3s; 
    width:35px; 
    height:35px; 
    border-radius:50%; 
    display:flex; 
    align-items:center; 
    justify-content:center; 
    z-index:11; 
}

.close-btn:hover { 
    background:#ffebee; 
    transform:rotate(90deg); 
}

.form-actions { 
    display:flex; 
    gap:12px; 
    justify-content:flex-end; 
    margin-top:20px; 
    padding-top:15px; 
    border-top:2px solid #f0f0f0;
    background:#fff;
    flex-shrink:0;
}

.submit-btn { 
    background:linear-gradient(135deg, #870000, #a30000); 
    color:#fff; 
    padding:10px 24px; 
    border:none; 
    border-radius:8px; 
    font-weight:600; 
    cursor:pointer; 
    transition:0.3s; 
    box-shadow:0 4px 12px rgba(135,0,0,0.2); 
}

.submit-btn:hover { 
    transform:translateY(-2px); 
    box-shadow:0 6px 16px rgba(135,0,0,0.3); 
}

.cancel-btn { 
    background:#e0e0e0; 
    color:#333; 
    padding:10px 24px; 
    border:none; 
    border-radius:8px; 
    cursor:pointer; 
    font-weight:600; 
    transition:0.3s; 
}

.cancel-btn:hover { 
    background:#d0d0d0; 
}

/* ID CARD */
.id-card { background:linear-gradient(135deg, #fff5f5 0%, #ffffff 100%); border:2px solid #870000; border-radius:16px; padding:30px; text-align:center; box-shadow:0 6px 20px rgba(0,0,0,0.1); }
.id-card img { width:140px; height:140px; border-radius:50%; margin-bottom:20px; object-fit:cover; border:5px solid #870000; box-shadow:0 4px 12px rgba(0,0,0,0.15); }
.id-card h3 { margin:15px 0; color:#870000; font-size:24px; font-weight:700; }
.id-card p { margin:12px 0; font-weight:500; text-align:left; padding:10px 15px; background:white; border-radius:8px; border-left:4px solid #870000; }
.id-card p strong { color:#870000; margin-right:8px; }

footer { text-align:center; background:linear-gradient(135deg, #870000, #a30000); color:#fff; padding:15px; font-size:14px; box-shadow:0 -4px 12px rgba(0,0,0,0.1); margin-top:40px; }

/* RESPONSIVE */
@media(max-width:768px){
    header { padding:12px 20px; flex-wrap:wrap; }
    header h1 { font-size:16px; }
    .header-nav { margin:10px 0 0 0; width:100%; justify-content:center; }
    .header-nav a { padding:8px 15px; font-size:14px; }
    .container { padding:0 15px; }
    .search-bar { flex-direction:column; }
    .search-bar .search-group { width:100%; flex-direction:column; }
    .search-bar input { max-width:100%; }
    .btn-add-student { width:100%; }
    table { font-size:13px; }
    th, td { padding:12px 10px; }
    .actions i { font-size:18px; margin:0 6px; }
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
    <a href="staffHome.php?view=students" class="<?php echo (!isset($_GET['view']) || $_GET['view'] == 'students') ? 'active' : ''; ?>"><i class="fas fa-user-graduate"></i> Students</a>
    <a href="staffHome.php?view=guards" class="<?php echo (isset($_GET['view']) && $_GET['view'] == 'guards') ? 'active' : ''; ?>"><i class="fas fa-user-shield"></i> Guards</a>
    <a href="addSchedule.php?campus=<?php echo urlencode($campus); ?>"><i class="fas fa-calendar-days"></i> Schedule</a>
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
    <!-- Student Records Section -->
    <?php if($currentView == 'students'): ?>
    <div class="records-section">
        <div class="section-header">
            <i class="fas fa-user-graduate"></i>
            <h2>Student Records - <?php echo htmlspecialchars($campus); ?> Campus</h2>
        </div>

        <div class="search-bar">
            <div class="search-group">
                <input type="text" id="searchInput" placeholder="🔍 Search by student number, name, or course..." />
                <button onclick="searchStudents()"><i class="fas fa-search"></i> Search</button>
            </div>
            <button class="btn-add-student" onclick="openModal('student','add')"><i class="fas fa-user-plus"></i> Add Student</button>
        </div>

        <div class="table-wrapper">
            <table id="studentTable">
                <thead>
                    <tr>
                        <th>Student Number</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Course</th>
                        <th>Section</th>
                        <th>Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentBody"></tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Guard Records Section -->
    <?php if($currentView == 'guards'): ?>
    <div class="records-section">
        <div class="section-header">
            <i class="fas fa-user-shield"></i>
            <h2>Guard Records - <?php echo htmlspecialchars($campus); ?> Campus</h2>
        </div>

        <div class="search-bar">
            <div class="search-group">
                <input type="text" id="searchGuardInput" placeholder="🔍 Search by name or gate..." />
                <button onclick="searchGuards()"><i class="fas fa-search"></i> Search</button>
            </div>
            <button class="btn-add-student" onclick="openModal('guard','add')"><i class="fas fa-user-plus"></i> Add Guard</button>
        </div>

        <div class="table-wrapper">
            <table id="guardTable">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Gate</th>
                        <th>Campus</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="guardBody"></tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<footer>&copy; <?php echo date("Y"); ?> Bulacan State University | Student Gate Restriction System</footer>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-content">
        <div class="modal-header">
            <button class="close-btn" onclick="closeModal()">&times;</button>
            <h3 id="modalTitle">Title</h3>
        </div>
        <div class="modal-body">
            <div id="modalFields"></div>
            <form id="modalForm" style="display:none;">
                <div id="formFields"></div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

const firebaseBase = '<?= rtrim(FIREBASE_DB_URL,"/") ?>';
const currentCampus = '<?= $campus ?>';
let studentData = <?= json_encode($studentData) ?>;
let guardData = <?= json_encode($guardData) ?>;

// STUDENT FUNCTIONS
function renderTable(data) {
    const tbody = document.getElementById('studentBody');
    tbody.innerHTML = '';
    
    if (Object.keys(data).length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty"><i class="fas fa-inbox"></i><p>No students found</p></td></tr>`;
        return;
    }

    for(const key in data){
        const rec = data[key];
        if((rec.campus || rec.Campus) !== currentCampus) continue;
        tbody.innerHTML += `<tr>
            <td>${rec.studentNumber || 'N/A'}</td>
            <td>${rec.first_name || 'N/A'}</td>
            <td>${rec.last_name || 'N/A'}</td>
            <td>${rec.course || 'N/A'}</td>
            <td>${rec.section || 'N/A'}</td>
            <td>${rec.year || 'N/A'}</td>
            <td class="actions">
                <i class="fas fa-eye view" title="View" onclick="openModal('student','view','${key}')"></i>
                <i class="fas fa-edit edit" title="Edit" onclick="openModal('student','edit','${key}')"></i>
                <i class="fas fa-trash delete" title="Delete" onclick="deleteRecord('student','${key}')"></i>
            </td>
        </tr>`;
    }
}

function searchStudents() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    
    if (!searchTerm) {
        renderTable(studentData);
        return;
    }
    
    let filtered = {};
    for(const key in studentData){
        const rec = studentData[key];
        if((rec.studentNumber && rec.studentNumber.toLowerCase().includes(searchTerm)) ||
           (rec.first_name && rec.first_name.toLowerCase().includes(searchTerm)) ||
           (rec.last_name && rec.last_name.toLowerCase().includes(searchTerm)) ||
           (rec.course && rec.course.toLowerCase().includes(searchTerm))){
            filtered[key] = rec;
        }
    }
    renderTable(filtered);
}

// GUARD FUNCTIONS
function renderGuardTable(data) {
    const tbody = document.getElementById('guardBody');
    tbody.innerHTML = '';
    
    if (Object.keys(data).length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="empty"><i class="fas fa-inbox"></i><p>No guards found</p></td></tr>`;
        return;
    }

    for(const key in data){
        const rec = data[key];
        const recCampus = (rec.Campus || rec.campus || '').trim().toLowerCase();
        if(recCampus !== currentCampus.toLowerCase()) continue;
        tbody.innerHTML += `<tr>
            <td>${rec.FirstName || rec.first_name || 'N/A'}</td>
            <td>${rec.LastName || rec.last_name || 'N/A'}</td>
            <td>${rec.Email || rec.email || 'N/A'}</td>
            <td>${rec.Gate || rec.gate || 'N/A'}</td>
            <td>${rec.Campus || rec.campus || 'N/A'}</td>
            <td class="actions">
                <i class="fas fa-eye view" title="View" onclick="openModal('guard','view','${key}')"></i>
                <i class="fas fa-edit edit" title="Edit" onclick="openModal('guard','edit','${key}')"></i>
                <i class="fas fa-trash delete" title="Delete" onclick="deleteRecord('guard','${key}')"></i>
            </td>
        </tr>`;
    }
}

function searchGuards() {
    const searchTerm = document.getElementById('searchGuardInput').value.toLowerCase().trim();
    
    if (!searchTerm) {
        renderGuardTable(guardData);
        return;
    }
    
    let filtered = {};
    for(const key in guardData){
        const rec = guardData[key];
        if((rec.FirstName && rec.FirstName.toLowerCase().includes(searchTerm)) ||
           (rec.first_name && rec.first_name.toLowerCase().includes(searchTerm)) ||
           (rec.LastName && rec.LastName.toLowerCase().includes(searchTerm)) ||
           (rec.last_name && rec.last_name.toLowerCase().includes(searchTerm)) ||
           (rec.Gate && rec.Gate.toLowerCase().includes(searchTerm)) ||
           (rec.gate && rec.gate.toLowerCase().includes(searchTerm))){
            filtered[key] = rec;
        }
    }
    renderGuardTable(filtered);
}

function openModal(type, mode, key='') {
    const modal = document.getElementById('modal');
    const title = document.getElementById('modalTitle');
    const fields = document.getElementById('modalFields');
    const form = document.getElementById('modalForm');
    const formFields = document.getElementById('formFields');
    
    let data = key ? (type === 'student' ? studentData[key] : guardData[key]) || {} : {};
    const defaultPic = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

    if(mode==='view'){
        title.textContent = type === 'student' ? 'View Student' : 'View Guard';
        form.style.display = 'none';
        
        if(type === 'student') {
            fields.innerHTML = `<div class="id-card">
                <img src="${data.profileImageUrl||defaultPic}" alt="Profile" onerror="this.src='${defaultPic}'">
                <h3>${data.first_name||''} ${data.last_name||''}</h3>
                <p><strong>Student Number:</strong> ${data.studentNumber||''}</p>
                <p><strong>Course:</strong> ${data.course||''}</p>
                <p><strong>Section:</strong> ${data.section||''}</p>
                <p><strong>Year:</strong> ${data.year||''}</p>
                <p><strong>Semester:</strong> ${data.semester||''}</p>
                <p><strong>Campus:</strong> ${data.campus||''}</p>
            </div>`;
        } else {
            fields.innerHTML = `<div class="id-card">
                <img src="${data.Photo||data.photo||defaultPic}" alt="Profile" onerror="this.src='${defaultPic}'">
                <h3>${data.FirstName||data.first_name||''} ${data.LastName||data.last_name||''}</h3>
                <p><strong>Email:</strong> ${data.Email||data.email||''}</p>
                <p><strong>Gate:</strong> ${data.Gate||data.gate||''}</p>
                <p><strong>Campus:</strong> ${data.Campus||data.campus||''}</p>
                <p><strong>Password:</strong> ${data.Password||data.password||''}</p>
            </div>`;
        }
    } else {
        title.textContent = (mode==='add'?'Add':'Edit')+' '+(type==='student'?'Student':'Guard');
        form.style.display = 'block';
        fields.innerHTML = '';

        if(type === 'student') {
            const fname = data.first_name||'';
            const lname = data.last_name||'';
            const mname = data.middle_name||'';
            const snum = data.studentNumber||'';
            const course = data.course||'';
            const section = data.section||'';
            const year = data.year||'';
            const semester = data.semester||'';
            const contact = data.contact_number||'';
            const email = data.email||'';

            formFields.innerHTML = `
                <label>Student Number</label>
                <input name="studentNumber" value="${snum}" ${mode==='edit'?'readonly':''} required>
                <label>First Name</label>
                <input name="first_name" value="${fname}" required>
                <label>Middle Name</label>
                <input name="middle_name" value="${mname}">
                <label>Last Name</label>
                <input name="last_name" value="${lname}" required>
                <label>Course</label>
                <input name="course" value="${course}" placeholder="e.g. BSIT" required>
                <label>Section</label>
                <input name="section" value="${section}" placeholder="e.g. 3H-G2" required>
                <label>Year</label>
                <select name="year" required>
                    <option value="1" ${year=='1'?'selected':''}>1st Year</option>
                    <option value="2" ${year=='2'?'selected':''}>2nd Year</option>
                    <option value="3" ${year=='3'?'selected':''}>3rd Year</option>
                    <option value="4" ${year=='4'?'selected':''}>4th Year</option>
                </select>
                <label>Semester</label>
                <select name="semester" required>
                    <option value="1st Semester" ${semester=='1st Semester'?'selected':''}>1st Semester</option>
                    <option value="2nd Semester" ${semester=='2nd Semester'?'selected':''}>2nd Semester</option>
                </select>
                <label>Contact Number</label>
                <input name="contact_number" value="${contact}" placeholder="e.g. 09123456789" ${mode==='add'?'required':''}>
                <label>Email</label>
                <input name="email" type="email" value="${email}" placeholder="e.g. student@gmail.com" ${mode==='add'?'required':''}>
                <label>Campus</label>
                <input name="campus" value="${currentCampus}" readonly>
            `;
        } else {
            const first = data.FirstName||data.first_name||'';
            const last = data.LastName||data.last_name||'';
            const email = data.Email||data.email||'';
            const gate = data.Gate||data.gate||'';
            const password = data.Password||data.password||'';

            formFields.innerHTML = `
                <label>First Name</label>
                <input name="FirstName" value="${first}" required>
                <label>Last Name</label>
                <input name="LastName" value="${last}" required>
                <label>Email</label>
                <input name="Email" type="email" value="${email}">
                <label>Password</label>
                <input name="Password" value="${password}" required>
                <label>Gate</label>
                <input name="Gate" value="${gate}" placeholder="e.g. Main Gate">
                <label>Campus</label>
                <input name="Campus" value="${currentCampus}" readonly>
            `;
        }

        form.onsubmit = async (e) => {
            e.preventDefault();
            const formData = Object.fromEntries(new FormData(form).entries());
            
            if(type === 'student' && mode === 'add') {
                try {
                    const response = await fetch('addStudent_handler.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(formData)
                    });
                    const result = await response.json();
                    if(result.success) {
                        alert('Student added successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (result.message || 'Failed to add student'));
                    }
                } catch(err) {
                    console.error(err);
                    alert('Error connecting to server');
                }
            } else {
                const firebaseKey = key || Date.now();
                const nodePath = type === 'student' ? 'Student' : 'Guard';
                try{
                    const res = await fetch(`${firebaseBase}/${nodePath}/${firebaseKey}.json`,{
                        method:'PUT',
                        headers:{'Content-Type':'application/json'},
                        body: JSON.stringify(formData)
                    });
                    if(res.ok){ alert('Saved successfully!'); location.reload(); }
                    else alert('Failed to save.' );
                } catch(err){ console.error(err); alert('Error connecting to Firebase'); }
            }
        };
    }

    modal.style.display='flex';
}

function closeModal() { 
    document.getElementById('modal').style.display='none'; 
}

async function deleteRecord(type, key){
    if(!confirm(`Are you sure to delete this ${type}?`)) return;
    const nodePath = type === 'student' ? 'Student' : 'Guard';
    try{
        const res = await fetch(`${firebaseBase}/${nodePath}/${key}.json`,{method:'DELETE'});
        if(res.ok){ alert('Deleted successfully'); location.reload(); }
        else alert('Failed to delete.');
    } catch(err){ console.error(err); alert('Error connecting to Firebase'); }
}

// Event listeners
const searchInput = document.getElementById('searchInput');
if(searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchStudents();
        }
    });
}

const searchGuardInput = document.getElementById('searchGuardInput');
if(searchGuardInput) {
    searchGuardInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchGuards();
        }
    });
}

window.addEventListener('load', () => { 
    if(document.getElementById('studentBody')) {
        renderTable(studentData);
    }
    if(document.getElementById('guardBody')) {
        renderGuardTable(guardData);
    }
});
</script>

</body>
</html>