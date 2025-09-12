<?php
require_once('config.php');
session_start();

if (isset($_GET['numero_operacion']) && isset($_GET['producto']) && isset($_GET['almacen_id'])) {
    $numero_operacion = (int)$_GET['numero_operacion'];
    $producto = (int)$_GET['producto'];
    $almacen_id = (int)$_GET['almacen_id'];

    $sql = "SELECT * FROM almacen_usd_tarjetas_estiba 
            WHERE numero_operacion = ? AND producto = ? AND almacen_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $numero_operacion, $producto, $almacen_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $registro = $result->fetch_assoc();
        echo json_encode(['success' => true, 'registro' => $registro]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Parámetros insuficientes']);
}

$conn->close();
