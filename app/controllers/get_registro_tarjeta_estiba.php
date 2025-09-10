<?php
require_once('config.php');
session_start();

if (isset($_GET['numero_operacion']) && isset($_GET['producto'])) {
    $numero_operacion = (int)$_GET['numero_operacion'];
    $producto = (int)$_GET['producto'];

    $sql = "SELECT * FROM almacen_canal_tarjetas_estiba_usd 
            WHERE numero_operacion = ? AND producto = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $numero_operacion, $producto);
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
