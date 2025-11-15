<?php

/**
 * Proyecto: Datafun
 * Desarrollador: liosdany-rivero (GitHub)
 * Fecha: Noviembre 2025
 */

//================================================================================================
// 1. Configuración Inicial y Seguridad
//================================================================================================

// 1.1. Inicialización del Buffer de Salida
ob_start();

// 1.2. Inclusión de Archivos Esenciales
include('../../templates/header.php');
require_once('../../controllers/auth_admin_check.php');
require_once('../../controllers/config.php');

//================================================================================================
// 2. Procesamiento de Eliminación de Bloqueos (Método POST)
//================================================================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_block'])) {


    // 2.1. Validación de Token CSRF 
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido"); // Detiene ejecución si hay discrepancia
    }

    // 2.2. Obtención de ID del Bloqueo a Eliminar
    $block_id = $_POST['block_id'];

    // 2.3. Consulta SQL Preparada - Previene Inyección SQL
    $sql = "DELETE FROM ips_bloqueadas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $block_id);

    // 2.4. Ejecución y Manejo de Resultados
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "✅ IP eliminada correctamente del bloqueo";
        //TODO: Registrar en log de auditoría (mejora futura)
    } else {
        $_SESSION['error_msg'] = "⚠️ Error al eliminar el bloqueo: " . $stmt->error;
        //TODO: Registrar en log de auditoría (mejora futura)
    }
    $stmt->close();

    // 2.5. Limpieza de Seguridad
    unset($_SESSION['csrf_token']);

    // 2.6. Redirección POST-REDIRECT-GET para Evitar Reenvíos
    header("Location: ips_bloqueadas.php");
    exit();
}

//================================================================================================
// 3. Generación de Token CSRF
//================================================================================================

// 3.1. Creación de Token Único por Sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

//================================================================================================
// 4. Consulta de IPs Bloqueadas
//================================================================================================

// 4.1. Inicialización del Array de Resultados
$blocked_ips = [];

// 4.2. Consulta SQL para Obtener IPs Bloqueadas
$sql = "SELECT b.id, b.direccion_ip, b.intentos, b.bloqueado_en, 
               COALESCE(u.username, 'Sistema') as blocked_by_name 
        FROM ips_bloqueadas b
        LEFT JOIN users u ON b.bloqueado_por = u.id
        ORDER BY b.bloqueado_en DESC";

// 4.3. Ejecución y Procesamiento de Resultados
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $blocked_ips[] = $row;
    }
}

// 4.4. Envío del Buffer de Salida
ob_end_flush();
?>

<!--================================================================================================
    5. Interfaz de Usuario - Gestión de IPs Bloqueadas
================================================================================================-->

<div class="form-container">
    <h2>IPs Bloqueadas</h2>

    <!-- 5.1. Tabla Principal de IPs Bloqueadas -->
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
                    <td data-label="IP"><?= htmlspecialchars($ip['direccion_ip']) ?></td>
                    <td data-label="Intentos"><?= htmlspecialchars($ip['intentos']) ?></td>
                    <td data-label="Bloqueada el"><?= htmlspecialchars($ip['bloqueado_en']) ?></td>
                    <td data-label="Bloqueada por"><?= htmlspecialchars($ip['blocked_by_name'] ?? 'Sistema') ?></td>
                    <td data-label="Acciones">
                        <div class="table-action-buttons">
                            <button onclick="showDeleteForm(<?= $ip['id'] ?>, '<?= htmlspecialchars($ip['direccion_ip']) ?>')">
                                Eliminar Bloqueo
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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
        5B. Formulario de Confirmación de Eliminación
    ================================================================================================-->

    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar bloqueo para <span id="deleteIpDisplay"></span>?</h3>
        <p>Esta acción permitirá que la IP pueda intentar iniciar sesión nuevamente.</p>
        <form method="POST" action="ips_bloqueadas.php">
            <!-- Campo oculto con token CSRF -->
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <!-- Campo oculto para ID del bloqueo -->
            <input type="hidden" name="block_id" id="delete_block_id">
            <!-- Botones de acción -->
            <button type="submit" name="delete_block" class="delete-btn">Confirmar Eliminación</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>
</div>

<!--================================================================================================
    6. Comportamiento de Interfaz - JavaScript
================================================================================================-->

<script>
    /**
     * 6.1. Muestra formulario de eliminación de bloqueo
     */

    function showDeleteForm(blockId, ipAddress) {
        hideForms();
        document.getElementById('delete_block_id').value = blockId;
        document.getElementById('deleteIpDisplay').textContent = ipAddress;
        document.getElementById('deleteFormContainer').style.display = 'block';
        scrollToBottom();
    }
</script>

<?php
//================================================================================================
// 7. Inclusión de Footer
//================================================================================================
include('../../templates/footer.php');
?>