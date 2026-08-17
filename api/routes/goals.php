<?php
// 目标管理 + 自动拆解

function getGoals() {
    global $pdo;
    $goals = $pdo->query("SELECT * FROM goals WHERE family_id=1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    json(['goals' => $goals]);
}

function createGoal() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $title = $input['title'] ?? '';
    $targetAmount = floatval($input['target_amount'] ?? 0);
    $startDate = $input['start_date'] ?? date('Y-01-01');
    $endDate = $input['end_date'] ?? date('Y-12-31');

    // 插入年度目标
    $pdo->prepare("INSERT INTO goals (family_id, title, type, target_amount, start_date, end_date) VALUES (1, ?, 'annual', ?, ?, ?)")
        ->execute([$title, $targetAmount, $startDate, $endDate]);
    $parentId = $pdo->lastInsertId();

    // 自动拆解月度目标
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    $totalMonths = max(1, $interval->m + 1);

    $monthlyTarget = $targetAmount / $totalMonths;

    for ($i = 0; $i < $totalMonths; $i++) {
        $m = (clone $start)->modify("+$i month");
        $mStart = $m->format('Y-m-01');
        $mEnd = $m->format('Y-m-t');

        $pdo->prepare("INSERT INTO goals (family_id, title, type, target_amount, start_date, end_date, parent_id) VALUES (1, ?, 'monthly', ?, ?, ?, ?)")
            ->execute([
                $m->format('Y年n月') . '目标',
                round($monthlyTarget, 2),
                $mStart,
                $mEnd,
                $parentId
            ]);

        $monthId = $pdo->lastInsertId();

        // 拆解周目标
        $wStart = new DateTime($mStart);
        $wEnd = new DateTime($mEnd);
        $weekTargets = [];
        $currentWeek = (clone $wStart);
        while ($currentWeek <= $wEnd) {
            $weekNum = $currentWeek->format('W');
            $weekTargets[] = [
                'start' => $currentWeek->format('Y-m-d'),
                'end' => (clone $currentWeek)->modify('+6 days')->format('Y-m-d'),
            ];
            $currentWeek->modify('+7 days');
        }

        $weeklyTarget = $monthlyTarget / max(1, count($weekTargets));
        foreach ($weekTargets as $wi => $w) {
            $pdo->prepare("INSERT INTO goals (family_id, title, type, target_amount, start_date, end_date, parent_id) VALUES (1, ?, 'weekly', ?, ?, ?, ?)")
                ->execute([
                    $m->format('Y年n月') . ' 第' . ($wi + 1) . '周',
                    round($weeklyTarget, 2),
                    $w['start'],
                    $w['end'],
                    $monthId
                ]);
        }
    }

    // 今日目标（剩余天数平均）
    $today = new DateTime();
    $remainingDays = max(1, (int)$today->diff($end)->days);
    $yearCompleted = getGoalCompleted($pdo, $parentId);
    $remaining = max(0, $targetAmount - $yearCompleted);
    $dailyTarget = $remaining / $remainingDays;

    $pdo->prepare("INSERT INTO goals (family_id, title, type, target_amount, start_date, end_date, parent_id) VALUES (1, ?, 'daily', ?, ?, ?, ?)")
        ->execute([
            '今日目标',
            round($dailyTarget, 2),
            $today->format('Y-m-d'),
            $today->format('Y-m-d'),
            $parentId
        ]);

    // 读取所有拆解后的目标
    $goals = $pdo->query("SELECT * FROM goals WHERE family_id=1 ORDER BY start_date, type")->fetchAll(PDO::FETCH_ASSOC);

    json([
        'message' => '目标创建并拆解成功',
        'annual_goal_id' => $parentId,
        'goals' => $goals
    ]);
}

function updateGoal($id) {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $fields = [];
    $params = [];
    if (isset($input['title'])) { $fields[] = 'title=?'; $params[] = $input['title']; }
    if (isset($input['target_amount'])) { $fields[] = 'target_amount=?'; $params[] = floatval($input['target_amount']); }
    if (isset($input['current_amount'])) { $fields[] = 'current_amount=?'; $params[] = floatval($input['current_amount']); }
    if (isset($input['status'])) { $fields[] = 'status=?'; $params[] = $input['status']; }
    $fields[] = 'updated_at=NOW()';
    $params[] = $id;

    $pdo->prepare("UPDATE goals SET " . implode(', ', $fields) . " WHERE id=?")->execute($params);
    json(['message' => '目标更新成功']);
}

// 辅助：获取某目标已完成金额
function getGoalCompleted($pdo, $goalId) {
    $stmt = $pdo->prepare("SELECT * FROM goals WHERE id=?");
    $stmt->execute([$goalId]);
    $goal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$goal) return 0;
    return floatval($goal['current_amount']);
}
