<?php
// roles/edit.php

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

$rol_id = $_GET['id'] ?? null;
if (!$rol_id) {
    header('Location: index.php');
    exit;
}

// Cargar datos del rol
$stmt_rol = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt_rol->execute([$rol_id]);
$rol = $stmt_rol->fetch();

if (!$rol) {
    header('Location: index.php?status=error&message=Rol no encontrado.');
    exit;
}

// Cargar todos los permisos disponibles
$permisos = $pdo->query("SELECT * FROM permisos ORDER BY clave_permiso")->fetchAll();

// Cargar los IDs de los permisos que este rol ya tiene asignados
$stmt_rol_perms = $pdo->prepare("SELECT id_permiso FROM rol_permiso WHERE id_rol = ?");
$stmt_rol_perms->execute([$rol_id]);
$rol_perm_ids = $stmt_rol_perms->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar Permisos del Rol: <?php echo htmlspecialchars($rol['nombre_rol']); ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Roles</a></li>
        <li class="breadcrumb-item active">Editar Permisos</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-shield-alt me-1"></i>Asignar Permisos</div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id_rol" value="<?php echo htmlspecialchars($rol['id']); ?>">
                
                <div class="alert alert-light">Selecciona los permisos que este rol podrá ejecutar.</div>

                <div class="row">
                    <?php foreach ($permisos as $permiso): ?>
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="perm_<?php echo $permiso['id']; ?>" name="permisos[]" value="<?php echo $permiso['id']; ?>"
                                    <?php echo in_array($permiso['id'], $rol_perm_ids) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="perm_<?php echo $permiso['id']; ?>">
                                    <strong><?php echo htmlspecialchars($permiso['clave_permiso']); ?></strong>
                                </label>
                                <small class="form-text text-muted d-block">
                                    <?php echo htmlspecialchars($permiso['descripcion']); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Permisos</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
