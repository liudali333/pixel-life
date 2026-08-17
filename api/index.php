<?php
/**
 * 像素人生 API 入口
 * 统一路由，分发到各模块
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ---------- 启动：加载配置、连接数据库、注册辅助函数 ---------- //
require_once __DIR__ . '/bootstrap/app.php';

$path = isset($_GET['route']) ? '/api/' . $_GET['route'] : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ---------- 健康检查 ---------- //
if ($path === '/api/health' && $method === 'GET') {
    json(['status' => 'ok', 'message' => '像素人生 API 运行中']);
}
// ---------- 调试信息 ---------- //
elseif ($path === '/api/debug' && $method === 'GET') {
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $memoryCount = (int)$pdo->query("SELECT COUNT(*) FROM memory WHERE family_id=1")->fetchColumn();
        json([
            'status' => 'ok',
            'php_version' => PHP_VERSION,
            'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
            'db_name' => DB_NAME,
            'db_host' => DB_HOST,
            'tables' => $tables,
            'memory_count' => $memoryCount,
        ]);
    } catch (PDOException $e) {
        json(['error' => $e->getMessage(), 'code' => $e->getCode()], 500);
    }
}

// -------- 个人资料 -------- //
elseif ($path === '/api/profile' && $method === 'GET') {
    require_once __DIR__ . '/routes/family.php';
    getProfile();
} elseif ($path === '/api/profile' && $method === 'POST') {
    require_once __DIR__ . '/routes/family.php';
    updateProfile();
}

// -------- AI 配置 -------- //
elseif ($path === '/api/ai/config' && $method === 'GET') {
    require_once __DIR__ . '/routes/ai/config.php';
    getAiConfig();
} elseif ($path === '/api/ai/config' && $method === 'POST') {
    require_once __DIR__ . '/routes/ai/config.php';
    saveAiConfig();
}

// -------- 目标 -------- //
elseif ($path === '/api/goals' && $method === 'GET') {
    require_once __DIR__ . '/routes/goals.php';
    getGoals();
} elseif ($path === '/api/goals' && $method === 'POST') {
    require_once __DIR__ . '/routes/goals.php';
    createGoal();
} elseif (preg_match('#^/api/goals/(d+)$#', $path, $m) && $method === 'PUT') {
    require_once __DIR__ . '/routes/goals.php';
    updateGoal($m[1]);
}

// -------- 收入 -------- //
elseif ($path === '/api/income' && $method === 'GET') {
    require_once __DIR__ . '/routes/income.php';
    getIncome();
} elseif ($path === '/api/income' && $method === 'POST') {
    require_once __DIR__ . '/routes/income.php';
    addIncome();
}

// -------- 支出 -------- //
elseif ($path === '/api/expense' && $method === 'GET') {
    require_once __DIR__ . '/routes/expense.php';
    getExpense();
} elseif ($path === '/api/expense' && $method === 'POST') {
    require_once __DIR__ . '/routes/expense.php';
    addExpense();
}

// -------- Dashboard -------- //
elseif ($path === '/api/dashboard' && $method === 'GET') {
    require_once __DIR__ . '/routes/dashboard.php';
    getDashboard();
}

// -------- 今日任务 -------- //
elseif ($path === '/api/today' && $method === 'GET') {
    require_once __DIR__ . '/routes/today.php';
    getToday();
} elseif ($path === '/api/tasks' && $method === 'GET') {
    require_once __DIR__ . '/routes/today.php';
    getTasks();
} elseif (preg_match('#^/api/tasks/(d+)/complete$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/routes/today.php';
    completeTask($m[1]);
} elseif ($path === '/api/tasks' && $method === 'POST') {
    require_once __DIR__ . '/routes/today.php';
    addTask();
}

// -------- AI 目标拆解 -------- //
elseif ($path === '/api/ai/goal-breakdown' && $method === 'POST') {
    require_once __DIR__ . '/routes/ai/chat.php';
    goalBreakdown();
}

// -------- 记忆 -------- //
elseif ($path === '/api/memory' && $method === 'GET') {
    require_once __DIR__ . '/routes/memory.php';
    getMemories();
} elseif ($path === '/api/memory' && $method === 'POST') {
    require_once __DIR__ . '/routes/memory.php';
    setMemory();
}

// -------- AI 记忆分析 -------- //
elseif ($path === '/api/ai/memory' && $method === 'POST') {
    require_once __DIR__ . '/routes/ai/memory.php';
    aiMemory();
}

// -------- AI 对话记录 -------- //
elseif ($path === '/api/ai/dialogue' && $method === 'GET') {
    require_once __DIR__ . '/routes/ai/dialogue.php';
    getDialogue();
} elseif ($path === '/api/ai/dialogue' && $method === 'POST') {
    require_once __DIR__ . '/routes/ai/dialogue.php';
    addDialogue();
}

// -------- 用户活动 -------- //
elseif ($path === '/api/activities' && $method === 'GET') {
    require_once __DIR__ . '/routes/activities.php';
    getActivities();
} elseif ($path === '/api/activities' && $method === 'POST') {
    require_once __DIR__ . '/routes/activities.php';
    addActivity();
} elseif ($path === '/api/activities/end' && $method === 'POST') {
    require_once __DIR__ . '/routes/activities.php';
    endActivity();
} elseif ($path === '/api/activities/today' && $method === 'GET') {
    require_once __DIR__ . '/routes/activities.php';
    getTodayActivities();
}

// -------- 每日提醒 -------- //
elseif ($path === '/api/reminders' && $method === 'GET') {
    require_once __DIR__ . '/routes/reminders.php';
    getReminders();
} elseif ($path === '/api/reminders' && $method === 'POST') {
    require_once __DIR__ . '/routes/reminders.php';
    createReminder();
} elseif ($path === '/api/reminders/generate' && $method === 'POST') {
    require_once __DIR__ . '/routes/reminders.php';
    generateDailyReminders();
} elseif (preg_match('#^/api/reminders/(d+)/read$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/routes/reminders.php';
    markReminderRead($m[1]);
} elseif (preg_match('#^/api/reminders/(d+)/done$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/routes/reminders.php';
    markReminderDone($m[1]);
}

// -------- 404 -------- //
else {
    json(['error' => 'Not Found'], 404);
}
