<?php
if (!hasPermission('users_manage')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'add':
        include 'modules/admin/users-add.php';
        break;
    case 'edit':
        include 'modules/admin/users-edit.php';
        break;
    default:
        include 'modules/admin/users-list.php';
}
?>