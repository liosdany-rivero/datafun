<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: tasas.php
 * DESCRIPCIÓN: Gestión de tasas de cambio 
 * - Administradores: CRUD completo
 * - Otros usuarios: Solo visualización
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Generar token CSRF para protección contra ataques (solo para administradores)
if ($_SESSION['role'] === 'Administrador' && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verificar permisos de escritura para Caja Panadería (centro de costo 728)
$tiene_permiso_editar = false;
$sql_permiso = "SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = 1";
$stmt_permiso = $conn->prepare($sql_permiso);
$stmt_permiso->bind_param("i", $_SESSION['user_id']);
$stmt_permiso->execute();
$result_permiso = $stmt_permiso->get_result();
if ($result_permiso && $result_permiso->num_rows > 0) {
    $row = $result_permiso->fetch_assoc();
    $tiene_permiso_editar = ($row['permiso'] == 'escribir');
}
$stmt_permiso->close();


// SECCIÓN 2: PROCESAMIENTO DE OPERACIONES (solo para administradores)
if ($_SESSION['role'] === 'Administrador' or $tiene_permiso_editar) {
    // 2.1 Procesamiento de eliminación de tasa
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_tasa'])) {
        // Validación CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("Token CSRF inválido");
        }

        $id = intval($_POST['id']);

        try {
            $sql_delete = "DELETE FROM tasas WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $id);
            $stmt_delete->execute();
            $stmt_delete->close();

            $_SESSION['success_msg'] = "✅ Tasa eliminada correctamente.";
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "⚠️ Error al eliminar tasa: " . $e->getMessage();
        }

        // Regenerar token y redirigir
        unset($_SESSION['csrf_token']);
        ob_clean();
        header("Location: tasas.php");
        exit();
    }

    // 2.2 Procesamiento de edición de tasa
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_tasa'])) {
        // Validación CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("Token CSRF inválido");
        }

        $id = intval($_POST['id']);
        $nueva_tasa = floatval($_POST['tasa']);
        $autocalcular = isset($_POST['autocalcular']) ? true : false;

        // Iniciar transacción
        $conn->begin_transaction();

        try {
            // Actualizar la tasa seleccionada
            $sql_update = "UPDATE tasas SET tasa = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("di", $nueva_tasa, $id);
            $stmt_update->execute();
            $stmt_update->close();

            // Si se marcó autocalcular, actualizar tasas posteriores
            if ($autocalcular) {
                // Obtener la fecha de la tasa que estamos editando
                $sql_fecha = "SELECT fecha FROM tasas WHERE id = ?";
                $stmt_fecha = $conn->prepare($sql_fecha);
                $stmt_fecha->bind_param("i", $id);
                $stmt_fecha->execute();
                $result_fecha = $stmt_fecha->get_result();
                $fecha_actual = $result_fecha->fetch_assoc()['fecha'];
                $stmt_fecha->close();

                // Actualizar todas las tasas posteriores
                $sql_update_posteriores = "UPDATE tasas SET tasa = ? WHERE fecha > ?";
                $stmt_update_posteriores = $conn->prepare($sql_update_posteriores);
                $stmt_update_posteriores->bind_param("ds", $nueva_tasa, $fecha_actual);
                $stmt_update_posteriores->execute();
                $stmt_update_posteriores->close();
            }

            // Confirmar transacción
            $conn->commit();
            $_SESSION['success_msg'] = "✅ Tasa actualizada correctamente." . ($autocalcular ? " Se actualizaron también las tasas posteriores." : "");
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_msg'] = "⚠️ Error al actualizar tasa: " . $e->getMessage();
        }

        // Regenerar token y redirigir
        unset($_SESSION['csrf_token']);
        ob_clean();
        header("Location: tasas.php");
        exit();
    }

    // 2.3 Procesamiento de creación de nueva tasa
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_tasa'])) {
        // Validación CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("Token CSRF inválido");
        }

        $fecha = $_POST['fecha'];
        $tasa = floatval($_POST['tasa']);

        try {
            $sql_insert = "INSERT INTO tasas (fecha, tasa) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("sd", $fecha, $tasa);
            $stmt_insert->execute();
            $stmt_insert->close();

            $_SESSION['success_msg'] = "✅ Tasa creada correctamente.";
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "⚠️ Error al crear tasa: " . $e->getMessage();
        }

        // Regenerar token y redirigir
        unset($_SESSION['csrf_token']);
        ob_clean();
        header("Location: tasas.php");
        exit();
    }
}

