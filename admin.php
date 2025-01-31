<?php
include 'includes/header.php';
require_once 'db.php';
checkPermission('admin');
?>
<div class="container mt-4">
    <h2>Página de administración</h2>
    <p>¡Bienvenido, administrador! Aquí puede administrar la aplicación.</p>
</div>
<?php include 'includes/footer.php'; ?>
