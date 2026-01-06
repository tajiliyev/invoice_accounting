<?php
// pages/view_invoice.php

$invoice_id = $_GET['id'] ?? 0;

if ($invoice_id <= 0) {
    header('Location: ?page=invoices');
    exit();
}

// Получаем информацию о счете
$sql = "
    SELECT 
        i.*,
        c.name as client_name,
        c.phone as client_phone,
        c.email as client_email,
        c.address as client_address,
        DATE_FORMAT(i.issue_date, '%d.%m.%Y') as formatted_issue_date,
        DATE_FORMAT(i.payment_date, '%d.%m.%Y') as formatted_payment_date,
        DATE_FORMAT(i.proxy_date, '%d.%m.%Y') as formatted_proxy_date,
        u.username as created_by_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo '<div class="alert alert-danger">Счет не найден</div>';
    return;
}

// Получаем историю платежей
$payments_sql = "
    SELECT 
        p.*,
        DATE_FORMAT(p.payment_date, '%d.%m.%Y') as formatted_date,
        DATE_FORMAT(p.created_at, '%d.%m.%Y %H:%i') as created_at_formatted,
        u.username as created_by_name
    FROM invoice_payments p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.invoice_id = ?
    ORDER BY p.payment_date DESC, p.created_at DESC
";

$payments_stmt = $pdo->prepare($payments_sql);
$payments_stmt->execute([$invoice_id]);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Рассчитываем оставшуюся сумму
$remaining = $invoice['amount'] - $invoice['paid_amount'];
$payment_percentage = $invoice['amount'] > 0 
    ? round(($invoice['paid_amount'] / $invoice['amount']) * 100, 1) 
    : 0;
?>

<div class="header">
    <div class="header-left">
        <h1><i class="fas fa-file-invoice"></i> Счет №<?php echo escape($invoice['invoice_number']); ?></h1>
        <p>Детальная информация и история платежей</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Печать
        </button>
        <a href="?page=invoices" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад к списку
        </a>
    </div>
</div>

