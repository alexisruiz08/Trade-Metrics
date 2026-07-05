<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($data['user_id'] ?? 0);

if ($user_id !== $_SESSION['user_id']) {
    echo json_encode(['error' => 'Usuario inválido']);
    exit;
}

// Monto FIJO en el servidor (nadie puede cambiarlo)
$amount = '12.99';

$order = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'amount' => [
            'currency_code' => 'USD',
            'value' => $amount
        ],
        'description' => 'Acceso permanente Trade Metrics - Pago único'
    ]]
];

echo json_encode($order);