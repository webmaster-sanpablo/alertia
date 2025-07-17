<?php
header('Content-Type: application/json');
$endpoint = $_GET['endpoint'] ?? '';
$id_cuenta = isset($_GET['id_cuenta']) ? (int)$_GET['id_cuenta'] : null;
$host = '192.1.0.239';
$bbdd   = 'alertia';
$user = 'alertia';
$pass = 'Casita123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$bbdd;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa prepared statements nativos si es posible
];

try {
    $db = new PDO($dsn, $user, $pass, $options);
    // echo 'Conectado con éxito a MySQL';
} catch (PDOException $e) {
    die('❌ Error de conexión con MySQL: ' . $e->getMessage());
}

function dias_esp() {
    return ['Sun'=>'Dom', 'Mon'=>'Lun', 'Tue'=>'Mar', 'Wed'=>'Mié', 'Thu'=>'Jue', 'Fri'=>'Vie', 'Sat'=>'Sáb'];
}

function getLatestMetric($db, $table, $field, $id_cuenta = null, $order = 'created_at') {
    $sql = "SELECT $field FROM $table";
    if ($id_cuenta !== null) {
        $sql .= " WHERE id_cuenta = :id_cuenta";
    }
    $sql .= " ORDER BY $order DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $id_cuenta !== null ? $stmt->execute(['id_cuenta' => $id_cuenta]) : $stmt->execute();
    return $stmt->fetchColumn();
}

function sumMetric(PDO $pdo, string $metric, string $platform = 'ig', ?int $id_cuenta = null): int {
    $map = $platform === 'ig' ? [
        'reach' => ['insights_ig', 'reach'],
        'views' => ['insights_ig', 'views'],
        'profile_views' => ['insights_ig', 'profile_views'],
        'likes' => ['insights_ig', 'likes'],
        'comments' => ['insights_ig', 'comments'],
        'shares' => ['insights_ig', 'shares'],
        'saves' => ['insights_ig', 'saves'],
    ] : [
        'reach' => ['insights_fb', 'page_impressions'],
        'likes' => ['post_fb', '(like + love + wow + haha + sorry + anger)'],
        'comments' => ['post_fb', 'comments'],
        'shares' => ['post_fb', 'shares'],
        'saves' => [null, null], // FB no tiene saves
    ];

    if (!isset($map[$metric]) || !$map[$metric][0]) return 0;
    [$table, $column] = $map[$metric];

    $sql = "SELECT SUM($column) FROM $table WHERE created_at >= CURDATE() - INTERVAL 30 DAY";
    if ($id_cuenta !== null) {
        $sql .= " AND id_cuenta = :id_cuenta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_cuenta' => $id_cuenta]);
        return (int) $stmt->fetchColumn();
    } else {
        return (int) $pdo->query($sql)->fetchColumn();
    }
}

