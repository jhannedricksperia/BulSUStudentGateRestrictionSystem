<?php
session_start();
if(!isset($_SESSION['admin'])){ header("Location: adminLogin.php"); exit; }
require_once 'config.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Handle Add Campus
    if (isset($data['action']) && $data['action'] === 'add') {
        if (!isset($data['campusName']) || empty(trim($data['campusName']))) {
            echo json_encode(['success' => false, 'error' => 'Campus name is required']);
            exit;
        }
        
        if (!isset($data['fileData']) || empty($data['fileData'])) {
            echo json_encode(['success' => false, 'error' => 'File data is missing']);
            exit;
        }
        
        $campusName = trim($data['campusName']);
        $fileName = $data['fileName'];
        $fileData = $data['fileData'];
        $fileExt = strtolower($data['fileExt']);
        
        // Validate file extension
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($fileExt, $allowedExt)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, WEBP allowed']);
            exit;
        }
        
        // Create uploads directory
        $uploadDir = __DIR__ . '/uploads/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                echo json_encode(['success' => false, 'error' => 'Failed to create uploads directory']);
                exit;
            }
            chmod($uploadDir, 0777);
        }
        
        // Save the file
        $filePath = $uploadDir . $fileName;
        $imageData = base64_decode($fileData);
        
        if ($imageData === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to decode image data']);
            exit;
        }
        
        if (file_put_contents($filePath, $imageData) === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to save logo file']);
            exit;
        }
        
        chmod($filePath, 0644);
        
        // Update campuses.json
        $campusesFile = __DIR__ . '/campuses.json';
        $campuses = [];
        
        if (file_exists($campusesFile)) {
            $jsonContent = file_get_contents($campusesFile);
            $campuses = json_decode($jsonContent, true);
            if (!is_array($campuses)) {
                $campuses = [];
            }
        }
        
        // Check if campus exists and delete old logo
        if (isset($campuses[$campusName])) {
            $oldLogo = $uploadDir . $campuses[$campusName];
            if (file_exists($oldLogo)) {
                unlink($oldLogo);
            }
        }
        
        $campuses[$campusName] = $fileName;
        
        if (file_put_contents($campusesFile, json_encode($campuses, JSON_PRETTY_PRINT)) === false) {
            unlink($filePath);
            echo json_encode(['success' => false, 'error' => 'Failed to save campus data']);
            exit;
        }
        
        chmod($campusesFile, 0644);
        
        echo json_encode([
            'success' => true,
            'message' => 'Campus added successfully',
            'campusName' => $campusName,
            'fileName' => $fileName
        ]);
        exit;
    }
    
    // Handle Delete Campus
    if (isset($data['action']) && $data['action'] === 'delete') {
        if (!isset($data['campusName'])) {
            echo json_encode(['success' => false, 'error' => 'Missing campus name']);
            exit;
        }
        
        $campusName = trim($data['campusName']);
        
        // Load campuses
        $campusesFile = __DIR__ . '/campuses.json';
        if (!file_exists($campusesFile)) {
            echo json_encode(['success' => false, 'error' => 'Campus database not found']);
            exit;
        }
        
        $campuses = json_decode(file_get_contents($campusesFile), true);
        
        if (!isset($campuses[$campusName])) {
            echo json_encode(['success' => false, 'error' => 'Campus not found']);
            exit;
        }
        
        // Delete the logo file
        $logoFile = __DIR__ . '/uploads/' . $campuses[$campusName];
        if (file_exists($logoFile)) {
            unlink($logoFile);
        }
        
        // Remove from campuses array
        unset($campuses[$campusName]);
        
        // Save updated campuses
        if (file_put_contents($campusesFile, json_encode($campuses, JSON_PRETTY_PRINT)) === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to update campus database']);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Campus deleted successfully']);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$campus = $_GET['campus'] ?? $_SESSION['admin']['Campus'] ?? 'Main';

