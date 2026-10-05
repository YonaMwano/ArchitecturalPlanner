<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/calculator.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$projectId = (int)($input['project_id'] ?? 0);

$db = getDB();
$user = currentUser();
$stmt = $db->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, $user['id']]);
if (!$stmt->fetch()) jsonResponse(['success' => false, 'message' => 'Project not found'], 404);

$calc = new BOQCalculator($projectId);
$result = $calc->calculate();
jsonResponse($result);
