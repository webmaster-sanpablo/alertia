<?php
    session_start();
    require_once 'db_connection.php';

    // if (isset($_SESSION['id_usuario'])) {
    //     header("Location: sign-in.php");
    //     exit;
    // }

    $id_usuario = $_SESSION['id_usuario'];

    // Obtener datos del usuario y su cuenta
    $sql = "
        SELECT u.nombres, u.apellidos, c.id_cuenta, c.nombre AS cuenta_nombre, 
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
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
        <link rel="icon" type="image/png" href="../assets/img/favicon.png">
        <title>Alertia | Cuentas</title>
        <!--     Fonts and icons     -->
        <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
        <!-- Nucleo Icons -->
        <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
        <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
        <!-- Font Awesome Icons -->
        <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
        <!-- Material Icons -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
        <!-- CSS Files -->
        <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    </head>

    <body class="g-sidenav-show bg-gray-100">
        <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2  bg-white my-2" id="sidenav-main">
            <div class="sidenav-header">
                <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
                <a class="navbar-brand px-4 py-3 m-0" href=" https://demos.creative-tim.com/material-dashboard/pages/dashboard " target="_blank">
                    <img src="../assets/img/logo-ct-dark.png" class="navbar-brand-img" width="26" height="26" alt="main_logo">
                    <span class="ms-1 text-sm text-dark">Alertia</span>
                </a>
            </div>
            <hr class="horizontal dark mt-0 mb-2">
            <div class="collapse navbar-collapse  w-auto " id="sidenav-collapse-main">
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
                        <a class="nav-link active bg-gradient-dark text-white" href="../pages/cuentas.php">
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
        <div class="main-content position-relative max-height-vh-100 h-100">
            <!-- Navbar -->
            <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
                <div class="container-fluid py-1 px-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                            <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
                            <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Cuentas de Meta</li>
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
            <div class="container-fluid px-2 px-md-4">
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Cuentas de Meta</h6>
                        <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#modalNuevaCuenta">
                            <i class="material-symbols-rounded align-middle">add</i> Nueva cuenta
                        </button>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <div class="table-responsive p-3">
                            <table class="table align-items-center mb-0" id="tablaCuentas">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th class="text-center">Facebook Page ID</th>
                                        <th class="text-center">Facebook Page Name</th>
                                        <th class="text-center">Instagram User ID</th>
                                        <th class="text-center">Instagram User Name</th>
                                        <th class="text-center">Ads Account ID</th>
                                        <th class="text-center">Access Token</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <!-- Se llena con JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Modal Registrar Cuenta -->
                <div class="modal fade" id="modalNuevaCuenta" tabindex="-1" aria-labelledby="modalNuevaCuentaLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form id="formNuevaCuenta">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalNuevaCuentaLabel">Registrar nueva cuenta</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal">
                                        <i class="material-symbols-rounded opacity-10 text-danger">cancel</i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-2">
                                        <div class="input-group input-group-outline">
                                            <label class="form-label">Nombre</label>
                                            <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="nombre" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Facebook Page ID</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="fb_page_id" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Facebook Page Name</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="fb_page_name" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Instagram User ID</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="ig_user_id" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Instagram User Name</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="ig_username" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Ads Account ID</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="ads_account_id" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Access Token</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="access_token" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Registrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Editar Cuenta -->
                <div class="modal fade" id="modalEditarCuenta" tabindex="-1" aria-labelledby="modalEditarCuentaLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form id="formEditarCuenta">
                                <input type="hidden" name="id_cuenta">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalEditarCuentaLabel">Modificar cuenta</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal">
                                        <i class="material-symbols-rounded opacity-10 text-danger">cancel</i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-2">
                                        <div class="input-group input-group-outline">
                                            <label class="form-label">Nombre</label>
                                            <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="nombre" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Facebook Page ID</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="fb_page_id" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Facebook Page Name</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="fb_page_name" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Instagram User ID</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="ig_user_id" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Instagram User Name</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="ig_username" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Ads Account ID</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="ads_account_id" required>
                                            </div>
                                        </div>
                                        <div class="mb-2 col-6">
                                            <div class="input-group input-group-outline">
                                                <label class="form-label">Access Token</label>
                                                <input type="text" class="form-control px-2" onfocus="focused(this)" onfocusout="defocused(this)" name="access_token" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                </div>
                            </form>
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
        <!--   Core JS Files   -->
        <script src="../assets/js/core/popper.min.js"></script>
        <script src="../assets/js/core/bootstrap.min.js"></script>
        <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
        <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                cargarCuentas();

                // Registro
                document.getElementById('formNuevaCuenta').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const form = e.target;
                    const data = Object.fromEntries(new FormData(form));

                    const response = await fetch('api.php?endpoint=cuentas/registrar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    if (result.status === 'ok') {
                        form.reset();
                        bootstrap.Modal.getInstance(document.getElementById('modalNuevaCuenta')).hide();
                        cargarCuentas();
                    } else {
                        alert('Error al registrar cuenta: ' + (result.error || ''));
                    }
                });

                // Edición
                document.getElementById('formEditarCuenta').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const form = e.target;
                    const data = Object.fromEntries(new FormData(form));

                    const response = await fetch('api.php?endpoint=cuentas/editar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    if (result.status === 'ok') {
                        bootstrap.Modal.getInstance(document.getElementById('modalEditarCuenta')).hide();
                        cargarCuentas();
                    } else {
                        alert('Error al actualizar cuenta: ' + (result.error || ''));
                    }
                });
            });

            async function cargarCuentas() {
                const res = await fetch('api.php?endpoint=cuentas/listar');
                const data = await res.json();
                const tbody = document.querySelector('#tablaCuentas tbody');
                tbody.innerHTML = '';

                data.forEach(c => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-xs">${c.nombre}</td>
                        <td class="text-xs text-center">${c.fb_page_id}</td>
                        <td class="text-xs text-center">${c.fb_page_name}</td>
                        <td class="text-xs text-center">${c.ig_user_id}</td>
                        <td class="text-xs text-center">${c.ig_username}</td>
                        <td class="text-xs text-center">${c.ads_account_id}</td>
                        <td class="text-xs text-center">${c.access_token}</td>
                        <td>
                            <button class="btn btn-outline-info btn-edit mb-0 border-0" data-id="${c.id_cuenta}">
                                <i class="material-symbols-rounded fixed-plugin-button-nav">edit</i>
                            </button>
                            <button class="btn btn-outline-danger btn-delete mb-0 border-0" data-id="${c.id_cuenta}">
                                <i class="material-symbols-rounded fixed-plugin-button-nav">delete</i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                document.querySelectorAll('.btn-edit').forEach(btn => {
                    btn.onclick = async () => {
                        const cuentaId = btn.dataset.id;

                        const res = await fetch('api.php?endpoint=cuentas/listar');
                        const cuentas = await res.json();
                        const cuenta = cuentas.find(c => c.id_cuenta == cuentaId);
                        if (!cuenta) return alert('Cuenta no encontrada');

                        const form = document.getElementById('formEditarCuenta');

                        // Llenar campos
                        form.id_cuenta.value = cuenta.id_cuenta ?? '';
                        form.nombre.value = cuenta.nombre ?? '';
                        form.fb_page_id.value = cuenta.fb_page_id ?? '';
                        form.fb_page_name.value = cuenta.fb_page_name ?? '';
                        form.ig_user_id.value = cuenta.ig_user_id ?? '';
                        form.ig_username.value = cuenta.ig_username ?? '';
                        form.ads_account_id.value = cuenta.ads_account_id ?? '';
                        form.access_token.value = cuenta.access_token ?? '';

                        // Mostrar modal
                        const modal = new bootstrap.Modal(document.getElementById('modalEditarCuenta'));
                        modal.show();

                        // Asegurar que labels no se superpongan
                        setTimeout(() => {
                            const form = document.getElementById('formEditarCuenta');

                            form.querySelectorAll('.form-control').forEach(input => {
                                const parent = input.closest('.input-group');

                                // Forzar floating label si tiene valor
                                if (input.value && input.value.trim() !== '') {
                                    input.classList.add('is-filled');
                                    parent.classList.add('is-focused');
                                } else {
                                    input.classList.remove('is-filled');
                                    parent.classList.remove('is-focused');
                                }
                            });
                        }, 300); // Espera a que se abra el modal
                    };
                });

                document.querySelectorAll('.btn-delete').forEach(btn => {
                    btn.onclick = async () => {
                        if (!confirm('¿Eliminar cuenta?')) return;
                        const res = await fetch('api.php?endpoint=cuentas/eliminar', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id_cuenta: btn.dataset.id })
                        });
                        const r = await res.json();
                        if (r.status === 'ok') cargarCuentas();
                        else alert('Error al eliminar: ' + (r.error || ''));
                    };
                });
            }
        </script>

        <!-- Github buttons -->
        <script async defer src="https://buttons.github.io/buttons.js"></script>
        <!-- Control Center for Material Dashboard: parallax effects, scripts for the example pages etc -->
        <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
    </body>
</html>