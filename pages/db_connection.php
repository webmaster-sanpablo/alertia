<?php
$servername = '192.1.0.239';
$username = 'alertia';
$password = 'Casita123';
$dbname = 'alertia';

// Crear la conexión
$conn = mysqli_connect( $servername, $username, $password, $dbname );

// Verificar la conexión
if ( !$conn ) {
    die( 'Error de conexiÃ³n con MySQL: ' . mysqli_connect_error() );
} else {
    // Establecer el conjunto de caracteres para la conexiÃ³n
    mysqli_set_charset( $conn, 'utf8mb4' );
    // echo 'Conectado';
}
?>


