<?php
// pages/clients.php

// Получаем клиентов
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT * FROM clients WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем статистику по клиентам
$stats_sql = "
    SELECT c.id, c.name, 
           COUNT(i.id) as invoice_count,
           SUM(i.amount) as total_amount,
           SUM(i.paid_amount) as total_paid
    FROM clients c
    LEFT JOIN invoices i ON c.id = i.client_id
    GROUP BY c.id
";
$stats = $pdo->query($stats_sql)->fetchAll(PDO::FETCH_ASSOC);
$stats_map = [];
foreach ($stats as $stat) {
    $stats_map[$stat['id']] = $stat;
}
?>

<div class="header">
    <h1><i class="fas fa-users"></i> Клиенты</h1>
    <p>Управление базой клиентов</p>
</div>

<!-- Статистика -->
<div class="stats-cards">
    <div class="card">
        <h3>Всего клиентов</h3>
        <div class="number"><?php echo count($clients); ?></div>
    </div>
    
    <div class="card">
        <h3>Активных клиентов</h3>
        <div class="number">
            <?php 
            $active_clients = array_filter($stats, fn($s) => $s['invoice_count'] > 0);
            echo count($active_clients);
            ?>
        </div>
    </div>
    
    <div class="card debt">
        <h3>Общая задолженность</h3>
        <div class="number">
            <?php 
            $total_debt = 0;
            foreach ($stats as $stat) {
                $debt = $stat['total_amount'] - $stat['total_paid'];
                if ($debt > 0) {
                    $total_debt += $debt;
                }
            }
            echo number_format($total_debt, 2) . ' TMT';
            ?>
        </div>
    </div>
</div>

