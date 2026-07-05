<?php
// api/add_trade.php
// Inserta una nueva operación en la base de datos

require 'db_connect.php'; // Conecta a la BD
header('Content-Type: application/json');

// Obtenemos los datos enviados desde JavaScript (en formato JSON)
$data = json_decode(file_get_contents('php://input'), true);

// Usamos declaraciones preparadas (prepared statements) para MÁXIMA SEGURIDAD
// Esto previene la inyección SQL.
$stmt = $conn->prepare("INSERT INTO trades (entry, tp, sl, capital, result, exitPrice, notes, trade_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

// Asignamos las variables. Usamos '?? null' para manejar campos vacíos.
$entry = $data['entry'] ?? null;
$tp = $data['tp'] ?? null;
$sl = $data['sl'] ?? null;
$capital = $data['capital'] ?? null;
$result = $data['result'] ?? null;
$exitPrice = $data['exitPrice'] ?? null;
$notes = $data['notes'] ?? null;
$date = $data['date'] ?? null;
if (empty($date)) $date = null; // Tratar fecha vacía como NULL

// 'ddddssss' indica el tipo de dato:
// d = decimal/double (flotante)
// s = string
// i = integer
$stmt->bind_param("ddddssss", $entry, $tp, $sl, $capital, $result, $exitPrice, $notes, $date);

if ($stmt->execute()) {
    // Si la operación fue exitosa
    $new_id = $conn->insert_id; // Obtenemos el ID auto-generado
    
    // Devolvemos el objeto 'trade' completo, tal como lo espera el JS
    $new_trade = [
        'id' => $new_id,
        'entry' => (float)$entry,
        'tp' => (float)$tp,
        'sl' => (float)$sl,
        'capital' => (float)$capital,
        'result' => $result,
        'exitPrice' => (float)$exitPrice,
        'notes' => $notes,
        'date' => $date
    ];
    echo json_encode(['success' => true, 'trade' => $new_trade]);
} else {
    // Si hubo un error
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>