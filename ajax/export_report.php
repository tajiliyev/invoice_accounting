<?php
// ajax/export_report.php
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    die('Доступ запрещен');
}

// Получение параметров
$type = isset($_GET['type']) ? $_GET['type'] : 'excel';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Получение данных
$sql = "
    SELECT 
        i.invoice_number as 'Номер счета',
        DATE_FORMAT(i.issue_date, '%d.%m.%Y') as 'Дата выписки',
        c.name as 'Клиент',
        c.phone as 'Телефон',
        i.amount as 'Сумма',
        i.paid_amount as 'Оплачено',
        (i.amount - i.paid_amount) as 'Задолженность',
        CASE i.status 
            WHEN 'pending' THEN 'Ожидание'
            WHEN 'partial' THEN 'Частично'
            WHEN 'paid' THEN 'Оплачено'
        END as 'Статус',
        i.proxy_number as 'Доверенность №',
        DATE_FORMAT(i.proxy_date, '%d.%m.%Y') as 'Дата доверенности',
        DATE_FORMAT(i.payment_date, '%d.%m.%Y') as 'Дата оплаты'
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.issue_date BETWEEN ? AND ?
    ORDER BY i.issue_date DESC, i.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Добавляем итоговую строку
$total_sql = "
    SELECT 
        COUNT(*) as total_count,
        SUM(amount) as total_amount,
        SUM(paid_amount) as total_paid,
        SUM(amount - paid_amount) as total_debt
    FROM invoices 
    WHERE issue_date BETWEEN ? AND ?
";

$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute([$start_date, $end_date]);
$totals = $total_stmt->fetch(PDO::FETCH_ASSOC);

// Создание Excel файла
if ($type == 'excel') {
    // Заголовки для Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Начинаем вывод
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 5px; text-align: left; }';
    echo '.header { background-color: #f2f2f2; font-weight: bold; }';
    echo '.total { background-color: #e8f5e8; font-weight: bold; }';
    echo '.debt { color: #d32f2f; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Заголовок отчета
    echo '<h2>Отчет по счетам</h2>';
    echo '<p>Период: с ' . date('d.m.Y', strtotime($start_date)) . ' по ' . date('d.m.Y', strtotime($end_date)) . '</p>';
    echo '<p>Дата формирования: ' . date('d.m.Y H:i') . '</p>';
    echo '<br>';
    
    // Таблица со счетами
    echo '<table>';
    
    // Заголовки таблицы
    echo '<tr class="header">';
    if (!empty($invoices)) {
        foreach (array_keys($invoices[0]) as $header) {
            echo '<th>' . $header . '</th>';
        }
    }
    echo '</tr>';
    
    // Данные
    foreach ($invoices as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            // Форматируем денежные значения
            if (in_array($cell, array_keys($row))) {
                $key = array_search($cell, $row);
                if (in_array($key, ['Сумма', 'Оплачено', 'Задолженность'])) {
                    $cell = number_format($cell, 2) . ' TMT';
                }
            }
            echo '<td>' . $cell . '</td>';
        }
        echo '</tr>';
    }
    
    // Итоговая строка
    echo '<tr class="total">';
    echo '<td colspan="4"><strong>ИТОГО:</strong></td>';
    echo '<td>' . number_format($totals['total_amount'], 2) . ' TMT</td>';
    echo '<td>' . number_format($totals['total_paid'], 2) . ' TMT</td>';
    echo '<td class="debt">' . number_format($totals['total_debt'], 2) . ' TMT</td>';
    echo '<td colspan="5"></td>';
    echo '</tr>';
    
    echo '</table>';
    
    echo '</body>';
    echo '</html>';
    
} elseif ($type == 'pdf') {
    // Для PDF экспорта нужна библиотека вроде TCPDF или DomPDF
    // Вот упрощенный вариант, который можно доработать
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.pdf"');
    
    // Простой HTML для PDF (можно подключить TCPDF)
    echo '<h1>Отчет по счетам (PDF)</h1>';
    echo '<p>Период: с ' . date('d.m.Y', strtotime($start_date)) . ' по ' . date('d.m.Y', strtotime($end_date)) . '</p>';
    echo '<p>Это PDF экспорт. Для полной функциональности установите библиотеку TCPDF.</p>';
}

exit();