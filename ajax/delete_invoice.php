<?php
// ajax/delete_invoice.php

// Включаем вывод ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Начинаем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Устанавливаем заголовок JSON сразу
header('Content-Type: application/json; charset=utf-8');

// Проверяем путь к config.php
$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    die(json_encode([
        'success' => false, 
        'message' => 'Config file not found: ' . $config_path
    ]));
}

require_once $config_path;

// Проверяем путь к auth.php
$auth_path = dirname(__DIR__) . '/includes/auth.php';
if (!file_exists($auth_path)) {
    die(json_encode([
        'success' => false, 
        'message' => 'Auth file not found'
    ]));
}

require_once $auth_path;

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Не авторизован']));
}

// Проверяем права администратора
if (!function_exists('isAdmin')) {
    die(json_encode(['success' => false, 'message' => 'Функция isAdmin не найдена']));
}

if (!isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Доступ запрещен. Требуются права администратора']));
}

// Получаем ID счета
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'Неверный ID счета']));
}

try {
    // Получаем информацию о счете
    $stmt = $pdo->prepare("SELECT i.invoice_number, c.name as client_name 
                          FROM invoices i 
                          JOIN clients c ON i.client_id = c.id 
                          WHERE i.id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        die(json_encode(['success' => false, 'message' => 'Счет не найден']));
    }
    
    // Сначала удаляем связанные платежи (если таблица payments существует)
    try {
        $stmt = $pdo->prepare("DELETE FROM payments WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
    } catch (Exception $e) {
        // Игнорируем ошибку если таблицы payments нет
        error_log("Payments delete warning: " . $e->getMessage());
    }
    
    // Удаляем счет
    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    
    echo json_encode([
        'success' => true,
        'message' => "Счет №{$invoice['invoice_number']} (клиент: {$invoice['client_name']}) успешно удален",
        'invoice_number' => $invoice['invoice_number']
    ]);
    
} catch (Exception $e) {
    // Логируем ошибку
    error_log("Delete invoice error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при удалении счета: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
}

// Завершаем выполнение
exit();
?>