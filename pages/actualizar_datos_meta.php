<?php
require_once 'db_connection.php';

date_default_timezone_set('America/Lima');

// === Logging y bloqueo ===
$logFile = __DIR__ . '/log_meta.txt';
$log = function ($msg) use ($logFile) {
    $fecha = date('[Y-m-d H:i:s] ');
    file_put_contents($logFile, $fecha . $msg . PHP_EOL, FILE_APPEND);
};

$log("==== INICIO de ejecución ====");

// Prevenir ejecución simultánea
$lockFile = fopen(__DIR__ . '/meta.lock', 'c');
if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
    $log("⚠️ Ya hay una ejecución en curso. Abortando.");
    exit;
}

// Validar token
if (!isset($_GET['token']) || $_GET['token'] !== 'mi_token_secreto123') {
    $log("❌ Token inválido o ausente.");
    http_response_code(403);
    exit('Acceso no autorizado');
}

// Obtener cuentas
$stmt = $pdo->query("SELECT * FROM cuenta");
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === FUNCIONES auxiliares
function upsertUnique($pdo, $tabla, $datos, $clavesUnicas, $log)
{
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

    if ($existe) return;

    $columnas = array_map(function($col){ return "`$col`"; }, array_keys($datos));
    $placeholders = array_map(function($col){ return ":$col"; }, array_keys($datos));
    $sqlInsert = "INSERT INTO `$tabla` (" . implode(',', $columnas) . ") VALUES (" . implode(',', $placeholders) . ")";

    $stmtInsert = $pdo->prepare($sqlInsert);
    foreach ($datos as $col => $val) {
        $stmtInsert->bindValue(":$col", $val);
    }

    try {
        $stmtInsert->execute();
    } catch (Exception $e) {
        $log("❌ Error al insertar en $tabla: " . $e->getMessage());
    }
}

// === PROCESAR CADA CUENTA ===
foreach ($cuentas as $cuenta) {
    $idCuenta = $cuenta['id'];
    $token = $cuenta['access_token'];

    $log("Procesando cuenta ID: $idCuenta");

    try {
        // Facebook followers
        $urlSeguidores = "https://graph.facebook.com/v19.0/{$cuenta['page_id']}?fields=followers_count&access_token=$token";
        $resp = json_decode(file_get_contents($urlSeguidores), true);
        if (isset($resp['followers_count'])) {
            upsertUnique($pdo, 'seguidores_fb', [
                'id_seguidores_fb' => date('Y-m-d'),
                'id_cuenta' => $idCuenta,
                'seguidores' => $resp['followers_count']
            ], ['id_seguidores_fb', 'id_cuenta'], $log);
        }

        // Facebook insights
        $urlInsights = "https://graph.facebook.com/v19.0/{$cuenta['page_id']}/insights?metric=page_impressions,page_views_total,page_posts_impressions,page_actions_post_reactions_total,page_post_engagements&date_preset=today&access_token=$token";
        $insights = json_decode(file_get_contents($urlInsights), true);
        $datos = [
            'id_insights_fb' => date('Y-m-d'),
            'id_cuenta' => $idCuenta,
            'page_impressions' => 0,
            'page_views_total' => 0,
            'page_posts_impressions' => 0,
            'total_interactions' => 0,
            'comments' => 0,
            'shares' => 0,
            'saves' => 0,
            'created' => date('Y-m-d')
        ];

        foreach ($insights['data'] as $metric) {
            $name = $metric['name'];
            $value = $metric['values'][0]['value'];
            if ($name === 'page_impressions') $datos['page_impressions'] = $value;
            if ($name === 'page_views_total') $datos['page_views_total'] = $value;
            if ($name === 'page_posts_impressions') $datos['page_posts_impressions'] = $value;
            if ($name === 'page_post_engagements') $datos['total_interactions'] = $value;
            if ($name === 'page_actions_post_reactions_total' && is_array($value)) {
                foreach ($value as $key => $val) {
                    $col = strtolower($key);
                    $datos[$col] = $val;
                }
            }
        }

        upsertUnique($pdo, 'insights_fb', $datos, ['id_insights_fb', 'id_cuenta'], $log);

        // Instagram followers
        if ($cuenta['ig_user_id']) {
            $urlIG = "https://graph.facebook.com/v19.0/{$cuenta['ig_user_id']}?fields=followers_count,media_count&access_token=$token";
            $respIG = json_decode(file_get_contents($urlIG), true);
            if (isset($respIG['followers_count'])) {
                upsertUnique($pdo, 'seguidores_ig', [
                    'id_seguidores_ig' => date('Y-m-d'),
                    'id_cuenta' => $idCuenta,
                    'seguidores' => $respIG['followers_count']
                ], ['id_seguidores_ig', 'id_cuenta'], $log);
            }

            $urlIGInsights = "https://graph.facebook.com/v19.0/{$cuenta['ig_user_id']}/insights?metric=impressions,reach,profile_views&period=day&access_token=$token";
            $insightsIG = json_decode(file_get_contents($urlIGInsights), true);
            $datosIG = [
                'id_insights_ig' => date('Y-m-d'),
                'id_cuenta' => $idCuenta,
                'created' => date('Y-m-d')
            ];
            foreach ($insightsIG['data'] as $metric) {
                $datosIG[$metric['name']] = $metric['values'][0]['value'];
            }
            upsertUnique($pdo, 'insights_ig', $datosIG, ['id_insights_ig', 'id_cuenta'], $log);
        }

        $log("✅ Cuenta $idCuenta actualizada correctamente");
    } catch (Exception $e) {
        $log("❌ Error al procesar cuenta $idCuenta: " . $e->getMessage());
    }
}

// Finalizar ejecución
flock($lockFile, LOCK_UN);
fclose($lockFile);
$log("==== FIN de ejecución ====\n");
