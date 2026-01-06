<?php
// ajax/search_clients.php
require_once '../config.php';

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';
$search = trim($search);

$results = [];

if (strlen($search) >= 2) {
    $sql = "SELECT id, name, phone FROM clients 
            WHERE name LIKE ? OR phone LIKE ? 
            ORDER BY name LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search%", "%$search%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($results);
?>