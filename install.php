<?php
/**
 * 音乐分享网站安装脚本
 * 创建数据库和必要的数据表 - 添加短链表
 */

// 数据库配置
$db_host = '';
$db_user = '';
$db_pass = '';
$db_name = '';

// 创建数据库连接
$conn = new mysqli($db_host, $db_user, $db_pass);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 创建数据库
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($sql) === TRUE) {
    echo "数据库创建成功<br>";
} else {
    echo "数据库创建失败: " . $conn->error . "<br>";
}

// 选择数据库
$conn->select_db($db_name);

// 创建音乐表
$sql = "CREATE TABLE IF NOT EXISTS music (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    cover_image VARCHAR(500),
    share_reason TEXT,
    share_code VARCHAR(20) UNIQUE NOT NULL,
    short_code VARCHAR(10) UNIQUE,
    uploader_ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
)";

if ($conn->query($sql) === TRUE) {
    echo "音乐表创建成功<br>";
} else {
    echo "音乐表创建失败: " . $conn->error . "<br>";
}

// 创建短链表 - 添加分享者信息
$sql = "CREATE TABLE IF NOT EXISTS short_links (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    short_code VARCHAR(10) UNIQUE NOT NULL,
    original_url VARCHAR(500) NOT NULL,
    music_id INT(11),
    sharer_name VARCHAR(100) DEFAULT '神秘分享者',
    sharer_message TEXT,
    click_count INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (music_id) REFERENCES music(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "短链表创建成功<br>";
} else {
    echo "短链表创建失败: " . $conn->error . '<br>';
}

// 创建评论表
$sql = "CREATE TABLE IF NOT EXISTS comments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    music_id INT(11) NOT NULL,
    user_ip VARCHAR(45) NOT NULL,
    username VARCHAR(100) DEFAULT '匿名用户',
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (music_id) REFERENCES music(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "评论表创建成功<br>";
} else {
    echo "评论表创建失败: " . $conn->error . "<br>";
}

// 创建管理员表
$sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "管理员表创建成功<br>";
} else {
    echo "管理员表创建失败: " . $conn->error . '<br>';
}

// 创建设置表
$sql = "CREATE TABLE IF NOT EXISTS settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "设置表创建成功<br>";
} else {
    echo "设置表创建失败: " . $conn->error . '<br>';
}

// 插入默认管理员账户 (用户名: admin, 密码: admin123)
$hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO admin_users (username, password) VALUES ('admin', '$hashed_password')";

if ($conn->query($sql) === TRUE) {
    echo "默认管理员账户创建成功 (用户名: admin, 密码: admin123)<br>";
} else {
    echo "默认管理员账户创建失败: " . $conn->error . '<br>';
}

// 插入默认设置
$default_settings = [
    ['daily_comment_limit', '10'],
    ['site_title', '音乐分享网站'],
    ['allow_anonymous', '1'],
    ['short_url_enabled', '1']
];

foreach ($default_settings as $setting) {
    $sql = "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('{$setting[0]}', '{$setting[1]}')";
    $conn->query($sql);
}

echo "默认设置插入成功<br>";

$conn->close();

// 创建配置文件
$config_content = "<?php
// 数据库配置
define('DB_HOST', '$db_host');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');
define('DB_NAME', '$db_name');

// 网站配置
define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . str_replace('install.php', '', \$_SERVER['SCRIPT_NAME']));
define('ROOT_PATH', dirname(__FILE__) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'static/uploads/');
define('MUSIC_UPLOAD_PATH', UPLOAD_PATH . 'music/');
define('IMAGE_UPLOAD_PATH', UPLOAD_PATH . 'images/');

// 确保上传目录存在
function ensure_upload_directories() {
    \$dirs = [UPLOAD_PATH, MUSIC_UPLOAD_PATH, IMAGE_UPLOAD_PATH];
    foreach (\$dirs as \$dir) {
        if (!file_exists(\$dir)) {
            mkdir(\$dir, 0755, true);
        }
        // 确保目录可写
        if (!is_writable(\$dir)) {
            chmod(\$dir, 0755);
        }
    }
}

// 创建上传目录
ensure_upload_directories();

