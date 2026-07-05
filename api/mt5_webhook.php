<?php
// mt5_webhook.php - V2.3 con soporte para múltiples cuentas (account_tag)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/rate_limiter.php';

$log_file = "webhook_errors.log";
function log_error($msg) {
    global $log_file;
    error_log("[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL, 3, $log_file);
}

// 1. Verificar Token
if (!isset($_POST['token'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Token no recibido"]);
    exit;
}
$mt5_token = $_POST['token'];

// Frena intentos automatizados de adivinar un token válido.
if (!rate_limit_check('mt5_token_auth', client_ip(), 30, 600)) {
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Demasiados intentos"]);
    exit;
}

// 2. Conexión y Autenticación
require 'db_connect.php';
$stmt = $conn->prepare("SELECT id FROM users WHERE mt5_token = ? LIMIT 1");
$stmt->bind_param("s", $mt5_token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Token invalido"]);
    $stmt->close();
    $conn->close();
    exit;
}
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// 3. Decodificar Transacciones + obtener account_number
if (!isset($_POST['json_data']) || empty($_POST['json_data'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "json_data vacío"]);
    exit;
}

$json_data = $_POST['json_data'];
$json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
$json_data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json_data);

$transactions = json_decode($json_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_error("JSON Decode failed: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Formato JSON inválido"]);
    exit;
}

// ← NUEVO: obtener account_number del POST
$account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : null;
if ($account_number === '' || $account_number === '0') {
    $account_number = null;
}

// 4. Preparar SQL con account_tag
$sql = "INSERT INTO transactions 
            (user_id, ticket, timestamp, type, symbol, amount, balance_after, risk_r, buysell_type, account_tag) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            timestamp       = VALUES(timestamp),
            type            = VALUES(type),
            symbol          = VALUES(symbol),
            amount          = VALUES(amount),
            balance_after   = VALUES(balance_after),
            risk_r          = VALUES(risk_r),
            buysell_type    = VALUES(buysell_type),
            account_tag     = VALUES(account_tag)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    log_error("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error al preparar consulta"]);
    exit;
}

// 5. Insertar Transacciones
$inserted = 0;
$total = count($transactions);
foreach ($transactions as $tx) {
    if (!isset($tx['ticket']) || !isset($tx['timestamp']) || !isset($tx['type']) ||
        !isset($tx['amount']) || !isset($tx['balance_after'])) {
        // Logueamos las claves realmente recibidas: si el EA renombra un campo
        // (ej. "ticket" -> "order"), esto lo hace evidente en el log en vez de "N/A".
        log_error("Datos incompletos. Claves recibidas: " . implode(',', array_keys((array)$tx)));
        continue;
    }

    // No confiamos en el cast numérico ciego: un valor no numérico casteado a (int)/(float)
    // se volvería 0 en silencio y podría pisar otra operación vía ON DUPLICATE KEY UPDATE.
    if (!is_numeric($tx['ticket']) || !is_numeric($tx['amount']) || !is_numeric($tx['balance_after'])) {
        log_error("Datos no numéricos para ticket " . json_encode($tx['ticket']));
        continue;
    }

    $ticket        = (int)$tx['ticket'];
    $timestamp     = $tx['timestamp'];
    $type          = $tx['type'];
    $symbol        = $tx['symbol'] ?? null;
    $amount        = (float)$tx['amount'];
    $balance_after = (float)$tx['balance_after'];
    $risk_r        = (isset($tx['risk_r']) && is_numeric($tx['risk_r'])) ? (float)$tx['risk_r'] : 0.0;
    $buysell_type  = $tx['buysell_type'] ?? 'N/A';
    $account_tag   = $account_number;   // mismo valor para todas las tx de este envío

    $stmt->bind_param("iisssdddss",
        $user_id, $ticket, $timestamp, $type, $symbol,
        $amount, $balance_after, $risk_r, $buysell_type, $account_tag
    );

    if ($stmt->execute()) {
        $inserted++;
    } else {
        log_error("Execute falló para ticket $ticket : " . $stmt->error);
    }
}

$stmt->close();
$conn->close();

$rejected = $total - $inserted;
// Antes esto siempre devolvía "success" aunque 0 transacciones se hubieran insertado,
// lo que ocultó un incidente real donde el EA cambió el formato del payload.
$status = 'success';
if ($total > 0 && $inserted === 0) {
    $status = 'error';
} elseif ($rejected > 0) {
    $status = 'partial';
}

http_response_code(200);
echo json_encode([
    "status" => $status,
    "transactions_processed" => $inserted,
    "transactions_rejected" => $rejected
]);
?>