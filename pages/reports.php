<?php
// pages/reports.php

// Период для отчетов
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Общая статистика
$total_sql = "
    SELECT 
        COUNT(*) as total_invoices,
        SUM(amount) as total_amount,
        SUM(paid_amount) as total_paid,
        AVG(amount) as avg_invoice
    FROM invoices 
    WHERE issue_date BETWEEN ? AND ?
";

$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute([$start_date, $end_date]);
$totals = $total_stmt->fetch(PDO::FETCH_ASSOC);

// Статистика по статусам
$status_sql = "
    SELECT 
        status,
        COUNT(*) as count,
        SUM(amount) as amount,
        SUM(paid_amount) as paid
    FROM invoices 
    WHERE issue_date BETWEEN ? AND ?
    GROUP BY status
";

$status_stmt = $pdo->prepare($status_sql);
$status_stmt->execute([$start_date, $end_date]);
$status_stats = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

// Топ клиентов по задолженности
$debtors_sql = "
    SELECT 
        c.id,
        c.name,
        c.phone,
        COUNT(i.id) as invoice_count,
        SUM(i.amount) as total_amount,
        SUM(i.paid_amount) as total_paid,
        (SUM(i.amount) - SUM(i.paid_amount)) as debt
    FROM clients c
    JOIN invoices i ON c.id = i.client_id
    WHERE i.status IN ('pending', 'partial')
    AND (i.amount - i.paid_amount) > 0
    GROUP BY c.id
    HAVING debt > 0
    ORDER BY debt DESC
    LIMIT 10
";

$debtors = $pdo->query($debtors_sql)->fetchAll(PDO::FETCH_ASSOC);

// Статистика по месяцам
$monthly_sql = "
    SELECT 
        DATE_FORMAT(issue_date, '%Y-%m') as month,
        COUNT(*) as invoice_count,
        SUM(amount) as total_amount,
        SUM(paid_amount) as total_paid
    FROM invoices
    WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
    ORDER BY month DESC
";

