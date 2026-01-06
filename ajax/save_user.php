<?php
// ajax/save_user.php - ИСПРАВЛЕННАЯ ВЕРСИЯ

// ОТКЛЮЧАЕМ ВЫВОД NOTICE
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// УСТАНАВЛИВАЕМ ЗАГОЛОВОК JSON ПЕРВЫМ ДЕЛОМ
header('Content-Type: application/json; charset=utf-8');

// ПОДКЛЮЧАЕМ КОНФИГ (СЕССИЯ УЖЕ ЗАПУСКАЕТСЯ В config.php)
$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    die(json_encode(['success' => false, 'message' => 'Config file not found']));
}

require_once $config_path;

// ПОДКЛЮЧАЕМ AUTH ДЛЯ ФУНКЦИИ isAdmin()
$auth_path = dirname(__DIR__) . '/includes/auth.php';
if (file_exists($auth_path)) {
    require_once $auth_path;
}

// ПРОВЕРЯЕМ АВТОРИЗАЦИЮ
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Не авторизован']));
}

// ПРОВЕРЯЕМ ПРАВА АДМИНИСТРАТОРА
if (!function_exists('isAdmin')) {
    die(json_encode(['success' => false, 'message' => 'Системная ошибка: функция isAdmin не найдена']));
}

if (!isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Доступ запрещен. Требуются права администратора']));
}

// ПРОВЕРЯЕМ МЕТОД ЗАПРОСА
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Неверный метод запроса']));
}

// ПОЛУЧАЕМ ДАННЫЕ
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$role = $_POST['role'] ?? '';

// ВАЛИДАЦИЯ
if (empty($username) || empty($password) || empty($role)) {
    die(json_encode(['success' => false, 'message' => 'Все поля обязательны']));
}

if (strlen($username) < 3) {
    die(json_encode(['success' => false, 'message' => 'Имя пользователя должно быть не менее 3 символов']));
}

if ($password !== $password_confirm) {
    die(json_encode(['success' => false, 'message' => 'Пароли не совпадают']));
}

if (strlen($password) < 4) {
    die(json_encode(['success' => false, 'message' => 'Пароль должен быть не менее 4 символов']));
}

try {
    // ПРОВЕРЯЕМ, СУЩЕСТВУЕТ ЛИ ПОЛЬЗОВАТЕЛЬ
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Пользователь с таким именем уже существует']));
    }
    
    // ХЭШИРУЕМ ПАРОЛЬ
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // СОЗДАЕМ ПОЛЬЗОВАТЕЛЯ
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $password_hash, $role]);
    
    $user_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Пользователь успешно создан',
        'user_id' => $user_id,
        'username' => $username
    ]);
    
} catch (Exception $e) {
    error_log("Save user error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при создании пользователя: ' . $e->getMessage()
    ]);
}

exit();
?>