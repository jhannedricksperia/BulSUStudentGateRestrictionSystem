<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin'])) {
    header("Location: adminLogin.php");
    exit;
}

$campus = isset($_GET['campus']) ? htmlspecialchars($_GET['campus']) : ($_SESSION['admin']['Campus'] ?? 'Main');

// Load campuses dynamically from campuses.json
$campusesFile = 'campuses.json';
$campuses = [];

if (file_exists($campusesFile)) {
    $jsonContent = file_get_contents($campusesFile);
    $campuses = json_decode($jsonContent, true);
    if (!is_array($campuses)) {
        $campuses = [];
    }
}

// If no campuses exist, create a default Main campus
if (empty($campuses)) {
    $campuses = ['Main' => 'BSUU.webp'];
    file_put_contents($campusesFile, json_encode($campuses, JSON_PRETTY_PRINT));
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

$staffData = fetchFirebaseData('Staff');
$guardData = fetchFirebaseData('Guard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= $campus ?> Campus Management | Gate System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{font-family:'Poppins',sans-serif;background:#fff;margin:0;color:#333;min-height:100vh;display:flex;flex-direction:column;}
header{background:#870000;color:#fff;padding:10px 30px;display:flex;justify-content:space-between;align-items:center;}
header .left-section{display:flex;align-items:center;gap:12px;}
header img{width:70px;height:70px;}
header h1{margin:0;font-size:24px;}
.nav-links{display:flex;gap:20px;}
.nav-links a{color:#fff;text-decoration:none;font-weight:500;font-size:16px;}
.nav-links a:hover, .nav-links a.active{color:#ffcc00;font-weight:600;border-bottom:2px solid #ffcc00;padding-bottom:3px;}

/* Campus Navigation */
.campus-nav {
    background: #f5f5f5;
    padding: 15px 30px;
    border-bottom: 2px solid #ddd;
    display: flex;
    gap: 10px;
    align-items: center;
    overflow-x: auto;
}
.campus-nav span {
    font-weight: 600;
    color: #333;
    white-space: nowrap;
}
.campus-nav a {
    padding: 8px 15px;
    background: #fff;
    color: #870000;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    border: 2px solid #ddd;
    white-space: nowrap;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.campus-nav a:hover {
    background: #870000;
    color: #fff;
    border-color: #870000;
}
.campus-nav a.active {
    background: #870000;
    color: #fff;
    border-color: #870000;
}
.campus-nav img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
}

.container{max-width:1100px;margin:20px auto;padding:20px;flex:1;width:100%;}
.card-container{display:flex;justify-content:center;flex-wrap:wrap;gap:25px;margin-bottom:30px;}
.card{background:#fff5f5;border:2px solid #870000;border-radius:12px;width:260px;padding:25px;text-align:center;box-shadow:0 3px 8px rgba(0,0,0,0.1);cursor:pointer;transition:.25s;}
.card:hover{transform:translateY(-5px);background:#ffecec;box-shadow:0 5px 15px rgba(135,0,0,0.2);}
.card i{font-size:40px;color:#870000;margin-bottom:12px;}
.card h3{margin:10px 0;color:#870000;}
.card p{margin:5px 0;font-size:13px;color:#666;}
select{padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:14px;}
.records-card{border:2px solid #870000;border-radius:12px;padding:20px;background:#fff5f5;box-shadow:0 3px 8px rgba(0,0,0,0.1);margin-top:30px;}
.records-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:15px;}
.records-header h2{margin:0;font-size:20px;}
table{width:100%;border-collapse:collapse;border-radius:10px;overflow:hidden;}
th, td{border:1px solid #ccc;padding:12px;text-align:left;vertical-align:middle;}
th{background:#870000;color:#fff;font-weight:600;}
tr:nth-child(even){background:#fefefe;}
tr:hover{background:#ffe5e5;}
.empty{padding:40px;text-align:center;color:#999;}
.empty i{font-size:60px;color:#ccc;margin-bottom:15px;display:block;}
footer{text-align:center;background:#870000;color:#fff;padding:10px;font-size:14px;margin-top:auto;}
.actions{text-align:center;}
.actions i{cursor:pointer;font-size:18px;margin:0 6px;transition:0.3s;color:#870000;}
.actions i:hover{transform:scale(1.3);color:#b30000;}

/* Modal styling */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:200;overflow-y:auto;}
.modal.active{display:flex;}
.modal-content{background:#fff;border-radius:15px;padding:30px;width:90%;max-width:600px;position:relative;box-shadow:0 4px 20px rgba(0,0,0,0.3);margin:20px auto;}
.modal-content h3{margin-top:0;color:#870000;text-align:center;font-size:22px;}
.modal-content label{font-weight:600;margin-top:15px;display:block;color:#333;}
.modal-content input, .modal-content select{width:100%;padding:10px;margin-top:5px;border:1px solid #ccc;border-radius:8px;font-size:14px;box-sizing:border-box;}
.modal-content input:read-only{background:#f5f5f5;cursor:not-allowed;}
.close-btn{position:absolute;top:15px;right:20px;font-size:28px;color:#870000;background:none;border:none;cursor:pointer;}
.close-btn:hover{color:#b30000;}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.submit-btn{background:#870000;color:#fff;padding:10px 20px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;transition:0.3s;}
.submit-btn:hover{background:#b30000;}
.cancel-btn{background:#ccc;color:#333;padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:bold;}
.cancel-btn:hover{background:#bbb;}

/* ID card display */
.id-card{background:#fff5f5;border:2px solid #870000;border-radius:12px;padding:20px;text-align:center;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.id-card img{width:120px;height:120px;border-radius:50%;margin-bottom:15px;object-fit:cover;}
.id-card h3{margin:10px 0;color:#870000;font-size:20px;}
.id-card p{margin:8px 0;font-weight:500;text-align:left;}
.id-card p strong{color:#870000;}

/* Profile dropdown styling */
.profile-dropdown {position: relative; cursor:pointer; display:flex; align-items:center;}
.profile-btn {display: flex; align-items:center; gap:8px; font-size:18px; background:none; border:none; color:white; padding:0; cursor:pointer;}
.profile-btn .fa-user-circle {font-size:40px;}
.dropdown-content {display:none;position:absolute;right:0;top:50px;background:#fff;min-width:200px;box-shadow:0 4px 10px rgba(0,0,0,0.2);border-radius:8px;overflow:hidden; z-index:100;}
.dropdown-content form {padding:0;margin:0;}
.dropdown-content .logout-btn {width:100%;text-align:left;background:none;color:#870000;padding:15px;border:none;cursor:pointer;font-weight:bold;font-size:15px;}
.dropdown-content .logout-btn:hover {background:#870000;color:white;}

@media(max-width:760px){.card{width:100%;}.container{padding:12px;}table{font-size:14px;}.records-header{flex-direction:column;align-items:flex-start;gap:10px;}.modal-content{width:95%;max-width:500px;}}
</style>
</head>
<body>

<header>
    <div class="left-section">
        <img src="BSUU.webp" alt="BSU Logo">
        <h1>Student Gate Restriction System</h1>
    </div>

    <div style="display:flex;align-items:center; gap:20px;">
        <div class="nav-links">
            <a href="adminHome.php?page=home&campus=<?=$campus?>"><i class="fa-solid fa-house"></i> Home</a>
            <a href="adminHome.php?page=logHistory&campus=<?=$campus?>"><i class="fa-solid fa-clock-rotate-left"></i> Log History</a>
            <a href="adminHome.php?page=visitor&campus=<?=$campus?>"><i class="fa-solid fa-user-check"></i> Visitor</a>
            <a href="adminHome.php?page=report&campus=<?=$campus?>"><i class="fa-solid fa-file-pdf"></i> Report</a>
            <a href="campusManage.php?campus=<?=$campus?>" class="active"><i class="fa-solid fa-users"></i> Manage</a>
            <a href="campus.php"><i class="fa-solid fa-building"></i> Campuses</a>
        </div>

        <div class="profile-dropdown">
            <button class="profile-btn" onclick="toggleDropdown()">
                <i class="fas fa-user-circle"></i>
                <i class="fas fa-caret-down"></i>
            </button>
            <div class="dropdown-content" id="dropdownContent">
                <form method="post" action="logout.php">
                    <button type="submit" name="logout" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<!-- Campus Navigation -->
<div class="campus-nav">
    <span><i class="fas fa-map-pin"></i> Campus:</span>
    <?php foreach($campuses as $name=>$img): 
        // All images are now in uploads/ directory
        $imgPath = 'uploads/'.$img;
        // Fallback to root directory if file doesn't exist in uploads
        if (!file_exists($imgPath) && file_exists($img)) {
            $imgPath = $img;
        }
    ?>
        <a href="campusManage.php?campus=<?=$name?>" class="<?=($campus==$name)?'active':''?>" title="<?=$name?> Campus">
            <img src="<?=$imgPath?>" alt="<?=$name?>" onerror="this.src='BSUU.webp'">
            <?=$name?>
        </a>
    <?php endforeach; ?>
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
</script>

<div class="container">

    <div class="card-container">
        <div class="card" onclick="openModal('staff','add')">
            <i class="fas fa-user-plus"></i>
            <h3>Add Staff</h3>
            <p>Add faculty member to this campus</p>
        </div>
        <div class="card" onclick="openModal('guard','add')">
            <i class="fas fa-user-shield"></i>
            <h3>Add Guard</h3>
            <p>Add security guard to this campus</p>
        </div>
    </div>

    <div class="records-card">
        <div class="records-header">
            <h2><i class="fas fa-database"></i> Campus Records - <?= $campus ?></h2>
            <select id="recordType" onchange="switchTable()">
                <option value="staff">Faculty</option>
                <option value="guard">Guard</option>
            </select>
        </div>

        <table id="staffTable">
            <thead><tr><th>Name</th><th>College</th><th>Campus</th><th>Email</th><th>Actions</th></tr></thead>
            <tbody id="staffBody"></tbody>
        </table>

        <table id="guardTable" style="display:none;">
            <thead><tr><th>Name</th><th>Campus</th><th>Email</th><th>Gate</th><th>Actions</th></tr></thead>
            <tbody id="guardBody"></tbody>
        </table>
    </div>
</div>

<footer>&copy; Student Gate Restriction System</footer>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <h3 id="modalTitle">Title</h3>
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

<script>
const firebaseBase = '<?= rtrim(FIREBASE_DB_URL,"/") ?>';
const selectedCampus = '<?= $campus ?>';
let staffData = <?= json_encode($staffData) ?>;
let guardData = <?= json_encode($guardData) ?>;

function switchTable() {
    const type = document.getElementById('recordType').value;
    document.getElementById('staffTable').style.display = type==='staff'?'table':'none';
    document.getElementById('guardTable').style.display = type==='guard'?'table':'none';
}

function renderTables() {
    const staffBody = document.getElementById('staffBody'); staffBody.innerHTML = '';
    for(const key in staffData){
        const rec = staffData[key];
        const recCampus = (rec.Campus || rec.campus || '').trim().toLowerCase();
        if(recCampus !== selectedCampus.toLowerCase()) continue;
        const name = (rec.FirstName||rec.first_name)+' '+(rec.LastName||rec.last_name);
        staffBody.innerHTML += `<tr>
            <td>${name}</td>
            <td>${rec.College||rec.college||''}</td>
            <td>${rec.Campus||rec.campus||''}</td>
            <td>${rec.Email||rec.email||''}</td>
            <td class="actions">
                <i class="fas fa-eye" onclick="openModal('staff','view','${key}')" title="View"></i>
                <i class="fas fa-pen" onclick="openModal('staff','edit','${key}')" title="Edit"></i>
                <i class="fas fa-trash" onclick="deleteRecord('staff','${key}')" title="Delete"></i>
            </td>
        </tr>`;
    }
    if(staffBody.innerHTML === '') staffBody.innerHTML = '<tr><td colspan="5" class="empty"><i class="fas fa-inbox"></i> No staff records for this campus</td></tr>';

    const guardBody = document.getElementById('guardBody'); guardBody.innerHTML = '';
    for(const key in guardData){
        const rec = guardData[key];
        const recCampus = (rec.Campus || rec.campus || '').trim().toLowerCase();
        if(recCampus !== selectedCampus.toLowerCase()) continue;
        const name = (rec.FirstName||rec.first_name)+' '+(rec.LastName||rec.last_name);
        guardBody.innerHTML += `<tr>
            <td>${name}</td>
            <td>${rec.Campus||rec.campus||''}</td>
            <td>${rec.Email||rec.email||''}</td>
            <td>${rec.Gate||rec.gate||''}</td>
            <td class="actions">
                <i class="fas fa-eye" onclick="openModal('guard','view','${key}')" title="View"></i>
                <i class="fas fa-pen" onclick="openModal('guard','edit','${key}')" title="Edit"></i>
                <i class="fas fa-trash" onclick="deleteRecord('guard','${key}')" title="Delete"></i>
            </td>
        </tr>`;
    }
    if(guardBody.innerHTML === '') guardBody.innerHTML = '<tr><td colspan="5" class="empty"><i class="fas fa-inbox"></i> No guard records for this campus</td></tr>';
}

function openModal(type, mode, key='') {
    const modal = document.getElementById('modal');
    const title = document.getElementById('modalTitle');
    const fields = document.getElementById('modalFields');
    const form = document.getElementById('modalForm');
    const formFields = document.getElementById('formFields');
    
    let data = key ? (type==='staff' ? staffData[key] : guardData[key]) || {} : {};
    const defaultPic = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

    if(mode==='view'){
        title.textContent = (type==='staff'?'Staff':'Guard')+' ID Card';
        form.style.display = 'none';
        fields.innerHTML = `<div class="id-card">
            <img src="${data.Photo||data.photo||defaultPic}" alt="Profile" onerror="this.src='${defaultPic}'">
            <h3>${data.FirstName||data.first_name||''} ${data.LastName||data.last_name||''}</h3>
            ${type==='staff'?`<p><strong>College:</strong> ${data.College||data.college||''}</p>`:''}
            <p><strong>Campus:</strong> ${data.Campus||data.campus||''}</p>
            <p><strong>Email:</strong> ${data.Email||data.email||''}</p>
            <p><strong>Password:</strong> ${data.Password||data.password||''}</p>
            ${type==='guard'?`<p><strong>Gate:</strong> ${data.Gate||data.gate||''}</p>`:''}
        </div>`;
    } else {
        title.textContent = (mode==='add'?'Add':'Edit')+' '+(type==='staff'?'Staff':'Guard');
        form.style.display = 'block';
        fields.innerHTML = '';

        const first = data.FirstName||data.first_name||'';
        const last = data.LastName||data.last_name||'';
        const email = data.Email||data.email||'';
        const college = data.College||data.college||'';
        const gate = data.Gate||data.gate||'';
        const password = data.Password||data.password||'';

        if(type==='staff'){
            formFields.innerHTML = `
            <label>First Name</label><input name="FirstName" value="${first}" required>
            <label>Last Name</label><input name="LastName" value="${last}" required>
            <label>College</label><input name="College" value="${college}">
            <label>Email</label><input name="Email" value="${email}">
            <label>Password</label><input name="Password" value="${password}" required>
            <label>Campus</label><input name="Campus" value="${selectedCampus}" readonly>`;
        } else {
            formFields.innerHTML = `
            <label>First Name</label><input name="FirstName" value="${first}" required>
            <label>Last Name</label><input name="LastName" value="${last}" required>
            <label>Email</label><input name="Email" value="${email}">
            <label>Password</label><input name="Password" value="${password}" required>
            <label>Campus</label><input name="Campus" value="${selectedCampus}" readonly>
            <label>Gate</label><input name="Gate" value="${gate}">`;
        }

        form.onsubmit = async (e) => {
            e.preventDefault();
            const formData = Object.fromEntries(new FormData(form).entries());
            const firebaseKey = key || Date.now();
            try{
                const res = await fetch(`${firebaseBase}/${type==='staff'?'Staff':'Guard'}/${firebaseKey}.json`,{
                    method:'PUT',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify(formData)
                });
                if(res.ok){ alert('Saved successfully!'); location.reload(); }
                else alert('Failed to save.');
            } catch(err){ console.error(err); alert('Error connecting to Firebase'); }
        };
    }

    modal.classList.add('active');
}

function closeModal() { 
    document.getElementById('modal').classList.remove('active');
}

async function deleteRecord(type, key){
    if(!confirm('Are you sure to delete this record?')) return;
    try{
        const res = await fetch(`${firebaseBase}/${type==='staff'?'Staff':'Guard'}/${key}.json`,{method:'DELETE'});
        if(res.ok){ alert('Deleted successfully'); location.reload(); }
        else alert('Failed to delete.');
    } catch(err){ console.error(err); alert('Error connecting to Firebase'); }
}

window.addEventListener('load', ()=>{ switchTable(); renderTables(); });
</script>

</body>
</html>