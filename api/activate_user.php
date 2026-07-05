<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($data['user_id'] ?? 0);

if ($user_id <= 0 || !isset($_SESSION['user_id']) || $_SESSION['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET 
                            subscription_status = 'active', 
                            subscription_expires_at = '9999-12-31' 
                        WHERE id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al activar']);
}

$stmt->close();
$conn->close();
?>