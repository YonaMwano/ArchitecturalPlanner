<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = registerUser(
        trim($_POST['full_name'] ?? ''),
        trim($_POST['email'] ?? ''),
        $_POST['password'] ?? '',
        trim($_POST['phone'] ?? ''),
        $_POST['region'] ?? 'Dar es Salaam'
    );
    if ($result['success']) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $result['message'];
}
$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | BOQ-CAD Tanzania</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-bg flex items-center justify-center p-4">
    <div class="auth-card w-full max-w-md p-8">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-800 text-white text-2xl mb-3">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Create Account</h1>
            <p class="text-slate-500 text-sm">Start estimating construction costs free</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 text-sm"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-3">
            <div>
                <label class="form-label text-sm font-medium">Full Name</label>
                <input type="text" name="full_name" class="form-control" required placeholder="Juma Hassan" value="<?= sanitize($_POST['full_name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label text-sm font-medium">Email</label>
                <input type="email" name="email" class="form-control" required placeholder="juma@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label text-sm font-medium">Phone (optional)</label>
                <input type="text" name="phone" class="form-control" placeholder="+255 7XX XXX XXX" value="<?= sanitize($_POST['phone'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label text-sm font-medium">Region</label>
                <select name="region" class="form-select">
                    <?php
                    $regions = ['Dar es Salaam','Dodoma','Arusha','Mwanza','Mbeya','Tanga','Morogoro','Zanzibar','Other'];
                    foreach ($regions as $r) {
                        $sel = (($_POST['region'] ?? 'Dar es Salaam') === $r) ? 'selected' : '';
                        echo "<option $sel>$r</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="form-label text-sm font-medium">Password</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2.5 font-semibold mt-2" style="background:#1e40af;border-color:#1e40af;">
                <i class="fas fa-user-plus me-2"></i> Create Account
            </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-5">
            Already have an account? <a href="login.php" class="text-primary-700 font-semibold hover:underline">Sign In</a>
        </p>
    </div>
</body>
</html>
