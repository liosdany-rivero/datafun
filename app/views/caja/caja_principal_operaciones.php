<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: caja_principal_operaciones.php
 * DESCRIPCIÓN: Gestión de operaciones de caja principal del sistema (edición/eliminación)
 * 
 * FUNCIONALIDADES:
 * - Visualización de listado completo
 * - Edición de operaciones existentes
 * - Eliminación de operaciones existentes
 * - Sincronización con cajas secundarias (Panadería, Trinidad, Galletera, Cochiquera)
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD


// Verificar permisos de escritura para Caja Principal (centro de costo 800)
$tiene_permiso_editar = false;
$sql_permiso = "SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = 800";
$stmt_permiso = $conn->prepare($sql_permiso);
$stmt_permiso->bind_param("i", $_SESSION['user_id']);
$stmt_permiso->execute();
$result_permiso = $stmt_permiso->get_result();
if ($result_permiso && $result_permiso->num_rows > 0) {
    $row = $result_permiso->fetch_assoc();
    $tiene_permiso_editar = ($row['permiso'] == 'escribir');
}
$stmt_permiso->close();

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECCIÓN 2: PROCESAMIENTO DE OPERACIONES

// 2.1 Procesamiento de eliminación de registro
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_operation'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Verificar permisos
    if (!$tiene_permiso_editar) {
        $_SESSION['error_msg'] = "⚠️ No tiene permisos para realizar esta acción";
        header("Location: caja_trinidad_operaciones.php");
        exit();
    }

    $numero_operacion = intval($_POST['numero_operacion']);

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener información de la operación
        $sql_info = "SELECT entrada, salida, fecha_operacion FROM caja_principal WHERE numero_operacion = ?";
        $stmt_info = $conn->prepare($sql_info);
        $stmt_info->bind_param("i", $numero_operacion);
        $stmt_info->execute();
        $result_info = $stmt_info->get_result();

        if ($result_info->num_rows == 0) {
            throw new Exception("Operación no encontrada");
        }

        $operacion = $result_info->fetch_assoc();
        $stmt_info->close();

        // Eliminar registros relacionados primero (entradas o salidas)
        if ($operacion['entrada'] > 0) {
            $sql_delete_rel = "DELETE FROM caja_principal_entradas WHERE numero_operacion = ?";
        } else {
            $sql_delete_rel = "DELETE FROM caja_principal_salidas WHERE numero_operacion = ?";
        }
        $stmt_delete_rel = $conn->prepare($sql_delete_rel);
        $stmt_delete_rel->bind_param("i", $numero_operacion);
        $stmt_delete_rel->execute();
        $stmt_delete_rel->close();

        // Verificar y eliminar relaciones con cajas secundarias
        $tablas_relacion = [
            'relacion_cajas_principal_panaderia' => ['tabla' => 'caja_panaderia', 'columna' => 'operacion_panaderia'],
            'relacion_cajas_principal_trinidad' => ['tabla' => 'caja_trinidad', 'columna' => 'operacion_trinidad'],
            'relacion_cajas_principal_galletera' => ['tabla' => 'caja_galletera', 'columna' => 'operacion_galletera'],
            'relacion_cajas_principal_cochiquera' => ['tabla' => 'caja_cochiquera', 'columna' => 'operacion_cochiquera']
        ];

        foreach ($tablas_relacion as $tabla_relacion => $config) {
            $sql_relacion = "SELECT {$config['columna']} FROM $tabla_relacion WHERE operacion_principal = ?";
            $stmt_relacion = $conn->prepare($sql_relacion);
            $stmt_relacion->bind_param("i", $numero_operacion);
            $stmt_relacion->execute();
            $result_relacion = $stmt_relacion->get_result();

            if ($result_relacion->num_rows > 0) {
                $relacion = $result_relacion->fetch_assoc();
                $operacion_relacionada = $relacion[$config['columna']];
                $stmt_relacion->close();

                // 1. Eliminar la relación primero
                $sql_delete_relacion = "DELETE FROM $tabla_relacion WHERE operacion_principal = ?";
                $stmt_delete_relacion = $conn->prepare($sql_delete_relacion);
                $stmt_delete_relacion->bind_param("i", $numero_operacion);
                $stmt_delete_relacion->execute();
                $stmt_delete_relacion->close();

                // 2. Obtener información para actualizar saldos en la caja destino
                $sql_destino_info = "SELECT entrada, salida, fecha_operacion FROM {$config['tabla']} WHERE numero_operacion = ?";
                $stmt_destino_info = $conn->prepare($sql_destino_info);
                $stmt_destino_info->bind_param("i", $operacion_relacionada);
                $stmt_destino_info->execute();
                $result_destino_info = $stmt_destino_info->get_result();

                if ($result_destino_info->num_rows > 0) {
                    $destino_info = $result_destino_info->fetch_assoc();
                    $stmt_destino_info->close();

                    // Obtener saldo anterior en la caja destino
                    $sql_prev_saldo_destino = "SELECT saldo FROM {$config['tabla']} 
                              WHERE (fecha_operacion < ?) 
                              OR (fecha_operacion = ? AND numero_operacion < ?)
                              ORDER BY fecha_operacion DESC, numero_operacion DESC 
                              LIMIT 1";
                    $stmt_prev_destino = $conn->prepare($sql_prev_saldo_destino);
                    $stmt_prev_destino->bind_param("ssi", $destino_info['fecha_operacion'], $destino_info['fecha_operacion'], $operacion_relacionada);
                    $stmt_prev_destino->execute();
                    $result_prev_destino = $stmt_prev_destino->get_result();
                    $saldo_anterior_destino = $result_prev_destino->num_rows > 0 ? $result_prev_destino->fetch_assoc()['saldo'] : 0;
                    $stmt_prev_destino->close();

                    // 3. Eliminar la operación en la caja destino
                    $sql_delete_destino = "DELETE FROM {$config['tabla']} WHERE numero_operacion = ?";
                    $stmt_delete_destino = $conn->prepare($sql_delete_destino);
                    $stmt_delete_destino->bind_param("i", $operacion_relacionada);
                    $stmt_delete_destino->execute();

                    if ($stmt_delete_destino->affected_rows === 0) {
                        throw new Exception("No se pudo eliminar la operación relacionada en {$config['tabla']}");
                    }

                    $stmt_delete_destino->close();

                    // 4. Actualizar saldos de registros posteriores en la caja destino
                    $sql_update_posteriores_destino = "UPDATE {$config['tabla']} p
                               JOIN (
                                   SELECT numero_operacion, 
                                          @running_balance := IFNULL(@running_balance, ?) + entrada - salida AS new_saldo
                                   FROM {$config['tabla']}, (SELECT @running_balance := ?) r
                                   WHERE (fecha_operacion > ?)
                                   OR (fecha_operacion = ? AND numero_operacion > ?)
                                   ORDER BY fecha_operacion, numero_operacion
                               ) p2 ON p.numero_operacion = p2.numero_operacion
                               SET p.saldo = p2.new_saldo";
                    $stmt_update_post_destino = $conn->prepare($sql_update_posteriores_destino);
                    $stmt_update_post_destino->bind_param("ddssi", $saldo_anterior_destino, $saldo_anterior_destino, $destino_info['fecha_operacion'], $destino_info['fecha_operacion'], $operacion_relacionada);
                    $stmt_update_post_destino->execute();
                    $stmt_update_post_destino->close();
                }
            }
        }

        // Obtener el saldo anterior al registro eliminado
        $sql_prev_saldo = "SELECT saldo FROM caja_principal 
                          WHERE (fecha_operacion < ?) 
                          OR (fecha_operacion = ? AND numero_operacion < ?)
                          ORDER BY fecha_operacion DESC, numero_operacion DESC 
                          LIMIT 1";
        $stmt_prev = $conn->prepare($sql_prev_saldo);
        $stmt_prev->bind_param("ssi", $operacion['fecha_operacion'], $operacion['fecha_operacion'], $numero_operacion);
        $stmt_prev->execute();
        $result_prev = $stmt_prev->get_result();
        $saldo_anterior = $result_prev->num_rows > 0 ? $result_prev->fetch_assoc()['saldo'] : 0;
        $stmt_prev->close();

        // Eliminar operación principal
        $sql_delete = "DELETE FROM caja_principal WHERE numero_operacion = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $numero_operacion);
        $stmt_delete->execute();
        $stmt_delete->close();

        // Actualizar saldos de registros posteriores
        $sql_update_posteriores = "UPDATE caja_principal p
                                  JOIN (
                                      SELECT numero_operacion, 
                                             @running_balance := IFNULL(@running_balance, ?) + entrada - salida AS new_saldo
                                      FROM caja_principal, (SELECT @running_balance := ?) r
                                      WHERE (fecha_operacion > ?)
                                      OR (fecha_operacion = ? AND numero_operacion > ?)
                                      ORDER BY fecha_operacion, numero_operacion
                                  ) p2 ON p.numero_operacion = p2.numero_operacion
                                  SET p.saldo = p2.new_saldo";
        $stmt_update_post = $conn->prepare($sql_update_posteriores);
        $stmt_update_post->bind_param("ddssi", $saldo_anterior, $saldo_anterior, $operacion['fecha_operacion'], $operacion['fecha_operacion'], $numero_operacion);
        $stmt_update_post->execute();
        $stmt_update_post->close();

        $conn->commit();

        $_SESSION['success_msg'] = "✅ Operación eliminada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ Error al eliminar operación: " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: caja_trinidad_dashboard.php");
    exit();
}

