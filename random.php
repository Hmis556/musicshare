<?php
/**
 * 随机音乐页面
 * 随机跳转到一首音乐的分享页面
 */
require_once 'config.php';

$conn = getDBConnection();

// 获取随机音乐
$sql = "SELECT share_code FROM music WHERE is_active = TRUE ORDER BY RAND() LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $music = $result->fetch_assoc();
    $conn->close();
    // 跳转到随机音乐的分享页面
    header('Location: share.php?code=' . $music['share_code']);
    exit;
} else {
    $conn->close();
    // 如果没有音乐，跳转到首页
    header('Location: index.php');
    exit;
}
?>