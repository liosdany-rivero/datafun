<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: caja_galletera_operaciones.php
 * DESCRIPCIÓN: Gestión de operaciones de caja galletera (edición/eliminación)
 * 
 * FUNCIONALIDADES:
 * - Edición de operaciones existentes
 * - Eliminación de operaciones
 * - Visualización de listado completo
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Verificar permisos de escritura para Caja Galletera (centro de costo 708)
$tiene_permiso_editar = false;
$tiene_permiso_leer = false;
$tiene_permiso_tramitar = false;
$sql_permiso = "SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = 708";
$stmt_permiso = $conn->prepare($sql_permiso);
$stmt_permiso->bind_param("i", $_SESSION['user_id']);
$stmt_permiso->execute();
$result_permiso = $stmt_permiso->get_result();
if ($result_permiso && $result_permiso->num_rows > 0) {
    $row = $result_permiso->fetch_assoc();
    $tiene_permiso_editar = ($row['permiso'] == 'escribir');
    $tiene_permiso_leer = ($row['permiso'] == 'leer');
    $tiene_permiso_tramitar = ($row['permiso'] == 'tramitar');
}
$stmt_permiso->close();

if (!$tiene_permiso_editar && !$tiene_permiso_leer  && !$tiene_permiso_tramitar) {
    $_SESSION['error_msg'] = "⚠️ No tienes permisos para acceder a esta sección";
    header("Location: caja_galletera_dashboard.php");
    exit();
}

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECCIÓN 2: PROCESAMIENTO DE OPERACIONES

// 2.1 Procesamiento de eliminación de operación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_operation'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $numero_operacion = intval($_POST['numero_operacion']);

    // Verificar si la operación está tramitada
    $sql_tramitado = "SELECT tramitado FROM caja_galletera WHERE numero_operacion = ?";
    $stmt_tramitado = $conn->prepare($sql_tramitado);
    $stmt_tramitado->bind_param("i", $numero_operacion);
    $stmt_tramitado->execute();
    $result_tramitado = $stmt_tramitado->get_result();

    if ($result_tramitado->num_rows > 0) {
        $operacion = $result_tramitado->fetch_assoc();
        if ($operacion['tramitado'] == 1) {
            $_SESSION['error_msg'] = "⚠️ No se puede eliminar una operación tramitada";
            header("Location: caja_galletera_operaciones.php");
            exit();
        }
    }
    $stmt_tramitado->close();

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener datos de la operación a eliminar
        $sql_select = "SELECT entrada, salida FROM caja_galletera WHERE numero_operacion = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $numero_operacion);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();

        if ($result_select->num_rows == 0) {
            throw new Exception("Operación no encontrada");
        }

        $operacion = $result_select->fetch_assoc();
        $monto = $operacion['entrada'] > 0 ? $operacion['entrada'] : $operacion['salida'];
        $stmt_select->close();

        // Eliminar la operación
        $sql_delete = "DELETE FROM caja_galletera WHERE numero_operacion = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $numero_operacion);
        $stmt_delete->execute();
        $stmt_delete->close();

        // Recalcular saldos de todas las operaciones posteriores
        if ($operacion['entrada'] > 0) {
            // Si era una entrada, restar el monto a los saldos posteriores
            $sql_update = "UPDATE caja_galletera 
                          SET saldo = saldo - ? 
                          WHERE numero_operacion > ?";
        } else {
            // Si era una salida, sumar el monto a los saldos posteriores
            $sql_update = "UPDATE caja_galletera 
                          SET saldo = saldo + ? 
                          WHERE numero_operacion > ?";
        }

        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("di", $monto, $numero_operacion);
        $stmt_update->execute();
        $stmt_update->close();

        // Confirmar transacción
        $conn->commit();
        $_SESSION['success_msg'] = "✅ Operación eliminada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ Error al eliminar operación: " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: caja_galletera_operaciones.php");
    exit();
}

