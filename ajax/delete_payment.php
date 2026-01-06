<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit();
}

$payment_id = $_GET['id'] ?? 0;

if ($payment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректный ID']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Получаем информацию о платеже
    $stmt = $pdo->prepare("SELECT invoice_id, amount FROM invoice_payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception("Платеж не найден");
    }
    
    // Удаляем платеж
    $stmt = $pdo->prepare("DELETE FROM invoice_payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    
    // Пересчитываем оплаты для счета
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_paid 
        FROM invoice_payments 
        WHERE invoice_id = ?
    ");
    $stmt->execute([$payment['invoice_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_paid = $result['total_paid'] ?? 0;
    
    // Получаем сумму счета
    $stmt = $pdo->prepare("SELECT amount FROM invoices WHERE id = ?");
    $stmt->execute([$payment['invoice_id']]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    $invoice_amount = $invoice['amount'] ?? 0;
    
    // Определяем новый статус
    $new_status = 'partial';
    if ($total_paid == 0) {
        $new_status = 'pending';
    } elseif ($total_paid >= $invoice_amount) {
        $new_status = 'paid';
        $total_paid = $invoice_amount;
    }
    
    // Обновляем счет
    $stmt = $pdo->prepare("
        UPDATE invoices 
        SET paid_amount = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$total_paid, $new_status, $payment['invoice_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Платеж удален',
        'invoice_id' => $payment['invoice_id']
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}