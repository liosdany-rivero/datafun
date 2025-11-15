<?php

/**
 * Proyecto: Datafun
 * Desarrollador: liosdany-rivero (GitHub)
 * Fecha: Noviembre 2025
 */

include('../../templates/header.php');
?>

<div class="form-container">

  <!-- Mensaje de bienvenida -->
  <!-- Muestra el nombre de usuario y rol almacenados en la sesión. -->
  <h2>
    Bienvenido, <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
  </h2>

  <!-- Mensaje según el rol de usuario -->
  <?php if ($_SESSION['role'] === 'Administrador'): ?>
    <p>Como administrador puedes registrar nuevos usuarios usando el menú superior.</p>
  <?php else: ?>
    <p>Tu sesión está activa.</p>
  <?php endif; ?>
</div>

<?php
include('../../templates/footer.php');
?>