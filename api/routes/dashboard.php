<?php
// Dashboard 核心数据

function getDashboard() {
    global $pdo;
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    $thisYear = date('Y');

    $profile = $pdo->query("SELECT * FROM family WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $annualGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='annual' AND status='active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $monthGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='monthly' AND start_date LIKE '$thisMonth%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='weekly' AND start_date='$weekStart' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $dailyGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='daily' AND start_date='$today' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $monthIncome = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM income_records WHERE family_id=1 AND date LIKE '$thisMonth%'")->fetchColumn());
    $monthExpense = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expense_records WHERE family_id=1 AND date LIKE '$thisMonth%'")->fetchColumn());
    $yearIncome = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM income_records WHERE family_id=1 AND date LIKE '$thisYear%'")->fetchColumn());
    $yearExpense = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expense_records WHERE family_id=1 AND date LIKE '$thisYear%'")->fetchColumn());
    $todayIncome = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM income_records WHERE family_id=1 AND date='$today'")->fetchColumn());

    $calcProgress = function($goal) {
        if (!$goal) return null;
        $target = floatval($goal['target_amount']);
        $current = floatval($goal['current_amount']);
        $progress = $target > 0 ? min(100, round($current / $target * 100, 1)) : 0;
        $remaining = max(0, $target - $current);
        return [
            'id' => $goal['id'],
            'title' => $goal['title'],
            'target' => $target,
            'current' => $current,
            'progress' => $progress,
            'remaining' => $remaining,
            'end_date' => $goal['end_date']
        ];
    };

    $annualEstimate = null;
    if ($annualGoal) {
        $yearDays = date('z', strtotime(date('Y-12-31')));
        $dayOfYear = date('z');
        $dailySpeed = $dayOfYear > 0 ? $yearIncome / $dayOfYear : 0;
        $estimatedYear = round($dailySpeed * $yearDays);
        $annualEstimate = [
            'estimated' => $estimatedYear,
            'target' => floatval($annualGoal['target_amount']),
            'gap' => max(0, floatval($annualGoal['target_amount']) - $estimatedYear),
            'daily_speed' => round($dailySpeed, 2),
        ];
    }

    json([
        'profile' => $profile,
        'annual' => $calcProgress($annualGoal),
        'monthly' => $calcProgress($monthGoal),
        'weekly' => $calcProgress($weekGoal),
        'daily' => $calcProgress($dailyGoal),
        'today_income' => $todayIncome,
        'finance' => [
            'month_income' => $monthIncome,
            'month_expense' => $monthExpense,
            'month_balance' => $monthIncome - $monthExpense,
            'year_income' => $yearIncome,
            'year_expense' => $yearExpense,
            'year_balance' => $yearIncome - $yearExpense,
        ],
        'annual_estimate' => $annualEstimate,
    ]);
}
