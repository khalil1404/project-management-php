<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

try {
    /* PROJECT STATS */
    $projectStats = $pdo->query(
        "SELECT status, COUNT(*) as count FROM projects GROUP BY status"
    )->fetchAll(PDO::FETCH_ASSOC);

    $statusCounts = ['Starting' => 0, 'Running' => 0, 'Finishing' => 0, 'Completed' => 0];
    foreach ($projectStats as $stat) {
        if (isset($statusCounts[$stat['status']])) {
            $statusCounts[$stat['status']] = $stat['count'];
        }
    }

    $activeProjects = $statusCounts['Starting'] + $statusCounts['Running'];
    $completedProjects = $statusCounts['Completed'];

    /* TASK STATS */
    if ($user['role'] === 'manager') {
        $taskStatusQuery = $pdo->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        $overdueTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE deadline < CURDATE() AND deadline != '0000-00-00' AND status != 'Done'")->fetchColumn();
        $taskPriority = $pdo->query("SELECT priority, COUNT(*) as count FROM tasks WHERE status != 'Done' GROUP BY priority")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE assigned_to = ? GROUP BY status");
        $stmt->execute([$user['id']]);
        $taskStatusQuery = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND deadline < CURDATE() AND deadline != '0000-00-00' AND status != 'Done'");
        $stmt->execute([$user['id']]);
        $overdueTasks = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT priority, COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status != 'Done' GROUP BY priority");
        $stmt->execute([$user['id']]);
        $taskPriority = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $taskStatus = ['Todo' => 0, 'In Progress' => 0, 'Done' => 0];
    foreach ($taskStatusQuery as $stat) {
        if (isset($taskStatus[$stat['status']])) {
            $taskStatus[$stat['status']] = $stat['count'];
        }
    }
    $openTasks = $taskStatus['Todo'] + $taskStatus['In Progress'];

    $priorityCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
    if (isset($taskPriority)) {
        foreach ($taskPriority as $stat) {
            if (isset($priorityCounts[$stat['priority']])) {
                $priorityCounts[$stat['priority']] = $stat['count'];
            }
        }
    }

    /* UPCOMING DEADLINES */
    if ($user['role'] === 'manager') {
        $upcomingDeadlines = $pdo->query(
            "SELECT t.id, t.title, t.deadline, t.priority, t.status, p.name as project_name, u.name as assigned_to_name
             FROM tasks t
             LEFT JOIN projects p ON t.project_id = p.id
             LEFT JOIN users u ON t.assigned_to = u.id
             WHERE t.status != 'Done' AND t.deadline != '0000-00-00' AND t.deadline >= CURDATE()
             ORDER BY t.deadline ASC LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            "SELECT t.id, t.title, t.deadline, t.priority, t.status, p.name as project_name
             FROM tasks t
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE t.assigned_to = ? AND t.status != 'Done' AND t.deadline != '0000-00-00' AND t.deadline >= CURDATE()
             ORDER BY t.deadline ASC LIMIT 10"
        );
        $stmt->execute([$user['id']]);
        $upcomingDeadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* RECENT ACTIVITY */
    if ($user['role'] === 'manager') {
        $recentActivity = $pdo->query(
            "SELECT t.title as name, t.status, u.name as user_name
             FROM tasks t
             LEFT JOIN users u ON t.assigned_to = u.id
             WHERE t.status = 'Done'
             ORDER BY t.id DESC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            "SELECT t.title as name, t.status, 'You' as user_name
             FROM tasks t
             WHERE t.assigned_to = ? AND t.status = 'Done'
             ORDER BY t.id DESC LIMIT 5"
        );
        $stmt->execute([$user['id']]);
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* TEAM PRODUCTIVITY */
    if ($user['role'] === 'manager') {
        $teamStats = $pdo->query(
            "SELECT u.name, COUNT(t.id) as total_tasks, SUM(CASE WHEN t.status = 'Done' THEN 1 ELSE 0 END) as completed_tasks
             FROM users u
             LEFT JOIN tasks t ON t.assigned_to = u.id
             WHERE u.role = 'employee'
             GROUP BY u.id, u.name
             ORDER BY completed_tasks DESC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data. Error: " . $e->getMessage();
}

function getUrgencyClass($deadline) {
    if (empty($deadline) || $deadline == '0000-00-00') return '';
    $days = floor((strtotime($deadline) - time()) / 86400);
    if ($days < 0) return 'overdue';
    if ($days <= 2) return 'urgent';
    if ($days <= 7) return 'soon';
    return 'normal';
}

function getDaysUntil($deadline) {
    if (empty($deadline) || $deadline == '0000-00-00') return 'No deadline';
    $days = floor((strtotime($deadline) - time()) / 86400);
    if ($days < 0) return abs($days) . ' days overdue';
    if ($days == 0) return 'Today';
    if ($days == 1) return 'Tomorrow';
    return $days . ' days';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    --purple: #a855f7;
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
}
button:hover { transform: translateY(-1px); }

.container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }

.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--red);
    color: var(--red);
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 24px;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--cyan));
}

