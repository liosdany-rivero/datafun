<?php

/**
 * ARCHIVO: usuarios.php
 * DESCRIPCIÓN: Gestión de usuarios del sistema (CRUD completo)
 * FUNCIONALIDADES:
 * - Registro de nuevos usuarios
 * - Cambio de contraseñas
 * - Eliminación de usuarios
 * - Listado de usuarios existentes
 */
// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN INICIAL

ob_start(); // Buffer de salida
include('../../templates/header.php');
require_once('../../controllers/auth_admin_check.php');
require_once('../../controllers/config.php');
// Generar token CSRF si no existe (protección contra ataques)
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Token seguro de 32 bytes
}
// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS
// 2.1 Cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
  // Validación CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }
  $user_id = $_POST['user_id'];
  $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT); // Hash seguro
  // Consulta preparada para actualizar contraseña
  $sql = "UPDATE users SET password = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("si", $new_password, $user_id);
  if ($stmt->execute()) {
    $_SESSION['success_msg'] = "✅ Contraseña actualizada correctamente";
  } else {
    $_SESSION['error_msg'] = "⚠️ Error al actualizar la contraseña: " . $stmt->error;
  }
  $stmt->close();
  // Regenerar token y redirigir (patrón PRG)
  unset($_SESSION['csrf_token']);
  header("Location: usuarios.php");
  exit();
}
// 2.2 Eliminación de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
  // Validación CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }
  $user_id = $_POST['user_id'];
  // Prevenir auto-eliminación
  if ($user_id == $_SESSION['user_id']) {
    $error_msg = "⚠️ No puedes eliminarte a ti mismo";
  } else {
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
      $_SESSION['success_msg'] = "✅ Usuario eliminado correctamente";
    } else {
      $_SESSION['error_msg'] = "Error al eliminar el usuario: " . $stmt->error;
    }
    $stmt->close();
  }
  unset($_SESSION['csrf_token']);
  header("Location: usuarios.php");
  exit();
}
// 2.3 Registro de nuevo usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_user'])) {
  // Validación CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }

  $username = trim($_POST['new_username']);
  $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
  $role = $_POST['role'];

  try {
    // VERIFICAR SI EL USUARIO YA EXISTE
    $check_sql = "SELECT id FROM users WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
      $_SESSION['error_msg'] = "⚠️ El nombre de usuario ya está en uso";
      $check_stmt->close();
      header("Location: usuarios.php");
      exit();
    }
    $check_stmt->close();

    // Si no existe, proceder con el registro
    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $password, $role);

    if ($stmt->execute()) {
      $_SESSION['success_msg'] = "✅ Registro exitoso";
    } else {
      $_SESSION['error_msg'] = "⚠️ Error al registrar el usuario.";
    }
    $stmt->close();
  } catch (mysqli_sql_exception $e) {
    $_SESSION['error_msg'] = "⚠️ Error: " . $e->getMessage();
  }

  unset($_SESSION['csrf_token']);
  ob_end_clean();
  header("Location: usuarios.php");
  exit();
}
// SECCIÓN 3: OBTENCIÓN DE DATOS
// Configuración de paginación
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
// Detectar página actual; si no hay registros, mostrar página 1
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
// Calcular índice de inicio
$inicio = ($pagina_actual - 1) * $por_pagina;
// Obtener listado de usuarios paginado
$users = [];
$sql = "SELECT id, username, role FROM users LIMIT $inicio, $por_pagina";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $users[] = $row;
  }
}
// Obtener ID del usuario actual para protección
$sql = "SELECT id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$stmt->bind_result($_SESSION['user_id']);
$stmt->fetch();
$stmt->close();
ob_end_flush(); // Enviar buffer al final del script
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
  <h2>Usuarios</h2>

  <!-- Tabla de usuarios -->
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Usuario</th>
        <th>Rol</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <!-- Todos los outputs están protegidos con htmlspecialchars() -->
          <td data-label="ID"><?= htmlspecialchars($user['id']) ?></td>
          <td data-label="Usuario"><?= htmlspecialchars($user['username']) ?></td>
          <td data-label="Rol"><?= htmlspecialchars($user['role']) ?></td>
          <td data-label>
            <div class="table-action-buttons">
              <button onclick="showPasswordForm(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                Cambiar Contraseña
              </button>
              <button onclick="showDeleteForm(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                Eliminar
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>


  <!-- Paginación -->
  <div class="pagination">
    <?php if ($pagina_actual > 1): ?>
      <a href="?pagina=1">&laquo; Primera</a>
      <a href="?pagina=<?= $pagina_actual - 1 ?>">&lsaquo; Anterior</a>
    <?php endif; ?>

    <?php
    // Mostrar solo 5 páginas alrededor de la actual
    $inicio_paginas = max(1, $pagina_actual - 2);
    $fin_paginas = min($total_paginas, $pagina_actual + 2);

    if ($inicio_paginas > 1) echo '<span>...</span>';

    for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
      <a href="?pagina=<?= $i ?>" class="<?= $i === $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor;

    if ($fin_paginas < $total_paginas) echo '<span>...</span>';
    ?>

    <?php if ($pagina_actual < $total_paginas): ?>
      <a href="?pagina=<?= $pagina_actual + 1 ?>">Siguiente &rsaquo;</a>
      <a href="?pagina=<?= $total_paginas ?>">Última &raquo;</a>
    <?php endif; ?>
  </div>


  <br>

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

  <!-- Formulario de registro (oculto inicialmente) -->
  <div id="registerFormContainer" class="sub-form" style="display: none;">
    <h3>Registrar nuevo usuario</h3>
    <form method="POST" action="usuarios.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <label for="new_username">Usuario:</label>
      <input type="text" id="new_username" name="new_username" required
        oninput="checkUsernameAvailability()" />
      <small id="username-feedback" style="display: block;"></small>
      <label for="new_password">Contraseña:</label>
      <input type="password" id="new_password" name="new_password" required />
      <label for="role">Rol:</label>
      <select id="role" name="role" required>
        <option value="Usuario">Usuario</option>
        <option value="Administrador">Administrador</option>
      </select>
      <button type="submit" name="register_user">Registrarse</button>
      <button type="button" onclick="hideForms()">Cancelar</button>
    </form>
  </div>

  <!-- Formulario cambio contraseña (oculto inicialmente) -->
  <div id="passwordFormContainer" class="sub-form" style="display: none;">
    <h3>Cambiar Contraseña para <span id="usernameDisplay"></span></h3>
    <form method="POST" action="usuarios.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="user_id" id="user_id">
      <label for="new_password">Nueva Contraseña:</label>
      <input type="password" id="new_password" name="new_password" required>
      <button type="submit" name="change_password">Actualizar</button>
      <button type="button" onclick="hideForms()">Cancelar</button>
    </form>
  </div>

  <!-- Formulario eliminación (oculto inicialmente) -->
  <div id="deleteFormContainer" class="sub-form" style="display: none;">
    <h3>¿Eliminar usuario <span id="deleteUsernameDisplay"></span>?</h3>
    <p>Esta acción no se puede deshacer. Todos los datos del usuario se perderán permanentemente.</p>
    <form method="POST" action="usuarios.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="user_id" id="delete_user_id">
      <button type="submit" name="delete_user" class="delete-btn">Confirmar Eliminación</button>
      <button type="button" onclick="hideForms()">Cancelar</button>
    </form>
  </div>
