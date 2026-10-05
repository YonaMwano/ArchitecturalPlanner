<?php
require_once 'includes/config.php';
requireLogin();
$db = getDB();

$materials = $db->query('SELECT * FROM materials WHERE is_active = 1 ORDER BY category, name')->fetchAll();
$byCat = [];
foreach ($materials as $m) $byCat[$m['category']][] = $m;

$catLabels = [
    'substructure' => 'Substructure (Msingi)',
    'superstructure' => 'Superstructure (Ukuta)',
    'roofing' => 'Roofing (Paa)',
    'finishing' => 'Finishing (Urembo)',
    'openings' => 'Openings (Milango & Madirisha)',
    'electrical' => 'Electrical (Umeme)',
    'plumbing' => 'Plumbing (Maji)',
    'labour' => 'Labour (Ufundi)'
];

$pageTitle = 'Material Prices';
require 'includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800"><i class="fas fa-cubes text-primary-600 me-2"></i>Building Material Prices — Tanzania</h1>
    <p class="text-slate-500">Current market rates (Dar es Salaam, Dodoma, Kariakoo) — October 2026. Used for all cost estimates.</p>
</div>

<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-6 text-sm text-amber-800">
    <i class="fas fa-info-circle me-1"></i>
    Prices shown are average retail. Actual site costs may vary by brand, quantity discounts and location. Labour rates are per m².
</div>

<?php foreach ($byCat as $cat => $items): ?>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-5 overflow-hidden">
    <div class="px-5 py-3 bg-primary-800 text-white font-semibold">
        <?= $catLabels[$cat] ?? ucfirst($cat) ?>
    </div>
    <div class="overflow-x-auto">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Material</th>
                    <th>Unit</th>
                    <th class="text-end">Min (TZS)</th>
                    <th class="text-end">Max (TZS)</th>
                    <th class="text-end">Avg Used</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $m): ?>
                <tr>
                    <td class="font-medium"><?= sanitize($m['name']) ?></td>
                    <td><span class="badge bg-slate-100 text-slate-600"><?= $m['unit'] ?></span></td>
                    <td class="text-end text-slate-500"><?= number_format($m['price_min'], 0) ?></td>
                    <td class="text-end text-slate-500"><?= number_format($m['price_max'], 0) ?></td>
                    <td class="text-end"><span class="price-badge"><?= number_format($m['price_avg'], 0) ?></span></td>
                    <td class="text-sm text-slate-400"><?= sanitize($m['notes'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php require 'includes/footer.php'; ?>
