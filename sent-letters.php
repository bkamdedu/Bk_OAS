<?php
if (!hasPermission('correspondence_view')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$db = connectDB();

// ساخت کوئری
$whereClauses = ["c.sender_id = :user_id"];
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

// گرفتن نامه‌های ارسال شده
$query = "SELECT c.*, 
          u.full_name as receiver_name,
          u.department as receiver_department,
          (SELECT COUNT(*) FROM letter_attachments WHERE letter_id = c.id) as attachment_count,
          (SELECT status FROM correspondence_tracking WHERE letter_id = c.id AND user_id = c.receiver_id ORDER BY id DESC LIMIT 1) as delivery_status
          FROM correspondence c
          LEFT JOIN users u ON c.receiver_id = u.id
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

$sentLetters = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $sentLetters[] = $row;
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
    COUNT(*) as total_sent,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM correspondence_tracking WHERE letter_id = c.id AND status = 'read') THEN 1 ELSE 0 END) as read_count,
    SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM correspondence_tracking WHERE letter_id = c.id AND status = 'read') AND c.created_at < DATE('now', '-1 day') THEN 1 ELSE 0 END) as unread_count,
    SUM(CASE WHEN c.status = 'archived' THEN 1 ELSE 0 END) as archived_count
    FROM correspondence c
    WHERE c.sender_id = {$_SESSION['user_id']}");

$stats = $statsQuery->fetchArray(SQLITE3_ASSOC);

closeDB($db);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-paper-plane"></i> نامه‌های ارسال شده</h5>
        <a href="index.php?page=correspondence&action=create" class="btn btn-primary">
            <i class="fas fa-plus"></i> نامه جدید
        </a>
    </div>
    
    <div class="card-body">
        <!-- آمار -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card <?php echo $status == 'all' ? 'bg-primary' : 'bg-secondary'; ?> text-white">
                    <div class="stat-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_sent']; ?></h3>
                        <p>کل ارسال شده</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success text-white">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['read_count']; ?></h3>
                        <p>خوانده شده</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-warning text-white">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['unread_count']; ?></h3>
                        <p>در انتظار خواندن</p>
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
                    <input type="hidden" name="action" value="sent-letters">
                    
                    <div class="col-md-3">
                        <label class="form-label">وضعیت تحویل</label>
                        <select name="delivery_status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo isset($_GET['delivery_status']) && $_GET['delivery_status'] == 'all' ? 'selected' : ''; ?>>همه</option>
                            <option value="delivered" <?php echo isset($_GET['delivery_status']) && $_GET['delivery_status'] == 'delivered' ? 'selected' : ''; ?>>تحویل شده</option>
                            <option value="read" <?php echo isset($_GET['delivery_status']) && $_GET['delivery_status'] == 'read' ? 'selected' : ''; ?>>خوانده شده</option>
                            <option value="pending" <?php echo isset($_GET['delivery_status']) && $_GET['delivery_status'] == 'pending' ? 'selected' : ''; ?>>در انتظار</option>
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
                                   placeholder="جستجوی موضوع، محتوا یا گیرنده..."
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
                            <a href="index.php?page=correspondence&action=sent-letters" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> بازنشانی
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- لیست نامه‌های ارسال شده -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>گیرنده</th>
                        <th>موضوع</th>
                        <th>اولویت</th>
                        <th>تاریخ ارسال</th>
                        <th>وضعیت تحویل</th>
                        <th>ضمایم</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sentLetters as $letter): ?>
                    <?php
                    $deliveryStatus = $letter['delivery_status'];
                    $statusColors = [
                        'delivered' => 'success',
                        'read' => 'primary',
                        'pending' => 'warning',
                        'failed' => 'danger'
                    ];
                    $statusTexts = [
                        'delivered' => 'تحویل شده',
                        'read' => 'خوانده شده',
                        'pending' => 'در انتظار',
                        'failed' => 'ناموفق'
                    ];
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm"><?php echo mb_substr($letter['receiver_name'], 0, 1); ?></div>
                                <div class="ms-2">
                                    <strong><?php echo $letter['receiver_name']; ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $letter['receiver_department']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="index.php?page=correspondence&action=view&id=<?php echo $letter['id']; ?>" 
                               class="text-decoration-none">
                                <?php echo $letter['subject']; ?>
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
                            <span class="badge bg-<?php echo $statusColors[$deliveryStatus] ?? 'secondary'; ?>">
                                <?php echo $statusTexts[$deliveryStatus] ?? 'نامشخص'; ?>
                            </span>
                            <?php if ($deliveryStatus == 'read'): ?>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-eye"></i> مشاهده شده
                            </small>
                            <?php endif; ?>
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
                                <button class="btn btn-outline-secondary" 
                                        onclick="resendLetter(<?php echo $letter['id']; ?>)" 
                                        title="ارسال مجدد">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button class="btn btn-outline-info" 
                                        onclick="trackLetter(<?php echo $letter['id']; ?>)" 
                                        title="پیگیری">
                                    <i class="fas fa-search"></i>
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
                    
                    <?php if (empty($sentLetters)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ نامه ارسال شده‌ای یافت نشد</p>
                                <a href="index.php?page=correspondence&action=create" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> ارسال اولین نامه
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
                    <a class="page-link" href="?page=correspondence&action=sent-letters&page_num=<?php echo $page - 1; ?>&status=<?php echo $status; ?>">
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
                    <a class="page-link" href="?page=correspondence&action=sent-letters&page_num=<?php echo $i; ?>&status=<?php echo $status; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=correspondence&action=sent-letters&page_num=<?php echo $page + 1; ?>&status=<?php echo $status; ?>">
                        بعدی
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- مودال پیگیری نامه -->
<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">پیگیری نامه</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="trackingContent">
                    <!-- محتوای پیگیری -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
// ارسال مجدد نامه
function resendLetter(letterId) {
    showConfirmationModal(
        'ارسال مجدد',
        'آیا از ارسال مجدد این نامه اطمینان دارید؟',
        function() {
            fetch(`modules/correspondence/resend_letter.php?id=${letterId}`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('نامه با موفقیت ارسال مجدد شد', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('خطا در ارسال مجدد', 'danger');
                }
            });
        }
    );
}

