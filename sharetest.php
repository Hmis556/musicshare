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

    /* 音效增强面板样式 */
    .audio-enhancement-panel {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 15px;
        padding: 20px;
        color: white;
        z-index: 1000;
        width: 320px;
        display: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        font-family: Arial, sans-serif;
    }

    .enhancement-controls .control-group {
        margin-bottom: 15px;
    }

    .enhancement-controls .control-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .enhancement-controls .control-group input[type="range"] {
        width: 100%;
    }

    .enhancement-controls .control-group select {
        width: 100%;
        padding: 5px;
        border-radius: 5px;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .audio-enhancement-btn {
        background: linear-gradient(135deg, #4a90e2, #9b59b6);
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

    .audio-enhancement-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
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
                <a href="admin/login.php">管理员登录</a>
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
                            <!-- 添加音效增强按钮 -->
                            <button class="control-btn" id="enhancementBtn" title="音效增强">🎛️</button>
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

    <!-- 音效增强面板 -->
    <div class="audio-enhancement-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; color: #4a90e2;">音效增强设置</h3>
            <button id="closeEnhancementPanel" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">×</button>
        </div>
        
        <div class="enhancement-controls">
            <div class="control-group">
                <label for="enhancementToggle">启用音效增强</label>
                <input type="checkbox" id="enhancementToggle">
            </div>
            
            <div class="control-group">
                <label for="presetSelect">音效预设</label>
                <select id="presetSelect">
                    <option value="hall">音乐厅</option>
                    <option value="theater">剧院</option>
                    <option value="club">俱乐部</option>
                    <option value="outdoor">户外</option>
                    <option value="custom">自定义</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="surroundSlider">环绕声: <span id="surroundValue">0.5</span></label>
                <input type="range" id="surroundSlider" min="0" max="1" step="0.01" value="0.5">
            </div>
            
            <div class="control-group">
                <label for="reverbSlider">混响: <span id="reverbValue">0.3</span></label>
                <input type="range" id="reverbSlider" min="0" max="1" step="0.01" value="0.3">
            </div>
            
            <div class="control-group">
                <label for="bassSlider">低音: <span id="bassValue">0</span></label>
                <input type="range" id="bassSlider" min="-1" max="1" step="0.1" value="0">
            </div>
            
            <div class="control-group">
                <label for="trebleSlider">高音: <span id="trebleValue">0</span></label>
                <input type="range" id="trebleSlider" min="-1" max="1" step="0.1" value="0">
            </div>
            
            <div class="control-group">
                <label for="delaySlider">延迟: <span id="delayValue">0</span></label>
                <input type="range" id="delaySlider" min="0" max="1" step="0.01" value="0">
            </div>
            
            <div class="control-group">
                <label for="visualizationSelect">可视化效果</label>
                <select id="visualizationSelect">
                    <option value="spectrum">频谱</option>
                    <option value="waveform">波形</option>
                    <option value="circular">圆形</option>
                    <option value="particles">粒子</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 15px; display: flex; justify-content: space-between;">
            <button id="resetEnhancements" style="background: #e74c3c; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">重置</button>
            <button id="applyEnhancements" style="background: #4a90e2; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">应用</button>
        </div>
    </div>

    <script>
    // 音效增强系统
    class AudioEnhancements {
        constructor() {
            this.isEnhanced = false;
            this.audioContext = null;
            this.source = null;
            this.analyser = null;
            this.stereoPanner = null;
            this.convolver = null;
            this.delay = null;
            this.eq = null;
            this.visualizerCanvas = null;
            this.visualizerCtx = null;
            this.enhancementSettings = {
                surround: 0.5,
                reverb: 0.3,
                bass: 0,
                treble: 0,
                delay: 0,
                visualization: 'spectrum'
            };
            this.presets = {
                hall: { surround: 0.7, reverb: 0.6, bass: 0.2, treble: 0.1, delay: 0.1 },
                theater: { surround: 0.8, reverb: 0.4, bass: 0.3, treble: 0.2, delay: 0.05 },
                club: { surround: 0.6, reverb: 0.5, bass: 0.5, treble: 0.3, delay: 0.2 },
                outdoor: { surround: 0.4, reverb: 0.2, bass: -0.1, treble: 0.1, delay: 0 }
            };
        }

        // 初始化音效增强
        init(audioElement, canvasElement) {
            if (!audioElement || !canvasElement) {
                console.error('音频元素或画布元素未找到');
                return false;
            }

            try {
                // 创建音频上下文
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // 创建音频节点
                this.source = this.audioContext.createMediaElementSource(audioElement);
                this.analyser = this.audioContext.createAnalyser();
                this.stereoPanner = this.audioContext.createStereoPanner();
                this.convolver = this.audioContext.createConvolver();
                this.delay = this.audioContext.createDelay();
                this.eq = {
                    bass: this.audioContext.createBiquadFilter(),
                    mid: this.audioContext.createBiquadFilter(),
                    treble: this.audioContext.createBiquadFilter()
                };
                
                // 配置分析器
                this.analyser.fftSize = 2048;
                this.analyser.smoothingTimeConstant = 0.8;
                
                // 配置均衡器
                this.eq.bass.type = 'lowshelf';
                this.eq.bass.frequency.value = 200;
                
                this.eq.mid.type = 'peaking';
                this.eq.mid.frequency.value = 1000;
                this.eq.mid.Q.value = 1;
                
                this.eq.treble.type = 'highshelf';
                this.eq.treble.frequency.value = 3000;
                
                // 配置延迟
                this.delay.delayTime.value = 0.1;
                
                // 设置可视化画布
                this.visualizerCanvas = canvasElement;
                this.visualizerCtx = canvasElement.getContext('2d');
                
                // 加载脉冲响应（混响）
                this.loadImpulseResponse('hall');
                
                // 连接节点
                this.connectNodes();
                
                this.isEnhanced = true;
                console.log('音效增强已启用');
                return true;
            } catch (error) {
                console.error('音效增强初始化失败:', error);
                return false;
            }
        }

        // 连接音频节点
        connectNodes() {
            if (!this.isEnhanced) return;
            
            // 断开默认连接
            this.source.disconnect();
            
            // 创建效果链
            this.source
                .connect(this.eq.bass)
                .connect(this.eq.mid)
                .connect(this.eq.treble)
                .connect(this.stereoPanner)
                .connect(this.delay)
                .connect(this.convolver)
                .connect(this.analyser)
                .connect(this.audioContext.destination);
        }

        // 加载脉冲响应（混响效果）
        loadImpulseResponse(type) {
            if (!this.convolver) return;
            
            // 创建简单的脉冲响应
            const sampleRate = this.audioContext.sampleRate;
            const length = sampleRate * 2; // 2秒
            const impulse = this.audioContext.createBuffer(2, length, sampleRate);
            const leftChannel = impulse.getChannelData(0);
            const rightChannel = impulse.getChannelData(1);
            
            // 根据类型生成不同的脉冲响应
            for (let i = 0; i < length; i++) {
                // 指数衰减
                const n = i / length;
                let decay;
                
                switch(type) {
                    case 'hall':
                        decay = Math.pow(1 - n, 2) * Math.random() * 2 - 1;
                        break;
                    case 'theater':
                        decay = Math.pow(1 - n, 1.5) * (Math.random() * 1.5 - 0.75);
                        break;
                    case 'club':
                        decay = Math.pow(1 - n, 3) * (Math.random() * 2.5 - 1.25);
                        break;
                    default: // outdoor - 几乎没有混响
                        decay = i === 0 ? 1 : 0;
                }
                
                leftChannel[i] = decay;
                rightChannel[i] = decay * 0.8; // 轻微立体声差异
            }
            
            this.convolver.buffer = impulse;
        }

        // 更新音效设置
        updateSettings(settings) {
            if (!this.isEnhanced) return;
            
            Object.assign(this.enhancementSettings, settings);
            
            // 应用设置
            if (settings.surround !== undefined) {
                this.stereoPanner.pan.value = Math.max(-1, Math.min(1, settings.surround * 2 - 1));
            }
            
            if (settings.reverb !== undefined) {
                // 调整混响增益
                this.convolver.gain = settings.reverb;
            }
            
            if (settings.bass !== undefined) {
                this.eq.bass.gain.value = settings.bass * 20; // -20 到 +20 dB
            }
            
            if (settings.treble !== undefined) {
                this.eq.treble.gain.value = settings.treble * 20; // -20 到 +20 dB
            }
            
            if (settings.delay !== undefined) {
                this.delay.delayTime.value = settings.delay * 0.5; // 最大0.5秒延迟
            }
            
            if (settings.visualization !== undefined) {
                this.enhancementSettings.visualization = settings.visualization;
            }
        }

        // 应用预设
        applyPreset(presetName) {
            if (this.presets[presetName]) {
                this.updateSettings(this.presets[presetName]);
                this.loadImpulseResponse(presetName);
                return true;
            }
            return false;
        }

        // 增强可视化效果
        enhancedVisualization() {
            if (!this.isEnhanced || !this.analyser || !this.visualizerCtx) return;
            
            const canvas = this.visualizerCanvas;
            const ctx = this.visualizerCtx;
            const width = canvas.width;
            const height = canvas.height;
            
            // 获取频率数据
            const bufferLength = this.analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            this.analyser.getByteFrequencyData(dataArray);
            
            // 清除画布
            ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
            ctx.fillRect(0, 0, width, height);
            
            // 根据设置选择可视化类型
            switch(this.enhancementSettings.visualization) {
                case 'waveform':
                    this.drawWaveform(dataArray, width, height);
                    break;
                case 'circular':
                    this.drawCircularSpectrum(dataArray, width, height);
                    break;
                case 'particles':
                    this.drawParticleSpectrum(dataArray, width, height);
                    break;
                default: // spectrum
                    this.drawEnhancedSpectrum(dataArray, width, height);
            }
            
            requestAnimationFrame(() => this.enhancedVisualization());
        }

        // 绘制增强频谱
        drawEnhancedSpectrum(dataArray, width, height) {
            const barWidth = (width / dataArray.length) * 2.5;
            let barHeight;
            let x = 0;
            
            // 创建渐变
            const gradient = this.visualizerCtx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, '#4a90e2');
            gradient.addColorStop(0.5, '#9b59b6');
            gradient.addColorStop(1, '#e74c3c');
            
            for (let i = 0; i < dataArray.length; i++) {
                barHeight = (dataArray[i] / 255) * height;
                
                // 使用渐变填充
                this.visualizerCtx.fillStyle = gradient;
                this.visualizerCtx.fillRect(x, height - barHeight, barWidth, barHeight);
                
                // 添加发光效果
                this.visualizerCtx.shadowBlur = 10;
                this.visualizerCtx.shadowColor = '#4a90e2';
                this.visualizerCtx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
                this.visualizerCtx.strokeRect(x, height - barHeight, barWidth, barHeight);
                
                x += barWidth + 1;
            }
            
            // 重置阴影
            this.visualizerCtx.shadowBlur = 0;
        }

        // 绘制波形
        drawWaveform(dataArray, width, height) {
            this.visualizerCtx.lineWidth = 2;
            this.visualizerCtx.strokeStyle = '#4a90e2';
            this.visualizerCtx.beginPath();
            
            const sliceWidth = width / dataArray.length;
            let x = 0;
            
            for (let i = 0; i < dataArray.length; i++) {
                const v = dataArray[i] / 128.0;
                const y = v * height / 2;
                
                if (i === 0) {
                    this.visualizerCtx.moveTo(x, y);
                } else {
                    this.visualizerCtx.lineTo(x, y);
                }
                
                x += sliceWidth;
            }
            
            this.visualizerCtx.lineTo(width, height / 2);
            this.visualizerCtx.stroke();
        }

        // 绘制圆形频谱
        drawCircularSpectrum(dataArray, width, height) {
            const centerX = width / 2;
            const centerY = height / 2;
            const radius = Math.min(width, height) / 3;
            
            this.visualizerCtx.beginPath();
            this.visualizerCtx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
            this.visualizerCtx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
            this.visualizerCtx.stroke();
            
            const bars = 180; // 显示的条形数量
            const step = Math.PI * 2 / bars;
            
            for (let i = 0; i < bars; i++) {
                const amplitude = dataArray[Math.floor(i * dataArray.length / bars)] / 255;
                const barLength = amplitude * radius * 0.8;
                
                const angle = i * step;
                const x1 = centerX + Math.cos(angle) * radius;
                const y1 = centerY + Math.sin(angle) * radius;
                const x2 = centerX + Math.cos(angle) * (radius + barLength);
                const y2 = centerY + Math.sin(angle) * (radius + barLength);
                
                // 创建渐变线条
                const gradient = this.visualizerCtx.createLinearGradient(x1, y1, x2, y2);
                gradient.addColorStop(0, '#4a90e2');
                gradient.addColorStop(1, '#e74c3c');
                
                this.visualizerCtx.beginPath();
                this.visualizerCtx.moveTo(x1, y1);
                this.visualizerCtx.lineTo(x2, y2);
                this.visualizerCtx.strokeStyle = gradient;
                this.visualizerCtx.lineWidth = 2;
                this.visualizerCtx.stroke();
            }
        }

        // 绘制粒子频谱
        drawParticleSpectrum(dataArray, width, height) {
            const centerX = width / 2;
            const centerY = height / 2;
            
            for (let i = 0; i < dataArray.length; i += 4) {
                const amplitude = dataArray[i] / 255;
                if (amplitude < 0.1) continue;
                
                const angle = (i / dataArray.length) * Math.PI * 2;
                const distance = amplitude * Math.min(width, height) / 3;
                
                const x = centerX + Math.cos(angle) * distance;
                const y = centerY + Math.sin(angle) * distance;
                
                // 粒子大小基于振幅
                const size = amplitude * 5 + 1;
                
                // 粒子颜色基于频率
                const hue = (i / dataArray.length) * 360;
                this.visualizerCtx.fillStyle = `hsla(${hue}, 100%, 60%, ${amplitude})`;
                
                // 绘制粒子
                this.visualizerCtx.beginPath();
                this.visualizerCtx.arc(x, y, size, 0, Math.PI * 2);
                this.visualizerCtx.fill();
                
                // 添加发光效果
                this.visualizerCtx.shadowBlur = 15;
                this.visualizerCtx.shadowColor = `hsla(${hue}, 100%, 60%, 0.7)`;
            }
            
            // 重置阴影
            this.visualizerCtx.shadowBlur = 0;
        }

        // 启用/禁用音效增强
        toggle() {
            if (this.isEnhanced) {
                this.disable();
            } else {
                // 需要在用户交互后启用
                if (this.audioContext && this.audioContext.state === 'suspended') {
                    this.audioContext.resume();
                }
                this.isEnhanced = true;
            }
            return this.isEnhanced;
        }

        // 禁用音效增强
        disable() {
            if (this.source && this.audioContext) {
                // 重新连接到默认输出，绕过效果
                this.source.disconnect();
                this.source.connect(this.audioContext.destination);
            }
            this.isEnhanced = false;
        }

        // 获取当前设置
        getSettings() {
            return {...this.enhancementSettings};
        }

        // 获取可用预设列表
        getPresets() {
            return Object.keys(this.presets);
        }
    }

    // 创建UI控制面板
    class AudioEnhancementUI {
        constructor(audioEnhancements) {
            this.audioEnhancements = audioEnhancements;
            this.panel = null;
            this.isVisible = false;
            
            this.initUI();
        }
        
        initUI() {
            // 面板已经在HTML中创建，只需获取引用
            this.panel = document.querySelector('.audio-enhancement-panel');
            
            this.setupEventListeners();
        }
        
        setupEventListeners() {
            // 关闭按钮
            document.getElementById('closeEnhancementPanel').addEventListener('click', () => {
                this.hidePanel();
            });
            
            // 启用/禁用切换
            document.getElementById('enhancementToggle').addEventListener('change', (e) => {
                const enabled = e.target.checked;
                if (enabled) {
                    this.audioEnhancements.toggle();
                    this.audioEnhancements.enhancedVisualization();
                } else {
                    this.audioEnhancements.disable();
                }
            });
            
            // 预设选择
            document.getElementById('presetSelect').addEventListener('change', (e) => {
                const preset = e.target.value;
                if (preset !== 'custom') {
                    this.audioEnhancements.applyPreset(preset);
                    this.updateSliderValues();
                }
            });
            
            // 滑块事件
            const sliders = ['surround', 'reverb', 'bass', 'treble', 'delay'];
            sliders.forEach(slider => {
                const sliderEl = document.getElementById(`${slider}Slider`);
                const valueEl = document.getElementById(`${slider}Value`);
                
                sliderEl.addEventListener('input', (e) => {
                    valueEl.textContent = e.target.value;
                    // 切换到自定义预设
                    document.getElementById('presetSelect').value = 'custom';
                });
            });
            
            // 可视化效果选择
            document.getElementById('visualizationSelect').addEventListener('change', (e) => {
                this.audioEnhancements.updateSettings({ visualization: e.target.value });
            });
            
            // 重置按钮
            document.getElementById('resetEnhancements').addEventListener('click', () => {
                this.resetSettings();
            });
            
            // 应用按钮
            document.getElementById('applyEnhancements').addEventListener('click', () => {
                this.applySettings();
            });
            
            // 点击面板外部关闭
            document.addEventListener('click', (e) => {
                if (this.isVisible && !this.panel.contains(e.target) && 
                    !e.target.classList.contains('audio-enhancement-btn') &&
                    e.target.id !== 'enhancementBtn') {
                    this.hidePanel();
                }
            });
        }
        
        updateSliderValues() {
            const settings = this.audioEnhancements.getSettings();
            
            document.getElementById('surroundSlider').value = settings.surround;
            document.getElementById('surroundValue').textContent = settings.surround;
            
            document.getElementById('reverbSlider').value = settings.reverb;
            document.getElementById('reverbValue').textContent = settings.reverb;
            
            document.getElementById('bassSlider').value = settings.bass;
            document.getElementById('bassValue').textContent = settings.bass;
            
            document.getElementById('trebleSlider').value = settings.treble;
            document.getElementById('trebleValue').textContent = settings.treble;
            
            document.getElementById('delaySlider').value = settings.delay;
            document.getElementById('delayValue').textContent = settings.delay;
            
            document.getElementById('visualizationSelect').value = settings.visualization;
        }
        
        applySettings() {
            const settings = {
                surround: parseFloat(document.getElementById('surroundSlider').value),
                reverb: parseFloat(document.getElementById('reverbSlider').value),
                bass: parseFloat(document.getElementById('bassSlider').value),
                treble: parseFloat(document.getElementById('trebleSlider').value),
                delay: parseFloat(document.getElementById('delaySlider').value),
                visualization: document.getElementById('visualizationSelect').value
            };
            
            this.audioEnhancements.updateSettings(settings);
            this.hidePanel();
        }
        
        resetSettings() {
            this.audioEnhancements.updateSettings({
                surround: 0.5,
                reverb: 0.3,
                bass: 0,
                treble: 0,
                delay: 0,
                visualization: 'spectrum'
            });
            
            this.updateSliderValues();
            document.getElementById('presetSelect').value = 'custom';
        }
        
        togglePanel() {
            if (this.isVisible) {
                this.hidePanel();
            } else {
                this.showPanel();
            }
        }
        
        showPanel() {
            this.panel.style.display = 'block';
            this.isVisible = true;
            this.updateSliderValues();
        }
        
        hidePanel() {
            this.panel.style.display = 'none';
            this.isVisible = false;
        }
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
            this.enhancementBtn = document.getElementById('enhancementBtn');
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
            this.audioEnhancements = null;
            this.enhancementUI = null;

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
            
            // 初始化音效增强系统
            this.initAudioEnhancements();
            
            // 添加调试信息更新
            setInterval(() => {
                if (document.getElementById('debugInfo').style.display !== 'none') {
                    updateDebugInfo();
                }
            }, 1000);
        }

        // 初始化音效增强系统
        initAudioEnhancements() {
            if (!this.fileExists) return;
            
            try {
                // 创建音效增强实例
                this.audioEnhancements = new AudioEnhancements();
                
                // 初始化音效增强
                const enhanced = this.audioEnhancements.init(this.media, this.visualizerCanvas);
                
                if (enhanced) {
                    // 创建UI控制面板
                    this.enhancementUI = new AudioEnhancementUI(this.audioEnhancements);
                    
                    console.log('音效增强系统已初始化');
                } else {
                    console.warn('音效增强初始化失败，将使用标准播放模式');
                }
            } catch (error) {
                console.error('音效增强初始化错误:', error);
            }
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
            const controls = [this.playPauseBtn, this.volumeBtn, this.volumeSlider, this.enhancementBtn];
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

            // 音效增强按钮
            this.enhancementBtn.addEventListener('click', () => {
                if (this.enhancementUI) {
                    this.enhancementUI.togglePanel();
                } else {
                    alert('音效增强功能尚未初始化');
                }
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
                // 如果音效增强已启用，使用增强版可视化
                if (this.audioEnhancements && this.audioEnhancements.isEnhanced) {
                    this.audioEnhancements.enhancedVisualization();
                    return;
                }
                
                // 原有的可视化代码
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
            // 如果音效增强已启用，跳过原有的可视化
            if (this.audioEnhancements && this.audioEnhancements.isEnhanced) return;
            
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
        const player = new UnifiedPlayer();
        
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