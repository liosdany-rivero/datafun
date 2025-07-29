<?php
include('../templates/header.php');
?>

<div class="form-container">
  <h2>Bienvenido, <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)</h2>

  <?php if ($_SESSION['role'] === 'Administrador'): ?>
    <p>Como administrador puedes registrar nuevos usuarios usando el menú superior.</p>
  <?php else: ?>
    <p>Tu sesión está activa. Puedes cerrar sesión desde el menú.</p>
  <?php endif; ?>
</div>

<?php include('../templates/footer.php'); ?>