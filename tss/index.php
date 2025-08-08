<?php
require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// Determinar el mes y año actual para pre-seleccionar en el formulario.
$current_year = date('Y');
$current_month = date('m');

require_once '../includes/header.php';
?>

<h1 class="mb-4">Exportar Archivo de Autodeterminación (TSS)</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        Seleccionar Período a Exportar
    </div>
    <div class="card-body">
        <p>Seleccione el mes y el año para generar una previsualización de los datos que se enviarán a la TSS.</p>
        <p><strong>Importante:</strong> Asegúrese de que todas las nóminas (tanto de inspectores como administrativas) de ese mes hayan sido procesadas y finalizadas.</p>
        
        <form action="preview.php" method="POST">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="year" class="form-label">Año</label>
                    <select name="year" id="year" class="form-select">
                        <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php if ($y == $current_year) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="month" class="form-label">Mes</label>
                    <select name="month" id="month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php if ($m == $current_month) echo 'selected'; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-info w-100">
                        <i class="bi bi-eye"></i> Previsualizar Datos
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
