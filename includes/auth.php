<?php
/**
 * Authentication functions
 */
require_once __DIR__ . '/config.php';

function registerUser(string $fullName, string $email, string $password, string $phone = '', string $region = 'Dar es Salaam'): array {
    $db = getDB();
    
    // Validate
    if (strlen($fullName) < 2) return ['success' => false, 'message' => 'Full name is too short'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Invalid email address'];
    if (strlen($password) < 6) return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    
    // Check existing
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) return ['success' => false, 'message' => 'Email already registered'];
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (full_name, email, phone, password_hash, region) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$fullName, $email, $phone, $hash, $region]);
    
    $userId = (int)$db->lastInsertId();
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $fullName;
    
    return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
}

function loginUser(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    
    return ['success' => true, 'message' => 'Login successful', 'user_id' => (int)$user['id']];
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
