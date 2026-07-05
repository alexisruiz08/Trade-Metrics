<?php
// get_trades.php - Recupera transacciones para mostrar en tabla

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Conexión y Autenticación (asumimos que incluye db_connect.php y obtiene $user_id)
// Nota: Debes asegurarte de que estas variables estén definidas antes de usarlas.
require 'db_connect.php'; 
// Asumimos que $user_id está disponible aquí, por ejemplo, de una sesión o token.
// Para este ejemplo, lo definimos temporalmente (debes ajustarlo a tu lógica de auth):
// $user_id = $_SESSION['user_id'] ?? 0; 
if (!isset($user_id) || $user_id === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Usuario no autenticado."]);
    exit;
}

// 2. Parámetros de la consulta (Paginación, Filtros, etc.)
// Puedes añadir filtros si los necesitas. Aquí solo nos enfocamos en el user_id.
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// 3. Consulta SQL (¡MODIFICADA!)
// Importante: Asegurarse de seleccionar la nueva columna 'buysell_type'
$sql = "SELECT 
            ticket, 
            timestamp, 
            type, 
            symbol, 
            amount, 
            balance_after, 
            risk_r,
            buysell_type  /* <--- ¡NUEVO CAMPO AGREGADO! */
        FROM 
            transactions 
        WHERE 
            user_id = ? 
        ORDER BY 
            timestamp DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error SQL al preparar la consulta."]);
    exit;
}

// Tipos de datos: i (user_id) i (limit) i (offset)
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = [
        'ticket'        => (int)$row['ticket'],
        'timestamp'     => $row['timestamp'],
        'type'          => $row['type'],
        'symbol'        => $row['symbol'],
        'amount'        => (float)$row['amount'],
        'balance_after' => (float)$row['balance_after'],
        'risk_r'        => (float)$row['risk_r'],
        'buysell_type'  => $row['buysell_type'] /* <--- ¡NUEVO DATO EN EL ARRAY! */
    ];
}

$stmt->close();
$conn->close();

// 4. Devolver la respuesta JSON
http_response_code(200);
echo json_encode(["status" => "success", "transactions" => $transactions]);
?>