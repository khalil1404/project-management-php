<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

$taskId = $_GET['task_id'] ?? 0;

$stmt = $pdo->prepare(
    "SELECT tc.*, u.name as user_name 
     FROM task_comments tc
     LEFT JOIN users u ON tc.user_id = u.id
     WHERE tc.task_id = ?
     ORDER BY tc.created_at DESC"
);
$stmt->execute([$taskId]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($comments);