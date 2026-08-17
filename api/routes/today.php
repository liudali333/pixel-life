<?php
// 今日任务

function getToday() {
    global $pdo;
    $today = date('Y-m-d');

    $dailyGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='daily' AND start_date='$today' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $tasks = $pdo->query("SELECT * FROM daily_tasks WHERE family_id=1 AND task_date='$today' ORDER BY priority DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $todayIncome = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM income_records WHERE family_id=1 AND date='$today'")->fetchColumn());
    $todayExpense = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expense_records WHERE family_id=1 AND date='$today'")->fetchColumn());
    $activities = $pdo->query("SELECT * FROM user_activities WHERE family_id=1 AND DATE(started_at)='$today' ORDER BY started_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $currentActivity = $pdo->query("SELECT * FROM user_activities WHERE family_id=1 AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    json([
        'date' => $today,
        'goal' => $dailyGoal,
        'tasks' => $tasks,
        'today_income' => $todayIncome,
        'today_expense' => $todayExpense,
        'task_count' => count($tasks),
        'completed_count' => count(array_filter($tasks, fn($t) => $t['status'] === 'completed')),
        'activities' => $activities,
        'current_activity' => $currentActivity,
    ]);
}

function getTasks() {
    global $pdo;
    $date = $_GET['date'] ?? date('Y-m-d');
    $tasks = $pdo->query("SELECT * FROM daily_tasks WHERE family_id=1 AND task_date='$date' ORDER BY priority DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    json(['tasks' => $tasks]);
}

function completeTask($id) {
    global $pdo;
    $pdo->prepare("UPDATE daily_tasks SET status='completed', completed_at=NOW() WHERE id=?")->execute([$id]);
    $pdo->prepare("UPDATE family SET exp = exp + 10 WHERE id=1")->execute();
    checkFamilyGrowth($pdo);
    json(['message' => '任务已完成']);
}

function addTask() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $today = date('Y-m-d');
    $pdo->prepare("INSERT INTO daily_tasks (family_id, goal_id, title, description, task_date, priority) VALUES (1, ?, ?, ?, ?, ?)")
        ->execute([
            $input['goal_id'] ?? null,
            $input['content'] ?? $input['title'] ?? '',
            $input['description'] ?? '',
            $input['task_date'] ?? $today,
            $input['priority'] ?? 2
        ]);
    json(['id' => $pdo->lastInsertId(), 'message' => '任务已添加']);
}

function checkFamilyGrowth($pdo) {
    $family = $pdo->query("SELECT * FROM family WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $exp = intval($family['exp']);
    $level = intval($family['level']);
    $threshold = $level * 1000;
    if ($exp >= $threshold) {
        $pdo->prepare("UPDATE family SET level=level+1, exp=? WHERE id=1")->execute([$exp - $threshold]);
    }
}