// 2.2 Procesamiento de edición de registro
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_edit'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Verificar permisos
    if (!$tiene_permiso_editar) {
        $_SESSION['error_msg'] = "⚠️ No tiene permisos para realizar esta acción";
        header("Location: caja_trinidad_operaciones.php");
        exit();
    }

    // Recoger datos del formulario
    $numero_operacion = intval($_POST['numero_operacion']);
    $tipo_operacion = $_POST['tipo_operacion'] === 'entrada' ? 'entrada' : 'salida';
    $observaciones = $_POST['observaciones'];

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        if ($tipo_operacion === 'entrada') {
            // Procesar edición de entrada
            $centro_costo = intval($_POST['centro_costo']);
            $tipo_entrada = $_POST['tipo_entrada'];
            $fecha_documento = !empty($_POST['fecha_documento']) ? $_POST['fecha_documento'] : null;
            $cantidad = floatval($_POST['cantidad']);
            $valor_recibido = floatval($_POST['valor_recibido']);

            // Actualizar tabla de entradas
            $sql_entradas = "UPDATE caja_principal_entradas 
                            SET tipo_entrada = ?, 
                                centro_costo_codigo = ?, 
                                fecha_documento = ?, 
                                cantidad = ?, 
                                observaciones = ?
                            WHERE numero_operacion = ?";
            $stmt_entradas = $conn->prepare($sql_entradas);
            $stmt_entradas->bind_param("sisssi", $tipo_entrada, $centro_costo, $fecha_documento, $cantidad, $observaciones, $numero_operacion);
            $stmt_entradas->execute();
            $stmt_entradas->close();

            // Calcular diferencia en el valor
            $sql_valor_actual = "SELECT entrada, fecha_operacion FROM caja_principal WHERE numero_operacion = ?";
            $stmt_valor = $conn->prepare($sql_valor_actual);
            $stmt_valor->bind_param("i", $numero_operacion);
            $stmt_valor->execute();
            $result_valor = $stmt_valor->get_result();
            $valor_actual = $result_valor->fetch_assoc();
            $stmt_valor->close();

            $diferencia = $valor_recibido - $valor_actual['entrada'];

            // Actualizar caja principal si hay diferencia
            if ($diferencia != 0) {
                // 1. Obtener saldo del registro anterior
                $sql_prev_saldo = "SELECT saldo FROM caja_principal 
                                  WHERE (fecha_operacion < ?) 
                                  OR (fecha_operacion = ? AND numero_operacion < ?)
                                  ORDER BY fecha_operacion DESC, numero_operacion DESC 
                                  LIMIT 1";
                $stmt_prev = $conn->prepare($sql_prev_saldo);
                $stmt_prev->bind_param("ssi", $valor_actual['fecha_operacion'], $valor_actual['fecha_operacion'], $numero_operacion);
                $stmt_prev->execute();
                $result_prev = $stmt_prev->get_result();
                $saldo_anterior = $result_prev->num_rows > 0 ? $result_prev->fetch_assoc()['saldo'] : 0;
                $stmt_prev->close();

                // 2. Calcular nuevo saldo para este registro
                $nuevo_saldo = $saldo_anterior + $valor_recibido;

                // 3. Actualizar este registro
                $sql_update_principal = "UPDATE caja_principal 
                                       SET entrada = ?, saldo = ?
                                       WHERE numero_operacion = ?";
                $stmt_update = $conn->prepare($sql_update_principal);
                $stmt_update->bind_param("ddi", $valor_recibido, $nuevo_saldo, $numero_operacion);
                $stmt_update->execute();
                $stmt_update->close();

                // 4. Actualizar saldos de registros posteriores
                $sql_update_posteriores = "UPDATE caja_principal p
                                          JOIN (
                                              SELECT numero_operacion, 
                                                     @running_balance := IFNULL(@running_balance, ?) + entrada - salida AS new_saldo
                                              FROM caja_principal, (SELECT @running_balance := ?) r
                                              WHERE (fecha_operacion > ?)
                                              OR (fecha_operacion = ? AND numero_operacion > ?)
                                              ORDER BY fecha_operacion, numero_operacion
                                          ) p2 ON p.numero_operacion = p2.numero_operacion
                                          SET p.saldo = p2.new_saldo";
                $stmt_update_post = $conn->prepare($sql_update_posteriores);
                $stmt_update_post->bind_param("ddssi", $nuevo_saldo, $nuevo_saldo, $valor_actual['fecha_operacion'], $valor_actual['fecha_operacion'], $numero_operacion);
                $stmt_update_post->execute();
                $stmt_update_post->close();
            }
        } else {
            // Procesar edición de salida
            $centro_costo = intval($_POST['centro_costo']);
            $tipo_salida = $_POST['tipo_salida'];
            $monto_entregado = floatval($_POST['monto_entregado']);

            // Validar que el monto sea positivo
            if ($monto_entregado <= 0) {
                throw new Exception("El monto debe ser mayor que cero");
            }

            // Actualizar tabla de salidas
            $sql_salidas = "UPDATE caja_principal_salidas 
                           SET tipo_salida = ?, 
                               centro_costo_codigo = ?, 
                               observaciones = ?
                           WHERE numero_operacion = ?";
            $stmt_salidas = $conn->prepare($sql_salidas);
            $stmt_salidas->bind_param("sisi", $tipo_salida, $centro_costo, $observaciones, $numero_operacion);
            $stmt_salidas->execute();
            $stmt_salidas->close();

            // Calcular diferencia en el valor
            $sql_valor_actual = "SELECT salida, fecha_operacion FROM caja_principal WHERE numero_operacion = ?";
            $stmt_valor = $conn->prepare($sql_valor_actual);
            $stmt_valor->bind_param("i", $numero_operacion);
            $stmt_valor->execute();
            $result_valor = $stmt_valor->get_result();
            $valor_actual = $result_valor->fetch_assoc();
            $stmt_valor->close();

            $diferencia = $monto_entregado - $valor_actual['salida'];

            // Actualizar caja principal si hay diferencia
            if ($diferencia != 0) {
                // 1. Obtener saldo del registro anterior
                $sql_prev_saldo = "SELECT saldo FROM caja_principal 
                                  WHERE (fecha_operacion < ?) 
                                  OR (fecha_operacion = ? AND numero_operacion < ?)
                                  ORDER BY fecha_operacion DESC, numero_operacion DESC 
                                  LIMIT 1";
                $stmt_prev = $conn->prepare($sql_prev_saldo);
                $stmt_prev->bind_param("ssi", $valor_actual['fecha_operacion'], $valor_actual['fecha_operacion'], $numero_operacion);
                $stmt_prev->execute();
                $result_prev = $stmt_prev->get_result();
                $saldo_anterior = $result_prev->num_rows > 0 ? $result_prev->fetch_assoc()['saldo'] : 0;
                $stmt_prev->close();

                // 2. Calcular nuevo saldo para este registro
                $nuevo_saldo = $saldo_anterior - $monto_entregado;

                // 3. Actualizar este registro
                $sql_update_principal = "UPDATE caja_principal 
                                       SET salida = ?, saldo = ?
                                       WHERE numero_operacion = ?";
                $stmt_update = $conn->prepare($sql_update_principal);
                $stmt_update->bind_param("ddi", $monto_entregado, $nuevo_saldo, $numero_operacion);
                $stmt_update->execute();
                $stmt_update->close();

                // 4. Actualizar saldos de registros posteriores
                $sql_update_posteriores = "UPDATE caja_principal p
                                          JOIN (
                                              SELECT numero_operacion, 
                                                     @running_balance := IFNULL(@running_balance, ?) + entrada - salida AS new_saldo
                                              FROM caja_principal, (SELECT @running_balance := ?) r
                                              WHERE (fecha_operacion > ?)
                                              OR (fecha_operacion = ? AND numero_operacion > ?)
                                              ORDER BY fecha_operacion, numero_operacion
                                          ) p2 ON p.numero_operacion = p2.numero_operacion
                                          SET p.saldo = p2.new_saldo";
                $stmt_update_post = $conn->prepare($sql_update_posteriores);
                $stmt_update_post->bind_param("ddssi", $nuevo_saldo, $nuevo_saldo, $valor_actual['fecha_operacion'], $valor_actual['fecha_operacion'], $numero_operacion);
                $stmt_update_post->execute();
                $stmt_update_post->close();

                // Verificar si es una transferencia a alguna caja secundaria
                $centros_transferencia = [728, 688, 708, 738]; // Códigos de centros de transferencia

                if (in_array($centro_costo, $centros_transferencia)) {
                    // Configuración para cada tipo de caja
                    $config = [
                        728 => ['tabla' => 'caja_panaderia', 'relacion' => 'relacion_cajas_principal_panaderia', 'columna' => 'operacion_panaderia'],
                        688 => ['tabla' => 'caja_trinidad', 'relacion' => 'relacion_cajas_principal_trinidad', 'columna' => 'operacion_trinidad'],
                        708 => ['tabla' => 'caja_galletera', 'relacion' => 'relacion_cajas_principal_galletera', 'columna' => 'operacion_galletera'],
                        738 => ['tabla' => 'caja_cochiquera', 'relacion' => 'relacion_cajas_principal_cochiquera', 'columna' => 'operacion_cochiquera']
                    ];

                    $tabla_destino = $config[$centro_costo]['tabla'];
                    $tabla_relacion = $config[$centro_costo]['relacion'];
                    $columna_relacion = $config[$centro_costo]['columna'];

                    // Obtener la operación relacionada
                    $sql_relacion = "SELECT $columna_relacion FROM $tabla_relacion WHERE operacion_principal = ?";
                    $stmt_relacion = $conn->prepare($sql_relacion);
                    $stmt_relacion->bind_param("i", $numero_operacion);
                    $stmt_relacion->execute();
                    $result_relacion = $stmt_relacion->get_result();

                    if ($result_relacion->num_rows > 0) {
                        $relacion = $result_relacion->fetch_assoc();
                        $operacion_relacionada = $relacion[$columna_relacion];
                        $stmt_relacion->close();

                        // Obtener información de la operación en la caja destino
                        $sql_destino_info = "SELECT entrada, fecha_operacion FROM $tabla_destino WHERE numero_operacion = ?";
                        $stmt_destino_info = $conn->prepare($sql_destino_info);
                        $stmt_destino_info->bind_param("i", $operacion_relacionada);
                        $stmt_destino_info->execute();
                        $result_destino_info = $stmt_destino_info->get_result();

                        if ($result_destino_info->num_rows > 0) {
                            $destino_info = $result_destino_info->fetch_assoc();
                            $stmt_destino_info->close();

                            // Obtener saldo anterior en la caja destino
                            $sql_prev_saldo_destino = "SELECT saldo FROM $tabla_destino 
                                WHERE (fecha_operacion < ?) 
                                OR (fecha_operacion = ? AND numero_operacion < ?)
                                ORDER BY fecha_operacion DESC, numero_operacion DESC 
                                LIMIT 1";
                            $stmt_prev_destino = $conn->prepare($sql_prev_saldo_destino);
                            $stmt_prev_destino->bind_param("ssi", $destino_info['fecha_operacion'], $destino_info['fecha_operacion'], $operacion_relacionada);
                            $stmt_prev_destino->execute();
                            $result_prev_destino = $stmt_prev_destino->get_result();
                            $saldo_anterior_destino = $result_prev_destino->num_rows > 0 ? $result_prev_destino->fetch_assoc()['saldo'] : 0;
                            $stmt_prev_destino->close();

                            // Calcular nuevo saldo para este registro en la caja destino
                            $nuevo_saldo_destino = $saldo_anterior_destino + $monto_entregado;

                            // Actualizar entrada y saldo en la caja destino
                            $sql_update_destino = "UPDATE $tabla_destino 
                                SET entrada = ?, saldo = ?
                                WHERE numero_operacion = ?";
                            $stmt_update_destino = $conn->prepare($sql_update_destino);
                            $stmt_update_destino->bind_param("ddi", $monto_entregado, $nuevo_saldo_destino, $operacion_relacionada);
                            $stmt_update_destino->execute();
                            $stmt_update_destino->close();

                            // Actualizar saldos de registros posteriores en la caja destino
                            $sql_update_posteriores_destino = "UPDATE $tabla_destino p
                                       JOIN (
                                           SELECT numero_operacion, 
                                                  @running_balance := IFNULL(@running_balance, ?) + entrada - salida AS new_saldo
                                           FROM $tabla_destino, (SELECT @running_balance := ?) r
                                           WHERE (fecha_operacion > ?)
                                           OR (fecha_operacion = ? AND numero_operacion > ?)
                                           ORDER BY fecha_operacion, numero_operacion
                                       ) p2 ON p.numero_operacion = p2.numero_operacion
                                       SET p.saldo = p2.new_saldo";
                            $stmt_update_post_destino = $conn->prepare($sql_update_posteriores_destino);
                            $stmt_update_post_destino->bind_param("ddssi", $nuevo_saldo_destino, $nuevo_saldo_destino, $destino_info['fecha_operacion'], $destino_info['fecha_operacion'], $operacion_relacionada);
                            $stmt_update_post_destino->execute();
                            $stmt_update_post_destino->close();
                        }
                    }
                }
            }
        }

        // Confirmar transacción
        $conn->commit();
        $_SESSION['success_msg'] = "✅ Operación actualizada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ Error al actualizar operación: " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    header("Location: caja_trinidad_dashboard.php");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Obtener centros de costo para formularios de edición
