<?php
// api/get_capital.php (Versión Multi-Usuario)
session_start(); // 1. Iniciar sesión

// 2. Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
$user_id = $_SESSION['user_id']; // 3. Obtener el ID del usuario

require 'db_connect.php'; // Conecta a la BD
header('Content-Type: application/json');

// 4. Modificar el SQL para filtrar por user_id
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'startingCapital' AND user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id); // "i" de integer
$stmt->execute();
$result = $stmt->get_result();

$value = '0'; // Valor por defecto

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $value = $row['setting_value'];
}

echo json_encode(['success' => true, 'value' => $value]);

$stmt->close();
$conn->close();
?>