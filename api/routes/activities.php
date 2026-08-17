<?php
// 用户活动记录

function getActivities() {
    global $pdo;
    $limit = intval($_GET['limit'] ?? 20);
    $stmt = $pdo->prepare("SELECT * FROM user_activities WHERE family_id=1 ORDER BY started_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    json(['activities' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function addActivity() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['activity_type'] ?? 'work';
    $desc = $input['description'] ?? '';

    $pdo->prepare("UPDATE user_activities SET ended_at=NOW(), duration_minutes=TIMESTAMPDIFF(MINUTE, started_at, NOW()) WHERE family_id=1 AND ended_at IS NULL")->execute();

    $pdo->prepare("INSERT INTO user_activities (family_id, activity_type, description) VALUES (1, ?, ?)")->execute([$type, $desc]);
    json(['id' => $pdo->lastInsertId(), 'message' => '活动已记录']);
}

function endActivity() {
    global $pdo;
    $pdo->prepare("UPDATE user_activities SET ended_at=NOW(), duration_minutes=TIMESTAMPDIFF(MINUTE, started_at, NOW()) WHERE family_id=1 AND ended_at IS NULL")->execute();
    json(['message' => '活动已结束']);
}

function getTodayActivities() {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM user_activities WHERE family_id=1 AND DATE(started_at)=? ORDER BY started_at DESC");
    $stmt->execute([$today]);
    json(['activities' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
