<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: almacen_usd_inventario.php
 * DESCRIPCIÓN: Gestión de tarjetas de estiba USD del sistema
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php');
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Obtener el ID del almacén desde la URL o sesión
$almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : 0;
if ($almacen_id > 0) {
    $_SESSION['almacen_actual'] = $almacen_id;
} elseif (isset($_SESSION['almacen_actual'])) {
    $almacen_id = (int)$_SESSION['almacen_actual'];
} else {
    // Redirigir al dashboard si no hay almacén seleccionado
    header("Location: almacen_usd_dashboard.php");
    exit();
}

// Validar que el usuario tenga permisos para este almacén
$stmt = $conn->prepare("SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $almacen_id);
$stmt->execute();
$result = $stmt->get_result();
$permiso = $result->fetch_assoc()['permiso'] ?? '';
$stmt->close();

if (!in_array($permiso, ['leer', 'escribir', 'tramitar'])) {
    $_SESSION['error_msg'] = "No tiene permisos para acceder a este almacén";
    header("Location: almacen_usd_dashboard.php");
    exit();
}

// Obtener nombre del almacén actual
$stmt = $conn->prepare("SELECT nombre FROM centros_costo WHERE codigo = ?");
$stmt->bind_param("i", $almacen_id);
$stmt->execute();
$result = $stmt->get_result();
$almacen_nombre = $result->fetch_assoc()['nombre'] ?? 'Almacén USD';
$stmt->close();

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener el producto seleccionado (si existe)
$producto_id = isset($_GET['producto']) ? (int)$_GET['producto'] : 0;

// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS

// 2.1 Eliminación de registro y actualización de saldos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_registro'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $numero_operacion = (int)$_POST['numero_operacion'];
    $producto = (int)$_POST['producto'];

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        // Obtener el registro a eliminar
        $sql_select = "SELECT * FROM almacen_usd_tarjetas_estiba 
                      WHERE numero_operacion = ? AND producto = ? AND almacen_id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("iii", $numero_operacion, $producto, $almacen_id);
        $stmt_select->execute();
        $registro_eliminar = $stmt_select->get_result()->fetch_assoc();
        $stmt_select->close();

        if (!$registro_eliminar) {
            throw new Exception("Registro no encontrado");
        }

        // Eliminar el registro
        $sql_delete = "DELETE FROM almacen_usd_tarjetas_estiba 
                      WHERE numero_operacion = ? AND producto = ? AND almacen_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("iii", $numero_operacion, $producto, $almacen_id);

        if (!$stmt_delete->execute()) {
            throw new Exception("Error al eliminar registro: " . $stmt_delete->error);
        }
        $stmt_delete->close();

        // Obtener todos los registros posteriores al número de operación eliminado
        $sql_posteriores = "SELECT numero_operacion, tipo_movimiento, cantidad_fisica, valor_usd 
                           FROM almacen_usd_tarjetas_estiba 
                           WHERE producto = ? AND almacen_id = ? AND numero_operacion > ?
                           ORDER BY numero_operacion ASC";
        $stmt_posteriores = $conn->prepare($sql_posteriores);
        $stmt_posteriores->bind_param("iii", $producto, $almacen_id, $numero_operacion);
        $stmt_posteriores->execute();
        $result_posteriores = $stmt_posteriores->get_result();
        $stmt_posteriores->close();

        // Obtener el saldo anterior al registro eliminado
        $sql_anterior = "SELECT saldo_fisico, saldo_usd 
                        FROM almacen_usd_tarjetas_estiba 
                        WHERE producto = ? AND almacen_id = ? AND numero_operacion < ?
                        ORDER BY numero_operacion DESC 
                        LIMIT 1";
        $stmt_anterior = $conn->prepare($sql_anterior);
        $stmt_anterior->bind_param("iii", $producto, $almacen_id, $numero_operacion);
        $stmt_anterior->execute();
        $result_anterior = $stmt_anterior->get_result();

        // Si hay registro anterior, usar sus saldos como base
        if ($result_anterior->num_rows > 0) {
            $saldo_base = $result_anterior->fetch_assoc();
            $saldo_fisico_actual = $saldo_base['saldo_fisico'];
            $saldo_usd_actual = $saldo_base['saldo_usd'];
        } else {
            // Si no hay registros anteriores, empezar desde cero
            $saldo_fisico_actual = 0;
            $saldo_usd_actual = 0;
        }
        $stmt_anterior->close();

        // Recalcular saldos para todos los registros posteriores
        while ($registro = $result_posteriores->fetch_assoc()) {
            if ($registro['tipo_movimiento'] === 'entrada') {
                $saldo_fisico_actual += $registro['cantidad_fisica'];
                $saldo_usd_actual += $registro['valor_usd'];
            } else {
                $saldo_fisico_actual -= $registro['cantidad_fisica'];
                $saldo_usd_actual -= $registro['valor_usd'];
            }

            // Aplicar redondeo para mantener precisión
            $saldo_fisico_actual = round($saldo_fisico_actual, 3);
            $saldo_usd_actual = round($saldo_usd_actual, 2);

            // Actualizar el registro con los nuevos saldos
            $sql_update = "UPDATE almacen_usd_tarjetas_estiba 
                      SET saldo_fisico = ?, saldo_usd = ? 
                      WHERE numero_operacion = ? AND producto = ? AND almacen_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param(
                "ddiii",
                $saldo_fisico_actual,
                $saldo_usd_actual,
                $registro['numero_operacion'],
                $producto,
                $almacen_id // MODIFICACIÓN: agregar parámetro
            );

            if (!$stmt_update->execute()) {
                throw new Exception("Error al actualizar saldos: " . $stmt_update->error);
            }
            $stmt_update->close();
        }

        // Actualizar el inventario con el último saldo
        // Obtener la fecha actual para la actualización del inventario
        $fecha_actual = date('Y-m-d');

        // Actualizar el inventario con el último saldo
        $sql_inv = "INSERT INTO almacen_usd_inventario 
           (producto, saldo_fisico, valor_usd, fecha_operacion, almacen_id) 
           VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE 
           saldo_fisico = VALUES(saldo_fisico), 
           valor_usd = VALUES(valor_usd), 
           fecha_operacion = VALUES(fecha_operacion)";
        $stmt_inv = $conn->prepare($sql_inv);
        $stmt_inv->bind_param(
            "iddsi",
            $producto,
            $saldo_fisico_actual,
            $saldo_usd_actual,
            $fecha_actual,  // ← Usar fecha actual
            $almacen_id
        );

        if (!$stmt_inv->execute()) {
            throw new Exception("Error al actualizar inventario: " . $stmt_inv->error);
        }
        $stmt_inv->close();

        $conn->commit();
        $_SESSION['success_msg'] = "✅ Registro eliminado correctamente. Saldos actualizados.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
    exit();
}

