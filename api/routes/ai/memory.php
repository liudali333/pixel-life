<?php
// AI 记忆分析：接收用户输入，分析内容，自动整理到 memory
function aiMemory() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $content = trim($input['content'] ?? '');

    if (!$content) {
        json(['error' => '内容不能为空'], 400);
    }

    // 读取上下文（最近记忆 + 家庭状态）
    $memories = $pdo->query("SELECT * FROM memory WHERE family_id=1 ORDER BY updated_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $family = $pdo->query("SELECT * FROM family WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $annual = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='annual' AND status='active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $todayTasks = $pdo->query("SELECT * FROM daily_tasks WHERE family_id=1 AND task_date='" . date('Y-m-d') . "' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $context = [];
    $context[] = "家庭等级：Lv.{$family['level']}，经验：{$family['exp']}";
    if ($annual) {
        $context[] = "年度目标：{$annual['title']}，进度：{$annual['current_amount']}/{$annual['target_amount']}";
    }
    if ($memories) {
        $context[] = "近期记忆：" . implode(' | ', array_column($memories, 'value'));
    }
    if ($todayTasks) {
        $context[] = "今日任务：" . implode(', ', array_column($todayTasks, 'title'));
    }

    // 优先使用前端传来的 API Key，否则用环境变量
    $apiKey = $input['api_key'] ?: getenv('OPENAI_API_KEY');
    $modelId = $input['model_id'] ?? 'gpt-3.5-turbo';
    $modelEndpoint = $input['endpoint'] ?? '';

    if ($apiKey) {
        $systemPrompt = "你是一个记忆整理助手。用户会输入一段想记录为日记/计划/复盘，请分析内容，提取关键信息，并决定如何保存。输出 JSON 格式（必须严格是 JSON，不要有 markdown 代码块）：{
  \"summary\": \"一句话总结这段内容\",
  \"memories\": [
    {\"key\": \"记忆分类key\", \"value\": \"记忆内容\"},
    ...
  ],
  \"response\": \"给用户的回复，温暖鼓励，1-2句话\"
}

记忆分类 key 可选：
- daily_thought（日常想法）
- goal_reflect（目标相关反思）
- finance_note（财务相关）
- task_insight（任务行动洞察）- idea（创意想法）
- habit（习惯相关）
- family_note（家庭相关）
- learning（学习成长）
请结合上下文判断这段内容是否重要，避免重复保存相似内容。";

        $userPrompt = "上下文：" . implode("\n", $context) . "\n\n用户输入：{$content}";

        // 解析实际模型名称
        $modelName = $modelId;
        if ($modelId === 'custom') {
            $modelName = $input['model_name'] ?? 'gpt-3.5-turbo';
        }

        $baseUrl = $modelEndpoint ?: 'https://api.openai.com';
        $url = rtrim($baseUrl, '/') . '/v1/chat/completions';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $modelName,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'temperature' => 0.7
            ])
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $content_str = $data['choices'][0]['message']['content'] ?? '';
            // 去掉可能的 markdown 代码块
            $content_str = preg_replace('/^```\s*/', '', $content_str);
            $content_str = preg_replace('/\s*```$/', '', $content_str);
            $parsed = json_decode($content_str, true);

            if ($parsed && is_array($parsed)) {
                // 保存记忆
                foreach (($parsed['memories'] ?? []) as $m) {
                    $key = $m['key'] ?? 'default';
                    $value = $m['value'] ?? '';
                    if (!$value) continue;
                    $existing = $pdo->prepare("SELECT id FROM memory WHERE family_id=1 AND `mkey`=?");
                    $existing->execute([$key]);
                    if ($existing->fetch()) {
                        $pdo->prepare("UPDATE memory SET value=?, updated_at=NOW() WHERE family_id=1 AND `mkey`=?")
                            ->execute([$value, $key]);
                    } else {
                        $pdo->prepare("INSERT INTO memory (family_id, `mkey`, value) VALUES (1, ?, ?)")
                            ->execute([$key, $value]);
                    }
                }
                json([
                    'summary' => $parsed['summary'] ?? '',
                    'response' => $parsed['response'] ?? '',
                    'saved' => count($parsed['memories'] ?? []),
                    'model' => $modelName
                ]);
            } else {
                saveSimpleMemory($pdo, 'daily_thought', $content);
                json(['response' => '已记录：' . mb_substr($content, 0, 30) . '...']);
            }
        } else {
            saveSimpleMemory($pdo, 'daily_thought', $content);
            json(['response' => '已保存到记忆（AI 调用失败，HTTP ' . $httpCode . '）']);
        }
    } else {
        // 无 API，自动规则保存
        saveSimpleMemory($pdo, $key, $content);
        $labels = [
            'daily_thought'=>'💭 日常想法','goal_reflect'=>'🎯 目标反思',
            'finance_note'=>'💰 财务记录','idea'=>'💡 创意想法',
            'habit'=>'🌱 习惯','family_note'=>'👨‍👩‍👧 家庭','default'=>'🧠 记忆'
        ];
        json(['response' => ($labels[$key] ?? '🧠') . ' 已保存']);
    }
}

function saveSimpleMemory($pdo, $key, $value) {
    $existing = $pdo->prepare("SELECT id FROM memory WHERE family_id=1 AND `mkey`=?");
    $existing->execute([$key]);
    if ($existing->fetch()) {
        $pdo->prepare("UPDATE memory SET value=CONCAT(value, ' | ', ?), updated_at=NOW() WHERE family_id=1 AND `mkey`=?")
            ->execute([$value, $key]);
    } else {
        $pdo->prepare("INSERT INTO memory (family_id, `mkey`, value) VALUES (1, ?, ?)")
            ->execute([$key, $value]);
    }
}

function detectMemoryKey($content) {
    $keywords = [
        'goal_reflect' => ['目标','计划','赚钱','收入','省钱','存钱','投资','副业'],
        'finance_note' => ['花了','收入','支出','工资','消费','理财','账单'],
        'idea' => ['想法','创意','灵感','突然想到','主意'],
        'habit' => ['习惯','每天','坚持','早起','运动','读书'],
        'family_note' => ['家庭','孩子','老婆','老公','父母','家人'],
        'learning' => ['学到知识','学习','了解','发现','成长'],
    ];
    foreach ($keywords as $key => $words) {
        foreach ($words as $w) {
            if (mb_strpos($content, $w) !== false) return $key;
        }
    }
    return 'daily_thought';
}
