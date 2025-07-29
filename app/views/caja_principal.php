<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Headers para evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include('../templates/header.php');
require_once('../controllers/config.php');

// Validar usuario logueado
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mensajes desde sesión
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// --- SECCIÓN MODIFICADA PARA MANEJO DE CONTADOR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['total_calculado'], $_POST['modo'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }

  $_SESSION['contador_valor'] = floatval($_POST['total_calculado']);
  $_SESSION['contador_modo'] = $_POST['modo'];

  header("Location: caja_principal.php");
  exit();
}

// Mostrar valores del contador desde sesión
if (isset($_SESSION['contador_valor'], $_SESSION['contador_modo'])) {
  $valor = $_SESSION['contador_valor'];
  $modo = $_SESSION['contador_modo'];
  unset($_SESSION['contador_valor'], $_SESSION['contador_modo']);

  echo "<script>
        window.addEventListener('DOMContentLoaded', () => {
            mostrarFormulario('$modo');
            const campo = document.querySelector('[name=\"" . ($modo === 'entrada' ? 'valor_contado_entrada' : 'valor_entregado_salida') . "\"]');
            if (campo) campo.value = '$valor';
        });
    </script>";
}
// --- FIN DE SECCIÓN MODIFICADA ---

// Paginado
$por_pagina = 1;
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM caja_principal"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : ($total_registros > 0 ? $total_paginas : 1);
$inicio = ($pagina_actual - 1) * $por_pagina;

// Cargar los registros paginados
$operaciones = [];
if ($total_registros > 0) {
  $query = "SELECT * FROM caja_principal ORDER BY numero_operacion ASC LIMIT $inicio, $por_pagina";
  $resultados = mysqli_query($conn, $query);
} else {
  $resultados = false;
}

// Función para obtener permiso
function obtenerPermiso($conn, $user_id, $codigo_establecimiento)
{
  $stmt = $conn->prepare("SELECT permiso FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = ?");
  $stmt->bind_param("ii", $user_id, $codigo_establecimiento);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc()['permiso'] ?? '';
}

$user_id = $_SESSION['user_id'];
$permiso = obtenerPermiso($conn, $user_id, 800);
if (!$permiso) {
  echo "⚠️ No tienes acceso al módulo Caja principal.";
  exit();
}

// Obtener saldo anterior
function obtenerSaldoAnterior($conn)
{
  $res = mysqli_query($conn, "SELECT saldo FROM caja_principal ORDER BY numero_operacion DESC LIMIT 1");
  return $res && mysqli_num_rows($res) > 0 ? mysqli_fetch_assoc($res)['saldo'] : 0;
}

