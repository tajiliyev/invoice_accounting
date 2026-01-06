<?php
// pages/dashboard.php

// Сегодняшняя дата
$today = date('Y-m-d');
$week_ago = date('Y-m-d', strtotime('-7 days'));
$month_ago = date('Y-m-d', strtotime('-30 days'));

// Статистика за сегодня
$today_sql = "
    SELECT 
        COUNT(*) as count,
        SUM(amount) as amount,
        SUM(paid_amount) as paid
    FROM invoices 
    WHERE issue_date = ?
";

$today_stmt = $pdo->prepare($today_sql);
$today_stmt->execute([$today]);
$today_stats = $today_stmt->fetch(PDO::FETCH_ASSOC);

// Статистика за неделю
$week_sql = "
    SELECT 
        COUNT(*) as count,
        SUM(amount) as amount,
        SUM(paid_amount) as paid
    FROM invoices 
    WHERE issue_date BETWEEN ? AND ?
";

$week_stmt = $pdo->prepare($week_sql);
$week_stmt->execute([$week_ago, $today]);
$week_stats = $week_stmt->fetch(PDO::FETCH_ASSOC);

// Последние счета
$recent_sql = "
    SELECT i.*, c.name as client_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    ORDER BY i.created_at DESC
    LIMIT 10
";

$recent_invoices = $pdo->query($recent_sql)->fetchAll(PDO::FETCH_ASSOC);

// Предстоящие оплаты (просроченные и скоро просрочивающиеся)
$upcoming_sql = "
    SELECT i.*, c.name as client_name, c.phone,
           DATEDIFF(?, i.issue_date) as days_passed
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.status IN ('pending', 'partial')
    AND i.amount > i.paid_amount
    ORDER BY i.issue_date ASC
    LIMIT 10
";

$upcoming_stmt = $pdo->prepare($upcoming_sql);
$upcoming_stmt->execute([$today]);
$upcoming_invoices = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

// Общая статистика
$overall_sql = "
    SELECT 
        (SELECT COUNT(*) FROM clients) as total_clients,
        (SELECT COUNT(*) FROM invoices) as total_invoices,
        (SELECT SUM(amount) FROM invoices) as total_amount,
        (SELECT SUM(paid_amount) FROM invoices) as total_paid
";

$overall = $pdo->query($overall_sql)->fetch(PDO::FETCH_ASSOC);
?>

<div class="header">
    <h1><i class="fas fa-tachometer-alt"></i> Панель управления</h1>
    <p>Обзор системы учета счетов</p>
</div>

<!-- Быстрая статистика -->
<div class="stats-cards">
    <div class="card">
        <h3>Всего клиентов</h3>
        <div class="number"><?php echo $overall['total_clients'] ?? 0; ?></div>
        <p>в базе данных</p>
    </div>
    
    <div class="card">
        <h3>Всего счетов</h3>
        <div class="number"><?php echo $overall['total_invoices'] ?? 0; ?></div>
        <p>за все время</p>
    </div>
    
    <div class="card">
        <h3>Оборот</h3>
        <div class="number"><?php echo number_format($overall['total_amount'] ?? 0, 2); ?> TMT</div>
        <p>общая сумма</p>
    </div>
    
    <div class="card">
        <h3>Собрано</h3>
        <div class="number"><?php echo number_format($overall['total_paid'] ?? 0, 2); ?> TMT</div>
        <p>оплачено</p>
    </div>
</div>

