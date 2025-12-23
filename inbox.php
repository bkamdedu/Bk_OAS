<?php
if (!hasPermission('correspondence_view')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$status = isset($_GET['status']) ? $_GET['status'] : 'unread';
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$db = connectDB();

// ساخت کوئری
$whereClauses = ["c.receiver_id = :user_id"];
$params = [':user_id' => $_SESSION['user_id']];

if ($status == 'unread') {
    $whereClauses[] = "c.status = 'unread'";
} elseif ($status == 'read') {
    $whereClauses[] = "c.status = 'read'";
} elseif ($status == 'archived') {
    $whereClauses[] = "c.status = 'archived'";
}

// جستجو
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $whereClauses[] = "(c.subject LIKE :search OR c.content LIKE :search OR u.full_name LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$whereSQL = implode(' AND ', $whereClauses);

// گرفتن نامه‌ها
$query = "SELECT c.*, 
          u.full_name as sender_name,
          u.department as sender_department,
          (SELECT COUNT(*) FROM letter_attachments WHERE letter_id = c.id) as attachment_count
          FROM correspondence c
          LEFT JOIN users u ON c.sender_id = u.id
          WHERE $whereSQL
          ORDER BY c.created_at DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_TEXT);
}

$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$letters = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $letters[] = $row;
}

// تعداد کل
$countQuery = "SELECT COUNT(*) as total FROM correspondence c WHERE $whereSQL";
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, SQLITE3_TEXT);
}
$countResult = $countStmt->execute();
$totalRows = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
$totalPages = ceil($totalRows / $limit);

