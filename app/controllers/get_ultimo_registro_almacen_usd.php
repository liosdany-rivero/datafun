<?php
require_once('config.php');
header('Content-Type: application/json');

if (isset($_GET['producto'])) {
    $producto_id = (int)$_GET['producto'];
    $almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : 0;

    // Primero intentar obtener de la tarjeta de estiba del almacén específico
    $sql = "SELECT saldo_fisico, saldo_usd 
            FROM almacen_usd_tarjetas_estiba 
            WHERE producto = ? AND almacen_id = ?
            ORDER BY numero_operacion DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $producto_id, $almacen_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'saldo_fisico' => $row['saldo_fisico'],
            'saldo_usd' => $row['saldo_usd']
        ]);
    } else {
        // Si no hay registros, obtener del inventario del almacén específico
        $sql_inv = "SELECT saldo_fisico, valor_usd as saldo_usd 
                   FROM almacen_usd_inventario 
                   WHERE producto = ? AND almacen_id = ?";

        $stmt_inv = $conn->prepare($sql_inv);
        $stmt_inv->bind_param("ii", $producto_id, $almacen_id);
        $stmt_inv->execute();
        $result_inv = $stmt_inv->get_result();

        if ($result_inv->num_rows > 0) {
            $row = $result_inv->fetch_assoc();
            echo json_encode([
                'success' => true,
                'saldo_fisico' => $row['saldo_fisico'],
                'saldo_usd' => $row['saldo_usd']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontraron registros para este producto en el almacén especificado'
            ]);
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Producto no especificado'
    ]);
}
