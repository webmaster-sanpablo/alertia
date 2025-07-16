<?php
header('Content-Type: application/json');
set_time_limit(300);
require_once 'db_connection.php';

$tokenEsperado = 'mi_token_secreto123';
if (!isset($_GET['token']) || $_GET['token'] !== $tokenEsperado) {
    http_response_code(403);
    echo json_encode(['error' => '🚫 Acceso denegado']);
    exit;
}

$apiVersion = 'v18.0';
$resultados = [];

function logMsg($msg) {
    global $resultados;
    $resultados[] = $msg;
}

function apiGet($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) throw new Exception("HTTP {$httpCode} - $url - $response");
    return json_decode($response, true);
}

function upsertUnique($pdo, $table, $keyFields, $data) {
    $whereParts = array();
    foreach ($keyFields as $f) {
        $whereParts[] = "`$f` = ?";
    }
    $where = implode(' AND ', $whereParts);

    $check = $pdo->prepare("SELECT 1 FROM `$table` WHERE $where");

    $keyValues = array();
    foreach ($keyFields as $f) {
        $keyValues[] = $data[$f];
    }
    $check->execute($keyValues);

    $columns = array_keys($data);
    $values = array_values($data);

    if ($check->fetch()) {
        $setParts = array();
        foreach ($columns as $c) {
            $setParts[] = "`$c` = ?";
        }
        $set = implode(', ', $setParts);
        $update = $pdo->prepare("UPDATE `$table` SET $set WHERE $where");
        $update->execute(array_merge($values, $keyValues));
        logMsg("🔄 Actualizado $table");
    } else {
        $cols = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
        $marks = implode(', ', array_fill(0, count($columns), '?'));
        $insert = $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($marks)");
        $insert->execute($values);
        logMsg("✅ Insertado en $table");
    }
}

function extractCost($entry) {
    if (isset($entry['cost_per_result'][0]['values'][0]['value'])) {
        return $entry['cost_per_result'][0]['values'][0]['value'];
    }
    return 0;
}

