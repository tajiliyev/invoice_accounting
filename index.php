<?php
// index.php
require_once 'config.php';
require_once 'includes/auth.php';

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}
// Определяем страницу
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$allowed_pages = ['invoices', 'clients', 'reports', 'dashboard', 'users','view_invoice'];
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Включаем заголовок
include 'includes/header.php';

// Включаем сайдбар
include 'includes/sidebar.php';
?>

<div class="main-content">
    <?php
    // Выводим сообщения (если есть)
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo $flash['message']; ?>
        </div>
        <?php
    }
    
    // Загружаем страницу
    $page_file = "pages/{$page}.php";
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        include 'pages/invoices.php';
    }
    ?>
</div>

<?php
// Включаем подвал
include 'includes/footer.php';
?>