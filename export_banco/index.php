<?php
// export_banco/index.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// Obtener todas las nóminas que ya han sido procesadas y aprobadas/finalizadas
$stmt = $pdo->query("
    SELECT id, tipo_nomina_procesada, periodo_inicio, periodo_fin 
    FROM nominasprocesadas 
    WHERE estado_nomina IN ('Aprobada y Finalizada', 'Pagada')
    ORDER BY periodo_fin DESC
");
$nominas_procesadas = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Exportación para Pago en Banco</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Exportación Banco</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-file-export me-1"></i>
            Seleccione la Nómina a Exportar
        </div>
        <div class="card-body">
            <form action="export.php" method="POST" target="_blank">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nomina_id" class="form-label">Nómina Procesada</label>
                        <select class="form-select" id="nomina_id" name="nomina_id" required>
                            <option value="">Seleccionar una nómina...</option>
                            <?php foreach ($nominas_procesadas as $nomina): ?>
                                <option value="<?php echo $nomina['id']; ?>">
                                    ID <?php echo $nomina['id']; ?>: 
                                    <?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?> 
                                    (<?php echo date('d/m/Y', strtotime($nomina['periodo_inicio'])); ?> - <?php echo date('d/m/Y', strtotime($nomina['periodo_fin'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="descripcion_pago" class="form-label">Descripción para el Banco</label>
                        <input type="text" class="form-control" id="descripcion_pago" name="descripcion_pago" value="Pago de Nomina" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-download"></i> Generar Archivo de Exportación</button>
                </div>
            </form>
        </div>
        <div class="card-footer">
            <small class="text-muted">
                <strong>Formato de Exportación:</strong><br>
                <code>Nro_Cuenta;Nombre_Completo;Tipo_Cuenta(1=Ahorro, 2=Corriente);Monto_Neto;Descripcion_Pago</code>
            </small>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