// 获取客户端IP
function get_client_ip() {
    \$ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach (\$ip_keys as \$key) {
        if (array_key_exists(\$key, \$_SERVER) === true) {
            foreach (explode(',', \$_SERVER[\$key]) as \$ip) {
                \$ip = trim(\$ip);
                if (filter_var(\$ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return \$ip;
                }
            }
        }
    }
    return \$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// 创建数据库连接
function getDBConnection() {
    \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (\$conn->connect_error) {
        die(\"数据库连接失败: \" . \$conn->connect_error);
    }
    
    \$conn->set_charset(\"utf8mb4\");
    return \$conn;
}

// 安全过滤函数
function sanitize_input(\$data) {
    return htmlspecialchars(strip_tags(trim(\$data)));
}

// 生成随机分享码
function generate_share_code() {
    return substr(md5(uniqid() . time()), 0, 10);
}

// 生成短链代码
function generate_short_code() {
    \$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    \$code = '';
    for (\$i = 0; \$i < 6; \$i++) {
        \$code .= \$characters[rand(0, strlen(\$characters) - 1)];
    }
    return \$code;
}

// 获取上传错误信息
function get_upload_error(\$error_code) {
    \$errors = [
        UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
        UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
        UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
    ];
    return \$errors[\$error_code] ?? '未知上传错误';
}

// 创建短链
function create_short_link(\$original_url, \$music_id = null) {
    \$conn = getDBConnection();
    
    // 检查是否已存在短链
    \$sql = \"SELECT short_code FROM short_links WHERE original_url = ? AND is_active = TRUE\";
    \$stmt = \$conn->prepare(\$sql);
    \$stmt->bind_param(\"s\", \$original_url);
    \$stmt->execute();
    \$result = \$stmt->get_result();
    
    if (\$result->num_rows > 0) {
        \$row = \$result->fetch_assoc();
        \$conn->close();
        return \$row['short_code'];
    }
    
    // 生成新的短链
    \$short_code = generate_short_code();
    
    // 确保短链唯一
    \$attempts = 0;
    while (\$attempts < 5) {
        \$sql = \"SELECT id FROM short_links WHERE short_code = ?\";
        \$stmt = \$conn->prepare(\$sql);
        \$stmt->bind_param(\"s\", \$short_code);
        \$stmt->execute();
        \$result = \$stmt->get_result();
        
        if (\$result->num_rows === 0) {
            break;
        }
        \$short_code = generate_short_code();
        \$attempts++;
    }
    
    // 插入短链记录
    \$expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
    \$sql = \"INSERT INTO short_links (short_code, original_url, music_id, expires_at) VALUES (?, ?, ?, ?)\";
    \$stmt = \$conn->prepare(\$sql);
    \$stmt->bind_param(\"ssis\", \$short_code, \$original_url, \$music_id, \$expires_at);
    
    if (\$stmt->execute()) {
        // 如果关联了音乐，更新音乐的短链字段
        if (\$music_id) {
            \$sql = \"UPDATE music SET short_code = ? WHERE id = ?\";
            \$stmt = \$conn->prepare(\$sql);
            \$stmt->bind_param(\"si\", \$short_code, \$music_id);
            \$stmt->execute();
        }
        \$conn->close();
        return \$short_code;
    } else {
        \$conn->close();
        return false;
    }
}

// 获取短链信息
function get_short_link(\$short_code) {
    \$conn = getDBConnection();
    
    \$sql = \"SELECT * FROM short_links WHERE short_code = ? AND is_active = TRUE AND (expires_at IS NULL OR expires_at > NOW())\";
    \$stmt = \$conn->prepare(\$sql);
    \$stmt->bind_param(\"s\", \$short_code);
    \$stmt->execute();
    \$result = \$stmt->get_result();
    
    if (\$result->num_rows > 0) {
        \$link = \$result->fetch_assoc();
        
        // 更新点击次数
        \$sql = \"UPDATE short_links SET click_count = click_count + 1 WHERE id = ?\";
        \$stmt = \$conn->prepare(\$sql);
        \$stmt->bind_param(\"i\", \$link['id']);
        \$stmt->execute();
        
        \$conn->close();
        return \$link;
    }
    
    \$conn->close();
    return false;
}
?>";

file_put_contents('config.php', $config_content);

echo "配置文件创建成功<br>";
echo "<h2>安装完成！</h2>";
echo "<p>请立即删除 install.php 文件以确保安全</p>";
echo "<p><a href='admin/login.php'>前往管理员登录</a></p>";
?>