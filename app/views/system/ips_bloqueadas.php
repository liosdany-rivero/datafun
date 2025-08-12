<?php
// Inicia el buffer de salida
ob_start();
/**
 * SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN INICIAL
 * 
 * Incluye archivos esenciales para el funcionamiento:
 * - header.php: Contiene la estructura HTML base, CSS y JS comunes
 * - auth_admin_check.php: Valida que el usuario sea administrador
 * - config.php: Establece la conexión a la base de datos ($conn)
 * 
 * Nota de mantenimiento: Verificar rutas relativas si se cambia la estructura de directorios
 */
include('../../templates/header.php');
require_once('../../controllers/auth_admin_check.php');
require_once('../../controllers/config.php');

/**
 * SECCIÓN 2: PROCESAMIENTO DE ELIMINACIÓN DE BLOQUEOS (MÉTODO POST)
 * 
 * Flujo cuando se envía el formulario de eliminación:
 * 1. Verifica método POST y existencia del campo delete_block
 * 2. Valida token CSRF para prevenir ataques CSRF
 * 3. Elimina registro de la base de datos
 * 4. Maneja respuesta (éxito/error)
 * 5. Redirige para evitar reenvío del formulario (PRG pattern)
 * 
 * Puntos críticos para mantenimiento:
 * - La tabla 'blocked_ips' debe contener el campo 'id'
 * - El token CSRF debe coincidir con el de sesión
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_block'])) {
    // Validación CSRF - CRÍTICO PARA SEGURIDAD
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido"); // Detiene ejecución si hay discrepancia
    }

    // Obtiene ID del bloqueo a eliminar
    $block_id = $_POST['block_id'];

    // Consulta SQL preparada - Previene inyección SQL
    $sql = "DELETE FROM ips_bloqueadas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $block_id); // 'i' indica tipo integer

    // En la sección de procesamiento POST (alrededor de línea 30)
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "✅ IP eliminada correctamente del bloqueo";
        // Registrar en log de auditoría (mejora futura)
    } else {
        $_SESSION['error_msg'] = "⚠️ Error al eliminar el bloqueo: " . $stmt->error;
        // Registrar error en log (mejora futura)
    }
    $stmt->close();

    // Limpieza de seguridad
    unset($_SESSION['csrf_token']);

    // Redirección POST-REDIRECT-GET para evitar reenvíos
    header("Location: ips_bloqueadas.php");
    exit();
}

/**
 * SECCIÓN 3: GENERACIÓN DE TOKEN CSRF
 * 
 * - Crea un token único por sesión si no existe
 * - Usa random_bytes() para generación criptográfica segura
 * - Almacenado en $_SESSION para validación posterior
 * 
 * Mantenimiento:
 * - No modificar la entropía (32 bytes es suficiente)
 * - Verificar que sessions estén habilitadas en PHP.ini
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * SECCIÓN 4: CONSULTA DE IPS BLOQUEADAS
 * 
 * Estructura de la consulta:
 * - Obtiene datos de blocked_ips
 * - JOIN con tabla users para obtener nombre de quien bloqueó
 * - Ordenado por fecha descendente (más recientes primero)
 * 
 * Notas para mantenimiento:
 * - Verificar nombres de campos si cambia estructura DB
 * - Considerar paginación si hay muchas IPs (mejora futura)
 * - Optimizar con índices en 'blocked_at' para grandes volúmenes
 */
$blocked_ips = []; // Array para almacenar resultados
$sql = "SELECT b.id, b.direccion_ip, b.intentos, b.bloqueado_en, 
               COALESCE(u.username, 'Sistema') as blocked_by_name 
        FROM ips_bloqueadas b
        LEFT JOIN users u ON b.bloqueado_por = u.id
        ORDER BY b.bloqueado_en DESC"; // Orden descendente

$result = $conn->query($sql); // Ejecuta consulta

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $blocked_ips[] = $row; // Almacena cada fila en el array
    }
}
ob_end_flush(); // Envía el buffer al final
?>

<!-- 
    SECCIÓN 5: INTERFAZ DE USUARIO 
    
    Estructura HTML con:
    - Tabla responsive (data-labels para móviles)
    - Formulario oculto de confirmación
    - Mensajes de estado (éxito/error)
    
    Mantenimiento:
    - Verificar clases CSS existen en header.php
    - Mantener consistencia en htmlspecialchars()
    - Actualizar textos según requerimientos
-->
<div class="form-container">
    <h2>IPs Bloqueadas</h2>

    <!-- Tabla principal de IPs -->
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
                    <!-- 
                        Cada celda usa:
                        - data-label para responsive design
                        - htmlspecialchars() para seguridad XSS
                    -->
                    <td data-label="ID"><?= htmlspecialchars($ip['id']) ?></td>
                    <td data-label="IP"><?= htmlspecialchars($ip['direccion_ip']) ?></td>
                    <td data-label="Intentos"><?= htmlspecialchars($ip['intentos']) ?></td>
                    <td data-label="Bloqueada el"><?= htmlspecialchars($ip['bloqueado_en']) ?></td>
                    <td data-label="Bloqueada por"><?= htmlspecialchars($ip['blocked_by_name'] ?? 'Sistema') ?></td>
                    <td data-label="Acciones">
                        <div class="table-action-buttons">
                            <!-- 
                                Botón que llama a función JS con parámetros:
                                - ID del bloqueo
                                - Dirección IP (escapada en JS)
                            -->
                            <button onclick="showDeleteForm(<?= $ip['id'] ?>, '<?= htmlspecialchars($ip['direccion_ip']) ?>')">
                                Eliminar Bloqueo
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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

    <!-- Formulario de confirmación (oculto inicialmente) -->
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

<!-- 
    SECCIÓN 6: JAVASCRIPT PARA INTERACCIÓN
    
    Funciones para:
    - Mostrar/ocultar formulario de confirmación
    - Scroll automático al formulario
    
    Mantenimiento:
    - No modificar los ID de elementos sin actualizar JS
    - Considerar migrar a archivo externo (mejora futura)
-->
<!-- SECCIÓN 6: JAVASCRIPT ESPECÍFICO DE PÁGINA -->
<script>
    /**
     * Muestra formulario de eliminación de bloqueo
     * @param {number} blockId - ID del bloqueo
     * @param {string} ipAddress - Dirección IP a mostrar
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
/**
 * SECCIÓN 7: INCLUSIÓN DE FOOTER
 * 
 * Cierra la estructura HTML y puede incluir:
 * - Scripts adicionales
 * - Elementos comunes de pie de página
 */
include('../../templates/footer.php');
?>