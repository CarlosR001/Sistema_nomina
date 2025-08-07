<?php
// payroll/send_payslips.php - v1.0 (Motor de Envío de Volantes de Pago)

// Usar los namespaces de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../auth.php';
require_login();
require_role('Admin');

// Cargar PHPMailer manualmente
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nomina_id'])) {
    header('Location: review.php');
    exit();
}

$id_nomina = (int)$_POST['nomina_id'];
$success_count = 0;
$error_count = 0;
$no_email_count = 0;

try {
    // 1. Obtener la configuración SMTP de la base de datos
    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 2. Obtener información de la nómina y los empleados
    $stmt_nomina = $pdo->prepare("SELECT * FROM NominasProcesadas WHERE id = ?");
    $stmt_nomina->execute([$id_nomina]);
    $nomina = $stmt_nomina->fetch();

    $stmt_empleados = $pdo->prepare("
        SELECT DISTINCT e.id, e.nombres, e.primer_apellido, e.email_personal, c.id as contrato_id
        FROM Empleados e
        JOIN Contratos c ON e.id = c.id_empleado
        JOIN NominaDetalle nd ON c.id = nd.id_contrato
        WHERE nd.id_nomina_procesada = ?
    ");
    $stmt_empleados->execute([$id_nomina]);
    $empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

    // 3. Preparar la consulta para los detalles del volante (se reutilizará en el bucle)
    $stmt_payslip = $pdo->prepare("
        SELECT nd.* 
        FROM NominaDetalle nd
        JOIN ConceptosNomina cn ON nd.codigo_concepto = cn.codigo_concepto
        WHERE nd.id_nomina_procesada = ? AND nd.id_contrato = ? AND cn.incluir_en_volante = 1
        ORDER BY nd.tipo_concepto DESC, nd.monto_resultado DESC
    ");

    // 4. Bucle para enviar correo a cada empleado
    foreach ($empleados as $empleado) {
        if (empty($empleado['email_personal']) || !filter_var($empleado['email_personal'], FILTER_VALIDATE_EMAIL)) {
            $no_email_count++;
            continue;
        }

        // Obtener los detalles filtrados para el volante
        $stmt_payslip->execute([$id_nomina, $empleado['contrato_id']]);
        $detalles = $stmt_payslip->fetchAll(PDO::FETCH_ASSOC);
        
        // Generar el HTML del volante
        $ingresos_html = '';
        $deducciones_html = '';
        $total_ingresos = 0;
        $total_deducciones = 0;

        foreach ($detalles as $detalle) {
            if ($detalle['tipo_concepto'] === 'Ingreso') {
                $total_ingresos += $detalle['monto_resultado'];
                $ingresos_html .= "<tr><td>{$detalle['descripcion_concepto']}</td><td style='text-align:right;'>" . number_format($detalle['monto_resultado'], 2) . "</td></tr>";
            } else if ($detalle['tipo_concepto'] === 'Deducción') {
                $total_deducciones += $detalle['monto_resultado'];
                $deducciones_html .= "<tr><td>{$detalle['descripcion_concepto']}</td><td style='text-align:right;'>-" . number_format($detalle['monto_resultado'], 2) . "</td></tr>";
            }
        }
        $neto_a_pagar = $total_ingresos - $total_deducciones;

        // Cuerpo del correo en HTML
        $body = "
            <h1>Volante de Pago</h1>
            <p>Hola {$empleado['nombres']}, aquí tienes el desglose de tu pago para el período del {$nomina['periodo_inicio']} al {$nomina['periodo_fin']}.</p>
            <hr>
            <h3>Ingresos</h3>
            <table width='100%' border='1' cellpadding='5'>{$ingresos_html}</table>
            <p><strong>Total Ingresos: " . number_format($total_ingresos, 2) . "</strong></p>
            <h3>Deducciones</h3>
            <table width='100%' border='1' cellpadding='5'>{$deducciones_html}</table>
            <p><strong>Total Deducciones: -" . number_format($total_deducciones, 2) . "</strong></p>
            <hr>
            <h2>Neto a Pagar: " . number_format($neto_a_pagar, 2) . "</h2>
        ";

        // 5. Configurar y enviar el correo con PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug = 2;
            $mail->isSMTP();
            $mail->Host = $configs_db['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $configs_db['SMTP_USER'];
            $mail->Password = $configs_db['SMTP_PASS'];
            $mail->SMTPSecure = $configs_db['SMTP_SECURE'];
            $mail->Port = $configs_db['SMTP_PORT'];
            $mail->setFrom($configs_db['SMTP_USER'], 'Departamento de Nómina');
            $mail->addAddress($empleado['email_personal'], $empleado['nombres'] . ' ' . $empleado['primer_apellido']);
            $mail->isHTML(true);
            $mail->Subject = 'Tu Volante de Pago - Periodo: ' . $nomina['periodo_inicio'];
            $mail->Body = $body;
            $mail->CharSet = 'UTF-8';

            $mail->send();
            $success_count++;
        } catch (Exception $e) {
            $error_count++;
            // Opcional: Registrar el error detallado en un log
            error_log("PHPMailer Error para {$empleado['email_personal']}: {$mail->ErrorInfo}");
        }
    }
        // 6. Crear mensaje de resumen y redirigir
        $message = "Proceso de envío finalizado. Enviados: {$success_count}.";
        if ($error_count > 0) $message .= " Fallidos: {$error_count}.";
        if ($no_email_count > 0) $message .= " Sin email: {$no_email_count}.";
        
        // --- INICIO DE LA MODIFICACIÓN PARA DEPURAR ---

        // Mostramos un mensaje final y detenemos el script para poder ver la salida de depuración.
        echo "<h2>Depuración Finalizada</h2>";
        echo "<p>{$message}</p>";
        echo "<a href='show.php?id={$id_nomina}'>Volver a la nómina</a>";
        exit(); // Detenemos el script aquí.

        // La redirección original queda temporalmente desactivada.
        // header('Location: show.php?id=' . $id_nomina . '&status=success&message=' . urlencode($message));
        // exit();
        
        // --- FIN DE LA MODIFICACIÓN ---

} catch (Exception $e) {
    header('Location: show.php?id=' . $id_nomina . '&status=error&message=' . urlencode('Error general: ' . $e->getMessage()));
    exit();
}
