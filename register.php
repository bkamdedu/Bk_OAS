<?php
if (!hasPermission('documents_create')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new SQLite3(DB_PATH);
    
    // تولید شماره سند
    $year = date('Y');
    $month = date('m');
    $last_doc = $db->querySingle("SELECT document_number FROM documents 
                                 WHERE document_number LIKE 'DOC-$year-$month-%' 
                                 ORDER BY id DESC LIMIT 1");
    
    $next_num = 1;
    if ($last_doc) {
        $parts = explode('-', $last_doc);
        $next_num = intval(end($parts)) + 1;
    }
    
    $document_number = sprintf("DOC-%s-%s-%04d", $year, $month, $next_num);
    
    // آپلود فایل
    $file_path = '';
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $upload_dir = 'assets/uploads/documents/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['document_file']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_file)) {
            $file_path = $target_file;
        }
    }
    
    // ذخیره در دیتابیس
    $stmt = $db->prepare("INSERT INTO documents 
                         (document_number, title, description, document_type, category, priority, 
                          file_path, created_by, status) 
                         VALUES (:doc_num, :title, :desc, :type, :category, :priority, 
                                 :file_path, :user_id, 'draft')");
    
    $stmt->bindValue(':doc_num', $document_number, SQLITE3_TEXT);
    $stmt->bindValue(':title', $_POST['title'], SQLITE3_TEXT);
    $stmt->bindValue(':desc', $_POST['description'], SQLITE3_TEXT);
    $stmt->bindValue(':type', $_POST['document_type'], SQLITE3_TEXT);
    $stmt->bindValue(':category', $_POST['category'], SQLITE3_TEXT);
    $stmt->bindValue(':priority', $_POST['priority'], SQLITE3_INTEGER);
    $stmt->bindValue(':file_path', $file_path, SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        $success = "پرونده با موفقیت ثبت شد. شماره پرونده: $document_number";
    } else {
        $error = "خطا در ثبت پرونده";
    }
    
    $db->close();
}
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-file-medical"></i> ثبت پرونده جدید</h5>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="title" class="form-label">عنوان پرونده <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="document_type" class="form-label">نوع سند <span class="text-danger">*</span></label>
                    <select class="form-select" id="document_type" name="document_type" required>
                        <option value="">انتخاب کنید</option>
                        <option value="اداری">اداری</option>
                        <option value="مالی">مالی</option>
                        <option value="پرسنلی">پرسنلی</option>
                        <option value="قرارداد">قرارداد</option>
                        <option value="مکاتبات">مکاتبات</option>
                        <option value="سایر">سایر</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="category" class="form-label">دسته‌بندی</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">انتخاب کنید</option>
                        <option value="فوری">فوری</option>
                        <option value="محرمانه">محرمانه</option>
                        <option value="عادی">عادی</option>
                        <option value="آرشیو">آرشیو</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="priority" class="form-label">اولویت</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="3">عادی</option>
                        <option value="2">بالا</option>
                        <option value="1">فوری</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">شرح پرونده</label>
                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
            </div>
            
            <div class="mb-3">
                <label for="document_file" class="form-label">ضمیمه (اختیاری)</label>
                <input type="file" class="form-control" id="document_file" name="document_file"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                <div class="form-text">فرمت‌های مجاز: PDF, Word, Excel, JPG, PNG</div>
            </div>
            
            <div class="mb-3">
                <label for="tags" class="form-label">کلیدواژه‌ها</label>
                <input type="text" class="form-control" id="tags" name="tags" 
                       placeholder="کلمات کلیدی را با کاما جدا کنید">
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-save"></i> ثبت پرونده
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> بازنشانی
                </button>
            </div>
        </form>
    </div>
</div>

<!-- راهنمای ثبت -->
<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-info-circle"></i> راهنمای ثبت پرونده</h6>
    </div>
    <div class="card-body">
        <ul>
            <li>عنوان پرونده باید واضح و گویای محتوای پرونده باشد</li>
            <li>نوع سند را با دقت انتخاب کنید</li>
            <li>برای پرونده‌های فوری، اولویت مناسب را انتخاب نمایید</li>
            <li>در صورت نیاز، فایل مربوطه را به عنوان ضمیمه آپلود کنید</li>
            <li>کلیدواژه‌ها به جستجوی بهتر پرونده کمک می‌کنند</li>
        </ul>
    </div>
</div>