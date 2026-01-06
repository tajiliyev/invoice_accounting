<?php
// pages/invoices.php
$order = isset($_GET['order']) && $_GET['order'] == 'old' ? 'ASC' : 'DESC';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$client_filter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

// Формируем SQL запрос
$sql = "SELECT i.*, c.name as client_name 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND i.status = ?";
    $params[] = $status_filter;
}

if ($client_filter > 0) {
    $sql .= " AND i.client_id = ?";
    $params[] = $client_filter;
}

$sql .= " ORDER BY i.issue_date $order, i.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем клиентов для фильтра
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();
?>

<div class="header">
    <h1><i class="fas fa-file-invoice"></i> Счета-фактуры</h1>
    <p>Управление счетами и отслеживание оплат</p>
</div>

<!-- Статистика -->
<div class="stats-cards">
    <?php
    $total_amount = array_sum(array_column($invoices, 'amount'));
    $total_paid = array_sum(array_column($invoices, 'paid_amount'));
    $pending_count = count(array_filter($invoices, fn($inv) => $inv['status'] == 'pending'));
    $partial_count = count(array_filter($invoices, fn($inv) => $inv['status'] == 'partial'));
    $paid_count = count(array_filter($invoices, fn($inv) => $inv['status'] == 'paid'));
    ?>
    
    <div class="card">
        <h3>Всего счетов</h3>
        <div class="number"><?php echo count($invoices); ?></div>
    </div>
    
    <div class="card pending">
        <h3>Ожидают оплаты</h3>
        <div class="number"><?php echo $pending_count; ?></div>
    </div>
    
    <div class="card paid">
        <h3>Оплачены</h3>
        <div class="number"><?php echo $paid_count; ?></div>
    </div>
    
    <div class="card debt">
        <h3>Общая задолженность</h3>
        <div class="number"><?php echo number_format($total_amount - $total_paid, 2); ?> TMT</div>
    </div>
</div>

