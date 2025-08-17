<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: caja_principal_flujo.php
 * DESCRIPCIÓN: Visualización del flujo de caja principal del sistema
 * 
 * FUNCIONALIDADES:
 * - Visualización de listado completo del flujo de caja
 * - Filtrado por permisos de usuario
 * - Separación de entradas y salidas
 * - Control de tramitado para usuarios con permisos
 * - Sistema de filtrado avanzado
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Verificar si el usuario es administrador
if ($_SESSION['role'] === 'Administrador') {
    $es_admin = 1;
} else {
    $es_admin = 0;
}

// Obtener todos los centros de costo a los que el usuario tiene permiso (leer, escribir o tramitar)
$centros_permitidos = [];
$permisos_tramitar = []; // Centros donde el usuario puede tramitar

$sql_permisos = "SELECT centro_costo_codigo, permiso FROM permisos 
                WHERE user_id = ? AND permiso IN ('leer', 'escribir', 'tramitar')";
$stmt_permisos = $conn->prepare($sql_permisos);
$stmt_permisos->bind_param("i", $_SESSION['user_id']);
$stmt_permisos->execute();
$result_permisos = $stmt_permisos->get_result();
while ($row = $result_permisos->fetch_assoc()) {
    $centros_permitidos[] = $row['centro_costo_codigo'];
    if ($row['permiso'] === 'tramitar') {
        $permisos_tramitar[] = $row['centro_costo_codigo'];
    }
}
$stmt_permisos->close();

// Si no tiene permisos en ningún centro de costo, mostrar mensaje y terminar
if (empty($centros_permitidos) && !$es_admin) {
    echo "<div class='form-container'><p>No tiene permisos para visualizar ningún centro de costo.</p></div>";
    include('../../templates/footer.php');
    exit();
}

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar actualización de tramitado si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_tramitado'])) {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $numero_operacion = intval($_POST['numero_operacion']);
    $tramitado = isset($_POST['tramitado']) ? 1 : 0;
    $tipo_operacion = $_POST['tipo_operacion']; // 'entrada' o 'salida'

    // Verificar permisos para esta operación
    $permiso_valido = false;

    if ($es_admin) {
        $permiso_valido = true;
    } else {
        // Obtener centro de costo de la operación
        $sql_centro = "SELECT centro_costo_codigo FROM caja_principal_" . $tipo_operacion . "s 
                      WHERE numero_operacion = ?";
        $stmt_centro = $conn->prepare($sql_centro);
        $stmt_centro->bind_param("i", $numero_operacion);
        $stmt_centro->execute();
        $result_centro = $stmt_centro->get_result();

        if ($row = $result_centro->fetch_assoc()) {
            $centro_costo = $row['centro_costo_codigo'];
            $permiso_valido = in_array($centro_costo, $permisos_tramitar);
        }
        $stmt_centro->close();
    }

    if ($permiso_valido) {
        // Actualizar estado de tramitado
        $sql_update = "UPDATE caja_principal_" . $tipo_operacion . "s 
                      SET tramitado = ? 
                      WHERE numero_operacion = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $tramitado, $numero_operacion);

        if ($stmt_update->execute()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                // Es una solicitud AJAX, devolver respuesta JSON
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Estado de tramitado actualizado correctamente']);
                exit();
            } else {
                $_SESSION['success_msg'] = "Estado de tramitado actualizado correctamente.";
            }
        } else {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado de tramitado']);
                exit();
            } else {
                $_SESSION['error_msg'] = "Error al actualizar el estado de tramitado.";
            }
        }
        $stmt_update->close();
    } else {
        // Manejo de error cuando no tiene permisos (también deberías adaptarlo para AJAX)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para tramitar esta operación']);
            exit();
        } else {
            $_SESSION['error_msg'] = "No tiene permisos para tramitar esta operación.";
        }
    }

    // Regenerar token CSRF solo para solicitudes normales (no AJAX)
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        unset($_SESSION['csrf_token']);
        header("Location: caja_principal_flujo.php");
        exit();
    }
}

// Obtener nombres de los centros de costo permitidos
$centros_costo_info = [];
if (!empty($centros_permitidos)) {
    $sql_centros = "SELECT codigo, nombre FROM centros_costo WHERE codigo IN (" . implode(',', $centros_permitidos) . ")";
    $result_centros = mysqli_query($conn, $sql_centros);
    while ($row = mysqli_fetch_assoc($result_centros)) {
        $centros_costo_info[$row['codigo']] = $row['nombre'];
    }
}

