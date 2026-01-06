<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

$invoice_id = (int)$_POST['id'];
$invoice_number = trim($_POST['invoice_number']);
$client_name = trim($_POST['client_name']);
$client_phone = trim($_POST['client_phone'] ?? '');
$amount = (float)$_POST['amount'];
$issue_date = $_POST['issue_date'];
$proxy_number = trim($_POST['proxy_number'] ?? '');
$proxy_date = !empty($_POST['proxy_date']) ? $_POST['proxy_date'] : null;

try {
    $pdo->beginTransaction();
    
    // 1. Получаем текущий счет
    $stmt = $pdo->prepare("SELECT client_id FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception("Счет не найден");
    }
    
    $client_id = $invoice['client_id'];
    
    // 2. Обновляем информацию о клиенте
    $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$client_name, $client_phone, $client_id]);
    
    // 3. Обновляем счет
    $sql = "UPDATE invoices SET 
            invoice_number = ?,
            amount = ?,
            issue_date = ?,
            proxy_number = ?,
            proxy_date = ?
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $invoice_number,
        $amount,
        $issue_date,
        $proxy_number ?: null,
        $proxy_date,
        $invoice_id
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Счет успешно обновлен!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $e->getMessage()
    ]);
}
?>