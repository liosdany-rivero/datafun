<?php
include('../templates/header.php');
require_once('../controllers/auth_admin_check.php');
require_once('../controllers/config.php');

// Procesar eliminación de registros
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_attempt'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $attempt_id = $_POST['attempt_id'];
    $sql = "DELETE FROM login_attempts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $attempt_id);

    if ($stmt->execute()) {
        $success_msg = "✅ Intento eliminado correctamente";
    } else {
        $error_msg = "⚠️ Error al eliminar el intento: " . $stmt->error;
    }
    $stmt->close();

    unset($_SESSION['csrf_token']);
    header("Location: login_attempts.php");
    exit();
}

// Procesar eliminación múltiple
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_all_attempts'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $sql = "TRUNCATE TABLE login_attempts";
    if ($conn->query($sql)) {
        $success_msg = "✅ Todos los intentos fueron eliminados";
    } else {
        $error_msg = "⚠️ Error al limpiar la tabla: " . $conn->error;
    }

    unset($_SESSION['csrf_token']);
    header("Location: login_attempts.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener intentos de login fallidos
$login_attempts = [];
$sql = "SELECT id, ip_address, username, attempt_time 
        FROM login_attempts 
        ORDER BY attempt_time DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $login_attempts[] = $row;
    }
}
?>

<div class="form-container">
    <h2>Registro de Intentos de Login Fallidos</h2>


    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Dirección IP</th>
                <th>Usuario</th>
                <th>Fecha/Hora</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($login_attempts as $attempt): ?>
                <tr>
                    <td data-label="ID"><?= htmlspecialchars($attempt['id']) ?></td>
                    <td data-label="IP"><?= htmlspecialchars($attempt['ip_address']) ?></td>
                    <td data-label="Usuario"><?= htmlspecialchars($attempt['username'] ?? 'N/A') ?></td>
                    <td data-label="Fecha/Hora"><?= htmlspecialchars($attempt['attempt_time']) ?></td>
                    <td data-label="Acciones">
                        <div class="table-action-buttons">
                            <button onclick="showDeleteForm(<?= $attempt['id'] ?>, '<?= htmlspecialchars($attempt['ip_address']) ?>')">
                                Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($login_attempts)): ?>
                <tr>
                    <td colspan="5" class="text-center">No hay intentos fallidos registrados</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="action-buttons">
        <button onclick="showDeleteAllForm()" class="delete-btn">Limpiar Todos los Registros</button>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert-success"><?= $success_msg ?></div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert-error"><?= $error_msg ?></div>
    <?php endif; ?>

    <!-- Formulario para eliminar un intento -->
    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar registro de intento fallido para <span id="deleteIpDisplay"></span>?</h3>
        <form method="POST" action="login_attempts.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="attempt_id" id="delete_attempt_id">
            <button type="submit" name="delete_attempt" class="delete-btn">Confirmar Eliminación</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>

    <!-- Formulario para eliminar todos los intentos -->
    <div id="deleteAllFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar TODOS los registros de intentos fallidos?</h3>
        <p>Esta acción no se puede deshacer y eliminará todos los registros históricos.</p>
        <form method="POST" action="login_attempts.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" name="delete_all_attempts" class="delete-btn">Confirmar Eliminación Total</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>
</div>

<script>
    function showDeleteForm(attemptId, ipAddress) {
        hideForms();
        document.getElementById('delete_attempt_id').value = attemptId;
        document.getElementById('deleteIpDisplay').textContent = ipAddress;
        document.getElementById('deleteFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function showDeleteAllForm() {
        hideForms();
        document.getElementById('deleteAllFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function hideForms() {
        document.getElementById('deleteFormContainer').style.display = 'none';
        document.getElementById('deleteAllFormContainer').style.display = 'none';
    }
</script>

<?php include('../templates/footer.php'); ?>