// پیگیری نامه
function trackLetter(letterId) {
    fetch(`modules/correspondence/get_tracking_info.php?id=${letterId}`)
        .then(response => response.json())
        .then(trackingInfo => {
            const content = document.getElementById('trackingContent');
            content.innerHTML = '';
            
            if (trackingInfo.length === 0) {
                content.innerHTML = '<p class="text-muted">اطلاعات پیگیری یافت نشد</p>';
            } else {
                trackingInfo.forEach(event => {
                    const eventElement = document.createElement('div');
                    eventElement.className = 'tracking-event mb-3';
                    
                    const statusColors = {
                        'sent': 'primary',
                        'delivered': 'success',
                        'read': 'info',
                        'failed': 'danger'
                    };
                    
                    eventElement.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${event.action}</strong>
                                <p class="mb-0">${event.description}</p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-${statusColors[event.status] || 'secondary'}">
                                    ${event.status}
                                </span>
                                <br>
                                <small class="text-muted">${event.created_at}</small>
                            </div>
                        </div>
                    `;
                    content.appendChild(eventElement);
                });
            }
            
            const modal = new bootstrap.Modal(document.getElementById('trackingModal'));
            modal.show();
        });
}

// حذف نامه ارسال شده
function deleteLetter(letterId) {
    showConfirmationModal(
        'حذف نامه',
        'آیا از حذف این نامه اطمینان دارید؟',
        function() {
            fetch(`modules/correspondence/delete_sent_letter.php?id=${letterId}`, {
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

.tracking-event {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-right: 4px solid var(--primary-color);
}
</style>