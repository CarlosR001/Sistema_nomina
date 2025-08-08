<?php
// payroll/send_payslips.php - v1.6 (Plantilla de Volante Premium)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

require_once __DIR__ . '/../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nomina_id'])) {
    header('Location: review.php');
    exit();
}

$id_nomina = (int)$_POST['nomina_id'];
$success_count = 0;
$error_count = 0;
$no_email_count = 0;

try {
    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $company_name = $configs_db['COMPANY_NAME'] ?? 'Nombre de Empresa no Configurado';
    
    $stmt_nomina = $pdo->prepare("SELECT * FROM NominasProcesadas WHERE id = ?");
    $stmt_nomina->execute([$id_nomina]);
    $nomina = $stmt_nomina->fetch();

    // Consulta mejorada para obtener más datos para el volante
    $stmt_empleados = $pdo->prepare("
        SELECT DISTINCT 
            e.id, e.nombres, e.primer_apellido, e.cedula, e.email_personal, 
            c.id as contrato_id,
            p.nombre_posicion
        FROM Empleados e 
        JOIN Contratos c ON e.id = c.id_empleado 
        JOIN Posiciones p ON c.id_posicion = p.id
        JOIN NominaDetalle nd ON c.id = nd.id_contrato
        WHERE nd.id_nomina_procesada = ?
    ");
    $stmt_empleados->execute([$id_nomina]);
    $empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

    $stmt_payslip = $pdo->prepare("
        SELECT nd.* FROM NominaDetalle nd
        JOIN ConceptosNomina cn ON nd.codigo_concepto = cn.codigo_concepto
        WHERE nd.id_nomina_procesada = ? AND nd.id_contrato = ? AND cn.incluir_en_volante = 1
        ORDER BY nd.tipo_concepto DESC, nd.monto_resultado DESC
    ");

    $mail = new PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host       = $configs_db['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $configs_db['SMTP_USER'];
    $mail->Password   = $configs_db['SMTP_PASS'];
    $mail->SMTPSecure = $configs_db['SMTP_SECURE'];
    $mail->Port       = (int)$configs_db['SMTP_PORT'];
    $mail->setFrom($configs_db['SMTP_USER'], $company_name);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    foreach ($empleados as $empleado) {
        if (empty($empleado['email_personal']) || !filter_var($empleado['email_personal'], FILTER_VALIDATE_EMAIL)) {
            $no_email_count++;
            continue;
        }

        $mail->clearAddresses();
        $mail->addAddress($empleado['email_personal'], $empleado['nombres'] . ' ' . $empleado['primer_apellido']);

        $stmt_payslip->execute([$id_nomina, $empleado['contrato_id']]);
        $detalles = $stmt_payslip->fetchAll(PDO::FETCH_ASSOC);
        
        $total_ingresos = 0; $total_deducciones = 0; $ingresos_html = ''; $deducciones_html = '';
        foreach ($detalles as $detalle) {
            $monto_formateado = number_format($detalle['monto_resultado'], 2);
            if ($detalle['tipo_concepto'] === 'Ingreso') {
                $total_ingresos += $detalle['monto_resultado'];
                $ingresos_html .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eaeaea;">' . htmlspecialchars($detalle['descripcion_concepto']) . '</td><td style="padding: 10px; border-bottom: 1px solid #eaeaea; text-align: right;">' . $monto_formateado . '</td></tr>';
            } else if ($detalle['tipo_concepto'] === 'Deducción') {
                $total_deducciones += $detalle['monto_resultado'];
                $deducciones_html .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eaeaea;">' . htmlspecialchars($detalle['descripcion_concepto']) . '</td><td style="padding: 10px; border-bottom: 1px solid #eaeaea; text-align: right;">-' . $monto_formateado . '</td></tr>';
            }
        }
        $neto_a_pagar = $total_ingresos - $total_deducciones;
        
        // --- INICIO DE LA NUEVA PLANTILLA PREMIUM ---
        $body = '
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"><style>body{margin:0;padding:0;background-color:#f4f4f4;}.wrapper{width:100%;table-layout:fixed;background-color:#f4f4f4;padding-bottom:60px;}.main{background-color:#ffffff;margin:0 auto;width:100%;max-width:600px;border-spacing:0;font-family:sans-serif;color:#4a4a4a;}</style></head>
        <body><center class="wrapper">
        <table class="main" width="100%">
            <tr><td style="padding:20px; text-align:center; background-color:#004a99; color:white;">
                <h1>' . htmlspecialchars($company_name) . '</h1>
                <p style="font-size:18px;">Volante de Pago</p>
            </td></tr>
            <tr><td style="padding:30px;">
                <h2 style="font-size: 20px; color: #333;">Hola, ' . htmlspecialchars($empleado['nombres']) . '</h2>
                <p>Aquí tienes el detalle de tu pago para el período del <strong>' . $nomina['periodo_inicio'] . '</strong> al <strong>' . $nomina['periodo_fin'] . '</strong>.</p>
                <table style="width:100%; border-collapse: collapse; margin-top: 20px;">
                    <tr>
                        <td style="padding:10px; background-color:#f8f8f8;"><strong>Cédula:</strong></td><td style="padding:10px;">' . htmlspecialchars($empleado['cedula']) . '</td>
                        <td style="padding:10px; background-color:#f8f8f8;"><strong>Posición:</strong></td><td style="padding:10px;">' . htmlspecialchars($empleado['nombre_posicion']) . '</td>
                    </tr>
                </table>
            </td></tr>
            <tr><td style="padding: 0 30px;">
                <h3 style="color:#004a99; border-bottom:2px solid #004a99; padding-bottom:5px;">Ingresos</h3>
                <table style="width:100%; border-collapse: collapse;">' . $ingresos_html . '</table>
            </td></tr>
            <tr><td style="padding: 20px 30px 0 30px;">
                <h3 style="color:#d9534f; border-bottom:2px solid #d9534f; padding-bottom:5px;">Deducciones</h3>
                <table style="width:100%; border-collapse: collapse;">' . $deducciones_html . '</table>
            </td></tr>
            <tr><td style="padding: 30px;">
                <table style="width:100%; background-color:#f8f8f8; border-top: 3px solid #004a99;">
                    <tr><td style="padding:15px;">Total Ingresos</td><td style="padding:15px; text-align:right; font-weight:bold;">' . number_format($total_ingresos, 2) . '</td></tr>
                    <tr><td style="padding:15px;">Total Deducciones</td><td style="padding:15px; text-align:right; font-weight:bold;">-' . number_format($total_deducciones, 2) . '</td></tr>
                    <tr><td style="padding:15px; font-size:18px; font-weight:bold;">NETO A PAGAR</td><td style="padding:15px; text-align:right; font-size:18px; font-weight:bold;">' . number_format($neto_a_pagar, 2) . '</td></tr>
                </table>
            </td></tr>
            <tr><td style="text-align:center; padding: 20px; font-size:12px; color:#999;">
                <p>Este es un documento generado automáticamente. Si tienes alguna pregunta sobre tu pago, por favor, contacta al departamento de Recursos Humanos.</p>
            </td></tr>
        </table></center></body></html>';
        // --- FIN DE LA PLANTILLA PREMIUM ---
        
        $mail->Subject = 'Tu Volante de Pago - ' . $company_name . ' - Periodo: ' . $nomina['periodo_inicio'];
        $mail->Body = $body;

        try {
            $mail->send();
            $success_count++;
        } catch (Exception $e) {
            $error_count++;
            error_log("PHPMailer Error para {$empleado['email_personal']}: {$mail->ErrorInfo}");
        }
    }

} catch (Exception $e) {
    header('Location: show.php?id=' . $id_nomina . '&status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}

$message = "Proceso de envío finalizado. Enviados: {$success_count}.";
if ($error_count > 0) $message .= " Fallidos: {$error_count}.";
if ($no_email_count > 0) $message .= " Sin email: {$no_email_count}.";

header('Location: show.php?id=' . $id_nomina . '&status=success&message=' . urlencode($message));
exit();
