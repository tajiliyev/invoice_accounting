<?php
// ajax/change_my_password.php

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Неверный метод']));
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$new_password_confirm = $_POST['new_password_confirm'] ?? '';

if (empty($current_password) || empty($new_password)) {
    die(json_encode(['success' => false, 'message' => 'Все поля обязательны']));
}

if ($new_password !== $new_password_confirm) {
    die(json_encode(['success' => false, 'message' => 'Новые пароли не совпадают']));
}

if (strlen($new_password) < 4) {
    die(json_encode(['success' => false, 'message' => 'Новый пароль должен быть не менее 4 символов']));
}

try {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($current_password, $user['password'])) {
        die(json_encode(['success' => false, 'message' => 'Текущий пароль неверен']));
    }
    
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$password_hash, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Пароль успешно изменен'
    ]);
    
} catch (Exception $e) {
    error_log("Change password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}

exit();
?>