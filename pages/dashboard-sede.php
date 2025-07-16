<?php
    session_start();
    require_once 'db_connection.php';

    if (!isset($_SESSION['id_usuario'])) {
        header("Location: sign-in.php");
        exit;
    }

    $id_usuario = $_SESSION['id_usuario'];
    $id_cuenta = $_GET['id_cuenta'] ?? null;

    if (!$id_cuenta || !is_numeric($id_cuenta)) {
        die("❌ ID de cuenta no válido.");
    }

    $stmt = $pdo->prepare("SELECT nombre FROM cuenta WHERE id_cuenta = ?");
    $stmt->execute([$id_cuenta]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta) {
        die("❌ Cuenta no encontrada.");
    }

    $nombre_cuenta = $cuenta['nombre'];

    $sql = "
        SELECT u.nombres, u.apellidos, c.id_cuenta, c.nombre AS cuenta_nombre, 
            c.fb_page_id, c.ig_user_id, c.fb_page_name, s.nombre AS sede_nombre
        FROM usuario u
        JOIN cuenta c ON u.id_cuenta = c.id_cuenta
        JOIN sede s ON u.id_sede = s.id_sede
        WHERE u.id_usuario = ?
    ";
    $stmt2 = $pdo->prepare($sql);
    $stmt2->execute([$id_usuario]);
    $data = $stmt2->fetch();

    if (!$data) {
        die("Error: No se pudo obtener la información del usuario.");
    }

    $fb_page_id = $data['fb_page_id'];
    $ig_user_id = $data['ig_user_id'];
    $fb_page_name = $data['fb_page_name'];
    $sede_nombre = $data['sede_nombre'];

    // IG metrics
    $stmt = $pdo->prepare("SELECT * FROM insights_ig WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
    $stmt->execute([$id_cuenta]);
    $rows = $stmt->fetchAll();

    $igHoy = $rows[0] ?? [];
    $igAyer = $rows[1] ?? [];

    $fechaHoy = $igHoy['created_at'] ?? null;
    $tiempo = $fechaHoy ? tiempoTranscurrido($fechaHoy) : 'sin datos recientes';

    // FB followers
    $stmt_fb = $pdo->prepare("SELECT followers_count FROM seguidores_fb WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
    $stmt_fb->execute([$id_cuenta]);
    $seguidoresFB = $stmt_fb->fetchAll();
    $fbHoy = $seguidoresFB[0] ?? ['followers_count' => 0];
    $fbAyer = $seguidoresFB[1] ?? ['followers_count' => 0];

    // IG followers
    $stmt_ig = $pdo->prepare("SELECT followers_count FROM seguidores_ig WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 2");
    $stmt_ig->execute([$id_cuenta]);
    $seguidoresIG = $stmt_ig->fetchAll();
    $igFHoy = $seguidoresIG[0] ?? ['followers_count' => 0];
    $igFAyer = $seguidoresIG[1] ?? ['followers_count' => 0];

    $totalSeguidores = (int)$fbHoy['followers_count'] + (int)$igFHoy['followers_count'];
    $deltaSeguidores = ($fbHoy['followers_count'] + $igFHoy['followers_count']) - ($fbAyer['followers_count'] + $igFAyer['followers_count']);

    // Get impressions from insights_fb
    $stmt = $pdo->prepare("SELECT page_impressions FROM insights_fb WHERE id_cuenta = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$id_cuenta]);
    $fbData = $stmt->fetch(PDO::FETCH_ASSOC);
    $page_impressions = $fbData['page_impressions'] ?? 0;

    // Get reach sum from ads_insights_fb
    $stmt = $pdo->prepare("SELECT SUM(reach) as total_reach FROM ads_insights_fb WHERE id_cuenta = ?");
    $stmt->execute([$id_cuenta]);
    $adsReach = $stmt->fetchColumn() ?? 0;

    // Get reach from IG
    $reach_ig_hoy = $igHoy['reach'] ?? 0;
    $reach_ig_ayer = $igAyer['reach'] ?? 0;

    // Get views from IG
    $views_ig_hoy = $igHoy['views'] ?? 0;
    $views_ig_ayer = $igAyer['views'] ?? 0;

    // Construir arreglo $hoy y $ayer con reach y views consolidados
    $hoy = [
        'reach' => (int)$reach_ig_hoy + (int)$adsReach,
        'views' => (int)$views_ig_hoy + (int)$page_impressions
    ];

    $ayer = [
        'reach' => (int)$reach_ig_ayer, // no se suma adsReach viejo, solo si tuvieras histórico diario de ads
        'views' => (int)$views_ig_ayer
    ];

    // Funciones
    function cambio($actual, $anterior) {
        return $actual - $anterior;
    }

    function claseCambio($valor) {
        return $valor >= 0 ? 'text-success' : 'text-danger';
    }

    function nuevosPerdidos($valor) {
        $abs = abs($valor);
        return $valor >= 0 ? ($abs === 1 ? 'nuevo' : 'nuevos') : ($abs === 1 ? 'perdido' : 'perdidos');
    }

    function tiempoTranscurrido($fecha) {
        if (!$fecha) return '';
        $ahora = new DateTime("now", new DateTimeZone("America/Lima"));
        $guardado = new DateTime($fecha, new DateTimeZone("America/Lima"));
        $diff = $ahora->getTimestamp() - $guardado->getTimestamp();

        if ($diff < 60) {
            return "actualizado hace un momento";
        } elseif ($diff < 3600) {
            return "actualizado hace " . floor($diff / 60) . " minuto(s)";
        } elseif ($diff < 86400) {
            return "actualizado hace " . floor($diff / 3600) . " hora(s)";
        } else {
            return "actualizado hace " . floor($diff / 86400) . " día(s)";
        }
    }

    // Interacciones últimos 14 días (likes + comments + shares + saves)
    $stmt_ig = $pdo->prepare("
        SELECT created_at, likes, comments, shares, saves
        FROM insights_ig
        WHERE id_cuenta = ?
        ORDER BY created_at DESC
        LIMIT 14
    ");
    $stmt_ig->execute([$id_cuenta]);
    $datos_ig = $stmt_ig->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por fecha IG
    $ig_by_date = [];
    foreach ($datos_ig as $row) {
        $fecha = substr($row['created_at'], 0, 10);
        $ig_by_date[$fecha] = [
            'likes' => (int)$row['likes'],
            'comments' => (int)$row['comments'],
            'shares' => (int)$row['shares'],
            'saves' => (int)$row['saves'],
        ];
    }

    // Obtener datos de Facebook desde post_fb (últimos 14 días)
    $stmt_fb = $pdo->prepare("
        SELECT DATE(created_at) as fecha,
            SUM(`like` + love + wow + haha + sorry + anger) as likes,
            SUM(comments) as comments,
            SUM(shares) as shares
        FROM post_fb
        WHERE id_cuenta = ? AND created_at >= CURDATE() - INTERVAL 14 DAY
        GROUP BY DATE(created_at)
        ORDER BY fecha DESC
        LIMIT 14
    ");
    $stmt_fb->execute([$id_cuenta]);
    $datos_fb = $stmt_fb->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por fecha FB
    $fb_by_date = [];
    foreach ($datos_fb as $row) {
        $fb_by_date[$row['fecha']] = [
            'likes' => (int)$row['likes'],
            'comments' => (int)$row['comments'],
            'shares' => (int)$row['shares'],
            'saves' => 0
        ];
    }

    // Unificar fechas de IG y FB
    $fechas = array_unique(array_merge(array_keys($ig_by_date), array_keys($fb_by_date)));
    rsort($fechas);
    $fechas = array_slice($fechas, 0, 14);

    $semanaActual = 0;
    $semanaPasada = 0;
    $historico = [];

    foreach ($fechas as $i => $fecha) {
        $ig = $ig_by_date[$fecha] ?? ['likes'=>0,'comments'=>0,'shares'=>0,'saves'=>0];
        $fb = $fb_by_date[$fecha] ?? ['likes'=>0,'comments'=>0,'shares'=>0,'saves'=>0];

        $total = $ig['likes'] + $fb['likes'] +
                $ig['comments'] + $fb['comments'] +
                $ig['shares'] + $fb['shares'] +
                $ig['saves'];

        if ($i < 7) {
            $semanaActual += $total;
        } else {
            $semanaPasada += $total;
        }

        $historico[] = [
            'fecha' => $fecha,
            'likes' => $ig['likes'] + $fb['likes'],
            'comments' => $ig['comments'] + $fb['comments'],
            'shares' => $ig['shares'] + $fb['shares'],
            'saves' => $ig['saves']
        ];
    }

    // Diferencia entre semanas
    $diferencia = $semanaActual - $semanaPasada;
    if ($diferencia >= 0) {
        $diferencia = '+' . $diferencia;
    }

    // Función para obtener métricas de campañas, adsets, ads
    function getMetrics($pdo, $table, $nameField, $id_cuenta) {
        $stmt = $pdo->prepare("
            SELECT $nameField AS label, clicks, impressions, reach, spend, ctr, cpc, cpm, cost_per_result
            FROM $table
            WHERE id_cuenta = $id_cuenta
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$id_cuenta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $adsFull = getMetrics($pdo, 'ads_insights_fb', 'ad_name', $id_cuenta);
    $adsetsFull = getMetrics($pdo, 'adsets_insights_fb', 'adset_name', $id_cuenta);
    $campaignsFull = getMetrics($pdo, 'campaigns_insights_fb', 'campaign_name', $id_cuenta);
    echo("$ adsFull : " . $adsFull);
    echo("$ adsetsFull : " . $adsetsFull);
    echo("$ campaignsFull : " . $campaignsFull);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
        <link rel="icon" type="image/png" href="../assets/img/favicon.png">
        <title>Alertia | Dashboard de indicadores</title>
        <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
        <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
        <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
        <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
        <style>
            /* Eliminar scroll horizontal y ajustar diseño */
            html, body {
                overflow-x: hidden;
                width: 100%;
            }

            .main-content {
                overflow: hidden;
            }

            .container-fluid {
                padding: 0 15px;
                width: 100%;
                margin: 0 auto;
            }

            .table {
                width: 100%;
                table-layout: auto;
            }

            .table-responsive {
                overflow: visible !important;
                border-radius: 1rem;
            }

            .card {
                border: none;
                border-radius: 1rem;
                overflow: visible;
            }

            #fb-posts-table {
                border-collapse: separate;
                border-spacing: 0;
            }

            #fb-posts-table th,
            #fb-posts-table td {
                border: none !important;
            }

            #fb-posts-table td:first-child {
                max-width: 320px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                text-align: left;
                padding-left: 1.25rem;
            }

            .truncate-message {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            #fb-posts-table thead {
                font-weight: 500;
                border-radius: 12px;
            }
        </style>
    </head>
    <body class="g-sidenav-show bg-gray-100">
        <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
            <div class="sidenav-header">
                <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
                <a class="navbar-brand px-4 py-3 m-0" href=" https://demos.creative-tim.com/material-dashboard/pages/dashboard " target="_blank">
                    <img src="../assets/img/logo-ct-dark.png" class="navbar-brand-img" width="26" height="26" alt="main_logo">
                    <span class="ms-1 text-sm text-dark">Alertia</span>
                </a>
            </div>
            <hr class="horizontal dark mt-0 mb-2">
            <div class="collapse navbar-collapse w-auto " id="sidenav-collapse-main">
                <ul class="navbar-nav">
                    
                    <?php if (isset($_SESSION['nivel_usuario']) && $_SESSION['nivel_usuario'] == 3): ?>
                    <li class="nav-item">
                        <a class="nav-link active bg-gradient-dark text-white" href="../pages/dashboard.php">
                            <i class="material-symbols-rounded opacity-5">dashboard</i>
                            <span class="nav-link-text ms-1">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['nivel_usuario']) && ($_SESSION['nivel_usuario'] == 2 || $_SESSION['nivel_usuario'] == 1)): ?>
                    <li class="nav-item">
                        <a class="nav-link active bg-gradient-dark text-white" href="../pages/dashboard-sede.php?id_cuenta=<?= $id_cuenta ?>">
                            <i class="material-symbols-rounded opacity-5">dashboard</i>
                            <span class="nav-link-text ms-1">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['nivel_usuario']) && $_SESSION['nivel_usuario'] == 3): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="../pages/usuarios.php">
                            <i class="material-symbols-rounded opacity-5">group</i>
                            <span class="nav-link-text ms-1">Usuarios</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['nivel_usuario']) && $_SESSION['nivel_usuario'] == 3): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="../pages/sedes.php">
                            <i class="material-symbols-rounded opacity-5">home_health</i>
                            <span class="nav-link-text ms-1">Sedes</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['nivel_usuario']) && $_SESSION['nivel_usuario'] == 3): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="../pages/cuentas.php">
                            <i class="material-symbols-rounded opacity-5">3p</i>
                            <span class="nav-link-text ms-1">Cuentas de Meta</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="sidenav-footer position-absolute w-100 bottom-0 ">
                <div class="mb-3">
                    <ul class="navbar-nav">
                        <li class="nav-item mt-3">
                            <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">Cuenta</h6>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="../pages/logout.php">
                                <i class="material-symbols-rounded opacity-5">logout</i>
                                <span class="nav-link-text ms-1">Cerrar sesión</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>
        <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
            <!-- Navbar -->
            <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
                <div class="container-fluid py-1 px-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                            <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
                            <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav>
                    <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                        <ul class="ms-md-auto navbar-nav d-flex align-items-center justify-content-end">
                            <li class="nav-item d-flex align-items-center">
                                <h6 class="text-dark text-sm my-0 me-3 border-0"><?php echo htmlspecialchars($nombre_cuenta); ?></h6>
                            </li>
                            <?php if (isset($_SESSION['nivel_usuario']) && $_SESSION['nivel_usuario'] == 3): ?>
                            <li class="nav-item px-3 d-flex align-items-center">
                                <button id="sync-button" class="d-flex align-items-center nav-link text-body p-0">
                                    <i class="material-symbols-rounded fixed-plugin-button-nav">autorenew</i>
                                </button>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item dropdown pe-3 d-flex align-items-center">
                                <a href="javascript:;" class="d-flex align-items-center nav-link text-body p-0" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="material-symbols-rounded">notifications</i>
                                </a>
                            </li>
                            <li class="nav-item d-flex align-items-center">
                                <a href="../pages/sign-in.php" class="d-flex align-items-center nav-link text-body font-weight-bold px-0">
                                    <i class="material-symbols-rounded">account_circle</i>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <!-- End Navbar -->
            <div class="container-fluid py-2">
                <div class="row">
                    <div class="ms-3">
                        <h3 class="mb-0 h4 font-weight-bolder">Dashboard</h3>
                        <p class="mb-4">Monitorea tu alcance, seguidores, interacción y visualizaciones.</p>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-white border-0 shadow-lg shadow">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Alcance</p>
                                        <h4>
                                            <span id="meta-total-reach"><?= $hoy['reach'] ?></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark text-center">
                                        <i class="material-symbols-rounded opacity-10">public</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span id="meta-reach-change" class="font-weight-bolder <?= claseCambio(cambio($hoy['reach'], $ayer['reach'])) ?>">
                                        <?= cambio($hoy['reach'], $ayer['reach']) ?>
                                    </span>
                                    <small class="text-muted ms-1"><?= tiempoTranscurrido($fechaHoy) ?></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-white border-0 shadow-lg shadow">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Seguidores</p>
                                        <h4>
                                            <span id="meta-total-followers"><?= $totalSeguidores ?></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark text-center">
                                        <i class="material-symbols-rounded opacity-10">group</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span id="meta-followers-change" class="font-weight-bolder <?= claseCambio($deltaSeguidores) ?>">
                                        <?= $deltaSeguidores ?>
                                    </span>
                                    <small class="text-muted ms-1"><?= nuevosPerdidos($deltaSeguidores) ?></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-white border-0 shadow-lg shadow">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Interacciones</p>
                                        <h4>
                                            <span id="meta-total-engagements"><?= $semanaActual ?></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark text-center">
                                        <i class="material-symbols-rounded opacity-10">thumb_up</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <small class="font-weight-bolder <?= claseCambio($diferencia) ?>">
                                        <?= $diferencia ?>
                                    </small>
                                    <small class="text-muted ms-1"> en los últimos 7 días</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-white border-0 shadow-lg shadow">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Visualizaciones</p>
                                        <h4>
                                            <span id="meta-total-views"><?= $hoy['views'] ?></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark text-center">
                                        <i class="material-symbols-rounded opacity-10">visibility</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span id="meta-views-change" class="font-weight-bolder <?= claseCambio(cambio($hoy['views'], $ayer['views'])) ?>">
                                        <?= cambio($hoy['views'], $ayer['views']) ?>
                                    </span>
                                    <small class="text-muted ms-1"><?= tiempoTranscurrido($fechaHoy) ?></small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-lg-6 col-md-12 mt-4">
                        <div class="card border-0 shadow-lg shadow h-100">
                            <div class="card-body">
                                <h6 class="mb-0">Cuentas alcanzadas</h6>
                                <p class="text-sm">Número de cuentas únicas que vieron tu contenido al menos una vez, incluido el de los anuncios.</p>
                                <div style="height: 300px;">
                                    <canvas id="meta-platform-reach-chart" class="chart-canvas" style="max-height:300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 mt-4">
                        <div class="card border-0 shadow-lg shadow h-100">
                            <div class="card-body">
                                <h6 class="mb-0">Interacciones con el contenido</h6>
                                <p class="text-sm">Número total de interacciones con publicaciones, historias, reels, videos y videos en vivo, incluidas las interacciones con contenido promocionado.</p>
                                <div style="height: 300px;">
                                    <canvas id="meta-engagement-types-chart" class="chart-canvas" style="max-height: 275px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 mt-4">
                        <div class="card border-0 shadow-lg shadow h-100">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <h6>Seguidores por Plataforma</h6>
                                <span id="meta-followers-change-label" class="text-sm fw-bold d-none"></span>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 300px;">
                                    <canvas id="meta-followers-history-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mt-4">
                        <div class="card border-0 shadow-lg shadow h-100">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <h6>Interacciones por Plataforma</h6>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 300px;">
                                    <canvas id="meta-engagements-history-chart" style="max-height:300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-4">
                        <div class="card border-0 shadow-lg shadow h-100">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <h6 class="text-center">Facebook Ads (Anuncios)</h6>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 300px">
                                    <canvas id="adsChartFull" style="max-height:300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-4">
                        <div class="card border-0 shadow-lg shadow h100">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <h6 class="text-center">Facebook Ads (Adsets)</h6>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 300px">
                                    <canvas id="adsetsChartFull" style="max-height:300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-4">
                        <div class="card border-0 shadow-lg shadow h-100">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <h6 class="text-center">Facebook Ads (Campañas)</h6>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 300px">
                                    <canvas id="campaignsChartFull" style="max-height:300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-4">
                        <div class="card mt-4 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-header bg-white border-bottom-0">
                                <h6 class="mb-0">Top 10 publicaciones de Facebook con detalle de reacciones</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table mb-2 text-center align-middle" id="fb-posts-table">
                                        <thead class="text-secondary">
                                            <tr>
                                                <th class="col-4 text-start px-4 d-flex align-items-center"></th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-primary">thumb_up</span>
                                                    <p class="text-sm m-0"><b>Me gusta</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-danger">favorite</span>
                                                    <p class="text-sm m-0"><b>Me encanta</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-warning">lightbulb</span>
                                                    <p class="text-sm m-0"><b>Me asombra</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-success">mood</span>
                                                    <p class="text-sm m-0"><b>Me divierte</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-muted">sentiment_dissatisfied</span>
                                                    <p class="text-sm m-0"><b>Me entristece</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-danger">mood_bad</span>
                                                    <p class="text-sm m-0"><b>Me enfada</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-info">chat_bubble</span>
                                                    <p class="text-sm m-0"><b>Comentarios</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <span class="material-symbols-rounded text-secondary">share</span>
                                                    <p class="text-sm m-0"><b>Compartidos</b></p>
                                                </th>
                                                <th class="col-1">
                                                    <p class="text-sm m-0"><b>Total</b></p>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody id="fb-posts-body"></tbody>
                                    </table>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="footer py-4">
                    <div class="container-fluid">
                        <div class="row align-items-center justify-content-lg-between">
                            <div class="col-lg-6 mb-lg-0 mb-4">
                                <div class="copyright text-center text-sm text-muted text-lg-start">
                                    ©
                                    <script>
                                        document.write(new Date().getFullYear())
                                    </script>,
                                    made with 
                                    <i class="fa fa-heart"></i>
                                    by 
                                    <a href="http://github.com/el-bryant" class="font-weight-bold" target="_blank">el-bryant</a>
                                    for a better performance.
                                </div>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </main>
        <!--   Core JS Files   -->
        <script src="../assets/js/core/popper.min.js"></script>
        <script src="../assets/js/core/bootstrap.min.js"></script>
        <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
        <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
        <script src="../assets/js/plugins/chartjs.min.js"></script>
        <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
        <!-- Github buttons -->
        <script async defer src="https://buttons.github.io/buttons.js"></script>
        <!-- Control Center for Material Dashboard: parallax effects, scripts for the example pages etc -->
        <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                // 🚀 Cargar resumen (reach, followers, engagements, views)
                fetch("api.php?endpoint=meta/summary")
                    .then(response => response.json())
                    .then(data => {
                        actualizarIndicador('meta-total-reach', 'meta-reach-change', {
                            total: data.totalReach,
                            change: `${data.reachChange > 0 ? '+' : ''}${data.reachChange}%`,
                            trend: data.reachChange >= 0 ? 'up' : 'down'
                        });

                        actualizarIndicador('meta-total-followers', 'meta-followers-change', {
                            total: data.totalFollowers,
                            change: `+${data.newFollowers}`,
                            trend: data.newFollowers >= 0 ? 'up' : 'down'
                        });

                        actualizarIndicador('meta-total-engagements', 'meta-engagements-change', {
                            total: data.totalEngagements,
                            change: `${data.engagementsChange > 0 ? '+' : ''}${data.engagementsChange}%`,
                            trend: data.engagementsChange >= 0 ? 'up' : 'down'
                        });

                        actualizarIndicador('meta-total-views', 'meta-views-change', {
                            total: data.totalViews,
                            change: `${data.viewsChange > 0 ? '+' : ''}${data.viewsChange}%`,
                            trend: data.viewsChange >= 0 ? 'up' : 'down'
                        });
                    })
                    .catch(error => {
                        console.error("Error al cargar datos del dashboard:", error);
                    });

                // 📈 Cargar gráfico histórico de reach por plataforma
                fetch('api.php?endpoint=meta/platform-reach-history')
                    .then(response => response.json())
                    .then(data => {
                        const ctx = document.getElementById('meta-platform-reach-chart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: data.labels,
                                datasets: [
                                    {
                                        label: 'Instagram',
                                        data: data.instagram,
                                        borderColor: '#e1306c',
                                        backgroundColor: 'rgba(225, 48, 108, 0.2)',
                                        fill: true,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Facebook',
                                        data: data.facebook,
                                        borderColor: '#4267B2',
                                        backgroundColor: 'rgba(66, 103, 178, 0.2)',
                                        fill: true,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'top' },
                                    title: { display: false }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    });

                // 🍩 Gráfico de tipos de interacción combinadas (IG + FB)
                fetch('api.php?endpoint=meta/engagement-types')
                    .then(response => response.json())
                    .then(data => {
                        const engagementTypesCtx = document.getElementById('meta-engagement-types-chart').getContext('2d');

                        new Chart(engagementTypesCtx, {
                            type: 'doughnut',
                            data: {
                                labels: ['Me gusta', 'Comentarios', 'Compartidos', 'Guardados'],
                                datasets: [{
                                    data: [
                                        data.likes || 0,
                                        data.comments || 0,
                                        data.shares || 0,
                                        data.saves || 0
                                    ],
                                    backgroundColor: [
                                        'rgba(65, 103, 177, 0.3)',   // Me gusta
                                        'rgba(255, 145, 0, 0.3)',    // Comentarios
                                        'rgba(193, 6, 176, 0.3)',    // Compartidos
                                        'rgba(255, 236, 0, 0.3)'     // Guardados
                                    ],
                                    borderColor: [
                                        '#4167b1',
                                        '#ff9100',
                                        '#c106b0',
                                        '#ffec00'
                                    ],
                                    borderWidth: 2

                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'right'
                                    }
                                }
                            }
                        });
                    });

                // 👥 Seguidores por plataforma
                fetch('api.php?endpoint=meta/followers-history')
                    .then(res => res.json())
                    .then(data => {
                        const ctx = document.getElementById('meta-followers-history-chart').getContext('2d');

                        const followersChangeText = `${data.change_ig >= 0 ? '+' : ''}${data.change_ig} IG / ${data.change_fb >= 0 ? '+' : ''}${data.change_fb} FB`;
                        document.getElementById('meta-followers-change-label').textContent = followersChangeText;
                        document.getElementById('meta-followers-change-label').classList.add(
                            (data.change_ig + data.change_fb) >= 0 ? 'text-success' : 'text-danger'
                        );

                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: data.labels,
                                datasets: [
                                    {
                                        label: 'Instagram',
                                        data: data.instagram,
                                        borderColor: '#e1306c',
                                        backgroundColor: 'rgba(225, 48, 108, 0.2)',
                                        fill: true,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Facebook',
                                        data: data.facebook,
                                        borderColor: '#4267B2',
                                        backgroundColor: 'rgba(66, 103, 178, 0.2)',
                                        fill: true,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'top' },
                                    title: { display: false }
                                },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });
                    });

                fetch('api.php?endpoint=meta/engagements-history')
                    .then(res => res.json())
                    .then(data => {
                        const ctx = document.getElementById('meta-engagements-history-chart').getContext('2d');

                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: data.labels,
                                datasets: [
                                    {
                                        label: 'Instagram',
                                        data: data.instagram,
                                        borderColor: '#e1306c',
                                        backgroundColor: 'rgba(225, 48, 108, 0.2)',
                                        fill: true,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Facebook',
                                        data: data.facebook,
                                        borderColor: '#4267B2',
                                        backgroundColor: 'rgba(66, 103, 178, 0.2)',
                                        fill: true,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'top' },
                                    title: { display: false }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });

                        // Mostramos variación debajo o al costado
                        const variacion = data.variation;
                        const variacionEl = document.getElementById('interactions-variation');
                        if (variacionEl) {
                            variacionEl.textContent = `${variacion > 0 ? '+' : ''}${variacion}% respecto a la semana anterior`;
                            variacionEl.classList.remove('text-success', 'text-danger');
                            variacionEl.classList.add(variacion >= 0 ? 'text-success' : 'text-danger');
                        }
                    });

                fetch('api.php?endpoint=meta/fb-posts-interactions')
                    .then(res => res.json())
                    .then(data => {
                        const tbody = document.getElementById('fb-posts-body');
                        tbody.innerHTML = '';

                        data.forEach(post => {
                            const total = (
                                parseInt(post.me_gusta) +
                                parseInt(post.me_encanta) +
                                parseInt(post.me_asombra) +
                                parseInt(post.me_divierte) +
                                parseInt(post.me_entristece) +
                                parseInt(post.me_enfada) +
                                parseInt(post.comentarios) +
                                parseInt(post.compartidos)
                            );

                            const tr = document.createElement('tr');

                            tr.innerHTML = `
                                <td class="text-start px-4 truncate-message d-flex align-items-center" title="${post.message}">
                                    ${post.message}
                                </td>
                                <td class="col-1 text-center">${post.me_gusta}</td>
                                <td class="col-1 text-center">${post.me_encanta}</td>
                                <td class="col-1 text-center">${post.me_asombra}</td>
                                <td class="col-1 text-center">${post.me_divierte}</td>
                                <td class="col-1 text-center">${post.me_entristece}</td>
                                <td class="col-1 text-center">${post.me_enfada}</td>
                                <td class="col-1 text-center">${post.comentarios}</td>
                                <td class="col-1 text-center">${post.compartidos}</td>
                                <td class="col-1 text-center fw-bold">${total}</td>
                            `;

                            tbody.appendChild(tr);
                        });
                    })
                    .catch(err => {
                        console.error("Error al cargar interacciones de Facebook:", err);
                    });


                function actualizarIndicador(idTotal, idChange, datos) {
                    const totalEl = document.getElementById(idTotal);
                    const changeEl = document.getElementById(idChange);

                    if (totalEl) totalEl.textContent = datos.total.toLocaleString('es-PE');
                    if (changeEl) {
                        changeEl.textContent = datos.change;
                        changeEl.classList.remove('text-success', 'text-danger');
                        changeEl.classList.add(datos.trend === 'up' ? 'text-success' : 'text-danger');
                    }
                }
            });

            document.getElementById('sync-button').addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                btn.textContent = 'Sincronizando...';

                fetch('sync_meta_data.php')
                    .then(response => {
                        if (!response.ok) throw new Error('Error al sincronizar');
                        return response.text();
                    })
                    .then(data => {
                        console.log('✅ Sincronización completada:', data);
                        location.reload(); // Recarga la página para mostrar datos nuevos
                    })
                    .catch(error => {
                        console.error('❌ Error:', error);
                        alert('Error al sincronizar los datos');
                        btn.disabled = false;
                        btn.textContent = '🔄 Sincronizar Datos Meta';
                    });
            });
            
            const adsFull = <?php echo json_encode($adsFull); ?>;
            const adsetsFull = <?php echo json_encode($adsetsFull); ?>;
            const campaignsFull = <?php echo json_encode($campaignsFull); ?>;

            function renderMultiMetricChart(canvasId, data) {
                const labels = data.map(d => d.label);
                const metrics = ['clicks', 'impressions', 'reach', 'spend', 'ctr', 'cpc', 'cpm', 'cost_per_result'];

                const datasets = metrics.map((metric, index) => {
                    const r = (index + 1) * 40 % 255;
                    const g = (index + 2) * 60 % 255;
                    const b = (index + 3) * 80 % 255;
                    return {
                    label: metric.toUpperCase(),
                    data: data.map(d => parseFloat(d[metric]) || 0),
                    backgroundColor: `rgba(${r}, ${g}, ${b}, 0.3)`,  // relleno transparente
                    borderColor: `rgba(${r}, ${g}, ${b}, 1)`,        // borde sólido
                    borderWidth: 1,
                    barThickness: 12                                // ← fuerza separación y grosor
                    };
                });

                new Chart(document.getElementById(canvasId).getContext('2d'), {
                    type: 'bar',
                    data: {
                    labels: labels,
                    datasets: datasets
                    },
                    options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    },
                    scales: {
                        x: {
                        stacked: false,
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        }
                        },
                        y: {
                        beginAtZero: true,
                        stacked: false
                        }
                    }
                    }
                });
            }
            console.log("adsFull : " . adsFull);
            console.log("adsetsFull : " . adsetsFull);
            console.log("campaignsFull : " . campaignsFull);

            renderMultiMetricChart('adsChartFull', adsFull);
            renderMultiMetricChart('adsetsChartFull', adsetsFull);
            renderMultiMetricChart('campaignsChartFull', campaignsFull);
        </script>
    </body>
</html>