// Función para registrar transferencia a panadería en PHP
function registrarTransferenciaPanaderia($conn, $fecha, $valor, $tipo_salida, $observaciones)
{
  $conn->begin_transaction();
  try {
    // 1. Obtener saldo anterior de caja principal
    $saldo_anterior = obtenerSaldoAnterior($conn);
    $nuevo_saldo = $saldo_anterior - $valor;

    // 2. Insertar en caja_principal
    $stmt_caja = $conn->prepare("INSERT INTO caja_principal (fecha_operacion, entrada, salida, saldo) VALUES (?, 0, ?, ?)");
    $stmt_caja->bind_param("sdd", $fecha, $valor, $nuevo_saldo);
    $stmt_caja->execute();
    $numero_op = $stmt_caja->insert_id;
    $stmt_caja->close();

    // 3. Insertar en salidas_caja_principal
    $stmt_salida = $conn->prepare("INSERT INTO salidas_caja_principal (numero_operacion, tipo_salida, establecimiento_codigo, observaciones, tramitado) VALUES (?, ?, 728, ?, 1)");
    $stmt_salida->bind_param("iss", $numero_op, $tipo_salida, $observaciones);
    $stmt_salida->execute();
    $stmt_salida->close();

    // 4. Obtener saldo anterior de panadería
    $res_panaderia = mysqli_query($conn, "SELECT saldo FROM caja_panaderia ORDER BY numero_operacion DESC LIMIT 1");
    $saldo_anterior_panaderia = $res_panaderia && mysqli_num_rows($res_panaderia) > 0 ? mysqli_fetch_assoc($res_panaderia)['saldo'] : 0;
    $nuevo_saldo_panaderia = $saldo_anterior_panaderia + $valor;

    // 5. Insertar en caja_panaderia
    $observaciones_panaderia = "Transferencia desde caja principal. Operación #$numero_op";
    $stmt_panaderia = $conn->prepare("INSERT INTO caja_panaderia (fecha_operacion, entrada, salida, saldo, desde_para, observaciones, tramitado) VALUES (?, ?, 0, ?, 800, ?, 1)");
    $stmt_panaderia->bind_param("sdds", $fecha, $valor, $nuevo_saldo_panaderia, $observaciones_panaderia);
    $stmt_panaderia->execute();
    $operacion_panaderia = $stmt_panaderia->insert_id;
    $stmt_panaderia->close();

    // 6. Registrar la relación
    $stmt_relacion = $conn->prepare("INSERT INTO relacion_cajas (operacion_principal, operacion_panaderia) VALUES (?, ?)");
    $stmt_relacion->bind_param("ii", $numero_op, $operacion_panaderia);
    $stmt_relacion->execute();
    $stmt_relacion->close();

    $conn->commit();
    return true;
  } catch (Exception $e) {
    $conn->rollback();
    error_log("Error en transferencia a panadería: " . $e->getMessage());
    return false;
  }
}

// Función para eliminar última operación en PHP
function eliminarUltimaOperacion($conn)
{
  $conn->begin_transaction();
  try {
    // Obtener el último número de operación
    $res = mysqli_query($conn, "SELECT numero_operacion FROM caja_principal ORDER BY numero_operacion DESC LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) {
      throw new Exception("No hay operaciones para eliminar");
    }
    $ultimo = mysqli_fetch_assoc($res);
    $ultimo_id = $ultimo['numero_operacion'];

    // Verificar si es transferencia a panadería (establecimiento 728)
    $res_transferencia = mysqli_query($conn, "SELECT COUNT(*) as count FROM salidas_caja_principal WHERE numero_operacion = $ultimo_id AND establecimiento_codigo = 728 AND tramitado = 1");
    $es_transferencia = $res_transferencia && mysqli_fetch_assoc($res_transferencia)['count'] > 0;

    if ($es_transferencia) {
      // Obtener operación relacionada en panadería
      $res_relacion = mysqli_query($conn, "SELECT operacion_panaderia FROM relacion_cajas WHERE operacion_principal = $ultimo_id LIMIT 1");
      $operacion_panaderia = $res_relacion && mysqli_num_rows($res_relacion) > 0 ? mysqli_fetch_assoc($res_relacion)['operacion_panaderia'] : null;

      if ($operacion_panaderia) {
        // Eliminar la relación
        mysqli_query($conn, "DELETE FROM relacion_cajas WHERE operacion_principal = $ultimo_id OR operacion_panaderia = $operacion_panaderia");

        // Eliminar operación en panadería
        mysqli_query($conn, "DELETE FROM caja_panaderia WHERE numero_operacion = $operacion_panaderia");
      }
    }

    // Eliminar registros relacionados
    mysqli_query($conn, "DELETE FROM entradas_caja_principal WHERE numero_operacion = $ultimo_id");
    mysqli_query($conn, "DELETE FROM salidas_caja_principal WHERE numero_operacion = $ultimo_id");

    // Eliminar operación principal
    mysqli_query($conn, "DELETE FROM caja_principal WHERE numero_operacion = $ultimo_id");

    $conn->commit();
    return "Operación $ultimo_id eliminada correctamente";
  } catch (Exception $e) {
    $conn->rollback();
    return "Error al eliminar operación: " . $e->getMessage();
  }
}

