<?php
// 每日提醒（AI 生成）

function getReminders() {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM daily_reminders WHERE family_id=1 AND DATE(remind_at)=? ORDER BY remind_at ASC");
    $stmt->execute([$today]);
    json(['reminders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createReminder() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $title = $input['title'] ?? '';
    $message = $input['message'] ?? '';
    $type = $input['type'] ?? 'goal';
    $relatedGoalId = $input['related_goal_id'] ?? null;
    $remindAt = $input['remind_at'] ?? date('Y-m-d H:i:s');

    $pdo->prepare("INSERT INTO daily_reminders (family_id, title, message, type, related_goal_id, remind_at) VALUES (1, ?, ?, ?, ?, ?)")
        ->execute([$title, $message, $type, $relatedGoalId, $remindAt]);
    json(['id' => $pdo->lastInsertId(), 'message' => '提醒已创建']);
}

function markReminderRead($id) {
    global $pdo;
    $pdo->prepare("UPDATE daily_reminders SET is_read=1 WHERE id=? AND family_id=1")->execute([$id]);
    json(['message' => '已标记为已读']);
}

function markReminderDone($id) {
    global $pdo;
    $pdo->prepare("UPDATE daily_reminders SET is_done=1 WHERE id=? AND family_id=1")->execute([$id]);
    json(['message' => '已标记为完成']);
}

function generateDailyReminders() {
    global $pdo;
    $today = date('Y-m-d');

    $dailyGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='daily' AND start_date='$today' AND status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $annualGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='annual' AND status='active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("DELETE FROM daily_reminders WHERE family_id=1 AND DATE(remind_at)=?")->execute([$today]);

    $reminders = [];

    if ($annualGoal) {
        $reminders[] = [
            'title' => '🎯 年度目标提醒',
            'message' => "今年目标：{$annualGoal['title']}，目标金额 ¥" . number_format($annualGoal['target_amount']) . "。加油！",
            'type' => 'goal',
            'related_goal_id' => $annualGoal['id'],
            'remind_at' => $today . ' 08:00'
        ];
    }

    if ($dailyGoal) {
        $reminders[] = [
            'title' => '📋 今日目标',
            'message' => "今日需完成：{$dailyGoal['title']}，目标金额 ¥" . number_format($dailyGoal['target_amount']) . "。",
            'type' => 'goal',
            'related_goal_id' => $dailyGoal['id'],
            'remind_at' => $today . ' 09:00'
        ];
    }

    $reminders[] = [
        'title' => '💪 早安提醒',
        'message' => '新的一天开始了！专注目标，高效执行。',
        'type' => 'habit',
        'related_goal_id' => $annualGoal['id'] ?? null,
        'remind_at' => $today . ' 07:30'
    ];

    $reminders[] = [
        'title' => '🌙 晚间复盘',
        'message' => '今天过得怎么样？记得记录你的进展和收获。',
        'type' => 'habit',
        'related_goal_id' => null,
        'remind_at' => $today . ' 21:00'
    ];

    foreach ($reminders as $r) {
        $pdo->prepare("INSERT INTO daily_reminders (family_id, title, message, type, related_goal_id, remind_at) VALUES (1, ?, ?, ?, ?, ?)")
            ->execute([$r['title'], $r['message'], $r['type'], $r['related_goal_id'], $r['remind_at']]);
    }

    json(['message' => '提醒生成成功', 'count' => count($reminders)]);
}
