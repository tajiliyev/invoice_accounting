<?php
// ajax/get_payment_history.php
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit();
}

$invoice_id = $_GET['invoice_id'] ?? 0;

if ($invoice_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректный ID счета']);
    exit();
}

// Получаем историю платежей
$sql = "
    SELECT 
        p.*,
        u.username as created_by_name,
        DATE_FORMAT(p.payment_date, '%d.%m.%Y') as formatted_date,
        DATE_FORMAT(p.created_at, '%d.%m.%Y %H:%i') as created_at_formatted
    FROM invoice_payments p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.invoice_id = ?
    ORDER BY p.payment_date DESC, p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем информацию о счете
$invoice_sql = "
    SELECT 
        i.*,
        c.name as client_name,
        DATE_FORMAT(i.issue_date, '%d.%m.%Y') as formatted_issue_date
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.id = ?
";

$invoice_stmt = $pdo->prepare($invoice_sql);
$invoice_stmt->execute([$invoice_id]);
$invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'invoice' => $invoice,
    'payments' => $payments,
    'total_payments' => count($payments)
]);