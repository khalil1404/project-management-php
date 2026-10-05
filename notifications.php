<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

try {
    /* MARK AS READ */
    if (isset($_POST['mark_read'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE id = ?");
        $stmt->execute([$_POST['notification_id']]);
        $success = "Notification marked as read!";
    }

    /* MARK ALL AS READ */
    if (isset($_POST['mark_all_read'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $success = "All notifications marked as read!";
    }

    /* DELETE NOTIFICATION */
    if (isset($_POST['delete_notification'])) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$_POST['notification_id']]);
        $success = "Notification deleted!";
    }

    /* FETCH NOTIFICATIONS */
    $stmt = $pdo->prepare(
        "SELECT id, message, type, read_status, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY read_status ASC, created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* NOTIFICATION STATS */
    $stmt = $pdo->prepare(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN read_status = 0 THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN type = 'urgent' THEN 1 ELSE 0 END) as urgent,
            SUM(CASE WHEN type = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN type = 'info' THEN 1 ELSE 0 END) as info
         FROM notifications
         WHERE user_id = ?"
    );
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Notifications Error: " . $e->getMessage());
    $error = "Unable to load notifications. Please try again.";
}

function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications</title>

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

button.small {
    padding: 6px 12px;
    font-size: 12px;
}

button.danger {
    background: transparent;
    color: var(--red);
    border-color: var(--red);
}

/* ================= LAYOUT ================= */
.container {
    max-width: 1200px;
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
    margin-bottom: 30px;
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

/* ================= HEADER ================= */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

/* ================= NOTIFICATIONS ================= */
.notification-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 15px;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.notification-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}

.notification-card.info::before { background: var(--cyan); }
.notification-card.warning::before { background: var(--orange); }
.notification-card.urgent::before { background: var(--red); }

.notification-card.unread {
    background: rgba(59, 130, 246, 0.05);
    border-color: rgba(59, 130, 246, 0.3);
}

.notification-card:hover {
    transform: translateX(5px);
    border-color: var(--primary);
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}

.notification-type {
    display: flex;
    align-items: center;
    gap: 8px;
}

.type-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.type-badge.info { background: rgba(6, 182, 212, 0.2); color: var(--cyan); }
.type-badge.warning { background: rgba(245, 158, 11, 0.2); color: var(--orange); }
.type-badge.urgent { background: rgba(239, 68, 68, 0.2); color: var(--red); }

.unread-badge {
    background: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
}

.notification-actions {
    display: flex;
    gap: 8px;
}

.notification-message {
    color: var(--text);
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 12px;
}

.notification-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-time {
    color: var(--muted);
    font-size: 13px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

/* ================= EMPTY STATE ================= */
.empty-state {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 60px 20px;
    text-align: center;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: var(--text);
}

.empty-state p {
    color: var(--muted);
    margin: 0;
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
    .header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    nav {
        flex-direction: column;
        gap: 15px;
    }
    
    .notification-header {
        flex-direction: column;
        gap: 10px;
    }
}
</style>
</head>

<body>

<nav>
<div class="nav-left">
    <strong> Notifications</strong>
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

    <!-- HEADER -->
    <div class="header">
        <h1>Your Notifications</h1>
        <?php if ($stats['unread'] > 0): ?>
            <form method="post" style="display:inline;">
                <button type="submit" name="mark_all_read" class="primary">
                    Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- STATISTICS -->
    <div class="stats">
        <div class="stat">
            <span class="stat-number" style="color:var(--primary)"><?= $stats['total'] ?? 0 ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--cyan)"><?= $stats['unread'] ?? 0 ?></span>
            <span class="stat-label">Unread</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--red)"><?= $stats['urgent'] ?? 0 ?></span>
            <span class="stat-label">Urgent</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--orange)"><?= $stats['warning'] ?? 0 ?></span>
            <span class="stat-label">Warnings</span>
        </div>
        <div class="stat">
            <span class="stat-number" style="color:var(--green)"><?= $stats['info'] ?? 0 ?></span>
            <span class="stat-label">Info</span>
        </div>
    </div>

    <!-- NOTIFICATIONS LIST -->
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔕</div>
            <h3>No notifications yet</h3>
            <p>You're all caught up! Check back later for updates.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="notification-card <?= strtolower($n['type']) ?> <?= $n['read_status'] == 0 ? 'unread' : '' ?>">
                <div class="notification-header">
                    <div class="notification-type">
                        <span class="type-badge <?= strtolower($n['type']) ?>">
                            <?php if ($n['type'] == 'urgent'): ?>⚠️<?php elseif ($n['type'] == 'warning'): ?>⚡<?php else: ?>ℹ️<?php endif; ?>
                            <?= htmlspecialchars($n['type']) ?>
                        </span>
                        <?php if ($n['read_status'] == 0): ?>
                            <span class="unread-badge">NEW</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="notification-message">
                    <?= htmlspecialchars($n['message']) ?>
                </div>

                <div class="notification-footer">
                    <span class="notification-time">
                        🕐 <?= getTimeAgo($n['created_at']) ?>
                    </span>
                    
                    <div class="action-buttons">
                        <?php if ($n['read_status'] == 0): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                <button type="submit" name="mark_read" class="small">
                                    ✓ Mark Read
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this notification?');">
                            <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                            <button type="submit" name="delete_notification" class="small danger">
                                🗑️ Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<footer>
    © <?= date('Y') ?> • Khalil Nafeti
</footer>

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
</script>

</body>
</html>