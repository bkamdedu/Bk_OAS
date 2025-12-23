<?php
if (!isLoggedIn()) {
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = connectDB();

// ساخت کوئری بر اساس وضعیت
$whereClauses = ["(t.assigned_to = :user_id OR t.created_by = :user_id)"];
$params = [':user_id' => $_SESSION['user_id']];

if ($status != 'all') {
    $whereClauses[] = "t.status = :status";
    $params[':status'] = $status;
}

// جستجو
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $whereClauses[] = "(t.title LIKE :search OR t.description LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$whereSQL = implode(' AND ', $whereClauses);

// گرفتن تسک‌ها
$query = "SELECT t.*, 
          u1.full_name as assignee_name,
          u2.full_name as creator_name,
          COUNT(c.id) as comment_count
          FROM tasks t
          LEFT JOIN users u1 ON t.assigned_to = u1.id
          LEFT JOIN users u2 ON t.created_by = u2.id
          LEFT JOIN task_comments c ON t.id = c.task_id
          WHERE $whereSQL
          GROUP BY t.id
          ORDER BY 
            CASE WHEN t.due_date < DATE('now') THEN 0 ELSE 1 END,
            t.priority ASC,
            t.due_date ASC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_TEXT);
}

$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$tasks = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $tasks[] = $row;
}

// تعداد کل
$countQuery = "SELECT COUNT(*) as total FROM tasks t WHERE $whereSQL";
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, SQLITE3_TEXT);
}
$countResult = $countStmt->execute();
$totalRows = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
$totalPages = ceil($totalRows / $limit);

