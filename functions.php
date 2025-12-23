<?php
if (!hasPermission('roles_manage')) {
    echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
    return;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'add':
        include 'modules/admin/roles-add.php';
        break;
    case 'edit':
        include 'modules/admin/roles-edit.php';
        break;
    case 'permissions':
        include 'modules/admin/roles-permissions.php';
        break;
    default:
        include 'modules/admin/roles-list.php';
}
?>