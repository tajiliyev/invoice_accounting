<?php
// ajax/save_client.php - ИСПРАВЛЕННАЯ ВЕРСИЯ

// ОТКЛЮЧАЕМ ВЫВОД NOTICE И WARNING
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0); // ВМЕСТО 1 СТАВИМ 0

header('Content-Type: application/json; charset=utf-8');

$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    die(json_encode(['success' => false, 'message' => 'Config file not found']));
}

require_once $config_path;

$auth_path = dirname(__DIR__) . '/includes/auth.php';
if (file_exists($auth_path)) {
    require_once $auth_path;
}

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Не авторизован']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Неверный метод']));
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (empty($name)) {
    die(json_encode(['success' => false, 'message' => 'Имя обязательно']));
}

try {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ?");
    $stmt->execute([$name]);
    
    if ($stmt->fetch()) {
        die(json_encode([
            'success' => false, 
            'message' => 'Клиент с таким именем уже существует'
        ]));
    }
    
    $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email, address, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email, $address, $notes]);
    
    $id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Клиент успешно добавлен',
        'client_id' => $id,
        'client_name' => $name
    ]);
    
} catch (Exception $e) {
    error_log("Save client error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $e->getMessage()
    ]);
}

exit();
?>