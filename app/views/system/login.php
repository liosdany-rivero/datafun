<?php

/**
 * login.php - Script de autenticación de usuarios
 * 
 * Este script maneja el proceso de inicio de sesión con protección contra ataques
 * de fuerza bruta mediante bloqueo de IPs después de múltiples intentos fallidos.
 * 
 * Características principales:
 * - Verificación de credenciales segura
 * - Bloqueo automático de IPs sospechosas
 * - Registro de intentos fallidos
 * - Manejo adecuado de sesiones
 * - Protección contra inyección SQL
 */



// Iniciamos la sesión para mantener el estado del usuario
session_start();

// Incluimos el archivo de configuración que contiene los datos de conexión a la BD
require_once('../../controllers/config.php');

/**
 * Configuración del manejo de errores:
 * - display_errors: 0 (No mostrar errores en pantalla por seguridad)
 * - log_errors: 1 (Registrar errores en archivo de log)
 * - error_log: Ruta del archivo de log de errores
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php_login_errors.log');

// Obtenemos la dirección IP del cliente para verificación y registro
$ip = $_SERVER['REMOTE_ADDR'];

// =============================================
// VERIFICACIÓN DE IP BLOQUEADA
// =============================================

/**
 * Preparamos la consulta para verificar si la IP actual está bloqueada
 * Usamos consultas preparadas para seguridad contra SQL injection
 */
$check_blocked_sql = "SELECT id FROM ips_bloqueadas WHERE direccion_ip = ?";
$check_blocked_stmt = $conn->prepare($check_blocked_sql);

// Verificamos si hubo error al preparar la consulta
if ($check_blocked_stmt === false) {
  // Registramos el error en el log
  error_log("[" . date('Y-m-d H:i:s') . "] Error al preparar la verificación de IP bloqueada: " . $conn->error);

  // Mostramos mensaje genérico al usuario
  $_SESSION['login_error'] = "Error del sistema. Por favor intente más tarde.";
  header("Location: login.php");
  exit();
}

// Vinculamos parámetros y ejecutamos la consulta
$check_blocked_stmt->bind_param("s", $ip);
$check_blocked_stmt->execute();
$check_blocked_stmt->store_result();

// Si encontramos registros, la IP está bloqueada
if ($check_blocked_stmt->num_rows > 0) {
  $_SESSION['login_error'] = "Tu dirección IP ($ip) ha sido bloqueada por múltiples intentos fallidos. Contacta al administrador.";
  $check_blocked_stmt->close();
  header("Location: login.php");
  exit();
}

// Cerramos la consulta de verificación de IP bloqueada
$check_blocked_stmt->close();

// =============================================
// PROCESAMIENTO DEL FORMULARIO (MÉTODO POST)
// =============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // Obtenemos y limpiamos los datos del formulario
  $username = trim($_POST['username']);  // trim() elimina espacios en blanco al inicio/final
  $password = $_POST['password'];       // La contraseña no se trim() por seguridad

  // Validación básica de campos vacíos
  if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Usuario y contraseña son requeridos";
    header("Location: login.php");
    exit();
  }

  // =============================================
  // VERIFICACIÓN DE CREDENCIALES
  // =============================================

  /**
   * Consulta para obtener los datos del usuario
   * Seleccionamos: id, password (hash), role
   * Usamos consultas preparadas para seguridad
   */
  $login_sql = "SELECT id, password, role FROM users WHERE username = ?";
  $login_stmt = $conn->prepare($login_sql);

  // Verificamos errores al preparar la consulta
  if ($login_stmt === false) {
    error_log("[" . date('Y-m-d H:i:s') . "] Error al preparar la consulta de inicio de sesión: " . $conn->error);
    $_SESSION['login_error'] = "Error del sistema. Por favor intente más tarde.";
    header("Location: login.php");
    exit();
  }

  // Vinculamos parámetros y ejecutamos
  $login_stmt->bind_param("s", $username);
  $login_stmt->execute();
  $login_stmt->store_result();
  $login_stmt->bind_result($user_id, $hashed_password, $role);

  // Verificamos credenciales
  if ($login_stmt->fetch() && password_verify($password, $hashed_password)) {
    // =============================================
    // LOGIN EXITOSO
    // =============================================

    // Liberamos recursos
    $login_stmt->free_result();
    $login_stmt->close();

    /**
     * Limpieza de intentos fallidos previos para esta IP
     * Esto permite que la IP pueda hacer nuevos intentos si antes había fallado
     */


    $cleanup_sql = "DELETE FROM intentos_login WHERE direccion_ip = ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);

    if ($cleanup_stmt) {
      $cleanup_stmt->bind_param("s", $ip);
      $cleanup_stmt->execute();
      $cleanup_stmt->close();
    }

    // Establecemos variables de sesión
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;

    /**
     * Configuramos headers para prevenir caching de páginas sensibles
     * Esto evita que el navegador guarde en caché la página de dashboard
     */

    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Location: dashboard.php");
    exit();
  } else {
    // =============================================
    // LOGIN FALLIDO
    // =============================================

    // Liberamos recursos
    if (isset($login_stmt)) {
      $login_stmt->free_result();
      $login_stmt->close();
    }

    /**
     * Registramos el intento fallido en la base de datos
     * Almacenamos: IP, nombre de usuario y timestamp (automático)
     */
    $attempt_sql = "INSERT INTO intentos_login (direccion_ip, username) VALUES (?, ?)";
    $attempt_stmt = $conn->prepare($attempt_sql);

    if ($attempt_stmt) {
      $attempt_stmt->bind_param("ss", $ip, $username);
      $attempt_stmt->execute();
      $attempt_stmt->close();
    }

    /**
     * Contamos intentos fallidos recientes (última hora) para esta IP
     * Esto determina si debemos bloquear la IP
     */
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
      $attempts = 1; // Valor por defecto si falla el conteo
    }

    /**
     * Bloqueo de IP si supera el límite de intentos (5 en 1 hora)
     * Registramos: IP, número de intentos, y quién la bloqueó (si aplica)
     */
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
  <!-- Formulario de inicio de sesión -->
  <form method="POST" action="login.php" class="form-login">
    <h1>Autentificación</h1>

    <!-- Campo de usuario -->
    <label for="username">Usuario:</label>
    <input type="text" id="username" name="username" required />

    <!-- Campo de contraseña -->
    <label for="password">Contraseña:</label>
    <input type="password" id="password" name="password" required />

    <!-- Notificación flotante para mensajes de login -->
    <?php if (isset($_SESSION['login_error'])): ?>
      <div id="floatingNotification" class="floating-notification error">
        <?= htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <!-- Botón de envío -->
    <button type="submit">Iniciar sesión</button>
  </form>
</body>

</html>

<script>
  // Mostrar y ocultar notificación flotante - mismo comportamiento que usuarios.php
  document.addEventListener('DOMContentLoaded', function() {
    const notification = document.getElementById('floatingNotification');
    if (notification) {
      // Mostrar notificación
      setTimeout(() => {
        notification.classList.add('show');
      }, 100);

      // Ocultar después de 5 segundos
      setTimeout(() => {
        notification.classList.remove('show');
        // Eliminar del DOM después de la animación
        setTimeout(() => {
          notification.remove();
        }, 300);
      }, 5000);
    }
  });
</script>