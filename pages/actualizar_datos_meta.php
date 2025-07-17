<?php
require_once 'db_connection.php';

function logMsg($msg) {
    global $resultados;
    $resultados[] = $msg;
}

// Función para insertar o actualizar evitando duplicados según claves únicas
function upsertUnique($pdo, $table, $keyFields, $data) {
    // WHERE
    $whereParts = array();
    foreach ($keyFields as $field) {
        $whereParts[] = "`$field` = ?";
    }
    $whereClause = implode(' AND ', $whereParts);

    $checkStmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE $whereClause");
    $checkValues = array();
    foreach ($keyFields as $field) {
        $checkValues[] = isset($data[$field]) ? $data[$field] : null;
    }
    $checkStmt->execute($checkValues);

    $allFields = array_keys($data);
    $setFields = array_diff($allFields, $keyFields);

    if ($checkStmt->fetch()) {
        if (count($setFields) > 0) {
            $setParts = array();
            foreach ($setFields as $field) {
                $setParts[] = "`$field` = ?";
            }
            $setClause = implode(', ', $setParts);

            $updateSql = "UPDATE `$table` SET $setClause WHERE $whereClause";
            $updateStmt = $pdo->prepare($updateSql);

            $updateValues = array();
            foreach ($setFields as $field) {
                $updateValues[] = $data[$field];
            }
            foreach ($keyFields as $field) {
                $updateValues[] = $data[$field];
            }

            $updateStmt->execute($updateValues);
        }
        logMsg("🔄 Actualizado $table");
    } else {
        $fieldsEscaped = array();
        $placeholders = array();
        $values = array();
        foreach ($data as $key => $val) {
            $fieldsEscaped[] = "`$key`";
            $placeholders[] = '?';
            $values[] = $val;
        }

        $insertSql = "INSERT INTO `$table` (" . implode(', ', $fieldsEscaped) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute($values);
        logMsg("✅ Insertado en $table");
    }
}

// ---------------------------
// INICIO DE PROCESAMIENTO
// ---------------------------
$resultados = array();

