<?php
// 用户资料（单人模式）

function getProfile() {
    global $pdo;
    $family = $pdo->query("SELECT * FROM family WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $member = $pdo->query("SELECT * FROM family_members WHERE family_id=1 AND role='self' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    json(['profile' => $family, 'member' => $member]);
}

function updateProfile() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $fields = [];
    $params = [];
    if (isset($input['name'])) { $fields[] = 'name=?'; $params[] = $input['name']; }
    if (isset($input['avatar'])) { $fields[] = 'avatar=?'; $params[] = $input['avatar']; }
    if (isset($input['job'])) { $fields[] = 'job=?'; $params[] = $input['job']; }
    if (isset($input['age'])) { $fields[] = 'age=?'; $params[] = intval($input['age']); }
    if (isset($input['target_income'])) { $fields[] = 'target_income=?'; $params[] = floatval($input['target_income']); }
    $fields[] = 'updated_at=NOW()';

    $pdo->prepare("UPDATE family SET " . implode(', ', $fields) . " WHERE id=1")->execute($params);

    if (isset($input['name']) || isset($input['avatar']) || isset($input['age']) || isset($input['job'])) {
        $mfields = [];
        $mparams = [];
        if (isset($input['name'])) { $mfields[] = 'name=?'; $mparams[] = $input['name']; }
        if (isset($input['avatar'])) { $mfields[] = 'avatar=?'; $mparams[] = $input['avatar']; }
        if (isset($input['age'])) { $mfields[] = 'age=?'; $mparams[] = intval($input['age']); }
        if (isset($input['job'])) { $mfields[] = 'job=?'; $mparams[] = $input['job']; }
        $mparams[] = 1;
        $pdo->prepare("UPDATE family_members SET " . implode(', ', $mfields) . " WHERE family_id=1 AND role='self'")->execute($mparams);
    }

    json(['message' => '资料已更新']);
}