.stat:hover { transform: translateY(-6px); border-color: var(--primary); }
.stat span { color: var(--muted); font-size: 14px; }
.stat strong { display: block; font-size: 32px; margin-top: 10px; font-weight: 700; }

.two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px; }

.charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.chart-box {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    min-height: 400px;
}

.chart-box h3 { margin: 0 0 20px 0; font-size: 18px; font-weight: 600; }
.chart-wrapper { height: 320px; position: relative; }
canvas { width: 100% !important; height: 100% !important; }

.deadlines-widget, .activity-feed {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
}

.deadlines-widget h3, .activity-feed h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.deadline-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    margin-bottom: 12px;
    border-left: 4px solid var(--muted);
    transition: 0.2s;
}

.deadline-item:hover { background: rgba(255,255,255,0.06); transform: translateX(5px); }
.deadline-item.overdue { border-left-color: var(--red); background: rgba(239, 68, 68, 0.1); }
.deadline-item.urgent { border-left-color: var(--orange); background: rgba(245, 158, 11, 0.1); }
.deadline-item.soon { border-left-color: var(--cyan); }

.deadline-date { min-width: 70px; text-align: center; }
.deadline-day { font-size: 24px; font-weight: 700; display: block; line-height: 1; }
.deadline-month { font-size: 12px; color: var(--muted); text-transform: uppercase; }

.deadline-info { flex: 1; }
.deadline-title { font-weight: 600; margin-bottom: 4px; }
.deadline-meta { font-size: 13px; color: var(--muted); display: flex; gap: 12px; }

.deadline-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.deadline-badge.high { background: rgba(239, 68, 68, 0.3); color: var(--red); }
.deadline-badge.medium { background: rgba(245, 158, 11, 0.3); color: var(--orange); }
.deadline-badge.low { background: rgba(34, 197, 94, 0.3); color: var(--green); }

.deadline-countdown { text-align: right; min-width: 100px; }
.countdown-number { font-size: 18px; font-weight: 700; }
.countdown-label { font-size: 11px; color: var(--muted); }

.empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
.empty-state-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }

.activity-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    margin-bottom: 12px;
    transition: 0.2s;
}

.activity-item:hover { background: rgba(255,255,255,0.06); }

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: var(--green);
    flex-shrink: 0;
}

.activity-content { flex: 1; }
.activity-title { font-weight: 600; margin-bottom: 4px; }
.activity-meta { font-size: 13px; color: var(--muted); }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 40px;
}

.quick-action {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: 0.3s;
}

.quick-action:hover {
    transform: translateY(-4px);
    border-color: var(--primary);
    background: rgba(59, 130, 246, 0.1);
}

.quick-action-icon { font-size: 32px; margin-bottom: 8px; }
.quick-action-label { font-size: 14px; font-weight: 600; }

