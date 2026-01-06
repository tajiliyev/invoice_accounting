<?php
// install_update.php - запустить один раз для обновления
require_once 'config.php';

try {
    // Добавляем поле created_at если его нет
    $pdo->exec("ALTER TABLE users 
                ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    echo "База данных успешно обновлена!<br>";
    echo "<a href='index.php'>Вернуться в систему</a>";
    
} catch (Exception $e) {
    echo "Ошибка обновления: " . $e->getMessage();
}
?>