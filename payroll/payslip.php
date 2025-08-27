<?php
// payroll/payslip.php - v2.2 (Corrección con LEFT JOIN para visibilidad total)

require_once '../auth.php';
require_login();
require_once '../config/init.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Autorización y obtención de IDs
if (!isset($_GET['nomina_id']) || !is_numeric($_GET['nomina_id']) || !isset($_GET['contrato_id']) || !is_numeric($_GET['contrato_id'])) {
    header('Location: ' . BASE_URL . 'index.php?status=error&message=Faltan%20par%C3%A1metros.');
    exit();
}
$id_nomina = $_GET['nomina_id'];
$id_contrato = $_GET['contrato_id'];

$stmt_get_employee_id = $pdo->prepare("SELECT id_empleado FROM contratos WHERE id = ?");
$stmt_get_employee_id->execute([$id_contrato]);
$empleado_id_del_contrato = $stmt_get_employee_id->fetchColumn();

$user_empleado_id = $_SESSION['user_id_empleado'] ?? null;
if (!has_permission('nomina.procesar') && $user_empleado_id != $empleado_id_del_contrato) {
    header('HTTP/1.0 403 Forbidden');
    die('Acceso denegado. No tienes permiso para ver este volante de pago.');
}

// 2. Obtención de Datos de la Nómina (Consulta CORREGIDA con LEFT JOIN)
$stmt_empleado = $pdo->prepare("SELECT e.nombres, e.primer_apellido, e.segundo_apellido, e.cedula, e.email_personal, p.nombre_posicion FROM contratos c JOIN empleados e ON c.id_empleado = e.id JOIN posiciones p ON c.id_posicion = p.id WHERE c.id = ?");
$stmt_empleado->execute([$id_contrato]);
$empleado = $stmt_empleado->fetch(PDO::FETCH_ASSOC);

$stmt_nomina = $pdo->prepare("SELECT * FROM nominasprocesadas WHERE id = ?");
$stmt_nomina->execute([$id_nomina]);
$nomina = $stmt_nomina->fetch(PDO::FETCH_ASSOC);

if (!$empleado || !$nomina) {
    header('Location: ' . BASE_URL . 'index.php?status=error&message=Datos%20no%20encontrados.');
    exit();
}