// 2.2 Edición de registro existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_registro'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos del formulario
    $numero_operacion = (int)$_POST['numero_operacion'];
    $producto = (int)$_POST['producto'];
    $fecha = trim($_POST['fecha']);
    $cantidad_fisica = (float)$_POST['cantidad_fisica'];
    $valor_usd = (float)$_POST['valor_usd'];
    $desde_para = (int)$_POST['desde_para'];
    $observaciones = trim($_POST['observaciones']);
    $tipo_movimiento = trim($_POST['tipo_movimiento']);

    // Validar campos obligatorios
    if (empty($fecha)) {
        $_SESSION['error_msg'] = "⚠️ La fecha es obligatoria";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    // Validar que existe tasa para la fecha seleccionada
    $sql_tasa = "SELECT tasa FROM tasas WHERE fecha = ?";
    $stmt_tasa = $conn->prepare($sql_tasa);
    $stmt_tasa->bind_param("s", $fecha);
    $stmt_tasa->execute();
    $result_tasa = $stmt_tasa->get_result();

    if ($result_tasa->num_rows === 0) {
        $_SESSION['error_msg'] = "⚠️ No existe una tasa definida para la fecha seleccionada";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }
    $stmt_tasa->close();

    if (empty($cantidad_fisica) || $cantidad_fisica <= 0) {
        $_SESSION['error_msg'] = "⚠️ La cantidad física debe ser mayor a 0";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    if (empty($valor_usd) || $valor_usd <= 0) {
        $_SESSION['error_msg'] = "⚠️ El valor USD debe ser mayor a 0";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    if (empty($desde_para)) {
        $_SESSION['error_msg'] = "⚠️ El centro de costo es obligatorio";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        // Obtener el registro original para comparar
        $sql_original = "SELECT cantidad_fisica, valor_usd, tipo_movimiento 
                    FROM almacen_usd_tarjetas_estiba 
                    WHERE numero_operacion = ? AND producto = ? AND almacen_id = ?";
        $stmt_original = $conn->prepare($sql_original);
        $stmt_original->bind_param("iii", $numero_operacion, $producto, $almacen_id);
        $stmt_original->execute();
        $registro_original = $stmt_original->get_result()->fetch_assoc();
        $stmt_original->close();

        if (!$registro_original) {
            throw new Exception("Registro no encontrado");
        }

        // Actualizar el registro
        $sql_update = "UPDATE almacen_usd_tarjetas_estiba 
                  SET fecha = ?, cantidad_fisica = ?, valor_usd = ?, 
                      desde_para = ?, observaciones = ?, tipo_movimiento = ?
                  WHERE numero_operacion = ? AND producto = ? AND almacen_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param(
            "sddissiii", // MODIFICACIÓN: cambiar a 9 parámetros
            $fecha,
            $cantidad_fisica,
            $valor_usd,
            $desde_para,
            $observaciones,
            $tipo_movimiento,
            $numero_operacion,
            $producto,
            $almacen_id // MODIFICACIÓN: agregar parámetro
        );

        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar registro: " . $stmt_update->error);
        }
        $stmt_update->close();

        // Recalcular todos los saldos desde este registro en adelante
        // Obtener el saldo anterior al registro editado
        $sql_anterior = "SELECT saldo_fisico, saldo_usd 
                    FROM almacen_usd_tarjetas_estiba 
                    WHERE producto = ? AND almacen_id = ? AND numero_operacion < ?
                    ORDER BY numero_operacion DESC 
                    LIMIT 1";
        $stmt_anterior = $conn->prepare($sql_anterior);
        $stmt_anterior->bind_param("iii", $producto, $almacen_id, $numero_operacion);
        $stmt_anterior->execute();
        $result_anterior = $stmt_anterior->get_result();

        // Si hay registro anterior, usar sus saldos como base
        if ($result_anterior->num_rows > 0) {
            $saldo_base = $result_anterior->fetch_assoc();
            $saldo_fisico_actual = $saldo_base['saldo_fisico'];
            $saldo_usd_actual = $saldo_base['saldo_usd'];
        } else {
            // Si no hay registros anteriores, empezar desde cero
            $saldo_fisico_actual = 0;
            $saldo_usd_actual = 0;
        }
        $stmt_anterior->close();

        // Obtener todos los registros desde el editado en adelante
        $sql_posteriores = "SELECT numero_operacion, tipo_movimiento, cantidad_fisica, valor_usd 
                       FROM almacen_usd_tarjetas_estiba 
                       WHERE producto = ? AND almacen_id = ? AND numero_operacion >= ?
                       ORDER BY numero_operacion ASC";
        $stmt_posteriores = $conn->prepare($sql_posteriores);
        $stmt_posteriores->bind_param("iii", $producto, $almacen_id, $numero_operacion);
        $stmt_posteriores->execute();
        $result_posteriores = $stmt_posteriores->get_result();
        $stmt_posteriores->close();

        // Recalcular saldos para todos los registros
        while ($registro = $result_posteriores->fetch_assoc()) {
            if ($registro['tipo_movimiento'] === 'entrada') {
                $saldo_fisico_actual += $registro['cantidad_fisica'];
                $saldo_usd_actual += $registro['valor_usd'];
            } else {
                $saldo_fisico_actual -= $registro['cantidad_fisica'];
                $saldo_usd_actual -= $registro['valor_usd'];
            }

            // Aplicar redondeo para mantener precisión
            $saldo_fisico_actual = round($saldo_fisico_actual, 3);
            $saldo_usd_actual = round($saldo_usd_actual, 2);

            // Actualizar el registro con los nuevos saldos
            $sql_update_saldo = "UPDATE almacen_usd_tarjetas_estiba 
                        SET saldo_fisico = ?, saldo_usd = ? 
                        WHERE numero_operacion = ? AND producto = ? AND almacen_id = ?";
            $stmt_update_saldo = $conn->prepare($sql_update_saldo);
            $stmt_update_saldo->bind_param(
                "ddiii", // MODIFICACIÓN: cambiar a 5 parámetros
                $saldo_fisico_actual,
                $saldo_usd_actual,
                $registro['numero_operacion'],
                $producto,
                $almacen_id // MODIFICACIÓN: agregar parámetro
            );

            if (!$stmt_update_saldo->execute()) {
                throw new Exception("Error al actualizar saldos: " . $stmt_update_saldo->error);
            }
            $stmt_update_saldo->close();
        }

        // Actualizar el inventario con el último saldo
        $sql_inv = "INSERT INTO almacen_usd_inventario 
           (producto, saldo_fisico, valor_usd, fecha_operacion, almacen_id) 
           VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE 
           saldo_fisico = VALUES(saldo_fisico), 
           valor_usd = VALUES(valor_usd), 
           fecha_operacion = VALUES(fecha_operacion)";
        $stmt_inv = $conn->prepare($sql_inv);
        $stmt_inv->bind_param(
            "iddsi",
            $producto,
            $saldo_fisico_actual,  // ← Usar la variable correcta
            $saldo_usd_actual,     // ← Usar la variable correcta
            $fecha,
            $almacen_id
        );

        if (!$stmt_inv->execute()) {
            throw new Exception("Error al actualizar inventario: " . $stmt_inv->error);
        }
        $stmt_inv->close();

        $conn->commit();
        $_SESSION['success_msg'] = "✅ Registro editado correctamente. Saldos actualizados.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
    exit();
}

// 2.3 Creación de entrada/salida en tarjeta de estiba
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['save_entrada']) || isset($_POST['save_salida']))) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Determinar tipo de movimiento
    $tipo_movimiento = isset($_POST['save_entrada']) ? 'entrada' : 'salida';

    // Recoger y sanitizar datos del formulario
    $producto = (int)$_POST['producto'];
    $fecha = trim($_POST['fecha']);
    $cantidad_fisica = (float)$_POST['cantidad_fisica'];
    $valor_usd = (float)$_POST['valor_usd'];
    $desde_para = (int)$_POST['desde_para'];
    $observaciones = trim($_POST['observaciones']);

    // Validar campos obligatorios
    if (empty($producto)) {
        $_SESSION['error_msg'] = "⚠️ El producto es obligatorio";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    if (empty($fecha)) {
        $_SESSION['error_msg'] = "⚠️ La fecha es obligatoria";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    // Validar que existe tasa para la fecha seleccionada
    $sql_tasa = "SELECT tasa FROM tasas WHERE fecha = ?";
    $stmt_tasa = $conn->prepare($sql_tasa);
    $stmt_tasa->bind_param("s", $fecha);
    $stmt_tasa->execute();
    $result_tasa = $stmt_tasa->get_result();

    if ($result_tasa->num_rows === 0) {
        $_SESSION['error_msg'] = "⚠️ No existe una tasa definida para la fecha seleccionada";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }
    $stmt_tasa->close();

    if (empty($cantidad_fisica) || $cantidad_fisica <= 0) {
        $_SESSION['error_msg'] = "⚠️ La cantidad física debe ser mayor a 0";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    if (empty($valor_usd) || $valor_usd <= 0) {
        $_SESSION['error_msg'] = "⚠️ El valor USD debe ser mayor a 0";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    if (empty($desde_para)) {
        $_SESSION['error_msg'] = "⚠️ El centro de costo es obligatorio";
        header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
        exit();
    }

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        // Obtener el saldo del inventario para este producto
        $sql_saldo = "SELECT saldo_fisico, valor_usd 
                     FROM almacen_usd_inventario 
                     WHERE producto = ? AND almacen_id = $almacen_id";
        $stmt_saldo = $conn->prepare($sql_saldo);
        $stmt_saldo->bind_param("i", $producto);
        $stmt_saldo->execute();
        $result_saldo = $stmt_saldo->get_result();
        $ultimo_saldo = $result_saldo->fetch_assoc();
        $stmt_saldo->close();

        // Calcular nuevos saldos con precisión decimal
        if ($tipo_movimiento === 'entrada') {
            $nuevo_saldo_fisico = round(($ultimo_saldo['saldo_fisico'] ?? 0) + $cantidad_fisica, 3);
            $nuevo_saldo_usd = round(($ultimo_saldo['valor_usd'] ?? 0) + $valor_usd, 2);
        } else {
            $nuevo_saldo_fisico = round(($ultimo_saldo['saldo_fisico'] ?? 0) - $cantidad_fisica, 3);
            $nuevo_saldo_usd = round(($ultimo_saldo['valor_usd'] ?? 0) - $valor_usd, 2);

            // Validar que no haya saldos negativos
            if ($nuevo_saldo_fisico < 0) {
                throw new Exception("No hay suficiente stock físico disponible");
            }

            if ($nuevo_saldo_usd < 0) {
                throw new Exception("No hay suficiente valor USD disponible");
            }
        }

        // Insertar en tarjeta de estiba (sin los campos CUP que no existen en la tabla)
        $sql = "INSERT INTO almacen_usd_tarjetas_estiba 
       (producto, fecha, tipo_movimiento, cantidad_fisica, valor_usd, saldo_fisico, saldo_usd, desde_para, observaciones, almacen_id) 
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issdddddsi",
            $producto,
            $fecha,
            $tipo_movimiento,
            $cantidad_fisica,
            $valor_usd,
            $nuevo_saldo_fisico,
            $nuevo_saldo_usd,
            $desde_para,
            $observaciones,
            $almacen_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al registrar movimiento: " . $stmt->error);
        }

        // Actualizar inventario
        $sql_inv = "INSERT INTO almacen_usd_inventario 
           (producto, saldo_fisico, valor_usd, fecha_operacion, almacen_id) 
           VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE 
           saldo_fisico = VALUES(saldo_fisico), 
           valor_usd = VALUES(valor_usd), 
           fecha_operacion = VALUES(fecha_operacion)";
        $stmt_inv = $conn->prepare($sql_inv);
        $stmt_inv->bind_param(
            "iddsi",
            $producto,
            $nuevo_saldo_fisico,
            $nuevo_saldo_usd,
            $fecha,
            $almacen_id
        );

        if (!$stmt_inv->execute()) {
            throw new Exception("Error al actualizar inventario: " . $stmt_inv->error);
        }

        $stmt->close();
        $stmt_inv->close();
        $conn->commit();

        $_SESSION['success_msg'] = "✅ Movimiento de $tipo_movimiento registrado correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_usd_tarjetas_estiba.php?producto=$producto&almacen_id=$almacen_id");
    exit();
}

// 2.4 Cerrar tarjeta de estiba automáticamente al volver a inventarios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['volver_inventarios'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $producto = (int)$_POST['producto'];

    try {
        // Obtener el último movimiento de la tarjeta de estiba
        $sql_ultimo = "SELECT saldo_fisico, saldo_usd, fecha 
              FROM almacen_usd_tarjetas_estiba 
              WHERE producto = ? AND almacen_id = ?
              ORDER BY numero_operacion DESC 
              LIMIT 1";
        $stmt_ultimo = $conn->prepare($sql_ultimo);
        $stmt_ultimo->bind_param("ii", $producto, $almacen_id);
        $stmt_ultimo->execute();
        $result_ultimo = $stmt_ultimo->get_result();
        $ultimo_movimiento = $result_ultimo->fetch_assoc();
        $stmt_ultimo->close();

        if ($ultimo_movimiento) {
            // Actualizar inventario con los últimos saldos
            $sql_inv = "INSERT INTO almacen_usd_inventario 
                       (producto, saldo_fisico, valor_usd, fecha_operacion) 
                       VALUES (?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE 
                       saldo_fisico = VALUES(saldo_fisico), 
                       valor_usd = VALUES(valor_usd), 
                       fecha_operacion = VALUES(fecha_operacion)";

            $stmt_inv = $conn->prepare($sql_inv);
            $stmt_inv->bind_param(
                "idds",
                $producto,
                $ultimo_movimiento['saldo_fisico'],
                $ultimo_movimiento['saldo_usd'],
                $ultimo_movimiento['fecha']
            );

            if ($stmt_inv->execute()) {
                $_SESSION['success_msg'] = "✅ Tarjeta de estiba cerrada correctamente. Inventario actualizado.";
            } else {
                throw new Exception("Error al actualizar inventario: " . $stmt_inv->error);
            }
            $stmt_inv->close();
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir a inventarios
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_usd_inventario.php");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Obtener información del producto seleccionado
$producto_nombre = "";
$producto_um = "";
if ($producto_id > 0) {
    $sql_producto = "SELECT p.codigo, p.nombre, p.um 
                    FROM productos p 
                    WHERE p.codigo = $producto_id";
    $result_producto = mysqli_query($conn, $sql_producto);
    if ($result_producto && $row = mysqli_fetch_assoc($result_producto)) {
        $producto_nombre = $row['nombre'];
        $producto_um = $row['um'];
    }
}

// Obtener lista de centros de costo para entrada (E_Almacen_USD = 1) y salida (S_Almacen_USD = 1)
// Excluyendo el almacén actual para evitar movimientos dentro del mismo establecimiento
$centros_costo_entrada = [];
$centros_costo_salida = [];

$sql_centros_entrada = "SELECT codigo, nombre FROM centros_costo WHERE E_Almacen_USD = 1 AND codigo != ? ORDER BY nombre";
$stmt_centros_entrada = $conn->prepare($sql_centros_entrada);
$stmt_centros_entrada->bind_param("i", $almacen_id);
$stmt_centros_entrada->execute();
$result_centros_entrada = $stmt_centros_entrada->get_result();
while ($row = $result_centros_entrada->fetch_assoc()) {
    $centros_costo_entrada[$row['codigo']] = $row['nombre'];
}
$stmt_centros_entrada->close();

$sql_centros_salida = "SELECT codigo, nombre FROM centros_costo WHERE S_Almacen_USD = 1 AND codigo != ? ORDER BY nombre";
$stmt_centros_salida = $conn->prepare($sql_centros_salida);
$stmt_centros_salida->bind_param("i", $almacen_id);
$stmt_centros_salida->execute();
$result_centros_salida = $stmt_centros_salida->get_result();
while ($row = $result_centros_salida->fetch_assoc()) {
    $centros_costo_salida[$row['codigo']] = $row['nombre'];
}
$stmt_centros_salida->close();

// Obtener saldo actual del inventario
$saldo_actual = ['saldo_fisico' => 0, 'valor_usd' => 0];
if ($producto_id > 0) {
    $sql_saldo = "SELECT saldo_fisico, valor_usd 
                 FROM almacen_usd_inventario 
                 WHERE producto = $producto_id";
    $result_saldo = mysqli_query($conn, $sql_saldo);
    if ($result_saldo && $row = mysqli_fetch_assoc($result_saldo)) {
        $saldo_actual = $row;
    }
}

// Configuración de paginación para movimientos
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = 0;
if ($producto_id > 0) {
    $total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM almacen_usd_inventario WHERE producto = $producto_id"))['total'] ?? 0;
}
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada de movimientos
$movimientos = [];
if ($producto_id > 0) {
    // REEMPLAZA la consulta anterior con esta:
    $sql = "SELECT t.*, p.nombre as producto_nombre, c.nombre as centro_nombre 
        FROM almacen_usd_tarjetas_estiba t 
        LEFT JOIN productos p ON t.producto = p.codigo 
        LEFT JOIN centros_costo c ON t.desde_para = c.codigo 
        WHERE t.producto = $producto_id AND t.almacen_id = $almacen_id
        ORDER BY t.numero_operacion DESC 
        LIMIT $inicio, $por_pagina";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $movimientos[] = $row;
    }
}

// Obtener fechas disponibles con tasas para el input date
$fechas_con_tasa = [];
$sql_fechas = "SELECT fecha FROM tasas ORDER BY fecha DESC";
$result_fechas = mysqli_query($conn, $sql_fechas);
while ($row = mysqli_fetch_assoc($result_fechas)) {
    $fechas_con_tasa[] = $row['fecha'];
}

// Obtener datos para edición si se ha solicitado
$registro_editar = null;
if (isset($_GET['editar']) && $producto_id > 0) {
    $numero_operacion_editar = (int)$_GET['editar'];
    $sql_editar = "SELECT * FROM almacen_usd_inventario 
                  WHERE numero_operacion = ? AND producto = ?";
    $stmt_editar = $conn->prepare($sql_editar);
    $stmt_editar->bind_param("ii", $numero_operacion_editar, $producto_id);
    $stmt_editar->execute();
    $result_editar = $stmt_editar->get_result();
    $registro_editar = $result_editar->fetch_assoc();
    $stmt_editar->close();
}

ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Tarjetas de Estiba USD - <?= htmlspecialchars($almacen_nombre) ?></h2>
    <h3><?= $producto_id > 0 ? htmlspecialchars($producto_nombre) . " (" . htmlspecialchars($producto_um) . ")" : "Seleccione un producto" ?></h3>
    <?php if ($producto_id > 0): ?>
    <?php else: ?>
        <!-- Si no hay producto seleccionado, mostrar mensaje y botón para volver -->
        <div class="alert alert-info">
            <p>Por favor, seleccione un producto desde la página de inventarios para gestionar sus tarjetas de estiba.</p>
            <button onclick="location.href='almacen_usd_inventario.php'" class="btn-primary">Volver a Inventarios</button>
        </div>
    <?php endif; ?>

    <?php if ($producto_id > 0): ?>
        <!-- Tabla de movimientos -->
        <table class="table">
            <thead>
                <tr>
                    <th>N° Operación</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Cantidad Física</th>
                    <th>Valor USD</th>
                    <th>Saldo Físico</th>
                    <th>Saldo USD</th>
                    <th>Centro Costo</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $row): ?>
                    <tr>
                        <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?></td>
                        <td data-label="Fecha"><?= htmlspecialchars($row['fecha']) ?></td>
                        <td data-label="Tipo"><?= htmlspecialchars($row['tipo_movimiento']) ?></td>
                        <td data-label="Cantidad Física"><?= number_format($row['cantidad_fisica'], 3) ?></td>
                        <td data-label="Valor USD">$<?= number_format($row['valor_usd'], 2) ?></td>
                        <td data-label="Saldo Físico"><?= number_format($row['saldo_fisico'], 3) ?></td>
                        <td data-label="Saldo USD">$<?= number_format($row['saldo_usd'], 2) ?></td>
                        <td data-label="Centro Costo"><?= htmlspecialchars($row['centro_nombre']) ?></td>
                        <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones']) ?></td>
                        <td data-label="Acciones">
                            <div style="display: flex; gap: 5px;">
                                <button onclick="editarRegistro(<?= $row['numero_operacion'] ?>)" class="btn-warning btn-small">Editar</button>
                                <form method="POST" action="almacen_usd_tarjetas_estiba.php?producto=<?= $producto_id ?>&almacen_id=<?= $almacen_id ?>"
                                    class="delete-form" onsubmit="return confirmarEliminacion(this)">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="numero_operacion" value="<?= $row['numero_operacion'] ?>">
                                    <input type="hidden" name="producto" value="<?= $producto_id ?>">
                                    <button type="submit" name="eliminar_registro" class="btn-danger btn-small">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <div class="pagination">
            <?php if ($pagina_actual > 1): ?>
                <a href="?producto=<?= $producto_id ?>&pagina=1">&laquo; Primera</a>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $pagina_actual - 1 ?>">&lsaquo; Anterior</a>
            <?php endif; ?>

            <?php
            $inicio_paginas = max(1, $pagina_actual - 2);
            $fin_paginas = min($total_paginas, $pagina_actual + 2);

            if ($inicio_paginas > 1) echo '<span>...</span>';

            for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $i ?>" class="<?= $i === $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor;

            if ($fin_paginas < $total_paginas) echo '<span>...</span>';
            ?>

            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $pagina_actual + 1 ?>">Siguiente &rsaquo;</a>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $total_paginas ?>">Última &raquo;</a>
            <?php endif; ?>
        </div>

        <br>

        <!-- Notificaciones flotantes -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div id="floatingNotification" class="floating-notification success">
                <?= $_SESSION['success_msg'] ?>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div id="floatingNotification" class="floating-notification error">
                <?= $_SESSION['error_msg'] ?>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- Formulario Entrada -->
        <div id="entradaFormContainer" class="sub-form" style="display: none;">
            <h3>Registrar Entrada</h3>
            <form method="POST" action="almacen_usd_tarjetas_estiba.php?producto=<?= $producto_id ?>&almacen_id=<?= $almacen_id ?>" id="entradaForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="producto" value="<?= $producto_id ?>">

                <label for="fecha_entrada">Fecha:</label>
                <input type="date" id="fecha_entrada" name="fecha" required
                    onchange="validarFechaConTasa(this.value)" />
                <datalist id="fechas_con_tasa">
                    <?php foreach ($fechas_con_tasa as $fecha): ?>
                        <option value="<?= $fecha ?>">
                        <?php endforeach; ?>
                </datalist>
                <div id="fecha_error" style="color: red; display: none;">No existe tasa para esta fecha</div>

                <label for="cantidad_fisica_entrada">Cantidad Física (<?= htmlspecialchars($producto_um) ?>):</label>
                <input type="number" id="cantidad_fisica_entrada" name="cantidad_fisica" step="0.001" min="0.001" required />

                <div style="margin: 15px 0; display: flex; align-items: center;">
                    <input type="checkbox" id="entrada_cup" name="entrada_cup" value="1" onchange="toggleCamposCUP()" />
                    <label for="entrada_cup" style="margin-left: 5px;">Entrada en CUP</label>
                </div>

                <div id="campos_cup" style="display: none;">
                    <label for="valor_cup">Valor en CUP:</label>
                    <input type="number" id="valor_cup" name="valor_cup" step="0.01" min="0.01" oninput="calcularValorUSD()" />

                    <label for="tasa">Tasa:</label>
                    <input type="number" id="tasa" name="tasa" step="0.01" min="0.01" readonly />
                </div>

                <label for="valor_usd_entrada">Valor USD:</label>
                <input type="number" id="valor_usd_entrada" name="valor_usd" step="0.01" min="0.01" required />

                <label for="desde_para_entrada">Desde/Centro Costo:</label>
                <select id="desde_para_entrada" name="desde_para" required>
                    <option value="">Seleccione centro de costo</option>
                    <?php foreach ($centros_costo_entrada  as $codigo => $nombre): ?>
                        <option value="<?= $codigo ?>"><?= htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="observaciones_entrada">Observaciones:</label>
                <textarea id="observaciones_entrada" name="observaciones" rows="3"></textarea>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save_entrada" class="btn-primary">Registrar Entrada</button>
                    <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Formulario Salida -->
        <div id="salidaFormContainer" class="sub-form" style="display: none;">
            <h3>Registrar Salida</h3>
            <form method="POST" action="almacen_usd_tarjetas_estiba.php?producto=<?= $producto_id ?>&almacen_id=<?= $almacen_id ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="producto" value="<?= $producto_id ?>">

                <label for="fecha_salida">Fecha:</label>
                <input type="date" id="fecha_salida" name="fecha" required
                    onchange="validarFechaConTasaSalida(this.value)" />
                <datalist id="fechas_con_tasa_salida">
                    <?php foreach ($fechas_con_tasa as $fecha): ?>
                        <option value="<?= $fecha ?>">
                        <?php endforeach; ?>
                </datalist>
                <div id="fecha_error_salida" style="color: red; display: none;">No existe tasa para esta fecha</div>

                <label for="cantidad_fisica_salida">Cantidad Física (<?= htmlspecialchars($producto_um) ?>):</label>
                <input type="number" id="cantidad_fisica_salida" name="cantidad_fisica" step="0.001" min="0.001" max="<?= $saldo_actual['saldo_fisico'] ?>" required />

                <div style="margin: 15px 0; display: flex; align-items: center;">
                    <input type="checkbox" id="establecer_importe_usd" name="establecer_importe_usd" value="1" onchange="toggleModoUSD()" />
                    <label for="establecer_importe_usd" style="margin-left: 5px;">Establecer importe USD</label>
                </div>

                <div style="margin: 15px 0; display: flex; align-items: center;">
                    <input type="checkbox" id="establecer_importe_cup" name="establecer_importe_cup" value="1" onchange="toggleModoCUP()" />
                    <label for="establecer_importe_cup" style="margin-left: 5px;">Establecer importe CUP</label>
                </div>

                <label for="valor_cup_salida">Valor CUP:</label>
                <input type="number" id="valor_cup_salida" name="valor_cup_salida" step="0.01" min="0.01" oninput="calcularValorUSDDesdeCUP()" readonly />

                <label for="tasa_salida">Tasa:</label>
                <input type="number" id="tasa_salida" name="tasa_salida" step="0.01" min="0.01" readonly />

                <label for="valor_usd_salida">Valor USD:</label>

                <input type="number" id="valor_usd_salida" name="valor_usd" step="0.01" min="0.01" max="<?= $saldo_actual['valor_usd'] ?>" required readonly />

                <label for="desde_para_salida">Para/Centro Costo:</label>
                <select id="desde_para_salida" name="desde_para" required>
                    <option value="">Seleccione centro de costo</option>
                    <?php foreach ($centros_costo_salida  as $codigo => $nombre): ?>
                        <option value="<?= $codigo ?>"><?= htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="observaciones_salida">Observaciones:</label>
                <textarea id="observaciones_salida" name="observaciones" rows="3"></textarea>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save_salida" class="btn-primary">Registrar Salida</button>
                    <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Formulario Edición -->
        <div id="editarFormContainer" class="sub-form" style="display: none;">
            <h3>Editar Registro <span id="editar_info_operacion" style="font-size: 0.8em; color: #666;"></span></h3>
            <form method="POST" action="almacen_usd_tarjetas_estiba.php?producto=<?= $producto_id ?>&almacen_id=<?= $almacen_id ?>" id="editarForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="producto" value="<?= $producto_id ?>">
                <input type="hidden" name="numero_operacion" id="editar_numero_operacion">
                <input type="hidden" name="tipo_movimiento" id="editar_tipo_movimiento">
                <input type="hidden" name="editar_registro" value="1">
                <input type="hidden" id="editar_tasa" name="tasa">

                <label for="editar_fecha">Fecha:</label>
                <input type="date" id="editar_fecha" name="fecha" required
                    onchange="validarFechaConTasaEditar(this.value)" />
                <datalist id="fechas_con_tasa_editar">
                    <?php foreach ($fechas_con_tasa as $fecha): ?>
                        <option value="<?= $fecha ?>">
                        <?php endforeach; ?>
                </datalist>
                <div id="fecha_error_editar" style="color: red; display: none;">No existe tasa para esta fecha</div>

                <label for="editar_cantidad_fisica">Cantidad Física (<?= htmlspecialchars($producto_um) ?>):</label>
                <input type="number" id="editar_cantidad_fisica" name="cantidad_fisica" step="0.001" min="0.001" required />

                <!-- Checkbox para Establecer importe CUP (solo para entradas) -->

                <div id="editar_establecer_cup_container" style="margin: 15px 0; display: none;">
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <input type="checkbox" id="editar_establecer_cup" name="editar_establecer_cup" value="1" onchange="toggleModoCUPEditar()" />
                        <label for="editar_establecer_cup" style="margin-left: 5px;">Establecer importe CUP</label>
                    </div>
                </div>



                <!-- Checkboxes para Establecer importe USD/CUP (solo para salidas) -->
                <div id="editar_establecer_usd_cup_container" style="margin: 15px 0; display: none;">
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <input type="checkbox" id="editar_establecer_usd" name="editar_establecer_usd" value="1"
                            onchange="toggleModoUSDEditar()" />
                        <label for="editar_establecer_usd" style="margin-left: 5px;">Establecer importe USD</label>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <input type="checkbox" id="editar_establecer_cup_salida" name="editar_establecer_cup_salida" value="1"
                            onchange="toggleModoCUPSalidaEditar()" />
                        <label for="editar_establecer_cup_salida" style="margin-left: 5px;">Establecer importe CUP</label>
                    </div>
                </div>

                <!-- Controles para Valor CUP y Tasa (solo frontend) -->
                <label for="editar_valor_cup">Valor CUP:</label>
                <input type="number" id="editar_valor_cup" step="0.01" min="0.01" oninput="calcularValorUSDDesdeCUPEditar()" />

                <label for="editar_tasa_visual">Tasa:</label>
                <input type="number" id="editar_tasa_visual" step="0.01" min="0.01" readonly />

                <label for="editar_valor_usd">Valor USD:</label>
                <input type="number" id="editar_valor_usd" name="valor_usd" step="0.01" min="0.01" required
                    oninput="calcularValorCUPDesdeUSDEditar()" />
                <label for="editar_desde_para">Centro Costo:</label>
                <select id="editar_desde_para" name="desde_para" required>
                    <option value="">Seleccione centro de costo</option>
                    <!-- Las opciones se llenarán dinámicamente con JavaScript -->
                </select>

                <label for="editar_observaciones">Observaciones:</label>
                <textarea id="editar_observaciones" name="observaciones" rows="3"></textarea>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>



        <!-- Formulario Volver a Inventarios (oculto) -->
        <form id="volverForm" method="POST" action="almacen_usd_inventario.php?producto=<?= $producto_id ?>" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="producto" value="<?= $producto_id ?>">
            <input type="hidden" name="volver_inventarios" value="1">
        </form>
    <?php endif; ?>
</div>

<BR>
<BR>
<BR>

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <?php if ($producto_id > 0): ?>
            <li><button onclick="document.getElementById('volverForm').submit();" class="nav-button">← Inventarios</button></li>
            <li><button onclick="showEntradaForm()" class="nav-button">+ Entrada</button></li>
            <li><button onclick="showSalidaForm()" class="nav-button">- Salida</button></li>
        <?php else: ?>
            <li><button onclick="location.href='almacen_usd_inventario.php?almacen_id=<?= $almacen_id ?>'" class="nav-button">← Inventarios</button></li>
        <?php endif; ?>
    </ul>
</div>

<!-- JavaScript para funcionalidades adicionales -->
<script>
    // Función para confirmar eliminación
    function confirmarEliminacion(form) {
        return confirm("¿Está seguro que desea eliminar este registro? Esta acción no se puede deshacer.");
    }

    // Funciones para mostrar/ocultar formularios
    function showEntradaForm() {
        document.getElementById('entradaFormContainer').style.display = 'block';
        document.getElementById('salidaFormContainer').style.display = 'none';

        // Hacer scroll suave hasta el formulario de entrada
        document.getElementById('entradaFormContainer').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // Enfocar el primer campo del formulario
        setTimeout(function() {
            document.getElementById('fecha_entrada').focus();
        }, 500);
    }

    function showSalidaForm() {
        document.getElementById('entradaFormContainer').style.display = 'none';
        document.getElementById('salidaFormContainer').style.display = 'block';

        // Hacer scroll suave hasta el formulario de salida
        document.getElementById('salidaFormContainer').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // Enfocar el primer campo del formulario
        setTimeout(function() {
            document.getElementById('fecha_salida').focus();
        }, 500);
    }

    function hideForms() {
        document.getElementById('entradaFormContainer').style.display = 'none';
        document.getElementById('salidaFormContainer').style.display = 'none';
    }

    // Validar fecha con tasa disponible
    function validarFechaConTasa(fecha) {
        const fechasConTasa = <?= json_encode($fechas_con_tasa) ?>;
        const fechaError = document.getElementById('fecha_error');
        const tasaInput = document.getElementById('tasa');
        const valorCUPInput = document.getElementById('valor_cup');
        const valorUSDInput = document.getElementById('valor_usd_entrada');

        if (!fechasConTasa.includes(fecha)) {
            fechaError.style.display = 'block';
            tasaInput.value = '';
            return;
        }

        fechaError.style.display = 'none';

        // Obtener tasa desde el servidor
        fetch('../../controllers/get_tasa.php?fecha=' + fecha)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tasaInput.value = data.tasa;
                    // Si hay valor en CUP, recalcular USD
                    if (valorCUPInput.value) {
                        valorUSDInput.value = (parseFloat(valorCUPInput.value) / parseFloat(data.tasa)).toFixed(2);
                    }
                } else {
                    alert('Error al obtener la tasa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al obtener la tasa');
            });
    }

    function validarFechaConTasaSalida(fecha) {
        const fechasConTasa = <?= json_encode($fechas_con_tasa) ?>;
        const fechaError = document.getElementById('fecha_error_salida');
        const tasaInput = document.getElementById('tasa_salida');

        if (!fechasConTasa.includes(fecha)) {
            fechaError.style.display = 'block';
            tasaInput.value = '';
            return;
        }

        fechaError.style.display = 'none';

        // Obtener tasa desde el servidor
        fetch('../../controllers/get_tasa.php?fecha=' + fecha)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tasaInput.value = data.tasa;
                    // Recalcular valores basados en la nueva tasa
                    if (document.getElementById('establecer_importe_cup').checked) {
                        calcularValorUSDDesdeCUP();
                    } else if (document.getElementById('establecer_importe_usd').checked) {
                        calcularValorCUPDesdeUSD();
                    } else {
                        // Si ninguno está marcado, calcular ambos
                        calcularValorUSDDesdeCantidad();
                    }
                } else {
                    alert('Error al obtener la tasa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al obtener la tasa');
            });
    }

    // Calcular valor USD basado en CUP y tasa
    function calcularValorUSD() {
        const valorCUP = parseFloat(document.getElementById('valor_cup').value) || 0;
        const tasa = parseFloat(document.getElementById('tasa').value) || 1;
        document.getElementById('valor_usd_entrada').value = (valorCUP / tasa).toFixed(2);
    }

    // Mostrar/ocultar campos CUP
    function toggleCamposCUP() {
        const checkbox = document.getElementById('entrada_cup');
        const camposCUP = document.getElementById('campos_cup');
        const valorUSDInput = document.getElementById('valor_usd_entrada');

        if (checkbox.checked) {
            camposCUP.style.display = 'block';
            valorUSDInput.readOnly = true;
            // Obtener tasa para la fecha seleccionada si ya hay una fecha
            const fechaInput = document.getElementById('fecha_entrada');
            if (fechaInput.value) {
                validarFechaConTasa(fechaInput.value);
            }
        } else {
            camposCUP.style.display = 'none';
            valorUSDInput.readOnly = false;
            document.getElementById('valor_cup').value = '';
            document.getElementById('tasa').value = '';
        }
    }

    // Ocultar notificación flotante después de 5 segundos
    setTimeout(() => {
        const notification = document.getElementById('floatingNotification');
        if (notification) {
            notification.style.display = 'none';
        }
    }, 5000);

    // Funciones para el formulario de salida
    function toggleModoUSD() {
        const establecerUSD = document.getElementById('establecer_importe_usd').checked;
        const valorUSDInput = document.getElementById('valor_usd_salida');
        const valorCUPInput = document.getElementById('valor_cup_salida');

        if (establecerUSD) {
            valorUSDInput.readOnly = false;
            valorCUPInput.readOnly = true; // Mantener CUP como solo lectura
            // Si está marcado, desactivar el modo CUP
            document.getElementById('establecer_importe_cup').checked = false;
            // Calcular CUP basado en USD cuando se activa el modo USD
            calcularValorCUPDesdeUSD();
        } else {
            valorUSDInput.readOnly = true;
            // Si tampoco está en modo CUP, calcular automáticamente
            if (!document.getElementById('establecer_importe_cup').checked) {
                calcularValorUSDDesdeCantidad();
            }
        }
    }

    function toggleModoCUP() {
        const establecerCUP = document.getElementById('establecer_importe_cup').checked;
        const valorCUPInput = document.getElementById('valor_cup_salida');
        const valorUSDInput = document.getElementById('valor_usd_salida');
        const establecerUSD = document.getElementById('establecer_importe_usd');

        if (establecerCUP) {
            valorCUPInput.readOnly = false;
            valorUSDInput.readOnly = true; // Mantener USD como solo lectura
            // Si está marcado, desactivar el modo USD
            establecerUSD.checked = false;
            // Calcular USD basado en CUP cuando se activa el modo CUP
            calcularValorUSDDesdeCUP();
        } else {
            valorCUPInput.readOnly = true;
            // Si tampoco está en modo USD, calcular automáticamente
            if (!document.getElementById('establecer_importe_usd').checked) {
                calcularValorUSDDesdeCantidad();
            }
        }
    }

    function calcularValorUSDDesdeCantidad() {
        const cantidad = parseFloat(document.getElementById('cantidad_fisica_salida').value) || 0;
        const almacenId = <?= $almacen_id ?>; // Obtener el ID del almacén actual

        // Obtener el último registro para este producto EN ESTE ALMACÉN
        fetch('../../controllers/get_ultimo_registro_almacen_usd.php?producto=<?= $producto_id ?>&almacen_id=' + almacenId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const precioUnitario = data.saldo_usd / data.saldo_fisico;
                    const valorUSD = cantidad * precioUnitario;
                    document.getElementById('valor_usd_salida').value = valorUSD.toFixed(2);
                    calcularValorCUPDesdeUSD();
                } else {
                    console.error('Error al obtener último registro:', data.message);
                    // Fallback: usar valor por defecto o mostrar error
                    document.getElementById('valor_usd_salida').value = '0.00';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('valor_usd_salida').value = '0.00';
            });
    }

    function calcularValorCUPDesdeUSD() {
        const valorUSD = parseFloat(document.getElementById('valor_usd_salida').value) || 0;
        const tasa = parseFloat(document.getElementById('tasa_salida').value) || 1;
        document.getElementById('valor_cup_salida').value = (valorUSD * tasa).toFixed(2);
    }

    function calcularValorUSDDesdeCUP() {
        const valorCUP = parseFloat(document.getElementById('valor_cup_salida').value) || 0;
        const tasa = parseFloat(document.getElementById('tasa_salida').value) || 1;
        document.getElementById('valor_usd_salida').value = (valorCUP / tasa).toFixed(2);
    }

    // Actualizar la función validarFechaConTasaSalida para obtener la tasa
    function validarFechaConTasaSalida(fecha) {
        const fechasConTasa = <?= json_encode($fechas_con_tasa) ?>;
        const fechaError = document.getElementById('fecha_error_salida');
        const tasaInput = document.getElementById('tasa_salida');

        if (!fechasConTasa.includes(fecha)) {
            fechaError.style.display = 'block';
            tasaInput.value = '';
            return;
        }

        fechaError.style.display = 'none';

        // Obtener tasa desde el servidor
        fetch('../../controllers/get_tasa.php?fecha=' + fecha)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tasaInput.value = data.tasa;
                    // Recalcular valores basados en la nueva tasa
                    if (document.getElementById('establecer_importe_cup').checked) {
                        calcularValorUSDDesdeCUP();
                    } else if (document.getElementById('establecer_importe_usd').checked) {
                        calcularValorCUPDesdeUSD();
                    } else {
                        // Si ninguno está marcado, calcular ambos
                        calcularValorUSDDesdeCantidad();
                    }
                } else {
                    alert('Error al obtener la tasa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al obtener la tasa');
            });
    }

    // Event listener para el campo cantidad_fisica_salida
    document.getElementById('cantidad_fisica_salida').addEventListener('input', function() {
        // Solo calcular automáticamente si ninguno de los checkboxes está marcado
        if (!document.getElementById('establecer_importe_usd').checked &&
            !document.getElementById('establecer_importe_cup').checked) {
            calcularValorUSDDesdeCantidad();
        }
    });



    // Inicializar el estado de los campos al cargar la página
    function inicializarCamposSalida() {
        // Establecer ambos campos como solo lectura inicialmente
        document.getElementById('valor_usd_salida').readOnly = true;
        document.getElementById('valor_cup_salida').readOnly = true;

        // Asegurarse de que los checkboxes estén desmarcados
        document.getElementById('establecer_importe_usd').checked = false;
        document.getElementById('establecer_importe_cup').checked = false;

        // Calcular valores iniciales
        calcularValorUSDDesdeCantidad();
    }

    // Llamar a la función de inicialización cuando se muestre el formulario de salida
    function showSalidaForm() {
        document.getElementById('entradaFormContainer').style.display = 'none';
        document.getElementById('salidaFormContainer').style.display = 'block';

        // Hacer scroll suave hasta el formulario de salida
        document.getElementById('salidaFormContainer').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // Inicializar campos de salida
        inicializarCamposSalida();

        // Enfocar el primer campo del formulario
        setTimeout(function() {
            document.getElementById('fecha_salida').focus();
        }, 500);
    }

    // Calcular valor CUP basado en USD y tasa
    function calcularValorCUPDesdeUSD() {
        const valorUSD = parseFloat(document.getElementById('valor_usd_salida').value) || 0;
        const tasa = parseFloat(document.getElementById('tasa_salida').value) || 1;
        document.getElementById('valor_cup_salida').value = (valorUSD * tasa).toFixed(2);
    }

    // Event listener para el campo valor_usd_salida cuando está en modo editable
    document.getElementById('valor_usd_salida').addEventListener('input', function() {
        // Si está en modo USD editable, calcular CUP automáticamente
        if (document.getElementById('establecer_importe_usd').checked) {
            calcularValorCUPDesdeUSD();
        }
    });



    function validarFechaConTasaEditar(fecha) {
        const fechasConTasa = <?= json_encode($fechas_con_tasa) ?>;
        const fechaError = document.getElementById('fecha_error_editar');
        const tasaInputVisual = document.getElementById('editar_tasa_visual');
        const tasaInputHidden = document.getElementById('editar_tasa');

        if (!fechasConTasa.includes(fecha)) {
            fechaError.style.display = 'block';
            tasaInputVisual.value = '';
            tasaInputHidden.value = '';
            return;
        }

        fechaError.style.display = 'none';

        // Obtener tasa desde el servidor
        fetch('../../controllers/get_tasa.php?fecha=' + fecha)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tasaInputVisual.value = data.tasa;
                    tasaInputHidden.value = data.tasa;

                    // Recalcular CUP basado en USD actual
                    calcularValorCUPDesdeUSDEditar();

                    // Si es una salida y estamos en modo automático, recalcular
                    const tipoMovimiento = document.getElementById('editar_tipo_movimiento').value;
                    const establecerUSD = document.getElementById('editar_establecer_usd')?.checked || false;
                    const establecerCUP = document.getElementById('editar_establecer_cup_salida')?.checked || false;

                    if (tipoMovimiento === 'salida' && !establecerUSD && !establecerCUP) {
                        calcularValorUSDDesdeCantidadEditar();
                    }
                } else {
                    alert('Error al obtener la tasa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al obtener la tasa');
            });
    }

    // Modificar la función hideForms para ocultar también el formulario de edición
    function hideForms() {
        document.getElementById('entradaFormContainer').style.display = 'none';
        document.getElementById('salidaFormContainer').style.display = 'none';
        document.getElementById('editarFormContainer').style.display = 'none';
    }

    // Funciones específicas para el formulario de edición
    function calcularValorCUPDesdeUSDEditar() {
        const valorUSD = parseFloat(document.getElementById('editar_valor_usd').value) || 0;
        const tasa = parseFloat(document.getElementById('editar_tasa_visual').value) || 1;
        document.getElementById('editar_valor_cup').value = (valorUSD * tasa).toFixed(2);
    }

    function calcularValorUSDDesdeCUPEditar() {
        const valorCUP = parseFloat(document.getElementById('editar_valor_cup').value) || 0;
        const tasa = parseFloat(document.getElementById('editar_tasa_visual').value) || 1;
        document.getElementById('editar_valor_usd').value = (valorCUP / tasa).toFixed(2);
    }

    function toggleModoUSDEditar() {
        const establecerUSD = document.getElementById('editar_establecer_usd').checked;
        const valorUSDInput = document.getElementById('editar_valor_usd');

        if (establecerUSD) {
            valorUSDInput.readOnly = false;
            document.getElementById('editar_establecer_cup_salida').checked = false;
            document.getElementById('editar_valor_cup').readOnly = true;
        } else {
            valorUSDInput.readOnly = true;
            // Si tampoco está en modo CUP, calcular automáticamente
            if (!document.getElementById('editar_establecer_cup_salida').checked) {
                calcularValorUSDDesdeCantidadEditar();
            }
        }
    }

    function toggleModoCUPSalidaEditar() {
        const establecerCUP = document.getElementById('editar_establecer_cup_salida').checked;
        const valorCUPInput = document.getElementById('editar_valor_cup');

        if (establecerCUP) {
            valorCUPInput.readOnly = false;
            document.getElementById('editar_establecer_usd').checked = false;
            document.getElementById('editar_valor_usd').readOnly = true;
        } else {
            valorCUPInput.readOnly = true;
            // Si tampoco está en modo USD, calcular automáticamente
            if (!document.getElementById('editar_establecer_usd').checked) {
                calcularValorUSDDesdeCantidadEditar();
            }
        }
    }

    function toggleModoCUPEditar() {
        const establecerCUP = document.getElementById('editar_establecer_cup').checked;
        const valorCUPInput = document.getElementById('editar_valor_cup');
        const valorUSDInput = document.getElementById('editar_valor_usd');

        if (establecerCUP) {
            valorCUPInput.readOnly = false;
            valorUSDInput.readOnly = true;
        } else {
            valorCUPInput.readOnly = true;
            valorUSDInput.readOnly = false;
        }
    }

    calcularValorUSDDesdeCantidadEditar

    // Modificar la función validarFechaConTasaEditar para que también obtenga la tasa
    function validarFechaConTasaEditar(fecha) {
        const fechasConTasa = <?= json_encode($fechas_con_tasa) ?>;
        const fechaError = document.getElementById('fecha_error_editar');
        const tasaInputVisual = document.getElementById('editar_tasa_visual');
        const tasaInputHidden = document.getElementById('editar_tasa');

        if (!fechasConTasa.includes(fecha)) {
            fechaError.style.display = 'block';
            tasaInputVisual.value = '';
            tasaInputHidden.value = '';
            return;
        }

        fechaError.style.display = 'none';

        // Obtener tasa desde el servidor
        fetch('../../controllers/get_tasa.php?fecha=' + fecha)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tasaInputVisual.value = data.tasa;
                    tasaInputHidden.value = data.tasa;
                    // Recalcular CUP basado en USD actual
                    calcularValorCUPDesdeUSDEditar();
                } else {
                    alert('Error al obtener la tasa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al obtener la tasa');
            });
    }

    function editarRegistro(numeroOperacion) {
        // Obtener datos del registro mediante AJAX
        const almacenId = <?= $almacen_id ?>;
        fetch(`../../controllers/get_registro_tarjeta_estiba_almacen_usd.php?numero_operacion=${numeroOperacion}&producto=<?= $producto_id ?>&almacen_id=${almacenId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Llenar el formulario con los datos
                    document.getElementById('editar_numero_operacion').value = data.registro.numero_operacion;
                    document.getElementById('editar_tipo_movimiento').value = data.registro.tipo_movimiento;
                    document.getElementById('editar_fecha').value = data.registro.fecha;
                    document.getElementById('editar_cantidad_fisica').value = data.registro.cantidad_fisica;
                    document.getElementById('editar_valor_usd').value = data.registro.valor_usd;
                    document.getElementById('editar_desde_para').value = data.registro.desde_para;
                    document.getElementById('editar_observaciones').value = data.registro.observaciones;

                    const tipoTexto = data.registro.tipo_movimiento === 'entrada' ? 'Entrada' : 'Salida';
                    document.getElementById('editar_info_operacion').textContent =
                        `(Operación #${data.registro.numero_operacion} - ${tipoTexto})`;

                    // Mostrar/ocultar controles según el tipo de movimiento
                    if (data.registro.tipo_movimiento === 'entrada') {
                        document.getElementById('editar_establecer_cup_container').style.display = 'block';
                        document.getElementById('editar_establecer_usd_cup_container').style.display = 'none';
                        document.getElementById('editar_valor_usd').readOnly = false;
                    } else {
                        document.getElementById('editar_establecer_cup_container').style.display = 'none';
                        document.getElementById('editar_establecer_usd_cup_container').style.display = 'block';
                        document.getElementById('editar_valor_usd').readOnly = true;
                        document.getElementById('editar_valor_cup').readOnly = true;
                    }

                    // Llenar select de centros de costo según el tipo
                    const selectCentro = document.getElementById('editar_desde_para');
                    selectCentro.innerHTML = '<option value="">Seleccione centro de costo</option>';

                    const centros = data.registro.tipo_movimiento === 'entrada' ?
                        <?= json_encode($centros_costo_entrada) ?> :
                        <?= json_encode($centros_costo_salida) ?>;

                    for (const [codigo, nombre] of Object.entries(centros)) {
                        const option = document.createElement('option');
                        option.value = codigo;
                        option.textContent = nombre;
                        if (codigo == data.registro.desde_para) {
                            option.selected = true;
                        }
                        selectCentro.appendChild(option);
                    }

                    // Obtener tasa para la fecha y calcular CUP
                    validarFechaConTasaEditar(data.registro.fecha);

                    // Mostrar formulario de edición
                    document.getElementById('editarFormContainer').style.display = 'block';
                    document.getElementById('entradaFormContainer').style.display = 'none';
                    document.getElementById('salidaFormContainer').style.display = 'none';

                    // Hacer scroll suave hasta el formulario de edición
                    document.getElementById('editarFormContainer').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                } else {
                    alert('Error al cargar los datos del registro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar los datos del registro');
            });
    }

    // Event listener para el campo cantidad_fisica en edición
    document.getElementById('editar_cantidad_fisica').addEventListener('input', function() {
        const tipoMovimiento = document.getElementById('editar_tipo_movimiento').value;
        const establecerUSD = document.getElementById('editar_establecer_usd')?.checked || false;
        const establecerCUP = document.getElementById('editar_establecer_cup_salida')?.checked || false;

        // Solo calcular automáticamente si es salida y ninguno de los checkboxes está marcado
        if (tipoMovimiento === 'salida' && !establecerUSD && !establecerCUP) {
            calcularValorUSDDesdeCantidadEditar(); // ¡NUEVA FUNCIÓN para edición!
        }
    });

    // Event listener para el campo valor_usd en edición (cuando está en modo editable)
    document.getElementById('editar_valor_usd').addEventListener('input', function() {
        // Si está en modo USD editable, calcular CUP automáticamente
        if (document.getElementById('editar_establecer_usd')?.checked) {
            calcularValorCUPDesdeUSDEditar();
        }
    });

    // Event listener para el campo valor_cup en edición (cuando está en modo editable)
    document.getElementById('editar_valor_cup').addEventListener('input', function() {
        // Si está en modo CUP editable, calcular USD automáticamente
        if (document.getElementById('editar_establecer_cup_salida')?.checked ||
            document.getElementById('editar_establecer_cup')?.checked) {
            calcularValorUSDDesdeCUPEditar();
        }
    });
    // Función específica para calcular valor USD en edición basado en registro ANTERIOR
    function calcularValorUSDDesdeCantidadEditar() {
        const cantidad = parseFloat(document.getElementById('editar_cantidad_fisica').value) || 0;
        const numeroOperacion = document.getElementById('editar_numero_operacion').value;
        const producto = <?= $producto_id ?>;
        const almacenId = <?= $almacen_id ?>;
        // Obtener el registro ANTERIOR al que se está editando

        fetch(`../../controllers/get_registro_anterior_almacenes_usd.php?numero_operacion=${numeroOperacion}&producto=${producto}&almacen_id=${almacenId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.registro_anterior) {
                    const saldoFisicoAnterior = parseFloat(data.registro_anterior.saldo_fisico) || 0;
                    const saldoUSDAnterior = parseFloat(data.registro_anterior.saldo_usd) || 0;

                    // Calcular precio unitario basado en el registro ANTERIOR
                    let precioUnitario = 0;
                    if (saldoFisicoAnterior > 0) {
                        precioUnitario = saldoUSDAnterior / saldoFisicoAnterior;
                    }

                    const valorUSD = cantidad * precioUnitario;
                    document.getElementById('editar_valor_usd').value = valorUSD.toFixed(2);

                    // Calcular CUP basado en la tasa actual
                    const tasa = parseFloat(document.getElementById('editar_tasa_visual').value) || 1;
                    document.getElementById('editar_valor_cup').value = (valorUSD * tasa).toFixed(2);
                } else {
                    console.error('Error al obtener registro anterior:', data.message);
                    // Fallback: usar cálculo con último registro (función existente)
                    calcularValorUSDDesdeCantidad();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                calcularValorUSDDesdeCantidad(); // Fallback
            });
    }
</script>

<?php include('../../templates/footer.php'); ?>