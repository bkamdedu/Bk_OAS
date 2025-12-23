<?php
if (!hasPermission('reports_view')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$reportType = isset($_GET['type']) ? $_GET['type'] : 'summary';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$department = isset($_GET['department']) ? $_GET['department'] : 'all';

$db = connectDB();

// گرفتن لیست دپارتمان‌ها
$departmentsResult = $db->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = [];
while ($row = $departmentsResult->fetchArray(SQLITE3_ASSOC)) {
    $departments[] = $row['department'];
}

// گزارش خلاصه
if ($reportType == 'summary') {
    // آمار کاربران
    $usersStats = $db->query("SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_users,
        COUNT(DISTINCT department) as departments_count
        FROM users")->fetchArray(SQLITE3_ASSOC);
    
    // آمار اسناد
    $docsStats = $db->query("SELECT 
        COUNT(*) as total_documents,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_documents,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_documents,
        SUM(CASE WHEN file_path IS NOT NULL AND file_path != '' THEN 1 ELSE 0 END) as documents_with_files
        FROM documents")->fetchArray(SQLITE3_ASSOC);
    
    // آمار تسک‌ها
    $tasksStats = $db->query("SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN due_date < DATE('now') AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
        FROM tasks")->fetchArray(SQLITE3_ASSOC);
    
    // آمار نامه‌نگاری
    $corrStats = $db->query("SELECT 
        COUNT(*) as total_letters,
        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_letters,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_letters,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_letters
        FROM correspondence")->fetchArray(SQLITE3_ASSOC);
}

// گزارش فعالیت‌ها
if ($reportType == 'activities') {
    $activitiesQuery = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as activity_count,
        GROUP_CONCAT(DISTINCT action) as actions
        FROM activity_logs
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
        GROUP BY DATE(created_at)
        ORDER BY date DESC";
    
    $stmt = $db->prepare($activitiesQuery);
    $stmt->bindValue(':start_date', $startDate, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $endDate, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $activities = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $activities[] = $row;
    }
}

// گزارش عملکرد کاربران
if ($reportType == 'users') {
    $usersQuery = "SELECT 
        u.full_name,
        u.department,
        u.username,
        r.role_name,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as assigned_tasks,
        (SELECT COUNT(*) FROM tasks WHERE created_by = u.id) as created_tasks,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed') as completed_tasks,
        (SELECT COUNT(*) FROM documents WHERE created_by = u.id) as created_documents,
        (SELECT COUNT(*) FROM correspondence WHERE sender_id = u.id) as sent_letters,
        (SELECT COUNT(*) FROM correspondence WHERE receiver_id = u.id AND status = 'read') as read_letters
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.status = 1";
    
    if ($department != 'all') {
        $usersQuery .= " AND u.department = :department";
    }
    
    $usersQuery .= " ORDER BY u.full_name";
    
    $stmt = $db->prepare($usersQuery);
    if ($department != 'all') {
        $stmt->bindValue(':department', $department, SQLITE3_TEXT);
    }
    $result = $stmt->execute();
    
    $usersReport = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $usersReport[] = $row;
    }
}

closeDB($db);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-chart-line"></i> گزارشات سیستم</h5>
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="printReport()">
                <i class="fas fa-print"></i> چاپ
            </button>
            <button class="btn btn-outline-success" onclick="exportReport()">
                <i class="fas fa-download"></i> خروجی
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <!-- تب‌های گزارشات -->
        <ul class="nav nav-tabs mb-4" id="reportsTab">
            <li class="nav-item">
                <button class="nav-link <?php echo $reportType == 'summary' ? 'active' : ''; ?>" 
                        onclick="loadReport('summary')">
                    <i class="fas fa-chart-pie"></i> خلاصه
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $reportType == 'activities' ? 'active' : ''; ?>" 
                        onclick="loadReport('activities')">
                    <i class="fas fa-history"></i> فعالیت‌ها
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $reportType == 'users' ? 'active' : ''; ?>" 
                        onclick="loadReport('users')">
                    <i class="fas fa-users"></i> عملکرد کاربران
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $reportType == 'tasks' ? 'active' : ''; ?>" 
                        onclick="loadReport('tasks')">
                    <i class="fas fa-tasks"></i> تسک‌ها
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $reportType == 'documents' ? 'active' : ''; ?>" 
                        onclick="loadReport('documents')">
                    <i class="fas fa-folder"></i> اسناد
                </button>
            </li>
        </ul>
        
        <!-- فیلترها -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="page" value="reports">
                    <input type="hidden" name="action" value="reports">
                    <input type="hidden" name="type" id="reportType" value="<?php echo $reportType; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">از تاریخ</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo $startDate; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">تا تاریخ</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo $endDate; ?>">
                    </div>
                    
                    <?php if ($reportType == 'users'): ?>
                    <div class="col-md-3">
                        <label class="form-label">دپارتمان</label>
                        <select name="department" class="form-select">
                            <option value="all">همه دپارتمان‌ها</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo $department == $dept ? 'selected' : ''; ?>>
                                <?php echo $dept; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> اعمال فیلتر
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- محتوای گزارش -->
        <div id="reportContent">
            <?php if ($reportType == 'summary'): ?>
            <!-- گزارش خلاصه -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card bg-primary text-white">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $usersStats['total_users']; ?></h3>
                            <p>کاربران</p>
                            <small><?php echo $usersStats['active_users']; ?> فعال</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-success text-white">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $docsStats['total_documents']; ?></h3>
                            <p>اسناد</p>
                            <small><?php echo $docsStats['active_documents']; ?> فعال</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning text-white">
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $tasksStats['total_tasks']; ?></h3>
                            <p>تسک‌ها</p>
                            <small><?php echo $tasksStats['completed_tasks']; ?> تکمیل شده</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-info text-white">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $corrStats['total_letters']; ?></h3>
                            <p>نامه‌ها</p>
                            <small><?php echo $corrStats['read_letters']; ?> خوانده شده</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- نمودارها -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">وضعیت تسک‌ها</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="tasksChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">وضعیت نامه‌ها</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="lettersChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($reportType == 'activities'): ?>
            <!-- گزارش فعالیت‌ها -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">گزارش فعالیت‌های سیستم</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($activities)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>تعداد فعالیت</th>
                                    <th>انواع فعالیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?php echo $activity['date']; ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $activity['activity_count']; ?></span>
                                    </td>
                                    <td>
                                        <small><?php echo $activity['actions']; ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="viewDailyActivities('<?php echo $activity['date']; ?>')">
                                            <i class="fas fa-eye"></i> مشاهده
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- نمودار فعالیت‌ها -->
                    <div class="mt-4">
                        <canvas id="activitiesChart" height="100"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        هیچ فعالیتی در بازه زمانی انتخاب شده یافت نشد.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($reportType == 'users'): ?>
            <!-- گزارش عملکرد کاربران -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">گزارش عملکرد کاربران</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($usersReport)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>نام کاربر</th>
                                    <th>دپارتمان</th>
                                    <th>نقش</th>
                                    <th>تسک اختصاصی</th>
                                    <th>تسک تکمیل شده</th>
                                    <th>اسناد ایجاد شده</th>
                                    <th>نامه ارسالی</th>
                                    <th>نامه دریافتی</th>
                                    <th>عملکرد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersReport as $user): ?>
                                <?php
                                $totalTasks = $user['assigned_tasks'];
                                $completedTasks = $user['completed_tasks'];
                                $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
                                
                                $performanceClass = '';
                                if ($completionRate >= 80) {
                                    $performanceClass = 'bg-success';
                                } elseif ($completionRate >= 60) {
                                    $performanceClass = 'bg-warning';
                                } else {
                                    $performanceClass = 'bg-danger';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $user['full_name']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $user['username']; ?></small>
                                    </td>
                                    <td><?php echo $user['department']; ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $user['role_name']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $user['assigned_tasks']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $user['completed_tasks']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $user['created_documents']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $user['sent_letters']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo $user['read_letters']; ?></span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px; width: 100px;">
                                            <div class="progress-bar <?php echo $performanceClass; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $completionRate; ?>%">
                                                <?php echo $completionRate; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        هیچ کاربری با فیلترهای انتخاب شده یافت نشد.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- مودال مشاهده فعالیت‌های روزانه -->
<div class="modal fade" id="dailyActivitiesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">فعالیت‌های روزانه</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="dailyActivitiesContent">
                    <!-- محتوای فعالیت‌ها -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
// بارگذاری گزارش
function loadReport(type) {
    document.getElementById('reportType').value = type;
    document.querySelector('form').submit();
}

// مشاهده فعالیت‌های روزانه
function viewDailyActivities(date) {
    fetch(`modules/reports/get_daily_activities.php?date=${date}`)
        .then(response => response.json())
        .then(activities => {
            const content = document.getElementById('dailyActivitiesContent');
            content.innerHTML = '';
            
            if (activities.length === 0) {
                content.innerHTML = '<p class="text-muted">هیچ فعالیتی در این روز یافت نشد</p>';
            } else {
                activities.forEach(activity => {
                    const activityElement = document.createElement('div');
                    activityElement.className = 'activity-item mb-3';
                    activityElement.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${activity.user_name}</strong>
                                <p class="mb-0">${activity.action}</p>
                                <small class="text-muted">${activity.details}</small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${activity.time}</small>
                                <br>
                                <small>${activity.ip_address}</small>
                            </div>
                        </div>
                    `;
                    content.appendChild(activityElement);
                });
            }
            
            const modal = new bootstrap.Modal(document.getElementById('dailyActivitiesModal'));
            modal.show();
        });
}

// چاپ گزارش
function printReport() {
    window.print();
}

// خروجی گزارش
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    const reportType = params.get('type') || 'summary';
    
    window.open(`modules/reports/export_report.php?type=${reportType}&${params.toString()}`, '_blank');
}

// نمودار تسک‌ها
<?php if ($reportType == 'summary'): ?>
const tasksCtx = document.getElementById('tasksChart').getContext('2d');
const tasksChart = new Chart(tasksCtx, {
    type: 'doughnut',
    data: {
        labels: ['در انتظار', 'در حال انجام', 'تکمیل شده', 'تأخیر'],
        datasets: [{
            data: [
                <?php echo $tasksStats['pending_tasks']; ?>,
                <?php echo $tasksStats['in_progress_tasks']; ?>,
                <?php echo $tasksStats['completed_tasks']; ?>,
                <?php echo $tasksStats['overdue_tasks']; ?>
            ],
            backgroundColor: [
                '#6c757d',
                '#ffc107',
                '#28a745',
                '#dc3545'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                rtl: true
            }
        }
    }
});

// نمودار نامه‌ها
const lettersCtx = document.getElementById('lettersChart').getContext('2d');
const lettersChart = new Chart(lettersCtx, {
    type: 'bar',
    data: {
        labels: ['خوانده نشده', 'خوانده شده', 'بایگانی شده'],
        datasets: [{
            label: 'تعداد',
            data: [
                <?php echo $corrStats['unread_letters']; ?>,
                <?php echo $corrStats['read_letters']; ?>,
                <?php echo $corrStats['archived_letters']; ?>
            ],
            backgroundColor: [
                '#dc3545',
                '#28a745',
                '#17a2b8'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// نمودار فعالیت‌ها
<?php if ($reportType == 'activities' && !empty($activities)): ?>
const activitiesCtx = document.getElementById('activitiesChart').getContext('2d');
const activitiesLabels = <?php echo json_encode(array_column($activities, 'date')); ?>;
const activitiesData = <?php echo json_encode(array_column($activities, 'activity_count')); ?>;

const activitiesChart = new Chart(activitiesCtx, {
    type: 'line',
    data: {
        labels: activitiesLabels.reverse(),
        datasets: [{
            label: 'تعداد فعالیت‌ها',
            data: activitiesData.reverse(),
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            fill: true,
            tension: 0.4
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
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>
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

.stat-content small {
    font-size: 12px;
    opacity: 0.8;
}

.activity-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-right: 3px solid var(--primary-color);
}

@media print {
    .card-header, .card-body, .nav-tabs, .btn-group {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    #reportContent {
        display: block !important;
    }
}
</style>