switch ($endpoint) {
    case 'meta/summary':
        // Reach IG
        $stmt = $db->prepare("SELECT reach FROM insights_ig WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
        $stmt->execute([$id_cuenta]);
        $reach_ig = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $reachHoyIG = (int)($reach_ig[0] ?? 0);
        $reachAyerIG = (int)($reach_ig[1] ?? 0);

        // Impressions FB
        $stmt = $db->prepare("SELECT SUM(impressions) FROM ads_insights_fb WHERE id_cuenta = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$id_cuenta]);
        $impHoyFB = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT SUM(impressions) FROM ads_insights_fb WHERE id_cuenta = ? AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
        $stmt->execute([$id_cuenta]);
        $impAyerFB = (int)$stmt->fetchColumn();

        $totalReachHoy = $reachHoyIG + $impHoyFB;
        $totalReachAyer = $reachAyerIG + $impAyerFB;
        $reachChange = $totalReachHoy - $totalReachAyer;

        // Seguidores
        $igFollowers = (int)getLatestMetric($db, 'seguidores_ig', 'followers_count', $id_cuenta);
        $fbFollowers = (int)getLatestMetric($db, 'seguidores_fb', 'followers_count', $id_cuenta);

        $stmt = $db->prepare("SELECT followers_count FROM seguidores_ig WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
        $stmt->execute([$id_cuenta]);
        $igSeg = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $deltaIG = ($igSeg[0] ?? 0) - ($igSeg[1] ?? 0);

        $stmt = $db->prepare("SELECT followers_count FROM seguidores_fb WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
        $stmt->execute([$id_cuenta]);
        $fbSeg = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $deltaFB = ($fbSeg[0] ?? 0) - ($fbSeg[1] ?? 0);

        $newFollowers = $deltaIG + $deltaFB;

        // Engagements IG últimos 2 días
        $stmt = $db->prepare("SELECT likes, comments, shares, saves FROM insights_ig WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
        $stmt->execute([$id_cuenta]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $engIGHoy = $rows[0] ?? ['likes'=>0, 'comments'=>0, 'shares'=>0, 'saves'=>0];
        $engIGAyer = $rows[1] ?? ['likes'=>0, 'comments'=>0, 'shares'=>0, 'saves'=>0];

        // Engagements FB últimos 2 días
        $stmt = $db->prepare("
            SELECT DATE(created_at) as fecha,
                SUM(`like` + love + wow + haha + sorry + anger) AS likes,
                SUM(comments) AS comments,
                SUM(shares) AS shares
            FROM post_fb
            WHERE id_cuenta = ? AND created_at >= CURDATE() - INTERVAL 1 DAY
            GROUP BY DATE(created_at)
            ORDER BY fecha DESC
            LIMIT 2
        ");
        $stmt->execute([$id_cuenta]);
        $rowsFB = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $engFBHoy = $rowsFB[0] ?? ['likes'=>0, 'comments'=>0, 'shares'=>0];
        $engFBAyer = $rowsFB[1] ?? ['likes'=>0, 'comments'=>0, 'shares'=>0];

        $engHoy = $engIGHoy['likes'] + $engIGHoy['comments'] + $engIGHoy['shares'] + $engIGHoy['saves']
                + $engFBHoy['likes'] + $engFBHoy['comments'] + $engFBHoy['shares'];

        $engAyer = $engIGAyer['likes'] + $engIGAyer['comments'] + $engIGAyer['shares'] + $engIGAyer['saves']
                + $engFBAyer['likes'] + $engFBAyer['comments'] + $engFBAyer['shares'];

        $engagementsChange = $engHoy - $engAyer;

        // Views IG
        $stmt = $db->prepare("SELECT views FROM insights_ig WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
        $stmt->execute([$id_cuenta]);
        $viewsIG = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $viewsChange = ((int)($viewsIG[0] ?? 0)) - ((int)($viewsIG[1] ?? 0));

        $response = [
            'totalReach' => $totalReachHoy,
            'reachChange' => $reachChange,

            'totalFollowers' => $igFollowers + $fbFollowers,
            'newFollowers' => $newFollowers,

            'totalEngagements' => $engHoy,
            'engagementsChange' => $engagementsChange,

            'totalViews' => (int)($viewsIG[0] ?? 0),
            'viewsChange' => $viewsChange
        ];

        echo json_encode($response);
        break;

    case 'meta/platform-reach-history':
        $dias = dias_esp();
        $labels = [];
        $igData = [];
        $fbData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dia = $dias[date('D', strtotime($date))] . ' ' . date('d', strtotime($date));
            $labels[] = $dia;

            // IG
            $stmt = $db->prepare("SELECT SUM(reach) FROM insights_ig WHERE DATE(created_at) = :fecha");
            $stmt->execute(['fecha' => $date]);
            $igData[] = (int) $stmt->fetchColumn();

            // FB
            $stmt2 = $db->prepare("SELECT SUM(page_impressions) FROM insights_fb WHERE DATE(created_at) = :fecha");
            $stmt2->execute(['fecha' => $date]);
            $fbData[] = (int) $stmt2->fetchColumn();
        }

        echo json_encode([
            'labels' => $labels,
            'instagram' => $igData,
            'facebook' => $fbData
        ]);
        break;

    case 'meta/engagement-types':
        // IG últimos 7 días
        $stmt = $db->prepare("
            SELECT 
                SUM(likes) as ig_likes, 
                SUM(comments) as ig_comments, 
                SUM(shares) as ig_shares, 
                SUM(saves) as ig_saves 
            FROM insights_ig 
            WHERE created_at >= CURDATE() - INTERVAL 7 DAY
        ");
        $stmt->execute();
        $ig = $stmt->fetch(PDO::FETCH_ASSOC);

        // FB últimos 7 días
        $stmt2 = $db->prepare("
            SELECT 
                SUM(`like` + love + wow + haha + sorry + anger) as fb_likes, 
                SUM(comments) as fb_comments, 
                SUM(shares) as fb_shares 
            FROM post_fb 
            WHERE created_at >= CURDATE() - INTERVAL 7 DAY
        ");
        $stmt2->execute();
        $fb = $stmt2->fetch(PDO::FETCH_ASSOC);


        echo json_encode([
            'likes' => (int)$ig['ig_likes'] + (int)$fb['fb_likes'],
            'comments' => (int)$ig['ig_comments'] + (int)$fb['fb_comments'],
            'shares' => (int)$ig['ig_shares'] + (int)$fb['fb_shares'],
            'saves' => (int)$ig['ig_saves']
        ]);
        break;

    case 'meta/top-posts':
        $sql = "SELECT * FROM post_fb ORDER BY comments DESC LIMIT 5";
        $result = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        break;

    case 'meta/followers-history':
        $dias = dias_esp();
        $labels = [];
        $igData = [];
        $fbData = [];

        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            $dia = $dias[date('D', strtotime($fecha))] . ' ' . date('d', strtotime($fecha));
            $labels[] = $dia;

            // IG
            $stmt = $db->prepare("SELECT followers_count FROM seguidores_ig WHERE DATE(created_at) = :fecha ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['fecha' => $fecha]);
            $igData[] = (int) $stmt->fetchColumn();

            // FB
            $stmt = $db->prepare("SELECT followers_count FROM seguidores_fb WHERE DATE(created_at) = :fecha ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['fecha' => $fecha]);
            $fbData[] = (int) $stmt->fetchColumn();
        }

        // Calcular variación de seguidores de IG y FB (del último día vs. anterior)
        $igChange = $igData[6] - $igData[5];
        $fbChange = $fbData[6] - $fbData[5];

        echo json_encode([
            'labels' => $labels,
            'instagram' => $igData,
            'facebook' => $fbData,
            'change_ig' => $igChange,
            'change_fb' => $fbChange
        ]);
        break;

    case 'meta/engagements-history':
        $dias = dias_esp();
        $labels = [];
        $igData = [];
        $fbData = [];

        $semanaActual = 0;
        $semanaAnterior = 0;

        for ($i = 13; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            $dia = $dias[date('D', strtotime($fecha))] . ' ' . date('d', strtotime($fecha));
            if ($i < 7) $labels[] = $dia; // solo mostramos últimos 7 días

            // Instagram
            $stmtIG = $db->prepare("
                SELECT 
                    COALESCE(SUM(likes), 0) + 
                    COALESCE(SUM(comments), 0) + 
                    COALESCE(SUM(shares), 0) + 
                    COALESCE(SUM(saves), 0) AS total
                FROM insights_ig 
                WHERE DATE(created_at) = :fecha
            ");
            $stmtIG->execute(['fecha' => $fecha]);
            $igTotal = (int) $stmtIG->fetchColumn();

            // Facebook
            $stmtFB = $db->prepare("
                SELECT 
                    COALESCE(SUM(`like` + love + wow + haha + sorry + anger), 0) + 
                    COALESCE(SUM(comments), 0) + 
                    COALESCE(SUM(shares), 0) AS total
                FROM post_fb 
                WHERE DATE(created_at) = :fecha
            ");
            $stmtFB->execute(['fecha' => $fecha]);
            $fbTotal = (int) $stmtFB->fetchColumn();

            $totalDia = $igTotal + $fbTotal;

            if ($i < 7) {
                $igData[] = $igTotal;
                $fbData[] = $fbTotal;
                $semanaActual += $totalDia;
            } else {
                $semanaAnterior += $totalDia;
            }
        }

        $variacion = 0;
        if ($semanaAnterior > 0) {
            $variacion = round((($semanaActual - $semanaAnterior) / $semanaAnterior) * 100, 1);
        }

        echo json_encode([
            'labels' => $labels,
            'instagram' => $igData,
            'facebook' => $fbData,
            'variation' => $variacion
        ]);
        break;

    case 'meta/fb-posts-interactions':
        $sql = "
            SELECT 
                message,
                created_at,
                `like`, love, wow, haha, sorry, anger,
                comments,
                shares
            FROM post_fb
            ORDER BY (`like` + love + wow + haha + sorry + anger + comments + shares) DESC
            LIMIT 10
        ";
        $posts = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($posts as $p) {
            $response[] = [
                'message' => mb_substr(strip_tags($p['message'] ?? ''), 0, 50) ?: '(Sin texto)',
                'fecha' => date('d/m/Y', strtotime($p['created_at'])),
                'me_gusta' => (int) $p['like'],
                'me_encanta' => (int) $p['love'],
                'me_asombra' => (int) $p['wow'],
                'me_divierte' => (int) $p['haha'],
                'me_entristece' => (int) $p['sorry'],
                'me_enfada' => (int) $p['anger'],
                'comentarios' => (int) $p['comments'],
                'compartidos' => (int) $p['shares']
            ];
        }

        echo json_encode($response);
        break;
    
    case 'usuarios/listar':
        $stmt = $db->query("
            SELECT 
                u.id_usuario, u.nombres, u.apellidos, u.correo, u.clave,
                u.id_sede, u.id_cuenta, u.id_nivel_usuario, u.token,
                s.nombre AS sede, 
                c.nombre AS cuenta
            FROM usuario u
            LEFT JOIN sede s ON u.id_sede = s.id_sede
            LEFT JOIN cuenta c ON u.id_cuenta = c.id_cuenta
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'usuarios/registrar':
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $db->prepare("
            INSERT INTO usuario (nombres, apellidos, correo, clave, token, id_sede, id_cuenta, id_nivel_usuario, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        try {
            $stmt->execute([
                $data['nombres'],
                $data['apellidos'],
                $data['correo'],
                $data['clave'],
                $data['token'],
                $data['id_sede'],
                $data['id_cuenta'],
                $data['id_nivel_usuario']
            ]);
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
        }
        break;

    case 'usuarios/editar':
        $data = json_decode(file_get_contents("php://input"), true);
        
        $stmt = $db->prepare("
            UPDATE usuario SET 
                nombres=?, apellidos=?, correo=?, clave=?, token=?, 
                id_sede=?, id_cuenta=?, id_nivel_usuario=?
            WHERE id_usuario=?
        ");
        try {
            $stmt->execute([
                $data['nombres'], $data['apellidos'], $data['correo'],
                $data['clave'], // <-- sin hash
                $data['token'],
                $data['id_sede'], $data['id_cuenta'], $data['id_nivel_usuario'],
                $data['id_usuario']
            ]);

            echo json_encode(['status'=>'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status'=>'error', 'error'=>$e->getMessage()]);
        }
        break;

    case 'usuarios/eliminar':
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id_usuario'])) {
                throw new Exception("Falta el ID del usuario");
            }

            $stmt = $db->prepare("DELETE FROM usuario WHERE id_usuario = ?");
            $stmt->execute([$data['id_usuario']]);

            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
        }
        break;

    case 'sedes/listar':
        $stmt = $db->query("SELECT id_sede, nombre FROM sede");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'sedes/registrar':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['nombre']) || trim($data['nombre']) === '') {
            echo json_encode(['status' => 'error', 'error' => 'El nombre es requerido']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO sede (nombre) VALUES (?)");
        $stmt->execute([$data['nombre']]);
        echo json_encode(['status' => 'ok']);
        break;

    case 'sedes/editar':
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("UPDATE sede SET nombre = ? WHERE id_sede = ?");
        $stmt->execute([$data['nombre'], $data['id_sede']]);
        echo json_encode(['status' => 'ok']);
        break;

    case 'sedes/eliminar':
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("DELETE FROM sede WHERE id_sede = ?");
        $stmt->execute([$data['id_sede']]);
        echo json_encode(['status' => 'ok']);
        break;

    case 'cuentas/listar':
        $stmt = $db->query("SELECT * FROM cuenta");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'cuentas/registrar':
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $db->prepare("
            INSERT INTO cuenta (nombre, fb_page_id, fb_page_name, ig_user_id, ig_username, ads_account_id, access_token, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        try {
            $stmt->execute([
                $data['nombre'],
                $data['fb_page_id'],
                $data['fb_page_name'],
                $data['ig_user_id'],
                $data['ig_username'],
                $data['ads_account_id'],
                $data['access_token']
            ]);
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
        }
        break;

    case 'cuentas/editar':
        $data = json_decode(file_get_contents("php://input"), true);
        
        $stmt = $db->prepare("
            UPDATE cuenta SET 
                nombre=?, fb_page_id=?, fb_page_name=?, ig_user_id=?, ig_username=?, ads_account_id=?, access_token=?
            WHERE id_cuenta=?
        ");
        try {
            $stmt->execute([
                $data['nombre'], $data['fb_page_id'], $data['fb_page_name'], $data['ig_user_id'],
                $data['ig_username'], $data['ads_account_id'], $data['access_token'], $data['id_cuenta']
            ]);

            echo json_encode(['status'=>'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status'=>'error', 'error'=>$e->getMessage()]);
        }
        break;

    case 'cuentas/eliminar':
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("DELETE FROM cuenta WHERE id_cuenta = ?");
        $stmt->execute([$data['id_cuenta']]);
        echo json_encode(['status' => 'ok']);
        break;

    case 'meta/sedes-extremos':
        $sql = "
            SELECT 
                cuenta.fb_page_name AS cuenta,
                cuenta.id_cuenta,
                SUM(IFNULL(insfb.page_impressions, 0)) + SUM(IFNULL(insig.reach, 0)) AS alcance,

                IFNULL((
                    SELECT seguidores.followers_count
                    FROM (
                        SELECT id_cuenta, followers_count, created_at FROM seguidores_fb WHERE created_at >= CURDATE() - INTERVAL 30 DAY
                        UNION ALL
                        SELECT id_cuenta, followers_count, created_at FROM seguidores_ig WHERE created_at >= CURDATE() - INTERVAL 30 DAY
                    ) AS seguidores
                    WHERE seguidores.id_cuenta = cuenta.id_cuenta
                    ORDER BY seguidores.created_at DESC
                    LIMIT 1
                ), 0) AS seguidores,

                SUM(
                    IFNULL(insig.likes,0) + IFNULL(insig.comments,0) + IFNULL(insig.shares,0) + IFNULL(insig.saves,0) +
                    IFNULL(pf.like,0) + IFNULL(pf.love,0) + IFNULL(pf.wow,0) + IFNULL(pf.haha,0) + 
                    IFNULL(pf.sorry,0) + IFNULL(pf.anger,0) + IFNULL(pf.comments,0) + IFNULL(pf.shares,0)
                ) AS interacciones,

                SUM(IFNULL(insig.views, 0)) AS visualizaciones

            FROM cuenta
            LEFT JOIN insights_fb insfb 
                ON insfb.id_cuenta = cuenta.id_cuenta 
                AND insfb.created_at >= CURDATE() - INTERVAL 30 DAY
            LEFT JOIN insights_ig insig 
                ON insig.id_cuenta = cuenta.id_cuenta 
                AND insig.created_at >= CURDATE() - INTERVAL 30 DAY
            LEFT JOIN post_fb pf 
                ON pf.id_cuenta = cuenta.id_cuenta 
                AND pf.created_at >= CURDATE() - INTERVAL 30 DAY
            GROUP BY cuenta.id_cuenta
        ";

        $data_now = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Corrección: aplicar cambio de fechas en todos los WHERE
        $sql_old = preg_replace(
            [
                '/seguidores_fb\s+WHERE\s+created_at\s+>=\s+CURDATE\(\)\s*-\s*INTERVAL\s+30\s+DAY/',
                '/seguidores_ig\s+WHERE\s+created_at\s+>=\s+CURDATE\(\)\s*-\s*INTERVAL\s+30\s+DAY/',
                '/insfb\.created_at\s+>=\s+CURDATE\(\)\s*-\s*INTERVAL\s+30\s+DAY/',
                '/insig\.created_at\s+>=\s+CURDATE\(\)\s*-\s*INTERVAL\s+30\s+DAY/',
                '/pf\.created_at\s+>=\s+CURDATE\(\)\s*-\s*INTERVAL\s+30\s+DAY/',
            ],
            [
                'seguidores_fb WHERE created_at >= CURDATE() - INTERVAL 60 DAY AND created_at < CURDATE() - INTERVAL 30 DAY',
                'seguidores_ig WHERE created_at >= CURDATE() - INTERVAL 60 DAY AND created_at < CURDATE() - INTERVAL 30 DAY',
                'insfb.created_at >= CURDATE() - INTERVAL 60 DAY AND insfb.created_at < CURDATE() - INTERVAL 30 DAY',
                'insig.created_at >= CURDATE() - INTERVAL 60 DAY AND insig.created_at < CURDATE() - INTERVAL 30 DAY',
                'pf.created_at >= CURDATE() - INTERVAL 60 DAY AND pf.created_at < CURDATE() - INTERVAL 30 DAY',
            ],
            $sql
        );

        $data_old = $db->query($sql_old)->fetchAll(PDO::FETCH_ASSOC);

        // Indexar por id_cuenta
        $old_lookup = [];
        foreach ($data_old as $row) {
            $old_lookup[$row['id_cuenta']] = $row;
        }

        // Función para calcular extremos con cambio porcentual
        function extremos($data, $campo, $old_lookup) {
            $min = $max = null;

            foreach ($data as $row) {
                $valor = (int)$row[$campo];

                $old_valor = 0;
                if (isset($old_lookup[$row['id_cuenta']])) {
                    $prev = $old_lookup[$row['id_cuenta']];
                    if (isset($prev[$campo]) && is_numeric($prev[$campo])) {
                        $old_valor = (int)$prev[$campo];
                    }
                }

                // Lógica de variación
                if ($old_valor === 0) {
                    $cambio = ($valor > 0) ? +100 : +0;
                } else {
                    $cambio = round((($valor - $old_valor) / $old_valor) * 100);
                }

                $row_result = [
                    'valor' => $valor,
                    'cuenta' => $row['cuenta'],
                    'cambio' => $cambio
                ];

                if ($max === null || $valor > $max['valor']) $max = $row_result;
                if ($min === null || $valor < $min['valor']) $min = $row_result;
            }

            return ['max' => $max, 'min' => $min];
        }

        echo json_encode([
            'alcance' => extremos($data_now, 'alcance', $old_lookup),
            'seguidores' => extremos($data_now, 'seguidores', $old_lookup),
            'interacciones' => extremos($data_now, 'interacciones', $old_lookup),
            'visualizaciones' => extremos($data_now, 'visualizaciones', $old_lookup),
        ]);
        break;

    case 'meta/ranking-cuentas': 
        $indicador = $_GET['indicador'] ?? 'costo';

        switch ($indicador) {
            case 'alcance':
                $indicadorCampo = 'alcance';
                break;
            case 'seguidores':
                $indicadorCampo = 'seguidores';
                break;
            case 'interacciones':
                $indicadorCampo = 'interacciones';
                break;
            case 'costo':
            default:
                $indicadorCampo = 'costo';
                break;
        }

        $sql = "
            SELECT 
                c.id_cuenta,
                c.fb_page_name AS cuenta,

                SUM(IFNULL(insfb.page_impressions, 0)) + SUM(IFNULL(insig.reach, 0)) AS alcance,

                (
                    SELECT SUM(sfb.followers_count)
                    FROM seguidores_fb sfb
                    WHERE sfb.id_cuenta = c.id_cuenta
                    AND sfb.created_at >= CURDATE() - INTERVAL 30 DAY
                ) +
                (
                    SELECT SUM(sig.followers_count)
                    FROM seguidores_ig sig
                    WHERE sig.id_cuenta = c.id_cuenta
                    AND sig.created_at >= CURDATE() - INTERVAL 30 DAY
                ) AS seguidores,

                SUM(
                    IFNULL(insig.likes,0) + IFNULL(insig.comments,0) + IFNULL(insig.shares,0) + IFNULL(insig.saves,0) +
                    IFNULL(pf.like,0) + IFNULL(pf.love,0) + IFNULL(pf.wow,0) + IFNULL(pf.haha,0) + 
                    IFNULL(pf.sorry,0) + IFNULL(pf.anger,0) + IFNULL(pf.comments,0) + IFNULL(pf.shares,0)
                ) AS interacciones,

                IFNULL((
                    SELECT ROUND(AVG(CAST(cost_per_result AS DECIMAL(10,4))), 2)
                    FROM ads_insights_fb
                    WHERE id_cuenta = c.id_cuenta
                    AND created_at >= CURDATE() - INTERVAL 30 DAY
                    AND cost_per_result REGEXP '^[0-9]+(\.[0-9]+)?$'
                ), 0) AS costo

            FROM cuenta c
            LEFT JOIN insights_fb insfb 
                ON insfb.id_cuenta = c.id_cuenta 
                AND insfb.created_at >= CURDATE() - INTERVAL 30 DAY
            LEFT JOIN insights_ig insig 
                ON insig.id_cuenta = c.id_cuenta 
                AND insig.created_at >= CURDATE() - INTERVAL 30 DAY
            LEFT JOIN post_fb pf 
                ON pf.id_cuenta = c.id_cuenta 
                AND pf.created_at >= CURDATE() - INTERVAL 30 DAY
            GROUP BY c.id_cuenta, c.fb_page_name
            HAVING $indicadorCampo > 0
            ORDER BY $indicadorCampo " . ($indicadorCampo === 'costo' ? 'ASC' : 'DESC') . "
            LIMIT 10
        ";

        $ranking = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $resultado = array_map(function($row) use ($indicadorCampo) {
            return [
                'id_cuenta' => $row['id_cuenta'],
                'cuenta' => $row['cuenta'],
                'valor' => floatval($row[$indicadorCampo])
            ];
        }, $ranking);

        echo json_encode($resultado);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Endpoint no válido']);
        break;
}
