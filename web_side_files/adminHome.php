<?php
session_start();
if(!isset($_SESSION['admin'])){ header("Location: adminLogin.php"); exit; }
$page = $_GET['page']??'home';
$campus = $_GET['campus']??$_SESSION['admin']['Campus']??'Main';
require_once 'config.php'; // Assuming config.php defines FIREBASE_DB_URL

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
<title>Admin | Gate System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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

/* Statistics Grid */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}
.stat-card {
    background: linear-gradient(135deg, #870000 0%, #b30000 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(135, 0, 0, 0.2);
    text-align: center;
    transition: 0.3s;
}
.stat-card.updated {
    animation: pulse 0.5s ease-in-out;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
.stat-card .number {
    font-size: 36px;
    font-weight: 700;
    margin: 10px 0;
}
.stat-card .label {
    font-size: 14px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.stat-card .subtext {
    font-size: 12px;
    margin-top: 10px;
    opacity: 0.8;
}

.charts-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin: 30px 0;
}
.chart-box {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.chart-box h3 {
    margin-top: 0;
    color: #870000;
    font-size: 18px;
}

.campus-container{display:grid;grid-template-columns:repeat(3,1fr);gap:30px;max-width:900px;margin:40px auto;}
.campus-card{background:#ffcc00;color:#000;padding:25px 20px;width:230px;text-align:center;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.15);display:flex;flex-direction:column;align-items:center;text-decoration:none;}
.campus-card:hover{background:#870000;color:#fff;transform:translateY(-5px);}
.campus-card img{width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:12px;}
.campus-name{font-size:18px;font-weight:600;}
table{width:100%;border-collapse:collapse;font-size:15px;background:white;box-shadow:0 4px 10px rgba(0,0,0,0.1);border-radius:10px;overflow:hidden;}
th,td{padding:14px 12px;text-align:center;border-bottom:1px solid #ddd;}
th{background:#870000;color:#fff;font-weight:600;}
tr:hover{background-color:#f5f5f5;}
.new-entry {
    animation: slideIn 0.5s ease-in-out;
}
@keyframes slideIn {
    from { background-color: #fff3cd; }
    to { background-color: transparent; }
}

/* Visitor form styling */
#visitorForm {
    max-width: 800px;
    margin: 20px auto;
    background: #fff5f0;
    padding: 25px 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    text-align: left;
}
#visitorForm .full-width { grid-column: 1 / -1; }
#visitorForm label { display:block; font-weight:600; margin-bottom:5px; }
#visitorForm input, #visitorForm select, #visitorForm textarea {
    width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:8px; font-size:15px; box-sizing:border-box;
}
#visitorForm textarea { resize: vertical; }
#visitorForm button { grid-column: 1 / -1; background:#870000; color:white; padding:12px 20px; border:none; border-radius:8px; font-weight:600; font-size:16px; cursor:pointer; transition:0.3s; margin-top:10px; }
#visitorForm button:hover { background:#b30000; }

/* Profile dropdown styling */
.profile-dropdown {position: relative; cursor:pointer; display:flex; align-items:center;}
.profile-btn {display: flex; align-items:center; gap:8px; font-size:18px; background:none; border:none; color:white; padding:0; cursor:pointer;}
.profile-btn .fa-user-circle {font-size:40px;}
.dropdown-content {display:none;position:absolute;right:0;top:50px;background:#fff;min-width:200px;box-shadow:0 4px 10px rgba(0,0,0,0.2);border-radius:8px;overflow:hidden; z-index:100;}
.dropdown-content form {padding:0;margin:0;}
.dropdown-content .logout-btn {width:100%;text-align:left;background:none;color:#870000;padding:15px;border:none;cursor:pointer;font-weight:bold;font-size:15px;}
.dropdown-content .logout-btn:hover {background:#870000;color:white;}

select[name="campusSelect"] {
    padding: 10px 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 20px;
}

/* Report Styling */
.report-controls {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}
.report-controls select, .report-controls input {
    padding: 10px 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 15px;
}
.report-controls button {
    background: #870000;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.report-controls button:hover {
    background: #b30000;
}
.report-controls button.secondary {
    background: #555;
}
.report-controls button.secondary:hover {
    background: #777;
}

#reportContainer {
    background: white;
    padding: 40px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 12px;
}

.report-section {
    margin: 30px 0;
}
.report-section h3 {
    color: #870000;
    border-bottom: 2px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-size: 20px;
}
.report-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.report-stat-box {
    background: #f8f8f8;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #870000;
}
.report-stat-box .label {
    font-size: 14px;
    color: #666;
    margin-bottom: 8px;
}
.report-stat-box .value {
    font-size: 32px;
    font-weight: 700;
    color: #870000;
}
.report-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}
.report-table th, .report-table td {
    padding: 12px;
    text-align: left;
    border: 1px solid #ddd;
}
.report-table th {
    background: #870000;
    color: white;
}
.report-table tr:nth-child(even) {
    background: #f8f8f8;
}
.report-footer {
    margin-top: 50px;
    text-align: center;
    color: #666;
    font-size: 14px;
    border-top: 2px solid #ddd;
    padding-top: 20px;
}

@media print {
    header, .campus-nav, footer, .report-controls {
        display: none !important;
    }
    #reportContainer {
        box-shadow: none;
        padding: 20px;
    }
    body {
        background: white;
    }
    .content {
        padding: 0;
        margin: 0;
    }
}
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
<a href="?page=home&campus=<?=$campus?>" class="<?=($page=='home')?'active':''?>"><i class="fa-solid fa-house"></i> Home</a>
<a href="?page=logHistory&campus=<?=$campus?>" class="<?=($page=='logHistory')?'active':''?>"><i class="fa-solid fa-clock-rotate-left"></i> Log History</a>
<a href="?page=visitor&campus=<?=$campus?>" class="<?=($page=='visitor')?'active':''?>"><i class="fa-solid fa-user-check"></i> Visitor</a>
<a href="?page=report&campus=<?=$campus?>" class="<?=($page=='report')?'active':''?>"><i class="fa-solid fa-file-pdf"></i> Report</a>
<a href="campusManage.php?campus=<?=$campus?>"><i class="fa-solid fa-users"></i> Manage</a>
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
        <a href="?page=<?=$page?>&campus=<?=$name?>" class="<?=($campus==$name)?'active':''?>" title="<?=$name?> Campus">
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

<?php if($page=='home'): ?>
<h2><?= $campus ?> Campus - Student Movement Dashboard</h2>
<p>Real-time statistics and insights</p>

<div class="stats-container" id="statsContainer">
    <div class="stat-card">
        <div class="label">Today's Entries</div>
        <div class="number" id="totalEntries">0</div>
        <div class="subtext" id="subToday">All gates</div>
    </div>
    <div class="stat-card">
        <div class="label">This Month</div>
        <div class="number" id="monthEntries">0</div>
        <div class="subtext" id="subMonth">Current month</div>
    </div>
    <div class="stat-card">
        <div class="label">Visitors</div>
        <div class="number" id="visitorCount">0</div>
        <div class="subtext">Today</div>
    </div>
</div>

<div class="charts-container">
    <div class="chart-box">
        <h3>Hourly Entry Distribution (Today)</h3>
        <canvas id="hourlyChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>Daily Entries (This Week)</h3>
        <canvas id="weekChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>Gate-wise Distribution (Today)</h3>
        <canvas id="gateChart"></canvas>
    </div>
</div>

<script>
const firebaseBase = '<?= rtrim(FIREBASE_DB_URL,"/") ?>';
const selectedCampus = '<?= $campus ?>';
let hourlyChart, weekChart, gateChart;
let lastLogTimestamp = null;

function getDateRange(){
    const today = new Date();
    today.setHours(0,0,0,0);
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 6);
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    
    return { today, weekAgo, monthStart };
}

function formatDate(date){
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function animateStatCard(cardId) {
    const card = document.querySelector(`[id="${cardId}"]`).parentElement;
    card.classList.add('updated');
    setTimeout(() => card.classList.remove('updated'), 500);
}

async function loadStatistics(){
    try{
        const res = await fetch(`${firebaseBase}/Logs.json?t=${Date.now()}`);
        const data = await res.json();
        if(!data) {
            console.warn('No logs data found in Firebase');
            resetStats();
            return;
        }

        const { today, weekAgo, monthStart } = getDateRange();
        const todayStr = formatDate(today);
        const weekAgoStr = formatDate(weekAgo);
        const monthStartStr = formatDate(monthStart);

        // Convert Firebase object to array and filter by campus
        let allLogs = [];
        if (typeof data === 'object' && data !== null) {
            allLogs = Object.entries(data)
                .map(([key, log]) => ({ ...log, id: key }))
                .filter(log => {
                    if (!log || !log.campus) return false;
                    // Case-insensitive comparison and trim whitespace
                    return log.campus.trim().toLowerCase() === selectedCampus.trim().toLowerCase();
                });
        }

        console.log('Selected Campus:', selectedCampus);
        console.log('Total logs for campus:', allLogs.length);
        
        // Today's logs
        const todayLogs = allLogs.filter(log => {
            if (!log.dateTime) return false;
            // Extract just the date part (YYYY-MM-DD)
            const logDate = log.dateTime.trim().split(' ')[0];
            return logDate === todayStr;
        });

        // This week's logs
        const weekLogs = allLogs.filter(log => {
            if (!log.dateTime) return false;
            const logDate = log.dateTime.split(' ')[0];
            return logDate >= weekAgoStr && logDate <= todayStr;
        });

        // This month's logs
        const monthLogs = allLogs.filter(log => {
            if (!log.dateTime) return false;
            const logDate = log.dateTime.split(' ')[0];
            return logDate >= monthStartStr && logDate <= todayStr;
        });

        // Check if new logs were added
        const latestLog = allLogs.length > 0 ? allLogs[allLogs.length - 1].dateTime : null;
        if (latestLog && latestLog !== lastLogTimestamp) {
            lastLogTimestamp = latestLog;
        }

        // Calculate statistics
        const totalToday = todayLogs.length;
        const totalMonth = monthLogs.length;
        
        
        
        // Count visitors
        const visitorsToday = todayLogs.filter(l => l.type === 'visitor').length;

        // Update stat cards with animation
        const totalEntriesEl = document.getElementById('totalEntries');
        const monthEntriesEl = document.getElementById('monthEntries');
        const visitorCountEl = document.getElementById('visitorCount');

        if (totalEntriesEl.textContent !== totalToday.toString()) {
            totalEntriesEl.textContent = totalToday;
            animateStatCard('totalEntries');
        }
        
       
        
        if (monthEntriesEl.textContent !== totalMonth.toString()) {
            monthEntriesEl.textContent = totalMonth;
            animateStatCard('monthEntries');
        }
        
        if (visitorCountEl.textContent !== visitorsToday.toString()) {
            visitorCountEl.textContent = visitorsToday;
            animateStatCard('visitorCount');
        }

        // Update subtexts
        const avgPerHour = totalToday > 0 ? Math.round(totalToday / 24) : 0;
        document.getElementById('subToday').textContent = `Avg: ${avgPerHour} per hour`;
        document.getElementById('subMonth').textContent = `Since ${monthStartStr}`;

        // === HOURLY CHART (Today) ===
        const hourlyData = {};
        for(let i = 0; i < 24; i++) hourlyData[i] = 0;

        todayLogs.forEach(log => {
            const timeStr = log.dateTime.split(' ')[1];
            if(timeStr) {
                const hour = parseInt(timeStr.split(':')[0]);
                if(!isNaN(hour) && hour >= 0 && hour < 24) hourlyData[hour]++;
            }
        });

        const ctx1 = document.getElementById('hourlyChart').getContext('2d');
        if(hourlyChart) {
            // Update existing chart without animation
            hourlyChart.data.datasets[0].data = Object.values(hourlyData);
            hourlyChart.update('none');
        } else {
            // Create chart with animation only on first load
            hourlyChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: Array.from({length: 24}, (_, i) => (i < 10 ? '0' : '') + i + ':00'),
                    datasets: [{
                        label: 'Entries per Hour',
                        data: Object.values(hourlyData),
                        borderColor: '#870000',
                        backgroundColor: 'rgba(135, 0, 0, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#870000'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: true } },
                    scales: { 
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // === WEEK CHART (Daily entries this week) ===
        const weekData = {};
        const dayLabels = [];
        for(let i = 6; i >= 0; i--){
            const d = new Date(today);
            d.setDate(d.getDate() - i);
            const dateStr = formatDate(d);
            const dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
            weekData[dateStr] = 0;
            dayLabels.push(dayName + ' (' + d.getDate() + ')');
        }

        weekLogs.forEach(log => {
            const logDate = log.dateTime.split(' ')[0];
            if(weekData.hasOwnProperty(logDate)) weekData[logDate]++;
        });

        const ctx2 = document.getElementById('weekChart').getContext('2d');
        if(weekChart) {
            // Update existing chart without animation
            weekChart.data.datasets[0].data = Object.values(weekData);
            weekChart.update('none');
        } else {
            // Create chart with animation only on first load
            weekChart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: dayLabels,
                    datasets: [{
                        label: 'Daily Entries',
                        data: Object.values(weekData),
                        backgroundColor: '#870000',
                        borderColor: '#b30000',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: true } },
                    scales: { 
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // === GATE CHART (Today) ===
        const gateData = {};
        todayLogs.forEach(log => {
            const gate = log.gate || 'Unknown';
            gateData[gate] = (gateData[gate] || 0) + 1;
        });

        const ctx3 = document.getElementById('gateChart').getContext('2d');
        if(gateChart) {
            // Update existing chart without animation
            gateChart.data.labels = Object.keys(gateData);
            gateChart.data.datasets[0].data = Object.values(gateData);
            gateChart.update('none');
        } else {
            // Create chart with animation only on first load
            gateChart = new Chart(ctx3, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(gateData),
                    datasets: [{
                        data: Object.values(gateData),
                        backgroundColor: ['#870000', '#b30000', '#ffcc00', '#ff9900', '#ff6600', '#ff3300', '#cc0000']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    }catch(err){
        console.error('Error loading statistics:', err);
        resetStats();
    }
}

function resetStats() {
    document.getElementById('totalEntries').textContent = '0';
    document.getElementById('monthEntries').textContent = '0';
    document.getElementById('visitorCount').textContent = '0';
}

loadStatistics();
setInterval(loadStatistics, 5000);
</script>

<?php elseif($page=='logHistory'): ?>
<h2>Log History - <?= $campus ?> Campus</h2>

<table>
<thead>
<tr>
<th>Student Number</th>
<th>Full Name</th>
<th>Type</th>
<th>Gate Entered</th>
<th>Date & Time</th>
<th>Status</th>
<th>Violation</th>
</tr>
</thead>
<tbody id="logTableBody"></tbody>
</table>

<script>
const firebaseBase2 = '<?= rtrim(FIREBASE_DB_URL,"/") ?>';
const selectedCampus2 = '<?= $campus ?>';
const tbody = document.getElementById('logTableBody');
let lastLogCount = 0;

async function loadLogs(){
    try{
        const res = await fetch(`${firebaseBase2}/Logs.json?t=${Date.now()}`);
        const data = await res.json();
        if(!data) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">No logs found</td></tr>';
            return;
        }

        const logsArray = Object.entries(data)
            .map(([key, log]) => ({ ...log, id: key }))
            .filter(log => log && log.campus && log.campus.trim().toLowerCase() === selectedCampus2.trim().toLowerCase())
            .sort((a, b) => {
                const dateA = new Date(a.dateTime || '');
                const dateB = new Date(b.dateTime || '');
                return dateB - dateA;
            });

        const isFirstLoad = lastLogCount === 0;
        const hasNewLogs = logsArray.length > lastLogCount;

        if (logsArray.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">No logs for this campus</td></tr>';
            lastLogCount = 0;
            return;
        }

        if (logsArray.length > lastLogCount) {
             // Only clear and rebuild if new logs arrived or it's the first load
            tbody.innerHTML = '';
        }

        logsArray.forEach((log, index) => {
            
            const tr = document.createElement('tr');
            tr.setAttribute('data-log-id', log.id);
            
            // Check if this is a newly arrived log (only applies to the very latest entry)
            if (index === 0 && logsArray.length > lastLogCount) {
                tr.classList.add('new-entry');
                // Remove the class after animation
                setTimeout(() => tr.classList.remove('new-entry'), 500);
            }
            
            const violation = log.violation ?? 'None';
            const type = log.type === 'visitor' ? 'Visitor' : 'Student';
            const statusBadge = `<span style="background:${log.status === 'Entry' ? '#c8e6c9' : '#ffe082'};padding:4px 8px;border-radius:4px;color:#333;">${log.status}</span>`;
            
            tr.innerHTML = `<td>${log.studentNumber ?? ''}</td>
                            <td>${log.fullName ?? ''}</td>
                            <td>${type}</td>
                            <td>${log.gate ?? ''}</td>
                            <td>${log.dateTime ?? ''}</td>
                            <td>${statusBadge}</td>
                            <td>${violation}</td>`;
            tbody.appendChild(tr);
        });

        lastLogCount = logsArray.length;
    }catch(err){
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red;">Error loading logs</td></tr>';
    }
}

// Initial load and subsequent interval reload
loadLogs();
setInterval(loadLogs, 5000);
</script>

<?php elseif($page=='visitor'): ?>
<h2>Add Visitor - <?= $campus ?> Campus</h2>
<form id="visitorForm">
<label for="type" class="full-width"><strong>Visitor Type:</strong></label>
<select id="type" class="full-width">
<option value="">Select Type</option>
<option value="StudentVisitor">Student Visitor</option>
<option value="NonMemberVisitor">Non-Member Visitor</option>
<option value="VIPVisitor">VIP Visitor</option>
</select>

<div id="dynamicFields" class="full-width"></div>
<button type="submit">Add Visitor</button>
</form>

<script>
const typeSelect = document.getElementById('type');
const dynamicFields = document.getElementById('dynamicFields');
const selectedCampus3 = '<?= $campus ?>';

typeSelect.addEventListener('change', () => {
    const type = typeSelect.value;
    dynamicFields.innerHTML = '';
    if(!type) return;

    if(type === 'StudentVisitor'){
        dynamicFields.innerHTML = `<div><label>Student Number:</label><input id="studentNumber" required></div>
<div><label>First Name:</label><input id="firstName" required></div>
<div><label>Last Name:</label><input id="lastName" required></div>
<div><label>College:</label><input id="college"></div>
<div><label>Section:</label><input id="section"></div>
<div><label>Office:</label><input id="office"></div>
<div class="full-width"><label>Purpose:</label><textarea id="purpose"></textarea></div>
<div><label>Email:</label><input id="email" type="email"></div>`;
    } else if(type === 'NonMemberVisitor'){
        dynamicFields.innerHTML = `<div><label>First Name:</label><input id="firstName" required></div>
<div><label>Last Name:</label><input id="lastName" required></div>
<div><label>Address:</label><input id="address"></div>
<div><label>Contact:</label><input id="contact"></div>
<div><label>Office:</label><input id="office"></div>
<div class="full-width"><label>Purpose:</label><textarea id="purpose"></textarea></div>`;
    } else if(type === 'VIPVisitor'){
        dynamicFields.innerHTML = `<div><label>First Name:</label><input id="firstName" required></div>
<div><label>Last Name:</label><input id="lastName"></div>
<div><label>Representative First Name:</label><input id="repFirstName"></div>
<div><label>Representative Last Name:</label><input id="repLastName"></div>
<div><label>Contact:</label><input id="contact"></div>`;
    }
});

document.getElementById('visitorForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const type = typeSelect.value;
    if (!type) { alert('Select a visitor type'); return; }

    const now = new Date();
    const refNo = type === 'StudentVisitor'
        ? `SV-${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}${now.getHours().toString().padStart(2,'0')}${now.getMinutes().toString().padStart(2,'0')}${now.getSeconds().toString().padStart(2,'0')}`
        : type === 'NonMemberVisitor'
        ? `VP-${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}${now.getHours().toString().padStart(2,'0')}${now.getMinutes().toString().padStart(2,'0')}${now.getSeconds().toString().padStart(2,'0')}`
        : `VIP-${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}${now.getHours().toString().padStart(2,'0')}${now.getMinutes().toString().padStart(2,'0')}${now.getSeconds().toString().padStart(2,'0')}`;

    let visitorData = { type, referenceNo: refNo, dateTime: now.toISOString().slice(0,16).replace('T',' '), campus: selectedCampus3 };

    const getValue = (id) => dynamicFields.querySelector(`#${id}`)?.value ?? '';

    if(type === 'StudentVisitor'){
        visitorData.studentNumber = getValue('studentNumber');
        visitorData.firstName = getValue('firstName');
        visitorData.lastName = getValue('lastName');
        visitorData.college = getValue('college');
        visitorData.section = getValue('section');
        visitorData.office = getValue('office');
        visitorData.purpose = getValue('purpose');
        visitorData.email = getValue('email');
    } else if(type === 'NonMemberVisitor'){
        visitorData.firstName = getValue('firstName');
        visitorData.lastName = getValue('lastName');
        visitorData.address = getValue('address');
        visitorData.contact = getValue('contact');
        visitorData.office = getValue('office');
        visitorData.purpose = getValue('purpose');
    } else if(type === 'VIPVisitor'){
        visitorData.firstName = getValue('firstName');
        visitorData.lastName = getValue('lastName');
        visitorData.repFirstName = getValue('repFirstName');
        visitorData.repLastName = getValue('repLastName');
        visitorData.contact = getValue('contact');
    }

    try{
        const res = await fetch(`<?= rtrim(FIREBASE_DB_URL,'/') ?>/Visitor/${refNo}.json`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(visitorData)
        });
        if(res.ok){
            alert('Visitor added successfully! Reference No: ' + refNo);
            document.getElementById('visitorForm').reset();
            dynamicFields.innerHTML = '';
        } else alert('Failed to add visitor');
    } catch(err){ console.error(err); alert('Error connecting to Firebase'); }
});
</script>

<?php elseif($page=='report'): ?>
<h2>Monthly Gate System Report - <?= $campus ?> Campus</h2>

<div class="report-controls">
    <label><strong>Select Month:</strong></label>
    <select id="reportMonth">
        <?php
        for($m = 1; $m <= 12; $m++) {
            $monthName = date('F', mktime(0, 0, 0, $m, 1));
            $selected = ($m == date('n')) ? 'selected' : '';
            echo "<option value='$m' $selected>$monthName</option>";
        }
        ?>
    </select>
    
    <label><strong>Select Year:</strong></label>
    <select id="reportYear">
        <?php
        $currentYear = date('Y');
        for($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $selected = ($y == $currentYear) ? 'selected' : '';
            echo "<option value='$y' $selected>$y</option>";
        }
        ?>
    </select>
    
    <button onclick="generateReport()"><i class="fas fa-sync"></i> Generate Report</button>
    <button onclick="window.print()" class="secondary"><i class="fas fa-print"></i> Print</button>
    <button onclick="downloadPDF()" class="secondary"><i class="fas fa-download"></i> Download PDF</button>
</div>

<div id="reportContainer">

    <div class="report-section">
        <h3><i class="fas fa-door-open"></i> Gate-wise Distribution</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Gate Name</th>
                    <th>Total Entries</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody id="rptGateTable">
                <tr><td colspan="3" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <h3><i class="fas fa-calendar-alt"></i> Daily Breakdown</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Entries</th>
                    <th>Students</th>
                    <th>Visitors</th>
                </tr>
            </thead>
            <tbody id="rptDailyTable">
                <tr><td colspan="5" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <h3><i class="fas fa-exclamation-triangle"></i> Violations Summary</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Violation Type</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody id="rptViolationTable">
                <tr><td colspan="3" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <h3><i class="fas fa-clock"></i> Peak Hours Analysis</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Time Range</th>
                    <th>Entries</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody id="rptPeakTable">
                <tr><td colspan="3" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const firebaseBase4 = '<?= rtrim(FIREBASE_DB_URL,"/") ?>';
const selectedCampus4 = '<?= $campus ?>';

async function generateReport() {
    const month = parseInt(document.getElementById('reportMonth').value);
    const year = parseInt(document.getElementById('reportYear').value);
    
    const monthName = new Date(year, month - 1).toLocaleString('en-US', { month: 'long' });
    
    try {
        const res = await fetch(`${firebaseBase4}/Logs.json`);
        const data = await res.json();
        
        if (!data) {
            alert('No data found');
            return;
        }
        
        // Filter logs for selected month, year, and campus
        const logs = Object.entries(data)
            .map(([key, log]) => ({ ...log, id: key }))
            .filter(log => {
                if (!log || !log.campus || !log.dateTime) return false;
                if (log.campus.trim().toLowerCase() !== selectedCampus4.trim().toLowerCase()) return false;
                
                const logDate = new Date(log.dateTime);
                return logDate.getMonth() === month - 1 && logDate.getFullYear() === year;
            });
        
        // Calculate statistics
        const totalEntries = logs.length;
        
        const daysInMonth = new Date(year, month, 0).getDate();
        
        // Gate-wise distribution
        const gateData = {};
        logs.forEach(log => {
            const gate = log.gate || 'Unknown';
            gateData[gate] = (gateData[gate] || 0) + 1;
        });
        
        let gateTableHTML = '';
        Object.entries(gateData).sort((a, b) => b[1] - a[1]).forEach(([gate, count]) => {
            const percentage = ((count / totalEntries) * 100).toFixed(1);
            gateTableHTML += `<tr>
                <td>${gate}</td>
                <td>${count}</td>
                <td>${percentage}%</td>
            </tr>`;
        });
        document.getElementById('rptGateTable').innerHTML = gateTableHTML || '<tr><td colspan="3" style="text-align:center;">No data</td></tr>';
        
        // Daily breakdown
        const dailyData = {};
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dailyData[dateStr] = { entries: 0, students: new Set(), visitors: 0 };
        }
        
        logs.forEach(log => {
            const dateStr = log.dateTime.split(' ')[0];
            if (dailyData[dateStr]) {
                dailyData[dateStr].entries++;
                if (log.type === 'student' && log.studentNumber) {
                    dailyData[dateStr].students.add(log.studentNumber.trim());
                } else if (log.type === 'visitor') {
                    dailyData[dateStr].visitors++;
                }
            }
        });
        
        let dailyTableHTML = '';
        Object.entries(dailyData).forEach(([date, data]) => {
            const dayName = new Date(date).toLocaleDateString('en-US', { weekday: 'short' });
            const formattedDate = new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            dailyTableHTML += `<tr>
                <td>${formattedDate}</td>
                <td>${dayName}</td>
                <td>${data.entries}</td>
                <td>${data.students.size}</td>
                <td>${data.visitors}</td>
            </tr>`;
        });
        document.getElementById('rptDailyTable').innerHTML = dailyTableHTML;
        
        // Violations summary
        const violationData = {};
        logs.forEach(log => {
            const violation = log.violation || 'None';
            if (violation !== 'None') {
                violationData[violation] = (violationData[violation] || 0) + 1;
            }
        });
        
        const totalViolations = Object.values(violationData).reduce((sum, count) => sum + count, 0);
        let violationTableHTML = '';
        Object.entries(violationData).sort((a, b) => b[1] - a[1]).forEach(([violation, count]) => {
            const percentage = ((count / totalViolations) * 100).toFixed(1);
            violationTableHTML += `<tr>
                <td>${violation}</td>
                <td>${count}</td>
                <td>${percentage}%</td>
            </tr>`;
        });
        document.getElementById('rptViolationTable').innerHTML = violationTableHTML || '<tr><td colspan="3" style="text-align:center;">No violations recorded</td></tr>';
        
        // Peak hours analysis
        const hourRanges = {
            'Early Morning (6AM-9AM)': { start: 6, end: 9, count: 0 },
            'Mid Morning (9AM-12PM)': { start: 9, end: 12, count: 0 },
            'Afternoon (12PM-3PM)': { start: 12, end: 15, count: 0 },
            'Late Afternoon (3PM-6PM)': { start: 15, end: 18, count: 0 },
            'Evening (6PM-9PM)': { start: 18, end: 21, count: 0 }
        };
        
        logs.forEach(log => {
            const timeStr = log.dateTime.split(' ')[1];
            if (timeStr) {
                const hour = parseInt(timeStr.split(':')[0]);
                Object.values(hourRanges).forEach(range => {
                    if (hour >= range.start && hour < range.end) {
                        range.count++;
                    }
                });
            }
        });
        
        let peakTableHTML = '';
        Object.entries(hourRanges).sort((a, b) => b[1].count - a[1].count).forEach(([range, data]) => {
            const percentage = totalEntries > 0 ? ((data.count / totalEntries) * 100).toFixed(1) : 0;
            peakTableHTML += `<tr>
                <td>${range}</td>
                <td>${data.count}</td>
                <td>${percentage}%</td>
            </tr>`;
        });
        document.getElementById('rptPeakTable').innerHTML = peakTableHTML;
        
    } catch (err) {
        console.error('Error generating report:', err);
        alert('Error generating report');
    }
}

function downloadPDF() {
    const element = document.getElementById('reportContainer');
    const month = document.getElementById('reportMonth').selectedOptions[0].text;
    const year = document.getElementById('reportYear').value;
    const filename = `Gate_Report_${selectedCampus4}_${month}_${year}.pdf`;
    
    const opt = {
        margin: 10,
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save();
}

// Generate report on page load
generateReport();
</script>

<?php endif; ?>

</div>

<footer>&copy; <?= date('Y') ?> Student Gate Restriction System</footer>
</body>
</html>