<?php
require_once 'includes/config.php';
require_once 'includes/calculator.php';
requireLogin();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $l = (float)($_POST['length'] ?? 0);
    $w = (float)($_POST['width'] ?? 0);
    $h = (float)($_POST['height'] ?? 3);
    $rooms = max(1, (int)($_POST['rooms'] ?? 1));
    if ($l >= 1 && $w >= 1) {
        $result = BOQCalculator::calculateManual($l, $w, $h, $rooms);
    }
}
$pageTitle = 'Quick Estimate';
require 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800"><i class="fas fa-bolt text-amber-500 me-2"></i>Quick Cost Estimate</h1>
        <p class="text-slate-500">Enter overall building dimensions — no drawing required. Instant Tanzania market rates.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <form method="POST" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label font-medium">Length (m)</label>
                <input type="number" name="length" class="form-control form-control-lg" step="0.1" min="1" required
                       value="<?= sanitize($_POST['length'] ?? '12') ?>" placeholder="12">
            </div>
            <div class="col-md-3">
                <label class="form-label font-medium">Width (m)</label>
                <input type="number" name="width" class="form-control form-control-lg" step="0.1" min="1" required
                       value="<?= sanitize($_POST['width'] ?? '10') ?>" placeholder="10">
            </div>
            <div class="col-md-2">
                <label class="form-label font-medium">Height (m)</label>
                <input type="number" name="height" class="form-control form-control-lg" step="0.1" value="<?= sanitize($_POST['height'] ?? '3') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label font-medium">Rooms</label>
                <input type="number" name="rooms" class="form-control form-control-lg" min="1" value="<?= sanitize($_POST['rooms'] ?? '4') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-lg w-100" style="background:#1e40af;border-color:#1e40af;">
                    <i class="fas fa-calculator"></i> Estimate
                </button>
            </div>
        </form>
    </div>

    <?php if ($result && $result['success']): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 text-center">
                <div class="text-xs text-blue-600">Floor Area</div>
                <div class="text-2xl font-bold text-blue-800"><?= $result['floor_area_m2'] ?> m²</div>
            </div>
            <div class="bg-slate-50 rounded-lg p-4 text-center">
                <div class="text-xs text-slate-500">Wall Area</div>
                <div class="text-2xl font-bold"><?= $result['wall_area_m2'] ?> m²</div>
            </div>
            <div class="bg-amber-50 rounded-lg p-4 text-center">
                <div class="text-xs text-amber-600">Materials + Labour</div>
                <div class="text-xl font-bold text-amber-800"><?= formatMoney($result['materials_subtotal'] + $result['labour_subtotal']) ?></div>
            </div>
            <div class="bg-emerald-50 rounded-lg p-4 text-center">
                <div class="text-xs text-emerald-600">Grand Total</div>
                <div class="text-2xl font-bold text-emerald-700"><?= formatMoney($result['grand_total']) ?></div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>Materials</span><strong><?= formatMoney($result['materials_subtotal']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Labour</span><strong><?= formatMoney($result['labour_subtotal']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Transport 10%</span><strong><?= formatMoney($result['transport_amount']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Contingency 10%</span><strong><?= formatMoney($result['contingency_amount']) ?></strong></li>
                </ul>
            </div>
            <div class="col-md-6">
                <div class="bg-emerald-600 text-white rounded-xl p-5 text-center h-100 d-flex flex-column justify-content-center">
                    <div class="text-sm opacity-80">Estimated Total Construction Cost</div>
                    <div class="text-3xl font-bold my-2"><?= formatMoney($result['grand_total']) ?></div>
                    <div class="text-sm opacity-70">≈ <?= number_format($result['grand_total'] / max(1,$result['floor_area_m2']), 0) ?> TZS / m²</div>
                </div>
            </div>
        </div>

        <h3 class="font-semibold mb-3">Key Material Quantities</h3>
        <div class="overflow-x-auto">
            <table class="table table-sm table-striped">
                <thead class="table-dark">
                    <tr><th>Item</th><th>Unit</th><th class="text-end">Quantity</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $item): ?>
                <tr>
                    <td><?= sanitize($item['item_name']) ?></td>
                    <td><?= $item['unit'] ?></td>
                    <td class="text-end"><?= number_format($item['quantity'], 2) ?></td>
                    <td class="text-end"><?= number_format($item['unit_price'], 0) ?></td>
                    <td class="text-end font-medium"><?= number_format($item['total_price'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-400 mt-3">5% waste included. Prices: Tanzania market Oct 2026. For detailed room-by-room BOQ, create a full project with drawing.</p>
    </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
