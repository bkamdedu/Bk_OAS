<?php
$db = connectDB();
$result = $db->query("SELECT * FROM roles ORDER BY id");
$roles = [];

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $roles[] = $row;
}

closeDB($db);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-tag"></i> مدیریت نقش‌ها</h5>
        <a href="index.php?page=admin&action=roles&subaction=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> افزودن نقش جدید
        </a>
    </div>
    
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام نقش</th>
                        <th>توضیحات</th>
                        <th>دسترسی مدیر</th>
                        <th>تعداد کاربران</th>
                        <th>تاریخ ایجاد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $index => $role): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo $role['role_name']; ?></strong>
                            <?php if ($role['is_admin']): ?>
                            <span class="badge bg-danger">مدیر</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $role['description'] ?: '-'; ?></td>
                        <td>
                            <?php echo $role['is_admin'] ? 
                                '<span class="badge bg-success">دارد</span>' : 
                                '<span class="badge bg-secondary">ندارد</span>'; ?>
                        </td>
                        <td>
                            <?php
                            $db = connectDB();
                            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = :role_id");
                            $stmt->bindValue(':role_id', $role['id'], SQLITE3_INTEGER);
                            $result = $stmt->execute();
                            $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
                            closeDB($db);
                            echo $count;
                            ?>
                        </td>
                        <td><?php echo date('Y/m/d', strtotime($role['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=admin&action=roles&subaction=edit&id=<?php echo $role['id']; ?>" 
                                   class="btn btn-outline-primary" title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="index.php?page=admin&action=roles&subaction=permissions&id=<?php echo $role['id']; ?>" 
                                   class="btn btn-outline-info" title="مدیریت دسترسی‌ها">
                                    <i class="fas fa-key"></i>
                                </a>
                                <?php if ($role['id'] != 1 && $role['id'] != $_SESSION['role_id']): ?>
                                <button onclick="deleteRole(<?php echo $role['id']; ?>)" 
                                        class="btn btn-outline-danger" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteRole(roleId) {
    showConfirmationModal(
        'حذف نقش',
        'آیا از حذف این نقش اطمینان دارید؟ کاربرانی که این نقش را دارند به نقش پیش‌فرض منتقل می‌شوند.',
        function() {
            fetch('modules/admin/delete_role.php?id=' + roleId, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('نقش با موفقیت حذف شد', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'خطا در حذف نقش', 'danger');
                }
            });
        }
    );
}
</script>