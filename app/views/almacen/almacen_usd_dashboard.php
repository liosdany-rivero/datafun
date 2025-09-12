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