<!-- Карточка информации о счете -->
<div class="invoice-details-card">
    <div class="row">
        <div class="col-md-6">
            <h3><i class="fas fa-info-circle"></i> Информация о счете</h3>
            <table class="details-table">
                <tr>
                    <td><strong>Номер счета:</strong></td>
                    <td><?php echo escape($invoice['invoice_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>Дата выписки:</strong></td>
                    <td><?php echo $invoice['formatted_issue_date']; ?></td>
                </tr>
                <tr>
                    <td><strong>Клиент:</strong></td>
                    <td>
                        <strong><?php echo escape($invoice['client_name']); ?></strong>
                        <?php if ($invoice['client_phone']): ?>
                            <br><small>📞 <?php echo escape($invoice['client_phone']); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Статус:</strong></td>
                    <td>
                        <span class="status status-<?php echo $invoice['status']; ?>">
                            <?php 
                            $status_text = [
                                'pending' => 'Ожидание оплаты',
                                'partial' => 'Частично оплачен',
                                'paid' => 'Полностью оплачен'
                            ];
                            echo $status_text[$invoice['status']];
                            ?>
                        </span>
                    </td>
                </tr>
                <?php if ($invoice['proxy_number']): ?>
                <tr>
                    <td><strong>Доверенность:</strong></td>
                    <td>
                        №<?php echo escape($invoice['proxy_number']); ?>
                        от <?php echo $invoice['formatted_proxy_date']; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="col-md-6">
            <h3><i class="fas fa-money-bill-wave"></i> Финансовая информация</h3>
            <div class="financial-info">
                <div class="amount-row">
                    <span>Общая сумма:</span>
                    <strong class="total-amount"><?php echo number_format($invoice['amount'], 2); ?> TMT</strong>
                </div>
                
                <div class="amount-row">
                    <span>Оплачено:</span>
                    <span class="paid-amount text-success">
                        <?php echo number_format($invoice['paid_amount'], 2); ?> TMT
                    </span>
                </div>
                
                <div class="amount-row">
                    <span>Остаток:</span>
                    <span class="remaining-amount <?php echo $remaining > 0 ? 'text-danger' : 'text-success'; ?>">
                        <strong><?php echo number_format($remaining, 2); ?> TMT</strong>
                    </span>
                </div>
                
                <div class="progress mt-3" style="height: 20px;">
                    <div class="progress-bar bg-success" 
                         role="progressbar" 
                         style="width: <?php echo $payment_percentage; ?>%"
                         aria-valuenow="<?php echo $payment_percentage; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?php echo $payment_percentage; ?>%
                    </div>
                </div>
                <small class="text-muted">
                    <?php echo $payment_percentage; ?>% оплачено
                </small>
            </div>
            
            <div class="mt-4">
                <button class="btn btn-success btn-lg" onclick="addPayment(<?php echo $invoice['id']; ?>)">
                    <i class="fas fa-plus-circle"></i> Внести оплату
                </button>
            </div>
        </div>
    </div>
</div>

<!-- История платежей -->
<div class="payment-history-section">
    <div class="section-header">
        <h3><i class="fas fa-history"></i> История платежей</h3>
        <span class="badge badge-primary"><?php echo count($payments); ?> платежей</span>
    </div>
    
    <?php if (empty($payments)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Нет зарегистрированных платежей
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Дата оплаты</th>
                        <th>Сумма</th>
                        <th>Метод оплаты</th>
                        <th>Примечание</th>
                        <th>Внес</th>
                        <th>Дата записи</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_payments = 0;
                    foreach ($payments as $payment): 
                        $total_payments += $payment['amount'];
                    ?>
                    <tr>
                        <td><?php echo $payment['formatted_date']; ?></td>
                        <td>
                            <strong class="text-success"><?php echo number_format($payment['amount'], 2); ?> TMT</strong>
                        </td>
                        <td>
                            <span class="badge badge-payment-<?php echo $payment['payment_method']; ?>">
                                <?php 
                                $methods = [
                                    'cash' => 'Наличные',
                                    'bank_transfer' => 'Банковский перевод',
                                    'card' => 'Карта',
                                    'online' => 'Онлайн',
                                    'other' => 'Другое'
                                ];
                                echo $methods[$payment['payment_method']] ?? $payment['payment_method'];
                                ?>
                            </span>
                        </td>
                        <td><?php echo escape($payment['notes'] ?: '—'); ?></td>
                        <td><?php echo escape($payment['created_by_name'] ?: 'Система'); ?></td>
                        <td><?php echo $payment['created_at_formatted']; ?></td>
                        <td>
                            <?php if (isAdmin()): ?>
                            <button class="btn btn-sm btn-warning" onclick="editPaymentFromView(<?php echo $payment['id']; ?>, <?php echo $invoice_id; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Итоговая строка -->
                    <tr class="table-active">
                        <td><strong>ИТОГО:</strong></td>
                        <td><strong><?php echo number_format($total_payments, 2); ?> TMT</strong></td>
                        <td colspan="4"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- График платежей -->
        <div class="payment-timeline mt-4">
            <h4><i class="fas fa-chart-line"></i> График платежей</h4>
            <div class="timeline">
                <?php 
                $payments_by_date = [];
                foreach ($payments as $payment) {
                    $date = $payment['formatted_date'];
                    if (!isset($payments_by_date[$date])) {
                        $payments_by_date[$date] = 0;
                    }
                    $payments_by_date[$date] += $payment['amount'];
                }
                ksort($payments_by_date);
                
                $max_amount = max($payments_by_date);
                ?>
                
                <?php foreach ($payments_by_date as $date => $amount): 
                    $percentage = $max_amount > 0 ? ($amount / $max_amount * 100) : 0;
                ?>
                <div class="timeline-item">
                    <div class="timeline-date"><?php echo $date; ?></div>
                    <div class="timeline-bar">
                        <div class="timeline-amount" style="width: <?php echo $percentage; ?>%">
                            <?php echo number_format($amount, 2); ?> TMT
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Кнопки действий -->
<div class="action-buttons mt-4">
    <button class="btn btn-success" onclick="addPayment(<?php echo $invoice_id; ?>)">
        <i class="fas fa-money-bill-wave"></i> Внести новую оплату
    </button>
    
    <a href="?page=invoices&view=<?php echo $invoice_id; ?>" class="btn btn-info">
        <i class="fas fa-receipt"></i> Печать счета
    </a>
    
    <?php if (isAdmin()): ?>
    <button class="btn btn-warning" onclick="editInvoice(<?php echo $invoice_id; ?>)">
        <i class="fas fa-edit"></i> Редактировать счет
    </button>
    
    <button class="btn btn-danger" onclick="deleteInvoice(<?php echo $invoice_id; ?>)">
        <i class="fas fa-trash"></i> Удалить счет
    </button>
    <?php endif; ?>
    
    <button class="btn btn-secondary" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Назад
    </button>
</div>

<style>
.invoice-details-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    border-left: 5px solid var(--primary);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header-left h1 {
    margin: 0;
    color: var(--dark);
}

.header-actions {
    display: flex;
    gap: 10px;
}

.details-table {
    width: 100%;
    border-collapse: collapse;
}

.details-table tr {
    border-bottom: 1px solid #eee;
}

.details-table td {
    padding: 10px 0;
    vertical-align: top;
}

.details-table td:first-child {
    width: 40%;
    color: #666;
    font-weight: 500;
}

.financial-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.amount-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 1.1em;
}

.amount-row:not(:last-child) {
    border-bottom: 1px dashed #dee2e6;
}

.total-amount {
    color: var(--primary);
    font-size: 1.2em;
}

.payment-history-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-top: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.section-header h3 {
    margin: 0;
    color: var(--dark);
}

.timeline {
    margin-top: 20px;
}

.timeline-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.timeline-date {
    width: 100px;
    font-weight: 500;
    color: #666;
}

.timeline-bar {
    flex: 1;
    height: 30px;
    background: #e9ecef;
    border-radius: 15px;
    overflow: hidden;
    position: relative;
}

.timeline-amount {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    color: white;
    display: flex;
    align-items: center;
    padding-left: 15px;
    font-size: 0.9em;
    font-weight: 500;
    transition: width 0.5s ease;
    min-width: 70px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
}

.status {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 600;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-partial {
    background: #d1ecf1;
    color: #0c5460;
}

.status-paid {
    background: #d4edda;
    color: #155724;
}

@media print {
    .header-actions, 
    .action-buttons,
    .payment-history-section .btn {
        display: none !important;
    }
    
    .invoice-details-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    body {
        font-size: 12pt;
    }
}
</style>

<script>
// Функция для редактирования платежа со страницы просмотра
function editPaymentFromView(paymentId, invoiceId) {
    fetch(`ajax/get_payment.php?id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const payment = data.payment;
                
                // Создаем модальное окно редактирования
                const modal = document.createElement('div');
                modal.className = 'modal';
                modal.id = 'editPaymentModalView';
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2><i class="fas fa-edit"></i> Редактировать платеж</h2>
                            <span class="close" onclick="closeModal('editPaymentModalView')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form onsubmit="updatePaymentFromView(event, ${paymentId}, ${invoiceId})">
                                <div class="form-group">
                                    <label>Сумма оплаты *</label>
                                    <input type="number" id="edit_amount" class="form-control" 
                                           value="${payment.amount}" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Дата оплаты *</label>
                                    <input type="date" id="edit_payment_date" class="form-control" 
                                           value="${payment.payment_date}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Метод оплаты</label>
                                    <select id="edit_method" class="form-control">
                                        <option value="cash" ${payment.payment_method == 'cash' ? 'selected' : ''}>Наличные</option>
                                        <option value="bank_transfer" ${payment.payment_method == 'bank_transfer' ? 'selected' : ''}>Банковский перевод</option>
                                        <option value="card" ${payment.payment_method == 'card' ? 'selected' : ''}>Карта</option>
                                        <option value="online" ${payment.payment_method == 'online' ? 'selected' : ''}>Онлайн оплата</option>
                                        <option value="other" ${payment.payment_method == 'other' ? 'selected' : ''}>Другое</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Примечание</label>
                                    <input type="text" id="edit_notes" class="form-control" 
                                           value="${escapeHtml(payment.notes || '')}">
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Сохранить
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="deletePaymentFromView(${paymentId}, ${invoiceId})" 
                                            style="float: right;">
                                        <i class="fas fa-trash"></i> Удалить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                modal.style.display = 'flex';
            }
        });
}

function updatePaymentFromView(event, paymentId, invoiceId) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('payment_id', paymentId);
    formData.append('invoice_id', invoiceId);
    formData.append('payment_amount', document.getElementById('edit_amount').value);
    formData.append('payment_date', document.getElementById('edit_payment_date').value);
    formData.append('payment_method', document.getElementById('edit_method').value);
    formData.append('notes', document.getElementById('edit_notes').value);
    
    fetch('ajax/update_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Платеж обновлен!');
            closeModal('editPaymentModalView');
            location.reload();
        } else {
            alert('Ошибка: ' + data.message);
        }
    });
}

function deletePaymentFromView(paymentId, invoiceId) {
    if (!confirm('Вы уверены, что хотите удалить этот платеж?')) {
        return;
    }
    
    fetch(`ajax/delete_payment.php?id=${paymentId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Платеж удален!');
            location.reload();
        } else {
            alert('Ошибка: ' + data.message);
        }
    });
}
</script>