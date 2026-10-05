<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

try {
    $employees = $pdo->query("SELECT id, name FROM users WHERE role='employee'")->fetchAll(PDO::FETCH_ASSOC);
    $projects = $pdo->query("SELECT id, name FROM projects")->fetchAll(PDO::FETCH_ASSOC);

    /* ADD TASK */
    if (isset($_POST['add_task']) && $user['role'] === 'manager') {
        $stmt = $pdo->prepare(
            "INSERT INTO tasks (title, description, project_id, assigned_to, status, priority, deadline, created_by)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $_POST['title'], $_POST['description'],
            $_POST['project_id'], $_POST['assigned_to'],
            'Todo', $_POST['priority'], $_POST['deadline'],
            $user['id']
        ]);
        $success = "Task created successfully!";
    }

    /* UPDATE STATUS */
    if (isset($_POST['update_status'])) {
        $stmt = $pdo->prepare(
            "UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?"
        );
        $stmt->execute([
            $_POST['status'], $_POST['task_id'], $user['id']
        ]);
        $success = "Task status updated!";
    }

    /* DELETE TASK */
    if (isset($_POST['delete_task']) && $user['role'] === 'manager') {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$_POST['task_id']]);
        $success = "Task deleted successfully!";
    }

    /* SEARCH & FILTER */
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
    $filterPriority = isset($_GET['priority']) ? $_GET['priority'] : '';
    $filterProject = isset($_GET['project']) ? $_GET['project'] : '';

    /* FETCH TASKS WITH FILTERS */
    if ($user['role'] === 'manager') {
        $whereConditions = ["1=1"];
        $params = [];
        
        if (!empty($searchQuery)) {
            $whereConditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
        }
        if (!empty($filterStatus)) {
            $whereConditions[] = "t.status = ?";
            $params[] = $filterStatus;
        }
        if (!empty($filterPriority)) {
            $whereConditions[] = "t.priority = ?";
            $params[] = $filterPriority;
        }
        if (!empty($filterProject)) {
            $whereConditions[] = "t.project_id = ?";
            $params[] = $filterProject;
        }
        
        $whereClause = implode(" AND ", $whereConditions);
        
        $stmt = $pdo->prepare(
            "SELECT t.*, u.name as emp, p.name as project
             FROM tasks t
             LEFT JOIN users u ON t.assigned_to = u.id
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE $whereClause
             ORDER BY 
                CASE t.status 
                    WHEN 'Todo' THEN 1 
                    WHEN 'In Progress' THEN 2 
                    WHEN 'Done' THEN 3 
                END,
                CASE t.priority 
                    WHEN 'High' THEN 1 
                    WHEN 'Medium' THEN 2 
                    WHEN 'Low' THEN 3 
                END,
                t.deadline ASC"
        );
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $whereConditions = ["assigned_to = ?"];
        $params = [$user['id']];
        
        if (!empty($searchQuery)) {
            $whereConditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
        }
        if (!empty($filterStatus)) {
            $whereConditions[] = "t.status = ?";
            $params[] = $filterStatus;
        }
        if (!empty($filterPriority)) {
            $whereConditions[] = "t.priority = ?";
            $params[] = $filterPriority;
        }
        if (!empty($filterProject)) {
            $whereConditions[] = "t.project_id = ?";
            $params[] = $filterProject;
        }
        
        $whereClause = implode(" AND ", $whereConditions);
        
        $stmt = $pdo->prepare(
            "SELECT t.*, p.name as project 
             FROM tasks t
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE $whereClause
             ORDER BY 
                CASE t.status 
                    WHEN 'Todo' THEN 1 
                    WHEN 'In Progress' THEN 2 
                    WHEN 'Done' THEN 3 
                END,
                CASE t.priority 
                    WHEN 'High' THEN 1 
                    WHEN 'Medium' THEN 2 
                    WHEN 'Low' THEN 3 
                END,
                t.deadline ASC"
        );
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /* Get all tasks for stats (without filters) */
    if ($user['role'] === 'manager') {
        $allTasks = $pdo->query("SELECT * FROM tasks")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to = ?");
        $stmt->execute([$user['id']]);
        $allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /* Calculate stats */
    $todoCount = 0;
    $inProgressCount = 0;
    $doneCount = 0;
    $highPriorityCount = 0;
    $overdueCount = 0;
    
    foreach ($allTasks as $task) {
        if ($task['status'] == 'Todo') $todoCount++;
        if ($task['status'] == 'In Progress') $inProgressCount++;
        if ($task['status'] == 'Done') $doneCount++;
        if ($task['priority'] == 'High' && $task['status'] != 'Done') $highPriorityCount++;
        if ($task['deadline'] < date('Y-m-d') && $task['deadline'] != '0000-00-00' && $task['status'] != 'Done') $overdueCount++;
    }
    
    $stats = [
        'total' => count($allTasks),
        'todo' => $todoCount,
        'inprogress' => $inProgressCount,
        'done' => $doneCount,
        'high_priority' => $highPriorityCount,
        'overdue' => $overdueCount
    ];

} catch (PDOException $e) {
    error_log("Tasks Error: " . $e->getMessage());
    $error = "Unable to load tasks. Please try again.";
}

function isOverdue($deadline) {
    if (empty($deadline) || $deadline == '0000-00-00') return false;
    return strtotime($deadline) < time();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tasks</title>

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
    font-size: 14px;
}

button:hover { transform: translateY(-1px); }
button.primary { background: var(--primary); border-color: var(--primary); }
button.small { padding: 6px 10px; font-size: 12px; }
button.danger { background: var(--red); border-color: var(--red); color: white; }

.container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }

