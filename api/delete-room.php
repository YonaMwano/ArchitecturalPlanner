<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$roomId = (int)($input['room_id'] ?? 0);

$db = getDB();
$user = currentUser();

$stmt = $db->prepare('SELECT r.id FROM rooms r JOIN projects p ON r.project_id = p.id WHERE r.id = ? AND p.user_id = ?');
$stmt->execute([$roomId, $user['id']]);
if (!$stmt->fetch()) jsonResponse(['success' => false, 'message' => 'Not found'], 404);

$db->prepare('DELETE FROM rooms WHERE id = ?')->execute([$roomId]);
jsonResponse(['success' => true]);
