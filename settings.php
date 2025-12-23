<?php
if (!hasPermission('settings_manage')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$db = new SQLite3(DB_PATH);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ذخیره رنگ‌ها
    if (isset($_POST['theme_colors'])) {
        foreach ($_POST['theme_colors'] as $key => $value) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO system_settings 
                                 (setting_key, setting_value, setting_type) 
                                 VALUES (:key, :value, 'color')");
            $stmt->bindValue(':key', 'color_' . $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $stmt->execute();
        }
    }
    
    // ذخیره تنظیمات عمومی
    if (isset($_POST['general_settings'])) {
        foreach ($_POST['general_settings'] as $key => $value) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO system_settings 
                                 (setting_key, setting_value, setting_type) 
                                 VALUES (:key, :value, 'general')");
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $stmt->execute();
        }
    }
    
    // آپلود لوگو
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
        $upload_dir = 'assets/uploads/logo/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = 'logo_' . time() . '.' . pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $target_file)) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO system_settings 
                                 (setting_key, setting_value, setting_type) 
                                 VALUES ('site_logo', :value, 'file')");
            $stmt->bindValue(':value', $target_file, SQLITE3_TEXT);
            $stmt->execute();
        }
    }
    
    $success = "تنظیمات با موفقیت ذخیره شدند";
}

// دریافت تنظیمات فعلی
$settings_result = $db->query("SELECT * FROM system_settings");
$settings = [];
while ($row = $settings_result->fetchArray(SQLITE3_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$db->close();
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-cogs"></i> تنظیمات سیستم</h5>
    </div>
    
    <div class="card-body">
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="colors-tab" data-bs-toggle="tab" data-bs-target="#colors" type="button">
                    <i class="fas fa-palette"></i> رنگ‌بندی
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                    <i class="fas fa-sliders-h"></i> تنظیمات عمومی
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="logo-tab" data-bs-toggle="tab" data-bs-target="#logo" type="button">
                    <i class="fas fa-image"></i> لوگو و ظاهر
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button">
                    <i class="fas fa-database"></i> پشتیبان‌گیری
                </button>
            </li>
        </ul>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="tab-content" id="settingsTabContent">
                
                <!-- تب رنگ‌بندی -->
                <div class="tab-pane fade show active" id="colors" role="tabpanel">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="color_primary" class="form-label">رنگ اصلی</label>
                            <input type="color" class="form-control form-control-color" 
                                   id="color_primary" name="theme_colors[primary]" 
                                   value="<?php echo $settings['color_primary'] ?? '#3498db'; ?>" 
                                   title="انتخاب رنگ اصلی">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="color_secondary" class="form-label">رنگ ثانویه</label>
                            <input type="color" class="form-control form-control-color" 
                                   id="color_secondary" name="theme_colors[secondary]" 
                                   value="<?php echo $settings['color_secondary'] ?? '#2c3e50'; ?>" 
                                   title="انتخاب رنگ ثانویه">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="color_success" class="form-label">رنگ موفقیت</label>
                            <input type="color" class="form-control form-control-color" 
                                   id="color_success" name="theme_colors[success]" 
                                   value="<?php echo $settings['color_success'] ?? '#27ae60'; ?>" 
                                   title="انتخاب رنگ موفقیت">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="color_danger" class="form-label">رنگ خطا</label>
                            <input type="color" class="form-control form-control-color" 
                                   id="color_danger" name="theme_colors[danger]" 
                                   value="<?php echo $settings['color_danger'] ?? '#e74c3c'; ?>" 
                                   title="انتخاب رنگ خطا">
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6>پیش‌نمایش رنگ‌ها:</h6>
                                    <div class="d-flex gap-2">
                                        <span class="badge" style="background-color: <?php echo $settings['color_primary'] ?? '#3498db'; ?>; padding: 10px;">رنگ اصلی</span>
                                        <span class="badge" style="background-color: <?php echo $settings['color_secondary'] ?? '#2c3e50'; ?>; padding: 10px;">رنگ ثانویه</span>
                                        <span class="badge" style="background-color: <?php echo $settings['color_success'] ?? '#27ae60'; ?>; padding: 10px;">موفقیت</span>
                                        <span class="badge" style="background-color: <?php echo $settings['color_danger'] ?? '#e74c3c'; ?>; padding: 10px;">خطا</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- تب تنظیمات عمومی -->
                <div class="tab-pane fade" id="general" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="site_title" class="form-label">عنوان سایت</label>
                            <input type="text" class="form-control" id="site_title" 
                                   name="general_settings[site_title]" 
                                   value="<?php echo $settings['site_title'] ?? 'سیستم اتوماسیون اداری'; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="items_per_page" class="form-label">تعداد آیتم در هر صفحه</label>
                            <select class="form-select" id="items_per_page" 
                                    name="general_settings[items_per_page]">
                                <option value="10" <?php echo ($settings['items_per_page'] ?? '20') == '10' ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo ($settings['items_per_page'] ?? '20') == '20' ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($settings['items_per_page'] ?? '20') == '50' ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($settings['items_per_page'] ?? '20') == '100' ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_format" class="form-label">فرمت تاریخ</label>
                            <select class="form-select" id="date_format" 
                                    name="general_settings[date_format]">
                                <option value="Y/m/d" <?php echo ($settings['date_format'] ?? 'Y/m/d') == 'Y/m/d' ? 'selected' : ''; ?>>1403/01/01</option>
                                <option value="d/m/Y" <?php echo ($settings['date_format'] ?? 'Y/m/d') == 'd/m/Y' ? 'selected' : ''; ?>>01/01/1403</option>
                                <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y/m/d') == 'Y-m-d' ? 'selected' : ''; ?>>1403-01-01</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="timezone" class="form-label">منطقه زمانی</label>
                            <select class="form-select" id="timezone" 
                                    name="general_settings[timezone]">
                                <option value="Asia/Tehran" selected>تهران (GMT+3:30)</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- تب لوگو -->
                <div class="tab-pane fade" id="logo" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="site_logo" class="form-label">لوگوی سایت</label>
                            <input type="file" class="form-control" id="site_logo" name="site_logo" 
                                   accept=".jpg,.jpeg,.png,.gif">
                            <div class="form-text">فرمت‌های مجاز: JPG, PNG, GIF. حداکثر سایز: 2MB</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <?php if (isset($settings['site_logo']) && !empty($settings['site_logo'])): ?>
                            <label class="form-label">لوگوی فعلی</label><br>
                            <img src="<?php echo $settings['site_logo']; ?>" alt="لوگوی سایت" 
                                 style="max-height: 100px; max-width: 200px;" class="img-thumbnail">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="favicon" class="form-label">Favicon</label>
                            <input type="file" class="form-control" id="favicon" name="favicon" 
                                   accept=".ico,.png">
                            <div class="form-text">فرمت‌های مجاز: ICO, PNG</div>
                        </div>
                    </div>
                </div>
                
                <!-- تب پشتیبان‌گیری -->
                <div class="tab-pane fade" id="backup" role="tabpanel">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        برای پشتیبان‌گیری از دیتابیس، بر روی دکمه زیر کلیک کنید.
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" onclick="backupDatabase()">
                            <i class="fas fa-download"></i> دریافت پشتیبان
                        </button>
                        
                        <button type="button" class="btn btn-warning" onclick="restoreDatabase()">
                            <i class="fas fa-upload"></i> بازیابی پشتیبان
                        </button>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">آخرین پشتیبان‌گیری‌ها:</label>
                        <div id="backupList">
                            <!-- لیست پشتیبان‌ها -->
                        </div>
                    </div>
                </div>
                
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> ذخیره تغییرات
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetSettings()">
                    <i class="fas fa-undo"></i> بازنشانی
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function backupDatabase() {
    // ایجاد پشتیبان از دیتابیس
    fetch('modules/admin/backup.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('پشتیبان‌گیری با موفقیت انجام شد', 'success');
                updateBackupList();
            } else {
                showToast('خطا در پشتیبان‌گیری', 'danger');
            }
        });
}