<!-- Панель управления -->
<div class="action-bar">
    <button class="btn btn-primary" onclick="openAddInvoiceModal()">
        <i class="fas fa-plus"></i> Новый счет (автономер)
    </button>
    
    <div class="filters">
        <select id="clientFilter" class="form-control" onchange="filterInvoices()">
            <option value="">Все клиенты</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?php echo $client['id']; ?>" 
                    <?php echo ($client_filter == $client['id']) ? 'selected' : ''; ?>>
                    <?php echo escape($client['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select id="statusFilter" class="form-control" onchange="filterInvoices()">
            <option value="">Все статусы</option>
            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Ожидание</option>
            <option value="partial" <?php echo ($status_filter == 'partial') ? 'selected' : ''; ?>>Частично</option>
            <option value="paid" <?php echo ($status_filter == 'paid') ? 'selected' : ''; ?>>Оплачено</option>
        </select>
        
        <select id="orderFilter" class="form-control" onchange="filterInvoices()">
            <option value="new" <?php echo ($order == 'DESC') ? 'selected' : ''; ?>>Сначала новые</option>
            <option value="old" <?php echo ($order == 'ASC') ? 'selected' : ''; ?>>Сначала старые</option>
        </select>
        
        <input type="text" id="searchBox" class="form-control" placeholder="Поиск..." onkeyup="searchTable()">
    </div>
</div>

<!-- Таблица счетов -->
<div class="table-container">
    <table id="invoicesTable">
        <thead>
            <tr>
                <th>Номер счета</th>
                <th>Доверенность</th>
                <th>Клиент</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th>Выписка</th>
                <th>Оплата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="8" class="text-center">Нет счетов</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): 
                    $remaining = $invoice['amount'] - $invoice['paid_amount'];
                ?>
                <tr data-id="<?php echo $invoice['id']; ?>">
                    <td>
                        <strong class="invoice-number"><?php echo escape($invoice['invoice_number']); ?></strong>
                    </td>
                    <td>
                        <?php if ($invoice['proxy_number']): ?>
                            №<?php echo escape($invoice['proxy_number']); ?><br>
                            <small><?php echo date('d.m.Y', strtotime($invoice['proxy_date'])); ?></small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo escape($invoice['client_name']); ?></td>
                    <td>
                        <div><strong><?php echo number_format($invoice['amount'], 2); ?> TMT</strong></div>
                        <?php if ($invoice['status'] == 'partial'): ?>
                            <small>Оплачено: <?php echo number_format($invoice['paid_amount'], 2); ?> TMT</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status status-<?php echo $invoice['status']; ?>">
                            <?php 
                            $status_text = [
                                'pending' => 'Ожидание',
                                'partial' => 'Частично',
                                'paid' => 'Оплачено'
                            ];
                            echo $status_text[$invoice['status']];
                            ?>
                        </span>
                    </td>
                    <td><?php echo date('d.m.Y', strtotime($invoice['issue_date'])); ?></td>
                    <td>
                        <?php if ($invoice['payment_date']): ?>
                            <?php echo date('d.m.Y', strtotime($invoice['payment_date'])); ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <button class="btn btn-sm btn-success" 
                                onclick="addPayment(<?php echo $invoice['id']; ?>)"
                                title="Внести оплату">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                        <button class="btn btn-sm btn-info" 
                                onclick="openPaymentHistory(<?php echo $invoice['id']; ?>)"
                                title="История платежей">
                            <i class="fas fa-history"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" 
                                onclick="editInvoice(<?php echo $invoice['id']; ?>)"
                                title="Редактировать">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (isAdmin()): ?>
                        <button class="btn btn-sm btn-danger" 
                                onclick="deleteInvoice(<?php echo $invoice['id']; ?>)"
                                title="Удалить">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Модальное окно добавления счета -->
<div id="addInvoice" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Новый счет-фактура</h2>
            <span class="close" onclick="closeModal('addInvoice'); resetInvoiceForm()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="invoiceForm" onsubmit="saveInvoice(event)">
                <div class="form-group">
                    <label>Номер счета</label>
                    <div class="input-with-suggestion">
                        <input type="text" name="invoice_number" id="invoiceNumber" 
                               class="form-control" 
                               placeholder="Нажмите для получения номера"
                               readonly
                               style="background-color: #f8f9fa; cursor: pointer;">
                        <div class="suggestion-hint" id="invoiceNumberHint">
                            Нажмите для автоматического номера
                        </div>
                    </div>
                    <small class="form-text text-muted">
                        Оставьте поле пустым для автоматической нумерации (01, 02, 03...)
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Клиент *</label>
                    <input type="text" name="client_name" id="clientName" 
                           class="form-control" required
                           placeholder="Начните вводить имя клиента..."
                           autocomplete="off">
                    <div id="clientSuggestions" class="suggestions"></div>
                </div>
                
                <div class="form-group">
                    <label>Телефон клиента</label>
                    <input type="tel" name="client_phone" id="clientPhone" 
                           class="form-control" 
                           placeholder="+7 (999) 123-45-67">
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Сумма *</label>
                            <input type="number" name="amount" id="invoiceAmount" 
                                   class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label>Дата выписки *</label>
                            <input type="date" name="issue_date" id="invoiceDate" 
                                   class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Номер доверенности</label>
                            <input type="text" name="proxy_number" class="form-control">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label>Дата доверенности</label>
                            <input type="date" name="proxy_date" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="autoNumber" checked>
                        <label class="form-check-label" for="autoNumber">
                            Использовать автоматическую нумерацию
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Сохранить счет
                    </button>
                    <button type="button" class="btn btn-secondary btn-block mt-2" 
                            onclick="resetInvoiceForm()">
                        <i class="fas fa-redo"></i> Сбросить форму
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно оплаты с историей -->
<div id="paymentModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2><i class="fas fa-money-bill-wave"></i> Внесение оплаты</h2>
            <span class="close" onclick="closeModal('paymentModal')">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Информация о счете -->
            <div id="paymentInfo" class="payment-info mb-4"></div>
            
            <!-- История платежей -->
            <div class="payment-history-section mb-4" id="paymentHistorySection" style="display: none;">
                <h4><i class="fas fa-history"></i> История платежей</h4>
                <div class="table-responsive">
                    <table class="table" id="paymentHistoryTable">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Сумма</th>
                                <th>Метод</th>
                                <th>Примечание</th>
                                <th>Внес</th>
                                <th>Дата записи</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="paymentHistoryBody">
                            <!-- История загрузится через AJAX -->
                        </tbody>
                    </table>
                </div>
                <div id="noPaymentsMessage" style="display: none;">
                    <div class="alert alert-info">Нет платежей</div>
                </div>
            </div>
            
            <!-- Форма нового платежа -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-plus-circle"></i> Новый платеж</h5>
                </div>
                <div class="card-body">
                    <form id="paymentForm" onsubmit="processPayment(event)">
                        <input type="hidden" id="invoice_id" name="invoice_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Сумма оплаты *</label>
                                    <input type="number" id="payment_amount" name="payment_amount" 
                                           class="form-control" step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Дата оплаты *</label>
                                    <input type="date" id="payment_date" name="payment_date" 
                                           class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Метод оплаты</label>
                                    <select name="payment_method" class="form-control">
                                        <option value="cash">Наличные</option>
                                        <option value="bank_transfer">Банковский перевод</option>
                                        <option value="card">Карта</option>
                                        <option value="online">Онлайн оплата</option>
                                        <option value="other">Другое</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Примечание</label>
                                    <input type="text" name="notes" class="form-control" 
                                           placeholder="Например: аванс, частичная оплата...">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-check"></i> Зарегистрировать оплату
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования платежа -->
<div id="editPaymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Редактировать платеж</h2>
            <span class="close" onclick="closeModal('editPaymentModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editPaymentForm" onsubmit="updatePayment(event)">
                <input type="hidden" id="edit_payment_id" name="payment_id">
                <input type="hidden" id="edit_invoice_id" name="invoice_id">
                
                <div class="form-group">
                    <label>Сумма оплаты *</label>
                    <input type="number" id="edit_payment_amount" name="payment_amount" 
                           class="form-control" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label>Дата оплаты *</label>
                    <input type="date" id="edit_payment_date" name="payment_date" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Метод оплата</label>
                    <select id="edit_payment_method" name="payment_method" class="form-control">
                        <option value="cash">Наличные</option>
                        <option value="bank_transfer">Банковский перевод</option>
                        <option value="card">Карта</option>
                        <option value="online">Онлайн оплата</option>
                        <option value="other">Другое</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Примечание</label>
                    <input type="text" id="edit_notes" name="notes" class="form-control">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить изменения
                    </button>
                    <button type="button" class="btn btn-danger" onclick="deletePayment()" 
                            style="float: right;">
                        <i class="fas fa-trash"></i> Удалить платеж
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterInvoices() {
    const clientId = document.getElementById('clientFilter').value;
    const status = document.getElementById('statusFilter').value;
    const order = document.getElementById('orderFilter').value;
    
    let url = '?page=invoices';
    if (clientId) url += '&client_id=' + clientId;
    if (status) url += '&status=' + status;
    if (order == 'old') url += '&order=old';
    
    window.location.href = url;
}

function searchTable() {
    const input = document.getElementById('searchBox');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('invoicesTable');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < td.length; j++) {
            if (td[j]) {
                const txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = found ? '' : 'none';
    }
}

// Улучшенная функция добавления оплаты с историей
function addPayment(invoiceId) {
    fetch(`ajax/get_invoice.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                return;
            }
            
            const invoice = data.data;
            const remaining = invoice.amount - invoice.paid_amount;
            
            // Заполняем скрытые поля
            document.getElementById('invoice_id').value = invoiceId;
            
            // Заполняем информацию о счете
            document.getElementById('paymentInfo').innerHTML = `
                <div class="invoice-info">
                    <h4><i class="fas fa-file-invoice"></i> Информация о счете</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Клиент:</strong> ${escapeHtml(invoice.client_name)}</p>
                            <p><strong>Номер счета:</strong> ${escapeHtml(invoice.invoice_number)}</p>
                            <p><strong>Дата выписки:</strong> ${invoice.formatted_issue_date || ''}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Общая сумма:</strong> <span class="text-primary">${formatCurrency(invoice.amount)}</span></p>
                            <p><strong>Оплачено:</strong> ${formatCurrency(invoice.paid_amount)}</p>
                            <p><strong>Остаток к оплате:</strong> <span class="text-danger"><strong>${formatCurrency(remaining)}</strong></span></p>
                        </div>
                    </div>
                </div>
            `;
            
            // Устанавливаем максимальную сумму для оплаты
            const paymentInput = document.getElementById('payment_amount');
            paymentInput.max = remaining;
            paymentInput.value = remaining > 0 ? remaining : 0;
            
            // Показываем историю платежей
            loadPaymentHistory(invoiceId);
            
            // Открываем модальное окно
            openModal('paymentModal');
        });
}

// Загрузка истории платежей
function loadPaymentHistory(invoiceId) {
    fetch(`ajax/get_payment_history.php?invoice_id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const historyBody = document.getElementById('paymentHistoryBody');
                const noPaymentsMsg = document.getElementById('noPaymentsMessage');
                const historySection = document.getElementById('paymentHistorySection');
                
                if (data.payments.length > 0) {
                    historySection.style.display = 'block';
                    noPaymentsMsg.style.display = 'none';
                    
                    let html = '';
                    data.payments.forEach(payment => {
                        html += `
                        <tr>
                            <td>${payment.formatted_date}</td>
                            <td><strong>${formatCurrency(payment.amount)}</strong></td>
                            <td>
                                <span class="badge badge-payment-${payment.payment_method}">
                                    ${getPaymentMethodText(payment.payment_method)}
                                </span>
                            </td>
                            <td>${escapeHtml(payment.notes || '—')}</td>
                            <td>${escapeHtml(payment.created_by_name || 'Система')}</td>
                            <td>${payment.created_at_formatted}</td>
                            <td>
                                ${isAdmin() ? `
                                <button class="btn btn-sm btn-warning" onclick="editPayment(${payment.id})" title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </button>
                                ` : ''}
                            </td>
                        </tr>
                        `;
                    });
                    historyBody.innerHTML = html;
                } else {
                    historySection.style.display = 'none';
                    noPaymentsMsg.style.display = 'block';
                }
            }
        });
}

