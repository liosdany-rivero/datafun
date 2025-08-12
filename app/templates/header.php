<?php

/**
 * ARCHIVO: header.php
 * VERSIÓN: 3.0 [Menú responsive + caché de permisos]
 * DESCRIPCIÓN: Cabecera principal con menú hamburguesa para móviles
 * AUTOR: [Tu Nombre]
 * FECHA: [Fecha de Actualización]
 * 
 * CAMBIOS PRINCIPALES:
 * - Implementado menú hamburguesa responsive
 * - Mejorada accesibilidad (ARIA)
 * - Optimizado sistema de caché de permisos
 * - Estructura semántica mejorada
 */
// 1. CONTROL DE SESIÓN ======================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 2. CABECERAS DE SEGURIDAD ================================================
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 3. AUTENTICACIÓN =========================================================
require_once('../../controllers/auth_user_check.php');

// 4. CONEXIÓN A BD =========================================================
require_once('../../controllers/config.php');

// 5. CONFIGURACIÓN DE ERRORES ==============================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 6. SISTEMA DE CACHÉ DE PERMISOS =========================================
define('CACHE_EXPIRATION', 600);
$permisoCajaPrincipal = '';
$mostrarFlujoCajaPrincipal = false;
$permisoPanaderia = '';
$tienePermisosCajas = false;
$permisosCentros = []; // Nuevo array para almacenar permisos por centro de costo

// Lógica de caché (versión optimizada)
if (isset($_SESSION['user_id'])) {
    if (
        isset($_SESSION['permisos_cache']['timestamp']) &&
        (time() - $_SESSION['permisos_cache']['timestamp'] < CACHE_EXPIRATION)
    ) {
        // Usar caché existente
        $permisoCajaPrincipal = $_SESSION['permisos_cache']['permisoCajaPrincipal'];
        $mostrarFlujoCajaPrincipal = $_SESSION['permisos_cache']['mostrarFlujoCajaPrincipal'];
        $permisoPanaderia = $_SESSION['permisos_cache']['permisoPanaderia'];
        $tienePermisosCajas = $_SESSION['permisos_cache']['tienePermisosCajas'];
        $permisosCentros = $_SESSION['permisos_cache']['permisosCentros'];
    } else {
        // Regenerar caché
        $user_id = intval($_SESSION['user_id']);

        // Consulta para obtener todos los permisos del usuario en centros de costo
        $stmt = $conn->prepare("SELECT centro_costo_codigo, permiso 
                              FROM permisos 
                              WHERE user_id = ? 
                              AND permiso IN ('leer', 'escribir', 'tramitar')");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $permisosCentros = [];
        while ($row = $result->fetch_assoc()) {
            $permisosCentros[$row['centro_costo_codigo']] = $row['permiso'];
        }
        $stmt->close();

        // Consulta para verificar permisos en establecimientos (mantener lógica existente)
        $stmt = $conn->prepare("SELECT COUNT(*) as total 
                              FROM permisos p
                              JOIN centros_costo cc ON p.centro_costo_codigo = cc.codigo
                              WHERE p.user_id = ? 
                              AND p.permiso IN ('leer', 'escribir', 'tramitar')
                              AND cc.Establecimiento = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $tienePermisosCajas = ($result->fetch_assoc()['total'] > 0);
        $stmt->close();

        // Actualizar caché
        $_SESSION['permisos_cache'] = [
            'permisoCajaPrincipal' => $permisoCajaPrincipal,
            'mostrarFlujoCajaPrincipal' => $mostrarFlujoCajaPrincipal,
            'permisoPanaderia' => $permisoPanaderia,
            'tienePermisosCajas' => $tienePermisosCajas,
            'permisosCentros' => $permisosCentros,
            'timestamp' => time()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es" dir="ltr">

<head>
    <!-- METADATOS PRINCIPALES -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Data Fun</title>

    <!-- HOJAS DE ESTILO -->
    <link rel="stylesheet" href="../../asset/css/style.css">

</head>

<body>
    <!-- CONTENEDOR PRINCIPAL -->
    <div class="page-wrapper">
        <!-- CABECERA Y MENÚ -->
        <header class="main-header">
            <!-- BOTÓN HAMBURGUESA (SOLO MÓVIL) -->
            <button class="menu-toggle" id="menuToggle" aria-label="Menú principal" aria-expanded="false">
                <span class="hamburger-box">
                    <span class="hamburger-inner"></span>
                </span>
            </button>

            <!-- MENÚ PRINCIPAL -->
            <nav class="main-navigation" id="mainNavigation" aria-label="Navegación principal">
                <ul class="nav-menu">

                    <!-- INICIO -->
                    <li><a href="../system/dashboard.php">Inicio</a></li>

                    <!-- SISTEMA -->
                    <?php if ($_SESSION['role'] === 'Administrador'): ?>
                        <li class="menu-item-has-children">
                            <a href="javascript:void(0)">Sistema</a>
                            <ul class="sub-menu">
                                <li><a href="../../controllers/exportar_bd.php" target="_blank">Respaldar BD</a></li>
                                <li><a href="../system/usuarios.php">Usuarios</a></li>
                                <li><a href="../system/centros_costo.php">Centros de costos</a></li>
                                <li><a href="../system/permisos.php">Permisos</a></li>
                                <li><a href="../system/ips_bloqueadas.php">IPs Bloqueadas</a></li>
                                <li><a href="../system/intentos_login.php">Inicios Fallidos</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- CAJAS -->
                    <?php if ($tienePermisosCajas): ?>
                        <li class="menu-item-has-children">
                            <a href="javascript:void(0)">Cajas</a>
                            <ul class="sub-menu">
                                <li><a href="../caja/caja_principal_dashboard.php">Caja Principal</a></li>
                                <?php if (isset($permisosCentros[688])): // Caja Trinidad 
                                ?>
                                    <li><a href="../caja/caja_trinidad_dashboard.php">Caja Trinidad</a></li>
                                <?php endif; ?>
                                <?php if (isset($permisosCentros[708])): // Caja Galletera 
                                ?>
                                    <li><a href="../caja/caja_galletera_dashboard.php">Caja Galletera</a></li>
                                <?php endif; ?>
                                <?php if (isset($permisosCentros[728])): // Caja Panadería 
                                ?>
                                    <li><a href="../caja/caja_panaderia_dashboard.php">Caja Panadería</a></li>
                                <?php endif; ?>
                                <?php if (isset($permisosCentros[738])): // Caja Cochiquera 
                                ?>
                                    <li><a href="../caja/caja_cochiquera_dashboard.php">Caja Cochiquera</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- CERRAR SECCION -->
                    <li><a href="../../controllers/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
                </ul>
            </nav>
        </header>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">