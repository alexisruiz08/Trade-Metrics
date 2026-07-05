<?php
// api/reset_trades.php
// Borra TODAS las operaciones de la tabla

require 'db_connect.php'; // Conecta a la BD
header('Content-Type: application/json');

// TRUNCATE TABLE es más rápido que DELETE FROM
// Resetea el contador AUTO_INCREMENT a 1
$sql = "TRUNCATE TABLE trades";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'All trades have been deleted.']);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$conn->close();
?>