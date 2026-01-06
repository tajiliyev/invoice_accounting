<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit();
}

$payment_id = $_POST['payment_id'] ?? 0;
$invoice_id = $_POST['invoice_id'] ?? 0;
$amount = $_POST['payment_amount'] ?? 0;

if ($payment_id <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Обновляем платеж
    $stmt = $pdo->prepare("
        UPDATE invoice_payments 
        SET amount = ?, payment_date = ?, payment_method = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $amount,
        $_POST['payment_date'],
        $_POST['payment_method'],
        $_POST['notes'] ?? '',
        $payment_id
    ]);
    
    // Пересчитываем общую сумму оплат для счета
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_paid 
        FROM invoice_payments 
        WHERE invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_paid = $result['total_paid'] ?? 0;
    
    // Получаем сумму счета
    $stmt = $pdo->prepare("SELECT amount FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
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
    $stmt->execute([$total_paid, $new_status, $invoice_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Платеж обновлен',
        'new_total_paid' => $total_paid,
        'new_status' => $new_status
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}