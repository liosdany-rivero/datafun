<?php
include('../templates/header.php');
require_once('../controllers/auth_admin_check.php'); // Solo para admin
require_once('../controllers/config.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mensajes desde sesión
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Procesar actualización de campo tramitado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_tramitado'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'error' => 'Token CSRF inválido']));
    }

    $numeroOperacion = intval($_POST['numero_operacion']);
    $tramitado = isset($_POST['tramitado']) ? 1 : 0;
    $tipo = $_POST['tipo'];

    try {
        if ($tipo === 'entrada') {
            $sql = "UPDATE entradas_caja_principal SET tramitado = ? WHERE numero_operacion = ?";
        } else {
            $sql = "UPDATE salidas_caja_principal SET tramitado = ? WHERE numero_operacion = ?";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $tramitado, $numeroOperacion);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'tramitado' => $tramitado]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
        exit();
    }
}

// Obtener todos los establecimientos (no solo los permitidos)
$establecimientos = [];
$query = "SELECT codigo, nombre FROM establecimientos ORDER BY nombre";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $establecimientos[$row['codigo']] = $row['nombre'];
}

// Procesar filtro
$codigoFiltro = $_GET['establecimiento'] ?? 'all';
$mostrarTodos = ($codigoFiltro === 'all');

// Construir condiciones WHERE para mostrar solo no tramitados
$whereEntradas = "e.tramitado = 0";
$whereSalidas = "s.tramitado = 0";

if (!$mostrarTodos) {
    $whereEntradas .= " AND e.establecimiento_codigo = ?";
    $whereSalidas .= " AND s.establecimiento_codigo = ?";
    $params = [$codigoFiltro];
    $paramTypes = "i";
}

// Obtener entradas pendientes
$entradas = [];
$queryEntradas = "SELECT c.numero_operacion, c.fecha_operacion, c.entrada, 
                 e.tipo_entrada, e.fecha_documento, e.cantidad, e.observaciones, e.tramitado,
                 est.nombre as establecimiento
                 FROM caja_principal c
                 JOIN entradas_caja_principal e ON c.numero_operacion = e.numero_operacion
                 JOIN establecimientos est ON e.establecimiento_codigo = est.codigo
                 WHERE $whereEntradas
                 ORDER BY c.fecha_operacion DESC, c.numero_operacion DESC";
$stmt = $conn->prepare($queryEntradas);
if (!$mostrarTodos) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $entradas[] = $row;
}
$stmt->close();

// Obtener salidas pendientes
$salidas = [];
$querySalidas = "SELECT c.numero_operacion, c.fecha_operacion, c.salida, 
                s.tipo_salida, s.observaciones, s.tramitado,
                est.nombre as establecimiento
                FROM caja_principal c
                JOIN salidas_caja_principal s ON c.numero_operacion = s.numero_operacion
                JOIN establecimientos est ON s.establecimiento_codigo = est.codigo
                WHERE $whereSalidas
                ORDER BY c.fecha_operacion DESC, c.numero_operacion DESC";
$stmt = $conn->prepare($querySalidas);
if (!$mostrarTodos) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $salidas[] = $row;
}
$stmt->close();
?>