// CORRECCIÓN CLAVE: Se cambia a LEFT JOIN para asegurar que todos los detalles se muestren.
$detalles_stmt = $pdo->prepare(
    "SELECT nd.*, cn.incluir_en_volante 
     FROM nominadetalle nd
     LEFT JOIN conceptosnomina cn ON nd.codigo_concepto = cn.codigo_concepto
     WHERE nd.id_nomina_procesada = ? AND nd.id_contrato = ? 
     ORDER BY nd.tipo_concepto DESC, nd.codigo_concepto"
);
$detalles_stmt->execute([$id_nomina, $id_contrato]);
$detalles = $detalles_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- INICIO: Lógica v3 (CORRECTA) para Calcular ISR Mensual Proyectado ---
$isr_mensual_proyectado_para_vista = 0;
if (has_permission('nomina.procesar')) {
    $id_contrato_actual = $payslip['id_contrato'];
    $anio = date('Y', strtotime($nomina['periodo_fin']));

    // 1. Obtener el salario mensual fijo del empleado
    $stmt_contrato = $pdo->prepare("SELECT salario_mensual_bruto FROM contratos WHERE id = ?");
    $stmt_contrato->execute([$id_contrato_actual]);
    $salario_mensual_bruto = (float)$stmt_contrato->fetchColumn();

    if ($salario_mensual_bruto > 0) {
        // 2. Cargar configuraciones y escala de ISR
        $configs_db_vista = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
        $escala_isr_vista = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio} ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Calcular la base imponible anual PROYECTADA solo con el salario fijo (Lógica del CSV)
        $porcentaje_sfs = (float)($configs_db_vista['TSS_PORCENTAJE_SFS'] ?? 0.0304);
        $porcentaje_afp = (float)($configs_db_vista['TSS_PORCENTAJE_AFP'] ?? 0.0287);
        $tope_sfs_mensual = (float)($configs_db_vista['TSS_TOPE_SFS'] ?? 0);
        $tope_afp_mensual = (float)($configs_db_vista['TSS_TOPE_AFP'] ?? 0);

        $sfs_mensual_fijo = round(min($salario_mensual_bruto, $tope_sfs_mensual) * $porcentaje_sfs, 2);
        $afp_mensual_fijo = round(min($salario_mensual_bruto, $tope_afp_mensual) * $porcentaje_afp, 2);
        $base_isr_mensual_fija = $salario_mensual_bruto - ($sfs_mensual_fijo + $afp_mensual_fijo);
        $ingreso_anual_proyectado = $base_isr_mensual_fija * 12;

        // 4. Calcular el ISR Anual
        $isr_anual_total = 0;
        if (count($escala_isr_vista) === 4) {
            $tramo1 = (float)$escala_isr_vista[0]['hasta_monto_anual']; $tramo2 = (float)$escala_isr_vista[1]['hasta_monto_anual']; $tramo3 = (float)$escala_isr_vista[2]['hasta_monto_anual'];
            if ($ingreso_anual_proyectado > $tramo3) { $excedente = $ingreso_anual_proyectado - $tramo3; $tasa = (float)$escala_isr_vista[3]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr_vista[3]['monto_fijo_adicional']; $isr_anual_total = $fijo + ($excedente * $tasa); } 
            elseif ($ingreso_anual_proyectado > $tramo2) { $excedente = $ingreso_anual_proyectado - $tramo2; $tasa = (float)$escala_isr_vista[2]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr_vista[2]['monto_fijo_adicional']; $isr_anual_total = $fijo + ($excedente * $tasa); } 
            elseif ($ingreso_anual_proyectado > $tramo1) { $excedente = $ingreso_anual_proyectado - $tramo1; $tasa = (float)$escala_isr_vista[1]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr_vista[1]['monto_fijo_adicional']; $isr_anual_total = $fijo + ($excedente * $tasa); }
        }
        
        // 5. El ISR mensual proyectado es el anual dividido entre 12
        $isr_mensual_proyectado_para_vista = round($isr_anual_total / 12, 2);
    }
}
// --- FIN: Lógica v3 ---


// Procesamiento de detalles para la visualización
$ingresos = [];
$deducciones = [];
$bases_calculo = [];
$total_ingresos = 0;
$total_deducciones = 0;

foreach ($detalles as $detalle) {
    $monto = (float)$detalle['monto_resultado'];
    // Si no se encuentra el concepto en la tabla maestra, por defecto se incluye para contabilidad pero no para el empleado.
    if (!isset($detalle['incluir_en_volante'])) {
        $detalle['incluir_en_volante'] = 0; // No se envía en el correo.
    }

    switch ($detalle['tipo_concepto']) {
        case 'Ingreso':
            $ingresos[] = $detalle;
            $total_ingresos += $monto;
            break;
        case 'Deducción':
            $deducciones[] = $detalle;
            $total_deducciones += $monto;
            break;
        case 'Base de Cálculo':
            $bases_calculo[] = $detalle;
            break;
    }
}
$neto_pagar = $total_ingresos - $total_deducciones;

// 3. Lógica de Envío de Correo (sin cambios, usa la plantilla)
$email_status = '';
$email_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    if (empty($empleado['email_personal'])) {
        $email_status = 'error';
        $email_message = 'El empleado no tiene una dirección de correo electrónico registrada.';
    } else {
        ob_start();
        include __DIR__ . '/payslip_template.php'; 
        $payslip_html = ob_get_clean();

        $configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $configs_db['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $configs_db['SMTP_USER'];
            $mail->Password = $configs_db['SMTP_PASS'];
            $mail->SMTPSecure = $configs_db['SMTP_SECURE'];
            $mail->Port = $configs_db['SMTP_PORT'];
            $mail->setFrom($configs_db['SMTP_USER'], $configs_db['COMPANY_NAME']);
            $mail->addAddress($empleado['email_personal'], $empleado['nombres'] . ' ' . $empleado['primer_apellido']);
            $mail->isHTML(true);
            $mail->Subject = 'Su Volante de Pago - ' . $configs_db['COMPANY_NAME'];
            $mail->Body    = $payslip_html;
            $mail->CharSet = 'UTF-8';
            $mail->send();
            $email_status = 'success';
            $email_message = 'Volante de pago enviado exitosamente a ' . htmlspecialchars($empleado['email_personal']);
        } catch (Exception $e) {
            $email_status = 'error';
            $email_message = "No se pudo enviar el correo. Error: {$mail->ErrorInfo}";
        }
    }
}

