<?php
// api/sync_capital.php - V14.0 (Multi-Usuario)

// 1. Verificar Token
if (!isset($_POST['token'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Token no recibido"]);
    exit;
}
$mt5_token = $_POST['token'];

// 2. Conexión y Autenticación del Token
// ¡ATENCIÓN! Asegúrate que la ruta a 'db_connect.php' es correcta. 
// Si está en el mismo directorio 'api/', usa 'db_connect.php'.
require 'db_connect.php'; 

$stmt = $conn->prepare("SELECT id FROM users WHERE mt5_token = ? LIMIT 1");
$stmt->bind_param("s", $mt5_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Token invalido o no asociado a un usuario"]);
    $stmt->close();
    $conn->close();
    exit;
}
// ¡Token válido! Obtenemos el user_id
$user = $result->fetch_assoc();
$user_id = $user['id']; 
$stmt->close();


// 3. Verificar Balance
if (!isset($_POST['balance'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Balance no recibido"]);
    exit;
}
$balance = (float)$_POST['balance'];


// 4. Preparar y ejecutar la actualización en la BD (ahora con user_id)
// Usamos user_id como parte de la Primary Key compuesta (user_id, setting_key)
$sql = "INSERT INTO settings (user_id, setting_key, setting_value) 
        VALUES (?, 'startingCapital', ?)
        ON DUPLICATE KEY UPDATE setting_value = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "SQL Prepare failed: " . $conn->error]);
    exit;
}

// 5. Añadir user_id al bind_param
// 'i' (user_id) 's' (startingCapital) 's' (value para INSERT) 's' (value para UPDATE)
$stmt->bind_param("iss", $user_id, $balance, $balance); 

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Capital actualizado a " . $balance]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "SQL Execute failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>