// Текст для методов оплаты
function getPaymentMethodText(method) {
    const methods = {
        'cash': 'Наличные',
        'bank_transfer': 'Банковский перевод',
        'card': 'Карта',
        'online': 'Онлайн',
        'other': 'Другое'
    };
    return methods[method] || method;
}

// Редактирование платежа
function editPayment(paymentId) {
    fetch(`ajax/get_payment.php?id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const payment = data.payment;
                
                document.getElementById('edit_payment_id').value = payment.id;
                document.getElementById('edit_invoice_id').value = payment.invoice_id;
                document.getElementById('edit_payment_amount').value = payment.amount;
                document.getElementById('edit_payment_date').value = payment.payment_date;
                document.getElementById('edit_payment_method').value = payment.payment_method;
                document.getElementById('edit_notes').value = payment.notes || '';
                
                openModal('editPaymentModal');
            }
        });
}

// Обновление платежа
function updatePayment(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('ajax/update_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', data.message);
            closeModal('editPaymentModal');
            
            // Обновляем историю и информацию о счете
            const invoiceId = document.getElementById('invoice_id').value;
            loadPaymentHistory(invoiceId);
            
            // Обновляем информацию о счете
            setTimeout(() => {
                addPayment(invoiceId);
            }, 500);
        } else {
            showMessage('error', data.message);
        }
    });
}

// Удаление платежа
function deletePayment() {
    if (!confirm('Вы уверены, что хотите удалить этот платеж?\nЭто действие нельзя отменить.')) {
        return;
    }
    
    const paymentId = document.getElementById('edit_payment_id').value;
    const invoiceId = document.getElementById('edit_invoice_id').value;
    
    fetch(`ajax/delete_payment.php?id=${paymentId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', data.message);
            closeModal('editPaymentModal');
            
            // Обновляем историю и информацию о счете
            loadPaymentHistory(invoiceId);
            
            // Обновляем информацию о счете
            setTimeout(() => {
                addPayment(invoiceId);
            }, 500);
        } else {
            showMessage('error', data.message);
        }
    });
}