require_once '../includes/header.php';
?>
<!-- INICIO: Estilos para la Vista de Contabilidad -->
<style>
    .accounting-info {
        background-color: #fff8e1; /* Un color amarillo claro */
        border: 1px solid #ffecb3;
        border-radius: 4px;
        padding: 15px;
        margin: 20px 0;
        font-size: 1.1em;
    }
    .accounting-info .info-item {
        display: flex;
        justify-content: space-between;
        color: #6d4c41;
        font-weight: bold;
    }
</style>
<!-- FIN: Estilos -->


<h1 class="mb-4">Desglose de Pago (Vista de Contabilidad)</h1>

<?php if ($email_message): ?>
<div class="alert alert-<?php echo $email_status === 'success' ? 'success' : 'danger'; ?>" role="alert">
    <?php echo $email_message; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-dark text-white">
        Recibo de Nómina - Vista Completa
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h4><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></h4>
                <p class="mb-0"><strong>Cédula:</strong> <?php echo htmlspecialchars($empleado['cedula']); ?></p>
                <p class="mb-0"><strong>Posición:</strong> <?php echo htmlspecialchars($empleado['nombre_posicion']); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0"><strong>ID de Nómina:</strong> <?php echo htmlspecialchars($nomina['id']); ?></p>
                <p class="mb-0"><strong>Período:</strong> <?php echo htmlspecialchars($nomina['periodo_inicio'] . ' al ' . $nomina['periodo_fin']); ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <h5 class="text-info">Bases de Cálculo</h5>
                <table class="table table-sm">
                    <?php if (empty($bases_calculo)): ?>
                        <tr><td class="text-muted">No hay bases de cálculo para mostrar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bases_calculo as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                            <td class="text-end"><strong>$<?php echo number_format($item['monto_resultado'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>

            <div class="col-md-4">
                <h5 class="text-success">Ingresos</h5>
                <table class="table table-sm">
                    <?php foreach ($ingresos as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                        <td class="text-end">$<?php echo number_format($item['monto_resultado'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Ingresos</span>
                    <span>$<?php echo number_format($total_ingresos, 2); ?></span>
                </div>
            </div>

            <div class="col-md-4">
                <h5 class="text-danger">Deducciones</h5>
                <table class="table table-sm">
                     <?php foreach ($deducciones as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                        <td class="text-end">-$<?php echo number_format($item['monto_resultado'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Deducciones</span>
                    <span>-$<?php echo number_format($total_deducciones, 2); ?></span>
                </div>
            </div>
        </div>

        <hr class="my-4">
    
    <?php if (has_permission('nomina.procesar') && $isr_mensual_proyectado_para_vista > 0): ?>
        <div class="accounting-info">
            <div class="info-item">
                <span>ISR Total Estimado del Mes:</span>
                <span>$<?php echo number_format($isr_mensual_proyectado_para_vista, 2); ?></span>
            </div>
        </div>
        <?php endif; ?>
        <div class="text-end">
            <h3>Neto a Pagar: <span class="text-primary">$<?php echo number_format($neto_pagar, 2); ?></span></h3>
        </div>

    </div>
    <div class="card-footer text-center no-print">
        <?php if (has_permission('nomina.procesar')): ?>
            <form method="POST" action="" class="d-inline">
                <button type="submit" name="send_email" class="btn btn-secondary">
                    <i class="fas fa-envelope"></i> Enviar por Correo al Empleado
                </button>
            </form>
        <?php endif; ?>
        <button onclick="window.print();" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimir Vista Completa
        </button>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
