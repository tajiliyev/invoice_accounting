<?php
// ajax/get_next_invoice_number.php
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Находим максимальный номер счета в формате "01", "02", и т.д.
    $stmt = $pdo->query("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
    $last_invoice = $stmt->fetch();
    
    $next_number = '01'; // По умолчанию
    
    if ($last_invoice) {
        // Извлекаем числовую часть из номера счета
        $last_number = $last_invoice['invoice_number'];
        
        // Пробуем разные форматы
        if (preg_match('/\d+/', $last_number, $matches)) {
            $num = intval($matches[0]);
            $next_number = str_pad($num + 1, 2, '0', STR_PAD_LEFT);
        }
    }
    
    echo json_encode([
        'success' => true,
        'next_number' => $next_number
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $e->getMessage(),
        'next_number' => '01'
    ]);
}
?>