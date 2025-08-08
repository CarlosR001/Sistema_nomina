<?php
// roles/index.php

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar'); // Solo usuarios con este permiso pueden gestionar roles

$roles = $pdo->query("SELECT * FROM roles ORDER BY nombre_rol")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Roles</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Roles</li>
    </ol>
    
    <div class="alert alert-info">
        Aquí puedes ver los roles del sistema. Haz clic en "Permisos" para editar qué puede hacer cada rol.
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-user-shield me-1"></i>Listado de Roles</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre del Rol</th>
                            <th>Descripción</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $rol): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rol['nombre_rol']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rol['descripcion']); ?></td>
                                <td class="text-center">
                                    <a href="edit.php?id=<?php echo $rol['id']; ?>" class="btn btn-warning btn-sm" title="Editar Permisos">
                                        <i class="bi bi-shield-lock-fill"></i> Gestionar Permisos
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
