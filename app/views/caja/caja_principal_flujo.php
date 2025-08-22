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
            $_SESSION['success_msg'] = "Estado de tramitado actualizado correctamente.";
        } else {
            $_SESSION['error_msg'] = "Error al actualizar el estado de tramitado.";
        }
        $stmt_update->close();
    } else {
        $_SESSION['error_msg'] = "No tiene permisos para tramitar esta operación.";
    }

    // Regenerar token CSRF
    unset($_SESSION['csrf_token']);
    header("Location: caja_trinidad_flujo.php?" . http_build_query($_GET));
    exit();
}

// Obtener parámetros de filtrado del GET
$filtro_tramitado = isset($_GET['tramitado']) ? $_GET['tramitado'] : 'todos';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01', strtotime('-1 month'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // Nuevo filtro para tipo de operación

// Validar fechas
if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
    $fecha_inicio = date('Y-m-01', strtotime('-1 month'));
    $fecha_fin = date('Y-m-t');
}

// SECCIÓN 2: OBTENCIÓN DE DATOS FILTRADOS

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

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja Trinidad - Flujo</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1300px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }

        .form-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        h3 {
            color: #2980b9;
            margin-top: 25px;
        }

        .filtros-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }

        .filtros-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .filtro-group {
            flex: 1;
            min-width: 200px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 25px;
        }

        button:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        .tramitado {
            background-color: #d4edda;
        }

        .no-tramitado {
            background-color: #f8d7da;
        }

        .floating-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            color: white;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s, transform 0.3s;
        }

        .floating-notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .floating-notification.success {
            background-color: #28a745;
        }

        .floating-notification.error {
            background-color: #dc3545;
        }

        .secondary-nav-menu {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 10px;
        }

        .nav-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .nav-button:hover {
            background-color: #2980b9;
        }

        @media (max-width: 768px) {
            .filtro-group {
                flex: 100%;
            }

            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
            }

            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
            }

            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
            }

            td:before {
                position: absolute;
                top: 12px;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                content: attr(data-label);
            }
        }
    </style>
</head>

<body>
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
                                                <?= $row['tramitado'] ? 'checked' : '' ?> onchange="this.form.submit()">
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
                                                <?= $row['tramitado'] ? 'checked' : '' ?> onchange="this.form.submit()">
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
                <button id="mostrarFiltrosBtn" class="nav-button" style=" display: <?= (isset($_GET['tramitado']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['tipo'])) ? 'block' : 'none' ?>;">
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
                document.getElementById('tipo').value = 'todos';

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
            const hasFilters = urlParams.has('tramitado') || urlParams.has('fecha_inicio') ||
                urlParams.has('fecha_fin') || urlParams.has('tipo');

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
        });
    </script>

    <?php include('../../templates/footer.php'); ?>