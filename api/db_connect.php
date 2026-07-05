<?php
// api/db_connect.php
// 
// ESTE ARCHIVO ES CRÍTICO.
// Rellena la información de la base de datos que creaste en cPanel.

$servername = "localhost"; 
$username = "bbrpkihlil_trademetrics_user"; 
$password = "chester5396";
$dbname = "bbrpkihlil_trademetrics_db"; 

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Establecer charset a UTF-8 (buena práctica para español)
$conn->set_charset("utf8");

// Verificar conexión
if ($conn->connect_error) {
    // No exponemos el detalle real del error (host, driver, etc.) al cliente.
    error_log('DB connection failed: ' . $conn->connect_error);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.'
    ]);
    exit();
}

// Si la conexión es exitosa, el script termina y la variable $conn estará disponible
// para cualquier otro script que haga 'require' de este archivo.
?>