function processPayment(event) {
    event.preventDefault();
    
    const formData = new FormData(document.getElementById('paymentForm'));
    
    fetch('ajax/process_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Оплата успешно зарегистрирована!');
            closeModal('paymentModal');
            location.reload();
        } else {
            alert('Ошибка: ' + data.message);
        }
    });
}
// Автодополнение клиентов
let clientSuggestionsTimeout = null;

document.getElementById('clientName').addEventListener('input', function(e) {
    const search = e.target.value.trim();
    
    clearTimeout(clientSuggestionsTimeout);
    
    if (search.length < 2) {
        document.getElementById('clientSuggestions').innerHTML = '';
        return;
    }
    
    clientSuggestionsTimeout = setTimeout(() => {
        fetch(`ajax/search_clients.php?search=${encodeURIComponent(search)}`)
            .then(response => response.json())
            .then(data => {
                const suggestions = document.getElementById('clientSuggestions');
                suggestions.innerHTML = '';
                
                if (data.length === 0) {
                    suggestions.innerHTML = '<div class="suggestion-item">Клиент не найден</div>';
                    return;
                }
                
                data.forEach(client => {
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.innerHTML = `
                        <div><strong>${escapeHtml(client.name)}</strong></div>
                        <small>${client.phone || 'Нет телефона'}</small>
                    `;
                    item.onclick = () => {
                        document.getElementById('clientName').value = client.name;
                        document.getElementById('clientPhone').value = client.phone || '';
                        suggestions.innerHTML = '';
                    };
                    suggestions.appendChild(item);
                });
            });
    }, 300);
});

