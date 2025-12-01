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

// Get campus from URL parameter if provided
$campus = htmlspecialchars($_GET['campus'] ?? $campus);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Records | Bulacan State University</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{font-family:'Poppins',sans-serif;background:#fff;margin:0;color:#333;min-height:100vh;display:flex;flex-direction:column;}
header{background:#870000;color:#fff;padding:15px 30px;display:flex;align-items:center;justify-content:center;position:relative;}
header img{width:70px;height:70px;border-radius:50%;margin-right:15px;}
header h1{font-size:24px;margin:0;}
.back-btn{position:absolute;left:20px;background:none;border:none;color:#fff;font-size:24px;cursor:pointer;}
.back-btn:hover{color:#ffcc00;}
.container{max-width:1100px;margin:40px auto;padding:20px;flex:1;}
.card-container{display:flex;justify-content:center;flex-wrap:wrap;gap:25px;margin-bottom:30px;}
.card{background:#fff5f5;border:2px solid #870000;border-radius:12px;width:260px;padding:25px;text-align:center;box-shadow:0 3px 8px rgba(0,0,0,0.1);cursor:pointer;transition:.25s;}
.card:hover{transform:translateY(-5px);background:#ffecec;}
.card i{font-size:40px;color:#870000;margin-bottom:12px;}
h2{color:#870000;margin:0;}
.search-section{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:20px;}
.search-section input{padding:12px 15px;width:300px;border:1px solid #ccc;border-radius:8px;font-size:14px;}
.search-section button{background:#870000;color:#fff;padding:12px 25px;border:none;border-radius:8px;cursor:pointer;font-weight:bold;transition:0.3s;}
.search-section button:hover{background:#650000;}
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
.actions i:hover{transform:scale(1.3);}

/* Modal styling */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:200;}
.modal-content{background:#fff;border-radius:15px;padding:30px;width:90%;max-width:600px;position:relative;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
.modal-content h3{margin-top:0;color:#870000;text-align:center;}
.modal-content label{font-weight:600;margin-top:10px;display:block;}
.modal-content input, .modal-content select{width:100%;padding:10px;margin-top:5px;border:1px solid #ccc;border-radius:8px;font-size:14px;box-sizing:border-box;}
.modal-content input:read-only{background:#f5f5f5;cursor:not-allowed;}
.close-btn{position:absolute;top:15px;right:20px;font-size:28px;color:#870000;background:none;border:none;cursor:pointer;}
.close-btn:hover{color:#b30000;}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.submit-btn{background:#870000;color:#fff;padding:10px 20px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;}
.submit-btn:hover{background:#650000;}
.cancel-btn{background:#ccc;color:#333;padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:bold;}
.cancel-btn:hover{background:#bbb;}

/* ID card display */
.id-card{background:#fff5f5;border:2px solid #870000;border-radius:12px;padding:20px;text-align:center;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.id-card img{width:120px;height:120px;border-radius:50%;margin-bottom:15px;object-fit:cover;}
.id-card h3{margin:10px 0;color:#870000;font-size:20px;}
.id-card p{margin:8px 0;font-weight:500;text-align:left;}
.id-card p strong{color:#870000;}

@media(max-width:768px){
    .card{width:100%;}
    .container{padding:12px;}
    table{font-size:14px;}
    th, td{padding:8px;}
    .search-section input{width:100%;max-width:300px;}
    .modal-content{width:95%;max-width:500px;}
}
</style>
</head>
<body>

<header>
    <button class="back-btn" onclick="window.location.href='staffHome.php?campus=<?php echo urlencode($campus); ?>'"><i class="fas fa-arrow-left"></i></button>
    <img src="BSUU.webp" alt="BSU Logo">
    <h1><?= $campus ?> Campus Management</h1>
</header>

<div class="container">

    <!-- Removed Add Student card -->

    <div class="records-card">
        <div class="records-header">
            <h2><i class="fas fa-database"></i> Student Records</h2>
        </div>

        <div class="search-section">
            <input type="text" id="searchInput" placeholder="Search by student number, name, or course..." />
            <button onclick="searchStudents()"><i class="fas fa-search"></i> Search</button>
        </div>

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

<footer>&copy; <?php echo date("Y"); ?> Bulacan State University | Gate Management System</footer>

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
let studentData = <?= json_encode($studentData) ?>;

function renderTable(data) {
    const tbody = document.getElementById('studentBody');
    tbody.innerHTML = '';
    
    if (Object.keys(data).length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty"><i class="fas fa-inbox"></i><p>No students found</p></td></tr>`;
        return;
    }

    for(const key in data){
        const rec = data[key];
        if((rec.campus || rec.Campus) !== '<?= $campus ?>') continue;
        tbody.innerHTML += `<tr>
            <td>${rec.studentNumber || 'N/A'}</td>
            <td>${rec.first_name || 'N/A'}</td>
            <td>${rec.last_name || 'N/A'}</td>
            <td>${rec.course || 'N/A'}</td>
            <td>${rec.section || 'N/A'}</td>
            <td>${rec.year || 'N/A'}</td>
            <td class="actions">
                <i class="fas fa-eye view" title="View" onclick="openModal('view','${key}')"></i>
                <i class="fas fa-edit edit" title="Edit" onclick="openModal('edit','${key}')"></i>
                <i class="fas fa-trash delete" title="Delete" onclick="deleteRecord('${key}')"></i>
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

function openModal(mode, key='') {
    const modal = document.getElementById('modal');
    const title = document.getElementById('modalTitle');
    const fields = document.getElementById('modalFields');
    const form = document.getElementById('modalForm');
    const formFields = document.getElementById('formFields');
    
    let data = key ? studentData[key] || {} : {};
    const defaultPic = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

    if(mode==='view'){
        title.textContent = 'View Student';
        form.style.display = 'none';
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
        title.textContent = (mode==='add'?'Add':'Edit')+' Student';
        form.style.display = 'block';
        fields.innerHTML = '';

        const fname = data.first_name||'';
        const lname = data.last_name||'';
        const snum = data.studentNumber||'';
        const course = data.course||'';
        const section = data.section||'';
        const year = data.year||'';
        const semester = data.semester||'';

        formFields.innerHTML = `
            <label>Student Number</label><input name="studentNumber" value="${snum}" readonly>
            <label>First Name</label><input name="first_name" value="${fname}" required>
            <label>Last Name</label><input name="last_name" value="${lname}" required>
            <label>Course</label><input name="course" value="${course}">
            <label>Section</label><input name="section" value="${section}">
            <label>Year</label><input name="year" value="${year}">
            <label>Semester</label><input name="semester" value="${semester}">
            <label>Campus</label><input name="campus" value="<?= $campus ?>" readonly>
        `;

        form.onsubmit = async (e) => {
            e.preventDefault();
            const formData = Object.fromEntries(new FormData(form).entries());
            const firebaseKey = key || Date.now();
            try{
                const res = await fetch(`${firebaseBase}/Student/${firebaseKey}.json`,{
                    method:'PUT',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify(formData)
                });
                if(res.ok){ alert('Saved successfully!'); location.reload(); }
                else alert('Failed to save.' );
            } catch(err){ console.error(err); alert('Error connecting to Firebase'); }
        };
    }

    modal.style.display='flex';
}

function closeModal() { 
    document.getElementById('modal').style.display='none'; 
}

async function deleteRecord(key){
    if(!confirm('Are you sure to delete this student?')) return;
    try{
        const res = await fetch(`${firebaseBase}/Student/${key}.json`,{method:'DELETE'});
        if(res.ok){ alert('Deleted successfully'); location.reload(); }
        else alert('Failed to delete.');
    } catch(err){ console.error(err); alert('Error connecting to Firebase'); }
}

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchStudents();
    }
});

window.addEventListener('load', () => { renderTable(studentData); });
</script>

</body>
</html>
