<?php
session_start();
require_once('../controllers/config.php');

// Configurar manejo de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Verificar si la IP está bloqueada
$ip = $_SERVER['REMOTE_ADDR'];
$check_blocked_sql = "SELECT id FROM blocked_ips WHERE ip_address = ?";
$check_blocked_stmt = $conn->prepare($check_blocked_sql);

if ($check_blocked_stmt === false) {
  error_log("Error preparing blocked IP check: " . $conn->error);
  $_SESSION['login_error'] = "Error del sistema. Por favor intente más tarde.";
  header("Location: login.php");
  exit();
}

$check_blocked_stmt->bind_param("s", $ip);
$check_blocked_stmt->execute();
$check_blocked_stmt->store_result();

if ($check_blocked_stmt->num_rows > 0) {
  $_SESSION['login_error'] = "Tu dirección IP ($ip) ha sido bloqueada por múltiples intentos fallidos. Contacta al administrador.";
  $check_blocked_stmt->close();
  header("Location: login.php");
  exit();
}
$check_blocked_stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Usuario y contraseña son requeridos";
    header("Location: login.php");
    exit();
  }

  // Consulta para verificar credenciales
  $login_sql = "SELECT id, password, role FROM users WHERE username = ?";
  $login_stmt = $conn->prepare($login_sql);

  if ($login_stmt === false) {
    error_log("Error preparing login query: " . $conn->error);
    $_SESSION['login_error'] = "Error del sistema. Por favor intente más tarde.";
    header("Location: login.php");
    exit();
  }

  $login_stmt->bind_param("s", $username);
  $login_stmt->execute();
  $login_stmt->store_result();
  $login_stmt->bind_result($user_id, $hashed_password, $role);

  if ($login_stmt->fetch() && password_verify($password, $hashed_password)) {
    // Login exitoso
    $login_stmt->free_result();
    $login_stmt->close();

    // Limpiar intentos previos de esta IP
    $cleanup_sql = "DELETE FROM login_attempts WHERE ip_address = ?";
    $cleanup_stmt = $conn->prepare($cleanup_sql);

    if ($cleanup_stmt) {
      $cleanup_stmt->bind_param("s", $ip);
      $cleanup_stmt->execute();
      $cleanup_stmt->close();
    }

    // Registrar sesión exitosa
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;

    // Redireccionar con headers de no cache
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Location: dashboard.php");
    exit();
  } else {
    // Login fallido
    if (isset($login_stmt)) {
      $login_stmt->free_result();
      $login_stmt->close();
    }

    // Registrar intento fallido
    $attempt_sql = "INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)";
    $attempt_stmt = $conn->prepare($attempt_sql);

    if ($attempt_stmt) {
      $attempt_stmt->bind_param("ss", $ip, $username);
      $attempt_stmt->execute();
      $attempt_stmt->close();
    }

    // Contar intentos recientes
    $count_sql = "SELECT COUNT(*) FROM login_attempts 
                      WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
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

    if ($attempts >= 5) {
      // Bloquear IP
      $blocked_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
      $block_sql = "INSERT INTO blocked_ips (ip_address, attempts, blocked_by) 
                          VALUES (?, ?, ?)";
      $block_stmt = $conn->prepare($block_sql);

      if ($block_stmt) {
        $block_stmt->bind_param("sii", $ip, $attempts, $blocked_by);
        $block_stmt->execute();
        $block_stmt->close();
      }

      $_SESSION['login_error'] = "Tu dirección IP ($ip) ha sido bloqueada por múltiples intentos fallidos. Contacta al administrador.";
    } else {
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
  <link rel="stylesheet" href="../asset/css/style.css">
</head>

<body>
  <form method="POST" action="login.php" class="form-container">
    <h1>Autentificación</h1>
    <label for="username">Usuario:</label>
    <input type="text" id="username" name="username" required />

    <label for="password">Contraseña:</label>
    <input type="password" id="password" name="password" required />

    <?php if (isset($_SESSION['login_error'])): ?>
      <div class="alert-error"><?= htmlspecialchars($_SESSION['login_error']) ?></div>
      <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <button type="submit">Iniciar sesión</button>
  </form>
</body>

</html>