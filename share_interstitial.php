<?php
/**
 * 短链仪式感页面
 * 显示分享信息并让用户选择是否查看
 */
require_once 'config.php';

if (!isset($_GET['c']) || empty($_GET['c'])) {
    header('Location: index.php');
    exit;
}

$short_code = sanitize_input($_GET['c']);
$short_link = get_short_link($short_code);

if (!$short_link) {
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

// 获取音乐详细信息
$music_info = null;
if (!empty($short_link['music_id'])) {
    $conn = getDBConnection();
    $sql = "SELECT title, artist, cover_image, share_reason FROM music WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $short_link['music_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $music_info = $result->fetch_assoc();
    }
    $conn->close();
}

// 获取分享者信息
//神秘分享者
$sharer_name = $short_link['sharer_name'] ?: '无法获取qaq';
$sharer_message = $short_link['sharer_message'] ?: '';

// 设置音乐信息
if ($music_info) {
    $music_title = $music_info['title'] ?: '一首神秘的音乐';
    $music_artist = $music_info['artist'] ?: '未知艺术家';
    $cover_image = $music_info['cover_image'] ?: '';
    $share_reason = $music_info['share_reason'] ?: '';
} else {
    // 如果没有找到音乐信息，使用默认值
    $music_title = '一首神秘的音乐';
    $music_artist = '未知艺术家';
    $cover_image = '';
    $share_reason = '';
}

