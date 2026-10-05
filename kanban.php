<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

try {
    /* UPDATE TASK STATUS */
    if (isset($_POST['update_task_status'])) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['task_id']]);
        header("Location: kanban.php");
        exit;
    }

    /* ADD COMMENT */
    if (isset($_POST['add_comment'])) {
        $stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['task_id'], $user['id'], $_POST['comment']]);
        $success = "Comment added!";
    }

    /* FETCH TASKS */
    if ($user['role'] === 'manager') {
        $allTasks = $pdo->query(
            "SELECT t.*, u.name as assigned_to_name, p.name as project_name,
             (SELECT COUNT(*) FROM task_comments WHERE task_id = t.id) as comment_count
             FROM tasks t
             LEFT JOIN users u ON t.assigned_to = u.id
             LEFT JOIN projects p ON t.project_id = p.id
             ORDER BY t.deadline ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            "SELECT t.*, p.name as project_name,
             (SELECT COUNT(*) FROM task_comments WHERE task_id = t.id) as comment_count
             FROM tasks t
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE t.assigned_to = ?
             ORDER BY t.deadline ASC"
        );
        $stmt->execute([$user['id']]);
        $allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Group by status
    $todoTasks = array_filter($allTasks, fn($t) => $t['status'] === 'Todo');
    $inProgressTasks = array_filter($allTasks, fn($t) => $t['status'] === 'In Progress');
    $doneTasks = array_filter($allTasks, fn($t) => $t['status'] === 'Done');

} catch (PDOException $e) {
    error_log("Kanban Error: " . $e->getMessage());
    $error = "Unable to load kanban board.";
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
<title>Kanban Board</title>

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

.container { max-width: 1600px; margin: 40px auto; padding: 0 20px; }

.success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid var(--green);
    color: var(--green);
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-header h1 { margin: 0; font-size: 28px; }

.view-toggle { display: flex; gap: 10px; }

.view-toggle a {
    padding: 10px 20px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    text-decoration: none;
    transition: 0.2s;
}

.view-toggle a:hover { border-color: var(--primary); }
.view-toggle a.active { background: var(--primary); border-color: var(--primary); }

.kanban-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    min-height: 70vh;
}

.kanban-column {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 20px;
}

.column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.column-header h2 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.column-count {
    background: rgba(255,255,255,0.1);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
}

.column-header.todo { border-bottom-color: var(--muted); }
.column-header.inprogress { border-bottom-color: var(--orange); }
.column-header.done { border-bottom-color: var(--green); }

.kanban-cards {
    display: flex;
    flex-direction: column;
    gap: 15px;
    min-height: 200px;
}

.kanban-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: 0.2s;
    position: relative;
}

.kanban-card:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
    background: rgba(255,255,255,0.06);
}

.card-priority {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.card-priority.high { background: var(--red); }
.card-priority.medium { background: var(--orange); }
.card-priority.low { background: var(--green); }

.card-title {
    font-weight: 600;
    margin-bottom: 8px;
    padding-right: 20px;
}

.card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 12px;
    color: var(--muted);
}

.card-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.badge { padding: 4px 8px; border-radius: 8px; font-size: 10px; font-weight: 600; }
.badge.overdue { background: var(--red); color: white; }

.empty-column {
    text-align: center;
    padding: 40px 20px;
    color: var(--muted);
    border: 2px dashed var(--border);
    border-radius: 12px;
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

.task-detail .value {
    font-size: 16px;
}

.status-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.status-buttons button {
    flex: 1;
    padding: 10px;
}

.comments-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid var(--border);
}

.comments-section h4 { margin: 0 0 15px 0; font-size: 16px; }

