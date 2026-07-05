<?php
require 'api/db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

$email = $data['email'];
$password = $data['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit;
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);
$mt5_token = bin2hex(random_bytes(16));

// Verificar email duplicado
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Este email ya está registrado.']);
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
    echo json_encode(['success' => false, 'message' => 'Error al crear la cuenta.']);
}

$stmt->close();
$conn->close();
?>