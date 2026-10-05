<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

// Get current month/year from URL or use current
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month/year
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

try {
    // Fetch tasks for the current month
    $firstDay = "$year-$month-01";
    $lastDay = date('Y-m-t', strtotime($firstDay));
    
    if ($user['role'] === 'manager') {
        $stmt = $pdo->prepare(
            "SELECT t.*, u.name as assigned_to_name, p.name as project_name
             FROM tasks t
             LEFT JOIN users u ON t.assigned_to = u.id
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE t.deadline >= ? AND t.deadline <= ?
             ORDER BY t.deadline ASC"
        );
        $stmt->execute([$firstDay, $lastDay]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            "SELECT t.*, p.name as project_name
             FROM tasks t
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE t.assigned_to = ? AND t.deadline >= ? AND t.deadline <= ?
             ORDER BY t.deadline ASC"
        );
        $stmt->execute([$user['id'], $firstDay, $lastDay]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Group tasks by date
    $tasksByDate = [];
    foreach ($tasks as $task) {
        if ($task['deadline'] && $task['deadline'] != '0000-00-00') {
            $date = $task['deadline'];
            if (!isset($tasksByDate[$date])) {
                $tasksByDate[$date] = [];
            }
            $tasksByDate[$date][] = $task;
        }
    }

} catch (PDOException $e) {
    error_log("Calendar Error: " . $e->getMessage());
    $error = "Unable to load calendar.";
}

// Calendar calculations
$firstDayOfMonth = date('N', strtotime($firstDay)); // 1 (Monday) to 7 (Sunday)
$daysInMonth = date('t', strtotime($firstDay));
$monthName = date('F', strtotime($firstDay));

// Previous/Next month links
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calendar</title>

<style>
:root {
    --bg: #05070f;
    --panel: rgba(255,255,255,0.06);
    --border: rgba(255,255,255,0.08);
    --text: #e5e7eb;
    --muted: #9ca3af;
    --primary: #3b82f6;
    --cyan: #06b6d4;
    --green: #22c55e;
    --orange: #f59e0b;
    --red: #ef4444;
}

body.light {
    --bg: #f4f6fb;
    --panel: white;
    --border: #e5e7eb;
    --text: #111827;
    --muted: #6b7280;
}

* { box-sizing: border-box; font-family: system-ui, Segoe UI, sans-serif; }
body { margin: 0; background: radial-gradient(circle at top, #0f172a, var(--bg)); color: var(--text); min-height: 100vh; }

nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 40px;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
}

.nav-left { display: flex; gap: 20px; align-items: center; }
.nav-left a { color: var(--muted); text-decoration: none; font-weight: 500; transition: color 0.2s; }
.nav-left a:hover { color: var(--text); }
.nav-right { display: flex; gap: 14px; align-items: center; }

button {
    background: var(--panel);
    color: var(--text);
    border: 1px solid var(--border);
    padding: 8px 14px;
    border-radius: 10px;
    cursor: pointer;
    transition: transform 0.2s;
    font-size: 14px;
}

button:hover { transform: translateY(-1px); }
button.primary { background: var(--primary); border-color: var(--primary); }

.container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 25px 30px;
}

.calendar-title { margin: 0; font-size: 28px; }

.calendar-nav { display: flex; gap: 10px; }

.calendar-nav a {
    padding: 10px 20px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    text-decoration: none;
    transition: 0.2s;
}

.calendar-nav a:hover { border-color: var(--primary); background: rgba(59, 130, 246, 0.1); }

.calendar-grid {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 20px;
    overflow: hidden;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}

.weekday {
    text-align: center;
    font-weight: 600;
    font-size: 14px;
    color: var(--muted);
    padding: 10px;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
}

.calendar-day {
    min-height: 120px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px;
    position: relative;
    transition: 0.2s;
}

.calendar-day:hover {
    background: rgba(255,255,255,0.06);
    border-color: var(--primary);
}

.calendar-day.empty {
    background: transparent;
    border: 1px dashed rgba(255,255,255,0.05);
}

.calendar-day.today {
    background: rgba(59, 130, 246, 0.1);
    border-color: var(--primary);
}

.day-number {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 8px;
}

.calendar-day.today .day-number {
    color: var(--primary);
}

.day-tasks {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.day-task {
    background: var(--panel);
    border-left: 3px solid var(--primary);
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    transition: 0.2s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.day-task:hover {
    background: rgba(255,255,255,0.1);
    transform: translateX(2px);
}

.day-task.todo { border-left-color: var(--muted); }
.day-task.inprogress { border-left-color: var(--orange); }
.day-task.done { border-left-color: var(--green); }

.day-task.high { background: rgba(239, 68, 68, 0.1); }
.day-task.medium { background: rgba(245, 158, 11, 0.1); }
.day-task.low { background: rgba(34, 197, 94, 0.1); }

.task-count {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active { display: flex; }

.modal-content {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    max-width: 700px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 20px;
}

.modal-header h3 { margin: 0; font-size: 20px; }

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--muted);
    padding: 0;
}

.task-detail {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.task-detail:last-child { border-bottom: none; }

.task-detail label {
    display: block;
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 5px;
}

.task-detail .value { font-size: 16px; }

.badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
.badge.todo { background: rgba(107, 114, 128, 0.3); color: var(--muted); }
.badge.inprogress { background: rgba(245, 158, 11, 0.3); color: var(--orange); }
.badge.done { background: rgba(34, 197, 94, 0.3); color: var(--green); }
.badge.high { background: rgba(239, 68, 68, 0.3); color: var(--red); }
.badge.medium { background: rgba(245, 158, 11, 0.3); color: var(--orange); }
.badge.low { background: rgba(34, 197, 94, 0.3); color: var(--green); }

.legend {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}

footer { text-align: center; padding: 30px; color: var(--muted); font-size: 14px; }

@media (max-width: 768px) {
    .calendar-header { flex-direction: column; gap: 15px; }
    .calendar-day { min-height: 80px; }
    .day-task { font-size: 10px; }
}
</style>
</head>

<body>

<nav>
    <div class="nav-left">
        <strong>📅 Calendar</strong>
        <a href="dashboard.php">Home</a>
        <a href="calendar.php">Calendar</a>
        <a href="projects.php">Projects</a>
        <a href="tasks.php">Tasks</a>
        <a href="kanban.php">Kanban</a>
        <?php if ($user['role'] === 'manager'): ?>
            <a href="employees.php">Employees</a>
        <?php endif; ?>
        <a href="notifications.php">Notifications</a>
    </div>

    <div class="nav-right">
        <span><?= htmlspecialchars($user['name']) ?></span>
        <button onclick="toggleTheme()">🌗</button>
        <a href="logout.php"><button>Logout</button></a>
    </div>
</nav>

<main class="container">

    <div class="calendar-header">
        <h1 class="calendar-title"><?= $monthName ?> <?= $year ?></h1>
        <div class="calendar-nav">
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">← Previous</a>
            <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>">Today</a>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next →</a>
        </div>
    </div>

    <div class="legend">
        <div class="legend-item">
            <div class="legend-color" style="background: var(--muted)"></div>
            <span>To Do</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: var(--orange)"></div>
            <span>In Progress</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: var(--green)"></div>
            <span>Done</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: var(--primary)"></div>
            <span>Today</span>
        </div>
    </div>

    <div class="calendar-grid">
        <div class="calendar-weekdays">
            <div class="weekday">Mon</div>
            <div class="weekday">Tue</div>
            <div class="weekday">Wed</div>
            <div class="weekday">Thu</div>
            <div class="weekday">Fri</div>
            <div class="weekday">Sat</div>
            <div class="weekday">Sun</div>
        </div>

        <div class="calendar-days">
            <?php
            // Add empty cells for days before the first day of the month
            for ($i = 1; $i < $firstDayOfMonth; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }

            // Add days of the month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isToday = $currentDate === date('Y-m-d');
                $dayTasks = $tasksByDate[$currentDate] ?? [];
                
                $todayClass = $isToday ? 'today' : '';
                ?>
                <div class="calendar-day <?= $todayClass ?>">
                    <div class="day-number"><?= $day ?></div>
                    <?php if (!empty($dayTasks)): ?>
                        <span class="task-count"><?= count($dayTasks) ?></span>
                        <div class="day-tasks">
                            <?php foreach (array_slice($dayTasks, 0, 3) as $task): 
                                $statusClass = strtolower(str_replace(' ', '', $task['status']));
                                $priorityClass = strtolower($task['priority']);
                            ?>
                                <div class="day-task <?= $statusClass ?> <?= $priorityClass ?>" 
                                     onclick='openTask(<?= json_encode($task) ?>)'>
                                    <?= htmlspecialchars($task['title']) ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($dayTasks) > 3): ?>
                                <div style="font-size:11px;color:var(--muted);text-align:center;margin-top:5px;">
                                    +<?= count($dayTasks) - 3 ?> more
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }

            // Add empty cells to complete the grid
            $totalCells = $firstDayOfMonth + $daysInMonth - 1;
            $remainingCells = 7 - ($totalCells % 7);
            if ($remainingCells < 7) {
                for ($i = 0; $i < $remainingCells; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
            }
            ?>
        </div>
    </div>

</main>

<footer>
    © <?= date('Y') ?> • Khalil Nafeti
</footer>

<!-- Task Detail Modal -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Task Details</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<script>
function toggleTheme() {
    document.body.classList.toggle('light');
    localStorage.setItem('theme', document.body.classList.contains('light') ? 'light' : 'dark');
}

if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
}

function openTask(task) {
    document.getElementById('modalTitle').textContent = task.title;
    
    const statusClass = task.status.toLowerCase().replace(' ', '');
    const priorityClass = task.priority.toLowerCase();
    
    let html = `
        <div class="task-detail">
            <label>Description</label>
            <div class="value">${task.description || 'No description'}</div>
        </div>
        <div class="task-detail">
            <label>Project</label>
            <div class="value">${task.project_name || 'No project'}</div>
        </div>
        ${task.assigned_to_name ? `
        <div class="task-detail">
            <label>Assigned To</label>
            <div class="value">${task.assigned_to_name}</div>
        </div>` : ''}
        <div class="task-detail">
            <label>Priority</label>
            <div class="value"><span class="badge ${priorityClass}">${task.priority}</span></div>
        </div>
        <div class="task-detail">
            <label>Status</label>
            <div class="value"><span class="badge ${statusClass}">${task.status}</span></div>
        </div>
        <div class="task-detail">
            <label>Deadline</label>
            <div class="value">${task.deadline ? new Date(task.deadline).toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'}) : 'No deadline'}</div>
        </div>
    `;
    
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('taskModal').classList.add('active');
}

function closeModal() {
    document.getElementById('taskModal').classList.remove('active');
}

document.getElementById('taskModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

</body>
</html>