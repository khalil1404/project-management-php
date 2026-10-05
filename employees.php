<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

// Only managers can access this page
if ($user['role'] !== 'manager') {
    header("Location: dashboard.php");
    exit;
}

try {
    /* ADD EMPLOYEE */
    if (isset($_POST['add_employee'])) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            $error = "Email already exists!";
        } else {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                $hashedPassword,
                $_POST['role']
            ]);
            $success = "Employee added successfully!";
        }
    }

    /* UPDATE EMPLOYEE */
    if (isset($_POST['update_employee'])) {
        // Check if email is taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $_POST['employee_id']]);
        if ($stmt->fetch()) {
            $error = "Email already exists!";
        } else {
            if (!empty($_POST['password'])) {
                // Update with new password
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?"
                );
                $stmt->execute([
                    $_POST['name'],
                    $_POST['email'],
                    $hashedPassword,
                    $_POST['role'],
                    $_POST['employee_id']
                ]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare(
                    "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?"
                );
                $stmt->execute([
                    $_POST['name'],
                    $_POST['email'],
                    $_POST['role'],
                    $_POST['employee_id']
                ]);
            }
            $success = "Employee updated successfully!";
        }
    }

    /* DELETE EMPLOYEE */
    if (isset($_POST['delete_employee'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        $stmt->execute([$_POST['employee_id'], $user['id']]); // Prevent self-deletion
        $success = "Employee deleted successfully!";
    }

    /* FETCH ALL USERS */
    $employees = $pdo->query(
        "SELECT 
            u.*,
            COUNT(DISTINCT t.id) as task_count,
            COUNT(CASE WHEN t.status = 'Done' THEN 1 END) as completed_tasks,
            COUNT(DISTINCT p.id) as project_count
         FROM users u
         LEFT JOIN tasks t ON t.assigned_to = u.id
         LEFT JOIN project_members pm ON pm.user_id = u.id
         LEFT JOIN projects p ON p.id = pm.project_id
         GROUP BY u.id
         ORDER BY u.role DESC, u.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* EMPLOYEE STATISTICS */
    $stats = $pdo->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) as managers,
            SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) as employees
         FROM users"
    )->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Employee Management Error: " . $e->getMessage());
    $error = "Unable to load employees. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Management</title>

<style>
/* ================= THEME ================= */
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

* {
    box-sizing: border-box;
    font-family: system-ui, Segoe UI, sans-serif;
}

body {
    margin: 0;
    background: radial-gradient(circle at top, #0f172a, var(--bg));
    color: var(--text);
    min-height: 100vh;
}

/* ================= NAVBAR ================= */
nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 40px;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
}

.nav-left {
    display: flex;
    gap: 20px;
    align-items: center;
}

.nav-left a {
    color: var(--muted);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.nav-left a:hover {
    color: var(--text);
}

.nav-right {
    display: flex;
    gap: 14px;
    align-items: center;
}

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

button:hover {
    transform: translateY(-1px);
}

button.primary {
    background: var(--primary);
    border-color: var(--primary);
}

button.danger {
    background: var(--red);
    border-color: var(--red);
    color: white;
}

button.small {
    padding: 6px 12px;
    font-size: 13px;
}

/* ================= LAYOUT ================= */
.container {
    max-width: 1400px;
    margin: 40px auto;
    padding: 0 20px;
}

/* ================= MESSAGES ================= */
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

/* ================= STATS ================= */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

.stat:hover {
    transform: translateY(-4px);
    border-color: var(--primary);
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    display: block;
    margin-bottom: 8px;
}

.stat-label {
    color: var(--muted);
    font-size: 13px;
}

/* ================= CARDS ================= */
.card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
}

.card h2 {
    margin: 0 0 20px 0;
    font-size: 20px;
    font-weight: 600;
}

/* ================= FORMS ================= */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    color: var(--text);
    font-weight: 500;
    font-size: 14px;
}

input, select {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 14px;
    transition: 0.2s;
}

input:focus, select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255,255,255,0.08);
}

/* ================= EMPLOYEE GRID ================= */
.employees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
}

.employee-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.employee-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.employee-card.manager::before { background: var(--purple); }
.employee-card.employee::before { background: var(--cyan); }

.employee-card:hover {
    transform: translateY(-6px);
    border-color: var(--primary);
}

.employee-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
}

.employee-info {
    flex: 1;
}

.employee-name {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 8px 0;
}

.employee-email {
    color: var(--muted);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.employee-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
    padding: 16px;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

.stat-label-small {
    font-size: 11px;
    color: var(--muted);
}

.employee-meta {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    font-size: 13px;
    color: var(--muted);
}

.employee-actions {
    display: flex;
    gap: 10px;
}

.employee-actions button {
    flex: 1;
}

/* ================= BADGES ================= */
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge.manager { background: var(--purple); color: white; }
.badge.employee { background: var(--cyan); color: white; }

/* ================= MODAL ================= */
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

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--muted);
}

/* ================= FOOTER ================= */
footer {
    text-align: center;
    padding: 30px;
    color: var(--muted);
    font-size: 14px;
}

