<?php
require_once 'includes/config.php';
requireLogin();
$user = currentUser();
$db = getDB();

// Stats
$stmt = $db->prepare('SELECT COUNT(*) as cnt, COALESCE(SUM(total_cost_tzs),0) as total_cost, COALESCE(SUM(total_area_m2),0) as total_area FROM projects WHERE user_id = ?');
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$stmt = $db->prepare('SELECT * FROM projects WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20');
$stmt->execute([$user['id']]);
$projects = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require 'includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Karibu, <?= sanitize(explode(' ', $user['full_name'])[0]) ?> 👋</h1>
    <p class="text-slate-500">Manage your architectural projects & construction estimates</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-sm">Total Projects</p>
                <p class="text-3xl font-bold"><?= (int)$stats['cnt'] ?></p>
            </div>
            <i class="fas fa-folder-open text-4xl opacity-30"></i>
        </div>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,#065f46,#10b981)">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-emerald-100 text-sm">Total Estimated Value</p>
                <p class="text-2xl font-bold"><?= formatMoney((float)$stats['total_cost']) ?></p>
            </div>
            <i class="fas fa-coins text-4xl opacity-30"></i>
        </div>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,#92400e,#f59e0b)">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-amber-100 text-sm">Total Floor Area</p>
                <p class="text-3xl font-bold"><?= number_format((float)$stats['total_area'], 1) ?> <span class="text-lg">m²</span></p>
            </div>
            <i class="fas fa-ruler-combined text-4xl opacity-30"></i>
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="flex flex-wrap gap-3 mb-8">
    <a href="project-new.php" class="btn btn-primary px-4 py-2" style="background:#1e40af;border-color:#1e40af;">
        <i class="fas fa-plus me-2"></i> New Project (with Drawing)
    </a>
    <a href="quick-estimate.php" class="btn btn-outline-primary px-4 py-2">
        <i class="fas fa-bolt me-2"></i> Quick Estimate (L × W)
    </a>
    <a href="materials.php" class="btn btn-outline-secondary px-4 py-2">
        <i class="fas fa-list me-2"></i> Material Prices
    </a>
</div>

<!-- Projects list -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
        <h2 class="font-semibold text-slate-800"><i class="fas fa-project-diagram me-2 text-primary-600"></i> Your Projects</h2>
    </div>
    <?php if (empty($projects)): ?>
    <div class="p-12 text-center text-slate-400">
        <i class="fas fa-drafting-compass text-5xl mb-4 opacity-40"></i>
        <p class="text-lg">No projects yet</p>
        <p class="text-sm mb-4">Create your first architectural plan or run a quick estimate</p>
        <a href="project-new.php" class="btn btn-primary" style="background:#1e40af;">Create Project</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Project Name</th>
                    <th>Type</th>
                    <th>Area (m²)</th>
                    <th>Est. Cost</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                <tr>
                    <td>
                        <a href="project.php?id=<?= $p['id'] ?>" class="font-medium text-primary-700 hover:underline">
                            <?= sanitize($p['name']) ?>
                        </a>
                        <?php if ($p['location']): ?>
                        <div class="text-xs text-slate-400"><i class="fas fa-map-marker-alt me-1"></i><?= sanitize($p['location']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-slate-100 text-slate-600"><?= ucfirst($p['project_type']) ?></span></td>
                    <td><?= number_format((float)$p['total_area_m2'], 1) ?></td>
                    <td class="font-semibold text-emerald-700"><?= formatMoney((float)$p['total_cost_tzs']) ?></td>
                    <td>
                        <?php
                        $statusColors = ['draft'=>'secondary','active'=>'primary','completed'=>'success','archived'=>'dark'];
                        $c = $statusColors[$p['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $c ?>"><?= ucfirst($p['status']) ?></span>
                    </td>
                    <td class="text-sm text-slate-500"><?= date('d M Y', strtotime($p['updated_at'])) ?></td>
                    <td class="text-end">
                        <a href="project.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Open"><i class="fas fa-edit"></i></a>
                        <a href="boq.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" title="BOQ Report"><i class="fas fa-file-invoice"></i></a>
                        <a href="api/delete-project.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this project permanently?" title="Delete"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
