<?php
// AI 对话记录管理

function getDialogue() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM dialogue_records WHERE family_id=1 ORDER BY created_at DESC LIMIT 50");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json(['records' => $records]);
}

function addDialogue() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !is_array($input)) {
        json(['error' => '无效的请求数据'], 400);
    }
    
    $role = $input['role'] ?? 'ai';
    $content = $input['content'] ?? '';
    $time = $input['time'] ?? date('Y-m-d H:i:s');
    
    if (empty($content)) {
        json(['error' => '内容不能为空'], 400);
    }
    
    $pdo->prepare("INSERT INTO dialogue_records (family_id, role, content, created_at) VALUES (1, ?, ?, ?)")
        ->execute([$role, $content, $time]);
    
    json(['message' => '对话已保存', 'id' => $pdo->lastInsertId()]);
}
