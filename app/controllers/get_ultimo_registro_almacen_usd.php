<?php
require_once('config.php');
header('Content-Type: application/json');

if (isset($_GET['producto'])) {
    $producto_id = (int)$_GET['producto'];

    $sql = "SELECT saldo_fisico, saldo_usd 
            FROM almacen_canal_tarjetas_estiba_usd 
            WHERE producto = ? 
            ORDER BY numero_operacion DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $producto_id);
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
        // Si no hay registros, obtener del inventario
        $sql_inv = "SELECT saldo_fisico, valor_usd as saldo_usd 
                   FROM almacen_canal_inventario_usd 
                   WHERE producto = ?";

        $stmt_inv = $conn->prepare($sql_inv);
        $stmt_inv->bind_param("i", $producto_id);
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
                'message' => 'No se encontraron registros para este producto'
            ]);
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Producto no especificado'
    ]);
}
