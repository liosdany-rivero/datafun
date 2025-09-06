<?php
require_once('config.php');

header('Content-Type: application/json');

if (isset($_GET['fecha'])) {
    $fecha = $_GET['fecha'];

    $sql = "SELECT tasa FROM tasas WHERE fecha = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $fecha);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'tasa' => $row['tasa']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró tasa para esta fecha']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Fecha no proporcionada']);
}

$conn->close();
