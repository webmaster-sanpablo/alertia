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
function upsertUnique($pdo, $tabla, $datos, $clavesUnicas, $log) {
    $condiciones = [];
    $params = [];
    foreach ($clavesUnicas as $clave) {
        $condiciones[] = "`$clave` = :$clave";
        $params[":$clave"] = $datos[$clave];
    }

    $sqlCheck = "SELECT COUNT(*) FROM `$tabla` WHERE " . implode(' AND ', $condiciones);
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($params);
    $existe = $stmtCheck->fetchColumn();

    if ($existe) {
        $log("ℹ️ Datos ya existen en $tabla, omitiendo inserción");
        return;
    }

    $columnas = array_map(function($col) { return "`$col`"; }, array_keys($datos));
    $placeholders = array_map(function($col) { return ":$col"; }, array_keys($datos));
    $sqlInsert = "INSERT INTO `$tabla` (" . implode(',', $columnas) . ") VALUES (" . implode(',', $placeholders) . ")";

    $stmtInsert = $pdo->prepare($sqlInsert);
    foreach ($datos as $col => $val) {
        $stmtInsert->bindValue(":$col", $val);
    }

    try {
        $stmtInsert->execute();
        $log("✓ Datos insertados en $tabla");
    } catch (Exception $e) {
        $log("❌ Error al insertar en $tabla: " . $e->getMessage());
    }
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
                'id_seguidores_fb' => date('Y-m-d'),
                'followers_count' => $resp['followers_count'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id_cuenta' => $idCuenta
            ], ['id_seguidores_fb', 'id_cuenta'], $log);
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
            'id_insights_fb' => date('Y-m-d'),
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

        upsertUnique($pdo, 'insights_fb', $datosInsights, ['id_insights_fb', 'id_cuenta'], $log);

        // === 3. Obtener datos de Instagram (si tiene ig_user_id) ===
        if (!empty($igUserId)) {
            $urlIG = "https://graph.facebook.com/v19.0/{$igUserId}?fields=followers_count,media_count&access_token=$token";
            $respIG = @file_get_contents($urlIG);
            
            if ($respIG !== false) {
                $igData = json_decode($respIG, true);
                
                if (!isset($igData['error']) && isset($igData['followers_count'])) {
                    upsertUnique($pdo, 'seguidores_ig', [
                        'id_seguidores_ig' => date('Y-m-d'),
                        'followers_count' => $igData['followers_count'],
                        'media_count' => $igData['media_count'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id_cuenta' => $idCuenta
                    ], ['id_seguidores_ig', 'id_cuenta'], $log);
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
                    
                    upsertUnique($pdo, 'insights_ig', $datosIG, ['id_insights_ig', 'id_cuenta'], $log);
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