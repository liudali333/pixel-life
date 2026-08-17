<?php
// AI 目标拆解 + 每日提醒生成

function goalBreakdown() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $goalTitle = $input['goal'] ?? '';
    $targetAmount = floatval($input['target_amount'] ?? 0);

    if (!$goalTitle || $targetAmount <= 0) {
        json(['error' => '请提供目标和目标金额'], 400);
    }

    $thisYear = date('Y');
    $yearIncome = floatval($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM income_records WHERE family_id=1 AND date LIKE '$thisYear%'")->fetchColumn());
    $profile = $pdo->query("SELECT * FROM family WHERE id=1")->fetch(PDO::FETCH_ASSOC);

    $systemPrompt = "你是一个人生规划助手。请根据用户的财务状况，帮助拆解年度目标。输出 JSON 格式：{"analysis": "...", "suggestions": ["..."], "monthly_plan": {...}}";
    $userPrompt = "用户目标：" . $goalTitle . "，年度目标金额：¥$targetAmount
";
    $userPrompt .= "用户当前年收入：¥$yearIncome
";
    $userPrompt .= "用户职业：" . ($profile['job'] ?? '未设置') . "
";
    $userPrompt .= "请分析差距并给出每月收入计划建议。输出 JSON";

    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey) {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-3.5-turbo',
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
            $content = $data['choices'][0]['message']['content'] ?? '';
            json(['ai' => json_decode($content, true), 'raw' => $content]);
        } else {
            json(['error' => 'AI 服务调用失败', 'detail' => $response], 500);
        }
    } else {
        $remainingMonths = 12 - (int)date('n') + 1;
        $monthlyNeeded = $remainingMonths > 0 ? round($gap / $remainingMonths) : $gap;

        json([
            'ai' => [
                'analysis' => "当前年收入¥" . number_format($yearIncome) . "，目标¥" . number_format($targetAmount) . "，差距¥" . number_format($gap),
                'suggestions' => [
                    "每月需要额外增加约 ¥" . number_format($monthlyNeeded) . " 收入",
                    "可以考虑：副业+¥" . number_format(round($monthlyNeeded * 0.4)) . "，产品收入+¥" . number_format(round($monthlyNeeded * 0.3)) . "，投资+¥" . number_format(round($monthlyNeeded * 0.3)),
                ],
                'monthly_plan' => [
                    'target' => round($targetAmount / 12),
                    'monthly_gap' => $monthlyNeeded,
                    'remaining_months' => $remainingMonths,
                ]
            ]
        ]);
    }
}

function generateDailyReminders() {
    global $pdo;
    $today = date('Y-m-d');

    $dailyGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='daily' AND start_date='$today' AND status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $annualGoal = $pdo->query("SELECT * FROM goals WHERE family_id=1 AND type='annual' AND status='active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("DELETE FROM daily_reminders WHERE family_id=1 AND DATE(remind_at)=?")->execute([$today]);

    $reminders = [];

    if ($annualGoal) {
        $reminders[] = [
            'title' => '🎯 年度目标提醒',
            'message' => "今年目标：{$annualGoal['title']}，目标金额 ¥" . number_format($annualGoal['target_amount']) . "。加油！",
            'type' => 'goal',
            'related_goal_id' => $annualGoal['id'],
            'remind_at' => $today . ' 08:00'
        ];
    }

    if ($dailyGoal) {
        $reminders[] = [
            'title' => '📋 今日目标',
            'message' => "今日需完成：{$dailyGoal['title']}，目标金额 ¥" . number_format($dailyGoal['target_amount']) . "。",
            'type' => 'goal',
            'related_goal_id' => $dailyGoal['id'],
            'remind_at' => $today . ' 09:00'
        ];
    }

    $reminders[] = [
        'title' => '💪 早安提醒',
        'message' => '新的一天开始了！专注目标，高效执行。',
        'type' => 'habit',
        'related_goal_id' => $annualGoal['id'] ?? null,
        'remind_at' => $today . ' 07:30'
    ];

    $reminders[] = [
        'title' => '🌙 晚间复盘',
        'message' => '今天过得怎么样？记得记录你的进展和收获。',
        'type' => 'habit',
        'related_goal_id' => null,
        'remind_at' => $today . ' 21:00'
    ];

    foreach ($reminders as $r) {
        $pdo->prepare("INSERT INTO daily_reminders (family_id, title, message, type, related_goal_id, remind_at) VALUES (1, ?, ?, ?, ?, ?)")
            ->execute([$r['title'], $r['message'], $r['type'], $r['related_goal_id'], $r['remind_at']]);
    }

    json(['message' => '提醒生成成功', 'count' => count($reminders)]);
}
