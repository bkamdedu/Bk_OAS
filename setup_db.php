<?php
// D:\Service\oas\setup_db.php
require_once 'config.php';

$db = getDB();

// ۱. جدول نقش‌ها
$db->exec("CREATE TABLE IF NOT EXISTS roles (id INTEGER PRIMARY KEY, role_name TEXT, is_admin INTEGER)");

// ۲. جدول کاربران
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT,
    full_name TEXT,
    role_id INTEGER,
    status INTEGER DEFAULT 1
)");

// ۳. جدول نامه‌ها (اتوماسیون)
$db->exec("CREATE TABLE IF NOT EXISTS letters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    content TEXT,
    sender_id INTEGER,
    receiver_id INTEGER,
    type TEXT, -- وارده / صادره
    priority TEXT, -- عادی / فوری
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read INTEGER DEFAULT 0
)");

// ۴. درج داده‌های اولیه
$db->exec("INSERT OR IGNORE INTO roles (id, role_name, is_admin) VALUES (1, 'مدیر سیستم', 1), (2, 'کارمند', 0)");
$admin_pass = md5('admin');
$db->exec("INSERT OR IGNORE INTO users (username, password, full_name, role_id) VALUES ('admin', '$admin_pass', 'مدیر کل', 1)");

echo "✅ دیتابیس با موفقیت آماده شد. نام کاربری و رمز: admin";
?>