// Obtener parámetros de filtrado del GET
$filtro_centro = isset($_GET['centro_costo']) ? $_GET['centro_costo'] : 'todos';
$filtro_tramitado = isset($_GET['tramitado']) ? $_GET['tramitado'] : 'todos';
$filtro_tipo = isset($_GET['tipo_operacion']) ? $_GET['tipo_operacion'] : 'todos';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01', strtotime('-1 month'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');

// Validar fechas
if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
    $fecha_inicio = date('Y-m-01', strtotime('-1 month'));
    $fecha_fin = date('Y-m-t');
}

// SECCIÓN 2: OBTENCIÓN DE DATOS FILTRADOS

// Consulta de entradas con filtro por centros de costo permitidos y otros filtros
$entradas = [];
$sql_entradas = "SELECT cp.*, cpe.*, cc.nombre as nombre_centro_costo
                FROM caja_principal cp
                JOIN caja_principal_entradas cpe ON cp.numero_operacion = cpe.numero_operacion
                JOIN centros_costo cc ON cpe.centro_costo_codigo = cc.codigo
                WHERE cp.entrada > 0 
                AND cp.fecha_operacion BETWEEN ? AND ? ";

if (!$es_admin) {
    $sql_entradas .= " AND cpe.centro_costo_codigo IN (" . implode(',', $centros_permitidos) . ")";
}

// Aplicar filtro de centro de costo
if ($filtro_centro !== 'todos' && in_array($filtro_centro, $centros_permitidos)) {
    $sql_entradas .= " AND cpe.centro_costo_codigo = " . intval($filtro_centro);
}

// Aplicar filtro de tramitado
if ($filtro_tramitado === 'tramitado') {
    $sql_entradas .= " AND cpe.tramitado = 1";
} elseif ($filtro_tramitado === 'sin_tramitar') {
    $sql_entradas .= " AND cpe.tramitado = 0";
}

$sql_entradas .= " ORDER BY cp.fecha_operacion DESC, cp.numero_operacion DESC";

$stmt_entradas = $conn->prepare($sql_entradas);
$stmt_entradas->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt_entradas->execute();
$result_entradas = $stmt_entradas->get_result();
while ($row = $result_entradas->fetch_assoc()) {
    $entradas[] = $row;
}
$stmt_entradas->close();

// Consulta de salidas con filtro por centros de costo permitidos y otros filtros
$salidas = [];
$sql_salidas = "SELECT cp.*, cps.*, cc.nombre as nombre_centro_costo
                FROM caja_principal cp
                JOIN caja_principal_salidas cps ON cp.numero_operacion = cps.numero_operacion
                JOIN centros_costo cc ON cps.centro_costo_codigo = cc.codigo
                WHERE cp.salida > 0 
                AND cp.fecha_operacion BETWEEN ? AND ? ";

if (!$es_admin) {
    $sql_salidas .= " AND cps.centro_costo_codigo IN (" . implode(',', $centros_permitidos) . ")";
}

// Aplicar filtro de centro de costo
if ($filtro_centro !== 'todos' && in_array($filtro_centro, $centros_permitidos)) {
    $sql_salidas .= " AND cps.centro_costo_codigo = " . intval($filtro_centro);
}

// Aplicar filtro de tramitado
if ($filtro_tramitado === 'tramitado') {
    $sql_salidas .= " AND cps.tramitado = 1";
} elseif ($filtro_tramitado === 'sin_tramitar') {
    $sql_salidas .= " AND cps.tramitado = 0";
}

$sql_salidas .= " ORDER BY cp.fecha_operacion DESC, cp.numero_operacion DESC";

$stmt_salidas = $conn->prepare($sql_salidas);
$stmt_salidas->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt_salidas->execute();
$result_salidas = $stmt_salidas->get_result();
while ($row = $result_salidas->fetch_assoc()) {
    $salidas[] = $row;
}
$stmt_salidas->close();

