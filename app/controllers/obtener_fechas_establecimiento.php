<?php
require_once('config.php');

header('Content-Type: application/json');

$establecimientoId = $_GET['establecimiento'] ?? 0;

// Obtener la última fecha disponible para el establecimiento
$query = "SELECT MAX(c.fecha_operacion) as ultima_fecha 
          FROM caja_principal c
          LEFT JOIN entradas_caja_principal e ON c.numero_operacion = e.numero_operacion
          LEFT JOIN salidas_caja_principal s ON c.numero_operacion = s.numero_operacion
          WHERE e.establecimiento_codigo = ? OR s.establecimiento_codigo = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $establecimientoId, $establecimientoId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    'ultima_fecha' => $data['ultima_fecha'] ?? null
]);
