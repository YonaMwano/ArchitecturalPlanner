<?php
if (!isset($pageTitle)) $pageTitle = APP_NAME;
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> | <?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom -->
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' },
                        accent: { 500:'#f59e0b',600:'#d97706' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen">
<?php if ($user): ?>
<nav class="bg-primary-800 text-white shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <a href="dashboard.php" class="flex items-center gap-2 font-bold text-xl">
                <i class="fas fa-drafting-compass text-accent-500"></i>
                <span>BOQ-CAD</span>
                <span class="text-xs bg-accent-500 text-white px-2 py-0.5 rounded-full">TZ</span>
            </a>
            <div class="hidden md:flex items-center gap-6">
                <a href="dashboard.php" class="hover:text-accent-500 transition"><i class="fas fa-home me-1"></i> Dashboard</a>
                <a href="project-new.php" class="hover:text-accent-500 transition"><i class="fas fa-plus-circle me-1"></i> New Project</a>
                <a href="materials.php" class="hover:text-accent-500 transition"><i class="fas fa-cubes me-1"></i> Materials</a>
                <a href="quick-estimate.php" class="hover:text-accent-500 transition"><i class="fas fa-calculator me-1"></i> Quick Estimate</a>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm hidden sm:inline"><i class="fas fa-user-circle me-1"></i> <?= sanitize($user['full_name']) ?></span>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded text-sm transition"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container mx-auto px-4 py-6">
