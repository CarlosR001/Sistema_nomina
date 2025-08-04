<?php
// conceptos/index.php - v3.0 (Versión Sincronizada)
// Asegura que el formulario y la tabla estén alineados con la BD.

require_once '../auth.php';
require_login();
require_role('Admin');

$conceptos = $pdo->query("SELECT * FROM ConceptosNomina ORDER BY tipo_concepto, codigo_concepto")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Conceptos de Nómina</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Añadir Nuevo Concepto</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="codigo_concepto" class="form-label">Código del Concepto</label>
                    <input type="text" class="form-control" id="codigo_concepto" name="codigo_concepto" placeholder="Ej: ING-INCENTIVO" required>
                </div>
                <div class="col-md-9 mb-3">
                    <label for="descripcion_publica" class="form-label">Descripción Pública</label>
                    <input type="text" class="form-control" id="descripcion_publica" name="descripcion_publica" placeholder="Ej: Incentivo por Desempeño" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="tipo_concepto" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo_concepto" name="tipo_concepto">
                        <option value="Ingreso">Ingreso</option>
                        <option value="Deducción">Deducción</option>
                        <option value="Base de Cálculo">Base de Cálculo</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="origen_calculo" class="form-label">Origen del Cálculo</label>
                    <select class="form-select" id="origen_calculo" name="origen_calculo">
                        <option value="Novedad" selected>Novedad (Manual)</option>
                        <option value="Formula">Fórmula (Automático)</option>
                        <option value="Fijo">Fijo</option>
                        <option value="Porcentaje">Porcentaje</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="afecta_tss" class="form-label">Afecta TSS</label>
                    <select class="form-select" id="afecta_tss" name="afecta_tss">
                        <option value="1">Sí</option>
                        <option value="0" selected>No</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="afecta_isr" class="form-label">Afecta ISR</label>
                    <select class="form-select" id="afecta_isr" name="afecta_isr">
                        <option value="1">Sí</option>
                        <option value="0" selected>No</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar Nuevo Concepto</button>
        </form>
    </div>
</div>

<h3 class="mt-4">Conceptos Existentes</h3>
<div class="table-responsive">
    <table class="table table-sm table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Afecta TSS</th>
                <th>Afecta ISR</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($conceptos as $concepto): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($concepto['codigo_concepto']); ?></strong></td>
                <td><?php echo htmlspecialchars($concepto['descripcion_publica']); ?></td>
                <td><?php echo htmlspecialchars($concepto['tipo_concepto']); ?></td>
                <td><?php echo htmlspecialchars($concepto['origen_calculo']); ?></td>
                <td><span class="badge bg-<?php echo $concepto['afecta_tss'] ? 'success' : 'secondary'; ?>"><?php echo $concepto['afecta_tss'] ? 'Sí' : 'No'; ?></span></td>
                <td><span class="badge bg-<?php echo $concepto['afecta_isr'] ? 'success' : 'secondary'; ?>"><?php echo $concepto['afecta_isr'] ? 'Sí' : 'No'; ?></span></td>
                <td>
                    <a href="edit.php?id=<?php echo $concepto['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                    <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea eliminar este concepto?');">
                        <input type="hidden" name="id" value="<?php echo $concepto['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
