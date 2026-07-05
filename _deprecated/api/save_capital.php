<?php
// api/save_capital.php
// Guarda o actualiza el capital inicial

require 'db_connect.php'; // Conecta a la BD
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$value = $data['value'] ?? '0';

// Usamos ON DUPLICATE KEY UPDATE para insertar si no existe, o actualizar si ya existe.
// 'startingCapital' es la Primary Key que definimos en el SQL.
$stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) 
                        VALUES ('startingCapital', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?");

$stmt->bind_param("ss", $value, $value);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>