// SECCIÓN 3: OBTENCIÓN DE DATOS Y AUTOCOMPLETADO DE FECHAS

// Función para completar fechas faltantes
function completarFechasFaltantes($conn)
{
    // Obtener la última fecha registrada
    $sql_ultima_fecha = "SELECT fecha FROM tasas ORDER BY fecha DESC LIMIT 1";
    $result_ultima_fecha = mysqli_query($conn, $sql_ultima_fecha);

    if ($result_ultima_fecha && mysqli_num_rows($result_ultima_fecha) > 0) {
        $ultima_fecha = mysqli_fetch_assoc($result_ultima_fecha)['fecha'];
        $fecha_actual = date('Y-m-d');

        // Si la última fecha es anterior a la fecha actual
        if ($ultima_fecha < $fecha_actual) {
            // Obtener la última tasa registrada
            $sql_ultima_tasa = "SELECT tasa FROM tasas ORDER BY fecha DESC LIMIT 1";
            $result_ultima_tasa = mysqli_query($conn, $sql_ultima_tasa);
            $ultima_tasa = mysqli_fetch_assoc($result_ultima_tasa)['tasa'];

            // Insertar registros para cada día faltante
            $fecha_actual_obj = new DateTime($fecha_actual);
            $ultima_fecha_obj = new DateTime($ultima_fecha);
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($ultima_fecha_obj->add($interval), $interval, $fecha_actual_obj);

            $conn->begin_transaction();
            try {
                foreach ($period as $date) {
                    $fecha = $date->format('Y-m-d');
                    $sql_insert = "INSERT IGNORE INTO tasas (fecha, tasa) VALUES (?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("sd", $fecha, $ultima_tasa);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error al completar fechas faltantes: " . $e->getMessage());
            }
        }
    }
}

// Completar fechas faltantes al cargar la página
completarFechasFaltantes($conn);

// Configuración de paginación
$por_pagina = 15;
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tasas"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada
$tasas = [];
$sql = "SELECT * FROM tasas ORDER BY fecha DESC LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $tasas[] = $row;
}