<div class="form-container">
    <h2>Registros Pendientes de Tramitación</h2>

    <form method="GET" id="filtroForm">
        <div class="input-group">
            <label for="establecimiento">Establecimiento:</label>
            <select name="establecimiento" id="establecimiento" required>
                <option value="all" <?= ($codigoFiltro === 'all') ? 'selected' : '' ?>>Todos los establecimientos</option>
                <?php foreach ($establecimientos as $codigo => $nombre): ?>
                    <option value="<?= $codigo ?>" <?= ($codigo == $codigoFiltro) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div id="resultados-tablas">
        <h3>Entradas Pendientes</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Establecimiento</th>
                    <th>N° Operación</th>
                    <th>Fecha Operación</th>
                    <th>Entrada</th>
                    <th>Tipo Entrada</th>
                    <th>Fecha Documento</th>
                    <th>Cantidad</th>
                    <th>Observaciones</th>
                    <th>Tramitado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entradas as $index => $entrada): ?>
                    <tr class="<?= $index % 2 === 0 ? 'even-row' : 'odd-row' ?>">
                        <td data-label="Establecimiento"><?= htmlspecialchars($entrada['establecimiento'] ?? '') ?></td>
                        <td data-label="N° Operación"><?= htmlspecialchars($entrada['numero_operacion'] ?? '') ?></td>
                        <td data-label="Fecha Operación"><?= htmlspecialchars($entrada['fecha_operacion'] ?? '') ?></td>
                        <td data-label="Entrada"><?= isset($entrada['entrada']) ? number_format($entrada['entrada'], 2) : '0.00' ?></td>
                        <td data-label="Tipo Entrada"><?= htmlspecialchars($entrada['tipo_entrada'] ?? '') ?></td>
                        <td data-label="Fecha Documento"><?= htmlspecialchars($entrada['fecha_documento'] ?? '') ?></td>
                        <td data-label="Cantidad"><?= isset($entrada['cantidad']) ? number_format($entrada['cantidad'], 2) : '0.00' ?></td>
                        <td data-label="Observaciones"><?= htmlspecialchars($entrada['observaciones'] ?? '') ?></td>
                        <td data-label="Tramitado">
                            <form method="POST" class="tramitado-form" data-numero="<?= $entrada['numero_operacion'] ?? '' ?>" data-tipo="entrada">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="numero_operacion" value="<?= $entrada['numero_operacion'] ?? '' ?>">
                                <input type="hidden" name="tipo" value="entrada">
                                <input type="checkbox" name="tramitado" class="tramitado-checkbox">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($entradas)): ?>
                    <tr>
                        <td colspan="9" class="text-center">No hay entradas pendientes</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>Salidas Pendientes</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Establecimiento</th>
                    <th>N° Operación</th>
                    <th>Fecha Operación</th>
                    <th>Salida</th>
                    <th>Tipo Salida</th>
                    <th>Observaciones</th>
                    <th>Tramitado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salidas as $index => $salida): ?>
                    <tr class="<?= $index % 2 === 0 ? 'even-row' : 'odd-row' ?>">
                        <td data-label="Establecimiento"><?= htmlspecialchars($salida['establecimiento'] ?? '') ?></td>
                        <td data-label="N° Operación"><?= htmlspecialchars($salida['numero_operacion'] ?? '') ?></td>
                        <td data-label="Fecha Operación"><?= htmlspecialchars($salida['fecha_operacion'] ?? '') ?></td>
                        <td data-label="Salida"><?= isset($salida['salida']) ? number_format($salida['salida'], 2) : '0.00' ?></td>
                        <td data-label="Tipo Salida"><?= htmlspecialchars($salida['tipo_salida'] ?? '') ?></td>
                        <td data-label="Observaciones"><?= htmlspecialchars($salida['observaciones'] ?? '') ?></td>
                        <td data-label="Tramitado">
                            <form method="POST" class="tramitado-form" data-numero="<?= $salida['numero_operacion'] ?? '' ?>" data-tipo="salida">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="numero_operacion" value="<?= $salida['numero_operacion'] ?? '' ?>">
                                <input type="hidden" name="tipo" value="salida">
                                <input type="checkbox" name="tramitado" class="tramitado-checkbox">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($salidas)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No hay salidas pendientes</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .notificacion {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px;
        border-radius: 5px;
        color: white;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .notificacion.mostrar {
        opacity: 1;
        transform: translateX(0);
    }

    .notificacion.exito {
        background-color: #4CAF50;
    }

    .notificacion.error {
        background-color: #F44336;
    }

    #resultados-tablas {
        margin-top: 20px;
    }

    .text-center {
        text-align: center;
    }

    .even-row {
        background-color: #f9f9f9;
    }

    .odd-row {
        background-color: #ffffff;
    }
</style>

<script>
    // Función para mostrar notificaciones
    function mostrarNotificacion(mensaje, esError = false) {
        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${esError ? 'error' : 'exito'}`;
        notificacion.textContent = mensaje;
        document.body.appendChild(notificacion);

        setTimeout(() => notificacion.classList.add('mostrar'), 100);
        setTimeout(() => {
            notificacion.classList.remove('mostrar');
            setTimeout(() => document.body.removeChild(notificacion), 500);
        }, 3000);
    }

    // Actualizar tramitado al cambiar el checkbox
    document.querySelectorAll('.tramitado-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const form = this.closest('.tramitado-form');
            const formData = new FormData(form);
            formData.append('actualizar_tramitado', '1');

            const originalState = this.checked;
            this.disabled = true;

            fetch('pendiente_caja_principal.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Error en la respuesta del servidor');
                    return response.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Error desconocido');

                    // Eliminar la fila de la tabla
                    const row = this.closest('tr');
                    row.style.opacity = '0.5';
                    setTimeout(() => {
                        row.remove();

                        // Verificar si la tabla está vacía y mostrar mensaje
                        const tableBody = row.closest('tbody');
                        if (tableBody.querySelectorAll('tr').length === 1) { // Solo queda la fila de encabezado
                            const emptyRow = document.createElement('tr');
                            emptyRow.innerHTML = '<td colspan="' + (row.cells.length) + '" class="text-center">No hay registros pendientes</td>';
                            tableBody.appendChild(emptyRow);
                        }
                    }, 500);

                    mostrarNotificacion('Registro tramitado correctamente');
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.checked = originalState;
                    mostrarNotificacion(error.message, true);
                })
                .finally(() => {
                    this.disabled = false;
                });
        });
    });

    // Actualizar resultados al cambiar el establecimiento
    document.getElementById('establecimiento').addEventListener('change', function() {
        document.getElementById('filtroForm').submit();
    });
</script>

<?php include('../templates/footer.php'); ?>