ob_end_flush();
?>

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Caja Principal - Flujo</h2>

    <!-- Formulario de filtrado -->
    <form id="filtrosForm" method="GET" action="caja_principal_flujo.php" class="filtros-form" style="display: <?= (isset($_GET['centro_costo']) || isset($_GET['tramitado']) || isset($_GET['tipo_operacion']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin'])) ? 'none' : 'block' ?>;">
        <div class="filtros-row">
            <div class="filtro-group">
                <label for="centro_costo">Centro de Costo:</label>
                <select name="centro_costo" id="centro_costo" class="form-control">
                    <option value="todos">Todos</option>
                    <?php foreach ($centros_costo_info as $codigo => $nombre): ?>
                        <option value="<?= $codigo ?>" <?= $filtro_centro == $codigo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-group">
                <label for="tramitado">Tramitado:</label>
                <select name="tramitado" id="tramitado" class="form-control">
                    <option value="todos" <?= $filtro_tramitado == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="tramitado" <?= $filtro_tramitado == 'tramitado' ? 'selected' : '' ?>>Tramitado</option>
                    <option value="sin_tramitar" <?= $filtro_tramitado == 'sin_tramitar' ? 'selected' : '' ?>>Sin tramitar</option>
                </select>
            </div>

            <div class="filtro-group">
                <label for="tipo_operacion">Tipo Operación:</label>
                <select name="tipo_operacion" id="tipo_operacion" class="form-control">
                    <option value="todos" <?= $filtro_tipo == 'todos' ? 'selected' : '' ?>>Todas</option>
                    <option value="entrada" <?= $filtro_tipo == 'entrada' ? 'selected' : '' ?>>Entradas</option>
                    <option value="salida" <?= $filtro_tipo == 'salida' ? 'selected' : '' ?>>Salidas</option>
                </select>
            </div>
        </div>

        <div class="filtros-row">
            <div class="filtro-group">
                <label for="fecha_inicio">Fecha Inicio:</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio"
                    value="<?= htmlspecialchars($fecha_inicio) ?>" class="form-control">
            </div>

            <div class="filtro-group">
                <label for="fecha_fin">Fecha Fin:</label>
                <input type="date" name="fecha_fin" id="fecha_fin"
                    value="<?= htmlspecialchars($fecha_fin) ?>" class="form-control">
            </div>
            <br>
            <div class="filtro-group">
                <button type="submit">Filtrar</button>
                <button type="button" id="resetFiltros" class="btn btn-secondary">Restablecer</button>
            </div>
        </div>
    </form>

    <!-- Notificaciones flotantes -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div id="floatingNotification" class="floating-notification success show">
            <?= $_SESSION['success_msg'] ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div id="floatingNotification" class="floating-notification error show">
            <?= $_SESSION['error_msg'] ?>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- Contenedor para las tablas que se mostrarán/ocultarán -->
    <div id="resultadosTablas" style="display: <?= (isset($_GET['centro_costo']) || isset($_GET['tramitado']) || isset($_GET['tipo_operacion']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin'])) ? 'block' : 'none' ?>;">
        <!-- Mostrar tablas según el filtro de tipo de operación -->
        <?php if ($filtro_tipo === 'todos' || $filtro_tipo === 'entrada'): ?>
            <!-- Sección de Entradas -->
            <h3>Entradas</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Operación</th>
                        <th>Fecha Operación</th>
                        <th>Tipo Entrada</th>
                        <th>Centro Costo</th>
                        <th>Monto</th>
                        <th>Fecha Documento</th>
                        <th>Cantidad</th>
                        <th>Saldo</th>
                        <th>Observaciones</th>
                        <th>Tramitado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entradas as $row):
                        $puede_tramitar = $es_admin || in_array($row['centro_costo_codigo'], $permisos_tramitar);
                        $tramitado_class = $row['tramitado'] ? 'tramitado' : 'no-tramitado';
                    ?>
                        <tr class="<?= $tramitado_class ?>">
                            <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?></td>
                            <td data-label="Fecha Operación"><?= htmlspecialchars($row['fecha_operacion']) ?></td>
                            <td data-label="Tipo Entrada"><?= htmlspecialchars($row['tipo_entrada']) ?></td>
                            <td data-label="Centro Costo"><?= htmlspecialchars($row['nombre_centro_costo']) ?></td>
                            <td data-label="Monto"><?= number_format($row['entrada'], 2) ?></td>
                            <td data-label="Fecha Documento"><?= htmlspecialchars($row['fecha_documento'] ?? 'N/A') ?></td>
                            <td data-label="Cantidad"><?= number_format($row['cantidad'] ?? 0, 2) ?></td>
                            <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                            <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones'] ?? '') ?></td>
                            <td data-label="Tramitado">
                                <?php if ($puede_tramitar && !$row['tramitado']): ?>
                                    <button class="btn-tramitar" data-numero="<?= $row['numero_operacion'] ?>" data-tipo="entrada">
                                        Tramitar
                                    </button>
                                <?php else: ?>
                                    <?= $row['tramitado'] ? '✅' : '❌' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($entradas)): ?>
                        <tr>
                            <td colspan="10">No hay entradas registradas con los filtros actuales</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($filtro_tipo === 'todos' || $filtro_tipo === 'salida'): ?>
            <!-- Sección de Salidas -->
            <h3>Salidas</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Operación</th>
                        <th>Fecha Operación</th>
                        <th>Tipo Salida</th>
                        <th>Centro Costo</th>
                        <th>Monto</th>
                        <th>Saldo</th>
                        <th>Observaciones</th>
                        <th>Tramitado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salidas as $row):
                        $puede_tramitar = $es_admin || in_array($row['centro_costo_codigo'], $permisos_tramitar);
                        $tramitado_class = $row['tramitado'] ? 'tramitado' : 'no-tramitado';
                    ?>
                        <tr class="<?= $tramitado_class ?>">
                            <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?></td>
                            <td data-label="Fecha Operación"><?= htmlspecialchars($row['fecha_operacion']) ?></td>
                            <td data-label="Tipo Salida"><?= htmlspecialchars($row['tipo_salida'] ?? 'N/A') ?></td>
                            <td data-label="Centro Costo"><?= htmlspecialchars($row['nombre_centro_costo']) ?></td>
                            <td data-label="Monto"><?= number_format($row['salida'], 2) ?></td>
                            <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                            <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones'] ?? '') ?></td>
                            <td data-label="Tramitado">
                                <?php if ($puede_tramitar && !$row['tramitado']): ?>
                                    <button class="btn-tramitar" data-numero="<?= $row['numero_operacion'] ?>" data-tipo="salida">
                                        Tramitar
                                    </button>
                                <?php else: ?>
                                    <?= $row['tramitado'] ? '✅' : '❌' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($salidas)): ?>
                        <tr>
                            <td colspan="8">No hay salidas registradas con los filtros actuales</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<BR>
