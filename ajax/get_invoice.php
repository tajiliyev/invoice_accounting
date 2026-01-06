<?php
require_once '../config.php';

$invoice_id = (int)$_GET['id'] ?? 0;

$sql = "SELECT i.*, c.name as client_name 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        WHERE i.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if ($invoice) {
    echo json_encode([
        'success' => true,
        'data' => $invoice
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Счет не найден'
    ]);
}
?>