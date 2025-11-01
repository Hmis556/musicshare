<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hmis 的小站</title>
    
    <?php
    /**
     * 数据库配置和函数
     */
    require_once 'config.php';
    
    // 获取基础URL
    $base_url = get_base_url();
    
    // 获取音乐信息
    $music = null;
    $lyrics_content = '';
    $file_exists = false;
    $file_url = '';
    $mime_type = '';
    $is_video = false;
    $cover_image_url = '';
    
    if (isset($_GET['code'])) {
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
        
        if ($result->num_rows > 0) {
            $music = $result->fetch_assoc();
            
            // 检查文件类型
            $file_ext = strtolower(pathinfo($music['file_path'], PATHINFO_EXTENSION));
            $is_video = in_array($file_ext, ['mp4', 'm4v', 'mov', 'avi', 'webm']);
            
            // 获取MIME类型
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
            
            // 获取文件URL - 处理相对路径
            $file_path = $music['file_path'];
            if (strpos($file_path, 'http') !== 0) {
                // 如果是相对路径，转换为绝对URL
                $file_url = $base_url . ltrim($file_path, '/');
            } else {
                $file_url = $file_path;
            }
            
            // 检查文件是否存在 - 处理相对路径
            $absolute_file_path = $file_path;
            if (strpos($file_path, 'http') !== 0) {
                // 如果是相对路径，转换为服务器绝对路径
                $absolute_file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($file_path, '/');
            }
            $file_exists = file_exists($absolute_file_path);
            
            // 加载歌词文件 - 处理相对路径
            if ($music['lyric_path']) {
                $lyric_path = $music['lyric_path'];
                if (strpos($lyric_path, 'http') !== 0) {
                    // 如果是相对路径，转换为服务器绝对路径
                    $absolute_lyric_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($lyric_path, '/');
                } else {
                    $absolute_lyric_path = $lyric_path;
                }
                
                if (file_exists($absolute_lyric_path)) {
                    $lyrics_content = file_get_contents($absolute_lyric_path);
                }
            }
            
            // 处理封面图片URL
            if ($music['cover_image']) {
                $cover_image = $music['cover_image'];
                if (strpos($cover_image, 'http') !== 0) {
                    // 如果是相对路径，转换为绝对URL
                    $cover_image_url = $base_url . ltrim($cover_image, '/');
                } else {
                    $cover_image_url = $cover_image;
                }
            }
        }
        $conn->close();
    }
    ?>
    
    <style>
        /* 所有CSS样式保持不变 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            min-height: 100vh;
            overflow: hidden;
            transition: background 0.5s ease;
            position: relative;
        }

        body.custom-bg {
            background: none;
        }

        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
            opacity: 0.7;
            transition: opacity 0.5s ease;
        }

        .container {
            position: relative;
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .player-controls {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 10;
            transition: all 0.5s ease;
        }

        .player-controls.collapsed {
            transform: translateX(-50%) translateY(calc(100% - 50px));
        }

        .toggle-controls {
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 80px;
            height: 30px;
            border-radius: 15px 15px 0 0;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .toggle-controls:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        .song-info {
            text-align: center;
            margin-bottom: 15px;
        }

        .song-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .song-artist {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.7);
        }

        .file-info {
            text-align: center;
            margin-bottom: 10px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .file-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 5px;
        }

        .file-exists {
            background: rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }

        .file-missing {
            background: rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }

        .progress-area {
            margin-bottom: 15px;
        }

        .progress-bar {
            height: 6px;
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            cursor: pointer;
            margin-bottom: 5px;
        }

        .progress {
            height: 100%;
            width: 0%;
            border-radius: 10px;
            position: relative;
            transition: width 0.1s linear;
        }

        .time {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 25px;
            margin-bottom: 20px;
        }

        .control-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .control-btn:hover {
            transform: scale(1.1);
        }

        .play-pause {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
        }

        .upload-area {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .upload-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .upload-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .file-name {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            margin-top: 5px;
        }

        .hidden {
            display: none;
        }

        /* 全屏歌词样式 */
        .lyrics-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: calc(100% - 200px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            z-index: 1;
            overflow: hidden;
        }

        .lyric-line {
            position: absolute;
            font-size: 2.5rem;
            text-align: center;
            opacity: 0;
            transform: translateY(50px) scale(0.9);
            transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            max-width: 90%;
            line-height: 1.4;
            will-change: transform, opacity;
            pointer-events: none;
            white-space: pre-wrap;
        }

        .lyric-line.active {
            opacity: 1;
            transform: translateY(0) scale(1);
            font-weight: 600;
        }

        .lyric-line.previous {
            opacity: 0.4;
            transform: translateY(-30px) scale(0.95);
            color: rgba(255, 255, 255, 0.7);
        }

        .lyric-line.next {
            opacity: 0.3;
            transform: translateY(30px) scale(0.9);
            color: rgba(255, 255, 255, 0.5);
        }

        /* 逐字效果 */
        .char {
            display: inline-block;
            opacity: 0.5;
            transform: translateY(0);
            transition: all 0.1s ease;
        }

        .char.visible {
            opacity: 1;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        /* RGB风格歌词效果 */
        .char.rgb {
            opacity: 0.7;
        }

        .char.rgb.visible {
            opacity: 1;
            animation: rgbColorCycle 2s infinite;
        }

        @keyframes rgbColorCycle {
            0% { color: #ff0000; }
            33% { color: #00ff00; }
            66% { color: #0000ff; }
            100% { color: #ff0000; }
        }

        /* 背景动画 */
        .background-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .visualizer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 150px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 3px;
        }

        .bar {
            width: 8px;
            border-radius: 4px 4px 0 0;
            transition: height 0.15s ease-out;
        }

        /* 响应式设计 - 增强移动端适配 */
        @media (max-width: 768px) {
            .lyric-line {
                font-size: 1.8rem;
            }
            
            .player-controls {
                padding: 15px;
                max-width: 90%;
                bottom: 20px;
            }
            
            .upload-btn {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .song-title {
                font-size: 18px;
            }
            
            .song-artist {
                font-size: 14px;
            }
            
            h1 {
                font-size: 20px;
                margin-bottom: 15px;
            }
            
            .controls {
                gap: 20px;
            }
            
            .play-pause {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .control-btn {
                font-size: 18px;
            }
            
            /* 移动端优化悬浮窗 */
            .player-controls.collapsed {
                transform: translateX(-50%) translateY(calc(100% - 40px));
                padding: 10px 15px;
            }
            
            .toggle-controls {
                width: 70px;
                height: 25px;
                font-size: 12px;
                top: -35px;
            }
            
            .lyrics-container {
                height: calc(100% - 150px);
                padding: 15px;
            }
            
            .upload-area {
                gap: 8px;
            }
            
            .theme-selector {
                gap: 8px;
            }
            
            .theme-btn {
                width: 20px;
                height: 20px;
            }
            
            .share-actions {
                gap: 8px;
            }
            
            .share-btn {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            .music-details {
                padding: 10px;
                font-size: 12px;
            }
            
            .music-details h3 {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .lyric-line {
                font-size: 1.5rem;
            }
            
            .player-controls {
                max-width: 95%;
                padding: 12px;
                bottom: 15px;
            }
            
            .controls {
                gap: 15px;
            }
            
            .play-pause {
                width: 45px;
                height: 45px;
                font-size: 18px;
            }
            
            .control-btn {
                font-size: 16px;
            }
            
            h1 {
                font-size: 18px;
                margin-bottom: 12px;
            }
            
            .song-title {
                font-size: 16px;
            }
            
            .song-artist {
                font-size: 13px;
            }
            
            .file-info {
                font-size: 12px;
            }
            
            .time {
                font-size: 12px;
            }
            
            .upload-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
            
            .file-name {
                font-size: 10px;
            }
            
            .lyric-offset {
                font-size: 12px;
            }
            
            .offset-btn {
                width: 25px;
                height: 25px;
                font-size: 14px;
            }
            
            .offset-value {
                min-width: 35px;
            }
            
            .language-btn {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            .bg-control-btn {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            /* 移动端小屏幕进一步优化 */
            .player-controls.collapsed {
                transform: translateX(-50%) translateY(calc(100% - 35px));
                padding: 8px 12px;
            }
            
            .toggle-controls {
                width: 60px;
                height: 22px;
                font-size: 11px;
                top: -32px;
            }
            
            .lyrics-container {
                height: calc(100% - 120px);
                padding: 10px;
            }
            
            .visualizer {
                height: 100px;
            }
            
            .bar {
                width: 6px;
            }
        }

        @media (max-width: 360px) {
            .lyric-line {
                font-size: 1.3rem;
            }
            
            .player-controls {
                max-width: 98%;
                padding: 10px;
            }
            
            h1 {
                font-size: 16px;
            }
            
            .song-title {
                font-size: 15px;
            }
            
            .upload-area {
                gap: 6px;
            }
            
            .theme-selector {
                gap: 6px;
            }
            
            .theme-btn {
                width: 18px;
                height: 18px;
            }
        }

        /* 横屏模式优化 */
        @media (max-height: 500px) and (orientation: landscape) {
            .player-controls {
                max-width: 80%;
                bottom: 10px;
            }
            
            .lyrics-container {
                height: calc(100% - 120px);
            }
            
            .lyric-line {
                font-size: 1.5rem;
            }
            
            h1 {
                margin-bottom: 10px;
                font-size: 16px;
            }
            
            .song-info {
                margin-bottom: 10px;
            }
            
            .progress-area {
                margin-bottom: 10px;
            }
            
            .controls {
                margin-bottom: 10px;
            }
            
            .upload-area {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .upload-btn {
                flex: 1;
                min-width: 120px;
                max-width: 150px;
            }
        }

        /* 歌词偏移调节器 */
        .lyric-offset {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        .offset-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .offset-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .offset-value {
            min-width: 40px;
            text-align: center;
        }
        
        /* 语言切换按钮 */
        .language-toggle {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        
        .language-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.7);
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }
        
        .language-btn.active {
            color: white;
        }
        
        /* 主题选择器 */
        .theme-selector {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .theme-btn {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .theme-btn.active {
            border-color: white;
            transform: scale(1.2);
        }
        
        .theme-btn:hover {
            transform: scale(1.1);
        }
        
        /* 背景图片控制 */
        .background-control {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            gap: 10px;
        }
        
        .bg-control-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.7);
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }
        
        .bg-control-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        /* 音乐信息显示 */
        .music-details {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            font-size: 14px;
        }

        .music-details h3 {
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.9);
        }

        .music-details p {
            margin-bottom: 5px;
            color: rgba(255, 255, 255, 0.7);
        }

        .share-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .share-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .share-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body data-theme="default">
    <!-- 自定义背景图片 - 默认使用音乐封面 -->
    <img class="background-image <?php echo $cover_image_url ? '' : 'hidden'; ?>" id="background-image" 
         src="<?php echo $cover_image_url ? htmlspecialchars($cover_image_url) : ''; ?>" 
         alt="背景图片" onerror="this.classList.add('hidden')">
    
    <div class="container">
        <!-- 背景动画 -->
        <div class="background-animation">
            <div class="visualizer" id="visualizer"></div>
        </div>
        
        <!-- 全屏歌词显示区域 -->
        <div class="lyrics-container" id="lyrics-container">
            <div class="lyric-line" id="default-lyric">
                <?php if ($music): ?>
                    正在加载: <?php echo htmlspecialchars($music['title']); ?>
                <?php else: ?>
                    上传音乐和歌词文件开始播放
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 播放器控制区域 -->
        <div class="player-controls" id="player-controls">
            <button class="toggle-controls" id="toggle-controls">收起</button>
            
            <h1>--Hmis的小站--</h1>
            
            <div class="song-info">
                <div class="song-title">
                    <?php if ($music): ?>
                        <?php echo htmlspecialchars($music['title']); ?>
                    <?php else: ?>
                        选择一首歌曲
                    <?php endif; ?>
                </div>
                <div class="song-artist">
                    <?php if ($music): ?>
                        <?php echo htmlspecialchars($music['artist']); ?>
                    <?php else: ?>
                        上传音乐和歌词文件
                    <?php endif; ?>
                </div>
                <?php if ($music): ?>
                <div class="file-info">
                    文件类型: <?php echo $is_video ? '视频' : '音频'; ?>
                    <span class="file-status <?php echo $file_exists ? 'file-exists' : 'file-missing'; ?>">
                        <?php echo $file_exists ? '✓ 文件可访问' : '✗ 文件不存在'; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="progress-area">
                <div class="progress-bar" id="progress-bar">
                    <div class="progress" id="progress"></div>
                </div>
                <div class="time">
                    <span class="current" id="current-time">0:00</span>
                    <span class="duration" id="duration">0:00</span>
                </div>
            </div>
            
            <div class="controls">
                <button class="control-btn" id="prev">⏮</button>
                <button class="control-btn play-pause" id="play-pause">▶</button>
                <button class="control-btn" id="next">⏭</button>
            </div>
            
            <div class="upload-area">
  

                </label>
                <input type="file" id="music-upload" class="hidden" accept="audio/*">
                <div class="file-name" id="music-file-name"></div>
                


                </label>
                <input type="file" id="lyrics-upload" class="hidden" accept=".lrc,.txt,.json">
                <div class="file-name" id="lyrics-file-name"></div>
                


                </label>
                <input type="file" id="background-upload" class="hidden" accept="image/*">
                <div class="file-name" id="background-file-name"></div>
            </div>

            <?php if ($music): ?>
            <div class="music-details">
                <h3>音乐信息</h3>
                <p><strong>上传时间:</strong> <?php echo date('Y-m-d H:i', strtotime($music['created_at'])); ?></p>
                <p><strong>如果遇到无法播放，请刷新网页！</strong> </p>
                <div class="share-actions">
                    <button class="share-btn" onclick="copyShareLink()">复制分享链接</button>
                    <button class="share-btn" onclick="window.location.href='index.php'">发现更多音乐</button>
                    <button class="share-btn" onclick="testMediaFile()">测试媒体文件</button>
                    <button class="share-btn" onclick="toggleCoverBackground()">切换封面背景</button>
                    <button class="share-btn" onclick="goToSharePage()">返回旧版</button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="lyric-offset">
                <span>歌词时间偏移:</span>
                <div class="offset-control">
                    <button class="offset-btn" id="offset-decrease">-</button>
                    <span class="offset-value" id="offset-value">0.0s</span>
                    <button class="offset-btn" id="offset-increase">+</button>
                </div>
            </div>
            
            <div class="language-toggle">
                <button class="language-btn active" id="chinese-mode">中文模式</button>
                <button class="language-btn" id="english-mode">英文模式</button>
            </div>
            
            <div class="theme-selector">
                <div class="theme-btn active" data-theme="default" style="background: linear-gradient(135deg, #ff6b6b, #ffd166);"></div>
                <div class="theme-btn" data-theme="ocean" style="background: linear-gradient(135deg, #4facfe, #00f2fe);"></div>
                <div class="theme-btn" data-theme="forest" style="background: linear-gradient(135deg, #43e97b, #38f9d7);"></div>
                <div class="theme-btn" data-theme="sunset" style="background: linear-gradient(135deg, #fa709a, #fee140);"></div>
                <div class="theme-btn" data-theme="purple" style="background: linear-gradient(135deg, #667eea, #764ba2);"></div>
                <div class="theme-btn" data-theme="rgb" style="background: linear-gradient(135deg, #ff0000, #00ff00, #0000ff);"></div>
            </div>
            
            <div class="background-control">
                <button class="bg-control-btn" id="remove-bg">移除背景</button>
                <button class="bg-control-btn" id="adjust-bg-opacity">调整透明度</button>
            </div>
        </div>
    </div>

    <script>
        // DOM元素
        const audio = new Audio();
        const playPauseBtn = document.getElementById('play-pause');
        const prevBtn = document.getElementById('prev');
        const nextBtn = document.getElementById('next');
        const progressBar = document.getElementById('progress-bar');
        const progress = document.getElementById('progress');
        const currentTimeEl = document.getElementById('current-time');
        const durationEl = document.getElementById('duration');
        const songTitle = document.querySelector('.song-title');
        const songArtist = document.querySelector('.song-artist');
        const lyricsContainer = document.getElementById('lyrics-container');
        const defaultLyric = document.getElementById('default-lyric');
        const musicUpload = document.getElementById('music-upload');
        const lyricsUpload = document.getElementById('lyrics-upload');
        const backgroundUpload = document.getElementById('background-upload');
        const musicFileName = document.getElementById('music-file-name');
        const lyricsFileName = document.getElementById('lyrics-file-name');
        const backgroundFileName = document.getElementById('background-file-name');
        const visualizer = document.getElementById('visualizer');
        const offsetDecreaseBtn = document.getElementById('offset-decrease');
        const offsetIncreaseBtn = document.getElementById('offset-increase');
        const offsetValueEl = document.getElementById('offset-value');
        const chineseModeBtn = document.getElementById('chinese-mode');
        const englishModeBtn = document.getElementById('english-mode');
        const themeButtons = document.querySelectorAll('.theme-btn');
        const playerControls = document.getElementById('player-controls');
        const toggleControlsBtn = document.getElementById('toggle-controls');
        const backgroundImage = document.getElementById('background-image');
        const removeBgBtn = document.getElementById('remove-bg');
        const adjustBgOpacityBtn = document.getElementById('adjust-bg-opacity');

        // 播放状态
        let isPlaying = false;
        let currentLyricIndex = 0;
        let lyrics = [];
        let audioContext;
        let analyser;
        let dataArray;
        let bufferLength;
        let bars = [];
        let animationFrameId;
        let lyricOffset = 0.0; // 歌词偏移量（秒）
        let lastUpdateTime = 0;
        let bassHistory = [];
        let midHistory = [];
        let trebleHistory = [];
        let activeLyrics = []; // 跟踪当前活跃的歌词
        let lastSyncedTime = -1; // 上次同步歌词的时间
        let isChineseMode = true; // 默认中文模式，使用更快的变色速度
        let controlsCollapsed = false; // 控制面板是否收起
        let currentTheme = 'default'; // 当前主题
        let bgOpacity = 0.7; // 背景图片透明度
        let isCoverBackgroundEnabled = true; // 是否启用封面背景

        // PHP传递的数据
        const musicData = <?php echo $music ? json_encode([
            'title' => $music['title'],
            'artist' => $music['artist'],
            'file_path' => $music['file_path'],
            'file_url' => $file_url,
            'lyric_path' => $music['lyric_path'],
            'share_reason' => $music['share_reason'],
            'cover_image' => $cover_image_url,
            'file_exists' => $file_exists,
            'is_video' => $is_video,
            'mime_type' => $mime_type
        ]) : 'null'; ?>;
        
        const lyricsContent = <?php echo $lyrics_content ? json_encode($lyrics_content) : 'null'; ?>;

        // 主题配置
        const themes = {
            default: {
                background: 'linear-gradient(135deg, #1a1a2e, #16213e)',
                titleColor: '#ff6b6b',
                songTitleColor: '#ffd166',
                progressColor: 'linear-gradient(90deg, #ff6b6b, #ffd166)',
                playButton: 'linear-gradient(135deg, #ff6b6b, #ffd166)',
                charColor: '#a8dadc',
                charActiveColor: '#ffd166',
                visualizer: 'linear-gradient(to top, #ff6b6b, #ffd166)',
                buttonHover: '#ffd166'
            },
            ocean: {
                background: 'linear-gradient(135deg, #0f2027, #203a43, #2c5364)',
                titleColor: '#4facfe',
                songTitleColor: '#00f2fe',
                progressColor: 'linear-gradient(90deg, #4facfe, #00f2fe)',
                playButton: 'linear-gradient(135deg, #4facfe, #00f2fe)',
                charColor: '#a8dadc',
                charActiveColor: '#00f2fe',
                visualizer: 'linear-gradient(to top, #4facfe, #00f2fe)',
                buttonHover: '#00f2fe'
            },
            forest: {
                background: 'linear-gradient(135deg, #0c3b2e, #1b5e3e, #2e7d32)',
                titleColor: '#43e97b',
                songTitleColor: '#38f9d7',
                progressColor: 'linear-gradient(90deg, #43e97b, #38f9d7)',
                playButton: 'linear-gradient(135deg, #43e97b, #38f9d7)',
                charColor: '#a8dadc',
                charActiveColor: '#38f9d7',
                visualizer: 'linear-gradient(to top, #43e97b, #38f9d7)',
                buttonHover: '#38f9d7'
            },
            sunset: {
                background: 'linear-gradient(135deg, #4a235a, #6a1b9a, #8e24aa)',
                titleColor: '#fa709a',
                songTitleColor: '#fee140',
                progressColor: 'linear-gradient(90deg, #fa709a, #fee140)',
                playButton: 'linear-gradient(135deg, #fa709a, #fee140)',
                charColor: '#a8dadc',
                charActiveColor: '#fee140',
                visualizer: 'linear-gradient(to top, #fa709a, #fee140)',
                buttonHover: '#fee140'
            },
            purple: {
                background: 'linear-gradient(135deg, #232526, #414345)',
                titleColor: '#667eea',
                songTitleColor: '#764ba2',
                progressColor: 'linear-gradient(90deg, #667eea, #764ba2)',
                playButton: 'linear-gradient(135deg, #667eea, #764ba2)',
                charColor: '#a8dadc',
                charActiveColor: '#764ba2',
                visualizer: 'linear-gradient(to top, #667eea, #764ba2)',
                buttonHover: '#764ba2'
            },
            rgb: {
                background: 'linear-gradient(135deg, #1a1a2e, #16213e)',
                titleColor: '#ffffff',
                songTitleColor: '#ffffff',
                progressColor: 'linear-gradient(90deg, #ff0000, #00ff00, #0000ff)',
                playButton: 'linear-gradient(135deg, #ff0000, #00ff00, #0000ff)',
                charColor: '#ffffff',
                charActiveColor: '#ffffff',
                visualizer: 'linear-gradient(to top, #ff0000, #00ff00, #0000ff)',
                buttonHover: '#ffffff',
                isRGB: true
            }
        };

        // 示例音乐数据
        const defaultMusic = {
            title: "示例音乐",
            artist: "未知艺术家",
            src: "https://assets.mixkit.co/music/preview/mixkit-tech-house-vibes-130.mp3"
        };

        // 示例歌词数据
        const defaultLyrics = [
            { time: 2, text: "欢迎使用逐字变色歌词播放器" },
            { time: 6, text: "上传你自己的音乐和歌词" },
            { time: 10, text: "体验独特的逐字变色效果" },
            { time: 14, text: "每个字符都会独立变色" },
            { time: 18, text: "感受音乐与文字的完美结合" },
            { time: 22, text: "让每一句歌词都充满生命力" },
            { time: 26, text: "感谢使用！" }
        ];

        // 初始化
        function init() {
            // 如果有数据库音乐数据，使用数据库数据
            if (musicData) {
                audio.src = musicData.file_url;
                songTitle.textContent = musicData.title;
                songArtist.textContent = musicData.artist;
                
                // 设置封面背景
                if (musicData.cover_image) {
                    setCoverBackground(musicData.cover_image);
                }
                
                // 加载歌词
                if (lyricsContent) {
                    parseLRCSubtitles(lyricsContent);
                } else {
                    lyrics = defaultLyrics;
                }
                
                // 检查文件是否存在
                if (!musicData.file_exists) {
                    showFileError();
                }
            } else {
                // 使用默认示例数据
                audio.src = defaultMusic.src;
                songTitle.textContent = defaultMusic.title;
                songArtist.textContent = defaultMusic.artist;
                lyrics = defaultLyrics;
            }
            
            // 初始化音频分析器
            initAudioAnalyser();
            
            // 设置事件监听器
            playPauseBtn.addEventListener('click', togglePlay);
            prevBtn.addEventListener('click', playPrevious);
            nextBtn.addEventListener('click', playNext);
            progressBar.addEventListener('click', setProgress);
            audio.addEventListener('timeupdate', updateProgress);
            audio.addEventListener('loadedmetadata', updateDuration);
            audio.addEventListener('ended', audioEnded);
            
            // 添加播放事件监听器，用于移除默认歌词
            audio.addEventListener('play', () => {
                // 移除默认歌词提示
                if (defaultLyric && defaultLyric.parentNode) {
                    defaultLyric.style.transition = 'opacity 0.5s ease';
                    defaultLyric.style.opacity = '0';
                    setTimeout(() => {
                        if (defaultLyric.parentNode) {
                            defaultLyric.parentNode.removeChild(defaultLyric);
                        }
                    }, 500);
                }
            });
            
            musicUpload.addEventListener('change', handleMusicUpload);
            lyricsUpload.addEventListener('change', handleLyricsUpload);
            backgroundUpload.addEventListener('change', handleBackgroundUpload);
            
            offsetDecreaseBtn.addEventListener('click', () => adjustLyricOffset(-0.1));
            offsetIncreaseBtn.addEventListener('click', () => adjustLyricOffset(0.1));
            
            chineseModeBtn.addEventListener('click', () => setLanguageMode(true));
            englishModeBtn.addEventListener('click', () => setLanguageMode(false));
            
            // 主题切换
            themeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const theme = btn.getAttribute('data-theme');
                    setTheme(theme);
                });
            });
            
            // 控制器收起/展开
            toggleControlsBtn.addEventListener('click', toggleControls);
            
            // 背景控制
            removeBgBtn.addEventListener('click', removeBackground);
            adjustBgOpacityBtn.addEventListener('click', adjustBackgroundOpacity);
            
            // 初始化歌词显示
            updateLyricsDisplay();
            
            // 创建可视化条
            createVisualizerBars();
            
            // 应用默认主题
            setTheme('default');
            
            // 移动端优化：自动检测屏幕尺寸并调整
            handleMobileOptimization();
        }

        // 移动端优化
        function handleMobileOptimization() {
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isSmallScreen = window.innerWidth <= 768;
            
            if (isMobile || isSmallScreen) {
                // 移动端默认收起控制面板
                controlsCollapsed = true;
                playerControls.classList.add('collapsed');
                toggleControlsBtn.textContent = '展开';
                lyricsContainer.style.height = 'calc(100% - 50px)';
                
                // 移动端调整字体大小
                document.documentElement.style.setProperty('--base-font-size', '14px');
                
                console.log('移动端优化已应用');
            }
        }

        // 设置封面背景
        function setCoverBackground(coverUrl) {
            if (!coverUrl) return;
            
            backgroundImage.src = coverUrl;
            backgroundImage.classList.remove('hidden');
            document.body.classList.add('custom-bg');
            isCoverBackgroundEnabled = true;
        }

        // 切换封面背景
        function toggleCoverBackground() {
            if (backgroundImage.classList.contains('hidden')) {
                // 启用封面背景
                if (musicData && musicData.cover_image) {
                    setCoverBackground(musicData.cover_image);
                }
            } else {
                // 禁用封面背景
                removeBackground();
            }
        }

        // 显示文件错误
        function showFileError() {
            const errorMessage = document.createElement('div');
            errorMessage.style.cssText = `
                background: rgba(231, 76, 60, 0.2);
                border: 1px solid rgba(231, 76, 60, 0.5);
                border-radius: 10px;
                padding: 1rem;
                margin: 1rem 0;
                text-align: center;
                font-size: 14px;
            `;
            errorMessage.innerHTML = `
                <strong>无法播放媒体文件</strong><br>
                文件路径: ${musicData.file_path}<br>
                请检查文件是否存在或路径是否正确
            `;
            
            const progressArea = document.querySelector('.progress-area');
            progressArea.parentNode.insertBefore(errorMessage, progressArea);
            
            // 禁用播放按钮
            playPauseBtn.disabled = true;
            playPauseBtn.style.opacity = '0.5';
            playPauseBtn.style.cursor = 'not-allowed';
        }

        // 测试媒体文件
        function testMediaFile() {
            if (!musicData) {
                alert('没有可测试的媒体文件');
                return;
            }
            
            const testUrl = musicData.file_url;
            
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

        // 复制分享链接
        function copyShareLink() {
            const shareUrl = window.location.href;
            copyToClipboard(shareUrl, '分享链接');
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

        // 切换控制器收起/展开状态
        function toggleControls() {
            controlsCollapsed = !controlsCollapsed;
            
            if (controlsCollapsed) {
                playerControls.classList.add('collapsed');
                toggleControlsBtn.textContent = '展开';
                // 调整歌词容器高度
                lyricsContainer.style.height = 'calc(100% - 50px)';
            } else {
                playerControls.classList.remove('collapsed');
                toggleControlsBtn.textContent = '收起';
                // 恢复歌词容器高度
                lyricsContainer.style.height = 'calc(100% - 200px)';
            }
        }

        // 设置主题
        function setTheme(themeName) {
            const theme = themes[themeName];
            if (!theme) return;
            
            currentTheme = themeName;
            
            // 更新body背景
            document.body.style.background = theme.background;
            
            // 更新标题颜色
            document.querySelector('h1').style.color = theme.titleColor;
            
            // 更新歌曲标题颜色
            document.querySelector('.song-title').style.color = theme.songTitleColor;
            
            // 更新进度条颜色
            document.querySelector('.progress').style.background = theme.progressColor;
            
            // 更新播放按钮颜色
            document.querySelector('.play-pause').style.background = theme.playButton;
            
            // 更新控制按钮悬停颜色
            const controlBtns = document.querySelectorAll('.control-btn');
            controlBtns.forEach(btn => {
                btn.style.setProperty('--hover-color', theme.buttonHover);
            });
            
            // 更新上传按钮悬停边框颜色
            const uploadBtns = document.querySelectorAll('.upload-btn');
            uploadBtns.forEach(btn => {
                btn.style.setProperty('--hover-border', theme.buttonHover);
            });
            
            // 更新语言按钮激活状态颜色
            document.documentElement.style.setProperty('--active-bg', theme.playButton);
            
            // 更新可视化条颜色
            document.documentElement.style.setProperty('--visualizer-color', theme.visualizer);
            
            // 更新主题按钮激活状态
            themeButtons.forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.theme-btn[data-theme="${themeName}"]`).classList.add('active');
            
            // 更新body数据属性
            document.body.setAttribute('data-theme', themeName);
            
            // 更新所有歌词颜色
            updateAllLyricsColor();
        }

        // 更新所有歌词颜色
        function updateAllLyricsColor() {
            const theme = themes[currentTheme];
            
            // 更新所有字符元素的颜色
            const chars = document.querySelectorAll('.char');
            chars.forEach(char => {
                // 如果是RGB主题，添加RGB类
                if (theme.isRGB) {
                    char.classList.add('rgb');
                } else {
                    char.classList.remove('rgb');
                    char.style.color = theme.charColor;
                }
            });
            
            // 更新可见字符的颜色
            const visibleChars = document.querySelectorAll('.char.visible');
            visibleChars.forEach(char => {
                if (!theme.isRGB) {
                    char.style.color = theme.charActiveColor;
                }
            });
        }

        // 设置语言模式
        function setLanguageMode(chinese) {
            isChineseMode = chinese;
            
            if (chinese) {
                chineseModeBtn.classList.add('active');
                englishModeBtn.classList.remove('active');
                // 中文模式使用更快的变色速度
                document.documentElement.style.setProperty('--char-transition-duration', '0.1s');
            } else {
                englishModeBtn.classList.add('active');
                chineseModeBtn.classList.remove('active');
                // 英文模式使用较慢的变色速度
                document.documentElement.style.setProperty('--char-transition-duration', '0.2s');
            }
            
            // 更新所有字符元素的过渡时间
            const chars = document.querySelectorAll('.char');
            chars.forEach(char => {
                char.style.transition = `all ${isChineseMode ? '0.1s' : '0.2s'} ease`;
            });
        }

        // 处理背景图片上传
        function handleBackgroundUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const fileURL = URL.createObjectURL(file);
            backgroundImage.src = fileURL;
            backgroundImage.classList.remove('hidden');
            document.body.classList.add('custom-bg');
            backgroundFileName.textContent = file.name;
            isCoverBackgroundEnabled = false; // 用户上传了自定义背景，禁用封面背景
        }

        // 移除背景图片
        function removeBackground() {
            backgroundImage.classList.add('hidden');
            document.body.classList.remove('custom-bg');
            backgroundFileName.textContent = '';
            isCoverBackgroundEnabled = false;
        }

        // 调整背景图片透明度
        function adjustBackgroundOpacity() {
            bgOpacity = bgOpacity === 0.7 ? 0.3 : bgOpacity === 0.3 ? 1.0 : 0.7;
            backgroundImage.style.opacity = bgOpacity;
        }

        // 初始化音频分析器
        function initAudioAnalyser() {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                const source = audioContext.createMediaElementSource(audio);
                source.connect(analyser);
                analyser.connect(audioContext.destination);
                
                analyser.fftSize = 512;
                bufferLength = analyser.frequencyBinCount;
                dataArray = new Uint8Array(bufferLength);
            } catch (e) {
                console.error("音频分析器初始化失败:", e);
            }
        }

        // 创建可视化条
        function createVisualizerBars() {
            visualizer.innerHTML = '';
            bars = [];
            const barCount = 80;
            
            for (let i = 0; i < barCount; i++) {
                const bar = document.createElement('div');
                bar.className = 'bar';
                bar.style.height = '5px';
                visualizer.appendChild(bar);
                bars.push(bar);
            }
        }

        // 更新可视化效果
        function updateVisualizer() {
            if (!analyser || !isPlaying) {
                cancelAnimationFrame(animationFrameId);
                return;
            }
            
            analyser.getByteFrequencyData(dataArray);
            
            // 计算低音、中音和高音的平均值
            let bass = 0, mid = 0, treble = 0;
            const bassCount = Math.floor(bufferLength * 0.15);
            const midCount = Math.floor(bufferLength * 0.4);
            const trebleCount = Math.floor(bufferLength * 0.45);
            
            for (let i = 0; i < bassCount; i++) {
                bass += dataArray[i];
            }
            bass /= bassCount;
            
            for (let i = bassCount; i < bassCount + midCount; i++) {
                mid += dataArray[i];
            }
            mid /= midCount;
            
            for (let i = bassCount + midCount; i < bufferLength; i++) {
                treble += dataArray[i];
            }
            treble /= trebleCount;
            
            // 保存历史数据用于平滑处理
            bassHistory.push(bass);
            midHistory.push(mid);
            trebleHistory.push(treble);
            
            if (bassHistory.length > 5) bassHistory.shift();
            if (midHistory.length > 5) midHistory.shift();
            if (trebleHistory.length > 5) trebleHistory.shift();
            
            // 计算平滑后的值
            const smoothBass = bassHistory.reduce((a, b) => a + b, 0) / bassHistory.length;
            const smoothMid = midHistory.reduce((a, b) => a + b, 0) / midHistory.length;
            const smoothTreble = trebleHistory.reduce((a, b) => a + b, 0) / trebleHistory.length;
            
            // 更新可视化条
            for (let i = 0; i < bars.length; i++) {
                const barGroup = Math.floor(i / (bars.length / 3));
                let value;
                
                if (barGroup === 0) value = smoothBass;
                else if (barGroup === 1) value = smoothMid;
                else value = smoothTreble;
                
                const height = (value / 255) * 120 + 10;
                bars[i].style.height = `${height}px`;
                
                // 根据当前主题设置可视化条颜色
                const theme = themes[currentTheme];
                
                if (barGroup === 0) bars[i].style.background = theme.visualizer;
                else if (barGroup === 1) bars[i].style.background = theme.visualizer;
                else bars[i].style.background = theme.visualizer;
            }
            
            // 更新歌词抖动
            updateLyricShake(smoothBass, smoothMid, smoothTreble);
            
            // 检查歌词同步
            updateLyricsSync();
            
            animationFrameId = requestAnimationFrame(updateVisualizer);
        }

        // 更新歌词抖动效果
        function updateLyricShake(bass, mid, treble) {
            const currentTime = Date.now();
            
            // 限制更新频率，提高性能
            if (currentTime - lastUpdateTime < 16) return; // ~60fps
            lastUpdateTime = currentTime;
            
            activeLyrics.forEach(lyric => {
                // 使用不同频率的数据驱动不同方面的抖动
                const bassIntensity = bass / 255;
                const midIntensity = mid / 255;
                const trebleIntensity = treble / 255;
                
                // 使用正弦波创建更自然的周期性运动
                const time = currentTime / 1000;
                
                // 水平移动 - 使用低音数据驱动，幅度更大
                const moveX = Math.sin(time * 2 + lyric.dataset.index * 0.5) * 25 * bassIntensity;
                
                // 垂直移动 - 使用中音数据驱动，幅度适中
                const moveY = Math.cos(time * 1.5 + lyric.dataset.index * 0.3) * 15 * midIntensity;
                
                // 旋转 - 使用高音数据驱动，幅度较小但更频繁
                const rotate = Math.sin(time * 3 + lyric.dataset.index * 0.2) * 8 * trebleIntensity;
                
                // 缩放 - 使用整体强度
                const overallIntensity = (bassIntensity + midIntensity + trebleIntensity) / 3;
                const scale = 1 + Math.sin(time * 2.5) * 0.15 * overallIntensity;
                
                // 应用变换
                lyric.style.transform = `translate(${moveX}px, ${moveY}px) rotate(${rotate}deg) scale(${scale})`;
                
                // 添加文字阴影效果增强立体感
                const shadowX = moveX / 5;
                const shadowY = moveY / 5;
                const blur = 10 + 5 * overallIntensity;
                lyric.style.textShadow = `
                    ${shadowX}px ${shadowY}px ${blur}px rgba(0, 0, 0, 0.5),
                    ${-shadowX}px ${-shadowY}px ${blur}px rgba(255, 255, 255, 0.2)
                `;
            });
        }

        // 更新歌词同步
        function updateLyricsSync() {
            const currentTime = audio.currentTime + lyricOffset;
            
            // 避免频繁更新
            if (Math.abs(currentTime - lastSyncedTime) < 0.05) return;
            lastSyncedTime = currentTime;
            
            // 找到当前时间对应的歌词
            let currentIndex = -1;
            for (let i = 0; i < lyrics.length; i++) {
                if (currentTime >= lyrics[i].time) {
                    currentIndex = i;
                } else {
                    break;
                }
            }
            
            // 如果找到了新的当前歌词
            if (currentIndex !== -1 && currentIndex !== currentLyricIndex) {
                // 移除之前的歌词
                removePreviousLyrics();
                
                // 显示新歌词
                currentLyricIndex = currentIndex;
                showLyric(currentLyricIndex);
            }
            
            // 更新逐字变色效果
            updateWordByWord(currentTime);
            
            // 检查是否需要移除过时的歌词
            checkAndRemoveOutdatedLyrics(currentTime);
        }

        // 更新逐字变色效果 - 根据语言模式调整速度
        function updateWordByWord(currentTime) {
            const activeLyric = document.querySelector('.lyric-line.active');
            if (!activeLyric) return;
            
            const lyricData = lyrics[currentLyricIndex];
            if (!lyricData) return;
            
            const chars = activeLyric.querySelectorAll('.char');
            if (chars.length === 0) return;
            
            // 计算每个字符应该显示的时间点
            const totalChars = chars.length;
            const nextLyricTime = currentLyricIndex < lyrics.length - 1 ? 
                lyrics[currentLyricIndex + 1].time : currentTime + 5;
            const lyricDuration = nextLyricTime - lyricData.time;
            
            // 根据语言模式调整字符显示速度
            const speedFactor = isChineseMode ? 0.5 : 0.8; // 中文模式更快
            const charDuration = (lyricDuration / totalChars) * speedFactor;
            
            // 计算当前时间在歌词中的进度
            const lyricProgress = currentTime - lyricData.time;
            const visibleChars = Math.min(totalChars, Math.floor(lyricProgress / charDuration));
            
            // 更新字符可见性
            chars.forEach((char, index) => {
                if (index < visibleChars) {
                    char.classList.add('visible');
                    // 如果不是RGB主题，应用主题激活颜色
                    if (!themes[currentTheme].isRGB) {
                        char.style.color = themes[currentTheme].charActiveColor;
                    }
                } else {
                    char.classList.remove('visible');
                    // 如果不是RGB主题，应用主题普通颜色
                    if (!themes[currentTheme].isRGB) {
                        char.style.color = themes[currentTheme].charColor;
                    }
                }
            });
        }

        // 检查并移除过时的歌词
        function checkAndRemoveOutdatedLyrics(currentTime) {
            // 获取所有歌词元素
            const allLyrics = document.querySelectorAll('.lyric-line');
            
            allLyrics.forEach(lyric => {
                const lyricIndex = parseInt(lyric.dataset.index);
                
                // 如果是前一句歌词，并且当前时间已经超过下一句歌词的时间，则移除
                if (lyricIndex < currentLyricIndex - 1) {
                    // 添加淡出动画
                    lyric.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    lyric.style.opacity = '0';
                    lyric.style.transform = 'translateY(-50px) scale(0.8)';
                    
                    // 延迟移除元素
                    setTimeout(() => {
                        if (lyric.parentNode) {
                            lyric.parentNode.removeChild(lyric);
                        }
                    }, 500);
                }
            });
        }

        // 移除之前的歌词
        function removePreviousLyrics() {
            // 将所有当前活跃的歌词标记为previous
            const activeLyrics = document.querySelectorAll('.lyric-line.active');
            activeLyrics.forEach(lyric => {
                lyric.classList.remove('active');
                lyric.classList.add('previous');
                
                // 添加淡出动画
                lyric.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                lyric.style.opacity = '0.4';
                lyric.style.transform = 'translateY(-30px) scale(0.95)';
            });
        }

        // 调整歌词偏移
        function adjustLyricOffset(amount) {
            lyricOffset += amount;
            lyricOffset = Math.round(lyricOffset * 10) / 10; // 保留一位小数
            offsetValueEl.textContent = `${lyricOffset > 0 ? '+' : ''}${lyricOffset.toFixed(1)}s`;
            
            // 重新计算当前应该显示的歌词
            const currentTime = audio.currentTime + lyricOffset;
            currentLyricIndex = -1;
            
            // 找到当前时间对应的歌词索引
            for (let i = 0; i < lyrics.length; i++) {
                if (currentTime >= lyrics[i].time) {
                    currentLyricIndex = i;
                } else {
                    break;
                }
            }
            
            // 重新显示歌词
            resetLyrics();
            if (currentLyricIndex >= 0) {
                showLyric(currentLyricIndex);
            }
        }

        // 切换播放/暂停
        function togglePlay() {
            if (isPlaying) {
                pauseAudio();
            } else {
                playAudio();
            }
        }

        // 播放音频
        function playAudio() {
            // 确保音频上下文在用户交互后恢复
            if (audioContext && audioContext.state === 'suspended') {
                audioContext.resume();
            }
            
            isPlaying = true;
            playPauseBtn.innerHTML = '⏸';
            audio.play().then(() => {
                updateVisualizer();
            }).catch(error => {
                console.error("播放失败:", error);
                isPlaying = false;
                playPauseBtn.innerHTML = '▶';
            });
        }

        // 暂停音频
        function pauseAudio() {
            isPlaying = false;
            playPauseBtn.innerHTML = '▶';
            audio.pause();
            cancelAnimationFrame(animationFrameId);
            
            // 重置可视化条
            bars.forEach(bar => {
                bar.style.height = '5px';
            });
        }

        // 播放上一首
        function playPrevious() {
            audio.currentTime = 0;
            if (isPlaying) {
                audio.play();
            }
            resetLyrics();
        }

        // 播放下一首
        function playNext() {
            audio.currentTime = 0;
            if (isPlaying) {
                audio.play();
            }
            resetLyrics();
        }

        // 更新进度条
        function updateProgress() {
            const { currentTime, duration } = audio;
            const progressPercent = (currentTime / duration) * 100;
            progress.style.width = `${progressPercent}%`;
            
            // 更新当前时间显示
            currentTimeEl.textContent = formatTime(currentTime);
        }

        // 设置进度
        function setProgress(e) {
            const width = this.clientWidth;
            const clickX = e.offsetX;
            const duration = audio.duration;
            
            audio.currentTime = (clickX / width) * duration;
        }

        // 更新总时长
        function updateDuration() {
            durationEl.textContent = formatTime(audio.duration);
        }

        // 格式化时间
        function formatTime(seconds) {
            if (isNaN(seconds)) return "0:00";
            
            const minutes = Math.floor(seconds / 60);
            seconds = Math.floor(seconds % 60);
            return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        }

        // 音频结束
        function audioEnded() {
            isPlaying = false;
            playPauseBtn.innerHTML = '▶';
            progress.style.width = '0%';
            currentTimeEl.textContent = '0:00';
            cancelAnimationFrame(animationFrameId);
            resetLyrics();
        }

        // 处理音乐上传
        function handleMusicUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const fileURL = URL.createObjectURL(file);
            audio.src = fileURL;
            
            // 更新显示信息
            const fileName = file.name.replace(/\.[^/.]+$/, ""); // 移除扩展名
            songTitle.textContent = fileName;
            songArtist.textContent = "上传用户";
            musicFileName.textContent = file.name;
            
            // 如果正在播放，重新开始播放
            if (isPlaying) {
                audio.play();
            }
            
            resetLyrics();
        }

        // 处理歌词上传
        function handleLyricsUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    // 尝试解析为JSON
                    lyrics = JSON.parse(e.target.result);
                    lyricsFileName.textContent = file.name;
                    resetLyrics();
                } catch (error) {
                    // 如果不是JSON，尝试解析为LRC格式
                    parseLRCSubtitles(e.target.result);
                    lyricsFileName.textContent = file.name;
                    resetLyrics();
                }
            };
            reader.readAsText(file);
        }

        // 解析LRC歌词格式
        function parseLRCSubtitles(text) {
            const lines = text.split('\n');
            lyrics = [];
            
            lines.forEach(line => {
                // 匹配LRC格式的时间标签和歌词
                const match = line.match(/\[(\d+):(\d+)\.(\d+)\](.*)/);
                if (match) {
                    const minutes = parseInt(match[1]);
                    const seconds = parseInt(match[2]);
                    const milliseconds = parseInt(match[3]);
                    const text = match[4].trim();
                    
                    if (text) {
                        const time = minutes * 60 + seconds + milliseconds / 100;
                        lyrics.push({ time, text });
                    }
                }
            });
            
            // 按时间排序
            lyrics.sort((a, b) => a.time - b.time);
        }

        // 显示歌词 - 修复颜色问题
        function showLyric(index) {
            // 创建新歌词元素
            const lyricEl = document.createElement('div');
            lyricEl.className = 'lyric-line';
            lyricEl.dataset.index = index;
            
            // 添加逐字效果
            const text = lyrics[index].text;
            
            // 使用正则表达式将文本拆分为字符，保留空格
            const chars = text.split('');
            
            // 获取当前主题的颜色
            const theme = themes[currentTheme];
            
            chars.forEach((char, i) => {
                // 如果是空格，保留空格
                if (char === ' ') {
                    const spaceSpan = document.createElement('span');
                    spaceSpan.className = 'char space';
                    spaceSpan.innerHTML = '&nbsp;';
                    // 根据语言模式调整延迟
                    spaceSpan.style.transitionDelay = `${i * (isChineseMode ? 0.01 : 0.02)}s`;
                    // 如果是RGB主题，添加RGB类
                    if (theme.isRGB) {
                        spaceSpan.classList.add('rgb');
                    } else {
                        spaceSpan.style.color = theme.charColor;
                    }
                    lyricEl.appendChild(spaceSpan);
                } else {
                    const charSpan = document.createElement('span');
                    charSpan.className = 'char';
                    charSpan.textContent = char;
                    // 根据语言模式调整延迟和过渡时间
                    charSpan.style.transitionDelay = `${i * (isChineseMode ? 0.01 : 0.02)}s`;
                    charSpan.style.transition = `all ${isChineseMode ? '0.1s' : '0.2s'} ease`;
                    // 如果是RGB主题，添加RGB类
                    if (theme.isRGB) {
                        charSpan.classList.add('rgb');
                    } else {
                        charSpan.style.color = theme.charColor;
                    }
                    lyricEl.appendChild(charSpan);
                }
            });
            
            lyricsContainer.appendChild(lyricEl);
            
            // 添加到活跃歌词列表
            activeLyrics.push(lyricEl);
            
            // 触发动画
            setTimeout(() => {
                lyricEl.classList.add('active');
            }, 10);
        }

        // 重置歌词
        function resetLyrics() {
            currentLyricIndex = 0;
            activeLyrics = [];
            const lyricElements = document.querySelectorAll('.lyric-line');
            lyricElements.forEach(el => {
                el.remove();
            });
        }

        // 更新歌词显示
        function updateLyricsDisplay() {
            // 清除现有歌词
            resetLyrics();
            
            // 如果有歌词数据，显示第一句
            if (lyrics.length > 0) {
                const lyricEl = document.createElement('div');
                lyricEl.className = 'lyric-line active';
                lyricEl.textContent = "";
                lyricsContainer.appendChild(lyricEl);
                activeLyrics.push(lyricEl);
            }
        }
function goToSharePage() {
  // 获取当前 URL 中的 code 参数
  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get('code');

  // 构造目标 URL：share.php?code=...
  let url = 'share.php';
  if (code !== null) {
    url += `?code=${encodeURIComponent(code)}`;
  }

  // 跳转
  window.location.href = url;
}
        // 初始化应用
        init();
    </script>
</body>
</html>