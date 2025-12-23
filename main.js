// فایل اصلی جاوااسکریپت سیستم اتوماسیون اداری

document.addEventListener('DOMContentLoaded', function() {
    // فعال‌سازی tooltip
    initTooltips();
    
    // فعال‌سازی popover
    initPopovers();
    
    // مدیریت فرم‌ها
    initForms();
    
    // مدیریت جدول‌ها
    initTables();
    
    // مدیریت نوتیفیکیشن‌ها
    initNotifications();
    
    // بارگذاری آخرین فعالیت‌ها
    loadRecentActivities();
    
    // مدیریت جستجو
    initSearch();
});

// فعال‌سازی tooltip
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            placement: 'top',
            trigger: 'hover'
        });
    });
}

// فعال‌سازی popover
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// مدیریت فرم‌ها
function initForms() {
    // اعتبارسنجی فرم‌ها
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // مدیریت تاریخ‌ها
    const dateInputs = document.querySelectorAll('.date-picker');
    dateInputs.forEach(function(input) {
        input.addEventListener('focus', function() {
            this.type = 'date';
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.type = 'text';
            }
        });
    });
    
    // مدیریت آپلود فایل
    const fileInputs = document.querySelectorAll('.file-upload');
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'هیچ فایلی انتخاب نشده';
            const label = this.nextElementSibling?.querySelector('.file-name') || 
                         this.parentElement.querySelector('.file-name');
            if (label) {
                label.textContent = fileName;
            }
        });
    });
}

// مدیریت جدول‌ها
function initTables() {
    // مرتب‌سازی جدول
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(function(header) {
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const columnIndex = Array.from(this.parentElement.children).indexOf(this);
            const isAscending = !this.classList.contains('asc');
            
            // حذف کلاس‌های مرتب‌سازی از همه هدرها
            sortableHeaders.forEach(h => h.classList.remove('asc', 'desc'));
            
            // اضافه کردن کلاس مرتب‌سازی به هدر جاری
            this.classList.add(isAscending ? 'asc' : 'desc');
            
            // مرتب‌سازی داده‌ها
            sortTable(table, columnIndex, isAscending);
        });
    });
    
    // جستجو در جدول
    const tableSearchInputs = document.querySelectorAll('.table-search');
    tableSearchInputs.forEach(function(input) {
        input.addEventListener('keyup', function() {
            const tableId = this.dataset.table;
            const table = document.getElementById(tableId);
            const searchTerm = this.value.toLowerCase();
            
            filterTable(table, searchTerm);
        });
    });
}

// مرتب‌سازی جدول
function sortTable(table, column, asc = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort(function(a, b) {
        const aVal = a.children[column].textContent.trim();
        const bVal = b.children[column].textContent.trim();
        
        // بررسی عددی بودن
        if (!isNaN(aVal) && !isNaN(bVal)) {
            return asc ? aVal - bVal : bVal - aVal;
        }
        
        // مقایسه رشته‌ای
        return asc ? aVal.localeCompare(bVal, 'fa') : bVal.localeCompare(aVal, 'fa');
    });
    
    // حذف ردیف‌های موجود
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    // اضافه کردن ردیف‌های مرتب‌شده
    rows.forEach(row => tbody.appendChild(row));
}

// فیلتر جدول
function filterTable(table, searchTerm) {
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// مدیریت نوتیفیکیشن‌ها
function initNotifications() {
    // بررسی نوتیفیکیشن‌های جدید
    checkNewNotifications();
    
    // تنظیم interval برای بررسی نوتیفیکیشن‌ها
    setInterval(checkNewNotifications, 30000); // هر 30 ثانیه
    
    // نمایش شمارنده نوتیفیکیشن‌ها
    updateNotificationCount();
}

// بررسی نوتیفیکیشن‌های جدید
function checkNewNotifications() {
    fetch('includes/check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                updateNotificationCount(data.count);
                
                // نمایش نوتیفیکیشن جدید
                if (data.count > parseInt(localStorage.getItem('lastNotificationCount') || 0)) {
                    showNotificationAlert(data.count);
                }
                
                localStorage.setItem('lastNotificationCount', data.count);
            }
        })
        .catch(error => console.error('خطا در دریافت نوتیفیکیشن‌ها:', error));
}

// بروزرسانی شمارنده نوتیفیکیشن‌ها
function updateNotificationCount(count = null) {
    const badge = document.querySelector('.notification-badge');
    if (!badge) return;
    
    if (count !== null) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    } else {
        fetch('includes/get_notification_count.php')
            .then(response => response.json())
            .then(data => {
                badge.textContent = data.count > 9 ? '9+' : data.count;
                badge.style.display = data.count > 0 ? 'flex' : 'none';
            });
    }
}

// نمایش هشدار نوتیفیکیشن جدید
function showNotificationAlert(count) {
    const message = `شما ${count} نوتیفیکیشن جدید دارید`;
    showToast(message, 'info');
    
    // پخش صدای نوتیفیکیشن
    playNotificationSound();
}

// پخش صدای نوتیفیکیشن
function playNotificationSound() {
    const audio = new Audio('assets/sounds/notification.mp3');
    audio.play().catch(e => console.log('خطا در پخش صدا:', e));
}

