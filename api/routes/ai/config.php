<?php
// AI / 大模型配置管理

function getAiConfig() {
    global $pdo;
    $stmt = $pdo->query("SELECT api_url, api_key, model_name, year_target, fixed_income, city FROM family WHERE id=1 LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    json([
        'api_url' => $row['api_url'] ?? '',
        'api_key' => $row['api_key'] ?? '',
        'model_name' => $row['model_name'] ?? 'gpt-3.5-turbo',
        'year_target' => $row['year_target'] ?? 1000000,
        'fixed_income' => $row['fixed_income'] ?? '',
        'city' => $row['city'] ?? '北京',
    ]);
}

function saveAiConfig() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !is_array($input)) {
        json(['error' => '无效的请求数据'], 400);
    }

    try {
        // 确保 id=1 的记录存在，避免主键冲突
        $pdo->prepare("
            INSERT INTO family (id, name, avatar, job, api_url, api_key, model_name, year_target, fixed_income, city)
            VALUES (1, '我的目标', '🧍', '职场人', ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id=id
        ")->execute([
            $input['api_url'] ?? '',
            $input['api_key'] ?? '',
            $input['model_name'] ?? 'gpt-3.5-turbo',
            floatval($input['year_target'] ?? 1000000),
            $input['fixed_income'] ?? '',
            $input['city'] ?? '北京'
        ]);

        $fields = [];
        $params = [];
        if (isset($input['api_url'])) { $fields[] = 'api_url=?'; $params[] = $input['api_url']; }
        if (isset($input['api_key'])) { $fields[] = 'api_key=?'; $params[] = $input['api_key']; }
        if (isset($input['model_name'])) { $fields[] = 'model_name=?'; $params[] = $input['model_name']; }
        if (isset($input['year_target'])) { $fields[] = 'year_target=?'; $params[] = floatval($input['year_target']); }
        if (isset($input['fixed_income'])) { $fields[] = 'fixed_income=?'; $params[] = $input['fixed_income']; }
        if (isset($input['city'])) { $fields[] = 'city=?'; $params[] = $input['city']; }
        $fields[] = 'updated_at=NOW()';

        $stmt = $pdo->prepare("UPDATE family SET " . implode(', ', $fields) . " WHERE id=1");
        $stmt->execute($params);
        $affected = $stmt->rowCount();

        json(['message' => 'AI 配置已保存', 'affected_rows' => $affected]);
    } catch (Exception $e) {
        json(['error' => '保存失败：' . $e->getMessage()], 500);
    }
}
