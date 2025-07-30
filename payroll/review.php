<?php
// payroll/review.php
require_once '../config/init.php';

// --- Verificación de Seguridad y Rol ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
// Solo los Administradores pueden revisar y finalizar la nómina.
if ($_SESSION['rol'] !== 'Administrador') {
    die('Acceso Denegado. No tienes los permisos necesarios para acceder a esta página.');
}
// --- Fin de la verificación ---

require_once '../includes/header.php';

// Buscar nóminas que están listas para ser revisadas y aprobadas.
$nominas_pendientes = $pdo->query("SELECT * FROM NominasProcesadas WHERE estado_nomina = 'Pendiente de Aprobación' ORDER BY fecha_ejecucion DESC")->fetchAll();
?>

<h1 class="mb-4">Revisión y Aprobación Final de Nómina</h1>

<?php
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'finalized') {
        echo '<div class="alert alert-success">Nómina finalizada y aprobada correctamente.</div>';
    } elseif ($_GET['status'] === 'error') {
        echo '<div class="alert alert-danger">Ocurrió un error al procesar la solicitud.</div>';
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
                        <td><?php echo $nomina['id']; ?></td>
                        <td><?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?></td>
                        <td><?php echo htmlspecialchars($nomina['periodo_inicio'] . ' al ' . $nomina['periodo_fin']); ?></td>
                        <td><?php echo htmlspecialchars($nomina['fecha_ejecucion']); ?></td>
                        <td>
                            <a href="show.php?id=<?php echo $nomina['id']; ?>" class="btn btn-sm btn-info">Ver Resumen</a>
                            <a href="finalize.php?id=<?php echo $nomina['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Está seguro? Esta acción finalizará la nómina y ya no podrá ser modificada.');">Aprobar y Finalizar</a>
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