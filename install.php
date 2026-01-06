<?php
// install.php - запустить один раз для установки
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Создаем базу данных
    $pdo->exec("CREATE DATABASE IF NOT EXISTS invoice_accounting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE invoice_accounting");
    
    // Таблица пользователей
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('cashier', 'admin') DEFAULT 'cashier',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Таблица клиентов
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Таблица счет-фактур
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT PRIMARY KEY AUTO_INCREMENT,
        invoice_number VARCHAR(50) UNIQUE NOT NULL,
        client_id INT NOT NULL,
        proxy_number VARCHAR(100),
        proxy_date DATE,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
        issue_date DATE NOT NULL,
        payment_date DATE NULL,
        paid_amount DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
    )");
    
    // Таблица платежей
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        invoice_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
    )");
    
    // Создаем администратора по умолчанию
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute(['admin', $password_hash, 'admin']);
    
    // Добавляем тестовые данные (опционально)
    $pdo->exec("INSERT IGNORE INTO clients (name, phone) VALUES 
        ('ИП Петров А.А.', '+7 701 123 4567'),
        ('ТОО \"Алматы Трейд\"', '+7 702 987 6543'),
        ('ООО \"КазТех\"', '+7 703 456 7890'),
        ('ИП Сидоров С.С.', '+7 707 111 2233')
    ");
    
    echo "База данных успешно создана!<br>";
    echo "Данные для входа:<br>";
    echo "Логин: admin<br>";
    echo "Пароль: admin123<br>";
    echo "<a href='login.php'>Перейти к входу</a>";
    
} catch(PDOException $e) {
    die("Ошибка установки: " . $e->getMessage());
}
?>