<?php
if (!hasPermission('documents_view')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$db = connectDB();

// فیلترها
$filters = [];
$whereClauses = [];

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $whereClauses[] = "document_type = :type";
    $filters[':type'] = $_GET['type'];
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $whereClauses[] = "category = :category";
    $filters[':category'] = $_GET['category'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $whereClauses[] = "status = :status";
    $filters[':status'] = $_GET['status'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $whereClauses[] = "(title LIKE :search OR document_number LIKE :search OR description LIKE :search)";
    $filters[':search'] = '%' . $_GET['search'] . '%';
}

// تاریخ
if (isset($_GET['from_date']) && !empty($_GET['from_date'])) {
    $whereClauses[] = "created_at >= :from_date";
    $filters[':from_date'] = $_GET['from_date'];
}

if (isset($_GET['to_date']) && !empty($_GET['to_date'])) {
    $whereClauses[] = "created_at <= :to_date";
    $filters[':to_date'] = $_GET['to_date'] . ' 23:59:59';
}

// ساخت کوئری
$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
$query = "SELECT d.*, u.full_name as creator_name 
          FROM documents d 
          LEFT JOIN users u ON d.created_by = u.id 
          $whereSQL 
          ORDER BY d.created_at DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

// بایند کردن فیلترها
foreach ($filters as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_TEXT);
}

$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$documents = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $documents[] = $row;
}

// تعداد کل رکوردها
$countQuery = "SELECT COUNT(*) as total FROM documents d $whereSQL";
$stmt = $db->prepare($countQuery);

foreach ($filters as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_TEXT);
}

$countResult = $stmt->execute();
$totalRows = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
$totalPages = ceil($totalRows / $limit);

// انواع سند
$typesResult = $db->query("SELECT DISTINCT document_type FROM documents WHERE document_type IS NOT NULL");
$documentTypes = [];
while ($row = $typesResult->fetchArray(SQLITE3_ASSOC)) {
    $documentTypes[] = $row['document_type'];
}

// دسته‌بندی‌ها
$categoriesResult = $db->query("SELECT DISTINCT category FROM documents WHERE category IS NOT NULL");
$categories = [];
while ($row = $categoriesResult->fetchArray(SQLITE3_ASSOC)) {
    $categories[] = $row['category'];
}