.comment {
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 10px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.comment-author { font-weight: 600; font-size: 14px; }
.comment-time { font-size: 12px; color: var(--muted); }
.comment-text { font-size: 14px; line-height: 1.5; }

.comment-form textarea {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 12px;
    border-radius: 10px;
    font-size: 14px;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

footer { text-align: center; padding: 30px; color: var(--muted); font-size: 14px; }

@media (max-width: 1200px) {
    .kanban-board { grid-template-columns: 1fr; }
}
</style>
</head>

<body>

<nav>
    <div class="nav-left">
        <strong>📊 Kanban Board</strong>
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

    <?php if (isset($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Task Board</h1>
        <div class="view-toggle">
            <a href="tasks.php">📋 List View</a>
            <a href="kanban.php" class="active">📊 Kanban View</a>
        </div>
    </div>

    <div class="kanban-board">
        <!-- TODO COLUMN -->
        <div class="kanban-column">
            <div class="column-header todo">
                <h2>📝 To Do</h2>
                <span class="column-count"><?= count($todoTasks) ?></span>
            </div>
            <div class="kanban-cards">
                <?php if (empty($todoTasks)): ?>
                    <div class="empty-column">No tasks</div>
                <?php else: ?>
                    <?php foreach ($todoTasks as $task): ?>
                        <div class="kanban-card" onclick='openTask(<?= $task["id"] ?>)'>
                            <div class="card-priority <?= strtolower($task['priority']) ?>"></div>
                            <div class="card-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div class="card-meta">
                                <span>📁 <?= htmlspecialchars($task['project_name'] ?? 'No Project') ?></span>
                                <?php if (isset($task['assigned_to_name'])): ?>
                                    <span>👤 <?= htmlspecialchars($task['assigned_to_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($task['deadline'] && $task['deadline'] != '0000-00-00'): ?>
                                    <span>📅 <?= date('M j', strtotime($task['deadline'])) ?></span>
                                <?php endif; ?>
                                <?php if ($task['comment_count'] > 0): ?>
                                    <span>💬 <?= $task['comment_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- IN PROGRESS COLUMN -->
        <div class="kanban-column">
            <div class="column-header inprogress">
                <h2>🔄 In Progress</h2>
                <span class="column-count"><?= count($inProgressTasks) ?></span>
            </div>
            <div class="kanban-cards">
                <?php if (empty($inProgressTasks)): ?>
                    <div class="empty-column">No tasks</div>
                <?php else: ?>
                    <?php foreach ($inProgressTasks as $task): ?>
                        <div class="kanban-card" onclick='openTask(<?= $task["id"] ?>)'>
                            <div class="card-priority <?= strtolower($task['priority']) ?>"></div>
                            <div class="card-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div class="card-meta">
                                <span>📁 <?= htmlspecialchars($task['project_name'] ?? 'No Project') ?></span>
                                <?php if (isset($task['assigned_to_name'])): ?>
                                    <span>👤 <?= htmlspecialchars($task['assigned_to_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($task['deadline'] && $task['deadline'] != '0000-00-00'): ?>
                                    <span>📅 <?= date('M j', strtotime($task['deadline'])) ?></span>
                                <?php endif; ?>
                                <?php if ($task['comment_count'] > 0): ?>
                                    <span>💬 <?= $task['comment_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- DONE COLUMN -->
        <div class="kanban-column">
            <div class="column-header done">
                <h2>✅ Done</h2>
                <span class="column-count"><?= count($doneTasks) ?></span>
            </div>
            <div class="kanban-cards">
                <?php if (empty($doneTasks)): ?>
                    <div class="empty-column">No tasks</div>
                <?php else: ?>
                    <?php foreach ($doneTasks as $task): ?>
                        <div class="kanban-card" onclick='openTask(<?= $task["id"] ?>)'>
                            <div class="card-priority <?= strtolower($task['priority']) ?>"></div>
                            <div class="card-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div class="card-meta">
                                <span>📁 <?= htmlspecialchars($task['project_name'] ?? 'No Project') ?></span>
                                <?php if (isset($task['assigned_to_name'])): ?>
                                    <span>👤 <?= htmlspecialchars($task['assigned_to_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($task['deadline'] && $task['deadline'] != '0000-00-00'): ?>
                                    <span>📅 <?= date('M j', strtotime($task['deadline'])) ?></span>
                                <?php endif; ?>
                                <?php if ($task['comment_count'] > 0): ?>
                                    <span>💬 <?= $task['comment_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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

<!-- Store all tasks data -->
<script>
const tasks = <?= json_encode($allTasks) ?>;

function toggleTheme() {
    document.body.classList.toggle('light');
    localStorage.setItem('theme', document.body.classList.contains('light') ? 'light' : 'dark');
}

if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
}

function openTask(taskId) {
    const task = tasks.find(t => t.id == taskId);
    if (!task) return;
    
    document.getElementById('modalTitle').textContent = task.title;
    
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
            <div class="value">${task.priority}</div>
        </div>
        <div class="task-detail">
            <label>Deadline</label>
            <div class="value">${task.deadline && task.deadline != '0000-00-00' ? new Date(task.deadline).toLocaleDateString() : 'No deadline'}</div>
        </div>
        <div class="task-detail">
            <label>Status</label>
            <div class="value">${task.status}</div>
        </div>
        
        <div class="status-buttons">
            <form method="post" style="display:inline;flex:1;">
                <input type="hidden" name="task_id" value="${task.id}">
                <input type="hidden" name="status" value="Todo">
                <button type="submit" name="update_task_status">Move to Todo</button>
            </form>
            <form method="post" style="display:inline;flex:1;">
                <input type="hidden" name="task_id" value="${task.id}">
                <input type="hidden" name="status" value="In Progress">
                <button type="submit" name="update_task_status">Move to In Progress</button>
            </form>
            <form method="post" style="display:inline;flex:1;">
                <input type="hidden" name="task_id" value="${task.id}">
                <input type="hidden" name="status" value="Done">
                <button type="submit" name="update_task_status" class="primary">Mark Done</button>
            </form>
        </div>
        
        <div class="comments-section">
            <h4>💬 Comments (${task.comment_count})</h4>
            <div id="commentsContainer">Loading...</div>
            <form method="post" class="comment-form">
                <input type="hidden" name="task_id" value="${task.id}">
                <textarea name="comment" placeholder="Add a comment..." required></textarea>
                <button type="submit" name="add_comment" class="primary" style="margin-top:10px;">Post Comment</button>
            </form>
        </div>
    `;
    
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('taskModal').classList.add('active');
    
    loadComments(task.id);
}

function loadComments(taskId) {
    fetch(`get_comments.php?task_id=${taskId}`)
        .then(response => response.json())
        .then(comments => {
            const container = document.getElementById('commentsContainer');
            if (comments.length === 0) {
                container.innerHTML = '<p style="color:var(--muted);text-align:center;padding:20px 0;">No comments yet</p>';
            } else {
                container.innerHTML = comments.map(c => `
                    <div class="comment">
                        <div class="comment-header">
                            <span class="comment-author">${c.user_name}</span>
                            <span class="comment-time">${new Date(c.created_at).toLocaleString()}</span>
                        </div>
                        <div class="comment-text">${c.comment}</div>
                    </div>
                `).join('');
            }
        })
        .catch(() => {
            document.getElementById('commentsContainer').innerHTML = '<p style="color:var(--red);">Error loading comments</p>';
        });
}

function closeModal() {
    document.getElementById('taskModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('taskModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

</body>
</html>