</div>

<BR>
<BR>
<BR>


<div id="barra-estado">
  <ul class="secondary-nav-menu">
    <li><button onclick="showRegisterForm()" class="nav-button">+ Nuevo Usuario</button></li>
  </ul>
</div>




<!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
<script>
  // Función para mostrar el formulario de registro
  function showRegisterForm() {
    hideForms();
    document.getElementById('registerFormContainer').style.display = 'block';
    window.scrollToBottom();
  }

  // Función para mostrar el formulario de cambio de contraseña
  function showPasswordForm(userId, username) {
    hideForms();
    document.getElementById('passwordFormContainer').style.display = 'block';
    document.getElementById('user_id').value = userId;
    document.getElementById('usernameDisplay').textContent = username;
    window.scrollToBottom();
  }

  // Función para mostrar el formulario de eliminación
  function showDeleteForm(userId, username) {
    hideForms();
    document.getElementById('deleteFormContainer').style.display = 'block';
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('deleteUsernameDisplay').textContent = username;
    window.scrollToBottom();
  }

  // Función para ocultar todos los formularios (ya está en menu.js pero la vinculamos)
  function hideForms() {
    document.querySelectorAll('.sub-form').forEach(form => {
      form.style.display = 'none';
    });
  }

  // Función para desplazarse al final de la página
  function scrollToBottom() {
    window.scrollTo({
      top: document.body.scrollHeight,
      behavior: 'smooth'
    });
  }

  // Función para verificar disponibilidad del nombre de usuario
  function checkUsernameAvailability() {
    const username = document.getElementById('new_username').value.trim();
    if (username.length < 3) return; // No verificar si es muy corto

    fetch('../../controllers/check_username.php?username=' + encodeURIComponent(username))
      .then(response => response.json())
      .then(data => {
        const usernameFeedback = document.getElementById('username-feedback');
        if (data.available) {
          usernameFeedback.textContent = "✔ Nombre disponible";
          usernameFeedback.style.color = 'green';
        } else {
          usernameFeedback.textContent = "✖ Nombre ya en uso";
          usernameFeedback.style.color = 'red';
        }
      });
  }

  // Modifica el formulario para incluir feedback
  document.getElementById('registerFormContainer').querySelector('form').addEventListener('submit', function(e) {
    const username = document.getElementById('new_username').value.trim();
    const feedback = document.getElementById('username-feedback');

    if (feedback.style.color === 'red') {
      e.preventDefault();
      alert('Por favor, elija otro nombre de usuario');
    }
  });
</script>

<?php include('../../templates/footer.php'); ?>