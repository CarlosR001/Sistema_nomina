<?php
// configuracion/index.php

require_once '../auth.php';
require_login();
require_role('Admin');

// Obtener todas las configuraciones globales
$configs = $pdo->query("SELECT clave, valor, descripcion FROM ConfiguracionGlobal ORDER BY clave")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Parámetros Globales del Sistema</h1>

<div class="card">
    <div class="card-header">
        Editar Configuración General
    </div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <?php foreach ($configs as $config): ?>
                <div class="mb-3">
                    <label for="<?php echo htmlspecialchars($config['clave']); ?>" class="form-label">
                        <strong><?php echo htmlspecialchars($config['clave']); ?></strong>
                    </label>
                    <input type="text" class="form-control" 
                           id="<?php echo htmlspecialchars($config['clave']); ?>" 
                           name="configs[<?php echo htmlspecialchars($config['clave']); ?>]" 
                           value="<?php echo htmlspecialchars($config['valor']); ?>">
                    <?php if (!empty($config['descripcion'])): ?>
                        <div class="form-text"><?php echo htmlspecialchars($config['descripcion']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
