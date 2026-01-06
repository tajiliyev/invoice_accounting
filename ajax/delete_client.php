<?php
// ajax/delete_client.php

// Подключаем конфиг (сессия уже запускается в config.php)
$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Config file not found']));
}
require_once $config_path;

// Подключаем функции авторизации
$auth_path = dirname(__DIR__) . '/includes/auth.php';
if (file_exists($auth_path)) {
    require_once $auth_path;
}

header('Content-Type: application/json');

// Проверяем авторизацию и права
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Не авторизован']));
}

// Проверяем функцию isAdmin
if (!function_exists('isAdmin')) {
    die(json_encode(['success' => false, 'message' => 'Системная ошибка: функция проверки прав не найдена']));
}

if (!isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Доступ запрещен. Только для администратора']));
}

$client_id = (int)($_GET['id'] ?? 0);

if ($client_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'Неверный ID клиента']));
}

try {
    // Проверяем, есть ли у клиента счета
    $stmt = $pdo->prepare("SELECT COUNT(*) as invoice_count FROM invoices WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $result = $stmt->fetch();
    
    if ($result['invoice_count'] > 0) {
        die(json_encode([
            'success' => false, 
            'message' => 'Нельзя удалить клиента, у которого есть счета'
        ]));
    }
    
    // Получаем информацию о клиенте
    $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if (!$client) {
        die(json_encode(['success' => false, 'message' => 'Клиент не найден']));
    }
    
    // Удаляем клиента
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    
    echo json_encode([
        'success' => true,
        'message' => "Клиент '{$client['name']}' успешно удален"
    ]);
    
} catch (Exception $e) {
    error_log("Delete client error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при удалении клиента: ' . $e->getMessage()
    ]);
}
?>