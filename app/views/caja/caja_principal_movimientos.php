<?php
ob_start();
/**
 * ARCHIVO: caja_principal_movimientos.php
 * DESCRIPCIÓN: Gestión de operaciones de caja principal del sistema
 * 
 * FUNCIONALIDADES:
 * - Creación de operaciones de entrada en caja
 * - Creación de operaciones de salida en caja
 * - Visualización de listado completo
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');
require_once('../../controllers/auth_user_check.php');
require_once('../../controllers/config.php');

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS

// 2.1 Procesamiento de formulario de entrada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_entrada'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos del formulario
    $centro_costo = intval($_POST['centro_costo']);
    $tipo_entrada = $_POST['tipo_entrada'];
    $fecha_documento = !empty($_POST['fecha_documento']) ? $_POST['fecha_documento'] : null;
    $cantidad = floatval($_POST['cantidad']);
    $valor_recibido = floatval($_POST['valor_recibido']);
    $observaciones = $_POST['observaciones'];

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener último saldo
        $saldo_anterior = 0;
        $sql_saldo = "SELECT saldo FROM caja_principal ORDER BY numero_operacion DESC LIMIT 1";
        $result_saldo = $conn->query($sql_saldo);
        if ($result_saldo && $result_saldo->num_rows > 0) {
            $row = $result_saldo->fetch_assoc();
            $saldo_anterior = $row['saldo'];
        }
        $nuevo_saldo = $saldo_anterior + $valor_recibido;

        // Insertar en caja_principal
        $sql_principal = "INSERT INTO caja_principal 
                         (fecha_operacion, entrada, salida, saldo) 
                         VALUES (CURDATE(), ?, 0, ?)";
        $stmt_principal = $conn->prepare($sql_principal);
        $stmt_principal->bind_param("dd", $valor_recibido, $nuevo_saldo);
        $stmt_principal->execute();
        $id_operacion = $conn->insert_id;
        $stmt_principal->close();

        // Insertar en caja_principal_entradas
        $sql_entradas = "INSERT INTO caja_principal_entradas 
                 (numero_operacion, tipo_entrada, centro_costo_codigo, fecha_documento, cantidad, observaciones, tramitado) 
                 VALUES (?, ?, ?, ?, ?, ?, 0)";
        $stmt_entradas = $conn->prepare($sql_entradas);
        $stmt_entradas->bind_param("isisss", $id_operacion, $tipo_entrada, $centro_costo, $fecha_documento, $cantidad, $observaciones);
        $stmt_entradas->execute();
        $stmt_entradas->close();

        // Confirmar transacción
        $conn->commit();
        $_SESSION['success_msg'] = "✅ Entrada registrada correctamente.";
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ Error al registrar entrada: " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: caja_principal_movimientos.php");
    exit();
}

// 2.2 Procesamiento de formulario de salida
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_salida'])) {
    // Validación CSRF - Protección contra ataques
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos del formulario
    $centro_costo = intval($_POST['centro_costo']);
    $es_transferencia = in_array($centro_costo, [728, 688, 708, 738]); // Centros de transferencia

    // Determinar tipo_salida - si es transferencia, forzar el tipo
    $tipo_salida = $es_transferencia ? 'Transferencia' : (isset($_POST['tipo_salida']) ? $_POST['tipo_salida'] : '');

    // Validar campos obligatorios
    if (empty($tipo_salida)) {
        $_SESSION['error_msg'] = "⚠️ Tipo de salida no especificado";
        header("Location: caja_principal_movimientos.php");
        exit();
    }

    $monto_entregado = floatval($_POST['monto_entregado']);
    $observaciones = $_POST['observaciones'];

    // Validar que el monto sea positivo
    if ($monto_entregado <= 0) {
        $_SESSION['error_msg'] = "⚠️ El monto debe ser mayor que cero";
        header("Location: caja_principal_movimientos.php");
        exit();
    }

    // Iniciar transacción para asegurar integridad de datos
    $conn->begin_transaction();

    try {
        // 1. Obtener último saldo de caja principal
        $saldo_anterior = 0;
        $sql_saldo = "SELECT saldo FROM caja_principal ORDER BY numero_operacion DESC LIMIT 1";
        $result_saldo = $conn->query($sql_saldo);

        if ($result_saldo && $result_saldo->num_rows > 0) {
            $row = $result_saldo->fetch_assoc();
            $saldo_anterior = $row['saldo'];

            // Validar saldo suficiente
            if ($saldo_anterior < $monto_entregado) {
                throw new Exception("Saldo insuficiente en caja principal");
            }
        } else {
            throw new Exception("No se pudo obtener el saldo actual de la caja principal");
        }

        $nuevo_saldo = $saldo_anterior - $monto_entregado;

        // 2. Insertar en caja_principal
        $sql_principal = "INSERT INTO caja_principal 
                         (fecha_operacion, entrada, salida, saldo) 
                         VALUES (CURDATE(), 0, ?, ?)";
        $stmt_principal = $conn->prepare($sql_principal);
        $stmt_principal->bind_param("dd", $monto_entregado, $nuevo_saldo);
        $stmt_principal->execute();
        $id_operacion = $conn->insert_id;
        $stmt_principal->close();

        // 3. Insertar en caja_principal_salidas
        $sql_salidas = "INSERT INTO caja_principal_salidas 
                       (numero_operacion, tipo_salida, centro_costo_codigo, observaciones, tramitado) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt_salidas = $conn->prepare($sql_salidas);
        $tramitado_salida = $es_transferencia ? 1 : 0;
        $stmt_salidas->bind_param("isisi", $id_operacion, $tipo_salida, $centro_costo, $observaciones, $tramitado_salida);
        $stmt_salidas->execute();
        $stmt_salidas->close();

        // 4. Si es transferencia a alguna caja
        if ($es_transferencia) {
            $tabla_destino = '';
            $tabla_relacion = '';
            $id_columna = '';

            // Determinar a qué caja es la transferencia
            switch ($centro_costo) {
                case 728: // Caja Panadería
                    $tabla_destino = 'caja_panaderia';
                    $tabla_relacion = 'relacion_cajas_principal_panaderia';
                    $id_columna = 'operacion_panaderia';
                    break;
                case 688: // Caja Trinidad
                    $tabla_destino = 'caja_trinidad';
                    $tabla_relacion = 'relacion_cajas_principal_trinidad';
                    $id_columna = 'operacion_trinidad';
                    break;
                case 708: // Caja Galletera
                    $tabla_destino = 'caja_galletera';
                    $tabla_relacion = 'relacion_cajas_principal_galletera';
                    $id_columna = 'operacion_galletera';
                    break;
                case 738: // Caja Cochiquera
                    $tabla_destino = 'caja_cochiquera';
                    $tabla_relacion = 'relacion_cajas_principal_cochiquera';
                    $id_columna = 'operacion_cochiquera';
                    break;
            }

            if (!empty($tabla_destino)) {
                // Obtener último saldo de la caja destino
                $saldo_destino = 0;
                $sql_saldo_destino = "SELECT saldo FROM $tabla_destino ORDER BY numero_operacion DESC LIMIT 1";
                $result_saldo_destino = $conn->query($sql_saldo_destino);
                if ($result_saldo_destino && $result_saldo_destino->num_rows > 0) {
                    $row = $result_saldo_destino->fetch_assoc();
                    $saldo_destino = $row['saldo'];
                }
                $nuevo_saldo_destino = $saldo_destino + $monto_entregado;

                // Insertar en la caja destino
                $obs_destino = "Transferencia automática de la caja principal (OP#$id_operacion)";
                $sql_destino = "INSERT INTO $tabla_destino 
                             (fecha_operacion, entrada, salida, saldo, desde_para, observaciones, tramitado) 
                             VALUES (CURDATE(), ?, 0, ?, ?, ?, 1)";
                $stmt_destino = $conn->prepare($sql_destino);
                $stmt_destino->bind_param("ddis", $monto_entregado, $nuevo_saldo_destino, $centro_costo, $obs_destino);
                $stmt_destino->execute();
                $id_operacion_destino = $conn->insert_id;
                $stmt_destino->close();

                // Insertar en tabla de relación
                $sql_relacion = "INSERT INTO $tabla_relacion 
                                (operacion_principal, $id_columna) 
                                VALUES (?, ?)";
                $stmt_relacion = $conn->prepare($sql_relacion);
                $stmt_relacion->bind_param("ii", $id_operacion, $id_operacion_destino);
                $stmt_relacion->execute();
                $stmt_relacion->close();
            }
        }

        // Confirmar transacción
        $conn->commit();
        $_SESSION['success_msg'] = "✅ Salida registrada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ Error al registrar salida: " . $e->getMessage();
        error_log("Error en caja_principal_movimientos.php: " . $e->getMessage());
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: caja_principal_movimientos.php");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Obtener centros de costo para entradas (E_Caja_Princ = 1)
$centros_entrada = [];
$sql_centros_entrada = "SELECT codigo, nombre FROM centros_costo WHERE E_Caja_Princ = 1";
$result_centros_entrada = mysqli_query($conn, $sql_centros_entrada);
while ($row = mysqli_fetch_assoc($result_centros_entrada)) {
    $centros_entrada[] = $row;
}

// Obtener centros de costo para salidas (S_Caja_Princ = 1)
$centros_salida = [];
$sql_centros_salida = "SELECT codigo, nombre FROM centros_costo WHERE S_Caja_Princ = 1";
$result_centros_salida = mysqli_query($conn, $sql_centros_salida);
while ($row = mysqli_fetch_assoc($result_centros_salida)) {
    $centros_salida[] = $row;
}

// Configuración de paginación
$por_pagina = 1;
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM caja_principal"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada
$operaciones = [];
$sql = "SELECT * FROM caja_principal ORDER BY fecha_operacion DESC, numero_operacion DESC LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $operaciones[] = $row;
}
ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->
<div class="form-container">
    <h2>Caja Principal - Movimientos</h2>

    <!-- Tabla de operaciones -->
    <table class="table">
        <thead>
            <tr>
                <th>N° Operación</th>
                <th>Fecha</th>
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
                    <td data-label="Entrada"><?= number_format($row['entrada'], 2) ?></td>
                    <td data-label="Salida"><?= number_format($row['salida'], 2) ?></td>
                    <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                    <td>
                        <div class="table-action-buttons">
                            <a href="caja_principal_detalles.php?numero=<?= $row['numero_operacion'] ?>&from=movimientos" class="btn-preview">Detalles</a>
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
        <h3>Registro de Entrada</h3>
        <form method="POST" action="caja_principal_movimientos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="save_entrada" value="1">

            <label for="centro_costo_entrada">Establecimiento:</label>
            <select id="centro_costo_entrada" name="centro_costo" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($centros_entrada as $centro): ?>
                    <option value="<?= $centro['codigo'] ?>"><?= htmlspecialchars($centro['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="tipo_entrada">Tipo de entrada:</label>
            <select id="tipo_entrada" name="tipo_entrada" required>
                <option value="">-- Seleccione --</option>
                <option value="Dinero de transferencias">Dinero de transferencias</option>
                <option value="Dinero de IPV">Dinero de IPV</option>
                <option value="Pago de deudas">Pago de deudas</option>
                <option value="Dinero de Tonito">Dinero de Tonito</option>
                <option value="Otras entradas">Otras entradas</option>
                <option value="Ajustes">Ajustes</option>
            </select>

            <div id="fecha_documento_container" style="display: none;">
                <label for="fecha_documento">Fecha del documento:</label>
                <input type="date" id="fecha_documento" name="fecha_documento">
            </div>

            <label for="cantidad">Cantidad estimada:</label>
            <input type="number" id="cantidad" name="cantidad" step="0.01" min="0">

            <div class="input-button-group">
                <div class="input-field">
                    <label for="valor_recibido">Valor contado recibido:</label>
                    <input type="number" id="valor_recibido" name="valor_recibido" step="0.01" min="0" required>
                </div>
                <button type="button" onclick="showContadorDinero('valor_recibido')" class="btn-secondary btn-counter">Contador de dinero</button>
            </div>

            <label for="observaciones_entrada">Observaciones:</label>
            <textarea id="observaciones_entrada" name="observaciones" maxlength="255"></textarea>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn-primary">Guardar operación</button>
                <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Formulario Salida -->
    <div id="salidaFormContainer" class="sub-form" style="display: none;">
        <h3>Registro de Salida</h3>
        <form method="POST" action="caja_principal_movimientos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="save_salida" value="1">

            <label for="centro_costo_salida">Establecimiento:</label>
            <select id="centro_costo_salida" name="centro_costo" required onchange="checkTransferencia(this)">
                <option value="">-- Seleccione --</option>
                <?php foreach ($centros_salida as $centro): ?>
                    <option value="<?= $centro['codigo'] ?>"><?= htmlspecialchars($centro['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="tipo_salida">Tipo de salida:</label>
            <select id="tipo_salida" name="tipo_salida" required>
                <option value="">-- Seleccione --</option>
                <option value="Gasto">Gasto</option>
                <option value="Compra de productos">Compra de productos</option>
                <option value="Compra de divisas">Compra de divisas</option>
                <option value="Inversiones">Inversiones</option>
                <option value="Prestamos">Prestamos</option>
                <option value="Utilidad socio">Utilidad socio</option>
                <option value="Dinero para Tonito">Dinero para Tonito</option>
                <option value="Otras salidas">Otras salidas</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Ajuste">Ajuste</option>
            </select>

            <div class="input-button-group">
                <div class="input-field">
                    <label for="monto_entregado">Monto entregado:</label>
                    <input type="number" id="monto_entregado" name="monto_entregado" step="0.01" min="0" required>
                </div>
                <button type="button" onclick="showContadorDinero('monto_entregado')" class="btn-secondary btn-counter">Contador de dinero</button>
            </div>

            <label for="observaciones_salida">Observaciones:</label>
            <textarea id="observaciones_salida" name="observaciones" maxlength="255"></textarea>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn-primary">Guardar operación</button>
                <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<BR>
<BR>
<BR>

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li><button onclick="showEntradaForm()" class="nav-button">+ Entrada</button></li>
        <li><button onclick="showSalidaForm()" class="nav-button">+ Salida</button></li>
        <li><button type="button" onclick="showContadorDinero('barra_estado')" class="nav-button">Contador</button></li>
    </ul>
</div>

<!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
<script>
    /**
     * Muestra formulario de entrada
     */
    function showEntradaForm() {
        hideForms();
        document.getElementById('entradaFormContainer').style.display = 'block';
        scrollToBottom();
        // Restaurar datos si existen
        restoreFormData('entrada');
    }

    /**
     * Muestra formulario de salida
     */
    function showSalidaForm() {
        hideForms();
        document.getElementById('salidaFormContainer').style.display = 'block';
        scrollToBottom();
        // Restaurar datos si existen
        restoreFormData('salida');
    }

    /**
     * Oculta todos los formularios emergentes
     */
    function hideForms() {
        document.getElementById('entradaFormContainer').style.display = 'none';
        document.getElementById('salidaFormContainer').style.display = 'none';
        hideContadorDinero();
    }

    /**
     * Muestra el modal del contador de dinero
     * @param {string} targetField - Campo objetivo donde se colocará el resultado
     */
    function showContadorDinero(targetField) {
        // Guardar los datos del formulario activo antes de ir al contador
        saveFormData();

        // Determinar qué formulario está activo
        const formType = document.getElementById('entradaFormContainer').style.display === 'block' ? 'entrada' :
            document.getElementById('salidaFormContainer').style.display === 'block' ? 'salida' : '';

        window.location.href = `contador_dinero.php?target=${targetField}&source=caja_principal_movimientos.php&formType=${formType}`;
    }

    /**
     * Oculta el modal del contador de dinero
     */
    function hideContadorDinero(result) {
        const modal = document.getElementById('contadorDineroModal');
        if (modal) {
            // Si se pasa un resultado, actualizar el campo correspondiente
            if (result && modal.dataset.targetField) {
                document.getElementById(modal.dataset.targetField).value = result;
            }
            modal.remove();
        }
    }

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
                document.querySelector('#salidaFormContainer form').appendChild(hiddenInput);
            }
        } else {
            tipoSalida.disabled = false;
            const hiddenInput = document.getElementById('force_transferencia');
            if (hiddenInput) hiddenInput.remove();
        }
    }

    /**
     * Muestra/oculta el campo de fecha documento según el tipo de entrada
     */
    document.getElementById('tipo_entrada').addEventListener('change', function() {
        const fechaDocContainer = document.getElementById('fecha_documento_container');
        fechaDocContainer.style.display = (this.value === 'Dinero de IPV') ? 'block' : 'none';
    });

    /**
     * Guarda los datos del formulario activo en localStorage
     */
    function saveFormData() {
        const entradaForm = document.getElementById('entradaFormContainer').style.display === 'block';
        const salidaForm = document.getElementById('salidaFormContainer').style.display === 'block';

        if (entradaForm) {
            const formData = {
                formType: 'entrada',
                centro_costo: document.getElementById('centro_costo_entrada').value,
                tipo_entrada: document.getElementById('tipo_entrada').value,
                fecha_documento: document.getElementById('fecha_documento').value,
                cantidad: document.getElementById('cantidad').value,
                valor_recibido: document.getElementById('valor_recibido').value,
                observaciones: document.getElementById('observaciones_entrada').value
            };
            localStorage.setItem('formData', JSON.stringify(formData));
        } else if (salidaForm) {
            const formData = {
                formType: 'salida',
                centro_costo: document.getElementById('centro_costo_salida').value,
                tipo_salida: document.getElementById('tipo_salida').value,
                monto_entregado: document.getElementById('monto_entregado').value,
                observaciones: document.getElementById('observaciones_salida').value
            };
            localStorage.setItem('formData', JSON.stringify(formData));
        }
    }

    /**
     * Restaura los datos del formulario desde localStorage
     * @param {string} formType - Tipo de formulario ('entrada' o 'salida')
     */
    function restoreFormData(formType) {
        const savedData = localStorage.getItem('formData');
        if (savedData) {
            const formData = JSON.parse(savedData);
            if (formData.formType === formType) {
                if (formType === 'entrada') {
                    // No restaurar valor_recibido si ya tiene un valor
                    const currentValorRecibido = document.getElementById('valor_recibido').value;

                    document.getElementById('centro_costo_entrada').value = formData.centro_costo || '';
                    document.getElementById('tipo_entrada').value = formData.tipo_entrada || '';
                    document.getElementById('fecha_documento').value = formData.fecha_documento || '';
                    document.getElementById('cantidad').value = formData.cantidad || '';
                    document.getElementById('observaciones_entrada').value = formData.observaciones || '';

                    // Solo restaurar valor_recibido si no tiene valor actual
                    if (!currentValorRecibido) {
                        document.getElementById('valor_recibido').value = formData.valor_recibido || '';
                    }

                    // Mostrar/ocultar campo de fecha documento según el tipo de entrada
                    const fechaDocContainer = document.getElementById('fecha_documento_container');
                    fechaDocContainer.style.display = (formData.tipo_entrada === 'Dinero de IPV') ? 'block' : 'none';
                } else if (formType === 'salida') {
                    // No restaurar monto_entregado si ya tiene un valor
                    const currentMontoEntregado = document.getElementById('monto_entregado').value;

                    document.getElementById('centro_costo_salida').value = formData.centro_costo || '';
                    document.getElementById('tipo_salida').value = formData.tipo_salida || '';
                    document.getElementById('observaciones_salida').value = formData.observaciones || '';

                    // Solo restaurar monto_entregado si no tiene valor actual
                    if (!currentMontoEntregado) {
                        document.getElementById('monto_entregado').value = formData.monto_entregado || '';
                    }

                    // Verificar si es transferencia
                    checkTransferencia(document.getElementById('centro_costo_salida'));
                }
            }
            // Limpiar los datos guardados después de restaurarlos
            localStorage.removeItem('formData');
        }
    }

    /**
     * Hace scroll suave al final de la página
     */
    function scrollToBottom() {
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth'
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Recuperar resultado del contador de dinero si existe
        const contadorResult = localStorage.getItem('contadorDineroResult');
        if (contadorResult) {
            const {
                target,
                value
            } = JSON.parse(contadorResult);
            if (target && document.getElementById(target)) {
                document.getElementById(target).value = value;
            }
            localStorage.removeItem('contadorDineroResult');
        }

        // Verificar si hay un formulario activo y restaurar sus datos
        const urlParams = new URLSearchParams(window.location.search);
        const formType = urlParams.get('formType');
        if (formType === 'entrada' || formType === 'salida') {
            if (formType === 'entrada') {
                showEntradaForm();
            } else {
                showSalidaForm();
            }
        }
    });
</script>

<?php include('../../templates/footer.php'); ?>