$centros_entrada = [];
$sql_centros_entrada = "SELECT codigo, nombre FROM centros_costo WHERE E_Caja_Princ = 1";
$result_centros_entrada = mysqli_query($conn, $sql_centros_entrada);
while ($row = mysqli_fetch_assoc($result_centros_entrada)) {
    $centros_entrada[] = $row;
}

$centros_salida = [];
$sql_centros_salida = "SELECT codigo, nombre FROM centros_costo WHERE S_Caja_Princ = 1";
$result_centros_salida = mysqli_query($conn, $sql_centros_salida);
while ($row = mysqli_fetch_assoc($result_centros_salida)) {
    $centros_salida[] = $row;
}

// Configuración de paginación
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM caja_principal"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada con información adicional
$operaciones = [];
$sql = "SELECT cp.*, 
               IF(cp.entrada > 0, cpe.tipo_entrada, cps.tipo_salida) AS tipo_operacion,
               IF(cp.entrada > 0, cpe.centro_costo_codigo, cps.centro_costo_codigo) AS centro_costo,
               IF(cp.entrada > 0, cpe.fecha_documento, NULL) AS fecha_documento,
               IF(cp.entrada > 0, cpe.cantidad, NULL) AS cantidad,
               IF(cp.entrada > 0, cpe.observaciones, cps.observaciones) AS detalle_observaciones
        FROM caja_principal cp
        LEFT JOIN caja_principal_entradas cpe ON cp.numero_operacion = cpe.numero_operacion AND cp.entrada > 0
        LEFT JOIN caja_principal_salidas cps ON cp.numero_operacion = cps.numero_operacion AND cp.salida > 0
        ORDER BY cp.fecha_operacion DESC, cp.numero_operacion DESC 
        LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $operaciones[] = $row;
}

