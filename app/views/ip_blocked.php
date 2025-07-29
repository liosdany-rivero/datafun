<?php
include('../templates/header.php');
require_once('../controllers/auth_admin_check.php');
require_once('../controllers/config.php');

// Procesar eliminación de bloqueo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_block'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $block_id = $_POST['block_id'];
    $sql = "DELETE FROM blocked_ips WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $block_id);

    if ($stmt->execute()) {
        $success_msg = "✅ Bloqueo eliminado correctamente";
    } else {
        $error_msg = "⚠️ Error al eliminar el bloqueo: " . $stmt->error;
    }
    $stmt->close();

    unset($_SESSION['csrf_token']);
    header("Location: ip_blocked.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener IPs bloqueadas con nombre de usuario
$blocked_ips = [];
$sql = "SELECT b.id, b.ip_address, b.attempts, b.blocked_at, u.username as blocked_by_name 
        FROM blocked_ips b
        LEFT JOIN users u ON b.blocked_by = u.id
        ORDER BY b.blocked_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $blocked_ips[] = $row;
    }
}
?>

<div class="form-container">
    <h2>IPs Bloqueadas</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Dirección IP</th>
                <th>Intentos</th>
                <th>Bloqueada el</th>
                <th>Bloqueada por</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($blocked_ips as $ip): ?>
                <tr>
                    <td data-label="ID"><?= htmlspecialchars($ip['id']) ?></td>
                    <td data-label="IP"><?= htmlspecialchars($ip['ip_address']) ?></td>
                    <td data-label="Intentos"><?= htmlspecialchars($ip['attempts']) ?></td>
                    <td data-label="Bloqueada el"><?= htmlspecialchars($ip['blocked_at']) ?></td>
                    <td data-label="Bloqueada por"><?= htmlspecialchars($ip['blocked_by']) ?></td>
                    <td data-label="Acciones">
                        <div class="table-action-buttons">
                            <button onclick="showDeleteForm(<?= $ip['id'] ?>, '<?= htmlspecialchars($ip['ip_address']) ?>')">
                                Eliminar Bloqueo
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

    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar bloqueo para <span id="deleteIpDisplay"></span>?</h3>
        <p>Esta acción permitirá que la IP pueda intentar iniciar sesión nuevamente.</p>
        <form method="POST" action="ip_blocked.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="block_id" id="delete_block_id">
            <button type="submit" name="delete_block" class="delete-btn">Confirmar Eliminación</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>
</div>

<script>
    function showDeleteForm(blockId, ipAddress) {
        hideForms();
        document.getElementById('delete_block_id').value = blockId;
        document.getElementById('deleteIpDisplay').textContent = ipAddress;
        document.getElementById('deleteFormContainer').style.display = 'block';
        window.scrollTo(0, document.body.scrollHeight);
    }

    function hideForms() {
        document.getElementById('deleteFormContainer').style.display = 'none';
    }
</script>

<?php include('../templates/footer.php'); ?>