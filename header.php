<?php
if (!defined('IN_SYSTEM')) {
    die('دسترسی غیرمجاز');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . ' - ' . $page_title; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Sahel Font -->
    <link rel="stylesheet" href="assets/css/sahel-font.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Page Specific CSS -->
    <?php if (isset($page_css)): ?>
    <link rel="stylesheet" href="assets/css/<?php echo $page_css; ?>">
    <?php endif; ?>
    
    <style>
        :root {
            --primary-color: <?php echo $theme_colors['primary']; ?>;
            --secondary-color: <?php echo $theme_colors['secondary']; ?>;
            --success-color: <?php echo $theme_colors['success']; ?>;
            --danger-color: <?php echo $theme_colors['danger']; ?>;
        }
    </style>
</head>
<body>