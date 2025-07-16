<?php
// callback.php
session_start();
require_once 'db_connection.php';

$app_id = '1065485985716458';
$app_secret = '9ae25c7af5478a0a320eceaa802cfc8e';
$redirect_uri = 'https://localhost/alertia/pages/callback.php'; // Debe coincidir con la redirección registrada

if (!isset($_GET['code'])) {
    die('Error: No se proporcionó código de autorización.');
}

$code = $_GET['code'];

// 1. Obtener access_token corto
$token_url = "https://graph.facebook.com/v18.0/oauth/access_token?client_id=$app_id&redirect_uri=" . urlencode($redirect_uri) . "&client_secret=$app_secret&code=$code";
$response = file_get_contents($token_url);
$data = json_decode($response, true);

if (!isset($data['access_token'])) {
    die('Error al obtener access token corto: ' . $response);
}

$short_token = $data['access_token'];

// 2. Intercambiar por un access_token largo
$long_token_url = "https://graph.facebook.com/v18.0/oauth/access_token?grant_type=fb_exchange_token&client_id=$app_id&client_secret=$app_secret&fb_exchange_token=$short_token";
$response = file_get_contents($long_token_url);
$data = json_decode($response, true);

if (!isset($data['access_token'])) {
    die('Error al obtener token largo: ' . $response);
}

$long_token = $data['access_token'];

// 3. Obtener páginas administradas con ese token
$pages_url = "https://graph.facebook.com/v18.0/me/accounts?access_token=$long_token";
$response = file_get_contents($pages_url);
$pages = json_decode($response, true);

if (!isset($pages['data'])) {
    die('Error al obtener páginas: ' . $response);
}

// 4. Guardar los tokens de página
foreach ($pages['data'] as $page) {
    $page_id = $page['id'];
    $page_name = $page['name'];
    $page_token = $page['access_token'];

    // Guarda o actualiza en la tabla `cuenta`
    $stmt = $pdo->prepare("SELECT id_cuenta FROM cuenta WHERE fb_page_id = ?");
    $stmt->execute([$page_id]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $update = $pdo->prepare("UPDATE cuenta SET access_token = ? WHERE fb_page_id = ?");
        $update->execute([$page_token, $page_id]);
    } else {
        $insert = $pdo->prepare("INSERT INTO cuenta (fb_page_id, nombre, access_token, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $insert->execute([$page_id, $page_name, $page_token]);
    }
    echo "<p>✅ Token guardado para: $page_name ($page_id)</p>";
}

echo '<p><strong>Proceso completado con éxito.</strong></p>';
