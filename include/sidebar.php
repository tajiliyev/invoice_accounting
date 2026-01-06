<!-- includes/sidebar.php -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-receipt"></i> Учет Счетов</h2>
        <div class="user-info">
            <p><i class="fas fa-user"></i> <?php echo $_SESSION['username'] ?? 'Гость'; ?></p>
        </div>
    </div>
    
    <ul class="nav-menu">
        <li class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
            <a href="?page=dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Панель управления</span>
            </a>
        </li>
        <li class="<?php echo ($page == 'invoices') ? 'active' : ''; ?>">
            <a href="?page=invoices">
                <i class="fas fa-file-invoice"></i>
                <span>Счета-фактуры</span>
            </a>
        </li>
        <li class="<?php echo ($page == 'clients') ? 'active' : ''; ?>">
            <a href="?page=clients">
                <i class="fas fa-users"></i>
                <span>Клиенты</span>
            </a>
        </li>
        <li class="<?php echo ($page == 'reports') ? 'active' : ''; ?>">
            <a href="?page=reports">
                <i class="fas fa-chart-bar"></i>
                <span>Отчеты</span>
            </a>
        </li>
        
        
            <?php if (isAdmin()): ?>
<li class="<?php echo ($page == 'users') ? 'active' : ''; ?>">
    <a href="?page=users">
        <i class="fas fa-users-cog"></i>
        <span>Пользователи</span>
    </a>
</li>
<?php endif; ?>
        <li>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Выход</span>
            </a>
        </li>
    </ul>
</div>