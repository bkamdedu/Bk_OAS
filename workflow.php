<?php
if (!hasPermission('tasks_view')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$db = connectDB();

// گرفتن تسک‌های در گردش
$query = "SELECT t.*, 
          u1.full_name as assignee_name,
          u2.full_name as creator_name,
          wf.current_step,
          wf.status as workflow_status
          FROM tasks t
          LEFT JOIN users u1 ON t.assigned_to = u1.id
          LEFT JOIN users u2 ON t.created_by = u2.id
          LEFT JOIN task_workflows wf ON t.id = wf.task_id
          WHERE t.status != 'completed' 
          AND wf.status = 'in_progress'
          ORDER BY t.priority ASC, t.due_date ASC";

$result = $db->query($query);
$tasks = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $tasks[] = $row;
}

// گرفتن مراحل گردش کار
$stepsQuery = $db->query("SELECT * FROM workflow_steps ORDER BY step_order");
$workflowSteps = [];
while ($row = $stepsQuery->fetchArray(SQLITE3_ASSOC)) {
    $workflowSteps[] = $row;
}

closeDB($db);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-project-diagram"></i> گردش کار تسک‌ها</h5>
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="refreshWorkflow()">
                <i class="fas fa-sync-alt"></i> بروزرسانی
            </button>
            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#workflowConfigModal">
                <i class="fas fa-cog"></i> تنظیمات
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <!-- مراحل گردش کار -->
        <div class="workflow-stages mb-5">
            <div class="row">
                <?php foreach ($workflowSteps as $index => $step): ?>
                <div class="col-md">
                    <div class="stage-card <?php echo $index == 0 ? 'active-stage' : ''; ?>" 
                         data-step="<?php echo $step['id']; ?>">
                        <div class="stage-header">
                            <div class="stage-number"><?php echo $index + 1; ?></div>
                            <h6><?php echo $step['step_name']; ?></h6>
                        </div>
                        <div class="stage-body">
                            <p class="stage-description"><?php echo $step['description']; ?></p>
                            <div class="stage-tasks" id="stage-<?php echo $step['id']; ?>">
                                <!-- تسک‌های این مرحله -->
                            </div>
                        </div>
                        <div class="stage-footer">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i>
                                <?php echo $step['estimated_days']; ?> روز
                            </small>
                        </div>
                    </div>
                </div>
                
                <?php if ($index < count($workflowSteps) - 1): ?>
                <div class="col-md-auto d-flex align-items-center">
                    <div class="stage-connector">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- لیست تسک‌های در گردش -->
        <div class="workflow-tasks">
            <h5 class="mb-3"><i class="fas fa-tasks"></i> تسک‌های در حال گردش</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="workflowTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>عنوان تسک</th>
                            <th>مرحله فعلی</th>
                            <th>اختصاص به</th>
                            <th>اولویت</th>
                            <th>مهلت</th>
                            <th>مانده به مهلت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $index => $task): ?>
                        <tr class="workflow-task" 
                            data-task-id="<?php echo $task['id']; ?>"
                            data-current-step="<?php echo $task['current_step']; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <a href="index.php?page=tasks&action=view&id=<?php echo $task['id']; ?>" 
                                   class="text-decoration-none">
                                    <?php echo mb_substr($task['title'], 0, 40); ?>
                                    <?php echo mb_strlen($task['title']) > 40 ? '...' : ''; ?>
                                </a>
                                <br>
                                <small class="text-muted"><?php echo $task['task_number']; ?></small>
                            </td>
                            <td>
                                <?php
                                $stepName = '';
                                foreach ($workflowSteps as $step) {
                                    if ($step['id'] == $task['current_step']) {
                                        $stepName = $step['step_name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="badge bg-info"><?php echo $stepName; ?></span>
                            </td>
                            <td>
                                <?php if ($task['assignee_name']): ?>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm"><?php echo mb_substr($task['assignee_name'], 0, 1); ?></div>
                                    <span class="ms-2"><?php echo $task['assignee_name']; ?></span>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">اختصاص داده نشده</span>
                                <?php endif; ?>
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
                            <td><?php echo date('Y/m/d', strtotime($task['due_date'])); ?></td>
                            <td>
                                <?php
                                $dueDate = new DateTime($task['due_date']);
                                $today = new DateTime();
                                $interval = $today->diff($dueDate);
                                $daysLeft = $interval->days;
                                $daysLeft = $interval->invert ? -$daysLeft : $daysLeft;
                                
                                if ($daysLeft < 0) {
                                    echo '<span class="text-danger">';
                                    echo 'تأخیر: ' . abs($daysLeft) . ' روز';
                                    echo '</span>';
                                } elseif ($daysLeft <= 2) {
                                    echo '<span class="text-warning">';
                                    echo $daysLeft . ' روز';
                                    echo '</span>';
                                } else {
                                    echo '<span class="text-success">';
                                    echo $daysLeft . ' روز';
                                    echo '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" 
                                            onclick="moveTaskForward(<?php echo $task['id']; ?>)"
                                            title="انتقال به مرحله بعد">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" 
                                            onclick="moveTaskBackward(<?php echo $task['id']; ?>)"
                                            title="بازگشت به مرحله قبل">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                    <button class="btn btn-outline-info" 
                                            onclick="showTaskHistory(<?php echo $task['id']; ?>)"
                                            title="تاریخچه گردش">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">هیچ تسکی در گردش کار نیست</p>
                                    <a href="index.php?page=tasks&action=create" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> ایجاد تسک جدید
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- مودال تنظیمات گردش کار -->
<div class="modal fade" id="workflowConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تنظیمات گردش کار</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="workflowConfigContent">
                    <!-- محتوای تنظیمات -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                <button type="button" class="btn btn-primary" onclick="saveWorkflowConfig()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال تاریخچه گردش -->
<div class="modal fade" id="taskHistoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تاریخچه گردش کار</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="taskHistoryContent">
                    <!-- محتوای تاریخچه -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
// بارگذاری تسک‌ها در مراحل
function loadTasksToStages() {
    <?php foreach ($workflowSteps as $step): ?>
    fetch(`modules/tasks/get_tasks_by_step.php?step_id=<?php echo $step['id']; ?>`)
        .then(response => response.json())
        .then(tasks => {
            const stageContainer = document.getElementById(`stage-<?php echo $step['id']; ?>`);
            stageContainer.innerHTML = '';
            
            if (tasks.length === 0) {
                stageContainer.innerHTML = '<p class="text-muted text-center">بدون تسک</p>';
                return;
            }
            
            tasks.forEach(task => {
                const taskElement = document.createElement('div');
                taskElement.className = 'stage-task-item';
                taskElement.draggable = true;
                taskElement.dataset.taskId = task.id;
                
                taskElement.innerHTML = `
                    <div class="stage-task-title">
                        <strong>${task.title.substring(0, 20)}${task.title.length > 20 ? '...' : ''}</strong>
                    </div>
                    <div class="stage-task-meta">
                        <small class="text-muted">${task.assignee_name || 'بدون اختصاص'}</small>
                    </div>
                `;
                
                stageContainer.appendChild(taskElement);
                
                // رویداد درگ و دراپ
                taskElement.addEventListener('dragstart', handleDragStart);
            });
        });
    <?php endforeach; ?>
}

// مدیریت درگ و دراپ
function handleDragStart(e) {
    e.dataTransfer.setData('text/plain', e.target.dataset.taskId);
}

// انتقال تسک به مرحله بعد
function moveTaskForward(taskId) {
    fetch('modules/tasks/move_task_forward.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ task_id: taskId })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('تسک به مرحله بعد منتقل شد', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message || 'خطا در انتقال تسک', 'danger');
        }
    });
}

// بازگشت تسک به مرحله قبل
function moveTaskBackward(taskId) {
    fetch('modules/tasks/move_task_backward.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ task_id: taskId })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('تسک به مرحله قبل بازگشت', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message || 'خطا در بازگشت تسک', 'danger');
        }
    });
}

