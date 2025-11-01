<?php
/**
 * 音乐分享页面
 * 通过分享码访问特定音乐并留言 - 统一风格的现代化播放器
 */
require_once 'config.php';

if (!isset($_GET['code'])) {
    header('Location: index.php');
    exit;
}

$share_code = sanitize_input($_GET['code']);
$conn = getDBConnection();

// 获取音乐信息
$sql = "SELECT m.*, sl.click_count as short_link_clicks 
        FROM music m 
        LEFT JOIN short_links sl ON m.short_code = sl.short_code 
        WHERE m.share_code = ? AND m.is_active = TRUE";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $share_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "分享链接无效或音乐已被删除";
    exit;
}

$music = $result->fetch_assoc();
$message = '';

// 处理评论提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    // 获取设置
    $settings_sql = "SELECT * FROM settings WHERE setting_key IN ('daily_comment_limit', 'allow_anonymous')";
    $settings_result = $conn->query($settings_sql);
    $settings = [];
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $user_ip = get_client_ip();
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '匿名用户';
    $content = sanitize_input($_POST['content']);
    
    // 检查每日评论限制
    $today = date('Y-m-d');
    $count_sql = "SELECT COUNT(*) as count FROM comments WHERE user_ip = ? AND DATE(created_at) = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ss", $user_ip, $today);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $comment_count = $count_result->fetch_assoc()['count'];
    
    if ($comment_count >= intval($settings['daily_comment_limit'])) {
        $message = '<div class="alert alert-error">今日评论次数已用完（每个IP每天限' . $settings['daily_comment_limit'] . '次）</div>';
    } elseif (empty($content)) {
        $message = '<div class="alert alert-error">评论内容不能为空</div>';
    } elseif ($settings['allow_anonymous'] == '0' && empty(trim($username))) {
        $message = '<div class="alert alert-error">请填写用户名</label>';
    } else {
        // 插入评论
        $insert_sql = "INSERT INTO comments (music_id, user_ip, username, content) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isss", $music['id'], $user_ip, $username, $content);
        
        if ($insert_stmt->execute()) {
            $message = '<div class="alert alert-success">评论发表成功！</div>';
        } else {
            $message = '<div class="alert alert-error">评论发表失败，请重试</div>';
        }
    }
}

// 获取评论列表
$comments = [];
$sql = "SELECT * FROM comments WHERE music_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $music['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}

$conn->close();

// 检查文件类型
$file_ext = strtolower(pathinfo($music['file_path'], PATHINFO_EXTENSION));
$is_video = in_array($file_ext, ['mp4', 'm4v', 'mov', 'avi', 'webm']);

// 生成链接信息 - 使用根目录URL
$base_url = get_base_url();
$share_url = $base_url . 'share.php?code=' . $music['share_code'];
$short_url = $music['short_code'] ? $base_url . 's.php?c=' . $music['short_code'] : '';

// 检查文件是否存在
$file_exists = file_exists($music['file_path']);

// 获取正确的MIME类型
$mime_types = [
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'mp4' => 'video/mp4',
    'm4v' => 'video/mp4',
    'mov' => 'video/quicktime',
    'avi' => 'video/x-msvideo',
    'webm' => 'video/webm'
];

$mime_type = $mime_types[$file_ext] ?? ($is_video ? 'video/mp4' : 'audio/mpeg');

