<?php
// productos/index.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

// Obtener todos los productos de la base de datos
$stmt = $pdo->query("SELECT * FROM productos ORDER BY nombre_producto");
$productos = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Productos</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Productos</li>
    </ol>
    
    <div class="mb-4">
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Añadir Nuevo Producto</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-box me-1"></i>
            Listado de Productos
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre del Producto</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="2" class="text-center">No hay productos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                                    <td class="text-center">
                                        <a href="edit.php?id=<?php echo $producto['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este producto?');">
                                            <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
