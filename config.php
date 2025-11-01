<?php
// 数据库配置
define('DB_HOST', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');

// 上传路径配置
define('ROOT_PATH', dirname(__FILE__) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'static/uploads/');
define('MUSIC_UPLOAD_PATH', UPLOAD_PATH . 'music/');
define('IMAGE_UPLOAD_PATH', UPLOAD_PATH . 'images/');
define('LYRIC_UPLOAD_PATH', UPLOAD_PATH . 'lyrics/');

// 网站URL
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // 处理端口信息
    $port = '';
    if (isset($_SERVER['SERVER_PORT'])) {
        $server_port = $_SERVER['SERVER_PORT'];
        // 如果不是标准端口（HTTP 80，HTTPS 443），则添加端口号
        if (($protocol === 'https://' && $server_port != 443) || 
            ($protocol === 'http://' && $server_port != 80)) {
            $port = ':' . $server_port;
        }
    }
    
    // 获取当前脚本的完整路径，然后移除admin目录
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    
    // 如果路径中包含admin，则移除它
    if (strpos($script_path, '/admin') !== false) {
        $script_path = str_replace('/admin', '', $script_path);
    }
    
    // 确保路径以斜杠结尾
    if (substr($script_path, -1) !== '/') {
        $script_path .= '/';
    }
    
    return $protocol . $host . $port . $script_path;
}
// 确保上传目录存在
function ensure_upload_directories() {
    $dirs = [UPLOAD_PATH, MUSIC_UPLOAD_PATH, IMAGE_UPLOAD_PATH, LYRIC_UPLOAD_PATH];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        // 确保目录可写
        if (!is_writable($dir)) {
            chmod($dir, 0755);
        }
    }
}

// 创建上传目录
ensure_upload_directories();

// 获取客户端IP
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// 创建数据库连接
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// 安全过滤函数
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// 生成随机分享码
function generate_share_code() {
    return substr(md5(uniqid() . time()), 0, 10);
}

// 生成短链代码
function generate_short_code() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// 获取上传错误信息
function get_upload_error($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
        UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
        UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
    ];
    return $errors[$error_code] ?? '未知上传错误';
}

// 创建短链 - 修复版本
function create_short_link($original_url, $music_id = null, $sharer_name = '神秘分享者', $sharer_message = '') {
    $conn = getDBConnection();
    
    // 检查是否已存在短链
    $sql = "SELECT short_code FROM short_links WHERE original_url = ? AND is_active = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $original_url);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        return $row['short_code'];
    }
    
    // 生成新的短链
    $short_code = generate_short_code();
    
    // 确保短链唯一
    $attempts = 0;
    while ($attempts < 5) {
        $sql = "SELECT id FROM short_links WHERE short_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $short_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            break;
        }
        $short_code = generate_short_code();
        $attempts++;
    }
    
    // 插入短链记录
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
    $sql = "INSERT INTO short_links (short_code, original_url, music_id, sharer_name, sharer_message, expires_at) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisss", $short_code, $original_url, $music_id, $sharer_name, $sharer_message, $expires_at);
    
    if ($stmt->execute()) {
        // 如果关联了音乐，更新音乐的短链字段
        if ($music_id) {
            $sql = "UPDATE music SET short_code = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $short_code, $music_id);
            $stmt->execute();
        }
        $conn->close();
        return $short_code;
    } else {
        $conn->close();
        return false;
    }
}

// 获取短链信息
function get_short_link($short_code) {
    $conn = getDBConnection();
    
    $sql = "SELECT * FROM short_links WHERE short_code = ? AND is_active = TRUE AND (expires_at IS NULL OR expires_at > NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $short_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $link = $result->fetch_assoc();
        
        // 更新点击次数
        $sql = "UPDATE short_links SET click_count = click_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $link['id']);
        $stmt->execute();
        
        $conn->close();
        return $link;
    }
    
    $conn->close();
    return false;
}

// 检查表是否存在
function check_table_exists($table_name) {
    $conn = getDBConnection();
    $result = $conn->query("SHOW TABLES LIKE '$table_name'");
    $exists = $result->num_rows > 0;
    $conn->close();
    return $exists;
}
?>