closeDB($db);
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-archive"></i> بایگانی اسناد</h5>
    </div>
    
    <div class="card-body">
        <!-- فیلترها -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="page" value="documents">
                    
                    <div class="col-md-3">
                        <label class="form-label">نوع سند</label>
                        <select name="type" class="form-select">
                            <option value="">همه</option>
                            <?php foreach ($documentTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo isset($_GET['type']) && $_GET['type'] == $type ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">دسته‌بندی</label>
                        <select name="category" class="form-select">
                            <option value="">همه</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $category ? 'selected' : ''; ?>>
                                <?php echo $category; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">وضعیت</label>
                        <select name="status" class="form-select">
                            <option value="">همه</option>
                            <option value="draft" <?php echo isset($_GET['status']) && $_GET['status'] == 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] == 'active' ? 'selected' : ''; ?>>فعال</option>
                            <option value="archived" <?php echo isset($_GET['status']) && $_GET['status'] == 'archived' ? 'selected' : ''; ?>>بایگانی شده</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">از تاریخ</label>
                        <input type="date" name="from_date" class="form-control" 
                               value="<?php echo $_GET['from_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">تا تاریخ</label>
                        <input type="date" name="to_date" class="form-control" 
                               value="<?php echo $_GET['to_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-md-8">
                        <label class="form-label">جستجو</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="عنوان، شماره سند یا توضیحات..." 
                               value="<?php echo $_GET['search'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> اعمال فیلتر
                            </button>
                            <a href="index.php?page=documents" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> بازنشانی
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- نتایج -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>شماره سند</th>
                        <th>عنوان</th>
                        <th>نوع</th>
                        <th>دسته‌بندی</th>
                        <th>اولویت</th>
                        <th>وضعیت</th>
                        <th>ایجاد کننده</th>
                        <th>تاریخ ایجاد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $index => $doc): ?>
                    <tr>
                        <td><?php echo ($page - 1) * $limit + $index + 1; ?></td>
                        <td>
                            <strong><?php echo $doc['document_number']; ?></strong>
                        </td>
                        <td>
                            <a href="index.php?page=documents&action=view&id=<?php echo $doc['id']; ?>" 
                               class="text-decoration-none">
                                <?php echo mb_substr($doc['title'], 0, 30); ?>
                                <?php echo mb_strlen($doc['title']) > 30 ? '...' : ''; ?>
                            </a>
                            <?php if ($doc['file_path']): ?>
                            <i class="fas fa-paperclip text-muted ms-1" title="دارای فایل ضمیمه"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $doc['document_type']; ?></span>
                        </td>
                        <td>
                            <?php if ($doc['category']): ?>
                            <span class="badge bg-secondary"><?php echo $doc['category']; ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $priorityColors = ['danger', 'warning', 'primary'];
                            $priorityTexts = ['فوری', 'بالا', 'عادی'];
                            ?>
                            <span class="badge bg-<?php echo $priorityColors[$doc['priority'] - 1]; ?>">
                                <?php echo $priorityTexts[$doc['priority'] - 1]; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $statusColors = [
                                'draft' => 'secondary',
                                'active' => 'success', 
                                'archived' => 'dark'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $statusColors[$doc['status']] ?? 'secondary'; ?>">
                                <?php echo $doc['status']; ?>
                            </span>
                        </td>
                        <td><?php echo $doc['creator_name']; ?></td>
                        <td><?php echo date('Y/m/d', strtotime($doc['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=documents&action=view&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-primary" title="مشاهده">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="index.php?page=documents&action=edit&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-warning" title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($doc['file_path']): ?>
                                <a href="<?php echo $doc['file_path']; ?>" 
                                   class="btn btn-outline-info" title="دانلود" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php endif; ?>
                                <button onclick="archiveDocument(<?php echo $doc['id']; ?>)" 
                                        class="btn btn-outline-dark" title="بایگانی">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($documents)): ?>
                    <tr>
                        <td colspan="10" class="text-center">
                            <div class="empty-state py-5">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ سندی یافت نشد</p>
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
                    <a class="page-link" href="?page=documents&page_num=<?php echo $page - 1; ?><?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
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
                    <a class="page-link" href="?page=documents&page_num=<?php echo $i; ?><?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=documents&page_num=<?php echo $page + 1; ?><?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                        بعدی
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <!-- آمار -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4><?php echo $totalRows; ?></h4>
                                <p class="text-muted mb-0">کل اسناد</p>
                            </div>
                            <div class="col-md-3">
                                <?php
                                $db = connectDB();
                                $activeCount = $db->querySingle("SELECT COUNT(*) FROM documents WHERE status = 'active'");
                                closeDB($db);
                                ?>
                                <h4><?php echo $activeCount; ?></h4>
                                <p class="text-muted mb-0">اسناد فعال</p>
                            </div>
                            <div class="col-md-3">
                                <?php
                                $db = connectDB();
                                $archivedCount = $db->querySingle("SELECT COUNT(*) FROM documents WHERE status = 'archived'");
                                closeDB($db);
                                ?>
                                <h4><?php echo $archivedCount; ?></h4>
                                <p class="text-muted mb-0">اسناد بایگانی</p>
                            </div>
                            <div class="col-md-3">
                                <?php
                                $db = connectDB();
                                $withFiles = $db->querySingle("SELECT COUNT(*) FROM documents WHERE file_path IS NOT NULL AND file_path != ''");
                                closeDB($db);
                                ?>
                                <h4><?php echo $withFiles; ?></h4>
                                <p class="text-muted mb-0">دارای ضمیمه</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function archiveDocument(documentId) {
    showConfirmationModal(
        'بایگانی سند',
        'آیا از بایگانی این سند اطمینان دارید؟',
        function() {
            fetch('modules/documents/archive_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    document_id: documentId
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('سند با موفقیت بایگانی شد', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('خطا در بایگانی سند', 'danger');
                }
            });
        }
    );
}
</script>