.success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid var(--green);
    color: var(--green);
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

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
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 20px;
    text-align: center;
    transition: 0.3s;
}

.stat:hover { transform: translateY(-4px); border-color: var(--primary); }
.stat-number { font-size: 32px; font-weight: 700; display: block; margin-bottom: 8px; }
.stat-label { color: var(--muted); font-size: 13px; }

.card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
}

.card h2 { margin: 0 0 20px 0; font-size: 20px; font-weight: 600; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group.full { grid-column: 1 / -1; }

label { color: var(--text); font-weight: 500; font-size: 14px; }

input, textarea, select {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 14px;
    transition: 0.2s;
}

input:focus, textarea:focus, select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255,255,255,0.08);
}

textarea { resize: vertical; min-height: 100px; }

.tasks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.task-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.task-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.task-card.todo::before { background: var(--muted); }
.task-card.inprogress::before { background: var(--orange); }
.task-card.done::before { background: var(--green); }
.task-card.overdue::before { background: var(--red); }

.task-card:hover { transform: translateY(-4px); border-color: var(--primary); }

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}

.task-title { font-size: 16px; font-weight: 600; margin: 0; flex: 1; }
.task-badges { display: flex; gap: 6px; flex-wrap: wrap; }

.task-description {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 16px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.task-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
    font-size: 13px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--muted);
}

.meta-item strong { color: var(--text); }

.task-actions {
    display: flex;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.task-actions select { flex: 1; padding: 8px 12px; }
.task-actions button { padding: 8px 16px; }

.badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
.badge.todo { background: rgba(107, 114, 128, 0.3); color: var(--muted); }
.badge.inprogress { background: rgba(245, 158, 11, 0.3); color: var(--orange); }
.badge.done { background: rgba(34, 197, 94, 0.3); color: var(--green); }
.badge.high { background: rgba(239, 68, 68, 0.3); color: var(--red); }
.badge.medium { background: rgba(245, 158, 11, 0.3); color: var(--orange); }
.badge.low { background: rgba(34, 197, 94, 0.3); color: var(--green); }
.badge.overdue { background: var(--red); color: white; }

.empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
.empty-state-icon { font-size: 64px; margin-bottom: 20px; opacity: 0.5; }

footer { text-align: center; padding: 30px; color: var(--muted); font-size: 14px; }

@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .tasks-grid { grid-template-columns: 1fr; }
    nav { flex-direction: column; gap: 15px; }
}
</style>
</head>

<body>

