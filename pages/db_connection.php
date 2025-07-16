<?php
$host = '192.1.0.239';
$db   = 'alertia';
$user = 'alertia';
$pass = 'Casita123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa prepared statements nativos si es posible
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo 'Conectado con éxito a MySQL';
} catch (PDOException $e) {
    die('❌ Error de conexión con MySQL: ' . $e->getMessage());
}
?>
