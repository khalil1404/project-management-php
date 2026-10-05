<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

try {
    /* ADD PROJECT */
    if (isset($_POST['add_project']) && $user['role'] === 'manager') {
        $stmt = $pdo->prepare("INSERT INTO projects (name, description, status, manager_id) VALUES (?,?,?,?)");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['status'], $user['id']]);
        $success = "Project created successfully!";
    }

    /* DELETE PROJECT */
    if (isset($_POST['delete_project']) && $user['role'] === 'manager') {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$_POST['project_id']]);
        $success = "Project deleted successfully!";
    }

    /* UPDATE PROJECT STATUS */
    if (isset($_POST['update_status']) && $user['role'] === 'manager') {
        $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['project_id']]);
        $success = "Project status updated!";
    }

    /* FETCH PROJECTS WITH STATS */
    $stmt = $pdo->query("SELECT p.*, u.name as manager_name FROM projects p LEFT JOIN users u ON p.manager_id = u.id ORDER BY p.id DESC");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get task counts for each project
    foreach ($projects as &$project) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ?");
        $stmt->execute([$project['id']]);
        $project['task_count'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND status = 'Done'");
        $stmt->execute([$project['id']]);
        $project['completed_tasks'] = $stmt->fetchColumn();
    }

    /* PROJECT STATISTICS */
    $stats = $pdo->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Starting' THEN 1 ELSE 0 END) AS `starting`,
            SUM(CASE WHEN status = 'Running' THEN 1 ELSE 0 END) AS `running`,
            SUM(CASE WHEN status = 'Finishing' THEN 1 ELSE 0 END) AS `finishing`,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS `completed`
         FROM projects"
    )->fetch(PDO::FETCH_ASSOC);
    

} catch (PDOException $e) {
    error_log("Projects Error: " . $e->getMessage());
    $error = "Unable to load projects. Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Projects</title>

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
button.danger { background: var(--red); border-color: var(--red); color: white; }
button.small { padding: 6px 12px; font-size: 13px; }

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

.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
}

.project-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.project-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.project-card.starting::before { background: var(--orange); }
.project-card.running::before { background: var(--cyan); }
.project-card.finishing::before { background: var(--purple); }
.project-card.completed::before { background: var(--green); }

.project-card:hover { transform: translateY(-6px); border-color: var(--primary); }

.project-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
}

.project-title { font-size: 18px; font-weight: 600; margin: 0; }

.project-description {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 16px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.project-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 16px;
    font-size: 13px;
    color: var(--muted);
}

.project-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 16px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--cyan), var(--primary));
    border-radius: 10px;
    transition: width 0.3s;
}

.project-actions { display: flex; gap: 10px; }
.project-actions button { flex: 1; padding: 10px; font-size: 13px; }

.badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge.starting { background: var(--orange); color: white; }
.badge.running { background: var(--cyan); color: white; }
.badge.finishing { background: var(--purple); color: white; }
.badge.completed { background: var(--green); color: white; }

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
}

.modal.active { display: flex; }

.modal-content {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 { margin: 0; }

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--muted);
}

footer { text-align: center; padding: 30px; color: var(--muted); font-size: 14px; }

@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .projects-grid { grid-template-columns: 1fr; }
    nav { flex-direction: column; gap: 15px; }
}
</style>
</head>

<body>

<nav>
<div class="nav-left">
    <strong>📁 Projects</strong>
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

    <div class="stats">
        <div class="stat">
            <span class="stat-number" style="color:var(--primary)"><?= $stats['total'] ?? 0 ?></span>
            <span class="stat-label">Total Projects</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--orange)"><?= $stats['starting'] ?? 0 ?></span>
            <span class="stat-label">Starting</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--cyan)"><?= $stats['running'] ?? 0 ?></span>
            <span class="stat-label">Running</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--purple)"><?= $stats['finishing'] ?? 0 ?></span>
            <span class="stat-label">Finishing</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--green)"><?= $stats['completed'] ?? 0 ?></span>
            <span class="stat-label">Completed</span>
        </div>
    </div>

    <?php if ($user['role'] === 'manager'): ?>
    <div class="card">
        <h2>Create New Project</h2>
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Project Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter project name" required>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="Starting">Starting</option>
                        <option value="Running">Running</option>
                        <option value="Finishing">Finishing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the project goals and requirements"></textarea>
                </div>
            </div>

            <button type="submit" name="add_project" class="primary">Create Project</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>All Projects (<?= count($projects) ?>)</h2>
        
        <?php if (empty($projects)): ?>
            <p style="text-align:center; color:var(--muted); padding:40px 0;">No projects yet. Create your first project to get started!</p>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $p): 
                    $progress = $p['task_count'] > 0 ? ($p['completed_tasks'] / $p['task_count']) * 100 : 0;
                    $statusClass = strtolower($p['status']);
                ?>
                <div class="project-card <?= $statusClass ?>">
                    <div class="project-header">
                        <h3 class="project-title"><?= htmlspecialchars($p['name']) ?></h3>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                    </div>

                    <?php if (!empty($p['description'])): ?>
                        <p class="project-description"><?= htmlspecialchars($p['description']) ?></p>
                    <?php endif; ?>

                    <div class="project-meta">
                        <span>👤 <?= htmlspecialchars($p['manager_name'] ?? 'Unassigned') ?></span>
                        <span>📋 <?= $p['task_count'] ?> tasks</span>
                    </div>

                    <?php if ($p['task_count'] > 0): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 16px;">
                            <?= $p['completed_tasks'] ?> / <?= $p['task_count'] ?> tasks completed (<?= round($progress) ?>%)
                        </div>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'manager'): ?>
                        <div class="project-actions">
                            <button onclick="openStatusModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['status']) ?>')">
                                Change Status
                            </button>
                            <button class="danger" onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">
                                Delete
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</main>

<footer>
    © <?= date('Y') ?> • Khalil Nafeti
</footer>

<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Project Status</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="project_id" id="modal_project_id">
            <div class="form-group">
                <label for="modal_status">New Status</label>
                <select name="status" id="modal_status" required>
                    <option value="Starting">Starting</option>
                    <option value="Running">Running</option>
                    <option value="Finishing">Finishing</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            <br>
            <button type="submit" name="update_status" class="primary">Update Status</button>
        </form>
    </div>
</div>

<form method="post" id="deleteForm" style="display:none;">
    <input type="hidden" name="project_id" id="delete_project_id">
    <input type="hidden" name="delete_project" value="1">
</form>

<script>
function toggleTheme() {
    document.body.classList.toggle('light');
    localStorage.setItem('theme', document.body.classList.contains('light') ? 'light' : 'dark');
}

if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
}

function openStatusModal(projectId, currentStatus) {
    document.getElementById('modal_project_id').value = projectId;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('statusModal').classList.add('active');
}

function closeModal() {
    document.getElementById('statusModal').classList.remove('active');
}

document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

function confirmDelete(projectId, projectName) {
    if (confirm(`Are you sure you want to delete "${projectName}"? This action cannot be undone.`)) {
        document.getElementById('delete_project_id').value = projectId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

</body>
</html>