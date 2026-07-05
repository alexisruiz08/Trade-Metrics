<?php
require_once __DIR__ . '/session_bootstrap.php';
start_secure_session();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "No autenticado"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$account_tag = isset($_GET['account_tag']) ? $_GET['account_tag'] : null;

$sql = "
    SELECT ticket, timestamp, type, symbol, amount, balance_after, risk_r, buysell_type
    FROM transactions
    WHERE user_id = ?
";

$types = "i";
$params = [$user_id];

if ($account_tag !== null) {
    $sql .= " AND account_tag = ?";
    $types .= "s";
    $params[] = $account_tag;
}

$sql .= " ORDER BY timestamp ASC, id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($transactions);
?>