<?php
header('Content-Type: application/json');
set_time_limit(300);
require_once 'db_connection.php';

// Protección con token simple
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
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) throw new Exception("HTTP {$httpCode} - $url - $response");
    return json_decode($response, true);
}

function insertOrUpdate($pdo, $table, $columns, $values, $dateField, $id_cuenta) {
    $date = date('Y-m-d');
    $check = $pdo->prepare("SELECT * FROM $table WHERE DATE($dateField) = ? AND id_cuenta = ?");
    $check->execute([$date, $id_cuenta]);
    $exists = $check->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        $changed = false;
        if (isset($columns) && is_array($columns)) {
            foreach ($columns as $i => $col) {
                if ((string)$exists[$col] !== (string)$values[$i]) {
                    $changed = true;
                    break;
                }
            }
        }
        if ($changed) {
            $set = implode(', ', array_map(function($c) { return "$c = ?"; }, $columns)) . ", updated_at = NOW()";
            $sql = "UPDATE $table SET $set WHERE DATE($dateField) = ? AND id_cuenta = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($values, [$date, $id_cuenta]));
            logMsg("🔄 Actualizado $table");
        } else {
            logMsg("ℹ️ Sin cambios en $table");
        }
    } else {
        $cols = implode(', ', $columns) . ', created_at, updated_at, id_cuenta';
        $marks = rtrim(str_repeat('?, ', count($columns)), ', ') . ', NOW(), NOW(), ?';
        $sql = "INSERT INTO $table ($cols) VALUES ($marks)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($values, [$id_cuenta]));
        logMsg("✅ Insertado en $table");
    }
}

function extractCost($entry) {
    return $entry['cost_per_result'][0]['values'][0]['value'] ?? 0;
}

