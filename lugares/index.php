<?php
// lugares/index.php - v2.0 (Jerárquico)

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// 1. Obtener todos los lugares y agrupar los sub-lugares bajo sus padres
$stmt = $pdo->query("SELECT * FROM lugares ORDER BY parent_id IS NULL DESC, nombre_zona_o_muelle");
$todos_lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lugares_jerarquizados = [];
// Primero, registrar todos los lugares principales
foreach ($todos_lugares as $lugar) {
    if ($lugar['parent_id'] === null) {
        $lugares_jerarquizados[$lugar['id']] = $lugar;
        $lugares_jerarquizados[$lugar['id']]['sub_lugares'] = [];
    }
}
// Segundo, asignar los sub-lugares a sus padres correspondientes
foreach ($todos_lugares as $lugar) {
    if ($lugar['parent_id'] !== null) {
        if (isset($lugares_jerarquizados[$lugar['parent_id']])) {
            $lugares_jerarquizados[$lugar['parent_id']]['sub_lugares'][] = $lugar;
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Lugares y Sub-Lugares</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Lugares</li>
    </ol>
    
    <div class="mb-4"><a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Añadir Nuevo Lugar/Sub-Lugar</a></div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-map-marked-alt me-1"></i>Listado Jerárquico</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre del Lugar / Sub-Lugar</th>
                            <th>Monto Transporte</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lugares_jerarquizados as $lugar_principal): ?>
                            <tr class="table-primary">
                                <td><strong><?php echo htmlspecialchars($lugar_principal['nombre_zona_o_muelle']); ?> (Principal)</strong></td>
                                <td><strong>RD$ <?php echo number_format($lugar_principal['monto_transporte_completo'], 2); ?></strong></td>
                                <td class="text-center">
                                    <a href="edit.php?id=<?php echo $lugar_principal['id']; ?>" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                    <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('ADVERTENCIA: Se eliminará este lugar principal y todos sus sub-lugares asociados. ¿Continuar?');">
                                        <input type="hidden" name="id" value="<?php echo $lugar_principal['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php if (empty($lugar_principal['sub_lugares'])): ?>
                                <tr>
                                    <td colspan="3" class="ps-4 fst-italic text-muted">No tiene sub-lugares asignados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lugar_principal['sub_lugares'] as $sub_lugar): ?>
                                <tr>
                                    <td class="ps-4"><i class="bi bi-arrow-return-right"></i> <?php echo htmlspecialchars($sub_lugar['nombre_zona_o_muelle']); ?></td>
                                    <td>RD$ <?php echo number_format($sub_lugar['monto_transporte_completo'], 2); ?></td>
                                    <td class="text-center">
                                        <a href="edit.php?id=<?php echo $sub_lugar['id']; ?>" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                        <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este sub-lugar?');">
                                            <input type="hidden" name="id" value="<?php echo $sub_lugar['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