// آمار
$statsQuery = $db->query("SELECT 
    SUM(CASE WHEN assigned_to = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as assigned_to_me,
    SUM(CASE WHEN created_by = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as created_by_me,
    SUM(CASE WHEN status = 'pending' AND assigned_to = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' AND assigned_to = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'completed' AND assigned_to = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as completed_tasks
    FROM tasks");

$stats = $statsQuery->fetchArray(SQLITE3_ASSOC);

closeDB($db);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-tasks"></i> تسک‌های من</h5>
        <a href="index.php?page=tasks&action=create" class="btn btn-primary">
            <i class="fas fa-plus"></i> تسک جدید
        </a>
    </div>
    
    <div class="card-body">
        <!-- آمار -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card bg-primary text-white">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['assigned_to_me']; ?></h3>
                        <p>اختصاص داده شده به من</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success text-white">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['created_by_me']; ?></h3>
                        <p>ایجاد شده توسط من</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-warning text-white">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending_tasks']; ?></h3>
                        <p>در انتظار</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-info text-white">
                    <div class="stat-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['in_progress_tasks']; ?></h3>
                        <p>در حال انجام</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- فیلترها -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="page" value="tasks">
                    <input type="hidden" name="action" value="my-tasks">
                    
                    <div class="col-md-3">
                        <label class="form-label">وضعیت</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>همه</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                            <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">اولویت</label>
                        <select name="priority" class="form-select">
                            <option value="">همه</option>
                            <option value="1">فوری</option>
                            <option value="2">بالا</option>
                            <option value="3">عادی</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">از تاریخ</label>
                        <input type="date" name="from_date" class="form-control" 
                               value="<?php echo $_GET['from_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">تا تاریخ</label>
                        <input type="date" name="to_date" class="form-control" 
                               value="<?php echo $_GET['to_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">جستجو</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="جستجوی عنوان یا توضیحات..."
                                   value="<?php echo $_GET['search'] ?? ''; ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> اعمال فیلتر
                            </button>
                            <a href="index.php?page=tasks&action=my-tasks" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> بازنشانی
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- لیست تسک‌ها -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>عنوان</th>
                        <th>وضعیت</th>
                        <th>اولویت</th>
                        <th>مهلت</th>
                        <th>اختصاص داده شده به</th>
                        <th>نظرات</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $index => $task): ?>
                    <?php
                    $dueDate = new DateTime($task['due_date']);
                    $today = new DateTime();
                    $isOverdue = $dueDate < $today && $task['status'] != 'completed';
                    ?>
                    <tr class="<?php echo $isOverdue ? 'table-danger' : ''; ?>">
                        <td><?php echo ($page - 1) * $limit + $index + 1; ?></td>
                        <td>
                            <a href="index.php?page=tasks&action=view&id=<?php echo $task['id']; ?>" 
                               class="text-decoration-none">
                                <strong><?php echo $task['title']; ?></strong>
                            </a>
                            <br>
                            <small class="text-muted"><?php echo $task['task_number']; ?></small>
                            <?php if ($task['created_by'] == $_SESSION['user_id']): ?>
                            <span class="badge bg-success">ایجاد شده توسط من</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select class="form-select form-select-sm status-select" 
                                    data-task-id="<?php echo $task['id']; ?>"
                                    style="width: 120px;">
                                <option value="pending" <?php echo $task['status'] == 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                                <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                                <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                            </select>
                        </td>
                        <td>
                            <?php
                            $priorityColors = ['danger', 'warning', 'primary'];
                            $priorityTexts = ['فوری', 'بالا', 'عادی'];
                            ?>
                            <span class="badge bg-<?php echo $priorityColors[$task['priority'] - 1]; ?>">
                                <?php echo $priorityTexts[$task['priority'] - 1]; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('Y/m/d', strtotime($task['due_date'])); ?>
                            <?php if ($isOverdue): ?>
                            <br>
                            <small class="text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                تأخیر: <?php echo $today->diff($dueDate)->days; ?> روز
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($task['assignee_name']): ?>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm"><?php echo mb_substr($task['assignee_name'], 0, 1); ?></div>
                                <span class="ms-2"><?php echo $task['assignee_name']; ?></span>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">بدون اختصاص</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <i class="fas fa-comment"></i>
                                <?php echo $task['comment_count']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=tasks&action=view&id=<?php echo $task['id']; ?>" 
                                   class="btn btn-outline-primary" title="مشاهده">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="index.php?page=tasks&action=edit&id=<?php echo $task['id']; ?>" 
                                   class="btn btn-outline-warning" title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-outline-info" 
                                        onclick="addComment(<?php echo $task['id']; ?>)" 
                                        title="افزودن نظر">
                                    <i class="fas fa-comment-medical"></i>
                                </button>
                                <button class="btn btn-outline-success" 
                                        onclick="updateProgress(<?php echo $task['id']; ?>)" 
                                        title="به‌روزرسانی پیشرفت">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ تسکی یافت نشد</p>
                                <a href="index.php?page=tasks&action=create" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> ایجاد اولین تسک
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="صفحه‌بندی" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=tasks&action=my-tasks&page_num=<?php echo $page - 1; ?>&status=<?php echo $status; ?>">
                        قبلی
                    </a>
                </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=tasks&action=my-tasks&page_num=<?php echo $i; ?>&status=<?php echo $status; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=tasks&action=my-tasks&page_num=<?php echo $page + 1; ?>&status=<?php echo $status; ?>">
                        بعدی
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- مودال افزودن نظر -->
<div class="modal fade" id="addCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن نظر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="commentForm">
                    <input type="hidden" id="commentTaskId">
                    <div class="mb-3">
                        <label class="form-label">متن نظر</label>
                        <textarea class="form-control" id="commentText" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">پیوست</label>
                        <input type="file" class="form-control" id="commentAttachment">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" onclick="submitComment()">ثبت نظر</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال به‌روزرسانی پیشرفت -->
<div class="modal fade" id="updateProgressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">به‌روزرسانی پیشرفت</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="progressForm">
                    <input type="hidden" id="progressTaskId">
                    <div class="mb-3">
                        <label class="form-label">درصد پیشرفت</label>
                        <input type="range" class="form-range" id="progressRange" min="0" max="100" step="5">
                        <div class="d-flex justify-content-between">
                            <small>0%</small>
                            <span id="progressValue" class="badge bg-primary">0%</span>
                            <small>100%</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیحات</label>
                        <textarea class="form-control" id="progressDescription" rows="3" 
                                  placeholder="گزارش کار انجام شده..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" onclick="submitProgress()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<script>
// تغییر وضعیت تسک
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const taskId = this.dataset.taskId;
        const newStatus = this.value;
        
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
                
                if (newStatus === 'completed') {
                    // نمایش مودال تکمیل تسک
                    showCompletionModal(taskId);
                }
            } else {
                showToast('خطا در به‌روزرسانی وضعیت', 'danger');
                this.value = this.dataset.previousValue;
            }
        })
        .catch(error => {
            showToast('خطا در ارتباط با سرور', 'danger');
            this.value = this.dataset.previousValue;
        });
        
        this.dataset.previousValue = newStatus;
    });
    
    select.dataset.previousValue = select.value;
});

