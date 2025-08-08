<?php
// divisiones/index.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

$stmt = $pdo->query("SELECT * FROM divisiones ORDER BY nombre_division");
$divisiones = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Divisiones</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Divisiones</li>
    </ol>
    
    <div class="mb-4">
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Añadir Nueva División</a>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-sitemap me-1"></i>Listado de Divisiones</div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre de la División</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($divisiones as $division): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($division['nombre_division']); ?></td>
                                <td class="text-center">
                                    <a href="edit.php?id=<?php echo $division['id']; ?>" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                    <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta división?');">
                                        <input type="hidden" name="id" value="<?php echo $division['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/header.php'; ?>
