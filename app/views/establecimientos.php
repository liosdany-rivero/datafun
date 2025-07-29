<?php
include('../templates/header.php');
require_once('../controllers/auth_admin_check.php');
require_once('../controllers/config.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mensajes desde sesión
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Procesar creación/edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_establecimiento'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $mostrar_en_caja = isset($_POST['mostrar_en_caja']) ? 1 : 0;
    $entrada_caja_panaderia = isset($_POST['entrada_caja_panaderia']) ? 1 : 0;
    $salida_caja_panaderia = isset($_POST['salida_caja_panaderia']) ? 1 : 0;

    $res = mysqli_query($conn, "SELECT * FROM establecimientos WHERE codigo = $codigo");

    if (mysqli_num_rows($res) > 0) {
        $sql = "UPDATE establecimientos SET nombre = ?, mostrar_en_caja = ?, entrada_caja_panaderia = ?, salida_caja_panaderia = ? WHERE codigo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siiii", $nombre, $mostrar_en_caja, $entrada_caja_panaderia, $salida_caja_panaderia, $codigo);
        $stmt->execute();
        $stmt->close();
        $success_msg = "✅ Establecimiento actualizado correctamente.";
    } else {
        $sql = "INSERT INTO establecimientos (codigo, nombre, mostrar_en_caja, entrada_caja_panaderia, salida_caja_panaderia) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isiii", $codigo, $nombre, $mostrar_en_caja, $entrada_caja_panaderia, $salida_caja_panaderia);
        $stmt->execute();
        $stmt->close();
        $success_msg = "✅ Establecimiento creado correctamente.";
    }
    unset($_SESSION['csrf_token']);
    $_SESSION['success_msg'] = $success_msg;
    header("Location: establecimientos.php");
    exit();
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_establecimiento'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }
    $codigo = $_POST['codigo'];

    $sql = "DELETE FROM establecimientos WHERE codigo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $codigo);
    if ($stmt->execute()) {
        $success_msg = "✅ Establecimiento eliminado correctamente.";
    } else {
        $error_msg = "⚠️ Error al eliminar: " . $stmt->error;
    }
    $stmt->close();

    unset($_SESSION['csrf_token']);
    $_SESSION['success_msg'] = $success_msg;
    $_SESSION['error_msg'] = $error_msg;
    header("Location: establecimientos.php");
    exit();
}

// Obtener establecimientos
$establecimientos = [];
$result = mysqli_query($conn, "SELECT * FROM establecimientos ORDER BY codigo");
while ($row = mysqli_fetch_assoc($result)) {
    $establecimientos[] = $row;
}
?>

<nav class="sub-menu">
    <ul>
        <li><button onclick="showCreateForm()">Crear</button></li>
    </ul>
</nav>

<div class="form-container">
    <h2>Establecimientos</h2>

    <table class="table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Mostrar en Caja</th>
                <th>Entrada Caja Panadería</th>
                <th>Salida Caja Panadería</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($establecimientos as $row): ?>
                <tr>
                    <td data-label><?= htmlspecialchars($row['codigo']) ?></td>
                    <td data-label><?= htmlspecialchars($row['nombre']) ?></td>
                    <td data-label><?= $row['mostrar_en_caja'] ? '✅' : '❌' ?></td>
                    <td data-label><?= $row['entrada_caja_panaderia'] ? '✅' : '❌' ?></td>
                    <td data-label><?= $row['salida_caja_panaderia'] ? '✅' : '❌' ?></td>
                    <td data-label>
                        <div class="table-action-buttons">
                            <button onclick="showEditForm(
                                '<?= $row['codigo'] ?>', 
                                '<?= htmlspecialchars($row['nombre']) ?>',
                                '<?= $row['mostrar_en_caja'] ?>',
                                '<?= $row['entrada_caja_panaderia'] ?>',
                                '<?= $row['salida_caja_panaderia'] ?>'
                            )">Editar</button>
                            <button onclick="showDeleteForm('<?= $row['codigo'] ?>', '<?= htmlspecialchars($row['nombre']) ?>')">Eliminar</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert-error"><?= $error_msg ?></div>
    <?php endif; ?>

    <!-- Formulario Crear/Editar -->
    <div id="establecimientoFormContainer" class="sub-form" style="display: none;">
        <h3 id="formTitle">Crear/Editar Establecimiento</h3>
        <form method="POST" action="establecimientos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label for="codigo">Código:</label>
            <input type="number" id="codigo" name="codigo" required />

            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" maxlength="25" required />

            <label for="mostrar_en_caja" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="mostrar_en_caja" name="mostrar_en_caja" value="1" />
                Mostrar en Caja Principal
            </label>

            <label for="entrada_caja_panaderia" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="entrada_caja_panaderia" name="entrada_caja_panaderia" value="1" />
                Entrada Caja Panadería
            </label>

            <label for="salida_caja_panaderia" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="salida_caja_panaderia" name="salida_caja_panaderia" value="1" />
                Salida Caja Panadería
            </label>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="save_establecimiento" class="btn-primary">Guardar</button>
                <button type="button" onclick="hideForms()" class="btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Formulario Eliminar -->
    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar establecimiento <span id="deleteNombreDisplay"></span>?</h3>
        <p>Esta acción no se puede deshacer.</p>
        <form method="POST" action="establecimientos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="codigo" id="delete_codigo">
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="delete_establecimiento" class="btn-danger">Confirmar Eliminación</button>
                <button type="button" onclick="hideForms()" class="btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showCreateForm() {
        hideForms();
        document.getElementById('formTitle').textContent = 'Crear Establecimiento';
        document.getElementById('codigo').value = '';
        document.getElementById('nombre').value = '';
        document.getElementById('mostrar_en_caja').checked = false;
        document.getElementById('entrada_caja_panaderia').checked = false;
        document.getElementById('salida_caja_panaderia').checked = false;
        document.getElementById('establecimientoFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function showEditForm(codigo, nombre, mostrar_en_caja, entrada_caja_panaderia, salida_caja_panaderia) {
        hideForms();
        document.getElementById('formTitle').textContent = 'Editar Establecimiento';
        document.getElementById('codigo').value = codigo;
        document.getElementById('nombre').value = nombre;
        document.getElementById('mostrar_en_caja').checked = mostrar_en_caja == '1';
        document.getElementById('entrada_caja_panaderia').checked = entrada_caja_panaderia == '1';
        document.getElementById('salida_caja_panaderia').checked = salida_caja_panaderia == '1';
        document.getElementById('establecimientoFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function showDeleteForm(codigo, nombre) {
        hideForms();
        document.getElementById('delete_codigo').value = codigo;
        document.getElementById('deleteNombreDisplay').textContent = nombre;
        document.getElementById('deleteFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function hideForms() {
        document.getElementById('establecimientoFormContainer').style.display = 'none';
        document.getElementById('deleteFormContainer').style.display = 'none';
    }
</script>

<?php include('../templates/footer.php'); ?>