// افزودن نظر
function addComment(taskId) {
    document.getElementById('commentTaskId').value = taskId;
    document.getElementById('commentText').value = '';
    document.getElementById('commentAttachment').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('addCommentModal'));
    modal.show();
}

function submitComment() {
    const taskId = document.getElementById('commentTaskId').value;
    const commentText = document.getElementById('commentText').value;
    const attachment = document.getElementById('commentAttachment').files[0];
    
    if (!commentText.trim()) {
        showToast('لطفا متن نظر را وارد کنید', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('comment', commentText);
    if (attachment) {
        formData.append('attachment', attachment);
    }
    
    fetch('modules/tasks/add_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('نظر با موفقیت ثبت شد', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCommentModal'));
            modal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('خطا در ثبت نظر', 'danger');
        }
    });
}

// به‌روزرسانی پیشرفت
function updateProgress(taskId) {
    document.getElementById('progressTaskId').value = taskId;
    
    // گرفتن پیشرفت فعلی
    fetch(`modules/tasks/get_task_progress.php?id=${taskId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('progressRange').value = data.progress || 0;
            document.getElementById('progressValue').textContent = (data.progress || 0) + '%';
        });
    
    const modal = new bootstrap.Modal(document.getElementById('updateProgressModal'));
    modal.show();
}

document.getElementById('progressRange').addEventListener('input', function() {
    document.getElementById('progressValue').textContent = this.value + '%';
});

function submitProgress() {
    const taskId = document.getElementById('progressTaskId').value;
    const progress = document.getElementById('progressRange').value;
    const description = document.getElementById('progressDescription').value;
    
    fetch('modules/tasks/update_progress.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            task_id: taskId,
            progress: progress,
            description: description
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('پیشرفت به‌روزرسانی شد', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('updateProgressModal'));
            modal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('خطا در به‌روزرسانی پیشرفت', 'danger');
        }
    });
}

// مودال تکمیل تسک
function showCompletionModal(taskId) {
    const modalHTML = `
        <div class="modal fade" id="completionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">تکمیل تسک</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>آیا از تکمیل این تسک اطمینان دارید؟</p>
                        <div class="mb-3">
                            <label class="form-label">گزارش نهایی</label>
                            <textarea class="form-control" id="completionReport" rows="4" 
                                      placeholder="نتایج و دستاوردهای تسک..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">فایل‌های مرتبط</label>
                            <input type="file" class="form-control" id="completionFiles" multiple>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-success" onclick="completeTask(${taskId})">تکمیل تسک</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('completionModal'));
    modal.show();
}

function completeTask(taskId) {
    const report = document.getElementById('completionReport').value;
    const files = document.getElementById('completionFiles').files;
    
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('report', report);
    
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    
    fetch('modules/tasks/complete_task.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('تسک با موفقیت تکمیل شد', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('completionModal'));
            modal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('خطا در تکمیل تسک', 'danger');
        }
    });
}
</script>

<style>
.stat-card {
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 2.5rem;
    margin-left: 15px;
    opacity: 0.8;
}

.stat-content h3 {
    margin: 0;
    font-weight: bold;
}

.stat-content p {
    margin: 5px 0 0;
    opacity: 0.9;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.form-range::-webkit-slider-thumb {
    background: var(--primary-color);
}

.form-range::-moz-range-thumb {
    background: var(--primary-color);
}
</style>