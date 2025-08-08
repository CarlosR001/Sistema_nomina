<?php
// lugares/edit.php - v2.0 (Jerárquico)

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Cargar el lugar a editar
$stmt = $pdo->prepare("SELECT * FROM lugares WHERE id = ?");
$stmt->execute([$id]);
$lugar_a_editar = $stmt->fetch();

if (!$lugar_a_editar) {
    header('Location: index.php?status=error&message=' . urlencode('Registro no encontrado.'));
    exit;
}

// Cargar lugares principales (excluyendo el actual, para evitar que sea padre de sí mismo)
$stmt_padres = $pdo->prepare("SELECT id, nombre_zona_o_muelle FROM lugares WHERE parent_id IS NULL AND id != ? ORDER BY nombre_zona_o_muelle");
$stmt_padres->execute([$id]);
$lugares_principales = $stmt_padres->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar Lugar o Sub-Lugar</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Lugares</a></li>
        <li class="breadcrumb-item active">Editar</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-edit me-1"></i>Datos del Registro</div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($lugar_a_editar['id']); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="parent_id" class="form-label">Asignar a (Lugar Principal)</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">-- Ninguno (Es un Lugar Principal) --</option>
                            <?php foreach ($lugares_principales as $lugar): ?>
                                <option value="<?php echo $lugar['id']; ?>" <?php echo ($lugar_a_editar['parent_id'] == $lugar['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lugar['nombre_zona_o_muelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="nombre_zona_o_muelle" class="form-label">Nombre del Lugar/Sub-Lugar</label>
                        <input type="text" class="form-control" id="nombre_zona_o_muelle" name="nombre_zona_o_muelle" value="<?php echo htmlspecialchars($lugar_a_editar['nombre_zona_o_muelle']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="monto_transporte_completo" class="form-label">Monto de Transporte</label>
                        <input type="number" step="0.01" class="form-control" id="monto_transporte_completo" name="monto_transporte_completo" value="<?php echo htmlspecialchars($lugar_a_editar['monto_transporte_completo']); ?>" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/header.php'; ?>
