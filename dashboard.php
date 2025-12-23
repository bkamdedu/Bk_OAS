<?php
// دریافت آمارهای سیستم
$db = new SQLite3(DB_PATH);

// تعداد کاربران
$users_count = $db->querySingle("SELECT COUNT(*) FROM users WHERE status = 1");

// تعداد اسناد
$documents_count = $db->querySingle("SELECT COUNT(*) FROM documents");

// تعداد تسک‌های جاری
$tasks_count = $db->querySingle("SELECT COUNT(*) FROM tasks WHERE status != 'completed'");

// تعداد نامه‌های جدید
$letters_count = $db->querySingle("SELECT COUNT(*) FROM correspondence WHERE status = 'pending'");

// تسک‌های اختصاص داده شده به کاربر
$user_tasks = $db->query("SELECT * FROM tasks WHERE assigned_to = {$_SESSION['user_id']} AND status != 'completed' ORDER BY due_date LIMIT 5");

// آخرین اسناد
$recent_docs = $db->query("SELECT * FROM documents ORDER BY created_at DESC LIMIT 5");
?>

<div class="row fade-in">
    <!-- کارت‌های آماری -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary mb-1">کاربران فعال</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $users_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success mb-1">تعداد اسناد</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $documents_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-alt fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning mb-1">وظایف جاری</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $tasks_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info mb-1">نامه‌های جدید</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $letters_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-envelope fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- تسک‌های من -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-tasks"></i> وظایف اختصاص یافته به من</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>اولویت</th>
                                <th>مهلت</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($task = $user_tasks->fetchArray(SQLITE3_ASSOC)): ?>
                            <tr onclick="window.location='index.php?page=tasks&action=view&id=<?php echo $task['id']; ?>'" style="cursor: pointer;">
                                <td><?php echo substr($task['title'], 0, 30); ?>...</td>
                                <td>
                                    <span class="badge bg-<?php echo $task['priority'] == 1 ? 'danger' : ($task['priority'] == 2 ? 'warning' : 'info'); ?>">
                                        <?php echo $task['priority'] == 1 ? 'فوری' : ($task['priority'] == 2 ? 'بالا' : 'عادی'); ?>
                                    </span>
                                </td>
                                <td><?php echo $task['due_date']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $task['status']; ?>">
                                        <?php echo $task['status'] == 'pending' ? 'در انتظار' : ($task['status'] == 'in_progress' ? 'در حال انجام' : 'تکمیل شده'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="index.php?page=tasks" class="btn btn-primary btn-sm">مشاهده همه وظایف</a>
            </div>
        </div>
    </div>

    <!-- آخرین اسناد -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-file-alt"></i> آخرین اسناد</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>شماره سند</th>
                                <th>عنوان</th>
                                <th>تاریخ ثبت</th>
                                <th>نوع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = $recent_docs->fetchArray(SQLITE3_ASSOC)): ?>
                            <tr onclick="window.location='index.php?page=documents&action=view&id=<?php echo $doc['id']; ?>'" style="cursor: pointer;">
                                <td><?php echo $doc['document_number']; ?></td>
                                <td><?php echo substr($doc['title'], 0, 25); ?>...</td>
                                <td><?php echo date('Y/m/d', strtotime($doc['created_at'])); ?></td>
                                <td><?php echo $doc['document_type']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="index.php?page=documents" class="btn btn-primary btn-sm">مشاهده همه اسناد</a>
            </div>
        </div>
    </div>
</div>

<!-- نمودارها -->
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-chart-bar"></i> آمار فعالیت‌ها</h6>
            </div>
            <div class="card-body">
                <canvas id="activityChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ایجاد نمودار فعالیت‌ها
const ctx = document.getElementById('activityChart').getContext('2d');
const activityChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور'],
        datasets: [{
            label: 'تعداد اسناد',
            data: [12, 19, 8, 15, 22, 18],
            backgroundColor: 'rgba(52, 152, 219, 0.7)',
            borderColor: 'rgba(52, 152, 219, 1)',
            borderWidth: 1
        }, {
            label: 'تعداد وظایف',
            data: [8, 12, 6, 10, 14, 11],
            backgroundColor: 'rgba(46, 204, 113, 0.7)',
            borderColor: 'rgba(46, 204, 113, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                rtl: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 5
                }
            }
        }
    }
});
</script>