/* ================= RESPONSIVE ================= */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .employees-grid {
        grid-template-columns: 1fr;
    }
    
    nav {
        flex-direction: column;
        gap: 15px;
    }
}
</style>
</head>

<body>

<nav>
    <div class="nav-left">
        <strong>👥 Employees</strong>
        <a href="dashboard.php">Home</a>
        <a href="calendar.php">Calendar</a>
        <a href="projects.php">Projects</a>
        <a href="tasks.php">Tasks</a>
        <a href="kanban.php">Kanban</a>
        <a href="employees.php">Employees</a>
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
            <span class="stat-number" style="color:var(--primary)"><?= $stats['total'] ?? 0 ?></span>
            <span class="stat-label">Total Users</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--purple)"><?= $stats['managers'] ?? 0 ?></span>
            <span class="stat-label">Managers</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--cyan)"><?= $stats['employees'] ?? 0 ?></span>
            <span class="stat-label">Employees</span>
        </div>
    </div>

    <!-- ADD EMPLOYEE BUTTON -->
    <div style="margin-bottom: 30px;">
        <button onclick="openAddModal()" class="primary">➕ Add New Employee</button>
    </div>

    <!-- EMPLOYEES GRID -->
    <div class="card">
        <h2>All Employees (<?= count($employees) ?>)</h2>
        
        <div class="employees-grid">
            <?php foreach ($employees as $emp): ?>
            <div class="employee-card <?= strtolower($emp['role']) ?>">
                <div class="employee-header">
                    <div class="employee-info">
                        <h3 class="employee-name">
                            <?= htmlspecialchars($emp['name']) ?>
                            <?php if ($emp['id'] == $user['id']): ?>
                                <span style="font-size:12px; color:var(--green);">(You)</span>
                            <?php endif; ?>
                        </h3>
                        <div class="employee-email">
                            📧 <?= htmlspecialchars($emp['email']) ?>
                        </div>
                    </div>
                    <span class="badge <?= strtolower($emp['role']) ?>">
                        <?= htmlspecialchars($emp['role']) ?>
                    </span>
                </div>

                <div class="employee-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $emp['task_count'] ?></span>
                        <span class="stat-label-small">Tasks</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value" style="color:var(--green)"><?= $emp['completed_tasks'] ?></span>
                        <span class="stat-label-small">Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value" style="color:var(--cyan)"><?= $emp['project_count'] ?></span>
                        <span class="stat-label-small">Projects</span>
                    </div>
                </div>

                <div class="employee-meta">
                    <span>📅 Joined <?= date('M j, Y', strtotime($emp['created_at'])) ?></span>
                </div>

                <div class="employee-actions">
                    <button onclick='openEditModal(<?= json_encode($emp) ?>)' class="small">
                        ✏️ Edit
                    </button>
                    <?php if ($emp['id'] != $user['id']): ?>
                        <button class="danger small" onclick="confirmDelete(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name']) ?>')">
                            🗑️ Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<footer>
    © <?= date('Y') ?> • Khalil
</footer>

<!-- ADD EMPLOYEE MODAL -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Employee</h3>
            <button class="close-modal" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="add_name">Full Name</label>
                    <input type="text" id="add_name" name="name" placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label for="add_email">Email</label>
                    <input type="email" id="add_email" name="email" placeholder="employee@example.com" required>
                </div>

                <div class="form-group">
                    <label for="add_password">Password</label>
                    <input type="password" id="add_password" name="password" placeholder="Enter password" required>
                </div>

                <div class="form-group">
                    <label for="add_role">Role</label>
                    <select id="add_role" name="role" required>
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_employee" class="primary">Add Employee</button>
        </form>
    </div>
</div>

<!-- EDIT EMPLOYEE MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Employee</h3>
            <button class="close-modal" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="employee_id" id="edit_id">
            <div class="form-grid">
                <div class="form-group">
                    <label for="edit_name">Full Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="edit_password">New Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password" placeholder="Leave blank to keep current">
                </div>

                <div class="form-group">
                    <label for="edit_role">Role</label>
                    <select id="edit_role" name="role" required>
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="update_employee" class="primary">Update Employee</button>
        </form>
    </div>
</div>

<!-- DELETE CONFIRMATION (hidden form) -->
<form method="post" id="deleteForm" style="display:none;">
    <input type="hidden" name="employee_id" id="delete_employee_id">
    <input type="hidden" name="delete_employee" value="1">
</form>

<script>
// Theme persistence
function toggleTheme() {
    document.body.classList.toggle('light');
    localStorage.setItem('theme', document.body.classList.contains('light') ? 'light' : 'dark');
}

// Load saved theme
if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
}

// Add modal
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

// Edit modal
function openEditModal(employee) {
    document.getElementById('edit_id').value = employee.id;
    document.getElementById('edit_name').value = employee.name;
    document.getElementById('edit_email').value = employee.email;
    document.getElementById('edit_role').value = employee.role;
    document.getElementById('edit_password').value = '';
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// Close modals on outside click
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// Delete confirmation
function confirmDelete(employeeId, employeeName) {
    if (confirm(`Are you sure you want to delete "${employeeName}"? This will also remove them from all projects and tasks. This action cannot be undone.`)) {
        document.getElementById('delete_employee_id').value = employeeId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

</body>
</html>