try {
    $cuentas = $pdo->query("SELECT * FROM cuenta")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cuentas as $cuenta) {
        $id_cuenta = $cuenta['id_cuenta'];
        $fbPageId = $cuenta['fb_page_id'];
        $igUserId = $cuenta['ig_user_id'];
        $adsAccountId = $cuenta['ads_account_id'];
        $accessToken = $cuenta['access_token'];

        if ($accessToken === '-' || empty($accessToken)) continue;
        logMsg("🟡 Procesando cuenta $id_cuenta");

        if ($fbPageId !== '-') {
            $fb = apiGet("https://graph.facebook.com/$apiVersion/$fbPageId?fields=name,followers_count&access_token=$accessToken");
            upsertUnique($pdo, 'seguidores_fb', ['id_seguidores_fb', 'id_cuenta'], array(
                'id_seguidores_fb' => date('Ymd'),
                'followers_count' => $fb['followers_count'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id_cuenta' => $id_cuenta
            ));

            $metrics = ['page_impressions', 'page_fans', 'page_post_engagements', 'page_video_views_paid', 'page_video_views_organic', 'page_actions_post_reactions_total'];
            $insights = apiGet("https://graph.facebook.com/$apiVersion/$fbPageId/insights?metric=" . implode(',', $metrics) . "&period=day&access_token=$accessToken");

            $values = array(
                'id_insights_fb' => date('Ymd'),
                'id_cuenta' => $id_cuenta,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            );

            foreach ($metrics as $m) {
                $found = 0;
                foreach ($insights['data'] as $entry) {
                    if ($entry['name'] === $m && isset($entry['values'][0]['value'])) {
                        $val = $entry['values'][0]['value'];
                        $found = is_array($val) ? 0 : $val;
                        break;
                    }
                }
                $values[$m] = $found;
            }

            upsertUnique($pdo, 'insights_fb', ['id_insights_fb', 'id_cuenta'], $values);

            $posts = apiGet("https://graph.facebook.com/$apiVersion/$fbPageId/posts?fields=id,message,created_time,reactions.type(LIKE).limit(0).summary(true).as(like),reactions.type(LOVE).limit(0).summary(true).as(love),reactions.type(WOW).limit(0).summary(true).as(wow),reactions.type(HAHA).limit(0).summary(true).as(haha),reactions.type(ANGRY).limit(0).summary(true).as(anger),reactions.type(SAD).limit(0).summary(true).as(sorry),comments.limit(0).summary(true),shares&limit=10&access_token=$accessToken");
            foreach ($posts['data'] as $post) {
                upsertUnique($pdo, 'post_fb', ['id', 'id_cuenta'], array(
                    'id' => $post['id'],
                    'message' => isset($post['message']) ? $post['message'] : '',
                    'created_time' => date('Y-m-d H:i:s', strtotime($post['created_time'])),
                    'like' => isset($post['like']['summary']['total_count']) ? $post['like']['summary']['total_count'] : 0,
                    'love' => isset($post['love']['summary']['total_count']) ? $post['love']['summary']['total_count'] : 0,
                    'wow' => isset($post['wow']['summary']['total_count']) ? $post['wow']['summary']['total_count'] : 0,
                    'haha' => isset($post['haha']['summary']['total_count']) ? $post['haha']['summary']['total_count'] : 0,
                    'anger' => isset($post['anger']['summary']['total_count']) ? $post['anger']['summary']['total_count'] : 0,
                    'sorry' => isset($post['sorry']['summary']['total_count']) ? $post['sorry']['summary']['total_count'] : 0,
                    'comments' => isset($post['comments']['summary']['total_count']) ? $post['comments']['summary']['total_count'] : 0,
                    'shares' => isset($post['shares']['count']) ? $post['shares']['count'] : 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id_cuenta' => $id_cuenta
                ));
            }
        }

        if ($igUserId !== '-' && is_numeric($igUserId)) {
            $ig = apiGet("https://graph.facebook.com/$apiVersion/$igUserId?fields=username,followers_count,media_count&access_token=$accessToken");
            upsertUnique($pdo, 'seguidores_ig', ['id_seguidores_ig', 'id_cuenta'], array(
                'id_seguidores_ig' => date('Ymd'),
                'followers_count' => $ig['followers_count'],
                'media_count' => $ig['media_count'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id_cuenta' => $id_cuenta
            ));

            $metrics = ['reach', 'profile_views', 'views', 'likes', 'comments', 'shares', 'saves'];
            $insights = apiGet("https://graph.facebook.com/$apiVersion/$igUserId/insights?metric=" . implode(',', $metrics) . "&metric_type=total_value&period=day&access_token=$accessToken");

            $values = array(
                'id_insights_ig' => date('Ymd'),
                'id_cuenta' => $id_cuenta,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            );

            foreach ($metrics as $m) {
                $val = 0;
                foreach ($insights['data'] as $entry) {
                    if ($entry['name'] === $m && isset($entry['total_value']['value'])) {
                        $val = $entry['total_value']['value'];
                        break;
                    }
                }
                $values[$m] = $val;
            }

            upsertUnique($pdo, 'insights_ig', ['id_insights_ig', 'id_cuenta'], $values);
        }

        if ($adsAccountId !== '-') {
            foreach (array('ad', 'adset', 'campaign') as $level) {
                $insights = apiGet("https://graph.facebook.com/$apiVersion/$adsAccountId/insights?level=$level&fields={$level}_id,{$level}_name,impressions,reach,clicks,spend,cpc,cpm,ctr,cost_per_result,date_start&date_preset=yesterday&access_token=$accessToken");

                foreach ($insights['data'] as $row) {
                    $rowData = array(
                        "{$level}_id" => $row["{$level}_id"],
                        "{$level}_name" => isset($row["{$level}_name"]) ? $row["{$level}_name"] : '',
                        'impressions' => $row['impressions'],
                        'reach' => $row['reach'],
                        'clicks' => $row['clicks'],
                        'spend' => $row['spend'],
                        'cpc' => $row['cpc'],
                        'cpm' => $row['cpm'],
                        'ctr' => $row['ctr'],
                        'cost_per_result' => extractCost($row),
                        'date_start' => $row['date_start'],
                        'date_end' => $row['date_start'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id_cuenta' => $id_cuenta
                    );
                    $table = "{$level}s_insights_fb";
                    $uniqueKeys = array("{$level}_id", 'id_cuenta', 'date_start');
                    upsertUnique($pdo, $table, $uniqueKeys, $rowData);
                }
            }
        }
    }
} catch (Exception $e) {
    logMsg("❌ Error: " . $e->getMessage());
}

echo json_encode(['resultados' => $resultados]);
