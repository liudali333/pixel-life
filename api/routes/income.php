<?php
// 收入管理

function getIncome() {
    global $pdo;
    $month = $_GET['month'] ?? date('Y-m');
    $year = $_GET['year'] ?? date('Y');
    $today = date('Y-m-d');

    $like = ($month !== 'all' ? $month : $year) . '-%';

    $records = $pdo->query("SELECT * FROM income_records WHERE family_id=1 AND date LIKE '$like' ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);

    // 统计
    $summary = $pdo->query("
        SELECT type, SUM(amount) as total
        FROM income_records
        WHERE family_id=1 AND date LIKE '$like'
        GROUP BY type
    ")->fetchAll(PDO::FETCH_ASSOC);

    $total = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM income_records WHERE family_id=1 AND date LIKE '$like'")->fetchColumn();

    // 今日收入
    $todayTotal = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM income_records WHERE family_id=1 AND date='$today'")->fetchColumn();

    json([
        'records' => $records,
        'summary' => $summary,
        'total' => floatval($total),
        'today' => floatval($todayTotal)
    ]);
}

function addIncome() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $amount = floatval($input['amount'] ?? 0);
    $type = $input['type'] ?? '其他收入';
    $date = $input['date'] ?? date('Y-m-d');
    $remark = $input['remark'] ?? '';

    $pdo->prepare("INSERT INTO income_records (family_id, amount, type, date, remark) VALUES (1, ?, ?, ?, ?)")
        ->execute([$amount, $type, $date, $remark]);

    // 更新今日目标完成金额
    updateDailyIncome($pdo, $amount, $date);

    json(['id' => $pdo->lastInsertId(), 'message' => '收入记录已添加']);
}

function updateDailyIncome($pdo, $amount, $date) {
    // 找到当天及本周、本月的日目标，更新 current_amount
    $dailyGoal = $pdo->prepare("SELECT id, current_amount FROM goals WHERE family_id=1 AND type='daily' AND start_date=?")->execute([$date]);
    $row = $dailyGoal ? $pdo->fetch() : null;
    if ($row) {
        $pdo->prepare("UPDATE goals SET current_amount = current_amount + ? WHERE id=?")->execute([$amount, $row['id']]);
    }

    // 周目标
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
    $pdo->prepare("UPDATE goals SET current_amount = current_amount + ? WHERE family_id=1 AND type='weekly' AND start_date<=? AND end_date>=?")
        ->execute([$amount, $date, $date]);

    // 月目标
    $month = substr($date, 0, 7);
    $pdo->prepare("UPDATE goals SET current_amount = current_amount + ? WHERE family_id=1 AND type='monthly' AND start_date LIKE ?")
        ->execute([$amount, "$month%"]);

    // 年度目标
    $year = substr($date, 0, 4);
    $pdo->prepare("UPDATE goals SET current_amount = current_amount + ? WHERE family_id=1 AND type='annual' AND start_date LIKE ?")
        ->execute([$amount, "$year%"]);

    // 更新家庭经验
    checkFamilyGrowth($pdo);
}

function checkFamilyGrowth($pdo) {
    $family = $pdo->query("SELECT * FROM family WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $exp = intval($family['exp']);
    $level = intval($family['level']);
    $threshold = $level * 1000;

    if ($exp >= $threshold) {
        $newLevel = $level + 1;
        $pdo->prepare("UPDATE family SET level=?, exp=? WHERE id=1")->execute([$newLevel, $exp - $threshold]);
    }
}
