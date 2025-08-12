<?php
require_once('../../controllers/config.php');

header('Content-Type: application/json');

if (!isset($_GET['username']) || empty(trim($_GET['username']))) {
    echo json_encode(['available' => false]);
    exit;
}

$username = trim($_GET['username']);

// Verificar si el usuario existe
$sql = "SELECT id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

echo json_encode([
    'available' => $stmt->num_rows === 0
]);

$stmt->close();
$conn->close();