// Load campuses dynamically from campuses.json only
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campus Management | Gate System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{font-family:'Poppins',sans-serif;background:#fff;margin:0;color:#333;}
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

.content{margin-top:20px;padding:20px 30px;padding-bottom:80px;}
.content h2{color:#870000;font-size:26px;margin-bottom:10px;}
footer{background:#870000;color:white;text-align:center;padding:10px;position:fixed;width:100%;bottom:0;font-size:0.9rem;}

/* Profile dropdown styling */
.profile-dropdown {position: relative; cursor:pointer; display:flex; align-items:center;}
.profile-btn {display: flex; align-items:center; gap:8px; font-size:18px; background:none; border:none; color:white; padding:0; cursor:pointer;}
.profile-btn .fa-user-circle {font-size:40px;}
.dropdown-content {display:none;position:absolute;right:0;top:50px;background:#fff;min-width:200px;box-shadow:0 4px 10px rgba(0,0,0,0.2);border-radius:8px;overflow:hidden; z-index:100;}
.dropdown-content form {padding:0;margin:0;}
.dropdown-content .logout-btn {width:100%;text-align:left;background:none;color:#870000;padding:15px;border:none;cursor:pointer;font-weight:bold;font-size:15px;}
.dropdown-content .logout-btn:hover {background:#870000;color:white;}

/* Page header */
.page-header{text-align:center;margin-bottom:40px;}
.page-header h2{color:#870000;font-size:32px;margin-bottom:10px;}
.page-header p{color:#666;font-size:16px;}

/* Add campus form */
.add-campus-form{background:#fff5f0;padding:30px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.1);margin-bottom:40px;max-width:600px;margin-left:auto;margin-right:auto;}
.add-campus-form h3{color:#870000;margin-top:0;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.form-group{margin-bottom:20px;}
.form-group label{display:block;font-weight:600;margin-bottom:8px;color:#333;}
.form-group input[type="text"]{width:100%;padding:12px 15px;border:1px solid #ccc;border-radius:8px;font-size:15px;box-sizing:border-box;transition:0.3s;}
.form-group input:focus{outline:none;border-color:#870000;box-shadow:0 0 0 3px rgba(135,0,0,0.1);}
.file-input-wrapper{position:relative;overflow:hidden;display:inline-block;width:100%;}
.file-input-wrapper input[type="file"]{position:absolute;left:-9999px;}
.file-input-label{display:flex;align-items:center;gap:10px;padding:12px 15px;background:#f8f8f8;border:2px dashed #ddd;border-radius:8px;cursor:pointer;transition:0.3s;}
.file-input-label:hover{background:#870000;color:white;border-color:#870000;}
.file-input-label i{font-size:20px;}
.selected-file{margin-top:10px;padding:8px 12px;background:#e8f5e9;border-radius:6px;font-size:14px;color:#2e7d32;}
.submit-btn{background:#870000;color:white;padding:12px 30px;border:none;border-radius:8px;font-weight:600;font-size:16px;cursor:pointer;transition:0.3s;display:flex;align-items:center;gap:10px;margin:20px auto 0;justify-content:center;}
.submit-btn:hover{background:#b30000;transform:translateY(-2px);box-shadow:0 4px 15px rgba(135,0,0,0.3);}

/* Campuses grid */
.campuses-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:25px;}
.campus-card{background:white;border-radius:12px;padding:25px;box-shadow:0 4px 15px rgba(0,0,0,0.1);text-align:center;transition:0.3s;position:relative;border:2px solid #ddd;}
.campus-card:hover{transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15);border-color:#870000;}
.campus-logo{width:100px;height:100px;border-radius:50%;object-fit:cover;margin:0 auto 15px;border:4px solid #870000;box-shadow:0 4px 10px rgba(0,0,0,0.1);}
.campus-name{font-size:20px;font-weight:600;color:#870000;margin-bottom:10px;}
.campus-badge{display:inline-block;padding:4px 12px;background:#ffcc00;color:#333;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:15px;}
.campus-actions{display:flex;gap:10px;justify-content:center;}
.btn-delete{background:#dc3545;color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:500;transition:0.3s;display:flex;align-items:center;gap:6px;}
.btn-delete:hover{background:#c82333;transform:translateY(-2px);}

.alert{padding:15px 20px;border-radius:8px;margin-bottom:20px;font-weight:500;animation:slideIn 0.3s ease-out;}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
@keyframes slideIn{from{opacity:0;transform:translateY(-20px);}to{opacity:1;transform:translateY(0);}}

.empty-state{text-align:center;padding:60px 20px;color:#999;}
.empty-state i{font-size:64px;margin-bottom:20px;color:#ddd;}
.empty-state p{font-size:18px;}
</style>
</head>
<body>

<header>
<div class="left-section">
<img src="BSUU.webp" alt="BSU Logo">
<h1>Bulacan State University - Gate System</h1>
</div>

<div style="display:flex;align-items:center; gap:20px;">
<div class="nav-links">
<a href="adminHome.php?page=home&campus=<?=$campus?>"><i class="fa-solid fa-house"></i> Home</a>
<a href="adminHome.php?page=logHistory&campus=<?=$campus?>"><i class="fa-solid fa-clock-rotate-left"></i> Log History</a>
<a href="adminHome.php?page=visitor&campus=<?=$campus?>"><i class="fa-solid fa-user-check"></i> Visitor</a>
<a href="adminHome.php?page=report&campus=<?=$campus?>"><i class="fa-solid fa-file-pdf"></i> Report</a>
<a href="campusManage.php?campus=<?=$campus?>"><i class="fa-solid fa-users"></i> Manage</a>
<a href="campus.php?campus=<?=$campus?>" class="active"><i class="fa-solid fa-building"></i> Campuses</a>
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
        <a href="campus.php?campus=<?=$name?>" class="<?=($campus==$name)?'active':''?>" title="<?=$name?> Campus">
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

<div class="content">

<div class="page-header">
<h2><i class="fas fa-building"></i> Campus Management</h2>
<p>Add, view, and manage all university campuses</p>
</div>

<div id="alertContainer"></div>

<div class="add-campus-form">
<h3><i class="fas fa-plus-circle"></i> Add New Campus</h3>
<form id="campusForm">
<div class="form-group">
<label for="campusName"><i class="fas fa-tag"></i> Campus Name</label>
<input type="text" id="campusName" name="campusName" placeholder="e.g., Malolos Campus" required>
</div>
<div class="form-group">
<label><i class="fas fa-image"></i> Campus Logo</label>
<div class="file-input-wrapper">
<input type="file" id="campusLogo" name="campusLogo" accept=".jpg,.jpeg,.png,.webp" required onchange="displayFileName(this)">
<label for="campusLogo" class="file-input-label">
<i class="fas fa-cloud-upload-alt"></i>
<span id="fileLabel">Choose logo file (JPG, PNG, WEBP)</span>
</label>
</div>
<div id="selectedFile" class="selected-file" style="display:none;"></div>
</div>
<button type="submit" class="submit-btn">
<i class="fas fa-plus"></i> Add Campus
</button>
</form>
</div>

<h3 style="color:#870000;margin-bottom:20px;margin-top:40px;"><i class="fas fa-list"></i> All Campuses</h3>

<div class="campuses-grid" id="campusesGrid">
<!-- Campuses will be loaded here -->
</div>

</div>

<footer>&copy; Student Gate Restriction System</footer>

<script>
let campusData = <?= json_encode($campuses) ?>;

function displayFileName(input) {
    const fileName = input.files[0]?.name;
    const fileLabel = document.getElementById('fileLabel');
    const selectedFile = document.getElementById('selectedFile');
    
    if (fileName) {
        fileLabel.textContent = 'Logo selected';
        selectedFile.textContent = '✓ ' + fileName;
        selectedFile.style.display = 'block';
    } else {
        fileLabel.textContent = 'Choose logo file (JPG, PNG, WEBP)';
        selectedFile.style.display = 'none';
    }
}

function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

function renderCampuses() {
    const grid = document.getElementById('campusesGrid');
    grid.innerHTML = '';
    
    if (Object.keys(campusData).length === 0) {
        grid.innerHTML = `<div class="empty-state" style="grid-column: 1/-1;">
            <i class="fas fa-building"></i>
            <p>No campuses found. Add your first campus above!</p>
        </div>`;
        return;
    }
    
    for (const [name, logo] of Object.entries(campusData)) {
        const imgPath = 'uploads/' + logo;
        
        const card = document.createElement('div');
        card.className = 'campus-card';
        card.innerHTML = `
            <img src="${imgPath}" alt="${name}" class="campus-logo" onerror="this.src='BSUU.webp'">
            <div class="campus-name">${name}</div>
            <span class="campus-badge">Campus</span>
            <div class="campus-actions">
                <button onclick="deleteCampus('${name}')" class="btn-delete">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        `;
        grid.appendChild(card);
    }
}

document.getElementById('campusForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const campusName = formData.get('campusName').trim();
    const logoFile = formData.get('campusLogo');
    
    console.log('Form submitted with campus name:', campusName);
    
    if (!campusName) {
        showAlert('Please enter a campus name', 'error');
        return;
    }
    
    if (!logoFile || !logoFile.name) {
        showAlert('Please select a logo file', 'error');
        return;
    }
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    try {
        const reader = new FileReader();
        reader.onerror = function() {
            console.error('FileReader error');
            showAlert('Failed to read file', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Campus';
        };
        
        reader.onload = async function(event) {
            try {
                const base64Data = event.target.result.split(',')[1];
                const fileExt = logoFile.name.split('.').pop().toLowerCase();
                const fileName = campusName.replace(/[^a-zA-Z0-9]/g, '') + '.' + fileExt;
                
                const payload = {
                    action: 'add',
                    campusName: campusName,
                    fileName: fileName,
                    fileData: base64Data,
                    fileExt: fileExt
                };
                
                console.log('Sending payload to server...');
                
                const response = await fetch('campus.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });
                
                console.log('Response status:', response.status);
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseErr) {
                    console.error('JSON parse error:', parseErr);
                    showAlert('Server error: ' + responseText.substring(0, 100), 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Campus';
                    return;
                }
                
                console.log('Parsed result:', result);
                
                if (result.success) {
                    showAlert('Campus added successfully!', 'success');
                    campusData[campusName] = fileName;
                    renderCampuses();
                    e.target.reset();
                    document.getElementById('selectedFile').style.display = 'none';
                    document.getElementById('fileLabel').textContent = 'Choose logo file (JPG, PNG, WEBP)';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('Failed: ' + (result.error || 'Unknown error'), 'error');
                }
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Campus';
            } catch (innerErr) {
                console.error('Inner error:', innerErr);
                showAlert('Error: ' + innerErr.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Campus';
            }
        };
        
        reader.readAsDataURL(logoFile);
    } catch (err) {
        console.error('Outer error:', err);
        showAlert('Error: ' + err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Campus';
    }
});

async function deleteCampus(campusName) {
    if (!confirm(`Are you sure you want to delete ${campusName} campus?`)) {
        return;
    }
    
    try {
        const response = await fetch('campus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ 
                action: 'delete',
                campusName: campusName 
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Campus deleted successfully!', 'success');
            delete campusData[campusName];
            renderCampuses();
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showAlert('Failed: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (err) {
        console.error(err);
        showAlert('Error: ' + err.message, 'error');
    }
}

// Initial render
renderCampuses();
</script>

</body>
</html>