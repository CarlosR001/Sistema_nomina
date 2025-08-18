<?php
// reports/payroll_summary.php - Interfaz para el Resumen General de Nómina

require_once '../auth.php';
require_login();
require_permission('nomina.procesar'); 

// Obtener la lista de todas las nóminas procesadas
$stmt = $pdo->query("
    SELECT id, tipo_nomina_procesada, periodo_inicio, periodo_fin, estado_nomina
    FROM nominasprocesadas 
    ORDER BY fecha_ejecucion DESC
");
$nominas_procesadas = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Reporte: Resumen General de Nómina</h1>

<div class="card">
    <div class="card-header">
        <h5>Seleccione una Nómina Procesada</h5>
    </div>
    <div class="card-body">
        <form action="generate_payroll_summary.php" method="POST" target="_blank">
            <div class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label for="id_nomina" class="form-label">Nómina</label>
                    <select class="form-select" id="id_nomina" name="id_nomina" required>
                        <option value="">Seleccione una nómina...</option>
                        <?php foreach ($nominas_procesadas as $nomina): ?>
                            <option value="<?php echo $nomina['id']; ?>">
                                ID: <?php echo $nomina['id']; ?> | 
                                <?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?> | 
                                Período: <?php echo htmlspecialchars($nomina['periodo_inicio'] . ' a ' . $nomina['periodo_fin']); ?> |
                                Estado: <?php echo htmlspecialchars($nomina['estado_nomina']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Generar Reporte</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
