<?php
if (!defined('IN_SYSTEM')) {
    die('دسترسی غیرمجاز');
}
?>
<!-- سایدبار -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="sidebar-header">
            <div class="user-info">
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h6><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-muted"><?php echo $_SESSION['role_name']; ?></small>
                </div>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>" href="index.php?page=dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    داشبورد
                </a>
            </li>
            
            <!-- بخش وظایف -->
            <?php if (hasPermission('tasks_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'tasks' ? 'active' : ''; ?>" href="index.php?page=tasks">
                    <i class="fas fa-tasks"></i>
                    وظایف
                    <span class="badge bg-primary float-start notification-badge" style="display: none;">0</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- بخش اسناد -->
            <?php if (hasPermission('documents_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'documents' ? 'active' : ''; ?>" href="index.php?page=documents">
                    <i class="fas fa-folder"></i>
                    اسناد
                </a>
            </li>
            <?php endif; ?>
            
            <!-- بخش نامه‌نگاری -->
            <?php if (hasPermission('correspondence_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'correspondence' ? 'active' : ''; ?>" href="index.php?page=correspondence">
                    <i class="fas fa-envelope"></i>
                    نامه‌نگاری
                    <span class="badge bg-danger float-start" id="unreadLetters" style="display: none;">0</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- بخش تقویم -->
            <?php if (hasPermission('calendar_view')): ?>
            <li class="nav-item">
                <a class="nav-link" href="index.php?page=calendar">
                    <i class="fas fa-calendar-alt"></i>
                    تقویم
                </a>
            </li>
            <?php endif; ?>
            
            <!-- بخش گزارشات -->
            <?php if (hasPermission('reports_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'reports' ? 'active' : ''; ?>" href="index.php?page=reports">
                    <i class="fas fa-chart-bar"></i>
                    گزارشات
                </a>
            </li>
            <?php endif; ?>
            
            <hr class="sidebar-divider">
            
            <!-- بخش مدیریت سیستم -->
            <?php if (hasPermission('admin_panel')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'admin' ? 'active' : ''; ?>" href="index.php?page=admin">
                    <i class="fas fa-user-shield"></i>
                    مدیریت سیستم
                </a>
            </li>
            <?php endif; ?>
            
            <!-- تنظیمات -->
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'settings' ? 'active' : ''; ?>" href="index.php?page=settings">
                    <i class="fas fa-cog"></i>
                    تنظیمات
                </a>
            </li>
            
            <!-- پروفایل -->
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'profile' ? 'active' : ''; ?>" href="index.php?page=profile">
                    <i class="fas fa-user-circle"></i>
                    پروفایل
                </a>
            </li>
            
            <!-- راهنما -->
            <li class="nav-item">
                <a class="nav-link" href="index.php?page=help">
                    <i class="fas fa-question-circle"></i>
                    راهنما
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="system-info">
                <small class="text-muted">
                    <i class="fas fa-server"></i>
                    <?php echo date('Y/m/d H:i'); ?>
                </small>
            </div>
        </div>
    </div>
</nav>