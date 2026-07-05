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
    // Si la conexión falla, se envía una respuesta de error en JSON y se termina el script.
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Si la conexión es exitosa, el script termina y la variable $conn estará disponible
// para cualquier otro script que haga 'require' de este archivo.
?>