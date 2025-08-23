<?php
// payroll/show.php - v3.0 (Estable, Robusto y Corregido)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=ID de nómina no válido.');
    exit();
}
$id_nomina = $_GET['id'];

// Obtener la información de la cabecera de la nómina
$stmt_nomina = $pdo->prepare("SELECT * FROM nominasprocesadas WHERE id = ?");
$stmt_nomina->execute([$id_nomina]);
$nomina = $stmt_nomina->fetch();

if (!$nomina) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=Nómina no encontrada.');
    exit();
}

// Obtener los detalles agrupados por empleado
$stmt_detalles = $pdo->prepare("
    SELECT c.id as id_contrato, e.cedula, e.nombres, e.primer_apellido, 
    (SELECT SUM(monto_resultado) FROM nominadetalle WHERE id_nomina_procesada = ? AND id_contrato = c.id AND tipo_concepto = 'Ingreso') as total_ingresos,
    (SELECT SUM(monto_resultado) FROM nominadetalle WHERE id_nomina_procesada = ? AND id_contrato = c.id AND tipo_concepto = 'Deducción') as total_deducciones
    FROM nominadetalle nd
    JOIN contratos c ON nd.id_contrato = c.id
    JOIN empleados e ON c.id_empleado = e.id
    WHERE nd.id_nomina_procesada = ?
    GROUP BY c.id, e.cedula, e.nombres, e.primer_apellido
    ORDER BY e.nombres, e.primer_apellido");
$stmt_detalles->execute([$id_nomina, $id_nomina, $id_nomina]);
$detalles = $stmt_detalles->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Resultados de la Nómina</h1>
    <a href="<?php echo BASE_URL; ?>payroll/review.php" class="btn btn-secondary">Volver a Revisión</a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Resumen General</h5>
        <div><span class="badge bg-warning text-dark fs-6"><?php echo htmlspecialchars($nomina['estado_nomina']); ?></span></div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>ID de Nómina:</strong> <?php echo htmlspecialchars($nomina['id']); ?></p>
                <p><strong>Tipo:</strong> <?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Período:</strong> <?php echo htmlspecialchars($nomina['periodo_inicio']) . " al " . htmlspecialchars($nomina['periodo_fin']); ?></p>
                <p><strong>Procesada el:</strong> <?php echo htmlspecialchars($nomina['fecha_ejecucion']); ?></p>
            </div>
            <div class="col-md-4 text-end">
                
            <?php if ($nomina['estado_nomina'] !== 'Aprobada y Finalizada'): ?>
    <?php
        // --- INICIO DE LA LÓGICA CORREGIDA v2.0 ---
        $recalc_script_url = '';
        $form_inputs = ''; // Para añadir inputs específicos si son necesarios

        if ($nomina['tipo_calculo_nomina'] === 'Especial') {
            $recalc_script_url = BASE_URL . 'pagos_especiales/procesar_pago.php';
            
            // 1. Obtener ID del empleado original
            $stmt_empleado = $pdo->prepare("SELECT c.id_empleado FROM contratos c JOIN nominadetalle nd ON c.id = nd.id_contrato WHERE nd.id_nomina_procesada = ? LIMIT 1");
            $stmt_empleado->execute([$id_nomina]);
            $id_empleado_original = $stmt_empleado->fetchColumn();
            
            // 2. Obtener los conceptos y montos originales que fueron ingresados manualmente
            $stmt_conceptos = $pdo->prepare("
                SELECT cn.id as concepto_id, nd.monto_resultado as monto
                FROM nominadetalle nd
                JOIN conceptosnomina cn ON nd.codigo_concepto = cn.codigo_concepto
                WHERE nd.id_nomina_procesada = ? AND cn.origen_calculo != 'Formula'
            ");
            $stmt_conceptos->execute([$id_nomina]);
            $conceptos_originales = $stmt_conceptos->fetchAll();
            
            // 3. Construir los campos ocultos para el formulario
            $form_inputs .= '<input type="hidden" name="id_empleado" value="' . htmlspecialchars($id_empleado_original) . '">';
            $form_inputs .= '<input type="hidden" name="fecha_pago" value="' . htmlspecialchars($nomina['periodo_inicio']) . '">';
            foreach ($conceptos_originales as $concepto) {
                $form_inputs .= '<input type="hidden" name="conceptos[id][]" value="' . htmlspecialchars($concepto['concepto_id']) . '">';
                $form_inputs .= '<input type="hidden" name="conceptos[monto][]" value="' . htmlspecialchars($concepto['monto']) . '">';
            }

        } elseif ($nomina['tipo_nomina_procesada'] === 'Administrativa') {
            $recalc_script_url = BASE_URL . 'nomina_administrativa/procesar_nomina_admin.php';
        } else { // Inspectores
            $recalc_script_url = BASE_URL . 'payroll/process.php';
        }
        // --- FIN DE LA LÓGICA CORREGIDA ---
    ?>
    
    <!-- Formulario de Recálculo (AHORA HABILITADO PARA TODOS) -->
    <form action="<?php echo $recalc_script_url; ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Recalcular? Los datos actuales se borrarán y se volverán a generar.');">
        <input type="hidden" name="id_nomina_a_recalcular" value="<?php echo htmlspecialchars($id_nomina); ?>">
        <?php echo $form_inputs; // Aquí se insertan los datos ocultos del pago especial ?>
        <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-clockwise"></i> Recalcular</button>
    </form>
    
    <!-- Formulario de Finalización -->
    <form action="<?php echo BASE_URL . 'payroll/finalize.php' ?>" method="POST" class="d-inline" onsubmit="return confirm('Este proceso es irreversible. ¿Finalizar y aprobar esta nómina?');">
        <input type="hidden" name="nomina_id" value="<?php echo htmlspecialchars($id_nomina); ?>">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Finalizar</button>
    </form>
<?php else: ?>
    <!-- La nómina está finalizada, mostrar botón de envío -->
    <form action="<?php echo BASE_URL . 'payroll/send_payslips.php' ?>" method="POST" class="d-inline" onsubmit="return confirm('Se enviarán los volantes de pago por correo a todos los empleados de esta nómina que tengan un email registrado. ¿Continuar?');">
        <input type="hidden" name="nomina_id" value="<?php echo htmlspecialchars($id_nomina); ?>">
        <button type="submit" class="btn btn-info"><i class="bi bi-envelope"></i> Enviar Volantes por Correo</button>
    </form>
<?php endif; ?>



            </div>
        </div>
    </div>
</div>

<!-- El resto del HTML para mostrar los detalles de los empleados -->
<h3 class="mt-5">Detalle por Empleado</h3>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>Cédula</th><th>Empleado</th><th class="text-end">Total Ingresos</th><th class="text-end">Total Deducciones</th><th class="text-end">Neto a Pagar</th><th class="text-center">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalles as $detalle): ?>
        <tr>
            <td><?php echo htmlspecialchars($detalle['cedula']); ?></td>
            <td><?php echo htmlspecialchars($detalle['nombres'] . ' ' . $detalle['primer_apellido']); ?></td>
            <td class="text-end">$<?php echo number_format((float)$detalle['total_ingresos'], 2); ?></td>
            <td class="text-end text-danger">-$<?php echo number_format((float)$detalle['total_deducciones'], 2); ?></td>
            <td class="text-end fw-bold">$<?php echo number_format((float)$detalle['total_ingresos'] - (float)$detalle['total_deducciones'], 2); ?></td>
            <td class="text-center">
                <a href="payslip.php?nomina_id=<?php echo $id_nomina; ?>&contrato_id=<?php echo $detalle['id_contrato']; ?>" class="btn btn-sm btn-info" target="_blank">Ver Desglose</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
