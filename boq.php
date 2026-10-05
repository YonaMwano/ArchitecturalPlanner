<?php
require_once 'includes/config.php';
requireLogin();
$user = currentUser();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$project = $stmt->fetch();
if (!$project) { header('Location: dashboard.php'); exit; }

$stmt = $db->prepare('SELECT * FROM cost_summaries WHERE project_id = ?');
$stmt->execute([$id]);
$summary = $stmt->fetch();

$stmt = $db->prepare('SELECT * FROM boq_items WHERE project_id = ? ORDER BY FIELD(category,"substructure","superstructure","roofing","finishing","openings","electrical","plumbing","labour"), id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$byCat = [];
foreach ($items as $item) {
    $byCat[$item['category']][] = $item;
}

$pageTitle = 'BOQ - ' . $project['name'];
require 'includes/header.php';
?>

<div class="mb-4 flex flex-wrap justify-between items-center gap-3 no-print">
    <div>
        <a href="project.php?id=<?= $id ?>" class="text-sm text-slate-500 hover:text-primary-700"><i class="fas fa-arrow-left me-1"></i> Back to Project</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Bill of Quantities (BOQ)</h1>
        <p class="text-slate-500"><?= sanitize($project['name']) ?> · <?= sanitize($project['location'] ?: '') ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i> Print</button>
        <a href="api/export-excel.php?id=<?= $id ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel me-1"></i> Export Excel</a>
        <a href="api/export-pdf.php?id=<?= $id ?>" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf me-1"></i> Export PDF</a>
    </div>
</div>

<!-- Header for print -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
    <div class="flex justify-between items-start border-b pb-4 mb-4">
        <div>
            <h2 class="text-xl font-bold text-primary-800"><i class="fas fa-drafting-compass me-2"></i>BOQ-CAD Tanzania</h2>
            <p class="text-sm text-slate-500">Professional Construction Cost Estimate</p>
        </div>
        <div class="text-end text-sm">
            <p class="font-semibold"><?= sanitize($project['name']) ?></p>
            <p class="text-slate-500"><?= sanitize($project['location'] ?: 'Tanzania') ?></p>
            <p class="text-slate-400">Date: <?= date('d M Y') ?></p>
            <p class="text-slate-400">Client: <?= sanitize($user['full_name']) ?></p>
        </div>
    </div>

    <?php if ($summary): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-50 rounded-lg p-3 text-center">
            <div class="text-xs text-slate-500">Floor Area</div>
            <div class="text-xl font-bold"><?= number_format((float)$project['total_area_m2'], 1) ?> m²</div>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 text-center">
            <div class="text-xs text-slate-500">Materials</div>
            <div class="text-lg font-bold text-blue-700"><?= formatMoney((float)$summary['materials_subtotal']) ?></div>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 text-center">
            <div class="text-xs text-slate-500">Labour</div>
            <div class="text-lg font-bold text-amber-700"><?= formatMoney((float)$summary['labour_subtotal']) ?></div>
        </div>
        <div class="bg-emerald-50 rounded-lg p-3 text-center">
            <div class="text-xs text-emerald-600">Grand Total</div>
            <div class="text-xl font-bold text-emerald-700"><?= formatMoney((float)$summary['grand_total']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <div class="text-center py-12 text-slate-400">
        <i class="fas fa-inbox text-4xl mb-3"></i>
        <p>No BOQ items yet. Go to the project and click <strong>Calculate BOQ</strong>.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="table table-sm boq-table w-full">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Unit</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Unit Price (TZS)</th>
                    <th class="text-end">Total (TZS)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $catLabels = [
                    'substructure' => '1. Substructure (Msingi)',
                    'superstructure' => '2. Superstructure (Ukuta)',
                    'roofing' => '3. Roofing (Paa)',
                    'finishing' => '4. Finishing (Urembo)',
                    'openings' => '5. Openings (Milango & Madirisha)',
                    'electrical' => '6. Electrical (Umeme)',
                    'plumbing' => '7. Plumbing (Maji)',
                    'labour' => '8. Labour (Ufundi)'
                ];
                $n = 1;
                foreach ($byCat as $cat => $catItems):
                ?>
                <tr class="category-row">
                    <td colspan="6"><?= $catLabels[$cat] ?? strtoupper($cat) ?></td>
                </tr>
                <?php foreach ($catItems as $item): ?>
                <tr>
                    <td><?= $n++ ?></td>
                    <td>
                        <?= sanitize($item['item_name']) ?>
                        <?php if ($item['notes']): ?><br><small class="text-slate-400"><?= sanitize($item['notes']) ?></small><?php endif; ?>
                    </td>
                    <td><?= sanitize($item['unit']) ?></td>
                    <td class="text-end"><?= number_format((float)$item['quantity'], 2) ?></td>
                    <td class="text-end"><?= number_format((float)$item['unit_price'], 0) ?></td>
                    <td class="text-end font-medium"><?= number_format((float)$item['total_price'], 0) ?></td>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
            <?php if ($summary): ?>
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end">Materials Subtotal</td>
                    <td class="text-end font-semibold"><?= number_format((float)$summary['materials_subtotal'], 0) ?></td>
                </tr>
                <tr>
                    <td colspan="5" class="text-end">Labour Subtotal</td>
                    <td class="text-end font-semibold"><?= number_format((float)$summary['labour_subtotal'], 0) ?></td>
                </tr>
                <tr>
                    <td colspan="5" class="text-end">Transport (<?= $summary['transport_percent'] ?>%)</td>
                    <td class="text-end"><?= number_format((float)$summary['transport_amount'], 0) ?></td>
                </tr>
                <tr>
                    <td colspan="5" class="text-end">Contingency (<?= $summary['contingency_percent'] ?>%)</td>
                    <td class="text-end"><?= number_format((float)$summary['contingency_amount'], 0) ?></td>
                </tr>
                <tr class="table-success">
                    <td colspan="5" class="text-end fw-bold">GRAND TOTAL</td>
                    <td class="text-end fw-bold fs-5"><?= number_format((float)$summary['grand_total'], 0) ?> TZS</td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <div class="mt-6 pt-4 border-t text-xs text-slate-400">
        <p><strong>Notes:</strong></p>
        <ul class="list-disc ms-4">
            <li>Prices based on Tanzania retail market (Dar es Salaam, Dodoma, Kariakoo) — October 2026.</li>
            <li>5% waste already included in material quantities.</li>
            <li>Transport calculated as 10% of materials. Contingency 10% of (materials + labour + transport).</li>
            <li>This is an approximate estimate. Actual costs may vary by site conditions, brand choices and negotiation.</li>
        </ul>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