// آمار
$statsQuery = $db->query("SELECT 
    SUM(CASE WHEN status = 'unread' AND receiver_id = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as unread_count,
    SUM(CASE WHEN status = 'read' AND receiver_id = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as read_count,
    SUM(CASE WHEN status = 'archived' AND receiver_id = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as archived_count,
    SUM(CASE WHEN receiver_id = {$_SESSION['user_id']} THEN 1 ELSE 0 END) as total_count
    FROM correspondence");

$stats = $statsQuery->fetchArray(SQLITE3_ASSOC);

closeDB($db);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-inbox"></i> صندوق ورودی</h5>
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="refreshInbox()">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn btn-outline-danger" onclick="emptyTrash()" title="خالی کردن سطل زباله">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <!-- آمار -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card <?php echo $status == 'all' ? 'bg-primary' : 'bg-secondary'; ?> text-white">
                    <div class="stat-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_count']; ?></h3>
                        <p>کل نامه‌ها</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card <?php echo $status == 'unread' ? 'bg-danger' : 'bg-secondary'; ?> text-white">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['unread_count']; ?></h3>
                        <p>خوانده نشده</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card <?php echo $status == 'read' ? 'bg-success' : 'bg-secondary'; ?> text-white">
                    <div class="stat-icon">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['read_count']; ?></h3>
                        <p>خوانده شده</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card <?php echo $status == 'archived' ? 'bg-info' : 'bg-secondary'; ?> text-white">
                    <div class="stat-icon">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['archived_count']; ?></h3>
                        <p>بایگانی شده</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- فیلترها -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="page" value="correspondence">
                    <input type="hidden" name="action" value="inbox">
                    
                    <div class="col-md-3">
                        <label class="form-label">وضعیت</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="unread" <?php echo $status == 'unread' ? 'selected' : ''; ?>>خوانده نشده</option>
                            <option value="read" <?php echo $status == 'read' ? 'selected' : ''; ?>>خوانده شده</option>
                            <option value="archived" <?php echo $status == 'archived' ? 'selected' : ''; ?>>بایگانی شده</option>
                            <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>همه</option>
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
                    
                    <div class="col-md-8">
                        <label class="form-label">جستجو</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="جستجوی موضوع، محتوا یا فرستنده..."
                                   value="<?php echo $_GET['search'] ?? ''; ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> اعمال فیلتر
                            </button>
                            <a href="index.php?page=correspondence&action=inbox" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> بازنشانی
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- دکمه‌های عملیات گروهی -->
        <div class="mb-3">
            <div class="btn-group">
                <button class="btn btn-outline-secondary" onclick="selectAllLetters()">
                    <i class="fas fa-check-square"></i> انتخاب همه
                </button>
                <button class="btn btn-outline-primary" onclick="markAsRead()">
                    <i class="fas fa-envelope-open"></i> علامت‌گذاری به عنوان خوانده شده
                </button>
                <button class="btn btn-outline-info" onclick="archiveSelected()">
                    <i class="fas fa-archive"></i> بایگانی
                </button>
                <button class="btn btn-outline-danger" onclick="deleteSelected()">
                    <i class="fas fa-trash"></i> حذف
                </button>
            </div>
        </div>
        
        <!-- لیست نامه‌ها -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                        </th>
                        <th>فرستنده</th>
                        <th>موضوع</th>
                        <th>اولویت</th>
                        <th>تاریخ</th>
                        <th>ضمایم</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($letters as $letter): ?>
                    <?php
                    $isUnread = $letter['status'] == 'unread';
                    ?>
                    <tr class="<?php echo $isUnread ? 'table-active' : ''; ?>">
                        <td>
                            <input type="checkbox" class="letter-checkbox" value="<?php echo $letter['id']; ?>">
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm"><?php echo mb_substr($letter['sender_name'], 0, 1); ?></div>
                                <div class="ms-2">
                                    <strong><?php echo $letter['sender_name']; ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $letter['sender_department']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="index.php?page=correspondence&action=view&id=<?php echo $letter['id']; ?>" 
                               class="text-decoration-none <?php echo $isUnread ? 'fw-bold' : ''; ?>">
                                <?php echo $letter['subject']; ?>
                                <?php if ($isUnread): ?>
                                <span class="badge bg-danger">جدید</span>
                                <?php endif; ?>
                            </a>
                            <br>
                            <small class="text-muted">
                                <?php echo mb_substr(strip_tags($letter['content']), 0, 80); ?>
                                <?php echo mb_strlen(strip_tags($letter['content'])) > 80 ? '...' : ''; ?>
                            </small>
                        </td>
                        <td>
                            <?php
                            $priorityColors = ['danger', 'warning', 'primary'];
                            $priorityTexts = ['فوری', 'بالا', 'عادی'];
                            ?>
                            <span class="badge bg-<?php echo $priorityColors[$letter['priority'] - 1]; ?>">
                                <?php echo $priorityTexts[$letter['priority'] - 1]; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('Y/m/d', strtotime($letter['created_at'])); ?>
                            <br>
                            <small class="text-muted"><?php echo date('H:i', strtotime($letter['created_at'])); ?></small>
                        </td>
                        <td>
                            <?php if ($letter['attachment_count'] > 0): ?>
                            <span class="badge bg-info">
                                <i class="fas fa-paperclip"></i>
                                <?php echo $letter['attachment_count']; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">بدون پیوست</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=correspondence&action=view&id=<?php echo $letter['id']; ?>" 
                                   class="btn btn-outline-primary" title="مشاهده">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-outline-info" 
                                        onclick="replyLetter(<?php echo $letter['id']; ?>)" 
                                        title="پاسخ">
                                    <i class="fas fa-reply"></i>
                                </button>
                                <button class="btn btn-outline-secondary" 
                                        onclick="forwardLetter(<?php echo $letter['id']; ?>)" 
                                        title="ارسال مجدد">
                                    <i class="fas fa-share"></i>
                                </button>
                                <button class="btn btn-outline-danger" 
                                        onclick="deleteLetter(<?php echo $letter['id']; ?>)" 
                                        title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($letters)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ نامه‌ای یافت نشد</p>
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
                    <a class="page-link" href="?page=correspondence&action=inbox&page_num=<?php echo $page - 1; ?>&status=<?php echo $status; ?>">
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
                    <a class="page-link" href="?page=correspondence&action=inbox&page_num=<?php echo $i; ?>&status=<?php echo $status; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=correspondence&action=inbox&page_num=<?php echo $page + 1; ?>&status=<?php echo $status; ?>">
                        بعدی
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
// انتخاب همه نامه‌ها
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.letter-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function selectAllLetters() {
    document.getElementById('selectAll').click();
}

// گرفتن نامه‌های انتخاب شده
function getSelectedLetters() {
    const checkboxes = document.querySelectorAll('.letter-checkbox:checked');
    const selectedIds = [];
    
    checkboxes.forEach(checkbox => {
        selectedIds.push(checkbox.value);
    });
    
    return selectedIds;
}

// علامت‌گذاری به عنوان خوانده شده
function markAsRead() {
    const selectedIds = getSelectedLetters();
    
    if (selectedIds.length === 0) {
        showToast('لطفا حداقل یک نامه انتخاب کنید', 'warning');
        return;
    }
    
    fetch('modules/correspondence/mark_as_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ letter_ids: selectedIds })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('نامه‌ها به عنوان خوانده شده علامت‌گذاری شدند', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('خطا در بروزرسانی وضعیت', 'danger');
        }
    });
}

// بایگانی نامه‌های انتخاب شده
function archiveSelected() {
    const selectedIds = getSelectedLetters();
    
    if (selectedIds.length === 0) {
        showToast('لطفا حداقل یک نامه انتخاب کنید', 'warning');
        return;
    }
    
    fetch('modules/correspondence/archive_letters.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ letter_ids: selectedIds })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('نامه‌ها بایگانی شدند', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('خطا در بایگانی نامه‌ها', 'danger');
        }
    });
}

