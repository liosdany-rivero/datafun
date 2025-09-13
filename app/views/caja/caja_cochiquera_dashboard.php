<?php

/**
 * ARCHIVO: caja_principal_dashboard.php
 * DESCRIPCIÓN: Dashboard principal para la gestión de la caja cochiquera
 * FUNCIONALIDADES:
 * - Acceso rápido a movimientos, operaciones y flujo de caja
 * - Control de acceso basado en permisos
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN INICIAL
ob_start();
include('../../templates/header.php');
require_once('../../controllers/auth_user_check.php');
require_once('../../controllers/config.php');

// Verificar permisos para los botones
$mostrarMovimientos = false;
$mostrarOperaciones = false;
$mostrarFlujo = false;

// Consultar permisos específicos para caja cochiquera (código 800)
$stmt = $conn->prepare("SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = 738");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$permisoCajaCochiquera = $result->fetch_assoc()['permiso'] ?? '';
$stmt->close();

// Botón Movimientos: solo para usuarios con permiso 'escribir' en centro de costo 800
if ($permisoCajaCochiquera === 'escribir') {
    $mostrarMovimientos = true;
}

// Botón Operaciones: para usuarios con permiso 'leer', 'escribir' o 'tramitar' en centro de costo 800
if (in_array($permisoCajaCochiquera, ['leer', 'escribir', 'tramitar'])) {
    $mostrarOperaciones = true;
}

// Botón Flujo: para usuarios con permiso 'leer', 'escribir' o 'tramitar' en cualquier centro de costo que sea establecimiento
$stmt = $conn->prepare("SELECT COUNT(*) as total 
                        FROM permisos p
                        JOIN centros_costo cc ON p.centro_costo_codigo = cc.codigo
                        WHERE p.user_id = ? 
                        AND p.permiso IN ('leer', 'escribir', 'tramitar')
                        AND cc.Punto_Venta = 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$mostrarFlujo = ($result->fetch_assoc()['total'] > 0);
$stmt->close();

ob_end_flush();
?>

<!-- SECCIÓN 2: INTERFAZ DE USUARIO -->
<div class="form-container">
    <h2>Caja Cochiquera - Dashboard</h2>

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
        <?php if ($mostrarMovimientos): ?>
            <a href="../caja/caja_cochiquera_movimientos.php" class="dashboard-button">
                <span>Movimientos</span>
                <p>Registro de entradas y salidas</p>
            </a>
        <?php endif; ?>

        <?php if ($mostrarOperaciones): ?>
            <a href="../caja/caja_cochiquera_operaciones.php" class="dashboard-button">
                <span>Operaciones</span>
                <p>Gestión de edición y eliminacion</p>
            </a>
        <?php endif; ?>

        <?php if ($mostrarFlujo): ?>
            <a href="../caja/caja_cochiquera_flujo.php" class="dashboard-button">
                <span>Flujo</span>
                <p>Reporte de flujo de caja</p>
            </a>
        <?php endif; ?>
    </div>
</div>



<?php include('../../templates/footer.php'); ?>