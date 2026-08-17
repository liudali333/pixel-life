<?php
// 支出管理

function getExpense() {
    global $pdo;
    $month = $_GET['month'] ?? date('Y-m');
    $year = $_GET['year'] ?? date('Y');

    $like = ($month !== 'all' ? $month : $year) . '-%';

    $records = $pdo->query("SELECT * FROM expense_records WHERE family_id=1 AND date LIKE '$like' ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);

    $summary = $pdo->query("
        SELECT type, SUM(amount) as total
        FROM expense_records
        WHERE family_id=1 AND date LIKE '$like'
        GROUP BY type
    ")->fetchAll(PDO::FETCH_ASSOC);

    $total = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expense_records WHERE family_id=1 AND date LIKE '$like'")->fetchColumn();

    json([
        'records' => $records,
        'summary' => $summary,
        'total' => floatval($total)
    ]);
}

function addExpense() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $amount = floatval($input['amount'] ?? 0);
    $type = $input['type'] ?? '其他';
    $date = $input['date'] ?? date('Y-m-d');
    $remark = $input['remark'] ?? '';

    $pdo->prepare("INSERT INTO expense_records (family_id, amount, type, date, remark) VALUES (1, ?, ?, ?, ?)")
        ->execute([$amount, $type, $date, $remark]);

    json(['id' => $pdo->lastInsertId(), 'message' => '支出记录已添加']);
}
