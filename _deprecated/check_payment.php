<?php
session_start();
require 'api/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- LÓGICA DE VERIFICACIÓN ---
// 1. Consultar estado actual en BD
$stmt = $conn->prepare("SELECT subscription_status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if ($user['subscription_status'] === 'active') {
    // Si ya está activo, entrar.
    header('Location: app.php');
    exit;
}

// 2. (OPCIONAL) Aquí podrías llamar a la API de Coinbase para ver si el pago 
// se completó recientemente y actualizar la BD si el webhook falló.
// Por ahora, simplemente redirigimos de vuelta a pago.php con un error si sigue pending.

header('Location: pago.php?error=payment_not_found');
exit;
?>