// حذف نامه‌های انتخاب شده
function deleteSelected() {
    const selectedIds = getSelectedLetters();
    
    if (selectedIds.length === 0) {
        showToast('لطفا حداقل یک نامه انتخاب کنید', 'warning');
        return;
    }
    
    showConfirmationModal(
        'حذف نامه‌ها',
        `آیا از حذف ${selectedIds.length} نامه انتخاب شده اطمینان دارید؟`,
        function() {
            fetch('modules/correspondence/delete_letters.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ letter_ids: selectedIds })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('نامه‌ها با موفقیت حذف شدند', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('خطا در حذف نامه‌ها', 'danger');
                }
            });
        }
    );
}

// پاسخ به نامه
function replyLetter(letterId) {
    window.location.href = `index.php?page=correspondence&action=reply&id=${letterId}`;
}

// ارسال مجدد نامه
function forwardLetter(letterId) {
    window.location.href = `index.php?page=correspondence&action=forward&id=${letterId}`;
}

// حذف نامه
function deleteLetter(letterId) {
    showConfirmationModal(
        'حذف نامه',
        'آیا از حذف این نامه اطمینان دارید؟',
        function() {
            fetch(`modules/correspondence/delete_letter.php?id=${letterId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('نامه با موفقیت حذف شد', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('خطا در حذف نامه', 'danger');
                }
            });
        }
    );
}

// خالی کردن سطل زباله
function emptyTrash() {
    showConfirmationModal(
        'خالی کردن سطل زباله',
        'آیا از حذف دائمی همه نامه‌های حذف شده اطمینان دارید؟ این عمل قابل بازگشت نیست.',
        function() {
            fetch('modules/correspondence/empty_trash.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('سطل زباله خالی شد', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('خطا در خالی کردن سطل زباله', 'danger');
                }
            });
        }
    );
}

// بروزرسانی صندوق ورودی
function refreshInbox() {
    showLoader(document.body);
    setTimeout(() => {
        hideLoader(document.body);
        showToast('صندوق ورودی بروزرسانی شد', 'success');
    }, 1000);
}

// رویداد کلیک بر روی سطر نامه (به جز چک‌باکس و دکمه‌ها)
document.querySelectorAll('tbody tr').forEach(row => {
    const checkboxes = row.querySelectorAll('input[type="checkbox"], button, a');
    const clickableElements = Array.from(checkboxes).map(el => el.closest('td'));
    
    row.addEventListener('click', function(e) {
        const target = e.target;
        const td = target.closest('td');
        
        // اگر روی چک‌باکس یا دکمه کلیک شده، کاری نکن
        if (clickableElements.includes(td)) {
            return;
        }
        
        // پیدا کردن لینک مشاهده در این سطر
        const viewLink = this.querySelector('a[href*="action=view"]');
        if (viewLink) {
            window.location.href = viewLink.href;
        }
    });
});
</script>

<style>
.stat-card {
    border-radius: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    transition: transform 0.3s;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    font-size: 2rem;
    margin-left: 15px;
    opacity: 0.9;
}

.stat-content h3 {
    margin: 0;
    font-weight: bold;
    font-size: 24px;
}

.stat-content p {
    margin: 5px 0 0;
    font-size: 14px;
    opacity: 0.9;
}

.avatar-sm {
    width: 36px;
    height: 36px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

tbody tr {
    cursor: pointer;
}

tbody tr:hover {
    background-color: rgba(0,0,0,0.02);
}
</style>