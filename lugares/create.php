<?php
// lugares/create.php - v2.0 (Jerárquico)

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// Cargar solo los lugares principales para usarlos como posibles padres
$stmt = $pdo->query("SELECT id, nombre_zona_o_muelle FROM lugares WHERE parent_id IS NULL ORDER BY nombre_zona_o_muelle");
$lugares_principales = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Añadir Nuevo Lugar o Sub-Lugar</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Lugares</a></li>
        <li class="breadcrumb-item active">Añadir</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-plus-circle me-1"></i>Datos del Nuevo Registro</div>
        <div class="card-body">
            <form action="store.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="parent_id" class="form-label">Asignar a (Lugar Principal)</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">-- Ninguno (Crear como Lugar Principal) --</option>
                            <?php foreach ($lugares_principales as $lugar): ?>
                                <option value="<?php echo $lugar['id']; ?>"><?php echo htmlspecialchars($lugar['nombre_zona_o_muelle']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Si seleccionas un lugar principal, este nuevo registro será un sub-lugar.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="nombre_zona_o_muelle" class="form-label">Nombre del Lugar/Sub-Lugar</label>
                        <input type="text" class="form-control" id="nombre_zona_o_muelle" name="nombre_zona_o_muelle" required>
                    </div>
                    <div class="col-md-6">
                        <label for="monto_transporte_completo" class="form-label">Monto de Transporte</label>
                        <input type="number" step="0.01" class="form-control" id="monto_transporte_completo" name="monto_transporte_completo" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/header.php'; ?>
