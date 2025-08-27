<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: productos.php
 * DESCRIPCIÓN: Gestión de productos del sistema
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_admin_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS

// 2.1 Creación/Actualización de productos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_producto'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos del formulario
    $nombre = trim($_POST['nombre']);
    $um = trim($_POST['um']);

    // Validar campos obligatorios
    if (empty($nombre)) {
        $_SESSION['error_msg'] = "⚠️ El nombre es obligatorio";
        header("Location: productos.php");
        exit();
    }

    if (empty($um)) {
        $_SESSION['error_msg'] = "⚠️ La unidad de medida es obligatoria";
        header("Location: productos.php");
        exit();
    }

    // Verificar si estamos en modo creación o edición
    $is_edit = isset($_POST['edit_mode']) && $_POST['edit_mode'] === 'true';
    $id = $is_edit ? (int)$_POST['id'] : null;

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        if ($is_edit) {
            // MODE EDIT - Usamos UPDATE
            $action = "actualizado";

            $sql = "UPDATE productos SET 
                    nombre = ?, 
                    um = ?
                    WHERE codigo = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssi",
                $nombre,
                $um,
                $id
            );
        } else {
            // MODE CREATE - Insertar sin especificar código (autoincrement)
            $action = "creado";

            $sql = "INSERT INTO productos 
                   (nombre, um) 
                   VALUES (?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ss",
                $nombre,
                $um
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Error al $action producto: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        $_SESSION['success_msg'] = "✅ Producto $action correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: productos.php");
    exit();
}

// 2.2 Eliminación de productos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_producto'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $id = $_POST['id'];

    try {
        // Consulta preparada para eliminación segura
        $sql = "DELETE FROM productos WHERE codigo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "✅ Producto eliminado correctamente.";
        } else {
            $_SESSION['error_msg'] = "⚠️ Error al eliminar: " . $stmt->error;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // Capturar excepción de clave foránea
        if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
            $_SESSION['error_msg'] = "⚠️ No se puede eliminar el producto porque está siendo utilizado en otros registros.";
        } else {
            $_SESSION['error_msg'] = "⚠️ Error al eliminar: " . $e->getMessage();
        }
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: productos.php");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Configuración de paginación
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM productos"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada
$productos = [];
$sql = "SELECT * FROM productos ORDER BY codigo LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $productos[] = $row;
}
ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Productos</h2>

    <!-- Tabla de productos -->
    <table class="table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Unidad de Medida</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $row): ?>
                <tr>
                    <td data-label="Código"><?= htmlspecialchars($row['codigo']) ?></td>
                    <td data-label="Nombre"><?= htmlspecialchars($row['nombre']) ?></td>
                    <td data-label="Unidad de Medida"><?= htmlspecialchars($row['um']) ?></td>

                    <td data-label>
                        <div class="table-action-buttons">
                            <button onclick="showEditForm(
                                '<?= $row['codigo'] ?>', 
                                '<?= htmlspecialchars($row['nombre']) ?>',
                                '<?= htmlspecialchars($row['um']) ?>'
                            )">Editar</button>
                            <button onclick="showDeleteForm('<?= $row['codigo'] ?>', '<?= htmlspecialchars($row['nombre']) ?>')">Eliminar</button>
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

    <!-- Formulario Crear/Editar -->
    <div id="productoFormContainer" class="sub-form" style="display: none;">
        <h3 id="formTitle">Crear/Editar Producto</h3>
        <form method="POST" action="productos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="edit_mode" name="edit_mode" value="false">
            <input type="hidden" id="id" name="id" value="">

            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" maxlength="100" required />

            <label for="um">Unidad de Medida:</label>
            <input type="text" id="um" name="um" maxlength="20" required />

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="save_producto" class="btn-primary">Guardar</button>
                <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Formulario Eliminar -->
    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar producto <span id="deleteNombreDisplay"></span>?</h3>
        <p>Esta acción no se puede deshacer.</p>
        <form method="POST" action="productos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="delete_id">
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="delete_producto" class="btn-danger">Confirmar Eliminación</button>
                <button type="button" onclick="hideForms()" class="btn-danger">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<BR>
<BR>
<BR>

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li><button onclick="showCreateForm()" class="nav-button">+ Nuevo Producto</button></li>
    </ul>
</div>

<!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
<script>
    /**
     * Muestra formulario de creación
     * - Restablece campos
     * - Muestra el contenedor
     * - Hace scroll al final
     */
    function showCreateForm() {
        hideForms();
        document.getElementById('formTitle').textContent = 'Crear Producto';
        document.getElementById('edit_mode').value = 'false';
        document.getElementById('id').value = '';
        resetFormFields();
        document.getElementById('productoFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Muestra formulario de edición con datos existentes
     * @param {number} id - ID del producto
     * @param {string} nombre - Nombre del producto
     * @param {string} um - Unidad de medida
     */
    function showEditForm(id, nombre, um) {
        hideForms();
        document.getElementById('formTitle').textContent = 'Editar Producto';
        document.getElementById('edit_mode').value = 'true';
        document.getElementById('id').value = id;
        document.getElementById('nombre').value = nombre;
        document.getElementById('um').value = um;
        document.getElementById('productoFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Muestra formulario de eliminación con confirmación
     * @param {number} id - ID del producto
     * @param {string} nombre - Nombre a mostrar en confirmación
     */
    function showDeleteForm(id, nombre) {
        hideForms();
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteNombreDisplay').textContent = nombre;
        document.getElementById('deleteFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Reinicia campos del formulario a valores por defecto
     */
    function resetFormFields() {
        const form = document.getElementById('productoFormContainer').querySelector('form');
        if (form) form.reset();
    }

    /**
     * Oculta todos los formularios
     */
    function hideForms() {
        document.getElementById('productoFormContainer').style.display = 'none';
        document.getElementById('deleteFormContainer').style.display = 'none';
    }

    /**
     * Hace scroll al final de la página
     */
    function scrollToBottom() {
        window.scrollTo(0, document.body.scrollHeight);
    }
</script>

<?php include('../../templates/footer.php'); ?>