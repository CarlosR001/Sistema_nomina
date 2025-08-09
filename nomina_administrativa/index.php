<?php
// nomina_administrativa/index.php - v2.0 (con Estado de Procesamiento)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// Función para obtener el nombre del mes en español
function get_month_name_es($month_number) {
    $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    return $meses[(int)$month_number] ?? 'Mes Desconocido';
}
// --- Lógica de Selección de Período ---
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');
$form_submitted = isset($_GET['month']); // <-- La nueva variable "bandera"

// Inicializar variables para evitar errores
$q1_procesada = null;
$q2_procesada = null;
$q1_inicio = null;
$q1_fin = null;
$q2_inicio = null;
$q2_fin = null;
$selected_date = null; // Se define como null por defecto

// Ejecutar la lógica solo si el usuario ha presionado "Consultar Estado"
if ($form_submitted) {
    $selected_date = $selected_year . '-' . $selected_month . '-01';
    $days_in_month = date('t', strtotime($selected_date));

    // Calcular períodos de quincena
    $q1_inicio = $selected_year . '-' . $selected_month . '-01';
    $q1_fin = $selected_year . '-' . $selected_month . '-15';
    $q2_inicio = $selected_year . '-' . $selected_month . '-16';
    $q2_fin = $selected_year . '-' . $selected_month . '-' . $days_in_month;

    // Verificar estado de la primera quincena
    $stmt_q1 = $pdo->prepare("SELECT id FROM nominasprocesadas WHERE tipo_nomina_procesada = 'Administrativa' AND periodo_inicio = ? AND periodo_fin = ?");
    $stmt_q1->execute([$q1_inicio, $q1_fin]);
    $q1_procesada = $stmt_q1->fetch(PDO::FETCH_ASSOC);

    // Verificar estado de la segunda quincena
    $stmt_q2 = $pdo->prepare("SELECT id FROM nominasprocesadas WHERE tipo_nomina_procesada = 'Administrativa' AND periodo_inicio = ? AND periodo_fin = ?");
    $stmt_q2->execute([$q2_inicio, $q2_fin]);
    $q2_procesada = $stmt_q2->fetch(PDO::FETCH_ASSOC);
}

// 1. Obtener todas las nóminas administrativas que ya fueron procesadas
$stmt_processed = $pdo->query("
    SELECT id, periodo_inicio, periodo_fin
    FROM NominasProcesadas
    WHERE tipo_nomina_procesada = 'Administrativa'
");
$processed_payrolls_raw = $stmt_processed->fetchAll(PDO::FETCH_ASSOC);

// 2. Crear un array para buscar eficientemente si una quincena fue procesada.
// La clave del array será 'AÑO-MES-QUINCENA', por ejemplo: "2024-08-1"
$processed_lookup = [];
foreach ($processed_payrolls_raw as $p) {
    // Determina si es la 1ra o 2da quincena basándose en el día de finalización
    $quincena = (int)date('d', strtotime($p['periodo_fin'])) <= 15 ? 1 : 2;
    $key = date('Y-m', strtotime($p['periodo_fin'])) . '-' . $quincena;
    $processed_lookup[$key] = $p['id']; // Guardamos el ID de la nómina procesada
}

// 3. Determinar el mes y año que el usuario quiere ver.
// Si no se especifica en la URL, se usa el mes y año actual.
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// 4. Calcular los datos para las dos quincenas del mes seleccionado.
// Primera Quincena
$q1_inicio = date('Y-m-d', mktime(0, 0, 0, $selected_month, 1, $selected_year));
$q1_fin = date('Y-m-d', mktime(0, 0, 0, $selected_month, 15, $selected_year));
$q1_key = "$selected_year-" . str_pad($selected_month, 2, '0', STR_PAD_LEFT) . "-1";
$q1_id = $processed_lookup[$q1_key] ?? null; // Buscamos si ya fue procesada. Si no, es null.

// Segunda Quincena
$q2_inicio = date('Y-m-d', mktime(0, 0, 0, $selected_month, 16, $selected_year));
$q2_fin = date('Y-m-t', mktime(0, 0, 0, $selected_month, 1, $selected_year)); // 't' da el último día del mes
$q2_key = "$selected_year-" . str_pad($selected_month, 2, '0', STR_PAD_LEFT) . "-2";
$q2_id = $processed_lookup[$q2_key] ?? null; // Buscamos si ya fue procesada.

require_once '../includes/header.php';
?>

<h1 class="mb-4">Procesar Nómina Administrativa</h1>

<!-- Formulario para seleccionar Mes y Año -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-calendar-month"></i> Seleccionar Período</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="month" class="form-label">Mes</label>
                <select id="month" name="month" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i == $selected_month ? 'selected' : ''; ?>>
                            <?php echo ucfirst(strftime('%B', mktime(0, 0, 0, $i, 1))); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="year" class="form-label">Año</label>
                <select id="year" name="year" class="form-select">
                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Consultar Estado</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Estado de las Quincenas -->
<?php if ($form_submitted): ?>
<div class="card">
<div class="card-header">
             <i class="fas fa-calendar-alt me-1"></i>
             <?php
                $fecha_titulo = new DateTime($selected_date);
                $nombre_mes = get_month_name_es($fecha_titulo->format('n'));
                $anio = $fecha_titulo->format('Y');
                echo "Estado para " . ucfirst($nombre_mes) . ' de ' . $anio;
             ?>
         </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th>Quincena</th>
                    <th>Período</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center" style="width: 200px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Fila para la Primera Quincena -->
                <tr>
                    <td><strong>1ra Quincena</strong></td>
                    <td><?php echo $q1_inicio . ' al ' . $q1_fin; ?></td>
                    <td class="text-center align-middle">
                        <?php if ($q1_id): ?>
                            <span class="badge bg-success">Procesada</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($q1_id): ?>
                            <a href="<?php echo BASE_URL; ?>payroll/show.php?id=<?php echo $q1_id; ?>" class="btn btn-sm btn-info">Ver / Recalcular</a>
                        <?php else: ?>
                            <form action="procesar_nomina_admin.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea procesar la 1ra quincena?');">
                                <input type="hidden" name="fecha_inicio" value="<?php echo $q1_inicio; ?>">
                                <input type="hidden" name="fecha_fin" value="<?php echo $q1_fin; ?>">
                                <input type="hidden" name="quincena" value="1">
                                <button type="submit" class="btn btn-sm btn-primary">Procesar Nómina</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Fila para la Segunda Quincena -->
                <tr>
                    <td><strong>2da Quincena</strong></td>
                    <td><?php echo $q2_inicio . ' al ' . $q2_fin; ?></td>
                    <td class="text-center align-middle">
                        <?php if ($q2_id): ?>
                            <span class="badge bg-success">Procesada</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($q2_id): ?>
                            <a href="<?php echo BASE_URL; ?>payroll/show.php?id=<?php echo $q2_id; ?>" class="btn btn-sm btn-info">Ver / Recalcular</a>
                        <?php else: ?>
                            <form action="procesar_nomina_admin.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea procesar la 2da quincena?');">
                                <input type="hidden" name="fecha_inicio" value="<?php echo $q2_inicio; ?>">
                                <input type="hidden" name="fecha_fin" value="<?php echo $q2_fin; ?>">
                                <input type="hidden" name="quincena" value="2">
                                <button type="submit" class="btn btn-sm btn-primary">Procesar Nómina</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