// Закрытие автодополнения при клике вне поля
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        document.getElementById('clientSuggestions').innerHTML = '';
    }
});

// Сохранение счета (без перезагрузки страницы)
function saveInvoice(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    fetch('ajax/save_invoice.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Показываем сообщение об успехе
            showMessage('success', data.message);
            
            // Закрываем модальное окно
            closeModal('addInvoice');
            
            // Очищаем форму
            form.reset();
            
            // Обновляем таблицу без перезагрузки страницы
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showMessage('error', data.message);
        }
    })
    .catch(error => {
        showMessage('error', 'Ошибка сети: ' + error.message);
    });
}

// Упрощенная функция добавления оплаты
function addPayment(invoiceId) {
    // Получаем информацию о счете
    fetch(`ajax/get_invoice.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                return;
            }
            
            const invoice = data.data;
            const remaining = invoice.amount - invoice.paid_amount;
            
            // Заполняем скрытые поля
            document.getElementById('invoice_id').value = invoiceId;
            
            // Заполняем информацию о счете
            document.getElementById('paymentInfo').innerHTML = `
                <div class="invoice-info">
                    <h4>Информация о счете</h4>
                    <p><strong>Клиент:</strong> ${escapeHtml(invoice.client_name)}</p>
                    <p><strong>Номер счета:</strong> ${escapeHtml(invoice.invoice_number)}</p>
                    <p><strong>Общая сумма:</strong> <strong>${formatCurrency(invoice.amount)}</strong></p>
                    <p><strong>Оплачено:</strong> ${formatCurrency(invoice.paid_amount)}</p>
                    <p><strong>Остаток к оплате:</strong> <span class="text-danger">${formatCurrency(remaining)}</span></p>
                </div>
            `;
            
            // Устанавливаем максимальную сумму для оплаты
            const paymentInput = document.getElementById('payment_amount');
            paymentInput.max = remaining;
            paymentInput.value = remaining;
            
            // Открываем модальное окно
            openModal('paymentModal');
        });
}

// Упрощенная функция обработки оплаты
function processPayment(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const paymentAmount = parseFloat(formData.get('payment_amount'));
    
    // Проверяем сумму оплаты
    if (paymentAmount <= 0) {
        showMessage('error', 'Сумма оплаты должна быть больше нуля');
        return;
    }
    
    fetch('ajax/process_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Показываем сообщение об успехе
            showMessage('success', data.message);
            
            // Закрываем модальное окно
            closeModal('paymentModal');
            
            // Очищаем форму
            form.reset();
            
            // Обновляем таблицу без полной перезагрузки
            updateInvoiceRow(data.invoice_id, data.new_status, data.new_paid);
            
            // Обновляем страницу через секунду для обновления статистики
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage('error', data.message);
        }
    })
    .catch(error => {
        showMessage('error', 'Ошибка сети: ' + error.message);
    });
}

// Функция для обновления строки в таблице после оплаты
function updateInvoiceRow(invoiceId, newStatus, newPaid) {
    const row = document.querySelector(`tr[data-id="${invoiceId}"]`);
    if (!row) return;
    
    // Обновляем статус
    const statusCell = row.querySelector('.status');
    if (statusCell) {
        const statusText = {
            'pending': 'Ожидание',
            'partial': 'Частично',
            'paid': 'Оплачено'
        };
        
        statusCell.className = `status status-${newStatus}`;
        statusCell.textContent = statusText[newStatus];
    }
    
    // Обновляем сумму оплаты
    const amountCell = row.querySelector('td:nth-child(4)');
    if (amountCell && newStatus === 'partial') {
        const amountMatch = amountCell.innerHTML.match(/(\d[\d\s]*,\d{2}) TMT/);
        if (amountMatch) {
            const totalAmount = parseFloat(amountMatch[1].replace(/\s/g, '').replace(',', '.'));
            amountCell.innerHTML = `
                <div><strong>${formatCurrency(totalAmount)}</strong></div>
                <small>Оплачено: ${formatCurrency(newPaid)}</small>
            `;
        }
    }
}

// Функция для показа сообщений
function showMessage(type, text) {
    // Создаем элемент сообщения
    const message = document.createElement('div');
    message.className = `flash-message flash-${type}`;
    message.innerHTML = `
        <div class="message-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${escapeHtml(text)}
        </div>
        <button class="message-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Добавляем сообщение в начало контента
    const mainContent = document.querySelector('.main-content');
    const firstChild = mainContent.firstChild;
    if (firstChild) {
        mainContent.insertBefore(message, firstChild);
    } else {
        mainContent.appendChild(message);
    }
    
    // Автоматически удаляем сообщение через 5 секунд
    setTimeout(() => {
        if (message.parentElement) {
            message.remove();
        }
    }, 5000);
}

// Вспомогательные функции
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'TMT'
    }).format(amount);
}

