<?php
// pages/users.php
// Только для администратора
if (!isAdmin()) {
    header('Location: ?page=dashboard');
    exit();
}

// Получаем всех пользователей
$users = $pdo->query("SELECT * FROM users ORDER BY role, username")->fetchAll();
?>

<div class="header">
    <h1><i class="fas fa-users-cog"></i> Управление пользователями</h1>
    <p>Создание и управление учетными записями</p>
</div>

<div class="action-bar">
    <button class="btn btn-primary" onclick="openModal('addUser')">
        <i class="fas fa-user-plus"></i> Добавить пользователя
    </button>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Имя пользователя</th>
                <th>Роль</th>
                <th>Дата создания</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td>#<?php echo $user['id']; ?></td>
                <td>
                    <strong><?php echo escape($user['username']); ?></strong>
                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                        <span class="badge badge-primary">Вы</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?php echo $user['role'] == 'admin' ? 'badge-danger' : 'badge-info'; ?>">
                        <?php echo $user['role'] == 'admin' ? 'Администратор' : 'Кассир'; ?>
                    </span>
                </td>
                <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                <td class="actions">
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-sm btn-warning" 
                                onclick="editUser(<?php echo $user['id']; ?>)"
                                title="Изменить пароль">
                            <i class="fas fa-key"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" 
                                onclick="deleteUser(<?php echo $user['id']; ?>)"
                                title="Удалить">
                            <i class="fas fa-trash"></i>
                        </button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-info" 
                                onclick="changeMyPassword()"
                                title="Изменить мой пароль">
                            <i class="fas fa-user-edit"></i>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Модальное окно добавления пользователя -->
<div id="addUser" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Добавить пользователя</h2>
            <span class="close" onclick="closeModal('addUser')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="userForm" onsubmit="saveUser(event)">
                <div class="form-group">
                    <label>Имя пользователя *</label>
                    <input type="text" name="username" class="form-control" 
                           required minlength="3" maxlength="50">
                </div>
                
                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="password" name="password" id="newPassword" 
                           class="form-control" required minlength="4">
                    <small class="form-text text-muted">Минимум 4 символа</small>
                </div>
                
                <div class="form-group">
                    <label>Подтверждение пароля *</label>
                    <input type="password" name="password_confirm" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Роль *</label>
                    <select name="role" class="form-control" required>
                        <option value="cashier">Кассир (может все, кроме удаления)</option>
                        <option value="admin">Администратор (полные права)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Создать пользователя
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Сохранение нового пользователя
function saveUser(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Проверяем совпадение паролей
    const password = formData.get('password');
    const passwordConfirm = formData.get('password_confirm');
    
    if (password !== passwordConfirm) {
        showMessage('error', 'Пароли не совпадают!');
        return;
    }
    
    // Показываем индикатор загрузки
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Создание...';
    submitBtn.disabled = true;
    
    // В функции saveUser обнови обработку ответа:
    fetch('ajax/save_user.php', {
    method: 'POST',
    body: formData
            })
    .then(response => response.text())
    .then(text => {
    console.log('Raw response:', text);
    
    // УДАЛЯЕМ PHP NOTICE ЕСЛИ ОНИ ЕСТЬ
    const jsonStart = text.indexOf('{');
    if (jsonStart > 0) {
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
        if (data.success) {
            showMessage('success', `Пользователь "${data.username}" успешно создан`);
            closeModal('addUser');
            setTimeout(() => location.reload(), 1500);
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

// Редактирование пользователя
function editUser(userId) {
    fetch(`ajax/get_user.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showMessage('error', data.message);
                return;
            }
            
            const user = data.user;
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'editUserModal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Изменить пользователя</h2>
                        <span class="close" onclick="closeModal('editUserModal')">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm" onsubmit="updateUser(event, ${userId})">
                            <div class="form-group">
                                <label>Имя пользователя</label>
                                <input type="text" class="form-control" 
                                       value="${escapeHtml(user.username)}" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>Новый пароль *</label>
                                <input type="password" name="password" 
                                       class="form-control" required minlength="4">
                                <small class="form-text text-muted">Минимум 4 символа</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Подтверждение пароля *</label>
                                <input type="password" name="password_confirm" 
                                       class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Роль</label>
                                <select name="role" class="form-control" required>
                                    <option value="cashier" ${user.role == 'cashier' ? 'selected' : ''}>
                                        Кассир
                                    </option>
                                    <option value="admin" ${user.role == 'admin' ? 'selected' : ''}>
                                        Администратор
                                    </option>
                                </select>
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

// Обновление пользователя
function updateUser(event, userId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('id', userId);
    
    // Проверяем совпадение паролей
    const password = formData.get('password');
    const passwordConfirm = formData.get('password_confirm');
    
    if (password !== passwordConfirm) {
        showMessage('error', 'Пароли не совпадают!');
        return;
    }
    
    fetch('ajax/update_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', data.message);
            closeModal('editUserModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage('error', data.message);
        }
    });
}

// Удаление пользователя
function deleteUser(userId) {
    if (!confirm('Вы уверены, что хотите удалить этого пользователя?\nЭто действие нельзя отменить.')) {
        return;
    }
    
    fetch(`ajax/delete_user.php?id=${userId}`)
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

// Смена своего пароля
function changeMyPassword() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.id = 'changePasswordModal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Смена пароля</h2>
                <span class="close" onclick="closeModal('changePasswordModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" onsubmit="updateMyPassword(event)">
                    <div class="form-group">
                        <label>Текущий пароль *</label>
                        <input type="password" name="current_password" 
                               class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Новый пароль *</label>
                        <input type="password" name="new_password" 
                               class="form-control" required minlength="4">
                        <small class="form-text text-muted">Минимум 4 символа</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Подтверждение пароля *</label>
                        <input type="password" name="new_password_confirm" 
                               class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-key"></i> Сменить пароль
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
}

// Обновление своего пароля
function updateMyPassword(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Проверяем совпадение паролей
    const newPassword = formData.get('new_password');
    const newPasswordConfirm = formData.get('new_password_confirm');
    
    if (newPassword !== newPasswordConfirm) {
        showMessage('error', 'Новые пароли не совпадают!');
        return;
    }
    
    fetch('ajax/change_my_password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', data.message);
            closeModal('changePasswordModal');
        } else {
            showMessage('error', data.message);
        }
    });
}
</script>