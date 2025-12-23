// مدیریت تغییر تم
function changeTheme(themeName) {
    const themes = {
        'default': {
            primary: '#3498db',
            secondary: '#2c3e50',
            success: '#27ae60',
            danger: '#e74c3c'
        },
        'blue': {
            primary: '#2980b9',
            secondary: '#1c2833',
            success: '#239b56',
            danger: '#c0392b'
        },
        'green': {
            primary: '#27ae60',
            secondary: '#1e8449',
            success: '#229954',
            danger: '#cb4335'
        },
        'dark': {
            primary: '#2c3e50',
            secondary: '#1c2833',
            success: '#27ae60',
            danger: '#e74c3c'
        }
    };
    
    const theme = themes[themeName] || themes['default'];
    
    // به‌روزرسانی متغیرهای CSS
    document.documentElement.style.setProperty('--primary-color', theme.primary);
    document.documentElement.style.setProperty('--secondary-color', theme.secondary);
    document.documentElement.style.setProperty('--success-color', theme.success);
    document.documentElement.style.setProperty('--danger-color', theme.danger);
    
    // ذخیره در localStorage
    localStorage.setItem('theme', themeName);
    
    // نمایش پیام
    showToast('تم سیستم تغییر کرد', 'success');
}

// بارگذاری تم ذخیره شده
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        changeTheme(savedTheme);
    }
    
    // فعال‌سازی tooltip
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            placement: 'top',
            trigger: 'hover'
        });
    });
});

// تابع نمایش پیام
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    
    const toastId = 'toast_' + Date.now();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, {
        delay: 3000
    });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// ایجاد container برای toast
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}