<!-- Поиск и фильтры -->
<div class="action-bar">
    <button class="btn btn-primary" onclick="openModal('addClient')">
        <i class="fas fa-plus"></i> Добавить клиента
    </button>
    
    <div class="search-box">
        <form method="GET" action="" class="search-form">
            <input type="hidden" name="page" value="clients">
            <input type="text" name="search" class="form-control" 
                   placeholder="Поиск по имени или телефону..." 
                   value="<?php echo escape($search); ?>">
            <button type="submit" class="btn btn-info">
                <i class="fas fa-search"></i> Найти
            </button>
            <?php if ($search): ?>
                <a href="?page=clients" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Сбросить
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Таблица клиентов -->
<div class="table-container">
    <table id="clientsTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Имя</th>
                <th>Телефон</th>
                <th>Количество счетов</th>
                <th>Общая сумма</th>
                <th>Оплачено</th>
                <th>Задолженность</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="8" class="text-center">Нет клиентов</td>
                </tr>
            <?php else: ?>
                <?php foreach ($clients as $client): 
                    $stat = $stats_map[$client['id']] ?? [
                        'invoice_count' => 0,
                        'total_amount' => 0,
                        'total_paid' => 0
                    ];
                    $debt = $stat['total_amount'] - $stat['total_paid'];
                ?>
                <tr>
                    <td>#<?php echo $client['id']; ?></td>
                    <td>
                        <strong><?php echo escape($client['name']); ?></strong><br>
                        <small>Добавлен: <?php echo date('d.m.Y', strtotime($client['created_at'])); ?></small>
                    </td>
                    <td>
                        <?php if ($client['phone']): ?>
                            <a href="tel:<?php echo $client['phone']; ?>">
                                <i class="fas fa-phone"></i> <?php echo escape($client['phone']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-info"><?php echo $stat['invoice_count']; ?></span>
                    </td>
                    <td>
                        <?php if ($stat['total_amount'] > 0): ?>
                            <strong><?php echo number_format($stat['total_amount'], 2); ?> TMT</strong>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($stat['total_paid'] > 0): ?>
                            <span class="text-success"><?php echo number_format($stat['total_paid'], 2); ?> TMT</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($debt > 0): ?>
                            <span class="text-danger"><?php echo number_format($debt, 2); ?> TMT</span>
                        <?php else: ?>
                            <span class="text-success">Нет долга</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="?page=invoices&client_id=<?php echo $client['id']; ?>" 
                        class="btn btn-sm btn-info" title="Счета клиента">
                            <i class="fas fa-file-invoice"></i>
                        </a>
                        
                        <!-- Показываем кнопку удаления только если нет счетов -->
                        <?php if ($stat['invoice_count'] == 0): ?>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="deleteClient(<?php echo $client['id']; ?>)" 
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

<!-- Модальное окно добавления клиента -->
<div id="addClient" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Добавить клиента</h2>
            <span class="close" onclick="closeModal('addClient'); resetClientForm()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="clientForm" onsubmit="saveClient(event)">
                <div class="form-group">
                    <label>Имя клиента *</label>
                    <input type="text" name="name" id="clientNameInput" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Телефон</label>
                    <input type="tel" name="phone" id="clientPhoneInput" class="form-control" 
                           placeholder="+993 (65) 12-45-67">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="clientEmailInput" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Адрес</label>
                    <textarea name="address" id="clientAddressInput" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Дополнительная информация</label>
                    <textarea name="notes" id="clientNotesInput" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Сохранить клиента
                    </button>
                    <button type="button" class="btn btn-secondary btn-block mt-2" 
                            onclick="resetClientForm()">
                        <i class="fas fa-redo"></i> Сбросить форму
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editClient(id) {
    fetch(`ajax/get_client.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'editClientModal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Редактировать клиента</h2>
                        <span class="close" onclick="closeModal('editClientModal')">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="editClientForm" onsubmit="saveClientEdit(event, ${id})">
                            <div class="form-group">
                                <label>Имя клиента *</label>
                                <input type="text" name="name" class="form-control" 
                                       value="${escapeHtml(data.name)}" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Телефон</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="${escapeHtml(data.phone || '')}">
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
}

function saveClientEdit(event, id) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('id', id);
    
    fetch('ajax/update_client.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Клиент успешно обновлен!');
            closeModal('editClientModal');
            location.reload();
        } else {
            alert('Ошибка: ' + data.message);
        }
    });
}

// Удаление клиента
function deleteClient(id) {
    if (!confirm('Вы уверены, что хотите удалить этого клиента?\nЭто действие нельзя отменить.')) {
        return;
    }
    
    fetch(`ajax/delete_client.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showMessage('error', data.message);
            }
        });
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
// Сохранение клиента (исправленная версия)
function saveClient(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const name = formData.get('name');
    if (!name || name.trim() === '') {
        showMessage('error', 'Пожалуйста, укажите имя клиента');
        return;
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
    submitBtn.disabled = true;
    
    fetch('ajax/save_client.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log('Raw response:', text);
        
        // УДАЛЯЕМ PHP NOTICE ИЗ ТЕКСТА ПЕРЕД ПАРСИНГОМ JSON
        const jsonStart = text.indexOf('{');
        if (jsonStart > 0) {
            // Если есть текст перед JSON (PHP notice), обрезаем его
            text = text.substring(jsonStart);
        }
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Cleaned text:', text);
            throw new Error('Не удалось обработать ответ сервера');
        }
    })
    .then(data => {
        console.log('Parsed data:', data);
        
        if (data.success) {
            showMessage('success', `Клиент "${data.client_name}" успешно добавлен`);
            closeModal('addClient');
            resetClientForm();
            
            // Обновляем страницу
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage('error', data.message || 'Неизвестная ошибка');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Save client error:', error);
        showMessage('error', 'Ошибка: ' + error.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Сброс формы клиента
function resetClientForm() {
    document.getElementById('clientNameInput').value = '';
    document.getElementById('clientPhoneInput').value = '';
    document.getElementById('clientEmailInput').value = '';
    document.getElementById('clientAddressInput').value = '';
    document.getElementById('clientNotesInput').value = '';
}
</script>