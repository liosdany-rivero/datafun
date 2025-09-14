<?php
// Incluye el archivo de cabecera (header) que probablemente contiene
// la estructura HTML inicial, estilos CSS, scripts JS y posiblemente
// la lógica de autenticación de usuarios.
// La ruta '../../templates/header.php' indica que está dos niveles arriba
// en la estructura de directorios, dentro de la carpeta 'templates'.
include('../../templates/header.php');
?>

<!-- Contenedor principal del formulario o contenido de la página -->
<div class="form-container">
  <!-- Mensaje de bienvenida personalizado -->
  <h2>
    <!-- 
      Muestra el nombre de usuario y rol almacenados en la sesión.
      htmlspecialchars() se usa para prevenir XSS (Cross-Site Scripting)
      escapando caracteres especiales.
    -->
    Bienvenido, <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
  </h2>

  <?php if ($_SESSION['role'] === 'Administrador'): ?>
    <!-- Mensaje específico para usuarios con rol de Administrador -->
    <p>Como administrador puedes registrar nuevos usuarios usando el menú superior.</p>
  <?php else: ?>
    <!-- Mensaje para usuarios que no son administradores -->
    <p>Tu sesión está activa.</p>
  <?php endif; ?>
</div>

<?php
// Incluye el archivo de pie de página (footer) que probablemente contiene
// el cierre de la estructura HTML, scripts finales y posiblemente
// información de copyright o enlaces adicionales.
include('../../templates/footer.php');
?>