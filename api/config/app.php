<?php
/**
 * 应用配置
 * 注意：敏感配置（API Key 等）请通过前端设置面板保存到数据库
 */

// ---------- 数据库 ---------- //
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'pixellife');
define('DB_USER', 'pixellife');
define('DB_PASS', 'liu123456');
define('DB_CHARSET', 'utf8mb4');

// ---------- AI / 大模型默认配置（空值，需从数据库加载） ---------- //
$ai_config = [
    'api_url'     => '',
    'api_key'     => '',
    'model_name'  => 'gpt-3.5-turbo',
    'year_target' => 1000000,
    'fixed_income' => '',
    'city' => '北京',
];

/**
 * 获取 AI 配置
 * 实际值由 bootstrap/app.php 从数据库加载并覆盖默认值
 */
function get_ai_config(): array {
    global $ai_config;
    return $ai_config;
}
