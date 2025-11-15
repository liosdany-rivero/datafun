<?php

/**
 * Proyecto: Datafun
 * Desarrollador: liosdany-rivero (GitHub)
 * Fecha: Noviembre 2025
 */

//================================================================================================
// 1. Configuración Inicial y Seguridad
//================================================================================================

ob_start();
include('../../templates/header.php');
require_once('../../controllers/auth_admin_check.php');
require_once('../../controllers/config.php');

//================================================================================================
// 2. Procesamiento de Formularios
//===============================================================================================

// 2.1. Eliminación de Intento Individual
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_attempt'])) {

    // 2.1.1. Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // 2.1.2. Ejecución de Eliminación
    $attempt_id = $_POST['attempt_id'];
    $sql = "DELETE FROM intentos_login WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $attempt_id);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "✅ Intento eliminado correctamente";
    } else {
        $_SESSION['error_msg'] = "⚠️ Error al eliminar el intento: " . $stmt->error;
    }
    $stmt->close();

    // 2.1.3. Limpieza y Redirección
    unset($_SESSION['csrf_token']);
    header("Location: intentos_login.php");
    exit();
}

// 2.2. Eliminación Masiva de Intentos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_all_attempts'])) {

    // 2.2.1. Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // 2.2.2. Ejecución de Eliminación Total
    $sql = "TRUNCATE TABLE intentos_login";
    if ($conn->query($sql)) {
        $_SESSION['success_msg'] = "✅ Todos los intentos fueron eliminados";
    } else {
        $_SESSION['error_msg'] = "⚠️ Error al limpiar la tabla: " . $conn->error;
    }

    // 2.2.3. Limpieza y Redirección
    unset($_SESSION['csrf_token']);
    header("Location: intentos_login.php");
    exit();
}

//================================================================================================
// 3. Generación de Token CSRF
//================================================================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

//================================================================================================
// 4. Obtención de Registros de Intentos de Login
//================================================================================================

$login_attempts = [];
$sql = "SELECT id, direccion_ip, username, hora_intento 
        FROM intentos_login 
        ORDER BY hora_intento DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $login_attempts[] = $row;
    }
}
ob_end_flush();
?>

<!--================================================================================================
    5. Interfaz de Usuario - Gestión de Intentos de Login
================================================================================================-->

<div class="form-container">
    <h2>Registro de Intentos de Login Fallidos</h2>

    <!--================================================================================================
        5A. Sistema de Notificaciones
    ================================================================================================-->
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

    <!--================================================================================================
        5B. Tabla Principal de Registros
    ================================================================================================-->
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
                    <td data-label="IP"><?= htmlspecialchars($attempt['direccion_ip']) ?></td>
                    <td data-label="Usuario"><?= htmlspecialchars($attempt['username'] ?? 'N/A') ?></td>
                    <td data-label="Fecha/Hora"><?= htmlspecialchars($attempt['hora_intento']) ?></td>
                    <td data-label="Acciones">
                        <div class="table-action-buttons">
                            <button onclick="showDeleteForm(<?= $attempt['id'] ?>, '<?= htmlspecialchars($attempt['direccion_ip']) ?>')">
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

    <!--================================================================================================
        5C. Formularios de Eliminación
    ================================================================================================-->

    <!-- 5C.1. Formulario de Eliminación Individual -->
    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar registro de intento fallido para <span id="deleteIpDisplay"></span>?</h3>
        <form method="POST" action="intentos_login.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="attempt_id" id="delete_attempt_id">
            <button type="submit" name="delete_attempt" class="delete-btn">Confirmar Eliminación</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>

    <div id="deleteAllFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar TODOS los registros de intentos fallidos?</h3>
        <p>Esta acción no se puede deshacer y eliminará todos los registros históricos.</p>
        <form method="POST" action="intentos_login.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" name="delete_all_attempts" class="delete-btn">Confirmar Eliminación Total</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>
</div>

<BR>
<BR>
<BR>

<!--================================================================================================
    5D. Barra de Estado y Acciones Adicionales
================================================================================================-->

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li><button onclick="showDeleteAllForm()" class="nav-button">Limpiar Todos los Registros</button></li>
    </ul>
</div>

<!--================================================================================================
    6. Comportamiento de Interfaz - JavaScript
================================================================================================-->

<script>
    /**
     * 6.1. Muestra formulario de eliminación individual
     */
    function showDeleteForm(attemptId, ipAddress) {
        hideForms();
        document.getElementById('delete_attempt_id').value = attemptId;
        document.getElementById('deleteIpDisplay').textContent = ipAddress;
        document.getElementById('deleteFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * 6.2. Muestra formulario de eliminación masiva
     */
    function showDeleteAllForm() {
        hideForms();
        document.getElementById('deleteAllFormContainer').style.display = 'block';
        scrollToBottom();
    }
</script>

<?php include('../../templates/footer.php'); ?>