// 确保文件路径是URL可访问的
$file_url = $music['file_path'];
if (strpos($file_url, 'http') !== 0) {
    // 如果是相对路径，转换为绝对URL
    $file_url = $base_url . ltrim($file_url, '/');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($music['title']); ?> - 音乐分享</title>
    <link rel="stylesheet" href="static/css/style.css">
    <style>
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
    /* 统一播放器样式 */    
    .unified-player {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 2rem;
        margin: 1rem auto;
        max-width: 800px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .player-header {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .cover-art {
        width: 120px;
        height: 120px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        flex-shrink: 0;
    }

    .cover-art img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cover-placeholder {
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        backdrop-filter: blur(10px);
    }

    .track-info {
        flex: 1;
    }

    .track-title {
        font-size: 1.8rem;
        font-weight: bold;
        margin: 0 0 0.5rem 0;
        line-height: 1.2;
    }

    .track-artist {
        font-size: 1.2rem;
        opacity: 0.9;
        margin: 0 0 1rem 0;
    }

    .file-type-badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .file-status {
        margin-top: 0.5rem;
        font-size: 0.9rem;
        padding: 0.3rem 0.8rem;
        border-radius: 15px;
        display: inline-block;
    }

    .file-exists {
        background: rgba(46, 204, 113, 0.3);
        color: #2ecc71;
    }

    .file-missing {
        background: rgba(231, 76, 60, 0.3);
        color: #e74c3c;
    }

    /* 媒体内容区域 */
    .media-content {
        margin-bottom: 2rem;
        border-radius: 15px;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.2);
    }

    .video-player {
        width: 100%;
        height: auto;
        display: block;
        background: #000;
    }

    .audio-visualizer {
        height: 150px;
        position: relative;
        overflow: hidden;
    }

    .visualizer-canvas {
        width: 100%;
        height: 100%;
    }

    /* 播放器控件 */
    .player-controls {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(15px);
        border-radius: 15px;
        padding: 1.5rem;
        margin-top: 1rem;
    }

    .progress-container {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .time-display {
        font-size: 0.9rem;
        opacity: 0.8;
        min-width: 45px;
        font-family: 'Courier New', monospace;
    }

    .progress-bar {
        flex: 1;
        height: 6px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .progress-fill {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        background: linear-gradient(90deg, #4a90e2, #9b59b6);
        border-radius: 3px;
        width: 0%;
        transition: width 0.1s ease;
    }

    .progress-handle {
        position: absolute;
        top: 50%;
        right: -6px;
        width: 12px;
        height: 12px;
        background: white;
        border-radius: 50%;
        transform: translateY(-50%);
        opacity: 0;
        transition: opacity 0.2s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    .progress-bar:hover .progress-handle {
        opacity: 1;
    }

    .control-buttons {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .control-btn {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .control-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
    }

    .play-btn {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
        background: linear-gradient(135deg, #4a90e2, #9b59b6);
    }

    .play-btn:hover {
        background: linear-gradient(135deg, #357abd, #8e44ad);
        transform: scale(1.15);
    }

    .secondary-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .volume-control {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .volume-btn {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.3s ease;
        color: white;
    }

    .volume-btn:hover {
        opacity: 1;
    }

    .volume-slider {
        width: 100px;
        height: 4px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 2px;
        outline: none;
        -webkit-appearance: none;
    }

    .volume-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 14px;
        height: 14px;
        background: white;
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    .extra-controls {
        display: flex;
        gap: 0.5rem;
    }

    /* 歌词容器 - 固定显示样式 */
    .lyrics-container {
        height: 120px; /* 固定高度 */
        overflow: hidden; /* 隐藏滚动条 */
        margin-top: 1.5rem;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .current-lyric-container {
        text-align: center;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .current-lyric {
        font-size: 1.3rem;
        font-weight: 600;
        padding: 0.8rem 0;
        transition: all 0.3s ease;
        opacity: 0.7;
        line-height: 1.4;
    }

    .current-lyric.active {
        opacity: 1;
        font-size: 1.5rem;
        font-weight: bold;
        color: #4a90e2;
        text-shadow: 0 0 10px rgba(74, 144, 226, 0.5);
        transform: scale(1.05);
    }

    .next-lyric {
        font-size: 1rem;
        opacity: 0.5;
        padding: 0.5rem 0;
        line-height: 1.4;
        font-style: italic;
    }

    .no-lyrics {
        text-align: center;
        opacity: 0.7;
        font-style: italic;
        padding: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
    }

    /* 错误提示 */
    .error-message {
        background: rgba(231, 76, 60, 0.2);
        border: 1px solid rgba(231, 76, 60, 0.5);
        border-radius: 10px;
        padding: 1rem;
        margin: 1rem 0;
        text-align: center;
    }

    /* 响应式设计 */
    @media (max-width: 768px) {
        .unified-player {
            padding: 1.5rem;
            margin: 0.5rem;
            border-radius: 15px;
        }

        .player-header {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }

        .cover-art {
            width: 100px;
            height: 100px;
        }

        .track-title {
            font-size: 1.5rem;
        }

        .track-artist {
            font-size: 1.1rem;
        }

        .player-controls {
            padding: 1rem;
        }

        .control-buttons {
            gap: 0.8rem;
        }

        .control-btn {
            width: 45px;
            height: 45px;
            font-size: 1.1rem;
        }

        .play-btn {
            width: 55px;
            height: 55px;
            font-size: 1.3rem;
        }

        .progress-container {
            gap: 0.8rem;
        }

        .time-display {
            font-size: 0.8rem;
            min-width: 40px;
        }

        .secondary-controls {
            flex-direction: column;
            gap: 1rem;
        }

        .volume-control {
            width: 100%;
            justify-content: center;
        }

        .volume-slider {
            width: 120px;
        }

        .extra-controls {
            width: 100%;
            justify-content: center;
        }

        .audio-visualizer {
            height: 120px;
        }

        .lyrics-container {
            height: 100px;
            padding: 0.8rem;
        }
        
        .current-lyric {
            font-size: 1.1rem;
            padding: 0.6rem 0;
        }
        
        .current-lyric.active {
            font-size: 1.3rem;
        }
        
        .next-lyric {
            font-size: 0.9rem;
            padding: 0.4rem 0;
        }
    }

    @media (max-width: 480px) {
        .unified-player {
            padding: 1rem;
        }

        .cover-art {
            width: 80px;
            height: 80px;
        }

        .track-title {
            font-size: 1.3rem;
        }

        .track-artist {
            font-size: 1rem;
        }

        .control-buttons {
            gap: 0.5rem;
        }

        .control-btn {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .play-btn {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }

        .audio-visualizer {
            height: 100px;
        }

        .lyrics-container {
            height: 90px;
            padding: 0.6rem;
        }
        
        .current-lyric {
            font-size: 1rem;
            padding: 0.5rem 0;
        }
        
        .current-lyric.active {
            font-size: 1.2rem;
        }
        
        .next-lyric {
            font-size: 0.8rem;
            padding: 0.3rem 0;
        }
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
        <div class="share-container">
            <!-- 统一播放器 -->
            <div class="unified-player">
                <div class="player-header">
                    <div class="cover-art">
                        <?php if ($music['cover_image']): ?>
                            <img src="<?php echo $music['cover_image']; ?>" alt="<?php echo htmlspecialchars($music['title']); ?>" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>
                        <div class="cover-placeholder" style="<?php echo $music['cover_image'] ? 'display:none' : 'display:flex'; ?>">
                            <?php echo $is_video ? '🎬' : '🎵'; ?>
                        </div>
                    </div>
                    <div class="track-info">
                        <h1 class="track-title"><?php echo htmlspecialchars($music['title']); ?></h1>
                        <h5 class="track-artist"><?php echo htmlspecialchars($music['artist']); ?></h5>
                        <h5 class="file-type-badgeartist">
                            <?php echo $is_video ? '🎬 视频文件' : '🎵 音频文件'; ?>
                        </h5>
                        <div class="file-status <?php echo $file_exists ? 'file-exists' : 'file-missing'; ?>">
                            <?php echo $file_exists ? '✓ 文件可访问' : '✗ 文件不存在'; ?>
                        </div>
                    </div>
                </div>

                <!-- 文件状态提示 -->
                <?php if (!$file_exists): ?>
                <div class="error-message">
                    <strong>警告：</strong> 媒体文件不存在于服务器上。路径：<?php echo htmlspecialchars($music['file_path']); ?>
                </div>
                <?php endif; ?>

                <!-- 媒体内容区域 -->
                <div class="media-content">
                    <?php if ($is_video): ?>
                        <video class="video-player" id="mediaPlayer" 
                               poster="<?php echo $music['cover_image'] ? $music['cover_image'] : ''; ?>"
                               <?php echo !$file_exists ? 'data-error="true"' : ''; ?>
                               controlsList="nodownload">
                            <source src="<?php echo $file_url; ?>" type="<?php echo $mime_type; ?>">
                            您的浏览器不支持视频播放
                        </video>
                    <?php else: ?>
                        <audio id="mediaPlayer" 
                               <?php echo !$file_exists ? 'data-error="true"' : ''; ?>
                               controlsList="nodownload">
                            <source src="<?php echo $file_url; ?>" type="<?php echo $mime_type; ?>">
                            您的浏览器不支持音频播放
                        </audio>
                        <div class="audio-visualizer">
                            <canvas class="visualizer-canvas" id="visualizerCanvas"></canvas>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 播放器控件 -->
                <div class="player-controls">
                    <div class="progress-container">
                        <div class="time-display" id="currentTime">0:00</div>
                        <div class="progress-bar" id="progressBar">
                            <div class="progress-fill" id="progressFill"></div>
                            <div class="progress-handle"></div>
                        </div>
                        <div class="time-display" id="durationTime">0:00</div>
                    </div>

                    <div class="control-buttons">
                        <button class="control-btn" id="prevBtn" title="上一首">⏮️</button>
                        <button class="control-btn play-btn" id="playPauseBtn" title="播放" <?php echo !$file_exists ? 'disabled' : ''; ?>>▶️</button>
                        <button class="control-btn" id="nextBtn" title="下一首">⏭️</button>
                    </div>

                    <div class="secondary-controls">
                        <div class="volume-control">
                            <button class="volume-btn" id="volumeBtn" title="音量" <?php echo !$file_exists ? 'disabled' : ''; ?>>🔊</button>
                            <input type="range" class="volume-slider" id="volumeSlider" min="0" max="100" value="80" title="音量调节" <?php echo !$file_exists ? 'disabled' : ''; ?>>
                        </div>
                        <div class="extra-controls">
                            <?php if ($is_video): ?>
                                <button class="control-btn" id="fullscreenBtn" title="全屏" <?php echo !$file_exists ? 'disabled' : ''; ?>>⛶</button>
                            <?php endif; ?>
                            <button class="control-btn" id="settingsBtn" title="设置">⚙️</button>
                        </div>
                    </div>
                </div>

                <!-- 歌词显示 -->
                <?php if (!$is_video && $music['lyric_path']): ?>
                <div class="lyrics-container">
                    <div class="lyrics-display" id="lyricsDisplay">
                        <div class="no-lyrics">歌词加载中...</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 调试信息 -->
                <div id="debugInfo" style="display: none; margin-top: 1rem; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 8px; font-size: 0.9rem;">
                    <h4>调试信息</h4>
                    <div id="debugContent"></div>
                </div>
            </div>

            <div class="music-details">
                <div class="link-info-box">
                    <h3>分享理由</h3>
                    <div class='link-item'>
                        <p><?php echo nl2br(htmlspecialchars($music['share_reason'])); ?></p>
                </div>
                    <div class="share-actions">
                    <div><p><?php echo '&nbsp;' ?></p></div>
                    <button onclick="copyShareLink()" class="btn btn-primary">复制分享链接</button>
                    <?php if ($short_url): ?>
                    <button onclick="copyShortLink()" class="btn btn-secondary">复制短链</button>
                    <?php endif; ?>
                    <button onclick="window.location.href = 'index.php'" class="btn btn-secondary">发现更多音乐</button>
                    <button onclick="toggleDebug()" class="btn btn-secondary">调试信息</button>
                    <button onclick="testMediaFile()" class="btn btn-secondary">测试媒体文件</button>
                    <button onclick="goToSharePage()" class="btn btn-secondary">前往测试版播放器</button>
                </div>
                    
                </div>



                <?php if ($short_url): ?>
                <div class="link-info-box">
                    <h4>分享数据</h4>
                    <div class="link-items">
                        <div class="link-item">
                            <strong>短链已经被打开 <?php echo $music['short_link_clicks']; ?> 次</strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="comments-section">
            <h2>留言评论</h2>
            
            <?php echo $message; ?>

            <div class="comment-form">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">用户名</label>
                            <input type="text" id="username" name="username" placeholder="匿名用户">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="content">评论内容 *</label>
                        <textarea id="content" name="content" placeholder="写下你的感受..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">发表评论</button>
                </form>
            </div>

            <div class="comment-list">
                <h3>全部评论 (<?php echo count($comments); ?>)</h3>
                <?php if (count($comments) > 0): ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <span class="comment-username"><?php echo htmlspecialchars($comment['username']); ?></span>
                            <span class="comment-time"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
                        </div>
                        <div class="comment-content">
                            <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center">暂无评论，快来发表第一条评论吧！</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Hmis的小站. 保留所有权利.</p>
        </div>
    </footer>

    <script>
function goToSharePage() {
  // 获取当前 URL 中的 code 参数
  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get('code');

  // 构造目标 URL：share.php?code=...
  let url = 'test_share.php';
  if (code !== null) {
    url += `?code=${encodeURIComponent(code)}`;
  }

  // 跳转
  window.location.href = url;
}
    // 调试功能
    function toggleDebug() {
        const debugInfo = document.getElementById('debugInfo');
        const isVisible = debugInfo.style.display !== 'none';
        debugInfo.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            updateDebugInfo();
        }
    }

    function updateDebugInfo() {
        const debugContent = document.getElementById('debugContent');
        const media = document.getElementById('mediaPlayer');
        
        let debugHtml = `
            <p><strong>文件路径:</strong> <?php echo htmlspecialchars($music['file_path']); ?></p>
            <p><strong>文件URL:</strong> <?php echo htmlspecialchars($file_url); ?></p>
            <p><strong>文件存在:</strong> <?php echo $file_exists ? '是' : '否'; ?></p>
            <p><strong>媒体类型:</strong> <?php echo $is_video ? '视频' : '音频'; ?></p>
            <p><strong>文件扩展名:</strong> <?php echo $file_ext; ?></p>
            <p><strong>MIME类型:</strong> <?php echo $mime_type; ?></p>
            <p><strong>当前状态:</strong> ${media.paused ? '暂停' : '播放'}</p>
            <p><strong>错误状态:</strong> ${media.error ? media.error.message : '无错误'}</p>
            <p><strong>就绪状态:</strong> ${media.readyState}</p>
            <p><strong>网络状态:</strong> ${media.networkState}</p>
            <p><strong>可播放:</strong> ${media.canPlayType ? '支持' : '不支持'}</p>
            <p><strong>音量:</strong> ${media.volume}</p>
            <p><strong>静音:</strong> ${media.muted}</p>
            <p><strong>时长:</strong> ${media.duration || '未知'}</p>
            <p><strong>当前时间:</strong> ${media.currentTime || '0'}</p>
        `;
        
        debugContent.innerHTML = debugHtml;
    }

    // 测试媒体文件
    function testMediaFile() {
        const media = document.getElementById('mediaPlayer');
        const testUrl = '<?php echo $file_url; ?>';
        
        fetch(testUrl, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    const size = response.headers.get('content-length');
                    const type = response.headers.get('content-type');
                    alert(`文件测试成功！\n大小: ${size} bytes\n类型: ${type}`);
                } else {
                    alert(`文件测试失败！状态码: ${response.status}`);
                }
            })
            .catch(error => {
                alert(`文件测试出错: ${error.message}`);
            });
    }

    // 统一播放器控制器
    class UnifiedPlayer {
        constructor() {
            this.isVideo = <?php echo $is_video ? 'true' : 'false'; ?>;
            this.media = document.getElementById('mediaPlayer');
            this.playPauseBtn = document.getElementById('playPauseBtn');
            this.progressBar = document.getElementById('progressBar');
            this.progressFill = document.getElementById('progressFill');
            this.currentTimeEl = document.getElementById('currentTime');
            this.durationTimeEl = document.getElementById('durationTime');
            this.volumeBtn = document.getElementById('volumeBtn');
            this.volumeSlider = document.getElementById('volumeSlider');
            this.fullscreenBtn = document.getElementById('fullscreenBtn');
            this.visualizerCanvas = document.getElementById('visualizerCanvas');
            this.ctx = this.visualizerCanvas ? this.visualizerCanvas.getContext('2d') : null;
            this.audioContext = null;
            this.analyser = null;
            this.dataArray = null;
            this.lyrics = [];
            this.currentLyricIndex = -1;
            this.lyricsContainer = document.querySelector('.lyrics-container');
            this.lyricsDisplay = document.getElementById('lyricsDisplay');
            this.fileExists = <?php echo $file_exists ? 'true' : 'false'; ?>;
            this.mediaLoaded = false;

            this.init();
        }

        init() {
            if (!this.fileExists) {
                this.showFileError();
                return;
            }
            
            this.setupMediaElement();
            this.setupEventListeners();
            this.setupVisualizer();
            this.loadLyrics();
            this.updateVolumeIcon();
            
            // 添加调试信息更新
            setInterval(() => {
                if (document.getElementById('debugInfo').style.display !== 'none') {
                    updateDebugInfo();
                }
            }, 1000);
        }

        showFileError() {
            const mediaContent = document.querySelector('.media-content');
            const errorHtml = `
                <div class="error-message">
                    <strong>无法播放媒体文件</strong><br>
                    文件路径: ${this.media.querySelector('source').src}<br>
                    请检查文件是否存在或路径是否正确
                </div>
            `;
            mediaContent.innerHTML += errorHtml;
            
            // 禁用所有控件
            const controls = [this.playPauseBtn, this.volumeBtn, this.volumeSlider];
            if (this.fullscreenBtn) controls.push(this.fullscreenBtn);
            
            controls.forEach(control => {
                if (control) {
                    control.disabled = true;
                    control.style.opacity = '0.5';
                    control.style.cursor = 'not-allowed';
                }
            });
        }

        setupMediaElement() {
            // 设置初始音量
            this.media.volume = this.volumeSlider.value / 100;

            // 视频特定设置
            if (this.isVideo) {
                this.media.setAttribute('controls', 'false');
                this.media.style.width = '100%';
                this.media.style.height = 'auto';
                this.media.style.borderRadius = '10px';
            }

            // 预加载元数据
            this.media.preload = 'metadata';
            
            // 设置跨域属性（如果需要）
            if (this.media.querySelector('source').src.indexOf('http') === 0) {
                this.media.crossOrigin = 'anonymous';
            }
        }

        setupEventListeners() {
            // 播放/暂停
            this.playPauseBtn.addEventListener('click', () => this.togglePlay());

            // 进度控制
            this.progressBar.addEventListener('click', (e) => this.seek(e));
            this.media.addEventListener('timeupdate', () => this.updateProgress());
            this.media.addEventListener('loadedmetadata', () => {
                this.durationTimeEl.textContent = this.formatTime(this.media.duration);
                this.mediaLoaded = true;
                console.log('媒体元数据加载完成，时长:', this.media.duration);
            });

            this.media.addEventListener('canplay', () => {
                console.log('媒体可以开始播放');
                this.mediaLoaded = true;
            });

            this.media.addEventListener('waiting', () => {
                console.log('媒体等待加载更多数据');
            });

            // 音量控制
            this.volumeSlider.addEventListener('input', () => {
                this.media.volume = this.volumeSlider.value / 100;
                this.updateVolumeIcon();
            });

            this.volumeBtn.addEventListener('click', () => {
                this.media.muted = !this.media.muted;
                this.updateVolumeIcon();
            });

            // 全屏控制（视频）
            if (this.isVideo && this.fullscreenBtn) {
                this.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
            }

            // 媒体事件
            this.media.addEventListener('play', () => {
                this.playPauseBtn.innerHTML = '⏸️';
                this.playPauseBtn.title = '暂停';
                console.log('开始播放');
            });

            this.media.addEventListener('pause', () => {
                this.playPauseBtn.innerHTML = '▶️';
                this.playPauseBtn.title = '播放';
                console.log('播放暂停');
            });

            this.media.addEventListener('ended', () => {
                this.playPauseBtn.innerHTML = '▶️';
                this.playPauseBtn.title = '播放';
                this.progressFill.style.width = '0%';
                this.currentTimeEl.textContent = '0:00';
                console.log('播放结束');
            });

            // 错误处理
            this.media.addEventListener('error', (e) => {
                console.error('媒体加载错误:', e);
                console.error('媒体错误详情:', this.media.error);
                this.showMediaError();
            });

            // 键盘快捷键
            document.addEventListener('keydown', (e) => {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                
                switch(e.key) {
                    case ' ':
                        e.preventDefault();
                        this.togglePlay();
                        break;
                    case 'f':
                        if (this.isVideo) {
                            e.preventDefault();
                            this.toggleFullscreen();
                        }
                        break;
                    case 'm':
                        e.preventDefault();
                        this.media.muted = !this.media.muted;
                        this.updateVolumeIcon();
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        this.media.currentTime = Math.max(0, this.media.currentTime - 10);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        this.media.currentTime = Math.min(this.media.duration, this.media.currentTime + 10);
                        break;
                }
            });
        }

        showMediaError() {
            const error = this.media.error;
            let message = '媒体播放错误: ';
            
            switch(error.code) {
                case error.MEDIA_ERR_ABORTED:
                    message += '播放被中止';
                    break;
                case error.MEDIA_ERR_NETWORK:
                    message += '网络错误';
                    break;
                case error.MEDIA_ERR_DECODE:
                    message += '解码错误 - 文件格式可能不被支持';
                    break;
                case error.MEDIA_ERR_SRC_NOT_SUPPORTED:
                    message += '不支持的格式';
                    break;
                default:
                    message += '未知错误';
            }
            
            alert(message + '\n\n文件路径: ' + this.media.querySelector('source').src);
        }

        setupVisualizer() {
            if (this.isVideo || !this.ctx || !this.fileExists) return;

            try {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                this.analyser = this.audioContext.createAnalyser();
                const source = this.audioContext.createMediaElementSource(this.media);
                
                this.analyser.fftSize = 256;
                source.connect(this.analyser);
                this.analyser.connect(this.audioContext.destination);
                
                this.bufferLength = this.analyser.frequencyBinCount;
                this.dataArray = new Uint8Array(this.bufferLength);
                
                this.animateVisualizer();
            } catch (error) {
                console.warn('音频可视化初始化失败:', error);
            }
        }

        animateVisualizer() {
            if (!this.analyser || !this.ctx || !this.fileExists) return;

            requestAnimationFrame(() => this.animateVisualizer());
            
            this.analyser.getByteFrequencyData(this.dataArray);
            
            const width = this.visualizerCanvas.width;
            const height = this.visualizerCanvas.height;
            
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
            this.ctx.fillRect(0, 0, width, height);
            
            const barWidth = (width / this.bufferLength) * 2.5;
            let barHeight;
            let x = 0;
            
            // 创建渐变效果
            const gradient = this.ctx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, '#4a90e2');
            gradient.addColorStop(0.5, '#9b59b6');
            gradient.addColorStop(1, '#e74c3c');
            
            for (let i = 0; i < this.bufferLength; i++) {
                barHeight = (this.dataArray[i] / 255) * height;
                
                this.ctx.fillStyle = gradient;
                this.ctx.fillRect(x, height - barHeight, barWidth, barHeight);
                
                x += barWidth + 1;
            }
        }

        togglePlay() {
            if (!this.fileExists) {
                alert('媒体文件不存在，无法播放');
                return;
            }

            if (!this.mediaLoaded) {
                alert('媒体文件尚未加载完成，请稍后再试');
                return;
            }

            if (this.media.paused) {
                // 解决浏览器自动播放策略问题
                const playPromise = this.media.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        // 自动播放成功
                        console.log('自动播放成功');
                    }).catch(error => {
                        // 自动播放失败，需要用户交互
                        console.log('自动播放被阻止，需要用户交互:', error);
                        // 显示提示信息
                        this.showPlayError(error);
                    });
                }
            } else {
                this.media.pause();
            }
        }

        showPlayError(error) {
            let message = '播放失败: ';
            
            if (error.name === 'NotAllowedError') {
                message += '浏览器禁止自动播放，请手动点击播放按钮';
            } else if (error.name === 'NotSupportedError') {
                message += '不支持的媒体格式';
            } else {
                message += error.message;
            }
            
            alert(message);
        }

        seek(e) {
            if (!this.fileExists || !this.mediaLoaded) return;
            
            const rect = this.progressBar.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.media.currentTime = percent * this.media.duration;
        }

        updateProgress() {
            if (!this.media.duration || !this.fileExists || !this.mediaLoaded) return;
            
            const percent = (this.media.currentTime / this.media.duration) * 100;
            this.progressFill.style.width = percent + '%';
            this.currentTimeEl.textContent = this.formatTime(this.media.currentTime);
            
            // 更新歌词
            this.updateLyrics();
        }

        updateVolumeIcon() {
            if (this.media.muted || this.media.volume === 0) {
                this.volumeBtn.innerHTML = '🔇';
                this.volumeBtn.title = '取消静音';
            } else if (this.media.volume < 0.5) {
                this.volumeBtn.innerHTML = '🔈';
                this.volumeBtn.title = '静音';
            } else {
                this.volumeBtn.innerHTML = '🔊';
                this.volumeBtn.title = '静音';
            }
        }

        toggleFullscreen() {
            if (!this.fileExists) return;
            
            if (!document.fullscreenElement) {
                this.media.requestFullscreen().catch(err => {
                    console.error('全屏模式失败:', err);
                });
            } else {
                document.exitFullscreen();
            }
        }

        formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        }

        loadLyrics() {
            const lyricPath = '<?php echo $music['lyric_path'] ?? ''; ?>';
            if (!lyricPath || this.isVideo || !this.fileExists) return;

            fetch(lyricPath)
                .then(response => {
                    if (!response.ok) throw new Error('歌词文件加载失败');
                    return response.text();
                })
                .then(lyricText => this.parseLyrics(lyricText))
                .catch(error => {
                    console.error('歌词加载失败:', error);
                    if (this.lyricsDisplay) {
                        this.lyricsDisplay.innerHTML = '<div class="no-lyrics">暂无歌词</div>';
                    }
                });
        }

        parseLyrics(lyricText) {
            const lines = lyricText.split('\n');
            const timeRegex = /\[(\d+):(\d+)(?:\.(\d+))?\]/g; // 改进正则，支持可选毫秒
            
            lines.forEach(line => {
                const matches = [...line.matchAll(timeRegex)];
                const text = line.replace(timeRegex, '').trim();
                
                if (matches.length > 0 && text) {
                    matches.forEach(match => {
                        const minutes = parseInt(match[1]);
                        const seconds = parseInt(match[2]);
                        const milliseconds = match[3] ? parseInt(match[3]) : 0;
                        const time = minutes * 60 + seconds + milliseconds / 100;
                        
                        this.lyrics.push({ time, text });
                    });
                }
            });
            
            // 按时间排序
            this.lyrics.sort((a, b) => a.time - b.time);
            this.renderLyrics();
        }

        renderLyrics() {
            if (!this.lyricsDisplay || this.lyrics.length === 0) {
                if (this.lyricsDisplay) {
                    this.lyricsDisplay.innerHTML = '<div class="no-lyrics">暂无歌词</div>';
                }
                return;
            }

            // 改为固定显示区域，不显示所有歌词行
            this.lyricsDisplay.innerHTML = `
                <div class="current-lyric-container">
                    <div class="current-lyric" id="currentLyricLine">准备播放</div>
                    <div class="next-lyric" id="nextLyricLine"></div>
                </div>
            `;
            
            this.currentLyricElement = document.getElementById('currentLyricLine');
            this.nextLyricElement = document.getElementById('nextLyricLine');
        }

        updateLyrics() {
            if (this.lyrics.length === 0 || !this.fileExists || !this.lyricsDisplay) return;
            
            const currentTime = this.media.currentTime;
            let currentIndex = -1;
            let nextIndex = -1;
            
            // 找到当前歌词和下一句歌词
            for (let i = 0; i < this.lyrics.length; i++) {
                if (currentTime >= this.lyrics[i].time) {
                    // 如果这是最后一句歌词，或者下一句歌词的时间大于当前时间
                    if (i === this.lyrics.length - 1 || currentTime < this.lyrics[i + 1].time) {
                        currentIndex = i;
                        nextIndex = i < this.lyrics.length - 1 ? i + 1 : -1;
                        break;
                    }
                }
            }
            
            // 更新当前歌词显示
            if (currentIndex !== -1) {
                const currentLyric = this.lyrics[currentIndex];
                this.currentLyricElement.textContent = currentLyric.text;
                this.currentLyricElement.classList.add('active');
                
                // 显示下一句歌词
                if (nextIndex !== -1) {
                    this.nextLyricElement.textContent = this.lyrics[nextIndex].text;
                } else {
                    this.nextLyricElement.textContent = '';
                }
            } else {
                // 歌曲刚开始，还没有匹配的歌词
                this.currentLyricElement.textContent = '准备播放';
                this.currentLyricElement.classList.remove('active');
                this.nextLyricElement.textContent = this.lyrics.length > 0 ? this.lyrics[0].text : '';
            }
        }

        escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    }

    // 初始化播放器
    document.addEventListener('DOMContentLoaded', function() {
        new UnifiedPlayer();
        
        // 调整可视化画布大小
        const canvas = document.getElementById('visualizerCanvas');
        if (canvas) {
            const resizeCanvas = () => {
                canvas.width = canvas.offsetWidth;
                canvas.height = canvas.offsetHeight;
            };
            
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
        }
    });

    // 分享功能
    function copyShareLink() {
        const shareUrl = '<?php echo $share_url; ?>';
        copyToClipboard(shareUrl, '分享链接');
    }

    function copyShortLink() {
        const shortUrl = '<?php echo $short_url; ?>';
        copyToClipboard(shortUrl, '短链');
    }

    function copyToClipboard(text, type) {
        navigator.clipboard.writeText(text).then(function() {
            alert(type + '已复制到剪贴板！');
        }, function() {
            // 备用方案
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            alert(type + '已复制到剪贴板！');
        });
    }
    </script>
</body>
</html>