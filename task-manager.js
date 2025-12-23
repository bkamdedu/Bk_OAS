// مدیریت تسک‌های سیستم

class TaskManager {
    constructor() {
        this.init();
    }
    
    init() {
        // بارگذاری تسک‌ها
        this.loadTasks();
        
        // مدیریت فرم ایجاد تسک
        this.initTaskForm();
        
        // مدیریت فیلترهای تسک
        this.initFilters();
        
        // مدیریت درگ و دراپ
        this.initDragAndDrop();
        
        // بارگذاری اولیه
        this.refreshTasks();
    }
    
    // بارگذاری تسک‌ها
    loadTasks() {
        const taskContainers = {
            'pending': document.getElementById('pendingTasks'),
            'in_progress': document.getElementById('inProgressTasks'),
            'completed': document.getElementById('completedTasks')
        };
        
        Object.keys(taskContainers).forEach(status => {
            const container = taskContainers[status];
            if (container) {
                this.loadTasksByStatus(status, container);
            }
        });
    }
    
    // بارگذاری تسک‌ها بر اساس وضعیت
    loadTasksByStatus(status, container) {
        fetch(`modules/tasks/get_tasks.php?status=${status}`)
            .then(response => response.json())
            .then(tasks => {
                container.innerHTML = '';
                
                if (tasks.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <p>هیچ تسکی یافت نشد</p>
                        </div>
                    `;
                    return;
                }
                
                tasks.forEach(task => {
                    const taskElement = this.createTaskElement(task);
                    container.appendChild(taskElement);
                });
                
                this.initTaskEvents();
            });
    }
    
    // ایجاد المان تسک
    createTaskElement(task) {
        const priorityClasses = {
            '1': 'task-priority-high',
            '2': 'task-priority-medium',
            '3': 'task-priority-low'
        };
        
        const priorityTexts = {
            '1': 'فوری',
            '2': 'بالا',
            '3': 'عادی'
        };
        
        const dueDate = new Date(task.due_date);
        const today = new Date();
        const daysDiff = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
        
        let dueDateClass = '';
        if (daysDiff < 0) {
            dueDateClass = 'text-danger';
        } else if (daysDiff <= 2) {
            dueDateClass = 'text-warning';
        }
        
        const taskElement = document.createElement('div');
        taskElement.className = `task-item ${priorityClasses[task.priority]}`;
        taskElement.dataset.taskId = task.id;
        taskElement.draggable = true;
        
        taskElement.innerHTML = `
            <div class="task-header">
                <div class="task-title">
                    <h6>${task.title}</h6>
                    <span class="task-number">#${task.task_number}</span>
                </div>
                <div class="task-actions">
                    <button class="btn btn-sm btn-outline-secondary task-edit" data-id="${task.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger task-delete" data-id="${task.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            <div class="task-body">
                <p class="task-description">${task.description || 'بدون توضیح'}</p>
                
                <div class="task-meta">
                    <div class="task-priority">
                        <i class="fas fa-flag"></i>
                        <span>${priorityTexts[task.priority]}</span>
                    </div>
                    
                    <div class="task-due-date ${dueDateClass}">
                        <i class="fas fa-calendar-alt"></i>
                        <span>${this.formatDate(task.due_date)}</span>
                    </div>
                    
                    <div class="task-assignee">
                        <i class="fas fa-user"></i>
                        <span>${task.assignee_name || 'اختصاص داده نشده'}</span>
                    </div>
                </div>
                
                <div class="task-progress">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" 
                             style="width: ${task.progress || 0}%">
                            ${task.progress || 0}%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="task-footer">
                <div class="task-tags">
                    ${this.generateTagsHTML(task.tags)}
                </div>
                <div class="task-comments">
                    <i class="fas fa-comment"></i>
                    <span>${task.comment_count || 0}</span>
                </div>
            </div>
        `;
        
        return taskElement;
    }
    
    // تولید HTML برای تگ‌ها
    generateTagsHTML(tags) {
        if (!tags) return '';
        
        const tagsArray = tags.split(',');
        return tagsArray.map(tag => 
            `<span class="badge bg-secondary">${tag.trim()}</span>`
        ).join('');
    }
    
    // فرمت تاریخ
    formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('fa-IR');
    }
    
    // مدیریت فرم ایجاد تسک
    initTaskForm() {
        const form = document.getElementById('createTaskForm');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.createTask(new FormData(form));
        });
        
        // تاریخ‌شکن فارسی
        this.initPersianDatePicker();
        
        // انتخاب کاربران
        this.initUserSelect();
    }
    
    // ایجاد تسک جدید
    createTask(formData) {
        fetch('modules/tasks/create_task.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast('تسک با موفقیت ایجاد شد', 'success');
                document.getElementById('createTaskForm').reset();
                
                // بستن مودال
                const modal = bootstrap.Modal.getInstance(document.getElementById('createTaskModal'));
                modal.hide();
                
                // رفرش لیست تسک‌ها
                this.refreshTasks();
            } else {
                showToast(result.message || 'خطا در ایجاد تسک', 'danger');
            }
        });
    }
    
    // مدیریت فیلترها
    initFilters() {
        const filters = document.querySelectorAll('.task-filter');
        filters.forEach(filter => {
            filter.addEventListener('change', () => {
                this.applyFilters();
            });
        });
        
        // دکمه بازنشانی فیلتر
        const resetBtn = document.getElementById('resetFilters');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetFilters();
            });
        }
    }
    
    // اعمال فیلترها
    applyFilters() {
        const filters = {
            priority: document.getElementById('filterPriority')?.value,
            assignee: document.getElementById('filterAssignee')?.value,
            fromDate: document.getElementById('filterFromDate')?.value,
            toDate: document.getElementById('filterToDate')?.value,
            search: document.getElementById('filterSearch')?.value
        };
        
        fetch('modules/tasks/filter_tasks.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(filters)
        })
        .then(response => response.json())
        .then(tasks => {
            this.displayFilteredTasks(tasks);
        });
    }
    
    // نمایش تسک‌های فیلتر شده
    displayFilteredTasks(tasks) {
        const container = document.getElementById('filteredTasks');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (tasks.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    هیچ تسکی با فیلترهای انتخاب شده یافت نشد
                </div>
            `;
            return;
        }
        
        tasks.forEach(task => {
            const taskElement = this.createTaskElement(task);
            container.appendChild(taskElement);
        });
        
        this.initTaskEvents();
    }
    
    // بازنشانی فیلترها
    resetFilters() {
        document.querySelectorAll('.task-filter').forEach(input => {
            if (input.type === 'text' || input.type === 'search') {
                input.value = '';
            } else if (input.type === 'select-one') {
                input.selectedIndex = 0;
            } else if (input.type === 'date') {
                input.value = '';
            }
        });
        
        this.refreshTasks();
    }
    
    // مدیریت درگ و دراپ
    initDragAndDrop() {
        const taskItems = document.querySelectorAll('.task-item');
        const columns = document.querySelectorAll('.task-column');
        
        taskItems.forEach(item => {
            item.addEventListener('dragstart', this.handleDragStart.bind(this));
        });
        
        columns.forEach(column => {
            column.addEventListener('dragover', this.handleDragOver.bind(this));
            column.addEventListener('drop', this.handleDrop.bind(this));
            column.addEventListener('dragenter', this.handleDragEnter.bind(this));
            column.addEventListener('dragleave', this.handleDragLeave.bind(this));
        });
    }
    
    handleDragStart(e) {
        e.dataTransfer.setData('text/plain', e.target.dataset.taskId);
        e.target.classList.add('dragging');
    }
    
    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }
    
    handleDrop(e) {
        e.preventDefault();
        const taskId = e.dataTransfer.getData('text/plain');
        const newStatus = e.target.closest('.task-column').dataset.status;
        
        // حذف کلاس dragging
        const draggingElement = document.querySelector('.task-item.dragging');
        if (draggingElement) {
            draggingElement.classList.remove('dragging');
        }
        
        // به‌روزرسانی وضعیت تسک
        this.updateTaskStatus(taskId, newStatus);
    }
    
    handleDragEnter(e) {
        e.preventDefault();
        e.target.classList.add('drag-over');
    }
    
    handleDragLeave(e) {
        e.target.classList.remove('drag-over');
    }
    
    // به‌روزرسانی وضعیت تسک
    updateTaskStatus(taskId, newStatus) {
        fetch('modules/tasks/update_task_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast('وضعیت تسک به‌روزرسانی شد', 'success');
                this.refreshTasks();
            } else {
                showToast('خطا در به‌روزرسانی وضعیت', 'danger');
            }
        });
    }
    
    // رویدادهای تسک
    initTaskEvents() {
        // ویرایش تسک
        document.querySelectorAll('.task-edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const taskId = e.target.closest('.task-edit').dataset.id;
                this.editTask(taskId);
            });
        });
        
        // حذف تسک
        document.querySelectorAll('.task-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const taskId = e.target.closest('.task-delete').dataset.id;
                this.deleteTask(taskId);
            });
        });
        
        // مشاهده جزئیات
        document.querySelectorAll('.task-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (!e.target.closest('.task-actions')) {
                    const taskId = item.dataset.taskId;
                    this.viewTaskDetails(taskId);
                }
            });
        });
    }
    
    // ویرایش تسک
    editTask(taskId) {
        fetch(`modules/tasks/get_task.php?id=${taskId}`)
            .then(response => response.json())
            .then(task => {
                this.populateEditForm(task);
                
                // نمایش مودال ویرایش
                const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
                modal.show();
            });
    }
    
    // پر کردن فرم ویرایش
    populateEditForm(task) {
        const form = document.getElementById('editTaskForm');
        if (!form) return;
        
        form.querySelector('[name="task_id"]').value = task.id;
        form.querySelector('[name="title"]').value = task.title;
        form.querySelector('[name="description"]').value = task.description || '';
        form.querySelector('[name="priority"]').value = task.priority;
        form.querySelector('[name="due_date"]').value = task.due_date;
        form.querySelector('[name="assigned_to"]').value = task.assigned_to || '';
        form.querySelector('[name="progress"]').value = task.progress || 0;
        form.querySelector('[name="tags"]').value = task.tags || '';
        
        // ثبت رویداد submit
        form.onsubmit = (e) => {
            e.preventDefault();
            this.updateTask(new FormData(form));
        };
    }
    
    // به‌روزرسانی تسک
    updateTask(formData) {
        fetch('modules/tasks/update_task.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast('تسک با موفقیت ویرایش شد', 'success');
                
                // بستن مودال
                const modal = bootstrap.Modal.getInstance(document.getElementById('editTaskModal'));
                modal.hide();
                
                // رفرش لیست تسک‌ها
                this.refreshTasks();
            } else {
                showToast(result.message || 'خطا در ویرایش تسک', 'danger');
            }
        });
    }
    
    // حذف تسک
    deleteTask(taskId) {
        showConfirmationModal(
            'حذف تسک',
            'آیا از حذف این تسک اطمینان دارید؟ این عمل قابل بازگشت نیست.',
            () => {
                fetch(`modules/tasks/delete_task.php?id=${taskId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showToast('تسک با موفقیت حذف شد', 'success');
                        this.refreshTasks();
                    } else {
                        showToast('خطا در حذف تسک', 'danger');
                    }
                });
            }
        );
    }
    
    // مشاهده جزئیات تسک
    viewTaskDetails(taskId) {
        fetch(`modules/tasks/get_task_details.php?id=${taskId}`)
            .then(response => response.json())
            .then(task => {
                this.displayTaskDetails(task);
                
                // نمایش مودال جزئیات
                const modal = new bootstrap.Modal(document.getElementById('taskDetailsModal'));
                modal.show();
            });
    }
    
    // نمایش جزئیات تسک
    displayTaskDetails(task) {
        const modalBody = document.getElementById('taskDetailsBody');
        if (!modalBody) return;
        
        const priorityTexts = {
            '1': 'فوری',
            '2': 'بالا',
            '3': 'عادی'
        };
        
        const statusTexts = {
            'pending': 'در انتظار',
            'in_progress': 'در حال انجام',
            'completed': 'تکمیل شده'
        };
        
        modalBody.innerHTML = `
            <div class="task-details">
                <div class="task-header-details">
                    <h4>${task.title}</h4>
                    <span class="task-number">#${task.task_number}</span>
                </div>
                
                <div class="task-meta-details">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <strong>اولویت:</strong>
                                <span class="badge bg-${task.priority == 1 ? 'danger' : task.priority == 2 ? 'warning' : 'info'}">
                                    ${priorityTexts[task.priority]}
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>وضعیت:</strong>
                                <span class="badge bg-${task.status == 'completed' ? 'success' : task.status == 'in_progress' ? 'warning' : 'secondary'}">
                                    ${statusTexts[task.status]}
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>تاریخ ایجاد:</strong>
                                <span>${this.formatDate(task.created_at)}</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="detail-item">
                                <strong>تاریخ مهلت:</strong>
                                <span class="${new Date(task.due_date) < new Date() ? 'text-danger' : ''}">
                                    ${this.formatDate(task.due_date)}
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>اختصاص داده شده به:</strong>
                                <span>${task.assignee_name || 'اختصاص داده نشده'}</span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>ایجاد شده توسط:</strong>
                                <span>${task.creator_name}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="task-description-details">
                    <h6>شرح:</h6>
                    <p>${task.description || 'بدون توضیح'}</p>
                </div>
                
                <div class="task-progress-details">
                    <h6>پیشرفت:</h6>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" 
                             style="width: ${task.progress || 0}%">
                            ${task.progress || 0}%
                        </div>
                    </div>
                </div>
                
                <div class="task-tags-details">
                    <h6>برچسب‌ها:</h6>
                    <div class="tags-container">
                        ${this.generateTagsHTML(task.tags) || '<span class="text-muted">بدون برچسب</span>'}
                    </div>
                </div>
                
                <div class="task-comments-section">
                    <h6>نظرات:</h6>
                    <div id="taskComments"></div>
                    <div class="add-comment mt-3">
                        <textarea class="form-control" placeholder="نظر خود را بنویسید..." rows="2"></textarea>
                        <button class="btn btn-primary mt-2" onclick="addComment(${task.id})">ارسال نظر</button>
                    </div>
                </div>
            </div>
        `;
        
        // بارگذاری نظرات
        this.loadComments(task.id);
    }
    
    // بارگذاری نظرات
    loadComments(taskId) {
        fetch(`modules/tasks/get_comments.php?task_id=${taskId}`)
            .then(response => response.json())
            .then(comments => {
                const container = document.getElementById('taskComments');
                if (!container) return;
                
                container.innerHTML = '';
                
                if (comments.length === 0) {
                    container.innerHTML = '<p class="text-muted">هنوز نظری ثبت نشده است</p>';
                    return;
                }
                
                comments.forEach(comment => {
                    const commentElement = this.createCommentElement(comment);
                    container.appendChild(commentElement);
                });
            });
    }
    
    // ایجاد المان نظر
    createCommentElement(comment) {
        const element = document.createElement('div');
        element.className = 'comment-item';
        element.innerHTML = `
            <div class="comment-header">
                <div class="comment-author">
                    <div class="avatar-small">${comment.author_name.charAt(0)}</div>
                    <div>
                        <strong>${comment.author_name}</strong>
                        <small class="text-muted">${this.formatTimeAgo(comment.created_at)}</small>
                    </div>
                </div>
            </div>
            <div class="comment-body">
                <p>${comment.content}</p>
            </div>
        `;
        return element;
    }
    
    // فرمت زمان گذشته
    formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'همین الان';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} دقیقه قبل`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} ساعت قبل`;
        if (seconds < 2592000) return `${Math.floor(seconds / 86400)} روز قبل`;
        
        return this.formatDate(dateString);
    }
    
    // تاریخ‌شکن فارسی
    initPersianDatePicker() {
        const dateInputs = document.querySelectorAll('.persian-date');
        dateInputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (typeof PersianDate !== 'undefined') {
                    const pd = new PersianDate();
                    this.value = pd.format('YYYY/MM/DD');
                }
            });
        });
    }
    
    // انتخاب کاربر
    initUserSelect() {
        const select = document.getElementById('assignedTo');
        if (!select) return;
        
        fetch('modules/tasks/get_users.php')
            .then(response => response.json())
            .then(users => {
                select.innerHTML = '<option value="">انتخاب کاربر...</option>';
                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.full_name;
                    select.appendChild(option);
                });
            });
    }
    
    // رفرش تسک‌ها
    refreshTasks() {
        this.loadTasks();
        this.initTaskEvents();
    }
}

// تابع اضافه کردن نظر
function addComment(taskId) {
    const commentInput = document.querySelector('#taskDetailsModal textarea');
    const content = commentInput.value.trim();
    
    if (!content) {
        showToast('لطفا متن نظر را وارد کنید', 'warning');
        return;
    }
    
    fetch('modules/tasks/add_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            task_id: taskId,
            content: content
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('نظر با موفقیت ثبت شد', 'success');
            commentInput.value = '';
            
            // بارگذاری مجدد نظرات
            const taskManager = new TaskManager();
            taskManager.loadComments(taskId);
        } else {
            showToast('خطا در ثبت نظر', 'danger');
        }
    });
}

// مقداردهی اولیه
let taskManager;
document.addEventListener('DOMContentLoaded', function() {
    taskManager = new TaskManager();
});