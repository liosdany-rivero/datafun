<?php

/**
 * ARCHIVO: permisos.php
 * DESCRIPCIÓN: Gestión de permisos de usuarios sobre establecimientos
 * FUNCIONALIDADES:
 * - Asignación de nuevos permisos (lectura/escritura)
 * - Eliminación de permisos existentes
 * - Listado paginado de permisos
 * - Sistema de notificaciones flotantes
 */

// =============================================
// SECCIÓN 1: CONFIGURACIÓN INICIAL Y SEGURIDAD
// =============================================

// Buffer de salida para evitar problemas con headers
ob_start();

// Incluir archivos necesarios
include('../../templates/header.php');          // Cabecera HTML común
require_once('../../controllers/auth_admin_check.php'); // Verificación de admin
require_once('../../controllers/config.php');   // Configuración de la base de datos

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Token seguro de 32 bytes
}

// =============================================
// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS
// =============================================

/**
 * 2.1 Procesar creación de nuevo permiso
 * Método: POST
 * Campos requeridos: user_id, codigo, permiso
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permiso'])) {
    // Validación CSRF para prevenir ataques
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Obtener datos del formulario
    $user_id = $_POST['user_id'];
    $codigo = $_POST['codigo'];
    $permiso = $_POST['permiso'];

    // Verificar si el permiso ya existe para evitar duplicados
    $check_sql = "SELECT * FROM permisos WHERE user_id = ? AND centro_costo_codigo = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $codigo);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_stmt->close();

    if ($check_result->num_rows > 0) {
        $_SESSION['error_msg'] = "⚠️ Este usuario ya tiene permisos para este establecimiento";
    } else {
        // Insertar nuevo permiso
        $sql = "INSERT INTO permisos (user_id, centro_costo_codigo, permiso) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $user_id, $codigo, $permiso);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "✅ Permiso asignado correctamente.";
        } else {
            $_SESSION['error_msg'] = "⚠️ Error al asignar permiso: " . $stmt->error;
        }
        $stmt->close();
    }

    // Limpiar y redirigir (patrón Post-Redirect-Get)
    unset($_SESSION['csrf_token']);
    header("Location: permisos.php");
    exit();
}

/**
 * 2.2 Procesar eliminación de permiso
 * Método: POST
 * Campos requeridos: user_id, codigo
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_permiso'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Obtener datos del formulario
    $user_id = $_POST['user_id'];
    $codigo = $_POST['codigo'];

    // Eliminar permiso de la base de datos
    $sql = "DELETE FROM permisos WHERE user_id = ? AND centro_costo_codigo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $codigo);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "✅ Permiso eliminado correctamente.";
    } else {
        $_SESSION['error_msg'] = "⚠️ Error al eliminar: " . $stmt->error;
    }

    $stmt->close();
    unset($_SESSION['csrf_token']);
    header("Location: permisos.php");
    exit();
}

// =============================================
// SECCIÓN 3: CONFIGURACIÓN DE PAGINACIÓN
// =============================================

// Configurar paginación (15 registros por página)
$por_pagina = 15;

// Obtener parámetros de filtrado de la URL
$filtro_usuario = isset($_GET['f_usuario']) ? intval($_GET['f_usuario']) : null;
$filtro_establecimiento = isset($_GET['f_establecimiento']) ? intval($_GET['f_establecimiento']) : null;

// =============================================
// SECCIÓN 4: OBTENCIÓN DE DATOS (VERSIÓN CORREGIDA)
// =============================================

// Construir condiciones WHERE según los filtros
$where_conditions = ["(e.Establecimiento = 1 OR e.modulo = 1)"]; // Condición base
$query_params = [];
$types = '';

if ($filtro_usuario) {
    $where_conditions[] = "p.user_id = ?";
    $query_params[] = $filtro_usuario;
    $types .= 'i';
}

if ($filtro_establecimiento) {
    $where_conditions[] = "p.centro_costo_codigo = ?";
    $query_params[] = $filtro_establecimiento;
    $types .= 'i';
}

// Consulta base para obtener permisos
$query_base = "
    SELECT p.user_id, u.username AS usuario, p.centro_costo_codigo, 
           e.nombre AS establecimiento, p.permiso
    FROM permisos p
    JOIN users u ON p.user_id = u.id
    JOIN centros_costo e ON p.centro_costo_codigo = e.codigo
";

// Consulta para conteo total
$count_query = "SELECT COUNT(*) as total FROM permisos p
                JOIN centros_costo e ON p.centro_costo_codigo = e.codigo
                WHERE " . implode(" AND ", $where_conditions);

// Obtener página actual desde la URL (default: 1)
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;



// Consulta para datos paginados
$data_query = $query_base . " WHERE " . implode(" AND ", $where_conditions) .
    " ORDER BY u.username, e.nombre LIMIT ?, ?";
$query_params_paginated = $query_params;
$query_params_paginated[] = $inicio;
$query_params_paginated[] = $por_pagina;
$types_paginated = $types . 'ii';


// Obtener total de registros
$stmt_count = $conn->prepare($count_query);
if (!empty($query_params)) {
    $stmt_count->bind_param($types, ...$query_params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_registros = $count_result->fetch_assoc()['total'];
$stmt_count->close();

// Calcular total de páginas
$total_paginas = max(1, ceil($total_registros / $por_pagina));

// Obtener datos paginados
$permisos = [];
$stmt_data = $conn->prepare($data_query);
$stmt_data->bind_param($types_paginated, ...$query_params_paginated);
$stmt_data->execute();
$result = $stmt_data->get_result();

while ($row = $result->fetch_assoc()) {
    $permisos[] = $row;
}
$stmt_data->close();

// Obtener datos para formularios (resetear punteros)
$usuarios = mysqli_query($conn, "SELECT id, username FROM users ORDER BY username");
$establecimientos = mysqli_query($conn, "SELECT codigo, nombre FROM centros_costo WHERE Establecimiento = 1 OR modulo = 1 ORDER BY nombre");

ob_end_flush();
?>

<!-- ============================================= -->
<!-- SECCIÓN 5: INTERFAZ DE USUARIO (HTML) -->
<!-- ============================================= -->
<!-- Contenedor principal -->
<div class="form-container">
    <h2>Gestión de permisos</h2>

    <!-- Notificación de éxito (verde) -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div id="floatingNotification" class="floating-notification success">
            <?= htmlspecialchars($_SESSION['success_msg']) ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <!-- Notificación de error (rojo) -->
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div id="floatingNotification" class="floating-notification error">
            <?= htmlspecialchars($_SESSION['error_msg']) ?>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- Formulario de filtrado -->
    <div class="filter-container">
        <form method="GET" action="permisos.php" class="filter-form">
            <div class="filter-group">
                <label for="f_usuario">Filtrar por Usuario:</label>
                <select name="f_usuario" id="f_usuario" onchange="applyFilters()">
                    <option value="">-- Todos los usuarios --</option>
                    <?php mysqli_data_seek($usuarios, 0); ?>
                    <?php while ($u = mysqli_fetch_assoc($usuarios)): ?>
                        <option value="<?= $u['id'] ?>" <?= ($filtro_usuario == $u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="f_establecimiento">Filtrar por Establecimiento:</label>
                <select name="f_establecimiento" id="f_establecimiento" onchange="applyFilters()">
                    <option value="">-- Todos los establecimientos --</option>
                    <?php mysqli_data_seek($establecimientos, 0); ?>
                    <?php while ($e = mysqli_fetch_assoc($establecimientos)): ?>
                        <option value="<?= $e['codigo'] ?>" <?= ($filtro_establecimiento == $e['codigo']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Mantener parámetro de página en los filtros -->
            <input type="hidden" name="pagina" value="1">
        </form>
    </div>

    <br>

    <!-- Tabla de permisos -->
    <h2>Permisos de la aplicación</h2>
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
                    <td data-label="Usuario"><?= htmlspecialchars($row['usuario']) ?></td>
                    <td data-label="Establecimiento"><?= htmlspecialchars($row['establecimiento']) ?></td>
                    <td data-label="Permiso"><?= htmlspecialchars($row['permiso']) ?></td>
                    <td data-label="Acciones">
                        <div class="table-action-buttons">
                            <button onclick="showDeleteForm(
                                <?= $row['user_id'] ?>, 
                                <?= $row['centro_costo_codigo'] ?>, 
                                '<?= htmlspecialchars(addslashes($row['usuario'])) ?>', 
                                '<?= htmlspecialchars(addslashes($row['establecimiento'])) ?>'
                            )">
                                Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($permisos)): ?>
                <tr>
                    <td colspan="4" class="text-center">No hay permisos registrados</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <div class="pagination">
        <?php
        function urlConFiltros($pagina)
        {
            $params = ['pagina' => $pagina];
            if (isset($_GET['f_usuario']) && $_GET['f_usuario'] != '') {
                $params['f_usuario'] = $_GET['f_usuario'];
            }
            if (isset($_GET['f_establecimiento']) && $_GET['f_establecimiento'] != '') {
                $params['f_establecimiento'] = $_GET['f_establecimiento'];
            }
            return '?' . http_build_query($params);
        }
        ?>

        <?php if ($pagina_actual > 1): ?>
            <a href="<?= urlConFiltros(1) ?>">&laquo; Primera</a>
            <a href="<?= urlConFiltros($pagina_actual - 1) ?>">&lsaquo; Anterior</a>
        <?php endif; ?>

        <?php
        $inicio_paginas = max(1, $pagina_actual - 2);
        $fin_paginas = min($total_paginas, $pagina_actual + 2);

        if ($inicio_paginas > 1) echo '<span>...</span>';

        for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
            <a href="<?= urlConFiltros($i) ?>" class="<?= $i === $pagina_actual ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor;

        if ($fin_paginas < $total_paginas) echo '<span>...</span>';
        ?>

        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="<?= urlConFiltros($pagina_actual + 1) ?>">Siguiente &rsaquo;</a>
            <a href="<?= urlConFiltros($total_paginas) ?>">Última &raquo;</a>
        <?php endif; ?>
    </div>

    <br><br>

    <!-- Formulario para crear permiso -->
    <div id="createFormContainer" class="sub-form" style="display: none;">
        <h3>Asignar nuevo permiso</h3>
        <form method="POST" action="permisos.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <label for="user_id">Usuario:</label>
            <select id="user_id" name="user_id" required>
                <option value="">-- Seleccione un usuario --</option>
                <?php mysqli_data_seek($usuarios, 0); ?>
                <?php while ($u = mysqli_fetch_assoc($usuarios)): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                <?php endwhile; ?>
            </select>

            <label for="codigo">Establecimiento:</label>
            <select id="codigo" name="codigo" required>
                <option value="">-- Seleccione un establecimiento --</option>
                <?php mysqli_data_seek($establecimientos, 0); ?>
                <?php while ($e = mysqli_fetch_assoc($establecimientos)): ?>
                    <option value="<?= $e['codigo'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                <?php endwhile; ?>
            </select>

            <label for="permiso">Tipo de permiso:</label>
            <select id="permiso" name="permiso" required>
                <option value="">-- Seleccione un permiso --</option>
                <option value="leer">Leer</option>
                <option value="escribir">Escribir</option>
                <option value="tramitar">Tramitar</option>
            </select>

            <button type="submit" name="save_permiso">Guardar</button>
            <button type="button" onclick="hideForms()">Cancelar</button>
        </form>
    </div>

    <!-- Formulario para eliminar permiso -->
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

<br><br><br>
<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li><button onclick="showCreateForm()" class="nav-button">+ Nuevo Permiso</button></li>
        <li><a href="permisos.php" class="nav-button">Limpiar filtros</a></li>
    </ul>
</div>

<script>
    /**
     * Muestra el formulario de eliminación con los datos del permiso
     */
    function showDeleteForm(userId, codigo, usuario, establecimiento) {
        hideForms();
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('delete_codigo').value = codigo;
        document.getElementById('deleteUsuarioDisplay').textContent = usuario;
        document.getElementById('deleteEstablecimientoDisplay').textContent = establecimiento;
        document.getElementById('deleteFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Oculta todos los formularios emergentes
     */
    function hideForms() {
        document.querySelectorAll('.sub-form').forEach(form => {
            form.style.display = 'none';
        });
    }

    /**
     * Desplaza la página al final suavemente
     */
    function scrollToBottom() {
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth'
        });
    }

    /**
     * Muestra el formulario para crear un nuevo permiso
     */
    function showCreateForm() {
        hideForms();
        document.getElementById('createFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Aplica los filtros al cambiar selección
     */
    function applyFilters() {
        // Resetear a página 1 al cambiar filtros
        document.querySelector('input[name="pagina"]').value = 1;
        document.querySelector('.filter-form').submit();
    }
</script>

<?php include('../../templates/footer.php'); ?>