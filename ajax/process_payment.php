<?php
// ajax/process_payment.php
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit();
}

$invoice_id = $_POST['invoice_id'] ?? 0;
$payment_amount = $_POST['payment_amount'] ?? 0;
$payment_date = $_POST['payment_date'] ?? date('Y-m-d');
$payment_method = $_POST['payment_method'] ?? 'cash';
$notes = $_POST['notes'] ?? '';

if ($invoice_id <= 0 || $payment_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Получаем текущую информацию о счете
    $stmt = $pdo->prepare("SELECT amount, paid_amount, status FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception("Счет не найден");
    }
    
    $current_paid = (float)$invoice['paid_amount'];
    $total_amount = (float)$invoice['amount'];
    $new_paid = $current_paid + $payment_amount;
    
    // Проверяем, не превышает ли оплата сумму счета
    if ($new_paid > $total_amount) {
        throw new Exception("Сумма оплаты превышает общую сумму счета");
    }
    
    // Добавляем запись в историю платежей
    $stmt = $pdo->prepare("
        INSERT INTO invoice_payments 
        (invoice_id, payment_date, amount, payment_method, notes, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_id, 
        $payment_date, 
        $payment_amount, 
        $payment_method, 
        $notes, 
        $_SESSION['user_id']
    ]);
    
    // Обновляем счет
    $new_status = 'partial';
    if ($new_paid == 0) {
        $new_status = 'pending';
    } elseif ($new_paid >= $total_amount) {
        $new_status = 'paid';
        $new_paid = $total_amount; // Корректируем, если оплата превышает
    }
    
    $stmt = $pdo->prepare("
        UPDATE invoices 
        SET paid_amount = ?, 
            status = ?, 
            payment_date = CASE WHEN ? = 'paid' THEN ? ELSE payment_date END,
            payment_method = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $new_paid, 
        $new_status, 
        $new_status, 
        $payment_date,
        $payment_method,
        $invoice_id
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Оплата успешно зарегистрирована',
        'invoice_id' => $invoice_id,
        'new_status' => $new_status,
        'new_paid' => $new_paid,
        'remaining' => $total_amount - $new_paid,
        'payment_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}