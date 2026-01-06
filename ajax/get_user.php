<?php
// ajax/get_user.php

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

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'Неверный ID пользователя']));
}

try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die(json_encode(['success' => false, 'message' => 'Пользователь не найден']));
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Get user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}

exit();
?>