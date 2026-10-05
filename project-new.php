<?php
require_once 'includes/config.php';
requireLogin();
$user = currentUser();
$db = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $loc  = trim($_POST['location'] ?? '');
    $type = $_POST['project_type'] ?? 'residential';

    if (strlen($name) < 2) {
        $error = 'Project name is required';
    } else {
        $stmt = $db->prepare('INSERT INTO projects (user_id, name, description, location, project_type) VALUES (?,?,?,?,?)');
        $stmt->execute([$user['id'], $name, $desc, $loc, $type]);
        $id = (int)$db->lastInsertId();
        header("Location: project.php?id=$id");
        exit;
    }
}
$pageTitle = 'New Project';
require 'includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="dashboard.php" class="text-sm text-slate-500 hover:text-primary-700"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2">Create New Project</h1>
        <p class="text-slate-500">Set up a new architectural plan & cost estimate</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <form method="POST" class="space-y-4">
            <div>
                <label class="form-label font-medium">Project Name *</label>
                <input type="text" name="name" class="form-control form-control-lg" required placeholder="e.g. 3-Bedroom House – Kinondoni" value="<?= sanitize($_POST['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label font-medium">Location</label>
                <input type="text" name="location" class="form-control" placeholder="e.g. Dar es Salaam, Mikocheni" value="<?= sanitize($_POST['location'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label font-medium">Project Type</label>
                <select name="project_type" class="form-select">
                    <option value="residential">Residential (Nyumba)</option>
                    <option value="commercial">Commercial (Biashara)</option>
                    <option value="industrial">Industrial</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="form-label font-medium">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the building..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn btn-primary px-5" style="background:#1e40af;border-color:#1e40af;">
                    <i class="fas fa-arrow-right me-2"></i> Create & Open Drawing Board
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