// Obtener datos de tasa para edición si se solicita
$tasa_editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $sql_editar = "SELECT * FROM tasas WHERE id = ?";
    $stmt_editar = $conn->prepare($sql_editar);
    $stmt_editar->bind_param("i", $id);
    $stmt_editar->execute();
    $result_editar = $stmt_editar->get_result();
    if ($result_editar->num_rows > 0) {
        $tasa_editar = $result_editar->fetch_assoc();
    }
    $stmt_editar->close();
}

ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->
<div class="form-container">
    <h2>Gestión de Tasas</h2>

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

    <!-- Tabla de tasas -->
    <table class="table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tasa</th>
                <?php if ($_SESSION['role'] === 'Administrador'): ?>
                    <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasas as $row): ?>
                <tr>
                    <td data-label="Fecha"><?= htmlspecialchars($row['fecha']) ?></td>
                    <td data-label="Tasa"><?= number_format($row['tasa'], 2) ?></td>
                    <?php if ($_SESSION['role'] === 'Administrador'): ?>
                        <td class="actions-cell">
                            <div class="table-action-buttons" style="display: flex; gap: 8px;">
                                <button onclick="showEditForm(<?= $row['id'] ?>)" class="btn-preview">Editar</button>
                                <form method="POST" action="tasas.php" style="display: flex; flex: 1;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="delete_tasa" value="1">
                                    <button type="submit" class="btn-preview" onclick="return confirm('¿Estás seguro de eliminar esta tasa?')" style="width: 100%;">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    <?php endif; ?>
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

    <?php if ($_SESSION['role'] === 'Administrador'): ?>
        <!-- Formulario para crear nueva tasa -->
        <div id="registerFormContainer" class="sub-form" style="display: none;">
            <h3>Crear Nueva Tasa</h3>
            <form method="POST" action="tasas.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="create_tasa" value="1">

                <div class="input-group">
                    <div class="input-field">
                        <label for="fecha">Fecha:</label>
                        <input type="date" id="fecha" name="fecha" required>
                    </div>
                    <div class="input-field">
                        <label for="tasa">Tasa:</label>
                        <input type="number" id="tasa" name="tasa" step="0.01" min="0" required>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Crear Tasa</button>
                    <button type="button" onclick="hideRegisterForm()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Formulario de edición (oculto inicialmente) -->
        <div id="editFormContainer" class="sub-form" style="display: <?= $tasa_editar ? 'block' : 'none' ?>;">
            <h3>Editar Tasa</h3>
            <form method="POST" action="tasas.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="edit_tasa" value="1">
                <input type="hidden" name="id" value="<?= $tasa_editar['id'] ?? '' ?>">

                <div class="input-group">
                    <div class="input-field">
                        <label for="fecha_edit">Fecha:</label>
                        <input type="date" id="fecha_edit" name="fecha" value="<?= $tasa_editar['fecha'] ?? '' ?>" readonly>
                    </div>
                    <div class="input-field">
                        <label for="tasa_edit">Tasa:</label>
                        <input type="number" id="tasa_edit" name="tasa" step="0.01" min="0" value="<?= $tasa_editar['tasa'] ?? 0 ?>" required>
                    </div>
                </div>

                <div class="checkbox-field">
                    <input type="checkbox" id="autocalcular" name="autocalcular">
                    <label for="autocalcular">Autocalcular tasas posteriores</label>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Guardar cambios</button>
                    <button type="button" onclick="hideEditForm()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($_SESSION['role'] === 'Administrador'): ?>
    <BR>
    <BR>
    <BR>
    <div id="barra-estado">
        <ul class="secondary-nav-menu">
            <li><button onclick="showRegisterForm()" class="nav-button">+ Nueva tasa</button></li>
        </ul>
    </div>
<?php endif; ?>

<!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
<script>
    /**
     * Muestra el formulario de edición para una tasa específica
     * @param {number} id - ID de la tasa a editar
     */
    function showEditForm(id) {
        window.location.href = `tasas.php?editar=${id}`;
    }

    /**
     * Oculta el formulario de edición
     */
    function hideEditForm() {
        window.location.href = "tasas.php";
    }

    /**
     * Muestra el formulario de registro de nueva tasa
     */
    function showRegisterForm() {
        document.getElementById('registerFormContainer').style.display = 'block';
        document.getElementById('registerFormContainer').scrollIntoView({
            behavior: 'smooth'
        });
        // Establecer fecha actual por defecto
        document.getElementById('fecha').valueAsDate = new Date();
    }

    /**
     * Oculta el formulario de registro
     */
    function hideRegisterForm() {
        document.getElementById('registerFormContainer').style.display = 'none';
    }

    // Configuración inicial al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer fecha actual por defecto en el formulario de creación
        if (document.getElementById('fecha')) {
            document.getElementById('fecha').valueAsDate = new Date();
        }

        // Scroll para formulario de edición si está visible
        if (document.getElementById('editFormContainer').style.display === 'block') {
            document.getElementById('editFormContainer').scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
</script>

<?php include('../../templates/footer.php'); ?>