<?php
require_once __DIR__ . '/api/session_bootstrap.php';
require_once __DIR__ . '/api/csrf.php';
require_once __DIR__ . '/api/rate_limiter.php';
start_secure_session();
require 'api/db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['email']) || !isset($data['password']) || !csrf_verify($data['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

// Límite de registros por IP para frenar la creación masiva de cuentas.
if (!rate_limit_check('register_ip', client_ip(), 5, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Demasiados registros desde esta conexión. Probá de nuevo más tarde.']);
    exit;
}

$email = $data['email'];
$password = $data['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
    exit;
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);
$mt5_token = bin2hex(random_bytes(16));

// Verificar email duplicado. Mensaje genérico a propósito: no confirmamos si el email
// ya existe o si falló por otra razón, para no facilitar enumeración de usuarios.
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'No se pudo completar el registro con esos datos.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// La app es gratuita: toda cuenta nueva queda activa de entrada.
$status = 'active';
$stmt = $conn->prepare("INSERT INTO users (email, password_hash, mt5_token, subscription_status) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $password_hash, $mt5_token, $status);

if ($stmt->execute()) {
    // Éxito
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No se pudo completar el registro con esos datos.']);
}

$stmt->close();
$conn->close();
?>