// Obtener cuentas activas con tokens
$stmt = $pdo->query("SELECT id_cuenta, access_token, page_id, ig_id FROM cuenta WHERE access_token IS NOT NULL AND estado = 1");
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cuentas as $cuenta) {
    $idCuenta = $cuenta['id_cuenta'];
    $token = $cuenta['access_token'];
    $pageId = $cuenta['page_id'];
    $igId = $cuenta['ig_id'];

    logMsg("🟡 Procesando cuenta $idCuenta");

    // -------------------------------
    // 1. Seguidores Facebook
    // -------------------------------
    if ($pageId) {
        $url = "https://graph.facebook.com/v19.0/{$pageId}?fields=followers_count&access_token={$token}";
        $json = @file_get_contents($url);
        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['followers_count'])) {
                upsertUnique($pdo, 'seguidores_fb', ['id_seguidores_fb', 'id_cuenta'], array(
                    'id_seguidores_fb' => date('Ymd'),
                    'id_cuenta' => $idCuenta,
                    'followers_count' => $data['followers_count'],
                    'fecha' => date('Y-m-d')
                ));
            }
        }
    }

    // -------------------------------
    // 2. Insights Facebook
    // -------------------------------
    if ($pageId) {
        $url = "https://graph.facebook.com/v19.0/{$pageId}/insights?metric=page_impressions,page_views_total,page_posts_impressions,page_actions_post_reactions_total,page_engaged_users,page_post_engagements,page_content_activity,post_saves,post_comments,post_shares&period=day&access_token={$token}";
        $json = @file_get_contents($url);
        if ($json) {
            $insights = json_decode($json, true);
            if (isset($insights['data'])) {
                $row = array(
                    'id_insights_fb' => date('Ymd'),
                    'id_cuenta' => $idCuenta,
                    'fecha' => date('Y-m-d'),
                    'page_impressions' => 0,
                    'page_views_total' => 0,
                    'page_posts_impressions' => 0,
                    'total_interactions' => 0,
                    'comments' => 0,
                    'shares' => 0,
                    'saves' => 0
                );

                foreach ($insights['data'] as $metric) {
                    $name = $metric['name'];
                    $value = isset($metric['values'][0]['value']) ? $metric['values'][0]['value'] : 0;

                    if ($name == 'page_impressions') $row['page_impressions'] = $value;
                    elseif ($name == 'page_views_total') $row['page_views_total'] = $value;
                    elseif ($name == 'page_posts_impressions') $row['page_posts_impressions'] = $value;
                    elseif ($name == 'post_comments') $row['comments'] = $value;
                    elseif ($name == 'post_shares') $row['shares'] = $value;
                    elseif ($name == 'post_saves') $row['saves'] = $value;
                    elseif ($name == 'page_actions_post_reactions_total' && is_array($value)) {
                        foreach ($value as $reaction => $count) {
                            $row[$reaction] = $count;
                            $row['total_interactions'] += $count;
                        }
                    }
                }

                upsertUnique($pdo, 'insights_fb', ['id_insights_fb', 'id_cuenta'], $row);
            }
        }
    }

    // -------------------------------
    // 3. Posts Facebook
    // -------------------------------
    if ($pageId) {
        $url = "https://graph.facebook.com/v19.0/{$pageId}/posts?fields=id,message,created_time,insights.metric(post_impressions,post_engaged_users,post_reactions_by_type_total,post_comments,post_shares)&access_token={$token}";
        $json = @file_get_contents($url);
        if ($json) {
            $posts = json_decode($json, true);
            if (isset($posts['data'])) {
                foreach ($posts['data'] as $post) {
                    $postData = array(
                        'id' => $post['id'],
                        'id_cuenta' => $idCuenta,
                        'message' => isset($post['message']) ? $post['message'] : '',
                        'created' => date('Y-m-d H:i:s', strtotime($post['created_time'])),
                        'impressions' => 0,
                        'interactions' => 0,
                        'comments' => 0,
                        'shares' => 0,
                        'saves' => 0
                    );

                    if (isset($post['insights']['data'])) {
                        foreach ($post['insights']['data'] as $insight) {
                            $name = $insight['name'];
                            $val = isset($insight['values'][0]['value']) ? $insight['values'][0]['value'] : 0;
                            if ($name == 'post_impressions') $postData['impressions'] = $val;
                            elseif ($name == 'post_engaged_users') $postData['interactions'] = $val;
                            elseif ($name == 'post_comments') $postData['comments'] = $val;
                            elseif ($name == 'post_shares') $postData['shares'] = $val;
                            elseif ($name == 'post_reactions_by_type_total' && is_array($val)) {
                                foreach ($val as $reaction => $count) {
                                    $postData[$reaction] = $count;
                                }
                            }
                        }
                    }

                    upsertUnique($pdo, 'post_fb', ['id'], $postData);
                }
            }
        }
    }

    // -------------------------------
    // 4. Seguidores Instagram
    // -------------------------------
    if ($igId) {
        $url = "https://graph.facebook.com/v19.0/{$igId}?fields=followers_count&access_token={$token}";
        $json = @file_get_contents($url);
        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['followers_count'])) {
                upsertUnique($pdo, 'seguidores_ig', ['id_seguidores_ig', 'id_cuenta'], array(
                    'id_seguidores_ig' => date('Ymd'),
                    'id_cuenta' => $idCuenta,
                    'seguidores' => $data['followers_count'],
                    'fecha' => date('Y-m-d')
                ));
            }
        }
    }

    // -------------------------------
    // 5. Insights Instagram
    // -------------------------------
    if ($igId) {
        $url = "https://graph.facebook.com/v19.0/{$igId}/insights?metric=impressions,reach,profile_views&period=day&access_token={$token}";
        $json = @file_get_contents($url);
        if ($json) {
            $insights = json_decode($json, true);
            if (isset($insights['data'])) {
                $row = array(
                    'id_insights_ig' => date('Ymd'),
                    'id_cuenta' => $idCuenta,
                    'fecha' => date('Y-m-d'),
                    'reach' => 0,
                    'impressions' => 0,
                    'profile_views' => 0
                );

                foreach ($insights['data'] as $metric) {
                    $name = $metric['name'];
                    $val = isset($metric['values'][0]['value']) ? $metric['values'][0]['value'] : 0;
                    if ($name == 'impressions') $row['impressions'] = $val;
                    elseif ($name == 'reach') $row['reach'] = $val;
                    elseif ($name == 'profile_views') $row['profile_views'] = $val;
                }

                upsertUnique($pdo, 'insights_ig', ['id_insights_ig', 'id_cuenta'], $row);
            }
        }
    }

    // -------------------------------
    // 6-8. Ads Insights (ad, adset, campaign)
    // -------------------------------
    $levels = array('ad', 'adset', 'campaign');
    foreach ($levels as $level) {
        $url = "https://graph.facebook.com/v19.0/act_{$pageId}/insights?level=$level&fields=impressions,reach,clicks,spend,cpc,cpm,ctr,cost_per_result,ad_id,ad_name,campaign_id,campaign_name,adset_id,adset_name,date_start,date_stop&access_token={$token}";
        $json = @file_get_contents($url);
        if ($json) {
            $ads = json_decode($json, true);
            if (isset($ads['data'])) {
                foreach ($ads['data'] as $ad) {
                    $common = array(
                        'id_cuenta' => $idCuenta,
                        'impressions' => $ad['impressions'] ?? 0,
                        'reach' => $ad['reach'] ?? 0,
                        'clicks' => $ad['clicks'] ?? 0,
                        'spend' => $ad['spend'] ?? 0,
                        'cpc' => $ad['cpc'] ?? 0,
                        'cpm' => $ad['cpm'] ?? 0,
                        'ctr' => $ad['ctr'] ?? 0,
                        'date_start' => $ad['date_start'],
                        'date_stop' => $ad['date_stop'],
                        'cost_per_result' => isset($ad['cost_per_result']['values'][0]['value']) ? $ad['cost_per_result']['values'][0]['value'] : 0
                    );

                    if ($level == 'ad') {
                        $common['ad_id'] = $ad['ad_id'];
                        $common['ad_name'] = $ad['ad_name'] ?? '';
                        upsertUnique($pdo, 'ads_insights_fb', ['ad_id'], $common);
                    } elseif ($level == 'adset') {
                        $common['adset_id'] = $ad['adset_id'];
                        $common['adset_name'] = $ad['adset_name'] ?? '';
                        upsertUnique($pdo, 'adsets_insights_fb', ['adset_id'], $common);
                    } elseif ($level == 'campaign') {
                        $common['campaign_id'] = $ad['campaign_id'];
                        $common['campaign_name'] = $ad['campaign_name'] ?? '';
                        upsertUnique($pdo, 'campaigns_insights_fb', ['campaign_id'], $common);
                    }
                }
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode(array('resultados' => $resultados));