$monthly_stats = $pdo->query($monthly_sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="header">
    <h1><i class="fas fa-chart-bar"></i> Отчеты</h1>
    <p>Аналитика и статистика по счетам</p>
</div>

<!-- Фильтры периода -->
<div class="filter-bar">
    <form method="GET" action="" class="date-filter">
        <input type="hidden" name="page" value="reports">
        
        <div class="form-group">
            <label>Период с:</label>
            <input type="date" name="start_date" class="form-control" 
                   value="<?php echo $start_date; ?>">
        </div>
        
        <div class="form-group">
            <label>по:</label>
            <input type="date" name="end_date" class="form-control" 
                   value="<?php echo $end_date; ?>">
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Применить
        </button>
        
        <a href="?page=reports" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Сбросить
        </a>
    </form>
</div>

<!-- Общая статистика -->
<div class="stats-cards">
    <div class="card">
        <h3>Всего счетов</h3>
        <div class="number"><?php echo $totals['total_invoices'] ?? 0; ?></div>
        <p>за период</p>
    </div>
    
    <div class="card">
        <h3>Общая сумма</h3>
        <div class="number"><?php echo number_format($totals['total_amount'] ?? 0, 2); ?> TMT</div>
        <p>выставлено</p>
    </div>
    
    <div class="card">
        <h3>Оплачено</h3>
        <div class="number"><?php echo number_format($totals['total_paid'] ?? 0, 2); ?> TMT</div>
        <p>получено</p>
    </div>
    
    <div class="card debt">
        <h3>Задолженность</h3>
        <div class="number">
            <?php 
            $debt = ($totals['total_amount'] ?? 0) - ($totals['total_paid'] ?? 0);
            echo number_format($debt, 2); ?> TMT
        </div>
        <p>ожидает оплаты</p>
    </div>
</div>

<!-- Две колонки с отчетами -->
<div class="report-grid">
    <!-- Статистика по статусам -->
    <div class="report-card">
        <div class="report-header">
            <h3><i class="fas fa-chart-pie"></i> Статистика по статусам</h3>
        </div>
        <div class="report-body">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Статус</th>
                        <th>Количество</th>
                        <th>Сумма</th>
                        <th>Оплачено</th>
                        <th>Задолженность</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_stats as $stat): 
                        $debt = $stat['amount'] - $stat['paid'];
                    ?>
                    <tr>
                        <td>
                            <span class="status status-<?php echo $stat['status']; ?>">
                                <?php 
                                $status_text = [
                                    'pending' => 'Ожидание',
                                    'partial' => 'Частично',
                                    'paid' => 'Оплачено'
                                ];
                                echo $status_text[$stat['status']];
                                ?>
                            </span>
                        </td>
                        <td><?php echo $stat['count']; ?></td>
                        <td><?php echo number_format($stat['amount'], 2); ?> TMT</td>
                        <td><?php echo number_format($stat['paid'], 2); ?> TMT</td>
                        <td>
                            <?php if ($debt > 0): ?>
                                <span class="text-danger"><?php echo number_format($debt, 2); ?> TMT</span>
                            <?php else: ?>
                                <span class="text-success">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($status_stats)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Нет данных за выбранный период</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Топ должников -->
    <div class="report-card">
        <div class="report-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Топ 10 должников</h3>
        </div>
        <div class="report-body">
            <?php if (!empty($debtors)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Клиент</th>
                            <th>Телефон</th>
                            <th>Счетов</th>
                            <th>Задолженность</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debtors as $debtor): ?>
                        <tr>
                            <td>
                                <strong><?php echo escape($debtor['name']); ?></strong>
                            </td>
                            <td>
                                <?php if ($debtor['phone']): ?>
                                    <a href="tel:<?php echo $debtor['phone']; ?>">
                                        <?php echo escape($debtor['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $debtor['invoice_count']; ?></td>
                            <td>
                                <span class="text-danger">
                                    <strong><?php echo number_format($debtor['debt'], 2); ?> TMT</strong>
                                </span>
                            </td>
                            <td>
                                <a href="?page=invoices&client_id=<?php echo $debtor['id']; ?>" 
                                   class="btn btn-sm btn-info" title="Счета">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Все клиенты оплатили счета!
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ежемесячная статистика -->
    <div class="report-card full-width">
        <div class="report-header">
            <h3><i class="fas fa-chart-line"></i> Статистика за 6 месяцев</h3>
        </div>
        <div class="report-body">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Месяц</th>
                        <th>Количество счетов</th>
                        <th>Общая сумма</th>
                        <th>Оплачено</th>
                        <th>Задолженность</th>
                        <th>Процент оплаты</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_stats as $month): 
                        $debt = $month['total_amount'] - $month['total_paid'];
                        $payment_percentage = $month['total_amount'] > 0 
                            ? round(($month['total_paid'] / $month['total_amount']) * 100, 1)
                            : 0;
                    ?>
                    <tr>
                        <td>
                            <?php 
                            $month_name = DateTime::createFromFormat('Y-m', $month['month'])->format('F Y');
                            echo $month_name;
                            ?>
                        </td>
                        <td><?php echo $month['invoice_count']; ?></td>
                        <td><?php echo number_format($month['total_amount'], 2); ?> TMT</td>
                        <td><?php echo number_format($month['total_paid'], 2); ?> TMT</td>
                        <td>
                            <?php if ($debt > 0): ?>
                                <span class="text-danger"><?php echo number_format($debt, 2); ?> TMT</span>
                            <?php else: ?>
                                <span class="text-success">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="progress">
                                <div class="progress-bar" 
                                     style="width: <?php echo $payment_percentage; ?>%">
                                    <?php echo $payment_percentage; ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($monthly_stats)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Нет данных за последние 6 месяцев</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="report-card">
    <div class="report-header">
        <h3><i class="fas fa-money-check-alt"></i> Детализация платежей</h3>
    </div>
    <div class="report-body">
        <?php
        $payment_sql = "
            SELECT 
                DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                COUNT(p.id) as payment_count,
                SUM(p.amount) as total_payments,
                p.payment_method,
                COUNT(DISTINCT p.invoice_id) as invoices_count
            FROM invoice_payments p
            WHERE p.payment_date BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m'), p.payment_method
            ORDER BY month DESC, total_payments DESC
        ";
        
        $payment_stmt = $pdo->prepare($payment_sql);
        $payment_stmt->execute([$start_date, $end_date]);
        $payments_stats = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <table class="report-table">
            <thead>
                <tr>
                    <th>Месяц</th>
                    <th>Метод оплаты</th>
                    <th>Кол-во платежей</th>
                    <th>Сумма</th>
                    <th>Кол-во счетов</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments_stats as $stat): ?>
                <tr>
                    <td><?php echo DateTime::createFromFormat('Y-m', $stat['month'])->format('F Y'); ?></td>
                    <td>
                        <span class="badge badge-payment-<?php echo $stat['payment_method']; ?>">
                            <?php 
                            $methods = [
                                'cash' => 'Наличные',
                                'bank_transfer' => 'Банковский перевод',
                                'card' => 'Карта',
                                'online' => 'Онлайн'
                            ];
                            echo $methods[$stat['payment_method']] ?? $stat['payment_method'];
                            ?>
                        </span>
                    </td>
                    <td><?php echo $stat['payment_count']; ?></td>
                    <td><?php echo number_format($stat['total_payments'], 2); ?> TMT</td>
                    <td><?php echo $stat['invoices_count']; ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($payments_stats)): ?>
                <tr>
                    <td colspan="5" class="text-center">Нет данных о платежах</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Экспорт отчетов -->
<div class="export-actions">
    <h3><i class="fas fa-download"></i> Экспорт отчетов</h3>
    <div class="btn-group">
        <a href="ajax/export_report.php?type=excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
           class="btn btn-success">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="ajax/export_report.php?type=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
           class="btn btn-danger">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
        <button onclick="printReport()" class="btn btn-info">
            <i class="fas fa-print"></i> Печать
        </button>
    </div>
</div>

<style>
.report-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.report-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.report-card.full-width {
    grid-column: 1 / -1;
}

.report-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.report-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark);
}

.report-body {
    padding: 20px;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th {
    background: #f8f9fa;
    padding: 10px;
    font-weight: 600;
    color: var(--dark);
    text-align: left;
}

.report-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.filter-bar {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.date-filter {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.export-actions {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
}

.export-actions h3 {
    margin-bottom: 15px;
}

.btn-group {
    display: flex;
    gap: 10px;
}

.progress {
    height: 20px;
    background: #eee;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: var(--success);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}
</style>

<script>
function printReport() {
    window.print();
}
</script>