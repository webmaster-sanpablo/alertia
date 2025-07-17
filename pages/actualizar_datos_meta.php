<?php
require_once 'db_connection.php';

date_default_timezone_set('America/Lima');

// === Configuración de logging ===
$logFile = __DIR__ . '/log_meta.txt';
$log = function ($msg) use ($logFile) {
    $fecha = date('[Y-m-d H:i:s] ');
    file_put_contents($logFile, $fecha . $msg . PHP_EOL, FILE_APPEND);
};

$log("==== INICIO de ejecución ====");

// === Bloqueo para evitar ejecuciones simultáneas ===
$lockFile = fopen(__DIR__ . '/meta.lock', 'c');
if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
    $log("⚠️ Ya hay una ejecución en curso. Abortando.");
    exit;
}

// === Validación de token de seguridad ===
if (!isset($_GET['token']) || $_GET['token'] !== 'mi_token_secreto123') {
    $log("❌ Token inválido o ausente.");
    http_response_code(403);
    exit('Acceso no autorizado');
}

// === Obtener cuentas activas ===
try {
    $stmt = $pdo->query("SELECT 
                        id_cuenta, 
                        fb_page_id, 
                        fb_page_name, 
                        ig_user_id, 
                        ig_username, 
                        ads_account_id,
                        access_token 
                    FROM cuenta 
                    WHERE fb_page_id IS NOT NULL 
                    AND access_token IS NOT NULL");
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cuentas)) {
        $log("⚠️ No hay cuentas válidas para procesar (faltan fb_page_id o access_token)");
        exit;
    }
} catch (Exception $e) {
    $log("❌ Error al obtener cuentas: " . $e->getMessage());
    exit;
}

// === Función para insertar o actualizar datos únicos ===
function upsertUnique($pdo, $tabla, $data, $clavesUnicas, $log = null) {
    // Validación de claves únicas
    foreach ($clavesUnicas as $clave) {
        if (!isset($data[$clave])) {
            $msg = "⚠️ Falta clave '$clave' en datos para tabla $tabla";
            if (is_callable($log)) call_user_func($log, $msg); else echo $msg . "\n";
            return;
        }
    }

    // WHERE para verificar existencia
    $where = array();
    $params = array();
    foreach ($clavesUnicas as $clave) {
        $where[] = "`$clave` = :$clave";
        $params[":$clave"] = $data[$clave];
    }

    $sql_check = "SELECT COUNT(*) FROM `$tabla` WHERE " . implode(" AND ", $where);
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($params);
    $existe = $stmt_check->fetchColumn() > 0;

    $campos = array_keys($data);
    $placeholders = array();
    foreach ($campos as $c) {
        $placeholders[] = ":$c";
    }

    if ($existe) {
        // UPDATE
        $sets = array();
        foreach ($campos as $campo) {
            if (!in_array($campo, $clavesUnicas)) {
                $sets[] = "`$campo` = :$campo";
            }
        }

        if (empty($sets)) return;

        $sql_update = "UPDATE `$tabla` SET " . implode(", ", $sets) . " WHERE " . implode(" AND ", $where);
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute($data);
        $clavesTexto = array();
        foreach ($clavesUnicas as $k) {
            $clavesTexto[] = "$k={$data[$k]}";
        }
        $msg = "🔁 Actualizado en $tabla [" . implode(', ', $clavesTexto) . "]";
    } else {
        // INSERT
        $sql_insert = "INSERT INTO `$tabla` (`" . implode("`, `", $campos) . "`) VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute($data);
        $clavesTexto = array();
        foreach ($clavesUnicas as $k) {
            $clavesTexto[] = "$k={$data[$k]}";
        }
        $msg = "✅ Insertado en $tabla [" . implode(', ', $clavesTexto) . "]";
    }

    if (is_callable($log)) call_user_func($log, $msg); else echo $msg . "\n";
}

