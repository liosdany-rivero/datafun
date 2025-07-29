<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('../templates/header.php');
require_once('../controllers/config.php');

// Verificar permisos
$esAdmin = ($_SESSION['role'] === 'Administrador');


// Obtener el mapeo de códigos de establecimiento a nombres
$establecimientos = [];
$queryEstablecimientos = "SELECT codigo, nombre FROM establecimientos";
$stmt = $conn->prepare($queryEstablecimientos);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $establecimientos[$row['codigo']] = $row['nombre'];
}
$stmt->close();


// Determinar si se han aplicado filtros
$filtrosAplicados = isset($_GET['fecha_desde']) || isset($_GET['fecha_hasta']);

// Procesar filtros
$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';

// Si es la primera carga (sin filtros aplicados), autocompletar fechas
if (!$filtrosAplicados) {
    $queryUltimaFecha = "SELECT MAX(fecha_operacion) as ultima_fecha FROM caja_panaderia";
    $stmt = $conn->prepare($queryUltimaFecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $ultimaFecha = $result->fetch_assoc()['ultima_fecha'] ?? null;
    $stmt->close();

    if ($ultimaFecha) {
        $fechaHasta = $ultimaFecha;
        $fechaObj = new DateTime($fechaHasta);
        $fechaObj->modify('-1 month')->modify('+5 days');
        $fechaDesde = $fechaObj->format('Y-m-d');
    }
}

// Procesar actualización de campo tramitado (solo para admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $esAdmin && isset($_POST['actualizar_tramitado'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'error' => 'Token CSRF inválido']));
    }

    $numeroOperacion = intval($_POST['numero_operacion']);
    $tramitado = isset($_POST['tramitado']) ? 1 : 0;

    try {
        $sql = "UPDATE caja_panaderia SET tramitado = ? WHERE numero_operacion = ?";
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

// Construir condiciones WHERE para las consultas
$whereBase = "1";
$params = [];
$paramTypes = "";

if (!empty($fechaDesde) && !empty($fechaHasta)) {
    $whereBase .= " AND fecha_operacion BETWEEN ? AND ?";
    array_push($params, $fechaDesde, $fechaHasta);
    $paramTypes .= "ss";
}

// Obtener entradas (operaciones con entrada > 0)
$entradas = [];
$queryEntradas = "SELECT 
    numero_operacion, 
    fecha_operacion, 
    entrada,
    desde_para as tipo_entrada,
    observaciones,
    tramitado
FROM caja_panaderia
WHERE entrada > 0 AND $whereBase
ORDER BY fecha_operacion DESC, numero_operacion DESC";
$stmt = $conn->prepare($queryEntradas);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $entradas[] = $row;
}
$stmt->close();

// Obtener salidas (operaciones con salida > 0)
$salidas = [];
$querySalidas = "SELECT 
    numero_operacion, 
    fecha_operacion, 
    salida,
    desde_para as tipo_salida,
    observaciones,
    tramitado
