<?php
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_GET['numero_operacion']) || !isset($_GET['producto']) || !isset($_GET['almacen_id'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit();
}

$numero_operacion = (int)$_GET['numero_operacion'];
$producto = (int)$_GET['producto'];
$almacen_id = (int)$_GET['almacen_id'];

try {
    // Obtener el registro anterior al especificado para el almacén específico
    $sql = "SELECT saldo_fisico, saldo_usd 
            FROM almacen_usd_tarjetas_estiba 
            WHERE producto = ? AND almacen_id = ? AND numero_operacion < ? 
            ORDER BY numero_operacion DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $producto, $almacen_id, $numero_operacion);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $registro_anterior = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'registro_anterior' => $registro_anterior
        ]);
    } else {
        // No hay registro anterior (primer movimiento)
        echo json_encode([
            'success' => true,
            'registro_anterior' => ['saldo_fisico' => 0, 'saldo_usd' => 0]
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
