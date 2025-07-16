<?php
require_once 'db_connection.php';

header('Content-Type: application/json');

$resultados = [];

function upsertUnique($pdo, $tabla, $datos, $claves_unicas) {
    $where = [];
    $params = [];

    foreach ($claves_unicas as $clave) {
        $where[] = "`$clave` = ?";
        $params[] = $datos[$clave];
    }

    $sql_check = "SELECT COUNT(*) FROM `$tabla` WHERE " . implode(' AND ', $where);
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($params);
    $exists = $stmt_check->fetchColumn();

    $campos = array_keys($datos);
    $placeholders = array_fill(0, count($campos), '?');
    $param_values = array_values($datos);

    $updates = [];
    foreach ($campos as $campo) {
        if (!in_array($campo, $claves_unicas)) {
            $updates[] = "`$campo` = ?";
            $param_values[] = $datos[$campo];
        }
    }

    if ($exists) {
        $sql = "UPDATE `$tabla` SET " . implode(', ', $updates) . " WHERE " . implode(' AND ', $where);
    } else {
        $sql = "INSERT INTO `$tabla` (`" . implode('`,`', $campos) . "`) VALUES (" . implode(',', $placeholders) . ")";
    }

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($param_values);
}

function limpiarReacciones($data) {
    $limpio = [
        'like' => 0, 'love' => 0, 'wow' => 0, 'haha' => 0, 'anger' => 0, 'sorry' => 0
    ];
    if (is_array($data)) {
        foreach ($limpio as $k => $v) {
            if (isset($data[$k]) && is_numeric($data[$k])) {
                $limpio[$k] = $data[$k];
            }
        }
    }
    return $limpio;
}

// Simulación de cuentas
$cuentas = [
    [
        'id_cuenta' => 1,
        'access_token' => 'TOKEN_1',
        'page_id' => 'PAGE_ID_1',
        'ig_id' => 'IG_ID_1'
    ],
    [
        'id_cuenta' => 3,
        'access_token' => 'TOKEN_2',
        'page_id' => 'PAGE_ID_2',
        'ig_id' => 'IG_ID_2'
    ]
];

foreach ($cuentas as $cuenta) {
    $id_cuenta = $cuenta['id_cuenta'];
    $token = $cuenta['access_token'];
    $page_id = $cuenta['page_id'];
    $ig_id = $cuenta['ig_id'];

    $resultados[] = "🟡 Procesando cuenta $id_cuenta";

    try {
        // 1. seguidores_fb
        $seguidores_fb = 12345;
        upsertUnique($pdo, 'seguidores_fb', [
            'id_seguidores_fb' => date('Ymd'),
            'id_cuenta' => $id_cuenta,
            'seguidores' => $seguidores_fb
        ], ['id_seguidores_fb', 'id_cuenta']);
        $resultados[] = "🔄 Actualizado seguidores_fb";

        // 2. insights_fb
        $reacciones = limpiarReacciones([
            'like' => 50, 'love' => 20, 'wow' => 5, 'haha' => 8, 'anger' => 3, 'sorry' => 1
        ]);
        upsertUnique($pdo, 'insights_fb', [
            'id_insights_fb' => date('Ymd'),
            'id_cuenta' => $id_cuenta,
            'page_impressions' => 1000,
            'page_views_total' => 500,
            'page_posts_impressions' => 800,
            'total_interactions' => 200,
            'page_actions_post_reactions_total' => array_sum($reacciones),
            '`like`' => $reacciones['like'],
            '`love`' => $reacciones['love'],
            '`wow`' => $reacciones['wow'],
            '`haha`' => $reacciones['haha'],
            '`anger`' => $reacciones['anger'],
            '`sorry`' => $reacciones['sorry'],
            'comments' => 10,
            'shares' => 5,
            'saves' => 3,
            'created' => date('Y-m-d')
        ], ['id_insights_fb', 'id_cuenta']);
        $resultados[] = "🔄 Actualizado insights_fb";

        // 3. post_fb
        for ($i = 0; $i < 3; $i++) {
            upsertUnique($pdo, 'post_fb', [
                'id' => 'POST' . $i,
                'id_cuenta' => $id_cuenta,
                'message' => 'Post de prueba ' . $i,
                'likes' => 10 + $i,
                'comments' => 5 + $i,
                'shares' => 2 + $i,
                'saves' => 1 + $i,
                'created' => date('Y-m-d')
            ], ['id']);
            $resultados[] = "🔄 Actualizado post_fb";
        }

        // 4. seguidores_ig
        $seguidores_ig = 678;
        upsertUnique($pdo, 'seguidores_ig', [
            'id_seguidores_ig' => date('Ymd'),
            'id_cuenta' => $id_cuenta,
            'seguidores' => $seguidores_ig
        ], ['id_seguidores_ig', 'id_cuenta']);
        $resultados[] = "🔄 Actualizado seguidores_ig";

        // 5. insights_ig
        upsertUnique($pdo, 'insights_ig', [
            'id_insights_ig' => date('Ymd'),
            'id_cuenta' => $id_cuenta,
            'views' => 300,
            'total_interactions' => 120,
            'created' => date('Y-m-d')
        ], ['id_insights_ig', 'id_cuenta']);
        $resultados[] = "🔄 Actualizado insights_ig";

        // 6. ads_insights_fb
        for ($j = 0; $j < 3; $j++) {
            upsertUnique($pdo, 'ads_insights_fb', [
                'ad_id' => 'AD' . $j,
                'ad_name' => 'Anuncio ' . $j,
                'impressions' => 1000 + $j * 10,
                'reach' => 900 + $j * 10,
                'clicks' => 80 + $j,
                'spend' => 50 + $j,
                'cpc' => 0.5 + $j * 0.01,
                'cpm' => 10 + $j * 0.5,
                'ctr' => 1.2 + $j * 0.1,
                'cost_per_result' => 5 + $j * 0.2,
                'date_start' => date('Y-m-d'),
                'date_stop' => date('Y-m-d'),
                'id_cuenta' => $id_cuenta
            ], ['ad_id']);
            $resultados[] = "🔄 Actualizado ads_insights_fb";
        }

        // 7. adsets_insights_fb
        for ($j = 0; $j < 3; $j++) {
            upsertUnique($pdo, 'adsets_insights_fb', [
                'adset_id' => 'ADSET' . $j,
                'adset_name' => 'Conjunto ' . $j,
                'impressions' => 1000 + $j * 10,
                'reach' => 900 + $j * 10,
                'spend' => 30 + $j,
                'date_start' => date('Y-m-d'),
                'date_stop' => date('Y-m-d'),
                'id_cuenta' => $id_cuenta
            ], ['adset_id']);
            $resultados[] = "🔄 Actualizado adsets_insights_fb";
        }

        // 8. campaigns_insights_fb
        for ($j = 0; $j < 3; $j++) {
            upsertUnique($pdo, 'campaigns_insights_fb', [
                'campaign_id' => 'CAMPAIGN' . $j,
                'campaign_name' => 'Campaña ' . $j,
                'impressions' => 5000 + $j * 10,
                'reach' => 4000 + $j * 10,
                'spend' => 100 + $j,
                'date_start' => date('Y-m-d'),
                'date_stop' => date('Y-m-d'),
                'id_cuenta' => $id_cuenta
            ], ['campaign_id']);
            $resultados[] = "🔄 Actualizado campaigns_insights_fb";
        }

    } catch (Exception $e) {
        $resultados[] = "❌ Error: " . $e->getMessage();
    }
}

echo json_encode(['resultados' => $resultados], JSON_UNESCAPED_UNICODE);
