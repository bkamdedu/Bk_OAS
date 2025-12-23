<?php
if (!hasPermission('documents_manage')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

// پردازش ایمپورت
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import'])) {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == 0) {
        $file = $_FILES['import_file'];
        
        // اعتبارسنجی فایل
        $allowedTypes = ['csv', 'xls', 'xlsx'];
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileType, $allowedTypes)) {
            $error = 'فرمت فایل باید CSV یا Excel باشد';
        } else {
            // آپلود فایل
            $uploadDir = 'assets/uploads/imports/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filename = time() . '_' . basename($file['name']);
            $targetFile = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                // پردازش فایل
                $importedCount = processImportFile($targetFile);
                $success = "تعداد $importedCount سند با موفقیت وارد شد";
            } else {
                $error = 'خطا در آپلود فایل';
            }
        }
    } else {
        $error = 'لطفا یک فایل انتخاب کنید';
    }
}

// پردازش فایل ایمپورت
function processImportFile($filePath) {
    $importedCount = 0;
    $db = connectDB();
    
    // در اینجا باید فایل Excel یا CSV خوانده شود
    // به دلیل پیچیدگی، یک نمونه ساده ارائه می‌شود
    
    if (($handle = fopen($filePath, 'r')) !== false) {
        $headers = fgetcsv($handle); // خواندن هدرها
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2) { // حداقل دو ستون: عنوان و نوع
                $title = $row[0];
                $type = $row[1];
                $description = $row[2] ?? '';
                $category = $row[3] ?? '';
                $priority = $row[4] ?? 3;
                
                // تولید شماره سند
                $documentNumber = generateUniqueNumber('DOC', 'documents', 'document_number');
                
                // ذخیره در دیتابیس
                $stmt = $db->prepare("INSERT INTO documents 
                                     (document_number, title, description, document_type, category, priority, created_by) 
                                     VALUES (:doc_num, :title, :desc, :type, :category, :priority, :user_id)");
                
                $stmt->bindValue(':doc_num', $documentNumber, SQLITE3_TEXT);
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
                $stmt->bindValue(':type', $type, SQLITE3_TEXT);
                $stmt->bindValue(':category', $category, SQLITE3_TEXT);
                $stmt->bindValue(':priority', $priority, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $importedCount++;
                }
            }
        }
        
        fclose($handle);
    }
    
    closeDB($db);
    return $importedCount;
}

// پردازش اکسپورت
if (isset($_GET['export'])) {
    $format = $_GET['format'] ?? 'excel';
    
    // گرفتن داده‌ها
    $db = connectDB();
    $query = "SELECT d.*, u.full_name as creator_name 
              FROM documents d 
              LEFT JOIN users u ON d.created_by = u.id 
              ORDER BY d.created_at DESC";
    $result = $db->query($query);
    
    $documents = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $documents[] = $row;
    }
    
    closeDB($db);
    
    // ایجاد فایل خروجی
    if ($format == 'excel') {
        exportToExcel($documents);
    } elseif ($format == 'pdf') {
        exportToPDF($documents);
    }
}