<!-- Две колонки -->
<div class="dashboard-grid">
    <!-- Последние счета -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Последние счета</h3>
            <a href="?page=invoices" class="btn btn-sm btn-primary">Все счета</a>
        </div>
        <div class="card-body">
            <?php if (empty($recent_invoices)): ?>
                <div class="alert alert-info">Нет счетов</div>
            <?php else: ?>
                <div class="recent-list">
                    <?php foreach ($recent_invoices as $invoice): ?>
                    <div class="recent-item">
                        <div class="recent-info">
                            <div class="recent-title">
                                <strong><?php echo escape($invoice['invoice_number']); ?></strong>
                                <span class="badge badge-<?php echo $invoice['status']; ?>">
                                    <?php echo $invoice['status'] == 'pending' ? 'Ожидание' : 
                                           ($invoice['status'] == 'partial' ? 'Частично' : 'Оплачено'); ?>
                                </span>
                            </div>
                            <div class="recent-meta">
                                <span><?php echo escape($invoice['client_name']); ?></span>
                                <span><?php echo number_format($invoice['amount'], 2); ?> TMT</span>
                            </div>
                        </div>
                        <div class="recent-actions">
                            <a href="?page=invoices&view=<?php echo $invoice['id']; ?>" 
                               class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Предстоящие оплаты -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Требуют внимания</h3>
        </div>
        <div class="card-body">
            <?php if (empty($upcoming_invoices)): ?>
                <div class="alert alert-success">Нет просроченных счетов!</div>
            <?php else: ?>
                <div class="upcoming-list">
                    <?php foreach ($upcoming_invoices as $invoice): 
                        $is_overdue = $invoice['days_passed'] > 30;
                    ?>
                    <div class="upcoming-item <?php echo $is_overdue ? 'overdue' : ''; ?>">
                        <div class="upcoming-info">
                            <div class="upcoming-title">
                                <strong><?php echo escape($invoice['invoice_number']); ?></strong>
                                <?php if ($is_overdue): ?>
                                    <span class="badge badge-danger">Просрочен</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Скоро просрочится</span>
                                <?php endif; ?>
                            </div>
                            <div class="upcoming-meta">
                                <span><?php echo escape($invoice['client_name']); ?></span>
                                <span><?php echo number_format($invoice['amount'], 2); ?> TMT</span>
                            </div>
                            <div class="upcoming-date">
                                Выписан: <?php echo date('d.m.Y', strtotime($invoice['issue_date'])); ?>
                                (<?php echo $invoice['days_passed']; ?> дней назад)
                            </div>
                        </div>
                        <div class="upcoming-actions">
                            <button onclick="addPayment(<?php echo $invoice['id']; ?>)" 
                                    class="btn btn-sm btn-success">
                                <i class="fas fa-money-bill-wave"></i>
                            </button>
                            <?php if ($invoice['phone']): ?>
                                <a href="tel:<?php echo $invoice['phone']; ?>" 
                                   class="btn btn-sm btn-info">
                                    <i class="fas fa-phone"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Быстрые действия -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Быстрые действия</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="?page=invoices" class="quick-action">
                    <i class="fas fa-file-invoice"></i>
                    <span>Создать счет</span>
                </a>
                
                <a href="?page=clients" class="quick-action">
                    <i class="fas fa-user-plus"></i>
                    <span>Добавить клиента</span>
                </a>
                
                <a href="?page=reports" class="quick-action">
                    <i class="fas fa-chart-bar"></i>
                    <span>Смотреть отчеты</span>
                </a>
                
                <a href="ajax/export_report.php?type=excel" class="quick-action">
                    <i class="fas fa-download"></i>
                    <span>Экспорт в Excel</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Статистика за период -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> Статистика по периодам</h3>
        </div>
        <div class="card-body">
            <div class="period-stats">
                <div class="period-stat">
                    <h4>Сегодня</h4>
                    <div class="stat-numbers">
                        <div>
                            <span class="stat-label">Счетов:</span>
                            <span class="stat-value"><?php echo $today_stats['count'] ?? 0; ?></span>
                        </div>
                        <div>
                            <span class="stat-label">Сумма:</span>
                            <span class="stat-value"><?php echo number_format($today_stats['amount'] ?? 0, 2); ?> TMT</span>
                        </div>
                    </div>
                </div>
                
                <div class="period-stat">
                    <h4>За неделю</h4>
                    <div class="stat-numbers">
                        <div>
                            <span class="stat-label">Счетов:</span>
                            <span class="stat-value"><?php echo $week_stats['count'] ?? 0; ?></span>
                        </div>
                        <div>
                            <span class="stat-label">Сумма:</span>
                            <span class="stat-value"><?php echo number_format($week_stats['amount'] ?? 0, 2); ?> TMT</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.dashboard-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.card-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark);
}

.card-body {
    padding: 20px;
}

.recent-list, .upcoming-list {
    max-height: 300px;
    overflow-y: auto;
}

.recent-item, .upcoming-item {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-item:last-child, .upcoming-item:last-child {
    border-bottom: none;
}

.recent-title, .upcoming-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
}

.recent-meta, .upcoming-meta {
    display: flex;
    justify-content: space-between;
    color: #666;
    font-size: 0.9rem;
}

.upcoming-date {
    font-size: 0.8rem;
    color: #999;
    margin-top: 5px;
}

.upcoming-item.overdue {
    background: #fff5f5;
    padding: 10px;
    margin: -10px;
    border-radius: 5px;
}

.badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-pending {
    background: #fff3cd;
    color: #856404;
}

.badge-partial {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-paid {
    background: #d4edda;
    color: #155724;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.quick-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    text-decoration: none;
    color: var(--dark);
    transition: all 0.3s;
}

.quick-action:hover {
    background: var(--secondary);
    color: white;
    transform: translateY(-2px);
}

.quick-action i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.quick-action span {
    font-weight: 500;
    text-align: center;
}

.period-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.period-stat {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
}

.period-stat h4 {
    margin: 0 0 10px 0;
    color: var(--dark);
}

.stat-numbers {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stat-numbers div {
    display: flex;
    justify-content: space-between;
}

.stat-label {
    color: #666;
}

.stat-value {
    font-weight: 600;
    color: var(--primary);
}
</style>