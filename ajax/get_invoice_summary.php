<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit();
}

$invoice_id = $_GET['id'] ?? 0;

$sql = "
    SELECT 
        i.*,
        c.name as client_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

$payments_sql = "
    SELECT 
        p.*,
        DATE_FORMAT(p.payment_date, '%d.%m.%Y') as formatted_date
    FROM invoice_payments p
    WHERE p.invoice_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 5
";

$payments_stmt = $pdo->prepare($payments_sql);
$payments_stmt->execute([$invoice_id]);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => !!$invoice,
    'invoice' => $invoice,
    'payments' => $payments
]);