<?php
if (!hasPermission('correspondence_create')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$db = connectDB();

// گرفتن لیست کاربران
$usersResult = $db->query("SELECT id, full_name, department FROM users WHERE status = 1 ORDER BY full_name");
$users = [];
while ($row = $usersResult->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

// گرفتن قالب‌های نامه
$templatesResult = $db->query("SELECT * FROM letter_templates WHERE status = 'active' ORDER BY name");
$templates = [];
while ($row = $templatesResult->fetchArray(SQLITE3_ASSOC)) {
    $templates[] = $row;
}

closeDB($db);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // پردازش فرم
    $subject = sanitizeInput($_POST['subject']);
    $content = $_POST['content']; // محتوای HTML
    $receiverId = intval($_POST['receiver_id']);
    $priority = intval($_POST['priority']);
    $letterType = sanitizeInput($_POST['letter_type']);
    $tags = sanitizeInput($_POST['tags']);
    
    // تولید شماره نامه
    $letterNumber = generateUniqueNumber('LTR', 'correspondence', 'letter_number');
    
    $db = connectDB();
    
    $stmt = $db->prepare("INSERT INTO correspondence 
                         (letter_number, subject, content, sender_id, receiver_id, priority, letter_type, tags) 
                         VALUES (:letter_num, :subject, :content, :sender_id, :receiver_id, :priority, :type, :tags)");
    
    $stmt->bindValue(':letter_num', $letterNumber, SQLITE3_TEXT);
    $stmt->bindValue(':subject', $subject, SQLITE3_TEXT);
    $stmt->bindValue(':content', $content, SQLITE3_TEXT);
    $stmt->bindValue(':sender_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':receiver_id', $receiverId, SQLITE3_INTEGER);
    $stmt->bindValue(':priority', $priority, SQLITE3_INTEGER);
    $stmt->bindValue(':type', $letterType, SQLITE3_TEXT);
    $stmt->bindValue(':tags', $tags, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        $letterId = $db->lastInsertRowID();
        
        // آپلود فایل‌های ضمیمه
        $attachments = [];
        if (isset($_FILES['attachments'])) {
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['attachments']['error'][$key] == 0) {
                    $fileName = time() . '_' . $_FILES['attachments']['name'][$key];
                    $uploadDir = 'assets/uploads/letters/';
                    
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $targetFile = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $targetFile)) {
                        $attachments[] = $targetFile;
                        
                        // ذخیره در دیتابیس
                        $attachStmt = $db->prepare("INSERT INTO letter_attachments 
                                                   (letter_id, file_name, file_path, uploaded_by) 
                                                   VALUES (:letter_id, :file_name, :file_path, :user_id)");
                        
                        $attachStmt->bindValue(':letter_id', $letterId, SQLITE3_INTEGER);
                        $attachStmt->bindValue(':file_name', $_FILES['attachments']['name'][$key], SQLITE3_TEXT);
                        $attachStmt->bindValue(':file_path', $targetFile, SQLITE3_TEXT);
                        $attachStmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                        $attachStmt->execute();
                    }
                }
            }
        }
        
        // ایجاد نوتیفیکیشن برای گیرنده
        createNotification(
            $receiverId,
            'نامه جدید',
            "نامه جدیدی با موضوع \"$subject\" برای شما ارسال شد",
            'info',
            "index.php?page=correspondence&action=view&id=$letterId"
        );
        
        // ارسال ایمیل
        $receiverInfo = getUserInfo($receiverId);
        if ($receiverInfo['email']) {
            $emailSubject = "نامه جدید - $subject";
            $emailBody = "
                <html>
                <body style='font-family: Tahoma, sans-serif;'>
                    <h3>نامه جدید دریافت کردید</h3>
                    <p>شما یک نامه جدید در سیستم اتوماسیون اداری دریافت کرده‌اید:</p>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>
                        <strong>موضوع:</strong> $subject<br>
                        <strong>شماره نامه:</strong> $letterNumber<br>
                        <strong>فرستنده:</strong> {$_SESSION['full_name']}
                    </div>
                    <p>برای مشاهده نامه، روی لینک زیر کلیک کنید:</p>
                    <a href='" . SITE_URL . "index.php?page=correspondence&action=view&id=$letterId' 
                       style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        مشاهده نامه
                    </a>
                </body>
                </html>
            ";
            
            sendEmail($receiverInfo['email'], $emailSubject, $emailBody);
        }
        
        // لاگ فعالیت
        logActivity(
            $_SESSION['user_id'],
            'send_letter',
            "ارسال نامه جدید: $subject (شماره: $letterNumber) به {$receiverInfo['full_name']}"
        );
        
        $success = "نامه با شماره $letterNumber با موفقیت ارسال شد";
        
        // ریدایرکت
        echo '<script>setTimeout(() => window.location.href = "index.php?page=correspondence&action=view&id=' . $letterId . '", 2000);</script>';
    } else {
        $error = "خطا در ارسال نامه";
    }
    
    closeDB($db);
}
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-edit"></i> ایجاد نامه جدید</h5>
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
        
        <form method="POST" action="" enctype="multipart/form-data" id="letterForm">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="subject" class="form-label">موضوع نامه <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subject" name="subject" required
                           placeholder="موضوع نامه را به صورت واضح وارد کنید">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="receiver_id" class="form-label">گیرنده <span class="text-danger">*</span></label>
                    <select class="form-select" id="receiver_id" name="receiver_id" required>
                        <option value="">انتخاب گیرنده...</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo $user['full_name']; ?> - <?php echo $user['department']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="priority" class="form-label">اولویت</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="3">عادی</option>
                        <option value="2">بالا</option>
                        <option value="1">فوری</option>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="letter_type" class="form-label">نوع نامه</label>
                    <select class="form-select" id="letter_type" name="letter_type">
                        <option value="اداری">اداری</option>
                        <option value="داخلی">داخلی</option>
                        <option value="خارجی">خارجی</option>
                        <option value="محرمانه">محرمانه</option>
                        <option value="فوری">فوری</option>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="template" class="form-label">قالب نامه</label>
                    <select class="form-select" id="template" onchange="loadTemplate(this.value)">
                        <option value="">انتخاب قالب...</option>
                        <?php foreach ($templates as $template): ?>
                        <option value="<?php echo $template['id']; ?>"><?php echo $template['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="tags" class="form-label">برچسب‌ها</label>
                    <input type="text" class="form-control" id="tags" name="tags"
                           placeholder="کلمات کلیدی را با کاما جدا کنید">
                </div>
            </div>
            
            <!-- ویرایشگر متن -->
            <div class="mb-3">
                <label class="form-label">متن نامه <span class="text-danger">*</span></label>
                <div class="editor-toolbar mb-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('bold')" title="درشت">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('italic')" title="کج">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('underline')" title="زیرخط">
                            <i class="fas fa-underline"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('alignRight')" title="راست‌چین">
                            <i class="fas fa-align-right"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('alignCenter')" title="وسط‌چین">
                            <i class="fas fa-align-center"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('alignLeft')" title="چپ‌چین">
                            <i class="fas fa-align-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="insertImage()" title="درج تصویر">
                            <i class="fas fa-image"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="insertTable()" title="درج جدول">
                            <i class="fas fa-table"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="insertLink()" title="درج لینک">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearFormatting()" title="پاک کردن فرمت">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                </div>
                <div class="editor-container">
                    <textarea class="form-control" id="content" name="content" rows="10" required
                              placeholder="متن نامه را اینجا بنویسید..."></textarea>
                    <div id="editorPreview" class="border p-3 mt-2 d-none" style="min-height: 200px;"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">برای مشاهده پیش‌نمایش، روی دکمه زیر کلیک کنید</small>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="togglePreview()">
                        <i class="fas fa-eye"></i> پیش‌نمایش
                    </button>
                </div>
            </div>
            
            <!-- ضمایم -->
            <div class="mb-4">
                <label class="form-label">ضمایم</label>
                <div class="file-upload-area border rounded p-3 text-center">
                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                    <p class="mb-2">فایل‌ها را اینجا رها کنید یا برای انتخاب کلیک کنید</p>
                    <input type="file" class="form-control d-none" id="attachments" name="attachments[]" multiple 
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar">
                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('attachments').click()">
                        <i class="fas fa-folder-open"></i> انتخاب فایل‌ها
                    </button>
                    <div class="mt-3" id="fileList">
                        <!-- لیست فایل‌های انتخاب شده -->
                    </div>
                    <div class="form-text">
                        حداکثر ۱۰ فایل، هر فایل تا ۲۰ مگابایت. فرمت‌های مجاز: PDF, Word, Excel, Image, Archive
                    </div>
                </div>
            </div>
            
            <!-- امضا -->
            <div class="mb-4">
                <label class="form-label">امضا</label>
                <div class="signature-area border rounded p-3">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نام امضا کننده</label>
                                <input type="text" class="form-control" name="signer_name" 
                                       value="<?php echo $_SESSION['full_name']; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">سمت</label>
                                <input type="text" class="form-control" name="signer_position" 
                                       placeholder="سمت امضا کننده">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">تاریخ</label>
                                <input type="date" class="form-control" name="signature_date" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">امضای دیجیتال</label>
                                <input type="file" class="form-control" name="signature_file" 
                                       accept=".png,.jpg,.jpeg">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> ارسال نامه
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="saveAsDraft()">
                        <i class="fas fa-save"></i> ذخیره پیش‌نویس
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-info" onclick="printLetter()">
                        <i class="fas fa-print"></i> چاپ
                    </button>
                    <a href="index.php?page=correspondence" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> انصراف
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- مودال درج تصویر -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">درج تصویر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">آدرس تصویر</label>
                    <input type="text" class="form-control" id="imageUrl" placeholder="https://example.com/image.jpg">
                </div>
                <div class="mb-3">
                    <label class="form-label">یا آپلود فایل</label>
                    <input type="file" class="form-control" id="imageUpload" accept=".jpg,.jpeg,.png,.gif">
                </div>
                <div class="mb-3">
                    <label class="form-label">توضیح تصویر (alt)</label>
                    <input type="text" class="form-control" id="imageAlt" placeholder="توضیح تصویر">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" onclick="insertImageToEditor()">درج</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال درج جدول -->
<div class="modal fade" id="tableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">درج جدول</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">تعداد سطرها</label>
                        <input type="number" class="form-control" id="tableRows" min="1" max="20" value="3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">تعداد ستون‌ها</label>
                        <input type="number" class="form-control" id="tableCols" min="1" max="10" value="3">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" onclick="insertTableToEditor()">درج</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال درج لینک -->
<div class="modal fade" id="linkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">درج لینک</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">متن لینک</label>
                    <input type="text" class="form-control" id="linkText" placeholder="متن نمایشی">
                </div>
                <div class="mb-3">
                    <label class="form-label">آدرس (URL)</label>
                    <input type="text" class="form-control" id="linkUrl" placeholder="https://example.com">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="linkNewTab" checked>
                    <label class="form-check-label">باز شدن در پنجره جدید</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" onclick="insertLinkToEditor()">درج</button>
            </div>
        </div>
    </div>
</div>

<script>
// مدیریت ویرایشگر متن
const editor = document.getElementById('content');
const preview = document.getElementById('editorPreview');

// فرمت‌دهی متن
function formatText(command) {
    editor.focus();
    
    if (command === 'bold') {
        document.execCommand('bold', false, null);
    } else if (command === 'italic') {
        document.execCommand('italic', false, null);
    } else if (command === 'underline') {
        document.execCommand('underline', false, null);
    } else if (command === 'alignRight') {
        document.execCommand('justifyRight', false, null);
    } else if (command === 'alignCenter') {
        document.execCommand('justifyCenter', false, null);
    } else if (command === 'alignLeft') {
        document.execCommand('justifyLeft', false, null);
    }
}

// پاک کردن فرمت‌ها
function clearFormatting() {
    editor.focus();
    document.execCommand('removeFormat', false, null);
}

// درج تصویر
function insertImage() {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
}

function insertImageToEditor() {
    const url = document.getElementById('imageUrl').value;
    const alt = document.getElementById('imageAlt').value || 'تصویر';
    
    if (url) {
        const imgHTML = `<img src="${url}" alt="${alt}" style="max-width: 100%;">`;
        insertHTML(imgHTML);
    }
    
    bootstrap.Modal.getInstance(document.getElementById('imageModal')).hide();
}

// درج جدول
function insertTable() {
    const modal = new bootstrap.Modal(document.getElementById('tableModal'));
    modal.show();
}

function insertTableToEditor() {
    const rows = parseInt(document.getElementById('tableRows').value);
    const cols = parseInt(document.getElementById('tableCols').value);
    
    let tableHTML = '<table class="table table-bordered" style="width: 100%;">';
    
    for (let i = 0; i < rows; i++) {
        tableHTML += '<tr>';
        for (let j = 0; j < cols; j++) {
            tableHTML += '<td>&nbsp;</td>';
        }
        tableHTML += '</tr>';
    }
    
    tableHTML += '</table>';
    insertHTML(tableHTML);
    
    bootstrap.Modal.getInstance(document.getElementById('tableModal')).hide();
}

// درج لینک
function insertLink() {
    const modal = new bootstrap.Modal(document.getElementById('linkModal'));
    modal.show();
}

function insertLinkToEditor() {
    const text = document.getElementById('linkText').value;
    const url = document.getElementById('linkUrl').value;
    const newTab = document.getElementById('linkNewTab').checked;
    
    if (text && url) {
        const target = newTab ? ' target="_blank"' : '';
        const linkHTML = `<a href="${url}"${target}>${text}</a>`;
        insertHTML(linkHTML);
    }
    
    bootstrap.Modal.getInstance(document.getElementById('linkModal')).hide();
}

// تابع کمکی برای درج HTML
function insertHTML(html) {
    editor.focus();
    document.execCommand('insertHTML', false, html);
}

// پیش‌نمایش
function togglePreview() {
    if (preview.classList.contains('d-none')) {
        preview.innerHTML = editor.value;
        preview.classList.remove('d-none');
        editor.classList.add('d-none');
        document.querySelector('[onclick="togglePreview()"]').innerHTML = '<i class="fas fa-edit"></i> ویرایش';
    } else {
        preview.classList.add('d-none');
        editor.classList.remove('d-none');
        document.querySelector('[onclick="togglePreview()"]').innerHTML = '<i class="fas fa-eye"></i> پیش‌نمایش';
    }
}

// مدیریت فایل‌ها
document.getElementById('attachments').addEventListener('change', function(e) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    const files = Array.from(e.target.files);
    let totalSize = 0;
    
    files.forEach((file, index) => {
        totalSize += file.size;
        
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item d-flex justify-content-between align-items-center p-2 border rounded mb-2';
        fileItem.innerHTML = `
            <div>
                <i class="fas fa-file me-2"></i>
                ${file.name}
                <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                <i class="fas fa-times"></i>
            </button>
        `;
        fileList.appendChild(fileItem);
    });
    
    if (totalSize > 200 * 1024 * 1024) {
        showToast('مجموع حجم فایل‌ها بیش از ۲۰۰ مگابایت است', 'warning');
        this.value = '';
        fileList.innerHTML = '';
    }
    
    if (files.length > 10) {
        showToast('حداکثر ۱۰ فایل مجاز است', 'warning');
        this.value = '';
        fileList.innerHTML = '';
    }
});

