<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Учет счет-фактур</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/fonts/all.min.css">
</head>
<body>
    <?php
    // Инициализируем соединение если оно еще не инициализировано
    if (!isset($pdo)) {
        require_once 'config.php';
    }
    
    // Инициализируем администратора при первом запуске
    initializeAdmin();
    ?>