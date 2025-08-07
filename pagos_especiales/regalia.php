<?php
// pagos_especiales/regalia.php - v1.0 (Interfaz para Cálculo de Regalía)
require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);
$current_year = date('Y');
require_once '../includes/header.php';
?>

<h1 class="mb-4">Cálculo de Regalía Pascual (Salario de Navidad)</h1>

<div class="card">
    <div class="card-header">
        <h5>Paso 1: Seleccionar Año y Previsualizar</h5>
    </div>
    <div class="card-body">
        <p>Seleccione el año para calcular el duodécimo (1/12) de los salarios acumulados por cada empleado.</p>
        
        <form action="preview_regalia.php" method="POST">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="year" class="form-label">Año a Calcular</label>
                    <select name="year" id="year" class="form-select">
                        <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calculator"></i> Previsualizar Cálculo de Regalía
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
