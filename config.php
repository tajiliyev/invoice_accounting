<?php
// config.php
session_start();

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'invoice_accounting');
define('DB_USER', 'root');
define('DB_PASS', '');

// Подключение к базе данных
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Автозагрузка классов (если понадобится)
spl_autoload_register(function($class) {
    require_once "classes/{$class}.php";
});

// Функция для редиректа
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Функция для вывода сообщений
function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Функция для защиты от XSS
function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

?>