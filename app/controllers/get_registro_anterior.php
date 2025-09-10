<?php
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_GET['numero_operacion']) || !isset($_GET['producto'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit();
}

$numero_operacion = (int)$_GET['numero_operacion'];
$producto = (int)$_GET['producto'];

try {
    // Obtener el registro anterior al especificado
    $sql = "SELECT saldo_fisico, saldo_usd 
            FROM almacen_canal_tarjetas_estiba_usd 
            WHERE producto = ? AND numero_operacion < ? 
            ORDER BY numero_operacion DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $producto, $numero_operacion);
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