.leaderboard {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 40px;
}

.leaderboard h3 { margin: 0 0 20px 0; font-size: 18px; font-weight: 600; }

.leaderboard-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    margin-bottom: 10px;
    transition: 0.2s;
}

.leaderboard-item:hover { background: rgba(255,255,255,0.06); transform: translateX(5px); }
.leaderboard-left { display: flex; align-items: center; gap: 12px; }
.rank { font-size: 20px; font-weight: 700; color: var(--muted); min-width: 30px; }
.rank.gold { color: #fbbf24; }
.rank.silver { color: #d1d5db; }
.rank.bronze { color: #fb923c; }

.leaderboard-right { text-align: right; }
.task-count { font-size: 20px; font-weight: 700; color: var(--green); }
.task-label { font-size: 12px; color: var(--muted); }

footer { text-align: center; padding: 30px; color: var(--muted); font-size: 14px; }

@media (max-width: 968px) {
    .two-column { grid-template-columns: 1fr; }
    .charts { grid-template-columns: 1fr; }
    nav { flex-direction: column; gap: 15px; }
}
</style>
</head>

<body>

<nav>
<div class="nav-left">
    <strong>🚀 Dashboard</strong>
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
        <span><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
        <button onclick="toggleTheme()" aria-label="Toggle theme">🌗</button>
        <a href="logout.php"><button>Logout</button></a>
    </div>
</nav>

<main class="container">

    <?php if (isset($error)): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat">
            <span>Active Projects</span>
            <strong><?= $activeProjects ?? 0 ?></strong>
        </div>
        <div class="stat">
            <span>Completed Projects</span>
            <strong style="color:var(--green)"><?= $completedProjects ?? 0 ?></strong>
        </div>
        <div class="stat">
            <span>Open Tasks</span>
            <strong style="color:var(--cyan)"><?= $openTasks ?? 0 ?></strong>
        </div>
        <div class="stat">
            <span>Overdue Tasks</span>
            <strong style="color:var(--red)"><?= $overdueTasks ?? 0 ?></strong>
        </div>
    </div>

    <?php if ($user['role'] === 'manager'): ?>
    <div class="quick-actions">
        <a href="projects.php" class="quick-action">
            <div class="quick-action-icon">📁</div>
            <div class="quick-action-label">New Project</div>
        </a>
        <a href="tasks.php" class="quick-action">
            <div class="quick-action-icon">✅</div>
            <div class="quick-action-label">New Task</div>
        </a>
        <a href="employees.php" class="quick-action">
            <div class="quick-action-icon">👥</div>
            <div class="quick-action-label">Manage Team</div>
        </a>
        <a href="notifications.php" class="quick-action">
            <div class="quick-action-icon">🔔</div>
            <div class="quick-action-label">Notifications</div>
        </a>
    </div>
    <?php endif; ?>

    <div class="two-column">
        <div class="deadlines-widget">
            <h3>📅 Upcoming Deadlines</h3>
            
            <?php if (empty($upcomingDeadlines)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🎉</div>
                    <p>No upcoming deadlines!</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcomingDeadlines as $deadline): 
                    $urgencyClass = getUrgencyClass($deadline['deadline']);
                    $daysUntil = getDaysUntil($deadline['deadline']);
                ?>
                <div class="deadline-item <?= $urgencyClass ?>">
                    <div class="deadline-date">
                        <span class="deadline-day"><?= date('d', strtotime($deadline['deadline'])) ?></span>
                        <span class="deadline-month"><?= date('M', strtotime($deadline['deadline'])) ?></span>
                    </div>
                    
                    <div class="deadline-info">
                        <div class="deadline-title"><?= htmlspecialchars($deadline['title']) ?></div>
                        <div class="deadline-meta">
                            <span>📁 <?= htmlspecialchars($deadline['project_name'] ?? 'No Project') ?></span>
                            <?php if (isset($deadline['assigned_to_name'])): ?>
                                <span>👤 <?= htmlspecialchars($deadline['assigned_to_name']) ?></span>
                            <?php endif; ?>
                            <span class="deadline-badge <?= strtolower($deadline['priority']) ?>">
                                <?= htmlspecialchars($deadline['priority']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="deadline-countdown">
                        <div class="countdown-number"><?= $daysUntil ?></div>
                        <div class="countdown-label">until due</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="activity-feed">
            <h3>⚡ Recent Activity</h3>
            
            <?php if (empty($recentActivity)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <p>No recent activity</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">✓</div>
                    <div class="activity-content">
                        <div class="activity-title"><?= htmlspecialchars($activity['name']) ?></div>
                        <div class="activity-meta">
                            <?= htmlspecialchars($activity['user_name']) ?> completed this task
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="charts">
        <div class="chart-box">
            <h3>📊 Project Status Breakdown</h3>
            <div class="chart-wrapper">
                <canvas id="projectChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <h3>📝 Task Overview</h3>
            <div class="chart-wrapper">
                <canvas id="taskChart"></canvas>
            </div>
        </div>
    </div>

    <?php if ($user['role'] === 'manager' && !empty($teamStats)): ?>
    <div class="leaderboard">
        <h3>🏆 Top Performers This Period</h3>
        <?php foreach ($teamStats as $index => $member): ?>
            <div class="leaderboard-item">
                <div class="leaderboard-left">
                    <span class="rank <?= $index === 0 ? 'gold' : ($index === 1 ? 'silver' : ($index === 2 ? 'bronze' : '')) ?>">
                        #<?= $index + 1 ?>
                    </span>
                    <strong><?= htmlspecialchars($member['name']) ?></strong>
                </div>
                <div class="leaderboard-right">
                    <div class="task-count"><?= $member['completed_tasks'] ?></div>
                    <div class="task-label">tasks completed</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<footer>
    © <?= date('Y') ?> • Khalil Nafeti
</footer>

<script>
function toggleTheme() {
    document.body.classList.toggle('light');
    localStorage.setItem('theme', document.body.classList.contains('light') ? 'light' : 'dark');
}

if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
}

new Chart(document.getElementById('projectChart'), {
    type: 'doughnut',
    data: {
        labels: ['Starting', 'Running', 'Finishing', 'Completed'],
        datasets: [{
            data: [<?= $statusCounts['Starting'] ?>, <?= $statusCounts['Running'] ?>, <?= $statusCounts['Finishing'] ?>, <?= $statusCounts['Completed'] ?>],
            backgroundColor: ['#f59e0b', '#06b6d4', '#a855f7', '#22c55e'],
            borderWidth: 0,
            hoverOffset: 15
        }]
    },
    options: {
        cutout: '0%',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: getComputedStyle(document.documentElement).getPropertyValue('--text'),
                    padding: 15,
                    font: { size: 13 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

new Chart(document.getElementById('taskChart'), {
    type: 'bar',
    data: {
        labels: ['Todo', 'In Progress', 'Done', 'High Priority', 'Medium Priority', 'Low Priority'],
        datasets: [{
            data: [<?= $taskStatus['Todo'] ?>, <?= $taskStatus['In Progress'] ?>, <?= $taskStatus['Done'] ?>, <?= $priorityCounts['High'] ?>, <?= $priorityCounts['Medium'] ?>, <?= $priorityCounts['Low'] ?>],
            backgroundColor: ['#3b82f6', '#06b6d4', '#22c55e', '#ef4444', '#f59e0b', '#a855f7'],
            borderRadius: 12,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Tasks: ' + context.parsed.y;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    color: getComputedStyle(document.documentElement).getPropertyValue('--muted')
                },
                grid: { color: 'rgba(255,255,255,0.05)' }
            },
            x: {
                ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--muted') },
                grid: { display: false }
            }
        }
    }
});
</script>

</body>
</html>