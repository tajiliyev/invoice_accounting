<?php
require_once '../config.php';

$client_id = (int)$_GET['client_id'] ?? 0;

$stmt = $pdo->prepare("SELECT id, name, phone FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'client' => $client ?: null
]);
?>