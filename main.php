<?php
require_once 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit(); }

// تعیین صفحه و مسیر ماژول
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$module_map = [
    'dashboard' => 'modules/dashboard.php',
    'inbox'     => 'modules/correspondence/inbox.php',
    'create-letter' => 'modules/correspondence/create-letter.php',
    'tasks'     => 'modules/tasks/my-tasks.php',
    'users'     => 'modules/admin/users.php',
    'settings'  => 'modules/admin/settings.php'
];

$page_file = isset($module_map[$page]) ? $module_map[$page] : 'modules/dashboard.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; color: white; transition: 0.3s; }
        .nav-link { color: #bdc3c7; border-radius: 5px; margin: 5px 15px; padding: 10px; }
        .nav-link:hover, .nav-link.active { background: #34495e; color: white; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card-box { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 p-0 sidebar">
                <div class="p-3 text-center">
                    <h5>پنل اتوماسیون</h5>
                    <small class="text-muted">نسخه ۱.۰</small>
                </div>
                <hr>
                <div class="nav flex-column">
                    <a class="nav-link <?php echo $page=='dashboard'?'active':''; ?>" href="main.php?page=dashboard"><i class="fa fa-gauge-high"></i> داشبورد</a>
                    <a class="nav-link <?php echo $page=='inbox'?'active':''; ?>" href="main.php?page=inbox"><i class="fa fa-inbox"></i> نامه‌های وارده</a>
                    <a class="nav-link <?php echo $page=='tasks'?'active':''; ?>" href="main.php?page=tasks"><i class="fa fa-check-double"></i> کارتابل وظایف</a>
                    <a class="nav-link" href="main.php?page=create-letter"><i class="fa fa-pen-nib"></i> ایجاد نامه</a>
                    <?php if($_SESSION['role_id'] == 1): ?>
                        <hr><small class="px-4 text-uppercase">مدیریت</small>
                        <a class="nav-link" href="main.php?page=users"><i class="fa fa-users"></i> کاربران</a>
                        <a class="nav-link" href="main.php?page=settings"><i class="fa fa-cog"></i> تنظیمات</a>
                    <?php endif; ?>
                    <a class="nav-link text-danger mt-5" href="logout.php"><i class="fa fa-power-off"></i> خروج از سیستم</a>
                </div>
            </nav>

            <main class="col-md-10 main-content p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>خوش آمدید، <?php echo $_SESSION['full_name']; ?></h4>
                    <div class="text-muted"><?php echo date('Y/m/d'); ?></div>
                </div>

                <div class="card card-box p-4 bg-white">
                    <?php 
                    if (file_exists($page_file)) {
                        include $page_file; 
                    } else {
                        echo "<div class='text-center py-5'><i class='fa fa-tools fa-3x text-muted mb-3'></i><h4>ماژول '$page' یافت نشد یا در حال ساخت است.</h4></div>";
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>