// نمایش تاریخچه گردش کار تسک
function showTaskHistory(taskId) {
    fetch(`modules/tasks/get_task_history.php?task_id=${taskId}`)
        .then(response => response.json())
        .then(history => {
            const modalContent = document.getElementById('taskHistoryContent');
            modalContent.innerHTML = '';
            
            if (history.length === 0) {
                modalContent.innerHTML = '<p class="text-muted">تاریخچه‌ای یافت نشد</p>';
            } else {
                history.forEach(item => {
                    const historyItem = document.createElement('div');
                    historyItem.className = 'history-item mb-3';
                    historyItem.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <strong>${item.step_name}</strong>
                            <small class="text-muted">${item.created_at}</small>
                        </div>
                        <p class="mb-1">${item.action_description}</p>
                        <small class="text-muted">توسط: ${item.user_name}</small>
                    `;
                    modalContent.appendChild(historyItem);
                });
            }
            
            const modal = new bootstrap.Modal(document.getElementById('taskHistoryModal'));
            modal.show();
        });
}

// بارگذاری تنظیمات گردش کار
function loadWorkflowConfig() {
    fetch('modules/tasks/get_workflow_config.php')
        .then(response => response.text())
        .then(html => {
            document.getElementById('workflowConfigContent').innerHTML = html;
        });
}

// ذخیره تنظیمات گردش کار
function saveWorkflowConfig() {
    const form = document.getElementById('workflowConfigForm');
    const formData = new FormData(form);
    
    fetch('modules/tasks/save_workflow_config.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('تنظیمات ذخیره شد', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('workflowConfigModal'));
            modal.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('خطا در ذخیره تنظیمات', 'danger');
        }
    });
}

// بروزرسانی گردش کار
function refreshWorkflow() {
    showLoader(document.body);
    setTimeout(() => {
        hideLoader(document.body);
        showToast('گردش کار بروزرسانی شد', 'success');
    }, 1000);
}

// رویدادهای اولیه
document.addEventListener('DOMContentLoaded', function() {
    loadTasksToStages();
    
    // رویداد نمایش تنظیمات
    document.getElementById('workflowConfigModal').addEventListener('show.bs.modal', function() {
        loadWorkflowConfig();
    });
});
</script>

<style>
.workflow-stages {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
}

.stage-card {
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s;
    height: 100%;
}

.stage-card.active-stage {
    border-color: var(--primary-color);
    background: rgba(52, 152, 219, 0.05);
}

.stage-header {
    margin-bottom: 15px;
}

.stage-number {
    width: 40px;
    height: 40px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-weight: bold;
    font-size: 18px;
}

.stage-card.active-stage .stage-number {
    background: var(--success-color);
}

.stage-description {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 15px;
    height: 60px;
    overflow: hidden;
}

.stage-tasks {
    min-height: 100px;
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.stage-task-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 8px;
    margin-bottom: 8px;
    cursor: move;
    transition: all 0.2s;
}

.stage-task-item:hover {
    background: #e9ecef;
    transform: translateX(-2px);
}

.stage-connector {
    width: 40px;
    height: 40px;
    background: #6c757d;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.history-item {
    padding: 10px;
    border-right: 3px solid var(--primary-color);
    background: #f8f9fa;
    border-radius: 5px;
}

.avatar-sm {
    width: 30px;
    height: 30px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}
</style>