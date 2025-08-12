<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: caja_trinidad_flujo.php
 * DESCRIPCIÓN: Visualización del flujo de caja de Trinidad
 * 
 * FUNCIONALIDADES:
 * - Visualización de listado completo del flujo de caja de Trinidad
 * - Filtrado por permisos de usuario
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

// Verificar permisos para el centro de costo 688 (Caja Trinidad)
$tiene_permiso = false;
$puede_tramitar = $es_admin;

$sql_permisos = "SELECT permiso FROM permisos 
                WHERE user_id = ? AND centro_costo_codigo = 688 AND permiso IN ('leer', 'escribir', 'tramitar')";
$stmt_permisos = $conn->prepare($sql_permisos);
$stmt_permisos->bind_param("i", $_SESSION['user_id']);
$stmt_permisos->execute();
$result_permisos = $stmt_permisos->get_result();

while ($row = $result_permisos->fetch_assoc()) {
    $tiene_permiso = true;
    if ($row['permiso'] === 'tramitar') {
        $puede_tramitar = true;
    }
}
$stmt_permisos->close();

// Si no tiene permisos, mostrar mensaje y terminar
if (!$tiene_permiso && !$es_admin) {
    echo "<div class='form-container'><p>No tiene permisos para visualizar la caja de Trinidad.</p></div>";
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

    // Verificar permisos para esta operación
    if ($es_admin || $puede_tramitar) {
        // Actualizar estado de tramitado
        $sql_update = "UPDATE caja_trinidad 
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
        // Manejo de error cuando no tiene permisos
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
        header("Location: caja_trinidad_flujo.php");
        exit();
    }
}

// Obtener parámetros de filtrado del GET
$filtro_tramitado = isset($_GET['tramitado']) ? $_GET['tramitado'] : 'todos';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01', strtotime('-1 month'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');

// Validar fechas
if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
    $fecha_inicio = date('Y-m-01', strtotime('-1 month'));
    $fecha_fin = date('Y-m-t');
}

// SECCIÓN 2: OBTENCIÓN DE DATOS FILTRADOS
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // Nuevo filtro para tipo de operación

// Consulta de operaciones de Trinidad con filtros
$entradas = [];
$salidas = [];
$sql_operaciones = "SELECT ct.*, cc.nombre as nombre_centro_costo
                   FROM caja_trinidad ct
                   LEFT JOIN centros_costo cc ON ct.desde_para = cc.codigo
                   WHERE ct.fecha_operacion BETWEEN ? AND ? ";

// Aplicar filtro de tramitado
if ($filtro_tramitado === 'tramitado') {
    $sql_operaciones .= " AND ct.tramitado = 1";
} elseif ($filtro_tramitado === 'sin_tramitar') {
    $sql_operaciones .= " AND ct.tramitado = 0";
}

$sql_operaciones .= " ORDER BY ct.fecha_operacion DESC, ct.numero_operacion DESC";

$stmt_operaciones = $conn->prepare($sql_operaciones);
$stmt_operaciones->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt_operaciones->execute();
$result_operaciones = $stmt_operaciones->get_result();
while ($row = $result_operaciones->fetch_assoc()) {
    if ($row['entrada'] > 0) {
        $entradas[] = $row;
    } else {
        $salidas[] = $row;
    }
}
$stmt_operaciones->close();

