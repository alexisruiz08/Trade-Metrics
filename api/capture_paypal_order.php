<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$orderID = $data['orderID'] ?? '';
$user_id = (int)($data['user_id'] ?? 0);

if ($user_id <= 0 || $user_id !== $_SESSION['user_id'] || empty($orderID)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Aquí capturamos la orden (en producción usarías la API de PayPal con tu Client ID + Secret)
$ch = curl_init("https://api.paypal.com/v2/checkout/orders/$orderID/capture");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer TU_ACCESS_TOKEN_AQUI'  // ← Obtén token con Client ID + Secret
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['status']) && $result['status'] === 'COMPLETED') {
    // Activación segura
    $stmt = $conn->prepare("UPDATE users SET 
                                subscription_status = 'active', 
                                subscription_expires_at = '9999-12-31' 
                            WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Pago no completado']);
}

$conn->close();
?>