// Функция редактирования счета
function editInvoice(invoiceId) {
    fetch(`ajax/get_invoice.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                return;
            }
            
            const invoice = data.data;
            
            // Получаем информацию о клиенте
            fetch(`ajax/get_client_info.php?client_id=${invoice.client_id}`)
                .then(response => response.json())
                .then(clientData => {
                    const client = clientData.client || {name: '', phone: ''};
                    
                    // Создаем модальное окно редактирования
                    const modal = document.createElement('div');
                    modal.className = 'modal';
                    modal.id = 'editInvoiceModal';
                    modal.innerHTML = `
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Редактировать счет</h2>
                                <span class="close" onclick="closeModal('editInvoiceModal')">&times;</span>
                            </div>
                            <div class="modal-body">
                                <form id="editInvoiceForm" onsubmit="updateInvoice(event, ${invoiceId})">
                                    <div class="form-group">
                                        <label>Номер счета *</label>
                                        <input type="text" name="invoice_number" class="form-control" 
                                               value="${escapeHtml(invoice.invoice_number)}" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Клиент *</label>
                                        <input type="text" name="client_name" class="form-control" 
                                               value="${escapeHtml(client.name)}" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Телефон клиента</label>
                                        <input type="tel" name="client_phone" class="form-control" 
                                               value="${escapeHtml(client.phone || '')}">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>Сумма *</label>
                                                <input type="number" name="amount" class="form-control" 
                                                       value="${invoice.amount}" step="0.01" min="0.01" required>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>Дата выписки *</label>
                                                <input type="date" name="issue_date" class="form-control" 
                                                       value="${invoice.issue_date}" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>Номер доверенности</label>
                                                <input type="text" name="proxy_number" class="form-control" 
                                                       value="${escapeHtml(invoice.proxy_number || '')}">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>Дата доверенности</label>
                                                <input type="date" name="proxy_date" class="form-control" 
                                                       value="${invoice.proxy_date || ''}">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-save"></i> Сохранить изменения
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(modal);
                    modal.style.display = 'flex';
                });
        });
}

// Функция обновления счета
function updateInvoice(event, invoiceId) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('id', invoiceId);
    
    fetch('ajax/update_invoice.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', data.message);
            closeModal('editInvoiceModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage('error', data.message);
        }
    });
}
// Автоматическая нумерация счетов
document.addEventListener('DOMContentLoaded', function() {
    // При открытии модального окна
    document.getElementById('invoiceNumber').addEventListener('click', function() {
        if (!this.value) {
            getNextInvoiceNumber();
        }
    });
    
    // При изменении состояния чекбокса
    document.getElementById('autoNumber').addEventListener('change', function() {
        const numberInput = document.getElementById('invoiceNumber');
        if (this.checked && !numberInput.value) {
            getNextInvoiceNumber();
        } else if (!this.checked) {
            numberInput.readOnly = false;
            numberInput.style.backgroundColor = '';
            numberInput.placeholder = 'Введите номер счета вручную';
        }
    });
    
    // При изменении даты выписки - обновляем номер если нужно
    document.getElementById('invoiceDate').addEventListener('change', function() {
        if (document.getElementById('autoNumber').checked) {
            getNextInvoiceNumber();
        }
    });
});

// Получить следующий номер счета
function getNextInvoiceNumber() {
    const numberInput = document.getElementById('invoiceNumber');
    const hint = document.getElementById('invoiceNumberHint');
    
    // Показываем загрузку
    numberInput.value = 'Загрузка...';
    numberInput.readOnly = true;
    numberInput.style.backgroundColor = '#f8f9fa';
    
    fetch('ajax/get_next_invoice_number.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                numberInput.value = data.next_number;
                hint.innerHTML = `<span class="text-success">✓ Автоматический номер: ${data.next_number}</span>`;
                numberInput.style.backgroundColor = '#e8f5e9';
            } else {
                numberInput.value = '01'; // Значение по умолчанию
                hint.innerHTML = `<span class="text-warning">⚠ Используется номер по умолчанию: 01</span>`;
            }
        })
        .catch(error => {
            numberInput.value = '01';
            hint.innerHTML = `<span class="text-danger">⚠ Ошибка загрузки, используется: 01</span>`;
        });
}

