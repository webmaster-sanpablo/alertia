<?php 
$host = '192.1.0.239';
$port = '1521'; // Puerto por defecto de Oracle
$service = 'alertia'; // Puede ser el SID o SERVICE_NAME según configuración
$user = 'alertia';
$pass = 'Casita123';

$dsn = "oci:dbname=//$host:$port/$service;charset=AL32UTF8";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Error al conectar con Oracle: " . $e->getMessage());
}
?>
