<?php
/**
 * 音乐分享网站首页
 * 显示随机音乐和最新分享 - 添加随机音乐入口
 */
require_once 'config.php';

$conn = getDBConnection();

// 获取随机音乐
$random_music = null;
$sql = "SELECT * FROM music WHERE is_active = TRUE ORDER BY RAND() LIMIT 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $random_music = $result->fetch_assoc();
}

// 获取最新音乐
$latest_music = [];
$sql = "SELECT * FROM music WHERE is_active = TRUE ORDER BY created_at DESC LIMIT 6";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $latest_music[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hmis的小站</title>
    <link rel="stylesheet" href="static/css/style.css">
    <style>
        /* 基础重置 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        /* 容器布局 */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* 导航栏 */
        .navbar {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            gap: 1rem;
        }
        .nav-brand h1 {
            color: #2c3e50;
            font-size: 1.5rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #007bff;
        }
        
        @media (min-width: 768px) {
            .navbar {
                flex-direction: row;
                justify-content: space-between;
            }
            
            .nav-brand h1 {
                font-size: 1.75rem;
            }
        }
        
        /* Hero区域 */
        .hero {
            text-align: center;
            padding: 3rem 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-bottom: 2rem;
            border-radius: 0 0 1rem 1rem;
        }
        
        .hero h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .hero-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* 按钮样式 */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        
        @media (min-width: 640px) {
            .btn {
                font-size: 1rem;
                padding: 0.875rem 1.75rem;
            }
            
            .hero h2 {
                font-size: 2.5rem;
            }
        }
        
        /* 章节样式 */
        section {
            margin-bottom: 3rem;
        }
        
        section h3 {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: #2c3e50;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #007bff;
            border-radius: 2px;
        }
        
        /* 图片自适应样式 */
        .music-cover {
            position: relative;
            overflow: hidden;
            border-radius: 8px 8px 0 0;
        }
        
        .music-cover img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }
        
        .music-card:hover .music-cover img {
            transform: scale(1.05);
        }
        
        .cover-placeholder {
            width: 100%;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            font-size: 3rem;
        }
        
        /* 特色卡片图片 */
        .music-card.featured .music-cover img {
            height: 300px;
        }
        
        .music-card.featured .cover-placeholder {
            height: 300px;
        }
        
        /* 卡片布局 */
        .music-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .music-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .music-card.featured {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        
        @media (min-width: 768px) {
            .music-card.featured {
                flex-direction: row;
                min-height: 300px;
            }
            
            .music-card.featured .music-cover {
                flex: 0 0 300px;
                border-radius: 8px 0 0 8px;
            }
            
            .music-card.featured .music-info {
                flex: 1;
            }
        }
        
        /* 卡片内容 */
        .music-info {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .music-card h4 {
            font-size: 1.25rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .music-card h5 {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .artist {
            color: #7f8c8d;
            font-weight: 500;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        .share-reason {
            color: #555;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            flex: 1;
        }
        
        .music-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: auto;
        }
        
        .music-actions .btn {
            flex: 1;
            font-size: 0.85rem;
            padding: 0.6rem 1rem;
        }
        
        /* 网格布局 */
        .music-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        @media (min-width: 1024px) {
            .music-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* 页脚 */
        footer {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-top: 3rem;
        }
        
        footer p {
            opacity: 0.8;
        }
    </style>
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
                <a href="notice.html">公告</a>
            </div>
        </nav>
    </header>

    <main class="container">
        <section class="hero">
            <h2>Hmis的小站</h2>
            <p>分享音乐，分享快乐！</p>
            <div class="hero-actions">
                <a href="random.php" class="btn btn-primary">随机听歌</a>
                <a href="admin/login.php" class="btn btn-secondary">分享音乐</a>
            </div>
        </section>

        <?php if ($random_music): ?>
        <section class="random-music">
            <h3>随机推荐</h3>
            <div class="music-card featured">
                <div class="music-cover">
                    <?php if ($random_music['cover_image']): ?>
                        <img src="<?php echo $random_music['cover_image']; ?>" alt="<?php echo $random_music['title']; ?>" loading="lazy">
                    <?php else: ?>
                        <div class="cover-placeholder">🎵</div>
                    <?php endif; ?>
                </div>
                <div class="music-info">
                    <h4><?php echo htmlspecialchars($random_music['title']); ?></h4>
                    <p class="artist"><?php echo htmlspecialchars($random_music['artist']); ?></p>
                    <p class="share-reason"><?php echo htmlspecialchars($random_music['share_reason']); ?></p>
                    <div class="music-actions">
                        <a href="share.php?code=<?php echo $random_music['share_code']; ?>" class="btn btn-primary">查看分享</a>
                        <a href="random.php" class="btn btn-primary">换一首</a>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="latest-music">
            <h3>最新分享</h3>
            <div class="music-grid">
                <?php foreach ($latest_music as $music): ?>
                <div class="music-card">
                    <div class="music-cover">
                        <?php if ($music['cover_image']): ?>
                            <img src="<?php echo $music['cover_image']; ?>" alt="<?php echo $music['title']; ?>" loading="lazy">
                        <?php else: ?>
                            <div class="cover-placeholder">🎵</div>
                        <?php endif; ?>
                    </div>
                    <div class="music-info">
                        <h5><?php echo htmlspecialchars($music['title']); ?></h5>
                        <p class="artist"><?php echo htmlspecialchars($music['artist']); ?></p>
                        <div class="music-actions">
                            <a href="share.php?code=<?php echo $music['share_code']; ?>" class="btn btn-primary">查看详情</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Hmis的小站. 保留所有权利.</p>
        </div>
    </footer>

    <script src="static/js/main.js"></script>
</body>
</html>