// بارگذاری آخرین فعالیت‌ها
function loadRecentActivities() {
    const activityList = document.getElementById('recentActivities');
    if (!activityList) return;
    
    fetch('includes/get_activities.php?limit=5')
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                activityList.innerHTML = '';
                data.forEach(activity => {
                    const item = document.createElement('div');
                    item.className = 'activity-item';
                    item.innerHTML = `
                        <div class="activity-icon">
                            <i class="fas fa-${activity.icon}"></i>
                        </div>
                        <div class="activity-content">
                            <p class="mb-0">${activity.description}</p>
                            <small class="text-muted">${activity.time_ago}</small>
                        </div>
                    `;
                    activityList.appendChild(item);
                });
            }
        });
}

// مدیریت جستجو
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        const searchTerm = this.value.trim();
        if (searchTerm.length < 2) {
            hideSearchResults();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performGlobalSearch(searchTerm);
        }, 500);
    });
    
    // بستن نتایج جستجو با کلیک خارج
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container')) {
            hideSearchResults();
        }
    });
}

// انجام جستجوی سراسری
function performGlobalSearch(term) {
    fetch(`includes/search.php?q=${encodeURIComponent(term)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        });
}

// نمایش نتایج جستجو
function displaySearchResults(results) {
    const container = document.getElementById('searchResults');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (results.length === 0) {
        container.innerHTML = '<div class="search-empty">نتیجه‌ای یافت نشد</div>';
        container.style.display = 'block';
        return;
    }
    
    results.forEach(result => {
        const item = document.createElement('a');
        item.href = result.link;
        item.className = 'search-result-item';
        item.innerHTML = `
            <div class="search-result-icon">
                <i class="fas fa-${result.icon}"></i>
            </div>
            <div class="search-result-content">
                <h6>${result.title}</h6>
                <p class="text-muted">${result.description}</p>
                <small class="text-muted">${result.category}</small>
            </div>
        `;
        container.appendChild(item);
    });
    
    container.style.display = 'block';
}

// مخفی کردن نتایج جستجو
function hideSearchResults() {
    const container = document.getElementById('searchResults');
    if (container) {
        container.style.display = 'none';
    }
}

// تابع عمومی برای نمایش toast
function showToast(message, type = 'info', duration = 3000) {
    // ایجاد container اگر وجود ندارد
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // ایجاد toast
    const toastId = 'toast_' + Date.now();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    // آیکون بر اساس نوع
    let icon = 'info-circle';
    switch (type) {
        case 'success': icon = 'check-circle'; break;
        case 'danger': icon = 'exclamation-circle'; break;
        case 'warning': icon = 'exclamation-triangle'; break;
    }
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${icon} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, {
        delay: duration
    });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
        if (toastContainer.children.length === 0) {
            toastContainer.remove();
        }
    });
}

// تابع برای کپی به کلیپ‌بورد
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('متن با موفقیت کپی شد', 'success');
    }, function(err) {
        console.error('خطا در کپی متن:', err);
        showToast('خطا در کپی متن', 'danger');
    });
}

// تابع برای دانلود فایل
function downloadFile(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// تابع برای نمایش مودال تایید
function showConfirmationModal(title, message, callback) {
    const modalHTML = `
        <div class="modal fade" id="confirmationModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-danger" id="confirmAction">تایید</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // حذف مودال قبلی اگر وجود دارد
    const existingModal = document.getElementById('confirmationModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // اضافه کردن مودال جدید
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    modal.show();
    
    // رویداد تایید
    document.getElementById('confirmAction').addEventListener('click', function() {
        callback();
        modal.hide();
    });
    
    // حذف مودال پس از بسته شدن
    modal._element.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// تابع برای فرمت کردن تاریخ
function formatDate(date, format = 'fa') {
    if (!date) return '';
    
    const d = new Date(date);
    
    if (format === 'fa') {
        // تبدیل به تاریخ فارسی (ساده)
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return d.toLocaleDateString('fa-IR', options);
    }
    
    // فرمت پیش‌فرض
    return d.toLocaleDateString('fa-IR');
}

// تابع برای نمایش لودر
function showLoader(element) {
    if (!element) {
        element = document.body;
    }
    
    const loader = document.createElement('div');
    loader.className = 'loader-overlay';
    loader.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">در حال بارگذاری...</span>
        </div>
    `;
    
    element.style.position = 'relative';
    element.appendChild(loader);
}

// تابع برای مخفی کردن لودر
function hideLoader(element) {
    if (!element) {
        element = document.body;
    }
    
    const loader = element.querySelector('.loader-overlay');
    if (loader) {
        loader.remove();
    }
}

// رویدادهای صفحه
window.addEventListener('beforeunload', function() {
    // ذخیره وضعیت فعلی
    localStorage.setItem('lastActiveTime', Date.now());
});

// بازگشت به بالا
const backToTop = document.getElementById('backToTop');
if (backToTop) {
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTop.style.display = 'block';
        } else {
            backToTop.style.display = 'none';
        }
    });
    
    backToTop.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// مدیریت تم تاریک/روشن
const themeToggle = document.getElementById('themeToggle');
if (themeToggle) {
    themeToggle.addEventListener('click', function() {
        const isDark = document.body.classList.toggle('dark-theme');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        showToast(isDark ? 'تم تاریک فعال شد' : 'تم روشن فعال شد', 'success');
    });
    
    // بارگذاری تم ذخیره شده
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
    }
}