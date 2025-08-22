<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: caja_panaderia_movimientos.php
 * DESCRIPCIÓN: Gestión de operaciones de caja panadería del sistema
 * 
 * FUNCIONALIDADES:
 * - Creación de operaciones de entrada en caja panadería
 * - Creación de operaciones de salida en caja panadería
 * - Visualización de listado completo
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD


// Verificar permisos de escritura para Caja Panadería (centro de costo 728)
$tiene_permiso_editar = false;
$sql_permiso = "SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = 728";
$stmt_permiso = $conn->prepare($sql_permiso);
$stmt_permiso->bind_param("i", $_SESSION['user_id']);
$stmt_permiso->execute();
$result_permiso = $stmt_permiso->get_result();
if ($result_permiso && $result_permiso->num_rows > 0) {
    $row = $result_permiso->fetch_assoc();
    $tiene_permiso_editar = ($row['permiso'] == 'escribir');
}
$stmt_permiso->close();

if (!$tiene_permiso_editar) {
    $_SESSION['error_msg'] = "⚠️ No tienes permisos para acceder a esta sección";
    header("Location: caja_panaderia_dashboard.php");
    exit();
}

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
    $desde_para = intval($_POST['desde_para']);
    $entrada = floatval($_POST['entrada']);
    $observaciones = $_POST['observaciones'];

    // Validar que el monto sea positivo
    if ($entrada <= 0) {
        $_SESSION['error_msg'] = "⚠️ El monto debe ser mayor que cero";
        header("Location: caja_panaderia_movimientos.php");
        exit();
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener último saldo
        $saldo_anterior = 0;
        $sql_saldo = "SELECT saldo FROM caja_panaderia ORDER BY numero_operacion DESC LIMIT 1";
        $result_saldo = $conn->query($sql_saldo);
        if ($result_saldo && $result_saldo->num_rows > 0) {
            $row = $result_saldo->fetch_assoc();
            $saldo_anterior = $row['saldo'];
        }
        $nuevo_saldo = $saldo_anterior + $entrada;

        // Insertar en caja_panaderia
        $sql = "INSERT INTO caja_panaderia 
                (fecha_operacion, entrada, salida, saldo, desde_para, observaciones, tramitado) 
                VALUES (CURDATE(), ?, 0, ?, ?, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddis", $entrada, $nuevo_saldo, $desde_para, $observaciones);
        $stmt->execute();
        $stmt->close();

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
    header("Location: caja_panaderia_movimientos.php");
    exit();
}

// 2.2 Procesamiento de formulario de salida
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_salida'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos del formulario
    $desde_para = intval($_POST['desde_para']);
    $salida = floatval($_POST['salida']);
    $observaciones = $_POST['observaciones'];

    // Validar que el monto sea positivo
    if ($salida <= 0) {
        $_SESSION['error_msg'] = "⚠️ El monto debe ser mayor que cero";
        header("Location: caja_panaderia_movimientos.php");
        exit();
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener último saldo
        $saldo_anterior = 0;
        $sql_saldo = "SELECT saldo FROM caja_panaderia ORDER BY numero_operacion DESC LIMIT 1";
        $result_saldo = $conn->query($sql_saldo);
        if ($result_saldo && $result_saldo->num_rows > 0) {
            $row = $result_saldo->fetch_assoc();
            $saldo_anterior = $row['saldo'];

            // Validar saldo suficiente
            if ($saldo_anterior < $salida) {
                throw new Exception("Saldo insuficiente en caja panadería");
            }
        }
        $nuevo_saldo = $saldo_anterior - $salida;

        // Insertar en caja_panaderia
        $sql = "INSERT INTO caja_panaderia 
                (fecha_operacion, entrada, salida, saldo, desde_para, observaciones, tramitado) 
                VALUES (CURDATE(), 0, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddis", $salida, $nuevo_saldo, $desde_para, $observaciones);
        $stmt->execute();
        $stmt->close();

        // Confirmar transacción
        $conn->commit();
        $_SESSION['success_msg'] = "✅ Salida registrada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ Error al registrar salida: " . $e->getMessage();
        error_log("Error en caja_panaderia_movimientos.php: " . $e->getMessage());
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: caja_panaderia_movimientos.php");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Obtener centros de costo para entradas (E_Caja_Panad = 1)
$centros_entrada = [];
$sql_centros_entrada = "SELECT codigo, nombre FROM centros_costo WHERE E_Caja_Panad = 1";
$result_centros_entrada = mysqli_query($conn, $sql_centros_entrada);
while ($row = mysqli_fetch_assoc($result_centros_entrada)) {
    $centros_entrada[] = $row;
}

// Obtener centros de costo para salidas (S_Caja_Panad = 1)
$centros_salida = [];
$sql_centros_salida = "SELECT codigo, nombre FROM centros_costo WHERE S_Caja_Panad = 1";
$result_centros_salida = mysqli_query($conn, $sql_centros_salida);
while ($row = mysqli_fetch_assoc($result_centros_salida)) {
    $centros_salida[] = $row;
}

// Configuración de paginación
$por_pagina = 1; // Mostrar 15 registros por página
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM caja_panaderia"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada con join para obtener nombre del centro de costo
$operaciones = [];
$sql = "SELECT cp.*, cc.nombre as centro_nombre 
        FROM caja_panaderia cp
        LEFT JOIN centros_costo cc ON cp.desde_para = cc.codigo
        ORDER BY fecha_operacion DESC, numero_operacion DESC 
        LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $operaciones[] = $row;
}
ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Caja Panadería</h2>

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
                    <td data-label="Centro de Costo"><?= htmlspecialchars($row['centro_nombre']) ?></td>
                    <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones']) ?></td>
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
        <form method="POST" action="caja_panaderia_movimientos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="save_entrada" value="1">

            <label for="desde_para_entrada">Desde:</label>
            <select id="desde_para_entrada" name="desde_para" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($centros_entrada as $centro): ?>
                    <option value="<?= $centro['codigo'] ?>"><?= htmlspecialchars($centro['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="input-button-group">
                <div class="input-field">
                    <label for="entrada">Valor recibido:</label>
                    <input type="number" id="entrada" name="entrada" step="0.01" min="0" required>
                </div>
                <button type="button" onclick="showContadorDinero('entrada')" class="btn-secondary btn-counter">Contador de dinero</button>
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
        <form method="POST" action="caja_panaderia_movimientos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="save_salida" value="1">

            <label for="desde_para_salida">Para:</label>
            <select id="desde_para_salida" name="desde_para" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($centros_salida as $centro): ?>
                    <option value="<?= $centro['codigo'] ?>"><?= htmlspecialchars($centro['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="input-button-group">
                <div class="input-field">
                    <label for="salida">Monto entregado:</label>
                    <input type="number" id="salida" name="salida" step="0.01" min="0" required>
                </div>
                <button type="button" onclick="showContadorDinero('salida')" class="btn-secondary btn-counter">Contador de dinero</button>
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

        window.location.href = `contador_dinero.php?target=${targetField}&source=caja_panaderia_movimientos.php&formType=${formType}`;
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
     * Guarda los datos del formulario activo en localStorage
     */
    function saveFormData() {
        const entradaForm = document.getElementById('entradaFormContainer').style.display === 'block';
        const salidaForm = document.getElementById('salidaFormContainer').style.display === 'block';

        if (entradaForm) {
            const formData = {
                formType: 'entrada',
                desde_para: document.getElementById('desde_para_entrada').value,
                entrada: document.getElementById('entrada').value,
                observaciones: document.getElementById('observaciones_entrada').value
            };
            localStorage.setItem('formData', JSON.stringify(formData));
        } else if (salidaForm) {
            const formData = {
                formType: 'salida',
                desde_para: document.getElementById('desde_para_salida').value,
                salida: document.getElementById('salida').value,
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
                    // No restaurar entrada si ya tiene un valor
                    const currentEntrada = document.getElementById('entrada').value;

                    document.getElementById('desde_para_entrada').value = formData.desde_para || '';
                    document.getElementById('observaciones_entrada').value = formData.observaciones || '';

                    // Solo restaurar entrada si no tiene valor actual
                    if (!currentEntrada) {
                        document.getElementById('entrada').value = formData.entrada || '';
                    }
                } else if (formType === 'salida') {
                    // No restaurar salida si ya tiene un valor
                    const currentSalida = document.getElementById('salida').value;

                    document.getElementById('desde_para_salida').value = formData.desde_para || '';
                    document.getElementById('observaciones_salida').value = formData.observaciones || '';

                    // Solo restaurar salida si no tiene valor actual
                    if (!currentSalida) {
                        document.getElementById('salida').value = formData.salida || '';
                    }
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