function restoreDatabase() {
    // انتخاب فایل برای بازیابی
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.db,.sqlite,.sql';
    input.onchange = function(event) {
        const file = event.target.files[0];
        const formData = new FormData();
        formData.append('backup_file', file);
        
        fetch('modules/admin/restore.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('بازیابی با موفقیت انجام شد', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast('خطا در بازیابی', 'danger');
            }
        });
    };
    input.click();
}

function updateBackupList() {
    // بارگذاری لیست پشتیبان‌ها
    fetch('modules/admin/get_backups.php')
        .then(response => response.json())
        .then(data => {
            const backupList = document.getElementById('backupList');
            backupList.innerHTML = '';
            
            if (data.length > 0) {
                const ul = document.createElement('ul');
                ul.className = 'list-group';
                
                data.forEach(backup => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    li.innerHTML = `
                        ${backup.name}
                        <span class="badge bg-secondary">${backup.size}</span>
                    `;
                    ul.appendChild(li);
                });
                
                backupList.appendChild(ul);
            } else {
                backupList.innerHTML = '<p class="text-muted">هیچ پشتیبانی یافت نشد</p>';
            }
        });
}

function resetSettings() {
    if (confirm('آیا از بازنشانی تنظیمات به حالت پیش‌فرض اطمینان دارید؟')) {
        fetch('modules/admin/reset_settings.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('تنظیمات با موفقیت بازنشانی شدند', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('خطا در بازنشانی تنظیمات', 'danger');
                }
            });
    }
}

// بارگذاری لیست پشتیبان‌ها هنگام نمایش تب
document.getElementById('backup-tab').addEventListener('shown.bs.tab', function() {
    updateBackupList();
});
</script>