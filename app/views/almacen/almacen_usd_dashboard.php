<?php

/**
 * ARCHIVO: almacen_usd_dashboard.php
 * DESCRIPCIÓN: Dashboard principal para la gestión de almacenes en USD
 * FUNCIONALIDADES:
 * - Acceso a diferentes almacenes USD basado en permisos de usuario
 * - Control de acceso basado en permisos para cada centro de costo
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN INICIAL
ob_start();
include('../../templates/header.php');
require_once('../../controllers/auth_user_check.php');
require_once('../../controllers/config.php');

function completarFechasFaltantes($conn)
{
    // Obtener la última fecha registrada
    $sql_ultima_fecha = "SELECT fecha FROM tasas ORDER BY fecha DESC LIMIT 1";
    $result_ultima_fecha = mysqli_query($conn, $sql_ultima_fecha);

    if ($result_ultima_fecha && mysqli_num_rows($result_ultima_fecha) > 0) {
        $ultima_fecha = mysqli_fetch_assoc($result_ultima_fecha)['fecha'];
        $fecha_actual = date('Y-m-d', strtotime('+1 day'));

        // Si la última fecha es anterior a la fecha actual
        if ($ultima_fecha < $fecha_actual) {
            // Obtener la última tasa registrada
            $sql_ultima_tasa = "SELECT tasa FROM tasas ORDER BY fecha DESC LIMIT 1";
            $result_ultima_tasa = mysqli_query($conn, $sql_ultima_tasa);
            $ultima_tasa = mysqli_fetch_assoc($result_ultima_tasa)['tasa'];

            // Insertar registros para cada día faltante
            $fecha_actual_obj = new DateTime($fecha_actual);
            $ultima_fecha_obj = new DateTime($ultima_fecha);
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($ultima_fecha_obj->add($interval), $interval, $fecha_actual_obj);

            $conn->begin_transaction();
            try {
                foreach ($period as $date) {
                    $fecha = $date->format('Y-m-d');
                    $sql_insert = "INSERT IGNORE INTO tasas (fecha, tasa) VALUES (?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("sd", $fecha, $ultima_tasa);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error al completar fechas faltantes: " . $e->getMessage());
            }
        }
    }
}

// Ejecutar la función para completar tasas faltantes
completarFechasFaltantes($conn);


// Obtener todos los centros de costo que son almacenes USD
$almacenesUSD = [];
$stmt = $conn->prepare("SELECT codigo, nombre FROM centros_costo WHERE Almacen_USD = 1");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $almacenesUSD[$row['codigo']] = $row['nombre'];
}
$stmt->close();

// Verificar permisos para cada almacén USD
$almacenesConPermiso = [];

foreach ($almacenesUSD as $codigo => $nombre) {
    $stmt = $conn->prepare("SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    $permiso = $result->fetch_assoc()['permiso'] ?? '';
    $stmt->close();

    // Si el usuario tiene permiso de leer, escribir o tramitar, mostrar el almacén
    if (in_array($permiso, ['leer', 'escribir', 'tramitar'])) {
        $almacenesConPermiso[$codigo] = $nombre;
    }
}
// Obtener el almacén por defecto si hay solo uno con permiso
$almacenDefault = null;
if (count($almacenesConPermiso) === 1) {
    $almacenDefault = array_key_first($almacenesConPermiso);
    $_SESSION['almacen_actual'] = $almacenDefault;
}

ob_end_flush();
?>

<!-- SECCIÓN 2: INTERFAZ DE USUARIO -->
<div class="form-container">
    <h2>Almacenes USD - Dashboard</h2>

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

    <!-- Panel de botones -->
    <div class="dashboard-buttons">
        <?php if (empty($almacenesConPermiso)): ?>
            <p>No tiene permisos para acceder a ningún almacén USD.</p>
        <?php else: ?>
            <?php foreach ($almacenesConPermiso as $codigo => $nombre): ?>
                <a href="../almacen/almacen_usd_inventario.php?almacen_id=<?= $codigo ?>" class="dashboard-button">
                    <span><?= htmlspecialchars($nombre) ?></span>
                    <p>Gestión de almacén USD</p>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include('../../templates/footer.php'); ?>