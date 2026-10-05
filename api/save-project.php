<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$projectId = (int)($input['project_id'] ?? 0);
$canvas = $input['canvas_data'] ?? null;
$rooms = $input['rooms'] ?? [];
$replace = !empty($input['replace_rooms']);

$db = getDB();
$user = currentUser();

$stmt = $db->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, $user['id']]);
if (!$stmt->fetch()) jsonResponse(['success' => false, 'message' => 'Project not found'], 404);

if ($canvas !== null) {
    $stmt = $db->prepare('UPDATE projects SET canvas_data = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$canvas, $projectId]);
}

if ($replace && is_array($rooms)) {
    $db->prepare('DELETE FROM rooms WHERE project_id = ?')->execute([$projectId]);
    $ins = $db->prepare('INSERT INTO rooms (project_id, name, length_m, width_m, height_m) VALUES (?,?,?,?,?)');
    foreach ($rooms as $r) {
        $ins->execute([
            $projectId,
            $r['name'] ?? 'Room',
            (float)($r['length_m'] ?? 0),
            (float)($r['width_m'] ?? 0),
            (float)($r['height_m'] ?? 3)
        ]);
    }
}

jsonResponse(['success' => true, 'message' => 'Saved']);
