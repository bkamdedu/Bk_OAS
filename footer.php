<?php
if (!defined('IN_SYSTEM')) {
    die('دسترسی غیرمجاز');
}
?>
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light border-top">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted">
                        © <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - نسخه 1.0
                    </span>
                </div>
                <div class="col-md-6 text-start">
                    <div class="footer-links">
                        <a href="#" class="text-muted me-3">
                            <i class="fas fa-question-circle"></i> راهنما
                        </a>
                        <a href="#" class="text-muted me-3">
                            <i class="fas fa-shield-alt"></i> حریم خصوصی
                        </a>
                        <a href="#" class="text-muted">
                            <i class="fas fa-file-contract"></i> قوانین
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top -->
    <button id="backToTop" class="btn btn-primary btn-lg back-to-top" title="بازگشت به بالا">
        <i class="fas fa-chevron-up"></i>
    </button>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/color-picker.js"></script>
    
    <?php if (isset($page_js)): ?>
    <script src="assets/js/<?php echo $page_js; ?>"></script>
    <?php endif; ?>
    
    <?php if ($page == 'tasks'): ?>
    <script src="assets/js/task-manager.js"></script>
    <?php endif; ?>
    
    <?php if ($page == 'documents'): ?>
    <script src="assets/js/document-manager.js"></script>
    <?php endif; ?>
    
    <?php if ($page == 'correspondence'): ?>
    <script src="assets/js/correspondence.js"></script>
    <?php endif; ?>
    
    <script>
    // بارگذاری شمارنده‌های اولیه
    $(document).ready(function() {
        // بارگذاری تعداد نوتیفیکیشن‌های خوانده نشده
        $.get('includes/get_notification_count.php', function(data) {
            if (data.count > 0) {
                $('.notification-badge').text(data.count > 9 ? '9+' : data.count).show();
            }
        });
        
        // بارگذاری تعداد نامه‌های خوانده نشده
        $.get('includes/get_unread_letters.php', function(data) {
            if (data.count > 0) {
                $('#unreadLetters').text(data.count).show();
            }
        });
        
        // مدیریت تم تاریک/روشن
        $('#themeToggle').click(function() {
            $('body').toggleClass('dark-theme');
            localStorage.setItem('theme', $('body').hasClass('dark-theme') ? 'dark' : 'light');
        });
        
        // بارگذاری تم ذخیره شده
        if (localStorage.getItem('theme') === 'dark') {
            $('body').addClass('dark-theme');
        }
    });
    </script>
</body>
</html>