// 设置页面标题
$page_title = $sharer_name . " 向你分享了 " . $music_title;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - 音乐分享</title>
    <link rel="stylesheet" href="static/css/style.css">
    <style>
    .interstitial-page {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #333;
    }

    .interstitial-header {
        padding: 1rem 0;
        background: rgba(0, 0, 0, 0.1);
    }

    .interstitial-header .navbar {
        background: transparent;
        box-shadow: none;
    }

    .interstitial-header .nav-brand h1 {
        color: white;
        margin: 0;
    }

    .interstitial-header .nav-links a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .interstitial-header .nav-links a:hover {
        color: white;
    }

    .interstitial-content {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .share-card {
        background: white;
        color: #333;
        border-radius: 15px;
        padding: 2.5rem;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        text-align: center;
        position: relative;
        overflow: hidden;
        border: 1px solid #e1e5e9;
    }

    .share-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4, #feca57, #ff9ff3, #54a0ff);
    }

    .sharer-info {
        margin-bottom: 2rem;
    }

    .sharer-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 2rem;
        color: white;
        border: 3px solid white;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .sharer-name {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }

    .sharer-message {
        font-size: 1.1rem;
        color: #5a6c7d;
        font-style: italic;
        margin-bottom: 1rem;
        line-height: 1.5;
    }

    .music-preview {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.5rem;
        margin: 2rem 0;
        border-left: 4px solid #4a90e2;
    }

    .music-cover-preview {
        width: 100px;
        height: 100px;
        border-radius: 10px;
        margin: 0 auto 1rem;
        overflow: hidden;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    }

    .music-cover-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .music-title {
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }

    .music-artist {
        color: #5a6c7d;
        margin-bottom: 1rem;
        font-size: 1rem;
    }

    .share-reason-preview {
        background: #e8f4fd;
        padding: 1rem;
        border-radius: 8px;
        margin: 1rem 0;
        border-left: 3px solid #4a90e2;
        text-align: left;
    }

    .share-reason-preview h4 {
        margin: 0 0 0.5rem 0;
        color: #2c5aa0;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .share-reason-preview p {
        margin: 0;
        color: #333;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn-special {
        padding: 1rem 1.5rem;
        border-radius: 50px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        border: none;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .btn-accept {
        background: linear-gradient(135deg, #4ecdc4, #27ae60);
        color: white;
    }

    .btn-accept:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(78, 205, 196, 0.3);
        background: linear-gradient(135deg, #27ae60, #219653);
        text-decoration: none;
        color: white;
    }

    .btn-decline {
        background: #f8f9fa;
        color: #5a6c7d;
        border: 2px solid #e1e5e9;
    }

    .btn-decline:hover {
        background: #e9ecef;
        transform: translateY(-2px);
        text-decoration: none;
        color: #2c3e50;
        border-color: #5a6c7d;
    }

    .security-note {
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #e1e5e9;
        color: #5a6c7d;
        font-size: 0.85rem;
    }

    .pulse {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            transform: scale(1);
        }
    }

    footer {
        background: rgba(0, 0, 0, 0.1);
        color: rgba(255, 255, 255, 0.8);
        padding: 1rem 0;
        text-align: center;
    }

    footer .container p {
        margin: 0;
        color: rgba(255, 255, 255, 0.8);
    }

    @media (max-width: 768px) {
        .share-card {
            padding: 2rem 1.5rem;
            margin: 1rem;
        }
        
        .action-buttons {
            grid-template-columns: 1fr;
        }
        
        .sharer-avatar {
            width: 70px;
            height: 70px;
            font-size: 1.8rem;
        }
        
        .music-cover-preview {
            width: 80px;
            height: 80px;
        }
        
        .btn-special {
            padding: 0.875rem 1.25rem;
            font-size: 1rem;
        }
    }

    @media (max-width: 480px) {
        .share-card {
            padding: 1.5rem 1rem;
        }
        
        .sharer-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        
        .sharer-name {
            font-size: 1.3rem;
        }
        
        .music-preview {
            padding: 1rem;
            margin: 1.5rem 0;
        }
    }
    </style>
</head>
<body class="interstitial-page">
    <header class="interstitial-header">
        <nav class="navbar">
            <div class="nav-brand">
                <h1>🎵 音乐分享</h1>
            </div>
            <div class="nav-links">
                <a href="index.php">首页</a>
                <a href="random.php">随机音乐</a>
                <a href="admin/login.php">管理员登录</a>
            </div>
        </nav>
    </header>

    <main class="interstitial-content">
        <div class="share-card">
            <div class="sharer-info">
                <div class="sharer-avatar pulse">
                    <?php echo mb_substr($sharer_name, 0, 1); ?>
                </div>
                <div class="sharer-name"><?php echo htmlspecialchars($sharer_name); ?></div>
                <div class="sharer-message">
                    <?php if ($sharer_message): ?>
                        "<?php echo htmlspecialchars($sharer_message); ?>"
                    <?php else: ?>
                        向你分享了一首音乐
                    <?php endif; ?>
                </div>
            </div>

            <div class="music-preview">
                <div class="music-cover-preview">
                    <?php if ($cover_image): ?>
                        <img src="<?php echo $cover_image; ?>" alt="<?php echo htmlspecialchars($music_title); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='🎵';">
                    <?php else: ?>
                        🎵
                    <?php endif; ?>
                </div>
                <div class="music-title"><?php echo htmlspecialchars($music_title); ?></div>
                <div class="music-artist"><?php echo htmlspecialchars($music_artist); ?></div>
                
                <?php if ($share_reason): ?>
                <div class="share-reason-preview">
                    <h4>分享理由</h4>
                    <p><?php echo htmlspecialchars($share_reason); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="action-buttons">
                <a href="<?php echo htmlspecialchars($short_link['original_url']); ?>" class="btn-special btn-accept">
                    🎧 立即收听
                </a>
                <a href="index.php" class="btn-special btn-decline">
                    ❌ 暂时不要
                </a>
            </div>

            <div class="security-note">
                <small>🔒 这是一个安全的分享链接，来自Hmis的小站</small>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Hmis的小站. 保留所有权利.</p>
        </div>
    </footer>

    <script>
    // 添加一些交互效果
    document.addEventListener('DOMContentLoaded', function() {
        const card = document.querySelector('.share-card');
        const buttons = document.querySelectorAll('.btn-special');
        
        // 卡片入场动画
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
        
        // 按钮悬停效果
        buttons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            button.addEventListener('mouseleave', function() {
                if (!this.matches(':hover')) {
                    this.style.transform = 'translateY(0)';
                }
            });
        });
        
        // 添加键盘事件
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                document.querySelector('.btn-accept').click();
            } else if (e.key === 'Escape') {
                document.querySelector('.btn-decline').click();
            }
        });

        // 图片加载失败处理
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                const parent = this.parentElement;
                if (parent.classList.contains('music-cover-preview')) {
                    parent.innerHTML = '🎵';
                }
            });
        });
    });
    </script>
</body>
</html>