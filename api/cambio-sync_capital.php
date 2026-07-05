<?php
// api/sync_capital.php - V14.0 (Multi-Usuario)

require_once __DIR__ . '/rate_limiter.php';

// 1. Verificar Token
if (!isset($_POST['token'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Token no recibido"]);
    exit;
}
$mt5_token = $_POST['token'];

// Frena intentos automatizados de adivinar un token válido.
if (!rate_limit_check('mt5_token_auth', client_ip(), 30, 600)) {
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Demasiados intentos"]);
    exit;
}

// 2. Conexión y autenticación del token
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
// Token válido, obtenemos el user_id
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();


// 3. Verificar Balance
if (!isset($_POST['balance']) || !is_numeric($_POST['balance'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Balance no recibido o inválido"]);
    exit;
}
$balance = (float)$_POST['balance'];


// 4. Preparar y ejecutar la actualización en la BD (con user_id)
// user_id es parte de la Primary Key compuesta (user_id, setting_key)
$sql = "INSERT INTO settings (user_id, setting_key, setting_value)
        VALUES (?, 'startingCapital', ?)
        ON DUPLICATE KEY UPDATE setting_value = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('sync_capital prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno del servidor"]);
    exit;
}

// 'i' (user_id) 's' (startingCapital) 's' (value para INSERT) 's' (value para UPDATE)
$stmt->bind_param("iss", $user_id, $balance, $balance);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Capital actualizado a " . $balance]);
} else {
    error_log('sync_capital execute failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno del servidor"]);
}

$stmt->close();
$conn->close();
?>