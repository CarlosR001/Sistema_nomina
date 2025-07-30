<?php
// payroll/review.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden revisar y finalizar la nómina.

// La conexión $pdo ya está disponible a través de auth.php

// Buscar nóminas que están listas para ser revisadas y aprobadas.
$nominas_pendientes = $pdo->query("SELECT * FROM NominasProcesadas WHERE estado_nomina = 'Pendiente de Aprobación' ORDER BY fecha_ejecucion DESC")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Revisión y Aprobación Final de Nómina</h1>

<?php
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'finalized') {
        echo '<div class="alert alert-success">Nómina finalizada y aprobada correctamente.</div>';
    } elseif ($_GET['status'] === 'error' && isset($_GET['message'])) {
        echo '<div class="alert alert-danger">Ocurrió un error al procesar la solicitud: ' . htmlspecialchars($_GET['message']) . '</div>';
    }
}
?>

<div class="card">
    <div class="card-header">
        Nóminas Pendientes de Aprobación
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID Nómina</th>
                    <th>Tipo</th>
                    <th>Período</th>
                    <th>Fecha de Cálculo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($nominas_pendientes)): ?>
                    <?php foreach ($nominas_pendientes as $nomina): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($nomina['id']); ?></td>
                        <td><?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?></td>
                        <td><?php echo htmlspecialchars($nomina['periodo_inicio'] . ' al ' . $nomina['periodo_fin']); ?></td>
                        <td><?php echo htmlspecialchars($nomina['fecha_ejecucion']); ?></td>
                        <td>
                            <a href="show.php?id=<?php echo htmlspecialchars($nomina['id']); ?>" class="btn btn-sm btn-info">Ver Resumen</a>
                            <a href="finalize.php?id=<?php echo htmlspecialchars($nomina['id']); ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Está seguro? Esta acción finalizará la nómina y ya no podrá ser modificada.');">Aprobar y Finalizar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No hay nóminas pendientes de aprobación.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
