<?php
/**
 * 短链重定向页面
 * 跳转到仪式感页面
 */
require_once 'config.php';

if (!isset($_GET['c']) || empty($_GET['c'])) {
    header('Location: index.php');
    exit;
}

$short_code = sanitize_input($_GET['c']);
$short_link = get_short_link($short_code);

if ($short_link) {
    // 跳转到仪式感页面
    header('Location: share_interstitial.php?c=' . $short_code);
    exit;
} else {
    // 短链不存在或已过期
    header('HTTP/1.0 404 Not Found');
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>链接不存在 - Hmis的小站</title>
        <link rel="stylesheet" href="static/css/style.css">
    </head>
    <body>
        <header>
            <nav class="navbar">
                <div class="nav-brand">
                    <h1>Hmis的小站</h1>
                </div>
                <div class="nav-links">
                    <a href="index.php">首页</a>
                    <a href="random.php">随机音乐</a>
                </div>
            </nav>
        </header>

        <main class="container">
            <div class="error-page">
                <h1>404 - 链接不存在</h1>
                <p>您访问的短链接不存在或已过期。</p>
                <div class="error-actions">
                    <a href="index.php" class="btn btn-primary">返回首页</a>
                    <a href="random.php" class="btn btn-secondary">随机听歌</a>
                </div>
            </div>
        </main>

        <footer>
            <div class="container">
                <p>&copy; 2025 Hmis的小站. 保留所有权利.</p>
            </div>
        </footer>
    </body>
    </html>
    <?php
    exit;
}
?>