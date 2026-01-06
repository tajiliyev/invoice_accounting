<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit();
}

$payment_id = $_GET['id'] ?? 0;

$sql = "SELECT * FROM invoice_payments WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => !!$payment,
    'payment' => $payment
]);