function removeFile(index) {
    const dt = new DataTransfer();
    const input = document.getElementById('attachments');
    const { files } = input;
    
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    
    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
}

// بارگذاری قالب
function loadTemplate(templateId) {
    if (!templateId) return;
    
    fetch(`modules/correspondence/get_template.php?id=${templateId}`)
        .then(response => response.json())
        .then(template => {
            if (template) {
                document.getElementById('subject').value = template.subject;
                editor.value = template.content;
                document.getElementById('letter_type').value = template.letter_type;
                showToast('قالب بارگذاری شد', 'success');
            }
        });
}

// ذخیره پیش‌نویس
function saveAsDraft() {
    const form = document.getElementById('letterForm');
    const draftInput = document.createElement('input');
    draftInput.type = 'hidden';
    draftInput.name = 'draft';
    draftInput.value = '1';
    form.appendChild(draftInput);
    form.submit();
}

// چاپ نامه
function printLetter() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html dir="rtl">
        <head>
            <title>چاپ نامه</title>
            <style>
                body { font-family: 'Sahel', Tahoma, sans-serif; padding: 20px; }
                .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                .print-content { line-height: 1.8; margin: 20px 0; }
                .print-signature { margin-top: 50px; text-align: left; }
                .print-footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h2>${document.getElementById('subject').value || 'نامه'}</h2>
                <p>شماره: در حال تولید</p>
            </div>
            <div class="print-content">
                ${editor.value.replace(/\n/g, '<br>')}
            </div>
            <div class="print-signature">
                <p>با احترام</p>
                <p><strong>${document.querySelector('[name="signer_name"]').value}</strong></p>
                <p>${document.querySelector('[name="signer_position"]').value || 'سمت'}</p>
                <p>تاریخ: ${document.querySelector('[name="signature_date"]').value}</p>
            </div>
            <div class="print-footer">
                <p>سیستم اتوماسیون اداری - این سند به صورت خودکار تولید شده است</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// دراپ فایل‌ها
const fileUploadArea = document.querySelector('.file-upload-area');
fileUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadArea.style.backgroundColor = '#e9ecef';
});

fileUploadArea.addEventListener('dragleave', (e) => {
    e.preventDefault();
    fileUploadArea.style.backgroundColor = '';
});

fileUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadArea.style.backgroundColor = '';
    
    const files = e.dataTransfer.files;
    document.getElementById('attachments').files = files;
    document.getElementById('attachments').dispatchEvent(new Event('change'));
});
</script>

<style>
.editor-toolbar {
    background: #f8f9fa;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-bottom: none;
    border-radius: 5px 5px 0 0;
}

.editor-container {
    border: 1px solid #dee2e6;
    border-radius: 0 0 5px 5px;
}

.editor-container textarea {
    border: none;
    resize: vertical;
    min-height: 300px;
}

#editorPreview {
    background: white;
    font-family: 'Sahel', Tahoma, sans-serif;
    line-height: 1.8;
}

.file-upload-area {
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.3s;
}

.file-upload-area:hover {
    background: #e9ecef;
}

.file-item {
    background: white;
}

.signature-area {
    background: #f8f9fa;
}

@media print {
    .card, .card-header, .card-body {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>