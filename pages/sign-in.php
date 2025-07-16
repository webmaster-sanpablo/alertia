<?php
    session_start();
    $error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">

    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Iniciar sesión | Dashboard</title>
        <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
        <link rel="icon" type="image/png" href="../assets/img/favicon.png">
        <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
        <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
        <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
        <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
        <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    </head>

    <body class="bg-gray-200">
        <main class="main-content mt-0">
            <div class="page-header align-items-start min-vh-100" style="background-image: url('https://images.unsplash.com/photo-1497294815431-9365093b7331?auto=format&fit=crop&w=1950&q=80');">
                <span class="mask bg-gradient-dark opacity-6"></span>
                <div class="container my-auto">
                    <div class="row">
                        <div class="col-lg-4 col-md-8 col-12 mx-auto">
                            <div class="card z-index-0 fadeIn3 fadeInBottom">
                                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2 bg-transparent">
                                    <div class="bg-gradient-dark shadow-dark border-radius-lg py-3 pe-1">
                                        <h4 class="text-white font-weight-bolder text-center my-2">Iniciar sesión</h4>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if ($error): ?>
                                    <div class="alert alert-danger text-white" role="alert"><?= htmlspecialchars($error) ?></div>
                                    <?php endif; ?>
                                    <form role="form" method="POST" action="login.php" class="text-start">
                                        <div class="input-group input-group-outline my-3">
                                            <input type="email" name="correo" class="form-control" placeholder="correo electrónico" required>
                                        </div>
                                        <div class="input-group input-group-outline mb-3">
                                            <input type="password" name="clave" class="form-control" placeholder="contraseña" required>
                                        </div>
                                        <div class="form-check form-switch d-flex align-items-center mb-3">
                                            <input class="form-check-input" type="checkbox" id="rememberMe">
                                            <label class="form-check-label mb-0 ms-3" for="rememberMe">Recordarme</label>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" class="btn bg-gradient-dark w-100 my-4 mb-2">Ingresar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>
