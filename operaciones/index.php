<?php
// operaciones/index.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

$stmt = $pdo->query("SELECT * FROM operaciones ORDER BY nombre_operacion");
$operaciones = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Operaciones</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Operaciones</li>
    </ol>
    
    <div class="mb-4">
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Añadir Nueva Operación</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-cogs me-1"></i>
            Listado de Operaciones
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre de la Operación</th>
                            <th>Descripción</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($operaciones)): ?>
                            <tr>
                                <td colspan="3" class="text-center">No hay operaciones registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($operaciones as $operacion): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($operacion['nombre_operacion']); ?></td>
                                    <td><?php echo htmlspecialchars($operacion['descripcion'] ?? 'N/A'); ?></td>
                                    <td class="text-center">
                                        <a href="edit.php?id=<?php echo $operacion['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