<nav>
<div class="nav-left">
    <strong>📋 Tasks</strong>
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

    <?php if (isset($success)): ?>
        <div class="success" role="alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="stats">
        <div class="stat">
            <span class="stat-number" style="color:var(--primary)"><?= $stats['total'] ?></span>
            <span class="stat-label">Total Tasks</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--muted)"><?= $stats['todo'] ?></span>
            <span class="stat-label">To Do</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--orange)"><?= $stats['inprogress'] ?></span>
            <span class="stat-label">In Progress</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--green)"><?= $stats['done'] ?></span>
            <span class="stat-label">Done</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--red)"><?= $stats['high_priority'] ?></span>
            <span class="stat-label">High Priority</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--red)"><?= $stats['overdue'] ?></span>
            <span class="stat-label">Overdue</span>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="card">
        <form method="get" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
            <div class="form-group" style="flex: 1; min-width: 250px;">
                <label for="search">🔍 Search Tasks</label>
                <input type="text" id="search" name="search" placeholder="Search by title or description..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            
            <div class="form-group" style="min-width: 150px;">
                <label for="filter_status">Status</label>
                <select id="filter_status" name="status">
                    <option value="">All Statuses</option>
                    <option value="Todo" <?= $filterStatus == 'Todo' ? 'selected' : '' ?>>Todo</option>
                    <option value="In Progress" <?= $filterStatus == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Done" <?= $filterStatus == 'Done' ? 'selected' : '' ?>>Done</option>
                </select>
            </div>
            
            <div class="form-group" style="min-width: 150px;">
                <label for="filter_priority">Priority</label>
                <select id="filter_priority" name="priority">
                    <option value="">All Priorities</option>
                    <option value="High" <?= $filterPriority == 'High' ? 'selected' : '' ?>>High</option>
                    <option value="Medium" <?= $filterPriority == 'Medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="Low" <?= $filterPriority == 'Low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>
            
            <div class="form-group" style="min-width: 150px;">
                <label for="filter_project">Project</label>
                <select id="filter_project" name="project">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= $filterProject == $proj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proj['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="primary" style="margin-bottom: 0;">Apply Filters</button>
            <a href="tasks.php"><button type="button" style="margin-bottom: 0;">Clear</button></a>
        </form>
    </div>

    <!-- CREATE TASK FORM (Manager Only) -->
    <?php if ($user['role'] === 'manager'): ?>
    <div class="card">
        <h2>Create New Task</h2>
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="title">Task Title</label>
                    <input type="text" id="title" name="title" placeholder="Enter task title" required>
                </div>

                <div class="form-group">
                    <label for="project_id">Project</label>
                    <select id="project_id" name="project_id" required>
                        <option value="">Select project...</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_to">Assign To</label>
                    <select id="assigned_to" name="assigned_to" required>
                        <option value="">Select employee...</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        <option value="High">High</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="deadline">Deadline</label>
                    <input type="date" id="deadline" name="deadline" required>
                </div>

                <div class="form-group full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Task details and requirements"></textarea>
                </div>
            </div>

            <button type="submit" name="add_task" class="primary">Create Task</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- TASKS GRID -->
    <div class="card">
        <h2><?= $user['role'] === 'manager' ? 'All Tasks' : 'My Tasks' ?> (<?= count($tasks) ?>)</h2>
        
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>No tasks found</h3>
                <p><?= !empty($searchQuery) || !empty($filterStatus) || !empty($filterPriority) || !empty($filterProject) ? 'Try adjusting your filters' : ($user['role'] === 'manager' ? 'Create your first task to get started!' : 'No tasks assigned to you yet.') ?></p>
            </div>
        <?php else: ?>
            <div class="tasks-grid">
                <?php foreach ($tasks as $t): 
                    $statusClass = strtolower(str_replace(' ', '', $t['status']));
                    $priorityClass = strtolower($t['priority']);
                    $overdue = isOverdue($t['deadline']) && $t['status'] != 'Done';
                ?>
                <div class="task-card <?= $statusClass ?> <?= $overdue ? 'overdue' : '' ?>">
                    <div class="task-header">
                        <h3 class="task-title"><?= htmlspecialchars($t['title']) ?></h3>
                        <div class="task-badges">
                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($t['status']) ?></span>
                            <span class="badge <?= $priorityClass ?>"><?= htmlspecialchars($t['priority']) ?></span>
                            <?php if ($overdue): ?>
                                <span class="badge overdue">⚠ Overdue</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($t['description'])): ?>
                        <p class="task-description"><?= htmlspecialchars($t['description']) ?></p>
                    <?php endif; ?>

                    <div class="task-meta">
                        <div class="meta-item">
                            📁 <strong><?= htmlspecialchars($t['project'] ?? 'No Project') ?></strong>
                        </div>
                        <?php if ($user['role'] === 'manager'): ?>
                            <div class="meta-item">
                                👤 <strong><?= htmlspecialchars($t['emp'] ?? 'Unassigned') ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            📅 <strong><?= $t['deadline'] && $t['deadline'] != '0000-00-00' ? date('M j, Y', strtotime($t['deadline'])) : 'No deadline' ?></strong>
                        </div>
                        <div class="meta-item">
                            🎯 Priority: <strong><?= htmlspecialchars($t['priority']) ?></strong>
                        </div>
                    </div>

                    <div class="task-actions">
                        <?php if ($user['role'] === 'employee'): ?>
                            <form method="post" style="display:flex; gap:8px; width:100%;">
                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                <select name="status" style="flex:1;">
                                    <option value="Todo" <?= $t['status'] == 'Todo' ? 'selected' : '' ?>>Todo</option>
                                    <option value="In Progress" <?= $t['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Done" <?= $t['status'] == 'Done' ? 'selected' : '' ?>>Done</option>
                                </select>
                                <button type="submit" name="update_status" class="primary small">Update</button>
                            </form>
                        <?php else: ?>
                            <button class="danger small" onclick="confirmDelete(<?= $t['id'] ?>, '<?= htmlspecialchars($t['title']) ?>')">
                                🗑️ Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</main>

<footer>
    © <?= date('Y') ?> • Khalil Nafeti
</footer>

<form method="post" id="deleteForm" style="display:none;">
    <input type="hidden" name="task_id" id="delete_task_id">
    <input type="hidden" name="delete_task" value="1">
</form>

<script>
function toggleTheme() {
    document.body.classList.toggle('light');
    localStorage.setItem('theme', document.body.classList.contains('light') ? 'light' : 'dark');
}

if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
}

function confirmDelete(taskId, taskTitle) {
    if (confirm(`Are you sure you want to delete "${taskTitle}"? This action cannot be undone.`)) {
        document.getElementById('delete_task_id').value = taskId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

</body>
</html>