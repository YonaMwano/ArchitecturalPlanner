<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$user = currentUser();

$stmt = $db->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);

header('Location: ../dashboard.php');
exit;
