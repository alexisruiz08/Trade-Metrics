<?php
// api/change_password.php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/csrf.php';
start_secure_session();
require 'db_connect.php';
header('Content-Type: application/json');

// 1. Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['current']) || !isset($data['new']) || !isset($data['confirm']) || !csrf_verify($data['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

$current_pass = $data['current'];
$new_pass = $data['new'];
$confirm_pass = $data['confirm'];

// 3. Validar nueva contraseña
if ($new_pass !== $confirm_pass) {
    echo json_encode(['success' => false, 'message' => 'Las nuevas contraseñas no coinciden.']);
    exit;
}
if (strlen($new_pass) < 8) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
    exit;
}

// 4. Verificar la contraseña actual
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($current_pass, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta.']);
    exit;
}

// 5. Hashear y actualizar la nueva contraseña
$new_password_hash = password_hash($new_pass, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->bind_param("si", $new_password_hash, $user_id);

if ($stmt->execute()) {
    // Invalida el ID de sesión actual: si alguien había fijado/robado la sesión,
    // deja de servir apenas cambia la contraseña.
    session_regenerate_id(true);
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada con éxito.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña.']);
}

$stmt->close();
$conn->close();
?>