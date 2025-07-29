<?php
include('../templates/header.php');
require_once('../controllers/auth_admin_check.php');
require_once('../controllers/config.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mensajes
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Procesar guardar permiso (solo creación)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permiso'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }
    $user_id = $_POST['user_id'];
    $codigo = $_POST['codigo'];
    $permiso = $_POST['permiso'];

    // Verificar si ya existe
    $check_sql = "SELECT * FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $codigo);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_stmt->close();

    if ($check_result->num_rows > 0) {
        $error_msg = "⚠️ Este usuario ya tiene permisos para este establecimiento";
    } else {
        $sql = "INSERT INTO permisos_establecimientos (user_id, establecimiento_codigo, permiso) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $user_id, $codigo, $permiso);
        if ($stmt->execute()) {
            $success_msg = "✅ Permiso asignado correctamente.";
        } else {
            $error_msg = "⚠️ Error al asignar permiso: " . $stmt->error;
        }
        $stmt->close();
    }

    unset($_SESSION['csrf_token']);
    $_SESSION['success_msg'] = $success_msg ?? '';
    $_SESSION['error_msg'] = $error_msg ?? '';
    header("Location: permisos.php");
    exit();
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_permiso'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }
    $user_id = $_POST['user_id'];
    $codigo = $_POST['codigo'];

    $sql = "DELETE FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $codigo);
    if ($stmt->execute()) {
        $success_msg = "✅ Permiso eliminado correctamente.";
    } else {
        $error_msg = "⚠️ Error al eliminar: " . $stmt->error;
    }
    $stmt->close();
    unset($_SESSION['csrf_token']);
    $_SESSION['success_msg'] = $success_msg ?? '';
    $_SESSION['error_msg'] = $error_msg ?? '';
    header("Location: permisos.php");
    exit();
}

// Obtener permisos
$permisos = [];
$query = "
  SELECT p.user_id, u.username AS usuario, p.establecimiento_codigo, e.nombre AS establecimiento, p.permiso
  FROM permisos_establecimientos p
  JOIN users u ON p.user_id = u.id
  JOIN establecimientos e ON p.establecimiento_codigo = e.codigo
";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $permisos[] = $row;
}

// Obtener usuarios y establecimientos
$usuarios = mysqli_query($conn, "SELECT id, username FROM users");
$establecimientos = mysqli_query($conn, "SELECT codigo, nombre FROM establecimientos");
?>

<nav class="sub-menu">
    <ul>
        <li><button onclick="showCreateForm()">Asignar nuevo permiso</button></li>
    </ul>
</nav>

<div class="form-container">
    <h2>Gestión de Permisos</h2>


    <table class="table">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Establecimiento</th>
                <th>Permiso</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permisos as $row): ?>
                <tr>
                    <td data-label><?= htmlspecialchars($row['usuario']) ?></td>
                    <td data-label><?= htmlspecialchars($row['establecimiento']) ?></td>
                    <td data-label><?= htmlspecialchars($row['permiso']) ?></td>
                    <td data-label>
                        <div class="table-action-buttons">
                            <button onclick="showDeleteForm(<?= $row['user_id'] ?>, <?= $row['establecimiento_codigo'] ?>, '<?= htmlspecialchars(addslashes($row['usuario'])) ?>', '<?= htmlspecialchars(addslashes($row['establecimiento'])) ?>')">Eliminar</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Formulario Crear -->
    <div id="permisoFormContainer" class="sub-form" style="display: none;">
        <h3>Asignar Nuevo Permiso</h3>
        <form method="POST" action="permisos.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <label for="user_id">Usuario:</label>
            <select name="user_id" id="user_id" required>
                <option value="" selected disabled>-- Seleccione --</option> <!-- Nueva opción vacía -->
                <?php mysqli_data_seek($usuarios, 0);
                while ($u = mysqli_fetch_assoc($usuarios)): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                <?php endwhile; ?>
            </select>

            <label for="codigo">Establecimiento:</label>
            <select name="codigo" id="codigo" required>
                <option value="" selected disabled>-- Seleccione --</option> <!-- Nueva opción vacía -->
                <?php mysqli_data_seek($establecimientos, 0);
                while ($e = mysqli_fetch_assoc($establecimientos)): ?>
                    <option value="<?= $e['codigo'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                <?php endwhile; ?>
            </select>

            <label for="permiso">Permiso:</label>
            <select name="permiso" id="permiso" required>
                <option value="lectura">Lectura</option>
                <option value="escritura">Escritura</option>
            </select>

            <button type="submit" name="save_permiso">Asignar Permiso</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>

    <!-- Formulario Eliminar -->
    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar permiso de <span id="deleteUsuarioDisplay"></span> sobre <span id="deleteEstablecimientoDisplay"></span>?</h3>
        <form method="POST" action="permisos.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="user_id" id="delete_user_id">
            <input type="hidden" name="codigo" id="delete_codigo">
            <button type="submit" name="delete_permiso" class="delete-btn">Confirmar Eliminación</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>
</div>

<script>
    function showCreateForm() {
        hideForms();
        document.getElementById('user_id').selectedIndex = 0;
        document.getElementById('codigo').selectedIndex = 0;
        document.getElementById('permiso').value = 'lectura';
        document.getElementById('permisoFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function showDeleteForm(user_id, codigo, usuario, establecimiento) {
        hideForms();
        document.getElementById('delete_user_id').value = user_id;
        document.getElementById('delete_codigo').value = codigo;
        document.getElementById('deleteUsuarioDisplay').textContent = usuario;
        document.getElementById('deleteEstablecimientoDisplay').textContent = establecimiento;
        document.getElementById('deleteFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function hideForms() {
        document.getElementById('permisoFormContainer').style.display = 'none';
        document.getElementById('deleteFormContainer').style.display = 'none';
    }
</script>

<?php include('../templates/footer.php'); ?>