FROM caja_panaderia
WHERE salida > 0 AND $whereBase
ORDER BY fecha_operacion DESC, numero_operacion DESC";
$stmt = $conn->prepare($querySalidas);
if (!empty($params)) {
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
    <h2>Flujo de Caja Panadería</h2>

    <form method="GET" id="filtroForm">
        <div class="input-group">
            <label for="fecha_desde">Desde:</label>
            <input type="date" name="fecha_desde" id="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>" required>
        </div>

        <div class="input-group">
            <label for="fecha_hasta">Hasta:</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>" required>
        </div>

        <button type="submit">Aplicar Filtros</button>
    </form>

    <div id="resultados-tablas" style="display: <?= $filtrosAplicados ? 'block' : 'none' ?>;">
        <?php if ($filtrosAplicados): ?>
            <h3>Entradas</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Operación</th>
                        <th>Fecha Operación</th>
                        <th>Entrada</th>
                        <th>Desde</th>
                        <th>Observaciones</th>
                        <th>Tramitado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entradas as $index => $entrada): ?>
                        <tr class="<?= $index % 2 === 0 ? 'even-row' : 'odd-row' ?>">
                            <td data-label="N° Operación"><?= htmlspecialchars($entrada['numero_operacion'] ?? '') ?></td>
                            <td data-label="Fecha Operación"><?= htmlspecialchars($entrada['fecha_operacion'] ?? '') ?></td>
                            <td data-label="Entrada"><?= isset($entrada['entrada']) ? number_format($entrada['entrada'], 2) : '0.00' ?></td>
                            <td data-label="Descripción"><?=
                                                            isset($entrada['tipo_entrada']) && isset($establecimientos[$entrada['tipo_entrada']])
                                                                ? htmlspecialchars($establecimientos[$entrada['tipo_entrada']])
                                                                : htmlspecialchars($entrada['tipo_entrada'] ?? '')
                                                            ?></td>
                            <td data-label="Observaciones"><?= htmlspecialchars($entrada['observaciones'] ?? '') ?></td>
                            <td data-label="Tramitado">
                                <?php if ($esAdmin): ?>
                                    <form method="POST" class="tramitado-form" data-numero="<?= $entrada['numero_operacion'] ?? '' ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="numero_operacion" value="<?= $entrada['numero_operacion'] ?? '' ?>">
                                        <input type="hidden" name="actualizar_tramitado" value="1">
                                        <input type="checkbox" name="tramitado" <?= ($entrada['tramitado'] ?? 0) ? 'checked' : '' ?> class="tramitado-checkbox">
                                    </form>
                                <?php else: ?>
                                    <?= ($entrada['tramitado'] ?? 0) ? '✅' : '❌' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($entradas)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay entradas registradas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3>Salidas</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Operación</th>
                        <th>Fecha Operación</th>
                        <th>Salida</th>
                        <th>Para</th>
                        <th>Observaciones</th>
                        <th>Tramitado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salidas as $index => $salida): ?>
                        <tr class="<?= $index % 2 === 0 ? 'even-row' : 'odd-row' ?>">
                            <td data-label="N° Operación"><?= htmlspecialchars($salida['numero_operacion'] ?? '') ?></td>
                            <td data-label="Fecha Operación"><?= htmlspecialchars($salida['fecha_operacion'] ?? '') ?></td>
                            <td data-label="Salida"><?= isset($salida['salida']) ? number_format($salida['salida'], 2) : '0.00' ?></td>
                            <td data-label="Descripción"><?=
                                                            isset($salida['tipo_salida']) && isset($establecimientos[$salida['tipo_salida']])
                                                                ? htmlspecialchars($establecimientos[$salida['tipo_salida']])
                                                                : htmlspecialchars($salida['tipo_salida'] ?? '')
                                                            ?></td>
                            <td data-label="Observaciones"><?= htmlspecialchars($salida['observaciones'] ?? '') ?></td>
                            <td data-label="Tramitado">
                                <?php if ($esAdmin): ?>
                                    <form method="POST" class="tramitado-form" data-numero="<?= $salida['numero_operacion'] ?? '' ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="numero_operacion" value="<?= $salida['numero_operacion'] ?? '' ?>">
                                        <input type="hidden" name="actualizar_tramitado" value="1">
                                        <input type="checkbox" name="tramitado" <?= ($salida['tramitado'] ?? 0) ? 'checked' : '' ?> class="tramitado-checkbox">
                                    </form>
                                <?php else: ?>
                                    <?= ($salida['tramitado'] ?? 0) ? '✅' : '❌' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($salidas)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay salidas registradas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
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
        transition: opacity 0.3s ease;
    }

    .fade-out {
        opacity: 0;
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

    // Ocultar tablas al interactuar con filtros
    document.querySelectorAll('#filtroForm input').forEach(control => {
        control.addEventListener('change', function() {
            const tablas = document.getElementById('resultados-tablas');
            if (tablas.style.display !== 'none') {
                tablas.classList.add('fade-out');
                setTimeout(() => {
                    tablas.style.display = 'none';
                    tablas.classList.remove('fade-out');
                }, 300);
            }
        });
    });

    // Mostrar tablas al enviar el formulario
    document.getElementById('filtroForm').addEventListener('submit', function(e) {
        const fechaDesde = document.getElementById('fecha_desde').value;
        const fechaHasta = document.getElementById('fecha_hasta').value;

        if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
            e.preventDefault();
            mostrarNotificacion('La fecha "Desde" no puede ser mayor que la fecha "Hasta"', true);
            return;
        }
    });

    // Actualizar tramitado al cambiar el checkbox
    document.querySelectorAll('.tramitado-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const form = this.closest('.tramitado-form');
            const formData = new FormData(form);

            const originalState = this.checked;
            this.disabled = true;

            fetch('flujo_caja_panaderia.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Error en la respuesta del servidor');
                    return response.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Error desconocido');

                    // Actualizar visualmente el checkbox
                    this.checked = data.tramitado === 1;
                    mostrarNotificacion('Estado actualizado correctamente');

                    // Si el usuario no es admin, actualizar el emoji
                    if (!<?= $esAdmin ? 'true' : 'false' ?>) {
                        const tdTramitado = this.closest('td');
                        tdTramitado.innerHTML = data.tramitado ? '✅' : '❌';
                    }
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
</script>

<?php include('../templates/footer.php'); ?>