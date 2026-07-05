<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "No autenticado"]);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT DISTINCT account_tag FROM transactions WHERE user_id = ? AND account_tag IS NOT NULL ORDER BY account_tag ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$accounts = [];
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row['account_tag'];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($accounts);
?>