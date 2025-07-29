<?php
// Debe ser lo PRIMERO en el archivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers anti-caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Iniciar sesión si no está iniciada
require_once('../controllers/auth_user_check.php');

// Conexión a la base de datos
require_once('../controllers/config.php');

// Mostrar errores solo en desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inicializar variable de permiso
$permisoCaja = '';

// Verificar si el usuario está autenticado
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);

    // Consultar permiso usando consulta preparada
    $query = "SELECT permiso FROM permisos_establecimientos 
              WHERE user_id = ? AND establecimiento_codigo = 800";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $permisoCaja = $row['permiso'];
    }
    $stmt->close();
}

// Verificar permisos para Flujo de Caja
$mostrarFlujoCaja = false;
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);

    // Consultar si tiene permisos en algún establecimiento
    $query = "SELECT COUNT(*) as total FROM permisos_establecimientos 
              WHERE user_id = ? AND permiso IN ('lectura', 'escritura')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $mostrarFlujoCaja = ($row['total'] > 0);
    $stmt->close();
}



// Verificar permisos para la panadería (código 721)
$permisoPanaderia = '';
if (isset($_SESSION['user_id'])) {
    $query = "SELECT permiso FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = 728";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $permisoPanaderia = $result->fetch_assoc()['permiso'] ?? '';
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Fun</title>
    <link rel="stylesheet" href="../asset/css/style.css">
</head>

<body>
    <!-- Menú principal -->
    <nav class="main-menu">
        <ul>
            <li><a href="../views/dashboard.php">Inicio</a></li>

            <?php if ($_SESSION['role'] === 'Administrador'): ?>
                <!-- Menú Sistema -->
                <li class="submenu">
                    <a href="javascript:void(0)">Sistema ▾</a>
                    <ul class="dropdown">
                        <li><a href="../controllers/exportar_bd.php" target="_blank">Respaldar BD</a></li>
                        <li><a href="ip_blocked.php">IPs Bloqueadas</a></li>
                        <li><a href="login_attempts.php">Inicios Fallidos</a></li>
                    </ul>
                </li>

                <!-- Menú Catálogos -->
                <li class="submenu">
                    <a href="javascript:void(0)">Catálogos ▾</a>
                    <ul class="dropdown">
                        <li><a href="usuarios.php">Usuarios</a></li>
                        <li><a href="establecimientos.php">Establecimientos</a></li>
                        <li><a href="permisos.php">Permisos</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if (in_array($permisoCaja, ['escritura', 'lectura']) || $mostrarFlujoCaja): ?>
                <!-- Menú Caja -->
                <li class="submenu">
                    <a href="javascript:void(0)">Caja ▾</a>
                    <ul class="dropdown">
                        <?php if (in_array($permisoCaja, ['escritura'])): ?>
                            <li><a href="caja_principal.php">Caja Principal</a></li>
                        <?php endif; ?>
                        <?php if (in_array($permisoCaja, ['escritura', 'lectura'])): ?>
                            <li><a href="operaciones.php">Operaciones</a></li>
                        <?php endif; ?>
                        <?php if ($mostrarFlujoCaja): ?>
                            <li><a href="flujo_caja.php">Flujo de Caja</a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'Administrador'): ?>
                            <li><a href="pendiente_caja_principal.php">Pendientes de Tramitación</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Nuevo menú Establecimientos -->
            <?php if (in_array($permisoPanaderia, ['escritura', 'lectura'])): ?>
                <li class="submenu">
                    <a href="javascript:void(0)">Panadería ▾</a>
                    <ul class="dropdown">
                        <!-- Nuevo botón para Caja Panadería -->
                        <?php if (in_array($permisoPanaderia, ['escritura'])): ?>
                            <li><a href="caja_panaderia.php">Caja Panadería</a></li>
                        <?php endif; ?>
                        <?php if (in_array($permisoPanaderia, ['escritura', 'lectura'])): ?>
                            <li><a href="operaciones_panaderia.php">Operaciones</a></li>
                            <li><a href="flujo_caja_panaderia.php">Flujo de Caja</a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'Administrador'): ?>
                            <li><a href="pendiente_caja_panaderia.php">Pendientes de Tramitación</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>


            <li><a href="../controllers/logout.php">Cerrar sesión</a></li>
        </ul>

    </nav>