ob_end_flush();
?>

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Caja Trinidad - Flujo</h2>

    <!-- Formulario de filtrado -->
    <form id="filtrosForm" method="GET" action="caja_trinidad_flujo.php" class="filtros-form" style="display: <?= (isset($_GET['tramitado']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['tipo'])) ? 'none' : 'block' ?>;">
        <div class="filtros-row">
            <div class="filtro-group">
                <label for="tipo">Tipo de operación:</label>
                <select name="tipo" id="tipo" class="form-control">
                    <option value="todos" <?= $filtro_tipo == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="entradas" <?= $filtro_tipo == 'entradas' ? 'selected' : '' ?>>Entradas</option>
                    <option value="salidas" <?= $filtro_tipo == 'salidas' ? 'selected' : '' ?>>Salidas</option>
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
    <div id="resultadosTablas" style="display: <?= (isset($_GET['tramitado']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['tipo'])) ? 'block' : 'none' ?>;">
        <?php if (($filtro_tipo === 'todos' || $filtro_tipo === 'entradas') && !empty($entradas)): ?>
            <!-- Tabla de entradas -->
            <h3>Entradas de Trinidad</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Operación</th>
                        <th>Fecha Operación</th>
                        <th>Centro Costo</th>
                        <th>Monto</th>
                        <th>Saldo</th>
                        <th>Observaciones</th>
                        <th>Tramitado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entradas as $row):
                        $tramitado_class = $row['tramitado'] ? 'tramitado' : 'no-tramitado';
                    ?>
                        <tr class="<?= $tramitado_class ?>">
                            <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?></td>
                            <td data-label="Fecha Operación"><?= htmlspecialchars($row['fecha_operacion']) ?></td>
                            <td data-label="Centro Costo"><?= htmlspecialchars($row['nombre_centro_costo'] ?? 'N/A') ?></td>
                            <td data-label="Monto"><?= number_format($row['entrada'], 2) ?></td>
                            <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                            <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones'] ?? '') ?></td>
                            <td data-label="Tramitado">
                                <?php if ($es_admin || $puede_tramitar): ?>
                                    <form method="POST" class="tramitado-form" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="numero_operacion" value="<?= $row['numero_operacion'] ?>">
                                        <input type="checkbox" name="tramitado" class="tramitado-checkbox"
                                            <?= $row['tramitado'] ? 'checked' : '' ?>>
                                        <input type="hidden" name="actualizar_tramitado" value="1">
                                    </form>
                                <?php else: ?>
                                    <?= $row['tramitado'] ? '✅' : '❌' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (($filtro_tipo === 'todos' || $filtro_tipo === 'salidas') && !empty($salidas)): ?>
            <!-- Tabla de salidas -->
            <h3>Salidas de Trinidad</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Operación</th>
                        <th>Fecha Operación</th>
                        <th>Centro Costo</th>
                        <th>Monto</th>
                        <th>Saldo</th>
                        <th>Observaciones</th>
                        <th>Tramitado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salidas as $row):
                        $tramitado_class = $row['tramitado'] ? 'tramitado' : 'no-tramitado';
                    ?>
                        <tr class="<?= $tramitado_class ?>">
                            <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?></td>
                            <td data-label="Fecha Operación"><?= htmlspecialchars($row['fecha_operacion']) ?></td>
                            <td data-label="Centro Costo"><?= htmlspecialchars($row['nombre_centro_costo'] ?? 'N/A') ?></td>
                            <td data-label="Monto"><?= number_format($row['salida'], 2) ?></td>
                            <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                            <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones'] ?? '') ?></td>
                            <td data-label="Tramitado">
                                <?php if ($es_admin || $puede_tramitar): ?>
                                    <form method="POST" class="tramitado-form" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="numero_operacion" value="<?= $row['numero_operacion'] ?>">
                                        <input type="checkbox" name="tramitado" class="tramitado-checkbox"
                                            <?= $row['tramitado'] ? 'checked' : '' ?>>
                                        <input type="hidden" name="actualizar_tramitado" value="1">
                                    </form>
                                <?php else: ?>
                                    <?= $row['tramitado'] ? '✅' : '❌' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (empty($entradas) && empty($salidas)): ?>
            <p>No hay operaciones registradas con los filtros actuales</p>
        <?php endif; ?>
    </div>
</div>

<BR>
<BR>
<BR>

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li>
            <button id="mostrarFiltrosBtn" class="nav-button" style=" display: <?= (isset($_GET['tramitado']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin'])) ? 'block' : 'none' ?>;">
                Mostrar Filtros
            </button>
        </li>
    </ul>
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
            document.getElementById('tramitado').value = 'todos';

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
        const hasFilters = urlParams.has('tramitado') || urlParams.has('fecha_inicio') || urlParams.has('fecha_fin');

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

        // Manejar el cambio en las casillas de tramitado con AJAX
        document.querySelectorAll('.tramitado-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const form = this.closest('form');
                const formData = new FormData(form);

                fetch('caja_trinidad_flujo.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Mostrar notificación de éxito/error
                        const notification = document.createElement('div');
                        notification.className = 'floating-notification success show';
                        notification.textContent = 'Estado de tramitado actualizado correctamente';
                        document.querySelector('.form-container').appendChild(notification);

                        // Ocultar la notificación después de 5 segundos
                        setTimeout(() => {
                            notification.classList.remove('show');
                            setTimeout(() => notification.remove(), 300);
                        }, 5000);

                        // Actualizar la clase de la fila para reflejar el nuevo estado
                        const row = this.closest('tr');
                        if (this.checked) {
                            row.classList.add('tramitado');
                            row.classList.remove('no-tramitado');
                        } else {
                            row.classList.add('no-tramitado');
                            row.classList.remove('tramitado');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Mostrar notificación de error
                        const notification = document.createElement('div');
                        notification.className = 'floating-notification error show';
                        notification.textContent = 'Error al actualizar el estado de tramitado';
                        document.querySelector('.form-container').appendChild(notification);

                        // Ocultar la notificación después de 5 segundos
                        setTimeout(() => {
                            notification.classList.remove('show');
                            setTimeout(() => notification.remove(), 300);
                        }, 5000);

                        // Revertir el cambio en el checkbox si falla
                        this.checked = !this.checked;
                    });
            });
        });
    });
</script>

<?php include('../../templates/footer.php'); ?>