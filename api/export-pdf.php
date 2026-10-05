<?php
/**
 * PDF-style printable BOQ (browser print → Save as PDF)
 * For full server-side PDF, install TCPDF/FPDF later.
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$user = currentUser();

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$project = $stmt->fetch();
if (!$project) { die('Not found'); }

$stmt = $db->prepare('SELECT * FROM cost_summaries WHERE project_id = ?');
$stmt->execute([$id]);
$summary = $stmt->fetch();

$stmt = $db->prepare('SELECT * FROM boq_items WHERE project_id = ? ORDER BY FIELD(category,"substructure","superstructure","roofing","finishing","openings","electrical","plumbing","labour"), id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$byCat = [];
foreach ($items as $item) $byCat[$item['category']][] = $item;

$catLabels = [
    'substructure'=>'1. Substructure','superstructure'=>'2. Superstructure','roofing'=>'3. Roofing',
    'finishing'=>'4. Finishing','openings'=>'5. Openings','electrical'=>'6. Electrical',
    'plumbing'=>'7. Plumbing','labour'=>'8. Labour'
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BOQ - <?= htmlspecialchars($project['name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; margin: 20px; }
        h1 { font-size: 18px; color: #1e40af; margin: 0; }
        h2 { font-size: 14px; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
        th { background: #1e40af; color: white; }
        .cat { background: #e2e8f0; font-weight: bold; }
        .right { text-align: right; }
        .total-row { background: #d1fae5; font-weight: bold; font-size: 13px; }
        .meta { color: #666; font-size: 10px; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:15px;">
        <button onclick="window.print()" style="padding:8px 16px;background:#1e40af;color:white;border:none;border-radius:4px;cursor:pointer;">Print / Save as PDF</button>
        <a href="../boq.php?id=<?= $id ?>" style="margin-left:10px;">← Back</a>
    </div>

    <h1>BOQ-CAD Tanzania — Bill of Quantities</h1>
    <p class="meta">
        Project: <strong><?= htmlspecialchars($project['name']) ?></strong> |
        Location: <?= htmlspecialchars($project['location'] ?: 'Tanzania') ?> |
        Client: <?= htmlspecialchars($user['full_name']) ?> |
        Date: <?= date('d M Y') ?> |
        Floor Area: <?= number_format($project['total_area_m2'],1) ?> m²
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th><th>Description</th><th>Unit</th>
                <th class="right">Qty</th><th class="right">Unit Price</th><th class="right">Total (TZS)</th>
            </tr>
        </thead>
        <tbody>
        <?php $n=1; foreach ($byCat as $cat => $catItems): ?>
            <tr class="cat"><td colspan="6"><?= $catLabels[$cat] ?? $cat ?></td></tr>
            <?php foreach ($catItems as $item): ?>
            <tr>
                <td><?= $n++ ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td><?= $item['unit'] ?></td>
                <td class="right"><?= number_format($item['quantity'],2) ?></td>
                <td class="right"><?= number_format($item['unit_price'],0) ?></td>
                <td class="right"><?= number_format($item['total_price'],0) ?></td>
            </tr>
            <?php endforeach; endforeach; ?>
        </tbody>
        <?php if ($summary): ?>
        <tfoot>
            <tr><td colspan="5" class="right">Materials Subtotal</td><td class="right"><?= number_format($summary['materials_subtotal'],0) ?></td></tr>
            <tr><td colspan="5" class="right">Labour Subtotal</td><td class="right"><?= number_format($summary['labour_subtotal'],0) ?></td></tr>
            <tr><td colspan="5" class="right">Transport 10%</td><td class="right"><?= number_format($summary['transport_amount'],0) ?></td></tr>
            <tr><td colspan="5" class="right">Contingency 10%</td><td class="right"><?= number_format($summary['contingency_amount'],0) ?></td></tr>
            <tr class="total-row"><td colspan="5" class="right">GRAND TOTAL</td><td class="right"><?= number_format($summary['grand_total'],0) ?> TZS</td></tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <p class="meta" style="margin-top:20px;">
        Notes: Prices based on Tanzania market (Oct 2026). 5% waste included in quantities. This is an approximate estimate.
    </p>
    <script>window.onload = function(){ /* optional auto print */ }</script>
</body>
</html>
