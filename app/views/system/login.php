<?php

/**
 * Proyecto: Datafun
 * Desarrollador: liosdany-rivero (GitHub)
 * Fecha: Noviembre 2025
 */


//================================================================================================
// 1. Configuración Inicial y Seguridad
//================================================================================================

// 1.1. Inicialización de Sesión
session_start();

// 1.2. Configuración de Base de Datos
require_once('../../controllers/config.php');

// 1.3. Configuración de Manejo de Errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php_login_errors.log');

// 1.4. Obtención de Dirección IP del Cliente
$ip = $_SERVER['REMOTE_ADDR'];

//================================================================================================
// 2. Verificación de IP Bloqueada
//================================================================================================

//2.1. Consulta de IP en Lista de Bloqueo
$check_blocked_sql = "SELECT id FROM ips_bloqueadas WHERE direccion_ip = ?";
$check_blocked_stmt = $conn->prepare($check_blocked_sql);

// 2.2. Manejo de Errores en Preparación de Consulta
if ($check_blocked_stmt === false) {
  error_log("[" . date('Y-m-d H:i:s') . "] Error al preparar la verificación de IP bloqueada: " . $conn->error);
  $_SESSION['login_error'] = "Error del sistema. Por favor intente más tarde.";
  header("Location: login.php");
  exit();
}

// 2.3. Ejecución y Evaluación de Resultados
$check_blocked_stmt->bind_param("s", $ip);
$check_blocked_stmt->execute();
$check_blocked_stmt->store_result();

// 2.4. Bloqueo de Acceso si IP está en Lista Negra
if ($check_blocked_stmt->num_rows > 0) {
  $_SESSION['login_error'] = "Tu dirección IP ($ip) ha sido bloqueada por múltiples intentos fallidos. Contacta al administrador.";
  $check_blocked_stmt->close();
  header("Location: login.php");
  exit();
}

// 2.5. Liberación de Recursos
$check_blocked_stmt->close();

//================================================================================================
// 3. Procesamiento de Solicitud POST
//================================================================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  // 3.1. Limpieza de Datos de Entrada
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  // 3.2. Validación de Campos Obligatorios
  if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Usuario y contraseña son requeridos";
    header("Location: login.php");
    exit();
  }

  //================================================================================================
  // 4. Verificación de Credenciales de Usuario
  //================================================================================================

  // 4.1. Consulta de Autenticación
  $login_sql = "SELECT id, password, role FROM users WHERE username = ?";
  $login_stmt = $conn->prepare($login_sql);

  // 4.2. Manejo de Errores en Preparación de Consulta
  if ($login_stmt === false) {
    error_log("[" . date('Y-m-d H:i:s') . "] Error al preparar la consulta de inicio de sesión: " . $conn->error);
    $_SESSION['login_error'] = "Error del sistema. Por favor intente más tarde.";
    header("Location: login.php");
    exit();
  }

  // 4.3. Ejecución y Recuperación de Resultados
  $login_stmt->bind_param("s", $username);
  $login_stmt->execute();
  $login_stmt->store_result();
  $login_stmt->bind_result($user_id, $hashed_password, $role);


  //================================================================================================
  // 5. Autenticación Exitosa
  //================================================================================================
  if ($login_stmt->fetch() && password_verify($password, $hashed_password)) {

    // 5.1. Liberación de Recursos de Consulta
    $login_stmt->free_result();
    $login_stmt->close();

    // 5.2. Limpieza de Intentos Fallidos Previos
    $cleanup_sql = "DELETE FROM intentos_login WHERE direccion_ip = ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);

    if ($cleanup_stmt) {
      $cleanup_stmt->bind_param("s", $ip);
      $cleanup_stmt->execute();
      $cleanup_stmt->close();
    }

    // 5.3. Establecimiento de Variables de Sesión
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;

    // 5.4. Configuración de Headers de Seguridad
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Location: dashboard.php");
    exit();

    //================================================================================================
    // 6. Autenticación Fallida
    //================================================================================================
  } else {

    // 6.1. Liberación de Recursos de Consulta
    if (isset($login_stmt)) {
      $login_stmt->free_result();
      $login_stmt->close();
    }

    // 6.2. Registro de Intento Fallido
    $attempt_sql = "INSERT INTO intentos_login (direccion_ip, username) VALUES (?, ?)";
    $attempt_stmt = $conn->prepare($attempt_sql);

    if ($attempt_stmt) {
      $attempt_stmt->bind_param("ss", $ip, $username);
      $attempt_stmt->execute();
      $attempt_stmt->close();
    }

    //================================================================================================
    // 7. Sistema de Protección contra Fuerza Bruta
    //================================================================================================

    // 7.1. Conteo de Intentos Recientes
    $count_sql = "SELECT COUNT(*) FROM intentos_login 
                     WHERE direccion_ip = ? AND hora_intento > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $count_stmt = $conn->prepare($count_sql);

    if ($count_stmt) {
      $count_stmt->bind_param("s", $ip);
      $count_stmt->execute();
      $count_stmt->bind_result($attempts);
      $count_stmt->fetch();
      $count_stmt->close();
    } else {
      $attempts = 1;
    }

    // 7.2. Bloqueo de IP por Exceso de Intentos
    if ($attempts >= 5) {
      $blocked_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
      $block_sql = "INSERT INTO ips_bloqueadas (direccion_ip, intentos, bloqueado_por) 
                         VALUES (?, ?, ?)";
      $block_stmt = $conn->prepare($block_sql);

      if ($block_stmt) {
        $block_stmt->bind_param("sii", $ip, $attempts, $blocked_by);
        $block_stmt->execute();
        $block_stmt->close();
      }

      $_SESSION['login_error'] = "Tu dirección IP ($ip) ha sido bloqueada por múltiples intentos fallidos. Contacta al administrador.";
    } else {
      // Mostramos intentos restantes antes del bloqueo
      $remaining_attempts = 5 - $attempts;
      $_SESSION['login_error'] = "Credenciales inválidas. Te quedan $remaining_attempts intentos.";
    }

    header("Location: login.php");
    exit();
  }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión</title>
  <link rel="stylesheet" href="../../asset/css/style.css">
</head>

<body>

  <!--================================================================================================
        8. Interfaz de Usuario - Formulario de Login
    ================================================================================================-->

  <form method="POST" action="login.php" class="form-login">
    <h1>Autentificación</h1>

    <!-- 8.1. Campo de Usuario -->
    <label for="username">Usuario:</label>
    <input type="text" id="username" name="username" required />

    <!-- 8.2. Campo de Contraseña -->
    <label for="password">Contraseña:</label>
    <input type="password" id="password" name="password" required />


    <!--================================================================================================
            8A. Sistema de Notificaciones
        ================================================================================================-->

    <?php if (isset($_SESSION['login_error'])): ?>
      <div id="floatingNotification" class="floating-notification error">
        <?= htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <!-- 8.3. Botón de Envío -->
    <button type="submit">Iniciar sesión</button>
  </form>
</body>

</html>

<!--================================================================================================
    10. Comportamiento de Interfaz - JavaScript
================================================================================================-->

<script>
  // 10.1. Gestión de Notificaciones Flotantes
  document.addEventListener('DOMContentLoaded', function() {
    const notification = document.getElementById('floatingNotification');
    if (notification) {
      setTimeout(() => {
        notification.classList.add('show');
      }, 100);
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          notification.remove();
        }, 300);
      }, 5000);
    }
  });
</script>