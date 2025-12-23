<?php
// config.php - secure config helper
// Put environment-specific/secret values into environment variables or a protected .env (not committed).
// This file is safe to commit because it doesn't contain secrets by default.

define('APP_NAME', 'Bk_OAS');

// Path to SQLite DB file (adjust if you move the DB outside webroot)
define('DB_PATH', __DIR__ . '/database/oas.db');

// Base URL (used for redirects). Set to '/' or '/your-subfolder/' as needed.
define('BASE_URL', '/');

// Session settings
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_name('oas_session');
session_start([
    'cookie_lifetime' => 0,
    'use_strict_mode' => 1,
    'cookie_httponly' => 1,
]);

// Error display: on dev enable, in production disable and log instead
if (getenv('OAS_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    ini_set('display_errors', 1);
    ini_set('log_errors', 0);
}
