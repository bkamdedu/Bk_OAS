<?php
require_once 'config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $u = $_POST['username'];
    $p = md5($_POST['password']);
    
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE username = :u AND password = :p AND status = 1");
    $stmt->bindValue(':u', $u, SQLITE3_TEXT);
    $stmt->bindValue(':p', $p, SQLITE3_TEXT);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        header('Location: main.php');
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود به اتوماسیون</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; display: flex; align-items: center; height: 100vh; font-family: Tahoma; }
        .card { width: 100%; max-width: 400px; margin: auto; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="card p-4">
        <h3 class="text-center mb-4">ورود به سیستم</h3>
        <?php if($error): ?> <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>
        <form method="POST">
            <div class="mb-3"><label>نام کاربری</label><input type="text" name="username" class="form-control" required></div>
            <div class="mb-3"><label>رمز عبور</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary w-100">ورود به پنل</button>
        </form>
    </div>
</body>
</html>