<BR>
<BR>

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li>
            <button id="mostrarFiltrosBtn" class="nav-button" style=" display: <?= (isset($_GET['centro_costo']) || isset($_GET['tramitado']) || isset($_GET['tipo_operacion']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin'])) ? 'block' : 'none' ?>;">
                Mostrar Filtros
            </button>
        </li>
    </ul>
</div>

<!-- Modal para confirmar tramitación -->
<div id="modalTramitar" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>¿Desea tramitar esta operación?</h3>
        <input type="hidden" id="modalNumeroOperacion">
        <input type="hidden" id="modalTipoOperacion">
        <div class="modal-buttons">
            <button id="btnTramitarSi" class="btn-confirm">Sí</button>
            <button id="btnTramitarNo" class="btn-cancel">No</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Script para ocultar automáticamente las notificaciones después de 5 segundos
        const notifications = document.querySelectorAll('.floating-notification');
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        });

        // Restablecer filtros
        document.getElementById('resetFiltros').addEventListener('click', function() {
            // Establecer valores predeterminados
            document.getElementById('centro_costo').value = 'todos';
            document.getElementById('tramitado').value = 'todos';
            document.getElementById('tipo_operacion').value = 'todos';

            // Establecer fechas predeterminadas (mes actual + último día del mes anterior)
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

            document.getElementById('fecha_inicio').value = formatDate(firstDay);
            document.getElementById('fecha_fin').value = formatDate(lastDay);

            // Enviar el formulario
            document.getElementById('filtrosForm').submit();
        });

        // Función para formatear fecha como YYYY-MM-DD
        function formatDate(date) {
            const d = new Date(date);
            let month = '' + (d.getMonth() + 1);
            let day = '' + d.getDate();
            const year = d.getFullYear();

            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;

            return [year, month, day].join('-');
        }

        // Mostrar/ocultar formulario y tablas
        const filtrosForm = document.getElementById('filtrosForm');
        const resultadosTablas = document.getElementById('resultadosTablas');
        const mostrarFiltrosBtn = document.getElementById('mostrarFiltrosBtn');

        // Verificar si hay parámetros de filtro en la URL
        const urlParams = new URLSearchParams(window.location.search);
        const hasFilters = urlParams.has('centro_costo') || urlParams.has('tramitado') ||
            urlParams.has('tipo_operacion') || urlParams.has('fecha_inicio') ||
            urlParams.has('fecha_fin');

        // Configurar visibilidad inicial
        if (hasFilters) {
            filtrosForm.style.display = 'none';
            resultadosTablas.style.display = 'block';
            mostrarFiltrosBtn.style.display = 'block';
        } else {
            filtrosForm.style.display = 'block';
            resultadosTablas.style.display = 'none';
            mostrarFiltrosBtn.style.display = 'none';
        }

        // Manejar clic en el botón "Mostrar Filtros"
        mostrarFiltrosBtn.addEventListener('click', function() {
            filtrosForm.style.display = 'block';
            resultadosTablas.style.display = 'none';
            mostrarFiltrosBtn.style.display = 'none';

            // Hacer scroll suave hacia el formulario
            filtrosForm.scrollIntoView({
                behavior: 'smooth'
            });
        });

        // Modal para tramitar operaciones
        const modal = document.getElementById('modalTramitar');
        const btnTramitarSi = document.getElementById('btnTramitarSi');
        const btnTramitarNo = document.getElementById('btnTramitarNo');
        const closeModal = document.querySelector('.close-modal');
        const csrfToken = "<?= $_SESSION['csrf_token'] ?>";

        // Mostrar modal al hacer clic en botón Tramitar
        document.querySelectorAll('.btn-tramitar').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('modalNumeroOperacion').value = this.dataset.numero;
                document.getElementById('modalTipoOperacion').value = this.dataset.tipo;
                modal.style.display = 'block';
            });
        });

        // Cerrar modal
        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Tramitar operación (Sí)
        btnTramitarSi.addEventListener('click', function() {
            const numero = document.getElementById('modalNumeroOperacion').value;
            const tipo = document.getElementById('modalTipoOperacion').value;
            actualizarTramitado(numero, tipo, 1);
            modal.style.display = 'none';
        });

        // No tramitar operación (No)
        btnTramitarNo.addEventListener('click', function() {
            const numero = document.getElementById('modalNumeroOperacion').value;
            const tipo = document.getElementById('modalTipoOperacion').value;
            actualizarTramitado(numero, tipo, 0);
            modal.style.display = 'none';
        });

        // Función para actualizar estado de tramitado
        function actualizarTramitado(numero, tipo, estado) {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('numero_operacion', numero);
            formData.append('tipo_operacion', tipo);
            formData.append('tramitado', estado);
            formData.append('actualizar_tramitado', 1);

            fetch('caja_principal_flujo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mostrar notificación
                        const notification = document.createElement('div');
                        notification.className = 'floating-notification success show';
                        notification.textContent = data.message;
                        document.querySelector('.form-container').appendChild(notification);

                        // Actualizar la UI
                        const btn = document.querySelector(`.btn-tramitar[data-numero="${numero}"]`);
                        if (btn) {
                            if (estado) {
                                btn.parentElement.innerHTML = '✅';
                            } else {
                                btn.parentElement.innerHTML = '❌';
                            }
                        }

                        // Ocultar notificación después de 5 segundos
                        setTimeout(() => {
                            notification.classList.remove('show');
                            setTimeout(() => notification.remove(), 300);
                        }, 5000);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const notification = document.createElement('div');
                    notification.className = 'floating-notification error show';
                    notification.textContent = error.message || 'Error al actualizar el estado de tramitado';
                    document.querySelector('.form-container').appendChild(notification);

                    setTimeout(() => {
                        notification.classList.remove('show');
                        setTimeout(() => notification.remove(), 300);
                    }, 5000);
                });
        }
    });

    // Cerrar modal al hacer clic fuera del contenido
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('modalTramitar');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
</script>

<style>
    /* Estilos para el botón Tramitar */
    .btn-tramitar {
        background-color: #4CAF50;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-tramitar:hover {
        background-color: #45a049;
    }

    /* Estilos para el modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 300px;
        border-radius: 5px;
        text-align: center;
    }

    .close-modal {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close-modal:hover {
        color: black;
    }

    .modal-buttons {
        margin-top: 20px;
    }

    .btn-confirm {
        background-color: #4CAF50;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-right: 10px;
    }

    .btn-cancel {
        background-color: #f44336;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    /* Estilos para filas tramitadas/no tramitadas */
    .tramitado {
        background-color: #f0fff0;
        /* Verde muy claro para tramitados */
    }

    .no-tramitado {
        background-color: #fff0f0;
        /* Rojo muy claro para no tramitados */
    }
</style>

<?php include('../../templates/footer.php'); ?>