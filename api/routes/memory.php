<?php
// 记忆管理

function getMemories() {
    global $pdo;
    $memories = $pdo->query("SELECT * FROM memory WHERE family_id=1 ORDER BY updated_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    json(['memories' => $memories]);
}

function setMemory() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $key = $input['key'] ?? 'default';
    $value = $input['value'] ?? '';

    // upsert
    $existing = $pdo->prepare("SELECT id FROM memory WHERE family_id=1 AND `mkey`=?");
    $existing->execute([$key]);
    if ($existing->fetch()) {
        $pdo->prepare("UPDATE memory SET value=?, updated_at=NOW() WHERE family_id=1 AND `mkey`=?")
            ->execute([$value, $key]);
    } else {
        $pdo->prepare("INSERT INTO memory (family_id, `mkey`, value) VALUES (1, ?, ?)")
            ->execute([$key, $value]);
    }

    json(['message' => '记忆已保存', 'key' => $key]);
}

function deleteMemory($id) {
    global $pdo;
    $pdo->prepare("DELETE FROM memory WHERE id=? AND family_id=1")->execute([$id]);
    json(['message' => '已删除']);
}
