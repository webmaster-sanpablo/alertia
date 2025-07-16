<?php
$host = '192.1.0.239'; // IP del servidor Oracle
$port = 1521;           // Puerto estándar
$service = 'alertia';   // Nombre del servicio (puede ser también SID, si aplica)
$user = 'alertia';
$pass = 'Casita123';

// TNS descriptor
$tns = "
(DESCRIPTION =
    (ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))
    (CONNECT_DATA =
      (SERVICE_NAME = $service)
    )
)";

try {
    $pdo = new PDO("oci:dbname=" . $tns, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // echo "✅ Conexión exitosa a Oracle.";
} catch (PDOException $e) {
    die("❌ Error al conectar con Oracle: " . $e->getMessage());
}
?>