// Procesar operación combinada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_operacion']) && $permiso === 'escritura') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }

  $conn->begin_transaction();
  try {
    $tipo = $_POST['tipo_operacion']; // entrada o salida
    $fecha = $_POST['fecha_operacion'];
    $codigo = intval($_POST['codigo']);
    $tramitado = 0;

    $saldo_anterior = obtenerSaldoAnterior($conn);

    if ($tipo === 'entrada') {
      $tipo_entrada = $_POST['tipo_detalle'];
      $fecha_doc = $_POST['fecha_documento'];
      $cantidad_estimado = floatval($_POST['cantidad']);
      $valor_contado = floatval($_POST['valor_contado_entrada']);
      $entrada = $valor_contado;
      $salida = 0;
      $saldo_nuevo = $saldo_anterior + $entrada;
      $observaciones = $_POST['observacionesE'];

      // Insertar en caja_principal
      $stmt_caja = $conn->prepare("INSERT INTO caja_principal (fecha_operacion, entrada, salida, saldo) VALUES (?, ?, ?, ?)");
      $stmt_caja->bind_param("sddd", $fecha, $entrada, $salida, $saldo_nuevo);
      $stmt_caja->execute();
      $numero_operacion = $stmt_caja->insert_id;
      $stmt_caja->close();

      // Insertar en entradas_caja_principal
      $stmt_entrada = $conn->prepare("INSERT INTO entradas_caja_principal (numero_operacion, tipo_entrada, establecimiento_codigo, fecha_documento, cantidad, observaciones, tramitado) VALUES (?, ?, ?, ?, ?, ?, ?)");
      $stmt_entrada->bind_param("isisdsi", $numero_operacion, $tipo_entrada, $codigo, $fecha_doc, $cantidad_estimado, $observaciones, $tramitado);
      $stmt_entrada->execute();
      $stmt_entrada->close();
    } elseif ($tipo === 'salida') {
      $tipo_salida = $_POST['tipo_detalle'];
      $valor_entregado = floatval($_POST['valor_entregado_salida']);
      $entrada = 0;
      $salida = $valor_entregado;
      $observaciones = $_POST['observacionesS'];

      // Validar si es para panadería (código 728)
      if ($codigo == 728) {
        $tipos_validos = ['Transferencia'];
        /*      if (!in_array(strtolower($tipo_salida), array_map('strtolower', $tipos_validos))) {
          throw new Exception("Tipo de salida no válido para transferencia a panadería");
        } */
        $tramitado = 1; // Marcamos como tramitado automáticamente

        // Registrar transferencia usando función PHP
        if (registrarTransferenciaPanaderia($conn, $fecha, $valor_entregado, "Transferencia", $observaciones)) {
          $conn->commit();
          $_SESSION['success_msg'] = "✅ Transferencia a panadería registrada correctamente.";
          header("Location: caja_principal.php");
          exit();
        } else {
          throw new Exception("Error al registrar transferencia a panadería");
        }
      } else {
        // Salida normal (no panadería)
        $saldo_nuevo = $saldo_anterior - $salida;

        // Insertar en caja_principal
        $stmt_caja = $conn->prepare("INSERT INTO caja_principal (fecha_operacion, entrada, salida, saldo) VALUES (?, ?, ?, ?)");
        $stmt_caja->bind_param("sddd", $fecha, $entrada, $salida, $saldo_nuevo);
        $stmt_caja->execute();
        $numero_operacion = $stmt_caja->insert_id;
        $stmt_caja->close();

        // Insertar en salidas_caja_principal
        $stmt_salida = $conn->prepare("INSERT INTO salidas_caja_principal (numero_operacion, tipo_salida, establecimiento_codigo, observaciones, tramitado) VALUES (?, ?, ?, ?, ?)");
        $stmt_salida->bind_param("isisi", $numero_operacion, $tipo_salida, $codigo, $observaciones, $tramitado);
        $stmt_salida->execute();
        $stmt_salida->close();
      }
    }

    // Confirmar transacción si todo salió bien
    $conn->commit();
    $_SESSION['success_msg'] = "✅ Operación registrada correctamente.";
  } catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    error_log("Error en operación de caja: " . $e->getMessage());
    $_SESSION['error_msg'] = "❌ Error al registrar la operación: " . $e->getMessage();
  }

  // Regenerar el token CSRF para el próximo formulario
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  header("Location: caja_principal.php");
  exit();
}