// 2.2 Procesamiento de edición de operación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_operation'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $numero_operacion = intval($_POST['numero_operacion']);

    // Verificar si la operación está tramitada
    $sql_tramitado = "SELECT tramitado FROM caja_galletera WHERE numero_operacion = ?";
    $stmt_tramitado = $conn->prepare($sql_tramitado);
    $stmt_tramitado->bind_param("i", $numero_operacion);
    $stmt_tramitado->execute();
    $result_tramitado = $stmt_tramitado->get_result();

    if ($result_tramitado->num_rows > 0) {
        $operacion = $result_tramitado->fetch_assoc();
        if ($operacion['tramitado'] == 1) {
            $_SESSION['error_msg'] = "⚠️ No se puede editar una operación tramitada";
            header("Location: caja_galletera_operaciones.php");
            exit();
        }
    }
    $stmt_tramitado->close();

    $nuevo_desde_para = intval($_POST['desde_para']);
    $nueva_entrada = floatval($_POST['entrada']);
    $nueva_salida = floatval($_POST['salida']);
    $nuevas_observaciones = $_POST['observaciones'];

    // Validar que solo haya entrada o salida, no ambas
    if ($nueva_entrada > 0 && $nueva_salida > 0) {
        $_SESSION['error_msg'] = "⚠️ Una operación no puede tener entrada y salida simultáneamente";
        header("Location: caja_galletera_operaciones.php");
        exit();
    }

    // Validar que el monto sea positivo
    if ($nueva_entrada < 0 || $nueva_salida < 0) {
        $_SESSION['error_msg'] = "⚠️ El monto debe ser mayor o igual a cero";
        header("Location: caja_galletera_operaciones.php");
        exit();
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener datos actuales de la operación y el saldo anterior
        $sql_select = "SELECT 
                        cp.entrada, 
                        cp.salida, 
                        cp.saldo,
                        (SELECT saldo FROM caja_galletera 
                         WHERE numero_operacion < ? 
                         ORDER BY numero_operacion DESC LIMIT 1) as saldo_anterior
                      FROM caja_galletera cp
                      WHERE cp.numero_operacion = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("ii", $numero_operacion, $numero_operacion);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();

        if ($result_select->num_rows == 0) {
            throw new Exception("Operación no encontrada");
        }

        $operacion_actual = $result_select->fetch_assoc();
        $stmt_select->close();

        // Calcular nuevo saldo para esta operación
        $nuevo_saldo = $operacion_actual['saldo_anterior'];
        if ($nueva_entrada > 0) {
            $nuevo_saldo += $nueva_entrada;
        } else {
            $nuevo_saldo -= $nueva_salida;
        }

        // Calcular diferencia para ajustar saldos posteriores
        $diferencia = $nuevo_saldo - $operacion_actual['saldo'];

        // Actualizar la operación con el nuevo saldo
        $sql_update = "UPDATE caja_galletera 
                      SET entrada = ?, salida = ?, desde_para = ?, observaciones = ?, saldo = ?
                      WHERE numero_operacion = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ddisdi", $nueva_entrada, $nueva_salida, $nuevo_desde_para, $nuevas_observaciones, $nuevo_saldo, $numero_operacion);
        $stmt_update->execute();
        $stmt_update->close();

        // Recalcular saldos de todas las operaciones posteriores si hubo cambio en el saldo
        if ($diferencia != 0) {
            $sql_update_saldos = "UPDATE caja_galletera 
                                 SET saldo = saldo + ? 
                                 WHERE numero_operacion > ?";
            $stmt_update_saldos = $conn->prepare($sql_update_saldos);
            $stmt_update_saldos->bind_param("di", $diferencia, $numero_operacion);
            $stmt_update_saldos->execute();
            $stmt_update_saldos->close();
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
    ob_clean();
    header("Location: caja_galletera_operaciones.php");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Obtener centros de costo para dropdowns
$centros_costo = [];
$sql_centros = "SELECT codigo, nombre FROM centros_costo WHERE E_Caja_Gallet = 1 OR S_Caja_Gallet = 1";
$result_centros = mysqli_query($conn, $sql_centros);
while ($row = mysqli_fetch_assoc($result_centros)) {
    $centros_costo[$row['codigo']] = $row['nombre'];
}

// Configuración de paginación
$por_pagina = 15;
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM caja_galletera"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada con join para obtener nombre del centro de costo y verificar si está relacionada
$operaciones = [];
$sql = "SELECT cp.*, cc.nombre as centro_nombre, 
        IFNULL((SELECT 1 FROM relacion_cajas_principal_galletera r WHERE r.operacion_galletera = cp.numero_operacion LIMIT 1), 0) as relacionada
        FROM caja_galletera cp
        LEFT JOIN centros_costo cc ON cp.desde_para = cc.codigo
        ORDER BY fecha_operacion DESC, numero_operacion DESC 
        LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $operaciones[] = $row;
}

// Obtener datos de operación para edición si se solicita
$operacion_editar = null;
if (isset($_GET['editar'])) {
    $numero_operacion = intval($_GET['editar']);

    // Verificar si la operación está relacionada antes de permitir edición
    $sql_relacionada = "SELECT 1 FROM relacion_cajas_principal_galletera WHERE operacion_galletera = ?";
    $stmt_relacionada = $conn->prepare($sql_relacionada);
    $stmt_relacionada->bind_param("i", $numero_operacion);
    $stmt_relacionada->execute();
    $result_relacionada = $stmt_relacionada->get_result();

    if ($result_relacionada->num_rows == 0) {
        // Verificar si la operación está tramitada
        $sql_tramitado = "SELECT tramitado FROM caja_galletera WHERE numero_operacion = ?";
        $stmt_tramitado = $conn->prepare($sql_tramitado);
        $stmt_tramitado->bind_param("i", $numero_operacion);
        $stmt_tramitado->execute();
        $result_tramitado = $stmt_tramitado->get_result();

        if ($result_tramitado->num_rows > 0) {
            $operacion = $result_tramitado->fetch_assoc();
            if ($operacion['tramitado'] == 0) {
                $sql_editar = "SELECT * FROM caja_galletera WHERE numero_operacion = ?";
                $stmt_editar = $conn->prepare($sql_editar);
                $stmt_editar->bind_param("i", $numero_operacion);
                $stmt_editar->execute();
                $result_editar = $stmt_editar->get_result();
                if ($result_editar->num_rows > 0) {
                    $operacion_editar = $result_editar->fetch_assoc();
                }
                $stmt_editar->close();
            } else {
                $_SESSION['error_msg'] = "⚠️ No se puede editar una operación tramitada";
                header("Location: caja_galletera_operaciones.php");
                exit();
            }
        }
        $stmt_tramitado->close();
    } else {
        $_SESSION['error_msg'] = "⚠️ No se puede editar una operación relacionada con la caja principal";
        header("Location: caja_galletera_operaciones.php");
        exit();
    }
    $stmt_relacionada->close();
}

ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Caja Galletera - Operaciones</h2>

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
                <th>Entrada</th>
                <th>Salida</th>
                <th>Saldo</th>
                <th>Centro de Costo</th>
                <th>Observaciones</th>
                <th>Tramitado</th>
                <?php if ($tiene_permiso_editar): ?>
                    <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($operaciones as $row): ?>
                <tr>
                    <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?>
                        <?php if ($row['relacionada']): ?>
                            <span class="badge">Relacionada</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Fecha"><?= htmlspecialchars($row['fecha_operacion']) ?></td>
                    <td data-label="Entrada"><?= number_format($row['entrada'], 2) ?></td>
                    <td data-label="Salida"><?= number_format($row['salida'], 2) ?></td>
                    <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                    <td data-label="Centro costo"><?= htmlspecialchars($row['centro_nombre']) ?></td>
                    <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones']) ?></td>
                    <td data-label="Tramitado">
                        <?= $row['tramitado'] ? '✅ Sí' : '❌ No' ?>
                    </td>
                    <?php if ($tiene_permiso_editar): ?>
                        <td class="actions-cell">
                            <div class="table-action-buttons">
                                <?php if (!$row['relacionada'] && $row['tramitado'] == 0): ?>
                                    <button onclick="showEditForm(<?= $row['numero_operacion'] ?>)" class="btn-preview">Editar</button>
                                    <form method="POST" action="caja_galletera_operaciones.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="numero_operacion" value="<?= $row['numero_operacion'] ?>">
                                        <input type="hidden" name="delete_operation" value="1">
                                        <button type="submit" class="btn-preview" onclick="return confirm('¿Estás seguro de eliminar esta operación?')">Eliminar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Operación <?= $row['relacionada'] ? 'relacionada' : 'tramitada' ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    <?php endif; ?>
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

    <!-- Formulario de edición (oculto inicialmente) -->
    <div id="editFormContainer" class="sub-form" style="display: <?= $operacion_editar ? 'block' : 'none' ?>;">
        <h3>Editar Operación</h3>
        <form method="POST" action="caja_galletera_operaciones.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="edit_operation" value="1">
            <input type="hidden" name="numero_operacion" value="<?= $operacion_editar['numero_operacion'] ?? '' ?>">

            <label for="desde_para_edit">Centro de Costo:</label>
            <select id="desde_para_edit" name="desde_para" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($centros_costo as $codigo => $nombre): ?>
                    <option value="<?= $codigo ?>" <?= ($operacion_editar['desde_para'] ?? '') == $codigo ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="input-group">
                <div class="input-field">
                    <label for="entrada_edit">Entrada:</label>
                    <input type="number" id="entrada_edit" name="entrada" step="0.01" min="0"
                        value="<?= $operacion_editar['entrada'] ?? 0 ?>" required>
                </div>
                <div class="input-field">
                    <label for="salida_edit">Salida:</label>
                    <input type="number" id="salida_edit" name="salida" step="0.01" min="0"
                        value="<?= $operacion_editar['salida'] ?? 0 ?>" required>
                </div>
            </div>

            <label for="observaciones_edit">Observaciones:</label>
            <textarea id="observaciones_edit" name="observaciones" maxlength="255"><?= htmlspecialchars($operacion_editar['observaciones'] ?? '') ?></textarea>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn-primary">Guardar cambios</button>
                <button type="button" onclick="hideEditForm()" class="btn-primary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
<script>
    /**
     * Muestra el formulario de edición para una operación específica
     * @param {number} numeroOperacion - Número de operación a editar
     */
    function showEditForm(numeroOperacion) {
        window.location.href = `caja_galletera_operaciones.php?editar=${numeroOperacion}`;
    }

    /**
     * Oculta el formulario de edición
     */
    function hideEditForm() {
        window.location.href = "caja_galletera_operaciones.php";
    }

    // Validación para asegurar que solo haya entrada o salida, no ambas
    document.addEventListener('DOMContentLoaded', function() {
        const entradaEdit = document.getElementById('entrada_edit');
        const salidaEdit = document.getElementById('salida_edit');

        if (entradaEdit && salidaEdit) {
            entradaEdit.addEventListener('input', function() {
                if (this.value > 0) {
                    salidaEdit.value = 0;
                }
            });

            salidaEdit.addEventListener('input', function() {
                if (this.value > 0) {
                    entradaEdit.value = 0;
                }
            });
        }

        // Hacer scroll al formulario de edición si está visible
        if (document.getElementById('editFormContainer').style.display === 'block') {
            document.getElementById('editFormContainer').scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
</script>

<style>
    .text-muted {
        color: #6c757d;
        font-style: italic;
    }

    .badge {
        background-color: #6c757d;
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.8em;
        margin-left: 5px;
    }
</style>

<?php include('../../templates/footer.php'); ?>