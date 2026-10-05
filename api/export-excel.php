<?php
/**
 * Export BOQ to CSV (Excel-compatible)
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$user = currentUser();

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$project = $stmt->fetch();
if (!$project) { header('Location: ../dashboard.php'); exit; }

$stmt = $db->prepare('SELECT * FROM boq_items WHERE project_id = ? ORDER BY category, id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM cost_summaries WHERE project_id = ?');
$stmt->execute([$id]);
$summary = $stmt->fetch();

$filename = 'BOQ_' . preg_replace('/[^a-zA-Z0-9]/', '_', $project['name']) . '_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// BOM for Excel UTF-8
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['BOQ-CAD Tanzania — Bill of Quantities']);
fputcsv($out, ['Project', $project['name']]);
fputcsv($out, ['Location', $project['location']]);
fputcsv($out, ['Date', date('Y-m-d')]);
fputcsv($out, ['Client', $user['full_name']]);
fputcsv($out, ['Floor Area (m2)', $project['total_area_m2']]);
fputcsv($out, []);
fputcsv($out, ['#', 'Category', 'Item', 'Unit', 'Quantity', 'Unit Price (TZS)', 'Total (TZS)', 'Notes']);

$n = 1;
foreach ($items as $item) {
    fputcsv($out, [
        $n++,
        $item['category'],
        $item['item_name'],
        $item['unit'],
        $item['quantity'],
        $item['unit_price'],
        $item['total_price'],
        $item['notes']
    ]);
}

if ($summary) {
    fputcsv($out, []);
    fputcsv($out, ['', '', 'Materials Subtotal', '', '', '', $summary['materials_subtotal']]);
    fputcsv($out, ['', '', 'Labour Subtotal', '', '', '', $summary['labour_subtotal']]);
    fputcsv($out, ['', '', 'Transport 10%', '', '', '', $summary['transport_amount']]);
    fputcsv($out, ['', '', 'Contingency 10%', '', '', '', $summary['contingency_amount']]);
    fputcsv($out, ['', '', 'GRAND TOTAL', '', '', '', $summary['grand_total']]);
}

fclose($out);
exit;
