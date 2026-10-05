<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = loginUser($email, $password);
    if ($result['success']) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $result['message'];
}
$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | BOQ-CAD Tanzania</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-bg flex items-center justify-center p-4">
    <div class="auth-card w-full max-w-md p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-800 text-white text-2xl mb-3">
                <i class="fas fa-drafting-compass"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">BOQ-CAD Tanzania</h1>
            <p class="text-slate-500 text-sm mt-1">Architectural Plans & Construction Cost Estimator</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 text-sm"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="form-label text-sm font-medium text-slate-700">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-slate-100"><i class="fas fa-envelope text-slate-400"></i></span>
                    <input type="email" name="email" class="form-control" required placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div>
                <label class="form-label text-sm font-medium text-slate-700">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-slate-100"><i class="fas fa-lock text-slate-400"></i></span>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2.5 font-semibold" style="background:#1e40af;border-color:#1e40af;">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
            Don't have an account? <a href="register.php" class="text-primary-700 font-semibold hover:underline">Create Account</a>
        </p>
        <p class="text-center text-xs text-slate-400 mt-4">
            <i class="fas fa-info-circle me-1"></i> Prices based on Tanzania market — Oct 2026
        </p>
    </div>
</body>
</html>