// Obtener operaciones
$operaciones = [];
$res = mysqli_query($conn, "SELECT * FROM caja_principal ORDER BY numero_operacion DESC");
while ($row = mysqli_fetch_assoc($res)) {
  $operaciones[] = $row;
}

// Obtener establecimientos
$establecimientos = mysqli_query($conn, "SELECT codigo, nombre FROM establecimientos WHERE mostrar_en_caja = 1");

// Detectar solicitud de detalles en PHP
$detalles_operacion = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ver_detalles'])) {
  $numero = intval($_POST['ver_detalles']);

  // Buscar la operación principal
  $res = mysqli_query($conn, "SELECT * FROM caja_principal WHERE numero_operacion = $numero");
  $base = mysqli_fetch_assoc($res);

  // Buscar en entradas
  $res_e = mysqli_query($conn, "SELECT * FROM entradas_caja_principal WHERE numero_operacion = $numero");
  $entrada = mysqli_fetch_assoc($res_e);

  // Buscar en salidas
  $res_s = mysqli_query($conn, "SELECT * FROM salidas_caja_principal WHERE numero_operacion = $numero");
  $salida = mysqli_fetch_assoc($res_s);

  $detalles_operacion = [
    'numero' => $numero,
    'fecha' => $base['fecha_operacion'],
    'entrada' => $base['entrada'],
    'salida' => $base['salida'],
    'saldo' => $base['saldo'],
    'entrada_detalle' => $entrada,
    'salida_detalle' => $salida
  ];
}

// Procesar eliminación del último registro
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_ultimo'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token CSRF inválido");
  }

  if ($permiso !== 'escritura') {
    $_SESSION['error_msg'] = "⚠️ No tienes permisos para realizar esta acción";
    header("Location: caja_principal.php");
    exit();
  }

  $resultado = eliminarUltimaOperacion($conn);
  if (strpos($resultado, 'Error') === false) {
    $_SESSION['success_msg'] = "✅ $resultado";
  } else {
    $_SESSION['error_msg'] = "❌ $resultado";
  }

  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  header("Location: caja_principal.php");
  exit();
}
?>

<?php if ($permiso === 'escritura'): ?>
  <nav class="sub-menu">
    <ul>
      <li><button onclick="mostrarFormulario('entrada')">Entrada</button></li>
      <li><button onclick="mostrarFormulario('salida')">Salida</button></li>
      <li><button onclick="mostrarEliminarForm()">Eliminar último</button></li>
      <li><button onclick="abrirContadorModal('calculadora')">Calculadora</button></li>
    </ul>
  </nav>
<?php endif; ?>