// Обновленная функция сохранения счета
function saveInvoice(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Если номер счета пустой и включена автонумерация - получаем новый
    if (!formData.get('invoice_number') && document.getElementById('autoNumber').checked) {
        // Используем значение из поля (уже должно быть заполнено)
        if (!document.getElementById('invoiceNumber').value) {
            showMessage('warning', 'Пожалуйста, нажмите на поле номера счета для генерации номера');
            return;
        }
    }
    
    // Проверяем обязательные поля
    if (!formData.get('client_name')) {
        showMessage('error', 'Пожалуйста, укажите клиента');
        return;
    }
    
    if (!formData.get('amount') || parseFloat(formData.get('amount')) <= 0) {
        showMessage('error', 'Пожалуйста, укажите корректную сумму');
        return;
    }
    
    // Показываем индикатор загрузки
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
    submitBtn.disabled = true;
    
    fetch('ajax/save_invoice.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', 
                `Счет №${data.invoice_number} успешно создан для клиента "${data.client_name}"`
            );
            
            // Закрываем модальное окно
            closeModal('addInvoice');
            
            // Сбрасываем форму
            resetInvoiceForm();
            
            // Обновляем таблицу через 1 секунду
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage('error', data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        showMessage('error', 'Ошибка сети: ' + error.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Сброс формы
function resetInvoiceForm() {
    const form = document.getElementById('invoiceForm');
    if (form) {
        form.reset();
        document.getElementById('invoiceDate').value = '<?php echo date("Y-m-d"); ?>';
        document.getElementById('invoiceNumber').value = '';
        document.getElementById('invoiceNumber').readOnly = true;
        document.getElementById('invoiceNumber').style.backgroundColor = '#f8f9fa';
        document.getElementById('invoiceNumberHint').innerHTML = 'Нажмите для автоматического номера';
        document.getElementById('invoiceAmount').value = '';
        document.getElementById('clientName').value = '';
        document.getElementById('clientPhone').value = '';
        document.getElementById('autoNumber').checked = true;
    }
}

// При открытии модального окна
function openAddInvoiceModal() {
    openModal('addInvoice');
    // Ждем немного чтобы модальное окно отобразилось
    setTimeout(() => {
        if (document.getElementById('autoNumber').checked) {
            getNextInvoiceNumber();
        }
    }, 100);
}

// Обновляем кнопку открытия модального окна
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.querySelector('[onclick*="openModal(\'addInvoice\')"]');
    if (addBtn) {
        addBtn.setAttribute('onclick', 'openAddInvoiceModal()');
    }
});

// Удаление счета
function deleteInvoice(invoiceId) {
    if (!confirm('Вы уверены, что хотите удалить этот счет?\nЭто действие нельзя отменить.')) {
        return;
    }
    
    // Показываем индикатор загрузки
    const row = document.querySelector(`tr[data-id="${invoiceId}"]`);
    if (row) {
        row.style.opacity = '0.5';
        const deleteBtn = row.querySelector('.btn-danger');
        if (deleteBtn) {
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            deleteBtn.disabled = true;
        }
    }
    
    fetch(`ajax/delete_invoice.php?id=${invoiceId}`)
        .then(response => {
            // Проверяем Content-Type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Server returned:', text.substring(0, 500));
                    throw new Error('Сервер вернул не JSON');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                
                // Удаляем строку из таблицы
                const row = document.querySelector(`tr[data-id="${invoiceId}"]`);
                if (row) {
                    setTimeout(() => {
                        row.remove();
                        
                        // Если таблица пустая, показываем сообщение
                        const tbody = document.querySelector('#invoicesTable tbody');
                        if (tbody && tbody.children.length === 0) {
                            const emptyRow = document.createElement('tr');
                            emptyRow.innerHTML = '<td colspan="8" class="text-center">Нет счетов</td>';
                            tbody.appendChild(emptyRow);
                        }
                        
                        // Обновляем статистику
                        updateStatsAfterDeletion();
                    }, 500);
                } else {
                    // Если не нашли строку, перезагружаем страницу
                    setTimeout(() => location.reload(), 1500);
                }
            } else {
                showMessage('error', data.message);
                if (row) {
                    row.style.opacity = '1';
                    const deleteBtn = row.querySelector('.btn-danger');
                    if (deleteBtn) {
                        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                        deleteBtn.disabled = false;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Delete invoice error:', error);
            showMessage('error', 'Ошибка при удалении: ' + error.message);
            if (row) {
                row.style.opacity = '1';
                const deleteBtn = row.querySelector('.btn-danger');
                if (deleteBtn) {
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    deleteBtn.disabled = false;
                }
            }
        });
}

// Функция для обновления статистики после удаления
function updateStatsAfterDeletion() {
    // Обновляем статистику на странице
    const statsCards = document.querySelectorAll('.stats-cards .card .number');
    if (statsCards.length >= 4) {
        // Уменьшаем общее количество счетов
        const totalInvoices = statsCards[0];
        let currentCount = parseInt(totalInvoices.textContent) || 0;
        if (currentCount > 0) {
            totalInvoices.textContent = currentCount - 1;
        }
        
        // TODO: Можно добавить обновление других статистик
        // Нужно будет делать AJAX запрос для актуальных данных
    }
}
function viewInvoice(id) {
     window.location.href = '?page=view_invoice&id=' + id;
}
// Функция для быстрого просмотра истории платежей
function openPaymentHistory(invoiceId) {
    fetch(`ajax/get_invoice_summary.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPaymentHistoryPopup(data.invoice, data.payments);
            } else {
                alert(data.message || 'Ошибка загрузки данных');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ошибка при загрузке истории платежей');
        });
}

// Функция показа всплывающего окна с историей
function showPaymentHistoryPopup(invoice, payments) {
    // Создаем HTML для всплывающего окна
    const popupHTML = `
        <div class="payment-history-popup-overlay" id="historyPopup">
            <div class="payment-history-popup">
                <div class="popup-header">
                    <h4>
                        <i class="fas fa-file-invoice"></i>
                        Счет №${escapeHtml(invoice.invoice_number)}
                    </h4>
                    <button class="popup-close" onclick="closePaymentHistoryPopup()">
                        &times;
                    </button>
                </div>
                
                <div class="popup-body">
                    <!-- Информация о счете -->
                    <div class="invoice-summary">
                        <div class="summary-item">
                            <span>Клиент:</span>
                            <strong>${escapeHtml(invoice.client_name)}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Общая сумма:</span>
                            <strong class="text-primary">${formatCurrency(invoice.amount)}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Оплачено:</span>
                            <strong class="text-success">${formatCurrency(invoice.paid_amount)}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Остаток:</span>
                            <strong class="text-danger">${formatCurrency(invoice.amount - invoice.paid_amount)}</strong>
                        </div>
                    </div>
                    
                    <!-- История платежей -->
                    <div class="payments-history">
                        <h5><i class="fas fa-history"></i> История платежей</h5>
                        
                        ${payments.length > 0 ? `
                            <div class="payments-list">
                                ${payments.map(payment => `
                                    <div class="payment-item">
                                        <div class="payment-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            ${payment.formatted_date || payment.payment_date}
                                        </div>
                                        <div class="payment-amount">
                                            <strong>${formatCurrency(payment.amount)}</strong>
                                        </div>
                                        <div class="payment-method">
                                            <span class="badge badge-payment-${payment.payment_method}">
                                                ${getPaymentMethodText(payment.payment_method)}
                                            </span>
                                        </div>
                                        ${payment.notes ? `
                                            <div class="payment-notes">
                                                <small><i class="fas fa-sticky-note"></i> ${escapeHtml(payment.notes)}</small>
                                            </div>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        ` : `
                            <div class="no-payments">
                                <i class="fas fa-info-circle"></i>
                                Нет зарегистрированных платежей
                            </div>
                        `}
                    </div>
                    
                    <!-- Кнопки действий -->
                    <div class="popup-actions">
                        <button class="btn btn-sm btn-success" onclick="addPayment(${invoice.id}); closePaymentHistoryPopup();">
                            <i class="fas fa-plus"></i> Внести оплату
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="viewInvoice(${invoice.id}); closePaymentHistoryPopup();">
                            <i class="fas fa-external-link-alt"></i> Подробнее
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="closePaymentHistoryPopup()">
                            <i class="fas fa-times"></i> Закрыть
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Добавляем попап в body
    const popupElement = document.createElement('div');
    popupElement.innerHTML = popupHTML;
    document.body.appendChild(popupElement.firstElementChild);
}

// Функция закрытия попапа
function closePaymentHistoryPopup() {
    const popup = document.getElementById('historyPopup');
    if (popup) {
        popup.remove();
    }
}

// Закрытие попапа при нажатии Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePaymentHistoryPopup();
    }
});

// Закрытие попапа при клике вне его
document.addEventListener('click', function(event) {
    const popup = document.getElementById('historyPopup');
    if (popup && event.target === popup) {
        closePaymentHistoryPopup();
    }
});
</script>