// تابع اکسپورت به اکسل
function exportToExcel($data) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="documents_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html dir="rtl"><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    
    // هدرها
    echo '<tr>';
    echo '<th>ردیف</th>';
    echo '<th>شماره سند</th>';
    echo '<th>عنوان</th>';
    echo '<th>نوع</th>';
    echo '<th>دسته‌بندی</th>';
    echo '<th>اولویت</th>';
    echo '<th>وضعیت</th>';
    echo '<th>ایجاد کننده</th>';
    echo '<th>تاریخ ایجاد</th>';
    echo '</tr>';
    
    // داده‌ها
    foreach ($data as $index => $row) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td>' . $row['document_number'] . '</td>';
        echo '<td>' . $row['title'] . '</td>';
        echo '<td>' . $row['document_type'] . '</td>';
        echo '<td>' . $row['category'] . '</td>';
        
        $priorityTexts = ['فوری', 'بالا', 'عادی'];
        echo '<td>' . $priorityTexts[$row['priority'] - 1] . '</td>';
        
        echo '<td>' . $row['status'] . '</td>';
        echo '<td>' . $row['creator_name'] . '</td>';
        echo '<td>' . date('Y/m/d', strtotime($row['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit;
}

// تابع اکسپورت به PDF
function exportToPDF($data) {
    // اینجا می‌توان از کتابخانه‌ای مانند TCPDF یا mPDF استفاده کرد
    // به دلیل پیچیدگی، یک پیام ساده نمایش می‌دهیم
    echo '<script>alert("برای تولید PDF نیاز به نصب کتابخانه PDF است")</script>';
}
?>

<div class="row">
    <!-- بخش ایمپورت -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-import"></i> ورود اطلاعات از فایل</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">انتخاب فایل</label>
                        <input type="file" class="form-control" name="import_file" required>
                        <div class="form-text">
                            فرمت‌های مجاز: CSV, Excel (xls, xlsx)
                            <a href="assets/templates/documents_template.csv" class="ms-2">
                                <i class="fas fa-download"></i> دانلود قالب نمونه
                            </a>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">نوع فایل</label>
                        <select class="form-select" name="file_type">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="has_headers" checked>
                            <label class="form-check-label">سطر اول فایل حاوی عنوان ستون‌ها است</label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">نحوه پردازش رکوردهای تکراری</label>
                        <select class="form-select" name="duplicate_handling">
                            <option value="skip">رد کردن رکوردهای تکراری</option>
                            <option value="update">به‌روزرسانی رکوردهای تکراری</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="import" class="btn btn-primary">
                        <i class="fas fa-upload"></i> شروع ورود اطلاعات
                    </button>
                </form>
            </div>
        </div>
        
        <!-- راهنمای فرمت فایل -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle"></i> راهنمای فرمت فایل</h6>
            </div>
            <div class="card-body">
                <p>فایل باید دارای ستون‌های زیر باشد:</p>
                <ul>
                    <li>عنوان (الزامی)</li>
                    <li>نوع سند (الزامی)</li>
                    <li>توضیحات (اختیاری)</li>
                    <li>دسته‌بندی (اختیاری)</li>
                    <li>اولویت (1-3) (اختیاری)</li>
                </ul>
                <p class="text-muted mb-0">برای جدا کردن مقادیر از کاما (,) استفاده کنید.</p>
            </div>
        </div>
    </div>
    
    <!-- بخش اکسپورت -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-export"></i> خروجی اطلاعات</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="page" value="documents">
                    <input type="hidden" name="action" value="import-export">
                    
                    <div class="mb-3">
                        <label class="form-label">فرمت خروجی</label>
                        <select class="form-select" name="format" required>
                            <option value="excel">Excel (.xls)</option>
                            <option value="pdf">PDF (.pdf)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">دسته‌بندی اسناد</label>
                        <select class="form-select" name="category">
                            <option value="">همه</option>
                            <option value="فوری">فوری</option>
                            <option value="محرمانه">محرمانه</option>
                            <option value="عادی">عادی</option>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">از تاریخ</label>
                            <input type="date" class="form-control" name="from_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">تا تاریخ</label>
                            <input type="date" class="form-control" name="to_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">وضعیت</label>
                        <select class="form-select" name="status">
                            <option value="">همه</option>
                            <option value="active">فعال</option>
                            <option value="draft">پیش‌نویس</option>
                            <option value="archived">بایگانی شده</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">فیلدهای خروجی</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="document_number" checked>
                                    <label class="form-check-label">شماره سند</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="title" checked>
                                    <label class="form-check-label">عنوان</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="document_type" checked>
                                    <label class="form-check-label">نوع</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="priority" checked>
                                    <label class="form-check-label">اولویت</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="status" checked>
                                    <label class="form-check-label">وضعیت</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="created_at" checked>
                                    <label class="form-check-label">تاریخ ایجاد</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="export" value="1" class="btn btn-success">
                        <i class="fas fa-download"></i> ایجاد خروجی
                    </button>
                </form>
            </div>
        </div>
        
        <!-- اطلاعات آماری -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar"></i> آمار خروجی</h6>
            </div>
            <div class="card-body">
                <?php
                $db = connectDB();
                $totalDocs = $db->querySingle("SELECT COUNT(*) FROM documents");
                $todayDocs = $db->querySingle("SELECT COUNT(*) FROM documents WHERE DATE(created_at) = DATE('now')");
                $lastExport = $db->querySingle("SELECT MAX(created_at) FROM export_logs WHERE export_type = 'documents'");
                closeDB($db);
                ?>
                <div class="row text-center">
                    <div class="col-md-6 mb-3">
                        <div class="stat-box">
                            <h4><?php echo $totalDocs; ?></h4>
                            <p class="text-muted mb-0">کل اسناد</p>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-box">
                            <h4><?php echo $todayDocs; ?></h4>
                            <p class="text-muted mb-0">اسناد امروز</p>
                        </div>
                    </div>
                </div>
                <?php if ($lastExport): ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-history"></i>
                    آخرین خروجی: <?php echo date('Y/m/d H:i', strtotime($lastExport)); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- تاریخچه خروجی‌ها -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-history"></i> تاریخچه خروجی‌ها</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>فرمت</th>
                        <th>تعداد رکورد</th>
                        <th>کاربر</th>
                        <th>دانلود</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $db = connectDB();
                    $query = "SELECT e.*, u.full_name 
                              FROM export_logs e 
                              LEFT JOIN users u ON e.user_id = u.id 
                              WHERE e.export_type = 'documents' 
                              ORDER BY e.created_at DESC 
                              LIMIT 10";
                    $result = $db->query($query);
                    
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)):
                    ?>
                    <tr>
                        <td><?php echo date('Y/m/d H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo strtoupper($row['format']); ?></span>
                        </td>
                        <td><?php echo $row['record_count']; ?></td>
                        <td><?php echo $row['full_name']; ?></td>
                        <td>
                            <?php if (file_exists($row['file_path'])): ?>
                            <a href="<?php echo $row['file_path']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-download"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">فایل موجود نیست</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php
                    closeDB($db);
                    if (!$result->fetchArray()): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">هیچ تاریخچه‌ای یافت نشد</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>