<?php
session_start();
require_once 'db_connection.php';

$correo = $_POST['correo'] ?? '';
$clave  = $_POST['clave'] ?? '';

if (!$correo || !$clave) {
    header("Location: sign-in.php?error=Completa todos los campos.");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT u.*, c.id_cuenta, c.fb_page_id, c.ig_user_id, c.ads_account_id
        FROM usuario u
        LEFT JOIN cuenta c ON u.id_cuenta = c.id_cuenta
        WHERE u.correo = ? AND u.clave = ?
        LIMIT 1");
    $stmt->execute([$correo, $clave]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['id_usuario']      = $user['id_usuario'];
        $_SESSION['correo']          = $user['correo'];
        $_SESSION['token']           = $user['token']; // token de la tabla usuario
        $_SESSION['id_cuenta']       = $user['id_cuenta'];
        $_SESSION['fb_page_id']      = $user['fb_page_id'];
        $_SESSION['ig_user_id']      = $user['ig_user_id'];
        $_SESSION['ads_account_id']  = $user['ads_account_id'];
        $_SESSION['nivel_usuario']   = $user['id_nivel_usuario'];

        header("Location: dashboard.php");
        exit;
    } else {
        header("Location: sign-in.php?error=Credenciales inválidas.");
        exit;
    }
} catch (PDOException $e) {
    header("Location: sign-in.php?error=Error en la base de datos.");
    exit;
}