<div class="form-container">
  <h2>Caja Principal</h2>

  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Fecha</th>
        <th>Entrada</th>
        <th>Salida</th>
        <th>Saldo</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($resultados && mysqli_num_rows($resultados) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($resultados)): ?>
          <tr>
            <td data-label="#"> <?= $row['numero_operacion'] ?> </td>
            <td data-label="Fecha"> <?= date('d/m/Y', strtotime($row['fecha_operacion'])) ?> </td>
            <td data-label="Entrada"> <?= number_format($row['entrada'], 2) ?> </td>
            <td data-label="Salida"> <?= number_format($row['salida'], 2) ?> </td>
            <td data-label="Saldo"> <?= number_format($row['saldo'], 2) ?> </td>

            <td data-label="Acciones" class="table-action-buttons">
              <a href="detalle_operacion_caja_principal.php?numero=<?= $row['numero_operacion'] ?>" class="btn-preview">Detalles</a>
              <?php if ($permiso === 'escritura'): ?>
                <a href="editar_operacion_caja_principal.php?numero=<?= $row['numero_operacion'] ?>" class="btn-preview">Editar</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" class="text-center">No hay operaciones registradas</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

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

  <?php if (!empty($success_msg)): ?>
    <div class="alert-success"><?= $success_msg ?></div>
  <?php endif; ?>

  <?php if (!empty($error_msg)): ?>
    <div class="alert-error"><?= $error_msg ?></div>
  <?php endif; ?>

  <?php if ($permiso === 'escritura'): ?>
    <div id="formularioOperacion" class="sub-form" style="display: none;">
      <h3 id="tituloFormulario">Registrar operación</h3>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="tipo_operacion" id="tipo_operacion">
        <input type="hidden" name="fecha_operacion" value="<?= date('Y-m-d') ?>">

        <label for="codigo">Establecimiento:</label>
        <select name="codigo" required>
          <option value="" selected disabled>-- Seleccione --</option>
          <?php mysqli_data_seek($establecimientos, 0);
          while ($e = mysqli_fetch_assoc($establecimientos)): ?>
            <option value="<?= $e['codigo'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
          <?php endwhile; ?>
        </select>

        <div id="detalle_entrada" style="display:none;">
          <label for="tipo_detalle">Tipo de entrada:</label>
          <select name="tipo_detalle">
            <option value="" selected disabled>-- Seleccione --</option>
            <option value="Dinero de tranferencias">Dinero de tranferencias</option>
            <option value="Dinero de IPV">Dinero de IPV</option>
            <option value="Pago de deudas">Pago de deudas</option>
            <option value="Dinero de Tonito">Dinero de Tonito</option>
            <option value="Otras entradas">Otras entradas</option>
            <option value="Ajustes">Ajustes</option>
          </select>

          <div id="fechaDocumentoGroup" style="display: none;">
            <label for="fecha_documento">Fecha del documento:</label>
            <input type="date" name="fecha_documento">
          </div>

          <label for="cantidad">Cantidad estimada:</label>
          <input type="number" step="1" name="cantidad">

          <div class="input-group">
            <label for="valor_contado_entrada">Valor contado recibido:</label>
            <div class="input-with-button" style="display: flex; gap: 8px;">
              <input type="number" step="1" name="valor_contado_entrada" required style="flex: 1; min-width: 120px;">
              <button type="button" class="btn-contador" data-modo="entrada" style="width: 50%; white-space: nowrap;">🧮 Contador de dinero</button>
            </div>
          </div>
          <label for="observacionesE">Observaciones:</label>
          <textarea name="observacionesE" maxlength="250"></textarea>
          <div id="formularioErrorContainer" class="alert-error" style="display: none;"></div>
          <button type="submit" name="registrar_operacion">Guardar operación</button>
          <button type="button" onclick="ocultarFormulario()">Cancelar</button>
        </div>

        <div id="detalle_salida" style="display:none;">
          <label for="tipo_detalle">Tipo de salida:</label>
          <select name="tipo_detalle" id="tipo_salida_select">
            <option value="" selected disabled>-- Seleccione --</option>
            <option value="Gasto">Gasto</option>
            <option value="Compra de productos">Compra de productos</option>
            <option value="Compra de divisas">Compra de divisas</option>
            <option value="Inversiones">Inversiones</option>
            <option value="Prestamos">Prestamos</option>
            <option value="Utilidad socio">Utilidad socio</option>
            <option value="Dinero para Tonito">Dinero para Tonito</option>
            <option value="Transferencia" id="transferencia_Opcion" style="display:none;">Transferencia</option>
            <option value="Otras salidas">Otras salidas</option>
            <option value="Ajustes">Ajustes</option>
          </select>
          <div class="input-group">
            <label for="valor_entregado_salida">Monto entregado:</label>
            <div class="input-with-button" style="display: flex; gap: 8px;">
              <input type="number" step="1" name="valor_entregado_salida" required style="flex: 1; min-width: 120px;">
              <button type="button" class="btn-contador" data-modo="salida" style="width: 50%; white-space: nowrap;">🧮 Contador de dinero</button>
            </div>
          </div>
          <label for="observacionesS">Observaciones:</label>
          <textarea name="observacionesS" maxlength="250"></textarea>
          <div id="formularioErrorContainer" class="alert-error" style="display: none;"></div>
          <button type="submit" name="registrar_operacion">Guardar operación</button>
          <button type="button" onclick="ocultarFormulario()">Cancelar</button>
        </div>
      </form>
    </div>

    <div id="eliminarFormContainer" class="sub-form" style="display: none;">
      <h3>¿Eliminar último registro de caja?</h3>
      <p>Esta acción no se puede deshacer. Se eliminarán todos los datos relacionados con esta operación.</p>
      <form method="POST" action="caja_principal.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <button type="submit" name="eliminar_ultimo" class="delete-btn">Confirmar Eliminación</button>
        <button type="button" onclick="ocultarEliminarForm()">Cancelar</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Modal para el contador de dinero -->
  <div id="contadorModal" class="modal" style="display:none;">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <iframe id="contadorIframe" src="" frameborder="0" style="width:100%; height:80vh;"></iframe>
    </div>
  </div>

  <script>
    function mostrarFormulario(tipo) {
      const entradaFields = document.querySelectorAll('#detalle_entrada input, #detalle_entrada select');
      const salidaFields = document.querySelectorAll('#detalle_salida input, #detalle_salida select');

      document.getElementById('formularioOperacion').style.display = 'block';
      document.getElementById('tipo_operacion').value = tipo;
      document.getElementById('tituloFormulario').innerText = tipo === 'entrada' ? 'Registrar entrada' : 'Registrar salida';

      if (tipo === 'entrada') {
        document.getElementById('detalle_entrada').style.display = 'block';
        document.getElementById('detalle_salida').style.display = 'none';
        entradaFields.forEach(f => f.disabled = false);
        salidaFields.forEach(f => f.disabled = true);
      } else {
        document.getElementById('detalle_entrada').style.display = 'none';
        document.getElementById('detalle_salida').style.display = 'block';
        entradaFields.forEach(f => f.disabled = true);
        salidaFields.forEach(f => f.disabled = false);
      }

      const alerta = document.getElementById('formularioErrorContainer');
      if (alerta) {
        alerta.style.display = 'none';
        alerta.textContent = '';
      }

      window.scrollTo({
        top: document.body.scrollHeight,
        behavior: 'smooth'
      });
    }

    function ocultarFormulario() {
      const entradaFields = document.querySelectorAll('#detalle_entrada input, #detalle_entrada select');
      const salidaFields = document.querySelectorAll('#detalle_salida input, #detalle_salida select');

      document.getElementById('formularioOperacion').style.display = 'none';
      document.getElementById('tipo_operacion').value = '';
      document.getElementById('tituloFormulario').innerText = 'Registrar operación';
      document.getElementById('detalle_entrada').style.display = 'none';
      document.getElementById('detalle_salida').style.display = 'none';
      entradaFields.forEach(f => f.disabled = true);
      salidaFields.forEach(f => f.disabled = true);
      document.getElementById('eliminarFormContainer').style.display = 'none';

      const alerta = document.getElementById('formularioErrorContainer');
      if (alerta) {
        alerta.style.display = 'none';
        alerta.textContent = '';
      }
    }

    function mostrarEliminarForm() {
      ocultarFormulario();
      document.getElementById('eliminarFormContainer').style.display = 'block';
      window.scrollTo({
        top: document.body.scrollHeight,
        behavior: 'smooth'
      });
    }

    function ocultarEliminarForm() {
      document.getElementById('eliminarFormContainer').style.display = 'none';
    }

    document.querySelector('form').addEventListener('submit', function(e) {
      const tipo = document.getElementById('tipo_operacion').value;
      let valido = true;
      let mensaje = '';

      if (tipo === 'entrada') {
        const cantidadRaw = document.querySelector('[name="cantidad"]').value;
        const cantidad = parseFloat(cantidadRaw);
        const valorContado = parseFloat(document.querySelector('[name="valor_contado_entrada"]').value);

        if (cantidadRaw !== '') {
          if (isNaN(cantidad) || cantidad < 0) {
            valido = false;
            mensaje = '⚠️ La cantidad estimada debe ser mayor o igual a cero.';
          }
        }

        if (isNaN(valorContado) || valorContado <= 0) {
          valido = false;
          mensaje = '⚠️ El valor contado debe ser un número positivo.';
        }
      }

      if (tipo === 'salida') {
        const valorEntregado = parseFloat(document.querySelector('[name="valor_entregado_salida"]').value);
        if (isNaN(valorEntregado) || valorEntregado <= 0) {
          valido = false;
          mensaje = '⚠️ El monto entregado debe ser un número positivo.';
        }
      }

      if (!valido) {
        e.preventDefault();
        mostrarAlerta(mensaje);
      }
    });

    function mostrarAlerta(texto) {
      let alerta = document.getElementById('formularioErrorContainer');
      if (alerta) {
        alerta.style.display = 'block';
        alerta.textContent = texto;
      }
    }

    document.querySelector('[name="tipo_detalle"]').addEventListener('change', function() {
      const seleccionado = this.value;
      const fechaGroup = document.getElementById('fechaDocumentoGroup');
      if (seleccionado === 'Dinero de IPV') {
        fechaGroup.style.display = 'block';
      } else {
        fechaGroup.style.display = 'none';
        const fechaInput = fechaGroup.querySelector('input[name="fecha_documento"]');
        if (fechaInput) fechaInput.value = '';
      }
    });

    function abrirContadorModal(modo) {
      const modal = document.getElementById('contadorModal');
      const iframe = document.getElementById('contadorIframe');

      iframe.src = `contador_dinero.php?modo=${modo}`;
      modal.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function cerrarContadorModal() {
      const modal = document.getElementById('contadorModal');
      const iframe = document.getElementById('contadorIframe');

      modal.style.display = 'none';
      iframe.src = '';
      document.body.style.overflow = 'auto';
    }

    document.querySelectorAll('.btn-contador').forEach(btn => {
      btn.addEventListener('click', function() {
        const modo = this.getAttribute('data-modo');
        abrirContadorModal(modo);
      });
    });

    document.querySelector('.close-modal').addEventListener('click', cerrarContadorModal);

    document.getElementById('contadorModal').addEventListener('click', function(e) {
      if (e.target === this) {
        cerrarContadorModal();
      }
    });

    function recibirValorContador(valor, modo) {
      const campo = document.querySelector(`[name="valor_${modo === 'entrada' ? 'contado_entrada' : 'entregado_salida'}"]`);
      if (campo) {
        campo.value = valor;
      }
      cerrarContadorModal();
    }

    window.addEventListener('message', function(event) {
      if (event.origin !== window.location.origin) return;

      const data = event.data;

      if (data.action === 'aplicarTotalContador') {
        const campo = document.querySelector(
          `[name="${data.modo === 'entrada' ? 'valor_contado_entrada' : 'valor_entregado_salida'}"]`
        );

        if (campo) {
          campo.value = data.total;
        }
      }

      if (data.action === 'cerrarModalContador') {
        cerrarContadorModal();
      }
    });

    document.querySelector('[name="codigo"]').addEventListener('change', function() {
      const codigo = this.value;
      const tipoSalidaSelect = document.getElementById('tipo_salida_select');
      const transferenciaOpcion = document.getElementById('transferencia_Opcion');

      if (codigo == '728') {
        // Mostrar y seleccionar automáticamente Transferencia entre cajas
        transferenciaOpcion.style.display = 'block';
        tipoSalidaSelect.value = 'Transferencia';
        tipoSalidaSelect.disabled = true;
      } else {
        // Ocultar y habilitar el select
        transferenciaOpcion.style.display = 'none';
        tipoSalidaSelect.disabled = false;
        if (tipoSalidaSelect.value === 'Transferencia') {
          tipoSalidaSelect.value = '';
        }
      }
    });
  </script>
  <?php include('../templates/footer.php'); ?>