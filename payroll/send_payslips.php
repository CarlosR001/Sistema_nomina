<?php
// payroll/send_payslips.php - v1.5 (Con Volante de Pago Moderno)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../auth.php';
require_login();
require_role('Admin');

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
    
    $stmt_nomina = $pdo->prepare("SELECT * FROM NominasProcesadas WHERE id = ?");
    $stmt_nomina->execute([$id_nomina]);
    $nomina = $stmt_nomina->fetch();

    $stmt_empleados = $pdo->prepare("
        SELECT DISTINCT e.id, e.nombres, e.primer_apellido, e.email_personal, c.id as contrato_id
        FROM Empleados e JOIN Contratos c ON e.id = c.id_empleado JOIN NominaDetalle nd ON c.id = nd.id_contrato
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
    $mail->setFrom($configs_db['SMTP_USER'], 'Departamento de Nómina J&C'); // Puedes cambiar el nombre del remitente
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
            if ($detalle['tipo_concepto'] === 'Ingreso') {
                $total_ingresos += $detalle['monto_resultado'];
                $ingresos_html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($detalle['descripcion_concepto']) . '</td><td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">' . number_format($detalle['monto_resultado'], 2) . '</td></tr>';
            } else if ($detalle['tipo_concepto'] === 'Deducción') {
                $total_deducciones += $detalle['monto_resultado'];
                $deducciones_html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($detalle['descripcion_concepto']) . '</td><td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">-' . number_format($detalle['monto_resultado'], 2) . '</td></tr>';
            }
        }
        $neto_a_pagar = $total_ingresos - $total_deducciones;
        
        // --- INICIO DE LA NUEVA PLANTILLA DE CORREO MODERNA ---
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { background-color: #004a99; color: white; padding: 10px; text-align: center; }
                .content-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .summary-table { width: 100%; margin-top: 20px; }
                .total-row { font-weight: bold; background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header"><h2>Volante de Pago</h2></div>
                <p>Hola ' . htmlspecialchars($empleado['nombres']) . ',</p>
                <p>Aquí tienes el desglose de tu pago para el período del <strong>' . $nomina['periodo_inicio'] . '</strong> al <strong>' . $nomina['periodo_fin'] . '</strong>.</p>
                
                <h3 style="color: #004a99;">Ingresos</h3>
                <table class="content-table">' . $ingresos_html . '</table>
                
                <h3 style="color: #004a99;">Deducciones</h3>
                <table class="content-table">' . $deducciones_html . '</table>
                
                <table class="summary-table">
                    <tr class="total-row"><td style="padding: 10px;">Total Ingresos</td><td style="padding: 10px; text-align: right;">' . number_format($total_ingresos, 2) . '</td></tr>
                    <tr class="total-row"><td style="padding: 10px;">Total Deducciones</td><td style="padding: 10px; text-align: right;">-' . number_format($total_deducciones, 2) . '</td></tr>
                    <tr class="total-row" style="font-size: 1.2em;"><td style="padding: 10px;">Neto a Pagar</td><td style="padding: 10px; text-align: right;">' . number_format($neto_a_pagar, 2) . '</td></tr>
                </table>
                <p style="text-align: center; font-size: 0.8em; color: #888; margin-top: 20px;">Este es un correo generado automáticamente. Por favor, no responda a este mensaje.</p>
            </div>
        </body>
        </html>';
        // --- FIN DE LA NUEVA PLANTILLA ---
        
        $mail->Subject = 'Tu Volante de Pago - Periodo: ' . $nomina['periodo_inicio'];
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
