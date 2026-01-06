<?php
// ajax/update_user.php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    die(json_encode(['success' => false, 'message' => 'Config file not found']));
}

require_once $config_path;

$auth_path = dirname(__DIR__) . '/includes/auth.php';
if (file_exists($auth_path)) {
    require_once $auth_path;
}

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Не авторизован']));
}

if (!function_exists('isAdmin') || !isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Доступ запрещен']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Неверный метод']));
}

$user_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$role = $_POST['role'] ?? '';

if (empty($password) || empty($role)) {
    die(json_encode(['success' => false, 'message' => 'Все поля обязательны']));
}

if ($password !== $password_confirm) {
    die(json_encode(['success' => false, 'message' => 'Пароли не совпадают']));
}

if (strlen($password) < 4) {
    die(json_encode(['success' => false, 'message' => 'Пароль должен быть не менее 4 символов']));
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    if (!$stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Пользователь не найден']));
    }
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
    $stmt->execute([$password_hash, $role, $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Пользователь успешно обновлен'
    ]);
    
} catch (Exception $e) {
    error_log("Update user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}

exit();
?>