<?php
/**
 * 应用启动文件
 * 负责：加载配置、初始化数据库连接、注册辅助函数、加载 AI 配置
 */

// 加载配置
require_once __DIR__ . '/../config/app.php';

// ---------- PDO 连接 ---------- //
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '数据库连接失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- 从数据库加载 AI 配置 ---------- //
try {
    $stmt = $pdo->query("
        SELECT api_url, api_key, model_name, year_target, fixed_income, city
        FROM family
        WHERE id = 1
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        global $ai_config;
        if (!empty($row['api_url'])) $ai_config['api_url'] = $row['api_url'];
        if (!empty($row['api_key'])) $ai_config['api_key'] = $row['api_key'];
        if (!empty($row['model_name'])) $ai_config['model_name'] = $row['model_name'];
        if (!empty($row['year_target'])) $ai_config['year_target'] = floatval($row['year_target']);
        if (!empty($row['fixed_income'])) $ai_config['fixed_income'] = $row['fixed_income'];
        if (!empty($row['city'])) $ai_config['city'] = $row['city'];
    }
} catch (Exception $e) {
    // 数据库未就绪时使用默认值
}

// ---------- 自动迁移：确保关键列存在 ---------- //
addColumnIfNotExists($pdo, 'family', 'api_url', "VARCHAR(255) DEFAULT ''");
addColumnIfNotExists($pdo, 'family', 'api_key', "VARCHAR(255) DEFAULT ''");
addColumnIfNotExists($pdo, 'family', 'model_name', "VARCHAR(100) DEFAULT 'gpt-3.5-turbo'");
addColumnIfNotExists($pdo, 'family', 'year_target', "DOUBLE DEFAULT 1000000");
addColumnIfNotExists($pdo, 'family', 'fixed_income', "TEXT");
addColumnIfNotExists($pdo, 'family', 'city', "VARCHAR(50) DEFAULT '北京'");

// 自动迁移：确保对话记录表存在
$pdo->exec("CREATE TABLE IF NOT EXISTS `dialogue_records` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `family_id` INT NOT NULL DEFAULT 1,
    `role` VARCHAR(20) NOT NULL DEFAULT 'ai',
    `content` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ---------- 辅助函数 ---------- //
function json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $dbName = DB_NAME;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.columns 
        WHERE table_schema = ? AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$dbName, $table, $column]);
    return $stmt->fetchColumn() > 0;
}

function addColumnIfNotExists(PDO $pdo, string $table, string $column, string $definition): void {
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