try {
    $stmt = $pdo->query("SELECT * FROM cuenta");
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($cuentas) && is_array($cuentas)) {
        foreach ($cuentas as $cuenta) {
            $id_cuenta = $cuenta['id_cuenta'];
            $fbPageId = $cuenta['fb_page_id'];
            $igUserId = $cuenta['ig_user_id'];
            $adsAccountId = $cuenta['ads_account_id'];
            $accessToken = $cuenta['access_token'];

            if ($accessToken === '-' || empty($accessToken)) {
                logMsg("⏩ Saltando cuenta $id_cuenta (sin token)");
                continue;
            }

            logMsg("🟡 Procesando cuenta $id_cuenta");

            if ($fbPageId !== '-') {
                $fbUrl = "https://graph.facebook.com/$apiVersion/$fbPageId?fields=name,followers_count&access_token=$accessToken";
                $fbData = apiGet($fbUrl);
                insertOrUpdate($pdo, 'seguidores_fb', ['followers_count'], [$fbData['followers_count']], 'created_at', $id_cuenta);

                $metricsFb = ['page_impressions', 'page_fans', 'page_post_engagements', 'page_video_views_paid', 'page_video_views_organic', 'page_actions_post_reactions_total'];
                $urlFbInsights = "https://graph.facebook.com/$apiVersion/$fbPageId/insights?metric=" . implode(',', $metricsFb) . "&period=day&access_token=$accessToken";
                $fbInsights = apiGet($urlFbInsights);
                $fbValues = array_fill_keys($metricsFb, 0);
                if (isset($fbInsights['data']) && is_array($fbInsights['data'])) {
                    foreach ($fbInsights['data'] as $item) {
                        if (isset($item['name'], $item['values'][0]['value'])) {
                            $fbValues[$item['name']] = is_array($item['values'][0]['value']) && isset($item['values'][0]['value']['like'])
                                ? $item['values'][0]['value']['like']
                                : (is_numeric($item['values'][0]['value']) ? $item['values'][0]['value'] : 0);
                        }
                    }
                }
                insertOrUpdate($pdo, 'insights_fb', array_keys($fbValues), array_values($fbValues), 'created_at', $id_cuenta);

                $fields = 'id,message,created_time,reactions.type(LIKE).limit(0).summary(true).as(like)'
                        . ',reactions.type(LOVE).limit(0).summary(true).as(love)'
                        . ',reactions.type(WOW).limit(0).summary(true).as(wow)'
                        . ',reactions.type(HAHA).limit(0).summary(true).as(haha)'
                        . ',reactions.type(ANGRY).limit(0).summary(true).as(anger)'
                        . ',reactions.type(SAD).limit(0).summary(true).as(sorry)'
                        . ',comments.limit(0).summary(true),shares';
                $urlPosts = "https://graph.facebook.com/$apiVersion/$fbPageId/posts?fields=$fields&limit=10&access_token=$accessToken";
                $postsData = apiGet($urlPosts);

                if (isset($postsData['data']) && is_array($postsData['data'])) {
                    foreach ($postsData['data'] as $post) {
                        $idPost = $post['id'];
                        $message = $post['message'] ?? '';
                        $createdTime = date('Y-m-d H:i:s', strtotime($post['created_time']));
                        $likes = $post['like']['summary']['total_count'] ?? 0;
                        $loves = $post['love']['summary']['total_count'] ?? 0;
                        $wows  = $post['wow']['summary']['total_count'] ?? 0;
                        $hahas = $post['haha']['summary']['total_count'] ?? 0;
                        $angers = $post['anger']['summary']['total_count'] ?? 0;
                        $sorries = $post['sorry']['summary']['total_count'] ?? 0;
                        $comments = $post['comments']['summary']['total_count'] ?? 0;
                        $shares = $post['shares']['count'] ?? 0;

                        $stmt = $pdo->prepare("SELECT * FROM post_fb WHERE id = ? AND id_cuenta = ?");
                        $stmt->execute([$idPost, $id_cuenta]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$existing) {
                            $stmt = $pdo->prepare("INSERT INTO post_fb 
                                (id, message, created_time, `like`, love, wow, haha, anger, sorry, comments, shares, created_at, updated_at, id_cuenta) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
                            $stmt->execute([
                                $idPost, $message, $createdTime, $likes, $loves, $wows, $hahas,
                                $angers, $sorries, $comments, $shares, $id_cuenta
                            ]);
                            logMsg("✅ Insertado post: $idPost");
                        } elseif (
                            $existing['like'] != $likes || $existing['love'] != $loves ||
                            $existing['wow'] != $wows || $existing['haha'] != $hahas ||
                            $existing['anger'] != $angers || $existing['sorry'] != $sorries ||
                            $existing['comments'] != $comments || $existing['shares'] != $shares
                        ) {
                            $stmt = $pdo->prepare("UPDATE post_fb SET 
                                message = ?, `like` = ?, love = ?, wow = ?, haha = ?, anger = ?, sorry = ?, 
                                comments = ?, shares = ?, updated_at = NOW() 
                                WHERE id = ? AND id_cuenta = ?");
                            $stmt->execute([
                                $message, $likes, $loves, $wows, $hahas, $angers, $sorries,
                                $comments, $shares, $idPost, $id_cuenta
                            ]);
                            logMsg("🔄 Actualizado post: $idPost");
                        } else {
                            logMsg("ℹ️ Post $idPost sin cambios");
                        }
                    }
                }
            }

            // Instagram Perfil
            if (!empty($igUserId) && $igUserId !== '-' && is_numeric($igUserId)) {
                $igUrl = "https://graph.facebook.com/$apiVersion/$igUserId?fields=username,followers_count,media_count&access_token=$accessToken";
                $igProfile = apiGet($igUrl);
                insertOrUpdate($pdo, 'seguidores_ig', ['followers_count', 'media_count'], [$igProfile['followers_count'], $igProfile['media_count']], 'created_at', $id_cuenta);
            }

            // Instagram Insights
            if (!empty($igUserId) && $igUserId !== '-' && is_numeric($igUserId)) {
                $metricsIg = ['reach', 'profile_views', 'views', 'likes', 'comments', 'shares', 'saves'];
                $urlIgInsights = "https://graph.facebook.com/$apiVersion/$igUserId/insights?metric=" . implode(',', $metricsIg) . "&metric_type=total_value&period=day&access_token=$accessToken";
                $igInsightsData = apiGet($urlIgInsights);
                $igValues = array_fill_keys($metricsIg, 0);
                if (isset($igInsightsData['data']) && is_array($igInsightsData['data'])) {
                    foreach ($igInsightsData['data'] as $metric) {
                        $igValues[$metric['name']] = $metric['total_value']['value'] ?? 0;
                    }
                }
                insertOrUpdate($pdo, 'insights_ig', array_keys($igValues), array_values($igValues), 'created_at', $id_cuenta);
            }

            // Ads Insights
            if ($adsAccountId !== '-') {
                foreach (['ad', 'adset', 'campaign'] as $level) {
                    $url = "https://graph.facebook.com/$apiVersion/$adsAccountId/insights?level=$level&fields={$level}_id,{$level}_name,impressions,reach,clicks,spend,cpc,cpm,ctr,cost_per_result,date_start&date_preset=yesterday&access_token=$accessToken";
                    $insights = apiGet($url);
                    $table = $level . 's_insights_fb';
                    $id_field = $level . '_id';
                    $name_field = $level . '_name';

                    if (isset($insights['data']) && is_array($insights['data'])) {
                        foreach ($insights['data'] as $row) {
                            $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE $id_field = ? AND date_start = ? AND id_cuenta = ?");
                            $stmt->execute([$row[$id_field], $row['date_start'], $id_cuenta]);
                            if ($stmt->fetch()) continue;

                            $stmt = $pdo->prepare("INSERT INTO $table ($id_field, $name_field, impressions, reach, clicks, spend, cpc, cpm, ctr, cost_per_result, date_start, created_at, updated_at, id_cuenta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
                            $stmt->execute([
                                $row[$id_field], $row[$name_field] ?? '', $row['impressions'] ?? 0,
                                $row['reach'] ?? 0, $row['clicks'] ?? 0, $row['spend'] ?? 0,
                                $row['cpc'] ?? 0, $row['cpm'] ?? 0, $row['ctr'] ?? 0,
                                extractCost($row), $row['date_start'], $id_cuenta
                            ]);
                            logMsg("✅ Insertado $level: {$row[$id_field]}");
                        }
                    }
                }
            }
        }
    }

} catch (Exception $e) {
    logMsg("❌ Error: " . $e->getMessage());
}

echo json_encode(['resultados' => $resultados]);
