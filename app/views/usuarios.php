<?php
include('../templates/header.php');
require_once('../controllers/auth_admin_check.php');
require_once('../controllers/config.php');

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }
  $user_id = $_POST['user_id'];
  $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

  $sql = "UPDATE users SET password = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("si", $new_password, $user_id);

  if ($stmt->execute()) {
    $success_msg = "✅ Contraseña actualizada correctamente";
  } else {
    $error_msg = "⚠️ Error al actualizar la contraseña: " . $stmt->error;
  }
  $stmt->close();

  // Regenerar token después de uso
  unset($_SESSION['csrf_token']);
  header("Location: usuarios.php");
  exit();
}

// Procesar eliminación de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }
  $user_id = $_POST['user_id'];

  // No permitir eliminarse a sí mismo
  if ($user_id == $_SESSION['user_id']) {
    $error_msg = "⚠️ No puedes eliminarte a ti mismo";
  } else {
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
      $success_msg = "✅ Usuario eliminado correctamente";
    } else {
      $error_msg = "Error al eliminar el usuario: " . $stmt->error;
    }
    $stmt->close();
  }
  // Regenerar token después de uso
  unset($_SESSION['csrf_token']);
  header("Location: usuarios.php");
  exit();
}

// Procesar registro de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_user'])) {

  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }
  $username = $_POST['new_username'];
  $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
  $role = $_POST['role'];

  try {
    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $password, $role);

    if ($stmt->execute()) {
      $success_msg = "✅ Registro exitoso";
    } else {
      $error_msg = "⚠️ Error al registrar el usuario.";
    }

    $stmt->close();
  } catch (mysqli_sql_exception $e) {
    $error_msg = "⚠️ Error: " . $e->getMessage();
  }
  // Regenerar token después de uso
  unset($_SESSION['csrf_token']);
  header("Location: usuarios.php");
  exit();
}



// Obtener ID del usuario actual para protección
$sql = "SELECT id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$stmt->bind_result($_SESSION['user_id']);
$stmt->fetch();
$stmt->close();

// Obtener todos los usuarios
$users = [];
$sql = "SELECT id, username, role FROM users";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $users[] = $row;
  }
}
?>

<nav class="sub-menu">
  <ul>
    <li><button onclick="showRegisterForm()">Crear</button></li>
  </ul>
</nav>

<div class="form-container">
  <h2>Usuarios</h2>
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
          <td data-label><?= htmlspecialchars($user['id']) ?></td>
          <td data-label><?= htmlspecialchars($user['username']) ?></td>
          <td data-label><?= htmlspecialchars($user['role']) ?></td>
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

  <?php if (isset($success_msg)): ?>
    <div class="alert-success"><?= $success_msg ?></div>
  <?php endif; ?>

  <?php if (isset($error_msg)): ?>
    <div class="alert-error"><?= $error_msg ?></div>
  <?php endif; ?>

  <div id="registerFormContainer" class="sub-form" style="display: none;">
    <h3>Registrar nuevo usuario</h3>
    <form method="POST" action="usuarios.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <label for="new_username">Usuario:</label>
      <input type="text" id="new_username" name="new_username" required />

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




<script>
  function showPasswordForm(userId, username) {
    hideForms();
    document.getElementById('user_id').value = userId;
    document.getElementById('usernameDisplay').textContent = username;
    document.getElementById('passwordFormContainer').style.display = 'block';
    window.scrollTo(0, document.body.scrollHeight);
  }

  function showDeleteForm(userId, username) {
    hideForms();
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('deleteUsernameDisplay').textContent = username;
    document.getElementById('deleteFormContainer').style.display = 'block';
    window.scrollTo(0, document.body.scrollHeight);
  }

  function showRegisterForm() {
    hideForms();
    document.getElementById('registerFormContainer').style.display = 'block';
    window.scrollTo(0, document.body.scrollHeight);
  }

  function hideForms() {
    document.getElementById('passwordFormContainer').style.display = 'none';
    document.getElementById('deleteFormContainer').style.display = 'none';
    document.getElementById('registerFormContainer').style.display = 'none';
  }
</script>


<?php include('../templates/footer.php'); ?>