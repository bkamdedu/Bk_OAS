<?php
if (!hasPermission('tasks_create')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$db = connectDB();

// گرفتن لیست کاربران برای اختصاص
$usersResult = $db->query("SELECT id, full_name FROM users WHERE status = 1 ORDER BY full_name");
$users = [];
while ($row = $usersResult->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

closeDB($db);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // پردازش فرم
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $priority = intval($_POST['priority']);
    $dueDate = $_POST['due_date'];
    $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    $tags = sanitizeInput($_POST['tags']);
    
    // تولید شماره تسک
    $taskNumber = generateUniqueNumber('TASK', 'tasks', 'task_number');
    
    $db = connectDB();
    
    $stmt = $db->prepare("INSERT INTO tasks 
                         (task_number, title, description, priority, due_date, assigned_to, created_by, tags) 
                         VALUES (:task_num, :title, :desc, :priority, :due_date, :assigned_to, :user_id, :tags)");
    
    $stmt->bindValue(':task_num', $taskNumber, SQLITE3_TEXT);
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
    $stmt->bindValue(':priority', $priority, SQLITE3_INTEGER);
    $stmt->bindValue(':due_date', $dueDate, SQLITE3_TEXT);
    $stmt->bindValue(':assigned_to', $assignedTo, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':tags', $tags, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        $taskId = $db->lastInsertRowID();
        
        // ایجاد نوتیفیکیشن برای کاربر اختصاص داده شده
        if ($assignedTo) {
            createNotification(
                $assignedTo,
                'تسک جدید',
                "تسک جدیدی به شما اختصاص داده شد: $title",
                'info',
                "index.php?page=tasks&action=view&id=$taskId"
            );
        }
        
        // لاگ فعالیت
        logActivity(
            $_SESSION['user_id'],
            'create_task',
            "ایجاد تسک جدید: $title (شماره: $taskNumber)"
        );
        
        $success = "تسک با شماره $taskNumber با موفقیت ایجاد شد";
        
        // ریدایرکت به صفحه تسک
        echo '<script>setTimeout(() => window.location.href = "index.php?page=tasks&action=view&id=' . $taskId . '", 2000);</script>';
    } else {
        $error = "خطا در ایجاد تسک";
    }
    
    closeDB($db);
}
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> ایجاد تسک جدید</h5>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
            <div class="spinner-border spinner-border-sm ms-2" role="status">
                <span class="visually-hidden">در حال انتقال...</span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="createTaskForm">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="title" class="form-label">عنوان تسک <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required
                           placeholder="عنوان واضح و کامل تسک را وارد کنید">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="priority" class="form-label">اولویت <span class="text-danger">*</span></label>
                    <select class="form-select" id="priority" name="priority" required>
                        <option value="3">عادی</option>
                        <option value="2">بالا</option>
                        <option value="1">فوری</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="due_date" class="form-label">تاریخ مهلت <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="due_date" name="due_date" required
                           min="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text">تاریخ مهلت باید از امروز به بعد باشد</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="assigned_to" class="form-label">اختصاص به</label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="">انتخاب کاربر...</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo $user['full_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">در صورت عدم انتخاب، تسک به خود شما اختصاص می‌یابد</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">شرح تسک</label>
                <textarea class="form-control" id="description" name="description" rows="6" 
                          placeholder="شرح کامل تسک، اهداف، مراحل و انتظارات را توضیح دهید"></textarea>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">حداقل ۱۰۰ کاراکتر توصیه می‌شود</small>
                    <small><span id="charCount">0</span> / 2000 کاراکتر</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="tags" class="form-label">برچسب‌ها</label>
                    <input type="text" class="form-control" id="tags" name="tags"
                           placeholder="کلمات کلیدی را با کاما جدا کنید">
                    <div class="form-text">برای سازماندهی بهتر تسک‌ها</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">اضافه کردن فایل</label>
                    <div class="input-group">
                        <input type="file" class="form-control" id="attachments" multiple>
                        <button class="btn btn-outline-secondary" type="button" onclick="uploadFiles()">
                            <i class="fas fa-upload"></i>
                        </button>
                    </div>
                    <div class="form-text">حداکثر ۵ فایل، هر فایل تا ۱۰ مگابایت</div>
                    <div id="fileList" class="mt-2"></div>
                    <input type="hidden" name="attachments_data" id="attachments_data">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="estimated_hours" class="form-label">زمان تخمینی (ساعت)</label>
                    <input type="number" class="form-control" id="estimated_hours" name="estimated_hours" 
                           min="1" max="1000" step="0.5" value="8">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="dependencies" class="form-label">وابستگی‌ها</label>
                    <select class="form-select" id="dependencies" name="dependencies[]" multiple>
                        <option value="">تسک‌های وابسته را انتخاب کنید</option>
                        <!-- لیست تسک‌های موجود -->
                    </select>
                    <div class="form-text">برای انتخاب چندگانه، کلید Ctrl را نگه دارید</div>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">نوع تسک</label>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="task_type" value="normal" checked>
                            <label class="form-check-label">عادی</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="task_type" value="project">
                            <label class="form-check-label">پروژه‌ای</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="task_type" value="recurring">
                            <label class="form-check-label">تکرار شونده</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="task_type" value="urgent">
                            <label class="form-check-label">فوری</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره تسک
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="saveAsDraft()">
                        <i class="fas fa-file-alt"></i> ذخیره پیش‌نویس
                    </button>
                </div>
                <div>
                    <a href="index.php?page=tasks" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> انصراف
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- قالب‌های آماده -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-copy"></i> قالب‌های آماده</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="template-card" onclick="loadTemplate('meeting')">
                    <div class="template-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <h6>جلسه</h6>
                    <small class="text-muted">قالب جلسات داخلی</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="template-card" onclick="loadTemplate('report')">
                    <div class="template-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h6>گزارش</h6>
                    <small class="text-muted">قالب تهیه گزارش</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="template-card" onclick="loadTemplate('development')">
                    <div class="template-icon">
                        <i class="fas fa-code"></i>
                    </div>
                    <h6>توسعه</h6>
                    <small class="text-muted">قالب توسعه نرم‌افزار</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="template-card" onclick="loadTemplate('support')">
                    <div class="template-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h6>پشتیبانی</h6>
                    <small class="text-muted">قالب درخواست پشتیبانی</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// شمارنده کاراکترها
document.getElementById('description').addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('charCount').textContent = count;
    
    if (count < 100) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// آپلود فایل‌ها
function uploadFiles() {
    const files = document.getElementById('attachments').files;
    const fileList = document.getElementById('fileList');
    const attachmentsData = [];
    
    fileList.innerHTML = '';
    
    if (files.length > 5) {
        showToast('حداکثر ۵ فایل مجاز است', 'warning');
        return;
    }
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        if (file.size > 10 * 1024 * 1024) {
            showToast(`فایل ${file.name} بیشتر از ۱۰ مگابایت است`, 'warning');
            continue;
        }
        
        // شبیه‌سازی آپلود
        const reader = new FileReader();
        reader.onload = function(e) {
            attachmentsData.push({
                name: file.name,
                type: file.type,
                size: file.size,
                data: e.target.result.split(',')[1]
            });
            
            // نمایش فایل
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item d-flex justify-content-between align-items-center p-2 border rounded mb-2';
            fileItem.innerHTML = `
                <div>
                    <i class="fas fa-file me-2"></i>
                    ${file.name}
                    <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            fileList.appendChild(fileItem);
            
            // ذخیره در hidden field
            document.getElementById('attachments_data').value = JSON.stringify(attachmentsData);
        };
        
        reader.readAsDataURL(file);
    }
}

function removeFile(button) {
    button.closest('.file-item').remove();
    // به‌روزرسانی لیست فایل‌ها
    // این بخش نیاز به پیاده‌سازی کامل‌تر دارد
}

// بارگذاری قالب
function loadTemplate(templateName) {
    const templates = {
        'meeting': {
            title: 'برگزاری جلسه تیم',
            description: 'موضوع جلسه: \n\nاعضای شرکت کننده: \n\nدستور جلسه: \n\nخروجی مورد انتظار: \n\nمکان و زمان: \n\nتوضیحات اضافی:',
            tags: 'جلسه,تیم,برنامه‌ریزی',
            priority: 2
        },
        'report': {
            title: 'تهیه گزارش ماهانه',
            description: 'عنوان گزارش: \n\nدوره زمانی: \n\nبخش‌های گزارش: \n\nمنابع اطلاعاتی: \n\nفرمت خروجی: \n\nمهلت تحویل:',
            tags: 'گزارش,تحلیل,داده',
            priority: 3
        },
        'development': {
            title: 'توسعه ماژول جدید',
            description: 'نام ماژول: \n\nتوضیحات فنی: \n\nنیازمندی‌ها: \n\nتکنولوژی‌ها: \n\nزمان تخمینی: \n\nوابستگی‌ها:',
            tags: 'توسعه,برنامه‌نویسی,ماژول',
            priority: 2
        },
        'support': {
            title: 'درخواست پشتیبانی',
            description: 'شرح مشکل: \n\nاولویت: \n\nتأثیر بر کار: \n\nاقدامات انجام شده: \n\nاطلاعات سیستم: \n\nتاریخ و زمان وقوع:',
            tags: 'پشتیبانی,مشکل,راه‌اندازی',
            priority: 1
        }
    };
    
    const template = templates[templateName];
    if (template) {
        document.getElementById('title').value = template.title;
        document.getElementById('description').value = template.description;
        document.getElementById('tags').value = template.tags;
        document.getElementById('priority').value = template.priority;
        
        showToast('قالب بارگذاری شد', 'success');
    }
}

// ذخیره پیش‌نویس
function saveAsDraft() {
    const form = document.getElementById('createTaskForm');
    const draftButton = document.createElement('input');
    draftButton.type = 'hidden';
    draftButton.name = 'draft';
    draftButton.value = '1';
    form.appendChild(draftButton);
    form.submit();
}

// تاریخ مهلت پیش‌فرض (سه روز بعد)
const today = new Date();
const threeDaysLater = new Date(today);
threeDaysLater.setDate(today.getDate() + 3);
document.getElementById('due_date').valueAsDate = threeDaysLater;
</script>

<style>
.template-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.template-card:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.template-card:hover .text-muted {
    color: rgba(255,255,255,0.8) !important;
}

.template-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.file-item {
    background: #f8f9fa;
}

.char-counter {
    font-size: 12px;
}
</style>