<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$projectId = (int)($input['project_id'] ?? 0);
$name = trim($input['name'] ?? 'Room');
$l = (float)($input['length_m'] ?? 0);
$w = (float)($input['width_m'] ?? 0);
$h = (float)($input['height_m'] ?? 3);

if ($l < 0.5 || $w < 0.5) jsonResponse(['success' => false, 'message' => 'Invalid dimensions'], 400);

$db = getDB();
$user = currentUser();
$stmt = $db->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, $user['id']]);
if (!$stmt->fetch()) jsonResponse(['success' => false, 'message' => 'Not found'], 404);

$stmt = $db->prepare('INSERT INTO rooms (project_id, name, length_m, width_m, height_m) VALUES (?,?,?,?,?)');
$stmt->execute([$projectId, $name, $l, $w, $h]);

jsonResponse(['success' => true, 'room_id' => (int)$db->lastInsertId()]);
