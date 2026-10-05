<?php
/**
 * BOQ-CAD Configuration
 * Tanzania Construction Cost Estimator & Architectural Planner
 */

// Database credentials - update for your MySQL server
define('DB_HOST', 'sql204.infinityfree.com');
define('DB_NAME', 'if0_43096585_artx');
define('DB_USER', 'if0_43096585');
define('DB_PASS', '8uO73YoJ3OTVoik');
define('DB_CHARSET', 'utf8mb4');

// App settings
define('APP_NAME', 'BOQ-CAD Tanzania');
define('APP_VERSION', '1.0.0');
define('APP_URL', ''); // leave empty for relative
define('CURRENCY', 'TZS');
define('CURRENCY_SYMBOL', 'TSh');

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly message if DB not ready
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2>Database Connection Error</h2>
                <p>Please create the database and import <code>sql/schema.sql</code></p>
                <p style="color:#666;font-size:14px;">' . htmlspecialchars($e->getMessage()) . '</p>
                </div>');
        }
    }
    return $pdo;
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare('SELECT id, full_name, email, phone, region FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function formatMoney(float $amount): string {
    return number_format($amount, 0, '.', ',') . ' ' . CURRENCY;
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
