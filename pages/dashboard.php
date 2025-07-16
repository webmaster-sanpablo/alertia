<?php
    session_start();
    require_once 'db_connection.php';

    if (!isset($_SESSION['id_usuario'])) {
        header("Location: sign-in.php");
        exit;
    }

    $id_usuario = $_SESSION['id_usuario'];

    // Obtener datos del usuario y su cuenta
    $sql = "
        SELECT u.nombres, u.apellidos, u.id_nivel_usuario, c.id_cuenta, c.nombre AS cuenta_nombre, 
            c.fb_page_id, c.ig_user_id, c.fb_page_name, s.nombre AS sede_nombre
        FROM usuario u
        JOIN cuenta c ON u.id_cuenta = c.id_cuenta
        JOIN sede s ON u.id_sede = s.id_sede
        WHERE u.id_usuario = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario]);
    $data = $stmt->fetch();

    if (!$data) {
        die("Error: No se pudo obtener la información del usuario.");
    }

    $nombre_cuenta = $data['cuenta_nombre'];
    $fb_page_id = $data['fb_page_id'];
    $ig_user_id = $data['ig_user_id'];
    $fb_page_name = $data['fb_page_name'];
    $sede_nombre = $data['sede_nombre'];
    $id_cuenta = $data['id_cuenta'];
    $id_nivel_usuario = $data['id_nivel_usuario'];

    if ($id_nivel_usuario == 1) {
        header("Location: dashboard-sede.php?id_cuenta=$id_cuenta");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
        <link rel="icon" type="image/png" href="../assets/img/favicon.png">
        <title>Dashboard de indicadores</title>
        <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
        <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
        <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
        <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
        <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
        <style>
            /* Eliminar completamente el scroll horizontal */
            html, body {
                overflow-x: hidden;
                width: 100%;
            }

            /* Eliminar scroll en tablas */
            .table-responsive {
                overflow: visible !important;
            }

            /* Asegurar que el contenido no cause overflow */
            .main-content {
                overflow: hidden;
            }

            /* Ajustar contenedor principal */
            .container-fluid {
                padding-right: 15px;
                padding-left: 15px;
                margin-right: auto;
                margin-left: auto;
                width: 100%;
            }

            /* Ajustar tablas para que no necesiten scroll */
            .table {
                width: 100%;
                table-layout: auto;
            }

            /* Asegurar que las tarjetas no causen overflow */
            .card {
                overflow: visible;
            }

            .ps__rail-x {
                display: none !important;
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
                        <a class="nav-link active bg-gradient-dark text-white" href="../pages/dashboard-sede.php">
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
                            <a class="nav-link text-dark" href="../pages/sign-in.php">
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
                        <ul class="ms-md-auto pe-md-3 navbar-nav d-flex align-items-center justify-content-end">
                            <li class="nav-item d-flex align-items-center">
                                <a class="btn btn-outline-dark btn-sm mb-0 me-3 border-0" target="_blank" href="#">Red de clínicas</a>
                            </li>
                            <?php if (isset($_SESSION['nivel_usuario']) && $_SESSION['nivel_usuario'] == 3): ?>
                            <li class="nav-item px-3 d-flex align-items-center">
                                <div id="log"></div>
                                <button id="sync-button" class="d-flex align-items-center nav-link text-body p-0">
                                    <i class="material-symbols-rounded fixed-plugin-button-nav">autorenew</i>
                                </button>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item dropdown pe-3 d-flex align-items-center">
                                <a href="javascript:;" class="nav-link text-body p-0" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="material-symbols-rounded">notifications</i>
                                </a>
                            </li>
                            <li class="nav-item d-flex align-items-center">
                                <a href="../pages/sign-in.php" class="nav-link text-body font-weight-bold px-0">
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
                        <p class="mb-4">Monitorea el alcance, seguidores, interacciones y visualizaciones de las sedes y unidades.</p>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-gradient-success border-0 shadow-lg shadow-success">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Alcance</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-reach-top" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape bg-transparent text-center">
                                        <i class="material-symbols-rounded opacity-10">public</i>
                                    </div>
                                </div>
                                <p id="meta-reach-sede-top" class="text-sm mt-0 mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-reach-change-top" class="font-weight-bolder"></span>
                                    <small id="meta-reach-time-top" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-gradient-success border-0 shadow-success">
                            <div class="card-header bg-transparent p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Seguidores</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-followers-top" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape text-center">
                                        <i class="material-symbols-rounded opacity-10">group</i>
                                    </div>
                                </div>
                                <p id="meta-followers-sede-top" class="text-sm mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-followers-change-top" class="font-weight-bolder"></span>
                                    <small id="meta-followers-time-top" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-gradient-success border-0 shadow-success">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Interacciones</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-engagements-top" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape text-center">
                                        <i class="material-symbols-rounded opacity-10">thumb_up</i>
                                    </div>
                                </div>
                                <p id="meta-engagements-sede-top" class="text-sm mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-engagements-change-top" class="font-weight-bolder"></span>
                                    <small id="meta-engagements-time-top" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card bg-gradient-success border-0 shadow-success">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Visualizaciones</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-views-top" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape text-center">
                                        <i class="material-symbols-rounded opacity-10">visibility</i>
                                    </div>
                                </div>
                                <p id="meta-views-sede-top" class="text-sm mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-views-change-top" class="font-weight-bolder"></span>
                                    <small id="meta-views-time-top" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-gradient-danger border-0 shadow-lg shadow-danger">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Alcance</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-reach-bottom" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape bg-transparent text-center">
                                        <i class="material-symbols-rounded opacity-10">public</i>
                                    </div>
                                </div>
                                <p id="meta-reach-sede-bottom" class="text-sm mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-reach-change-bottom" class="font-weight-bolder"></span>
                                    <small id="meta-reach-time-bottom" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-gradient-danger border-0 shadow-danger">
                            <div class="card-header bg-transparent p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Seguidores</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-followers-bottom" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape bg-transparent text-center">
                                        <i class="material-symbols-rounded opacity-10">group</i>
                                    </div>
                                </div>
                                <p id="meta-followers-sede-bottom" class="text-sm mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-followers-change-bottom" class="font-weight-bolder"></span>
                                    <small id="meta-followers-time-bottom" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card bg-gradient-danger border-0 shadow-danger">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Interacciones</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-engagements-bottom" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape bg-transparent text-center">
                                        <i class="material-symbols-rounded opacity-10">thumb_up</i>
                                    </div>
                                </div>
                                <p id="meta-engagements-sede-bottom" class="text-sm mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-engagements-change-bottom" class="font-weight-bolder"></span>
                                    <small id="meta-engagements-time-bottom" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card bg-gradient-danger border-0 shadow-danger">
                            <div class="card-header p-2 ps-3 bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize text-white">Visualizaciones</p>
                                        <h4 class="mt-2 mb-0">
                                            <span id="meta-total-views-bottom" class="text-white"></span>
                                        </h4>
                                    </div>
                                    <div class="icon icon-lg icon-shape bg-transparent text-center">
                                        <i class="material-symbols-rounded opacity-10">visibility</i>
                                    </div>
                                </div>
                                <p id="meta-views-sede-bottom" class="text-sm mb-0 text-capitalize text-white"></p>
                            </div>
                            <hr class="light horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm text-white">
                                    <span id="meta-views-change-bottom" class="font-weight-bolder"></span>
                                    <small id="meta-views-time-bottom" class="text-white ms-1"></small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row my-4">
                    <div class="col-12 mb-md-0 mb-4">
                        <div class="card shadow border-0">
                            <div class="card-header pb-0">
                                <div class="row">
                                    <div class="col-lg-6 col-7">
                                        <h6>Ranking de cuentas</h6>
                                        <p id="indicador-seleccionado" class="text-sm mb-0">Costo por resultados</p>
                                    </div>
                                    <div class="col-lg-6 col-5 my-auto text-end">
                                        <div class="dropdown float-lg-end pe-4">
                                            <a class="cursor-pointer" id="indicadores" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="material-symbols-rounded opacity-10">arrow_drop_down</i>
                                            </a>
                                            <ul class="dropdown-menu px-2 py-3 ms-sm-n4 ms-n5" aria-labelledby="indicadores">
                                                <li>
                                                    <a class="dropdown-item border-radius-md" href="javascript:;">Alcance</a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item border-radius-md" href="javascript:;">Seguidores</a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item border-radius-md" href="javascript:;">Interacción</a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item border-radius-md" href="javascript:;">Costo por resultados</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-0 pb-2">
                                <div class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cuenta</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 id="nombre-cuenta" class="mb-0 text-sm"></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span id="valor" class="text-xs font-weight-bold"></span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-md-0 mb-4 d-none">
                        <div class="card shadow border-0">
                            <div class="card-header pb-0">
                                <div class="row">
                                    <div class="col-lg-6 col-7">
                                        <h6>Páginas web</h6>
                                        <p class="text-sm mb-0">Más visitadas (No hay datos para mostrar)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-0 pb-2">
                                <div class="table-responsive d-none">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cuenta</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo La Victoria</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 4,817 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Dermoesthetik</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 4,729 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Juan Bautista</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 4,694 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">MedikCenter</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 4,683 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Centro de Salud Ocupacional</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 4,383 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Huaraz</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 4,109 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Jesús del Norte</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 4,024 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Salud</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 3,978 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Trujillo</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 3,690 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Arequipa</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 3,632 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Santa Martha del Sur</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 3,353 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Cardiomóvil</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 2,353 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Chacarilla</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 2,214 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Gabriel</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 2,014 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Fundación Alvartez</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 1,453 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Surco</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 814 </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Tomomedic</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold"> 183 </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-md-0 mb-4 d-none">
                        <div class="card shadow border-0">
                            <div class="card-header pb-0">
                                <div class="row">
                                    <div class="col-lg-6 col-7">
                                        <h6>Google My Business</h6>
                                        <p class="text-sm mb-0">Valoraciones (No hay datos para mostrar)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-0 pb-2">
                                <div class="table-responsive d-none">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cuenta</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Salud</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 4.5 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Santa Martha del Sur</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 4.5 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Dermoesthetik</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 4.4 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Fundación Alvartez</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 4.4 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Trujillo</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 4.3 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Chacarilla</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 4.2 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">MedikCenter</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 3.5 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Tomomedic</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 3.3 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Centro de Salud Ocupacional</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 2.5 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Arequipa</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 2.4 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Gabriel</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 2.3 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Jesús del Norte</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 2.3 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Huaraz</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 2.3 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo Surco</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 1.5 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Pablo La Victoria</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 1.3 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">Cardiomóvil</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 1.2 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">San Juan Bautista</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <div class="row align-items-center">
                                                        <span class="col-auto text-xs font-weight-bold pe-0"> 1.1 </span>
                                                        <i class="col-auto material-symbols-rounded opacity-10 ps-0 text-warning">star</i>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
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
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                fetch("api.php?endpoint=meta/sedes-extremos")
                    .then(res => res.json())
                    .then(data => {
                        const fields = [
                            { key: 'reach', label: 'alcance' },
                            { key: 'followers', label: 'seguidores' },
                            { key: 'engagements', label: 'interacciones' },
                            { key: 'views', label: 'visualizaciones' }
                        ];

                        fields.forEach(({ key, label }) => {
                            const fieldData = data[label];

                            const formatCambio = (cambio) => {
                                return (cambio >= 0 ? `+${cambio}` : `${cambio}`) + '%';
                            };

                            // Top
                            const topValue = document.getElementById(`meta-total-${key}-top`);
                            const topChange = document.getElementById(`meta-${key}-change-top`);
                            const topSede = document.getElementById(`meta-${key}-sede-top`);
                            const topTime = document.getElementById(`meta-${key}-time-top`);

                            if (topValue) topValue.textContent = fieldData.max.valor.toLocaleString();
                            if (topChange) topChange.textContent = formatCambio(fieldData.max.cambio);
                            if (topSede) topSede.textContent = fieldData.max.cuenta;
                            if (topTime) topTime.textContent = 'últimos 30 días';

                            // Bottom
                            const bottomValue = document.getElementById(`meta-total-${key}-bottom`);
                            const bottomChange = document.getElementById(`meta-${key}-change-bottom`);
                            const bottomSede = document.getElementById(`meta-${key}-sede-bottom`);
                            const bottomTime = document.getElementById(`meta-${key}-time-bottom`);

                            if (bottomValue) bottomValue.textContent = fieldData.min.valor.toLocaleString();
                            if (bottomChange) bottomChange.textContent = formatCambio(fieldData.min.cambio);
                            if (bottomSede) bottomSede.textContent = fieldData.min.cuenta;
                            if (bottomTime) bottomTime.textContent = 'últimos 30 días';
                        });
                    })
                    .catch(err => console.error("Error al cargar datos de sedes-extremos:", err));

                const indicadorTexto = document.getElementById("indicador-seleccionado");
                const dropdownItems = document.querySelectorAll(".dropdown-menu .dropdown-item");

                // Mapeo de nombres mostrados a parámetros del backend
                const indicadorMap = {
                    "Alcance": "alcance",
                    "Seguidores": "seguidores",
                    "Interacción": "interacciones",
                    "Costo por resultados": "costo"
                };

                // Función que actualiza el ranking según el indicador
                function cargarRanking(indicador = "costo") {
                    fetch(`api.php?endpoint=meta/ranking-cuentas&indicador=${indicador}`)
                        .then(res => res.json())
                        .then(data => {
                            const tbody = document.querySelector(".table tbody");
                            tbody.innerHTML = "";

                            data.forEach(cuenta => {
                                const tr = document.createElement("tr");

                                tr.classList.add("cursor-pointer");
                                tr.onclick = () => {
                                    window.location.href = `dashboard-sede.php?id_cuenta=${encodeURIComponent(cuenta.id_cuenta)}`;
                                };

                                tr.innerHTML = `
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm">${cuenta.cuenta}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        <span class="text-xs font-weight-bold">${cuenta.valor.toLocaleString()}</span>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                        })
                        .catch(err => console.error("Error cargando ranking:", err));
                }

                // Inicial
                cargarRanking();

                // Cambiar al seleccionar del dropdown
                dropdownItems.forEach(item => {
                    item.addEventListener("click", function () {
                        const nombreIndicador = this.textContent.trim();
                        const indicador = indicadorMap[nombreIndicador];

                        if (indicador) {
                            indicadorTexto.textContent = nombreIndicador;
                            cargarRanking(indicador);
                        }
                    });
                });
            });

            document.getElementById('sync-button').addEventListener('click', () => {
                const button = document.getElementById('sync-button');
                const logDiv = document.getElementById('log');

                // Mostrar ícono de carga
                button.disabled = true;
                const originalText = button.innerHTML;
                logDiv.innerHTML = '⌛ Ejecutando...';

                fetch('actualizar_datos_meta.php?token=mi_token_secreto123')
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            logDiv.innerHTML = `<span style="color:red">🚫 ${data.error}</span>`;
                        } else if (Array.isArray(data.resultados)) {
                            logDiv.innerHTML = '';
                        } else {
                            logDiv.innerHTML = `<span style="color:red">⚠️ Respuesta inesperada del servidor</span>`;
                            console.error('Respuesta inesperada:', data);
                        }
                    })
                    .catch(err => {
                        logDiv.innerHTML = `<span style="color:red">❌ Error: ${err.message}</span>`;
                        console.error('Error al ejecutar:', err);
                    })
                    .finally(() => {
                        button.disabled = false;
                    });
            });
        </script>
        <!-- Github buttons -->
        <script async defer src="https://buttons.github.io/buttons.js"></script>
        <!-- Control Center for Material Dashboard: parallax effects, scripts for the example pages etc -->
        <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
    </body>
</html>