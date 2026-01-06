<?php
// ajax/save_invoice.php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получаем данные
$invoice_number = trim($_POST['invoice_number']);
$client_name = trim($_POST['client_name']);
$client_phone = trim($_POST['client_phone'] ?? '');
$amount = (float)$_POST['amount'];
$issue_date = $_POST['issue_date'];
$proxy_number = trim($_POST['proxy_number'] ?? '');
$proxy_date = !empty($_POST['proxy_date']) ? $_POST['proxy_date'] : null;

try {
    $pdo->beginTransaction();
    
    // 1. Генерируем номер счета если он пустой
    if (empty($invoice_number)) {
        // Ищем максимальный номер в формате "01", "02" и т.д.
        $stmt = $pdo->query("SELECT invoice_number FROM invoices ORDER BY id DESC");
        $max_number = 0;
        
        while ($row = $stmt->fetch()) {
            if (preg_match('/^(\d+)$/', $row['invoice_number'], $matches)) {
                $num = intval($matches[1]);
                if ($num > $max_number) {
                    $max_number = $num;
                }
            }
        }
        
        $invoice_number = str_pad($max_number + 1, 2, '0', STR_PAD_LEFT);
    }
    
    // 2. Проверяем уникальность номера счета
    $stmt = $pdo->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
    $stmt->execute([$invoice_number]);
    if ($stmt->fetch()) {
        // Если номер существует, генерируем следующий
        $max_number = intval($invoice_number);
        
        // Ищем следующий свободный номер
        for ($i = $max_number + 1; $i <= $max_number + 10; $i++) {
            $test_number = str_pad($i, 2, '0', STR_PAD_LEFT);
            $stmt->execute([$test_number]);
            if (!$stmt->fetch()) {
                $invoice_number = $test_number;
                break;
            }
        }
    }
    
    // 3. Ищем или создаем клиента
    $client_id = null;
    
    if (!empty($client_phone)) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE phone = ?");
        $stmt->execute([$client_phone]);
        $client = $stmt->fetch();
        
        if ($client) {
            $client_id = $client['id'];
            // Обновляем имя если изменилось
            $stmt = $pdo->prepare("UPDATE clients SET name = ? WHERE id = ?");
            $stmt->execute([$client_name, $client_id]);
        }
    }
    
    // Если клиент не найден по телефону или телефон не указан
    if (!$client_id) {
        // Пробуем найти по имени (точное совпадение)
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ?");
        $stmt->execute([$client_name]);
        $client = $stmt->fetch();
        
        if ($client) {
            $client_id = $client['id'];
            // Обновляем телефон если он изменился
            if ($client_phone) {
                $stmt = $pdo->prepare("UPDATE clients SET phone = ? WHERE id = ?");
                $stmt->execute([$client_phone, $client_id]);
            }
        } else {
            // Создаем нового клиента
            $stmt = $pdo->prepare("INSERT INTO clients (name, phone) VALUES (?, ?)");
            $stmt->execute([$client_name, $client_phone]);
            $client_id = $pdo->lastInsertId();
        }
    }
    
    // 4. Создаем счет
    $sql = "INSERT INTO invoices (invoice_number, client_id, amount, issue_date, 
            proxy_number, proxy_date, status, paid_amount) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 0)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $invoice_number,
        $client_id,
        $amount,
        $issue_date,
        $proxy_number ?: null,
        $proxy_date
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Счет успешно создан',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number,
        'client_name' => $client_name,
        'amount' => $amount
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Invoice save error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при сохранении: ' . $e->getMessage()
    ]);
}
?>