// Obtener datos de operación específica para edición si se solicita
$operacion_editar = null;
if (isset($_GET['editar']) && $tiene_permiso_editar) {
    $numero_operacion = intval($_GET['editar']);
    $sql_editar = "SELECT cp.*, 
                          IF(cp.entrada > 0, cpe.tipo_entrada, cps.tipo_salida) AS tipo_operacion,
                          IF(cp.entrada > 0, cpe.centro_costo_codigo, cps.centro_costo_codigo) AS centro_costo,
                          IF(cp.entrada > 0, cpe.fecha_documento, NULL) AS fecha_documento,
                          IF(cp.entrada > 0, cpe.cantidad, NULL) AS cantidad,
                          IF(cp.entrada > 0, cpe.observaciones, cps.observaciones) AS detalle_observaciones
                   FROM caja_principal cp
                   LEFT JOIN caja_principal_entradas cpe ON cp.numero_operacion = cpe.numero_operacion AND cp.entrada > 0
                   LEFT JOIN caja_principal_salidas cps ON cp.numero_operacion = cps.numero_operacion AND cp.salida > 0
                   WHERE cp.numero_operacion = ?";
    $stmt_editar = $conn->prepare($sql_editar);
    $stmt_editar->bind_param("i", $numero_operacion);
    $stmt_editar->execute();
    $result_editar = $stmt_editar->get_result();
    if ($result_editar->num_rows > 0) {
        $operacion_editar = $result_editar->fetch_assoc();
    }
    $stmt_editar->close();
}




ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Caja Principal - Operaciones</h2>

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

    <!-- Tabla de operaciones -->
    <table class="table">
        <thead>
            <tr>
                <th>N° Operación</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Centro Costo</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Saldo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($operaciones as $row): ?>
                <tr>
                    <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?></td>
                    <td data-label="Fecha"><?= htmlspecialchars($row['fecha_operacion']) ?></td>
                    <td data-label="Tipo"><?= htmlspecialchars($row['tipo_operacion'] ?? 'N/A') ?></td>
                    <td data-label="Centro Costo"><?= htmlspecialchars($row['centro_costo'] ?? 'N/A') ?></td>
                    <td data-label="Entrada"><?= number_format($row['entrada'], 2) ?></td>
                    <td data-label="Salida"><?= number_format($row['salida'], 2) ?></td>
                    <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                    <td>
                        <div class="table-action-buttons">
                            <a href="caja_principal_detalles.php?numero=<?= $row['numero_operacion'] ?>&from=operaciones" class="btn-preview">Detalles</a>
                            <?php if ($tiene_permiso_editar): ?>
                                <a href="?editar=<?= $row['numero_operacion'] ?>" class="btn-preview">Editar</a>

                                <form method="POST" action="caja_principal_operaciones.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="delete_operation" value="1">
                                    <input type="hidden" name="numero_operacion" value="<?= $row['numero_operacion'] ?>">
                                    <button type="submit" class="btn-preview" onclick="return confirm('¿Está seguro de eliminar esta operación?')">Eliminar</button>
                                </form>



                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <div class="pagination">
        <?php if ($pagina_actual > 1): ?>
            <a href="?pagina=1">&laquo; Primera</a>
            <a href="?pagina=<?= $pagina_actual - 1 ?>">&lsaquo; Anterior</a>
        <?php endif; ?>

        <?php
        $inicio_paginas = max(1, $pagina_actual - 2);
        $fin_paginas = min($total_paginas, $pagina_actual + 2);

        if ($inicio_paginas > 1) echo '<span>...</span>';

        for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
            <a href="?pagina=<?= $i ?>" class="<?= $i === $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor;

        if ($fin_paginas < $total_paginas) echo '<span>...</span>';
        ?>

        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?pagina=<?= $pagina_actual + 1 ?>">Siguiente &rsaquo;</a>
            <a href="?pagina=<?= $total_paginas ?>">Última &raquo;</a>
        <?php endif; ?>
    </div>
    <br>
    <br> <!-- Formulario de Edición de Entrada -->
    <?php if ($operacion_editar && $operacion_editar['entrada'] > 0): ?>
        <div id="editFormContainer" class="sub-form">
            <h3>Editar Operación de Entrada #<?= $operacion_editar['numero_operacion'] ?></h3>
            <form method="POST" action="caja_principal_operaciones.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="save_edit" value="1">
                <input type="hidden" name="numero_operacion" value="<?= $operacion_editar['numero_operacion'] ?>">
                <input type="hidden" name="tipo_operacion" value="entrada">

                <label for="centro_costo_entrada">Establecimiento:</label>
                <select id="centro_costo_entrada" name="centro_costo" required>
                    <option value="">-- Seleccione --</option>
                    <?php foreach ($centros_entrada as $centro): ?>
                        <option value="<?= $centro['codigo'] ?>" <?= $centro['codigo'] == $operacion_editar['centro_costo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($centro['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="tipo_entrada">Tipo de entrada:</label>
                <select id="tipo_entrada" name="tipo_entrada" required>
                    <option value="">-- Seleccione --</option>
                    <option value="Dinero de transferencias" <?= $operacion_editar['tipo_operacion'] == 'Dinero de transferencias' ? 'selected' : '' ?>>Dinero de transferencias</option>
                    <option value="Dinero de IPV" <?= $operacion_editar['tipo_operacion'] == 'Dinero de IPV' ? 'selected' : '' ?>>Dinero de IPV</option>
                    <option value="Pago de deudas" <?= $operacion_editar['tipo_operacion'] == 'Pago de deudas' ? 'selected' : '' ?>>Pago de deudas</option>
                    <option value="Dinero de Tonito" <?= $operacion_editar['tipo_operacion'] == 'Dinero de Tonito' ? 'selected' : '' ?>>Dinero de Tonito</option>
                    <option value="Otras entradas" <?= $operacion_editar['tipo_operacion'] == 'Otras entradas' ? 'selected' : '' ?>>Otras entradas</option>
                    <option value="Ajustes" <?= $operacion_editar['tipo_operacion'] == 'Ajustes' ? 'selected' : '' ?>>Ajustes</option>
                </select>

                <div id="fecha_documento_container" style="<?= $operacion_editar['tipo_operacion'] == 'Dinero de IPV' ? 'display: block;' : 'display: none;' ?>">
                    <label for="fecha_documento">Fecha del documento:</label>
                    <input type="date" id="fecha_documento" name="fecha_documento" value="<?= htmlspecialchars($operacion_editar['fecha_documento']) ?>">
                </div>

                <label for="cantidad">Cantidad estimada:</label>
                <input type="number" id="cantidad" name="cantidad" step="0.01" min="0" value="<?= htmlspecialchars($operacion_editar['cantidad']) ?>">

                <label for="valor_recibido">Valor contado recibido:</label>
                <input type="number" id="valor_recibido" name="valor_recibido" step="0.01" min="0" required value="<?= htmlspecialchars($operacion_editar['entrada']) ?>">

                <label for="observaciones">Observaciones:</label>
                <textarea id="observaciones" name="observaciones" maxlength="255"><?= htmlspecialchars($operacion_editar['detalle_observaciones']) ?></textarea>




                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Guardar cambios</button>
                    <a href="caja_principal_operaciones.php" class="btn-primary">Cancelar</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Formulario de Edición de Salida -->
    <?php if ($operacion_editar && $operacion_editar['salida'] > 0): ?>
        <div id="editFormContainer" class="sub-form">
            <h3>Editar Operación de Salida #<?= $operacion_editar['numero_operacion'] ?></h3>
            <form method="POST" action="caja_principal_operaciones.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="save_edit" value="1">
                <input type="hidden" name="numero_operacion" value="<?= $operacion_editar['numero_operacion'] ?>">
                <input type="hidden" name="tipo_operacion" value="salida">

                <label for="centro_costo_salida">Establecimiento:</label>
                <select id="centro_costo_salida" name="centro_costo" required onchange="checkTransferencia(this)"
                    <?= (in_array($operacion_editar['centro_costo'], [728, 688, 708, 738])) ? 'disabled' : '' ?>>
                    <option value="">-- Seleccione --</option>
                    <?php foreach ($centros_salida as $centro):
                        // Mostrar solo centros de transferencia si estamos editando una transferencia
                        if (in_array($centro['codigo'], [728, 688, 708, 738])) {
                            if ($centro['codigo'] == $operacion_editar['centro_costo']) {
                                echo '<option value="' . $centro['codigo'] . '" selected>' . htmlspecialchars($centro['nombre']) . '</option>';
                            }
                        } else {
                            echo '<option value="' . $centro['codigo'] . '"' .
                                ($centro['codigo'] == $operacion_editar['centro_costo'] ? ' selected' : '') . '>' .
                                htmlspecialchars($centro['nombre']) . '</option>';
                        }
                    endforeach; ?>
                </select>

                <!-- Añadir input hidden si es una transferencia -->
                <?php if (in_array($operacion_editar['centro_costo'], [728, 688, 708, 738])): ?>
                    <input type="hidden" name="centro_costo" value="<?= $operacion_editar['centro_costo'] ?>">
                <?php endif; ?>

                <label for="tipo_salida">Tipo de salida:</label>
                <select id="tipo_salida" name="tipo_salida" required <?= (in_array($operacion_editar['centro_costo'], [728, 688, 708, 738])) ? 'disabled' : '' ?>>
                    <option value="">-- Seleccione --</option>
                    <option value="Gasto" <?= $operacion_editar['tipo_operacion'] == 'Gasto' ? 'selected' : '' ?>>Gasto</option>
                    <option value="Compra de productos" <?= $operacion_editar['tipo_operacion'] == 'Compra de productos' ? 'selected' : '' ?>>Compra de productos</option>
                    <option value="Compra de divisas" <?= $operacion_editar['tipo_operacion'] == 'Compra de divisas' ? 'selected' : '' ?>>Compra de divisas</option>
                    <option value="Inversiones" <?= $operacion_editar['tipo_operacion'] == 'Inversiones' ? 'selected' : '' ?>>Inversiones</option>
                    <option value="Prestamos" <?= $operacion_editar['tipo_operacion'] == 'Prestamos' ? 'selected' : '' ?>>Prestamos</option>
                    <option value="Utilidad socio" <?= $operacion_editar['tipo_operacion'] == 'Utilidad socio' ? 'selected' : '' ?>>Utilidad socio</option>
                    <option value="Dinero para Tonito" <?= $operacion_editar['tipo_operacion'] == 'Dinero para Tonito' ? 'selected' : '' ?>>Dinero para Tonito</option>
                    <option value="Otras salidas" <?= $operacion_editar['tipo_operacion'] == 'Otras salidas' ? 'selected' : '' ?>>Otras salidas</option>
                    <option value="Transferencia" <?= $operacion_editar['tipo_operacion'] == 'Transferencia' ? 'selected' : '' ?>>Transferencia</option>
                    <option value="Ajuste" <?= $operacion_editar['tipo_operacion'] == 'Ajuste' ? 'selected' : '' ?>>Ajuste</option>
                </select>

                <?php if (in_array($operacion_editar['centro_costo'], [728, 688, 708, 738])): ?>
                    <input type="hidden" name="tipo_salida" value="Transferencia">
                <?php endif; ?>

                <label for="monto_entregado">Monto entregado:</label>
                <input type="number" id="monto_entregado" name="monto_entregado" step="0.01" min="0" required
                    value="<?= htmlspecialchars($operacion_editar['salida']) ?>">

                <label for="observaciones">Observaciones:</label>
                <textarea id="observaciones" name="observaciones" maxlength="255"><?= htmlspecialchars($operacion_editar['detalle_observaciones']) ?></textarea>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Guardar cambios</button>
                    <a href="caja_principal_operaciones.php" class="btn-primary">Cancelar</a>
                </div>
            </form>
        </div>
    <?php endif; ?>


    <!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
    <script>
        /**
         * Muestra/oculta el campo de fecha documento según el tipo de entrada
         */
        document.getElementById('tipo_entrada')?.addEventListener('change', function() {
            const fechaDocContainer = document.getElementById('fecha_documento_container');
            if (fechaDocContainer) {
                fechaDocContainer.style.display = (this.value === 'Dinero de IPV') ? 'block' : 'none';
            }
        });

        /**
         * Verifica si el establecimiento seleccionado es de transferencia
         * y ajusta el tipo de salida automáticamente
         * @param {object} select - Elemento select del establecimiento
         */
        function checkTransferencia(select) {
            const tipoSalida = document.getElementById('tipo_salida');
            const centrosTransferencia = [728, 688, 708, 738]; // Todos los centros de transferencia
            const esTransferencia = centrosTransferencia.includes(parseInt(select.value));

            if (esTransferencia) {
                tipoSalida.value = 'Transferencia';
                tipoSalida.disabled = true;
                // Agregar campo hidden para asegurar el envío
                if (!document.getElementById('force_transferencia')) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.id = 'force_transferencia';
                    hiddenInput.name = 'tipo_salida';
                    hiddenInput.value = 'Transferencia';
                    document.querySelector('form').appendChild(hiddenInput);
                }
            } else {
                tipoSalida.disabled = false;
                const hiddenInput = document.getElementById('force_transferencia');
                if (hiddenInput) hiddenInput.remove();
            }
        }

        // Inicializar el estado del formulario si es una transferencia
        document.addEventListener('DOMContentLoaded', function() {
            const centroCostoSelect = document.getElementById('centro_costo_salida');
            if (centroCostoSelect) {
                checkTransferencia(centroCostoSelect);
            }

            const editForm = document.getElementById('editFormContainer');
            if (editForm) {
                editForm.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    </script>

    <?php include('../../templates/footer.php'); ?>