// === Procesar cada cuenta ===
foreach ($cuentas as $cuenta) {
    if (empty($cuenta['id_cuenta'])) {
        $log("⚠️ Cuenta sin id_cuenta, omitiendo");
        continue;
    }
    
    $idCuenta = $cuenta['id_cuenta'];
    $token = $cuenta['access_token'];
    $pageId = $cuenta['fb_page_id'];
    $igUserId = $cuenta['ig_user_id'] ?? null;

    $log("Procesando cuenta ID: $idCuenta, Página: {$cuenta['fb_page_name']} ($pageId)");

    try {
        // Validar datos mínimos
        if (empty($pageId) || empty($token)) {
            throw new Exception("Datos incompletos - fb_page_id o token vacíos");
        }

        // === 1. Obtener seguidores de Facebook ===
        $urlSeguidores = "https://graph.facebook.com/v19.0/{$pageId}?fields=followers_count&access_token=$token";
        $response = @file_get_contents($urlSeguidores);
        
        if ($response === false) {
            throw new Exception("Error al obtener seguidores de Facebook");
        }
        
        $resp = json_decode($response, true);
        
        if (isset($resp['error'])) {
            throw new Exception("Error de API Facebook: " . $resp['error']['message']);
        }
        
        if (isset($resp['followers_count'])) {
            upsertUnique($pdo, 'seguidores_fb', [
                'followers_count' => $resp['followers_count'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id_cuenta' => $idCuenta
            ], ['id_cuenta'], $log);
        }

        // === 2. Obtener insights de Facebook ===
        $urlInsights = "https://graph.facebook.com/v19.0/{$pageId}/insights?" . 
                      "metric=page_impressions,page_fans,page_post_engagements," .
                      "page_video_views,page_actions_post_reactions_total&" .
                      "date_preset=today&access_token=$token";
        
        $insightsResponse = @file_get_contents($urlInsights);
        
        if ($insightsResponse === false) {
            throw new Exception("Error al obtener insights de Facebook");
        }
        
        $insights = json_decode($insightsResponse, true);
        
        if (isset($insights['error'])) {
            throw new Exception("Error de API Insights: " . $insights['error']['message']);
        }
        
        // Preparar datos según estructura de tu tabla insights_fb
        $datosInsights = [
            'page_impressions' => 0,
            'page_fans' => 0,
            'page_post_engagements' => 0,
            'page_video_views_paid' => 0,
            'page_video_views_organic' => 0,
            'page_actions_post_reactions_total' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'id_cuenta' => $idCuenta
        ];

        if (isset($insights['data']) && is_array($insights['data'])) {
            foreach ($insights['data'] as $metric) {
                $name = $metric['name'];
                $value = $metric['values'][0]['value'] ?? 0;
                
                switch ($name) {
                    case 'page_impressions':
                        $datosInsights['page_impressions'] = $value;
                        break;
                    case 'page_fans':
                        $datosInsights['page_fans'] = $value;
                        break;
                    case 'page_post_engagements':
                        $datosInsights['page_post_engagements'] = $value;
                        break;
                    case 'page_video_views':
                        // Asumiendo que son vistas orgánicas
                        $datosInsights['page_video_views_organic'] = $value;
                        break;
                    case 'page_actions_post_reactions_total':
                        if (is_array($value)) {
                            $datosInsights['page_actions_post_reactions_total'] = array_sum($value);
                        } else {
                            $datosInsights['page_actions_post_reactions_total'] = $value;
                        }
                        break;
                }
            }
        }

        upsertUnique($pdo, 'insights_fb', $datosInsights, ['id_cuenta'], $log);

        // === 3. Obtener datos de Instagram (si tiene ig_user_id) ===
        if (!empty($igUserId)) {
            $urlIG = "https://graph.facebook.com/v19.0/{$igUserId}?fields=followers_count,media_count&access_token=$token";
            $respIG = @file_get_contents($urlIG);
            
            if ($respIG !== false) {
                $igData = json_decode($respIG, true);
                
                if (!isset($igData['error']) && isset($igData['followers_count'])) {
                    upsertUnique($pdo, 'seguidores_ig', [
                        'followers_count' => $igData['followers_count'],
                        'media_count' => $igData['media_count'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id_cuenta' => $idCuenta
                    ], ['id_cuenta'], $log);
                }
            }

            // Insights de Instagram
            $urlIGInsights = "https://graph.facebook.com/v19.0/{$igUserId}/insights?metric=impressions,reach,profile_views&period=day&access_token=$token";
            $insightsIG = @file_get_contents($urlIGInsights);
            
            if ($insightsIG !== false) {
                $igInsights = json_decode($insightsIG, true);
                
                if (!isset($igInsights['error']) && isset($igInsights['data'])) {
                    $datosIG = [
                        'id_insights_ig' => date('Y-m-d'),
                        'reach' => 0,
                        'profile_views' => 0,
                        'views' => 0,
                        'likes' => 0,
                        'comments' => 0,
                        'shares' => 0,
                        'saves' => 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id_cuenta' => $idCuenta
                    ];
                    
                    foreach ($igInsights['data'] as $metric) {
                        $metricName = $metric['name'];
                        $metricValue = $metric['values'][0]['value'] ?? 0;
                        
                        if (array_key_exists($metricName, $datosIG)) {
                            $datosIG[$metricName] = $metricValue;
                        }
                    }
                    
                    upsertUnique($pdo, 'insights_ig', $datosIG, ['id_cuenta'], $log);
                }
            }
        }

        // === 4. Obtener insights de campañas, adsets y ads (si tiene ads_account_id) ===
        if (!empty($cuenta['ads_account_id'])) {
            $adsAccountId = $cuenta['ads_account_id'];
            $fechaHoy = date('Y-m-d');

            $urlInsights = "https://graph.facebook.com/v19.0/act_{$adsAccountId}/insights?fields=campaign_id,campaign_name,adset_id,adset_name,ad_id,ad_name,impressions,reach,clicks,spend,cpc,cpm,ctr,cost_per_result,date_start,date_end&level=ad&time_increment=1&date_preset=today&access_token=$token";

            $responseInsights = @file_get_contents($urlInsights);
            if ($responseInsights !== false) {
                $json = json_decode($responseInsights, true);
                if (isset($json['data']) && is_array($json['data'])) {
                    foreach ($json['data'] as $fila) {
                        $common = [
                            'impressions' => $fila['impressions'] ?? 0,
                            'reach' => $fila['reach'] ?? 0,
                            'clicks' => $fila['clicks'] ?? 0,
                            'spend' => $fila['spend'] ?? 0,
                            'cpc' => $fila['cpc'] ?? 0,
                            'cpm' => $fila['cpm'] ?? 0,
                            'ctr' => $fila['ctr'] ?? 0,
                            'cost_per_result' => $fila['cost_per_result'] ?? 0,
                            'date_start' => $fila['date_start'] ?? $fechaHoy,
                            'date_end' => $fila['date_end'] ?? $fechaHoy,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'id_cuenta' => $idCuenta
                        ];

                        // Campañas
                        if (!empty($fila['campaign_id'])) {
                            upsertUnique($pdo, 'campaigns_insights_fb', array_merge($common, [
                                'campaign_id' => $fila['campaign_id'],
                                'campaign_name' => $fila['campaign_name'] ?? ''
                            ]), ['campaign_id', 'date_start', 'id_cuenta'], $log);
                        }

                        // Adsets
                        if (!empty($fila['adset_id'])) {
                            upsertUnique($pdo, 'adsets_insights_fb', array_merge($common, [
                                'adset_id' => $fila['adset_id'],
                                'adset_name' => $fila['adset_name'] ?? ''
                            ]), ['adset_id', 'date_start', 'id_cuenta'], $log);
                        }

                        // Ads
                        if (!empty($fila['ad_id'])) {
                            upsertUnique($pdo, 'ads_insights_fb', array_merge($common, [
                                'ad_id' => $fila['ad_id'],
                                'ad_name' => $fila['ad_name'] ?? ''
                            ]), ['ad_id', 'date_start', 'id_cuenta'], $log);
                        }
                    }
                }
            }
        }
        
        // === 5. Obtener publicaciones recientes de Facebook ===
        $urlPosts = "https://graph.facebook.com/v19.0/{$pageId}/posts?fields=id,message,created_time&limit=10&access_token=$token";
        $responsePosts = @file_get_contents($urlPosts);
        if ($responsePosts !== false) {
            $posts = json_decode($responsePosts, true);
            if (isset($posts['data'])) {
                foreach ($posts['data'] as $post) {
                    $postId = $post['id'];
                    $mensaje = $post['message'] ?? '';
                    $creado = $post['created_time'] ?? date('Y-m-d H:i:s');

                    // === 5.1 Obtener reacciones y estadísticas por publicación ===
                    $fields = "reactions.type(LIKE).summary(total_count).limit(0).as(like),
                            reactions.type(LOVE).summary(total_count).limit(0).as(love),
                            reactions.type(WOW).summary(total_count).limit(0).as(wow),
                            reactions.type(HAHA).summary(total_count).limit(0).as(haha),
                            reactions.type(ANGRY).summary(total_count).limit(0).as(anger),
                            reactions.type(SAD).summary(total_count).limit(0).as(sorry),
                            comments.summary(true),
                            shares";

                    $urlDetallePost = "https://graph.facebook.com/v19.0/{$postId}?fields=$fields&access_token=$token";
                    $detalle = @file_get_contents($urlDetallePost);
                    $detPost = $detalle ? json_decode($detalle, true) : [];

                    upsertUnique($pdo, 'post_fb', [
                        'id' => $postId,
                        'message' => $mensaje,
                        'created_time' => $creado,
                        'like' => $detPost['like']['summary']['total_count'] ?? 0,
                        'love' => $detPost['love']['summary']['total_count'] ?? 0,
                        'wow' => $detPost['wow']['summary']['total_count'] ?? 0,
                        'haha' => $detPost['haha']['summary']['total_count'] ?? 0,
                        'anger' => $detPost['anger']['summary']['total_count'] ?? 0,
                        'sorry' => $detPost['sorry']['summary']['total_count'] ?? 0,
                        'comments' => $detPost['comments']['summary']['total_count'] ?? 0,
                        'shares' => $detPost['shares']['count'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id_cuenta' => $idCuenta
                    ], ['id'], $log);
                }
            }
        }

        $log("✅ Cuenta $idCuenta actualizada correctamente");
    } catch (Exception $e) {
        $log("❌ Error al procesar cuenta $idCuenta: " . $e->getMessage());
        continue;
    }
}

// === Finalizar ejecución ===
flock($lockFile, LOCK_UN);
fclose($lockFile);
$log("==== FIN de ejecución ====\n");
?>