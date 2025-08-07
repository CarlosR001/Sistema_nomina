<?php
// payroll/send_payslips.php - v1.2 (VERSIÓN FINAL DE DEPURACIÓN)

// Usar los namespaces de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Inicia el búfer para capturar toda la salida y poder ver la depuración
ob_start();

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
    // 1. Obtener la configuración y los datos de la nómina
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

    // 2. Crear y configurar el objeto PHPMailer UNA SOLA VEZ
    $mail = new PHPMailer(true);
    
    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host       = $configs_db['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $configs_db['SMTP_USER'];
    $mail->Password   = $configs_db['SMTP_PASS'];
    $mail->SMTPSecure = $configs_db['SMTP_SECURE'];
    $mail->Port       = (int)$configs_db['SMTP_PORT'];
    $mail->setFrom($configs_db['SMTP_USER'], 'Departamento de Nómina');
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    
    // --- MODO DE DEPURACIÓN MÁXIMO ---
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
    $mail->Debugoutput = 'html';

    // 3. Bucle para enviar un correo a cada empleado
    foreach ($empleados as $empleado) {
        if (empty($empleado['email_personal']) || !filter_var($empleado['email_personal'], FILTER_VALIDATE_EMAIL)) {
            $no_email_count++;
            continue;
        }

        // Limpiar destinatarios y adjuntos de la iteración anterior
        $mail->clearAddresses();
        $mail->addAddress($empleado['email_personal'], $empleado['nombres'] . ' ' . $empleado['primer_apellido']);

        // Generar el cuerpo del correo (volante)
        $stmt_payslip->execute([$id_nomina, $empleado['contrato_id']]);
        $detalles = $stmt_payslip->fetchAll(PDO::FETCH_ASSOC);
        $total_ingresos = 0; $total_deducciones = 0; $ingresos_html = ''; $deducciones_html = '';
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
        $body = "<h1>Volante de Pago</h1><p>Hola {$empleado['nombres']}, aquí tienes el desglose de tu pago para el período del {$nomina['periodo_inicio']} al {$nomina['periodo_fin']}.</p><hr><h3>Ingresos</h3><table width='100%' border='1' cellpadding='5'>{$ingresos_html}</table><p><strong>Total Ingresos: " . number_format($total_ingresos, 2) . "</strong></p><h3>Deducciones</h3><table width='100%' border='1' cellpadding='5'>{$deducciones_html}</table><p><strong>Total Deducciones: -" . number_format($total_deducciones, 2) . "</strong></p><hr><h2>Neto a Pagar: " . number_format($neto_a_pagar, 2) . "</h2>";
        
        $mail->Subject = 'Tu Volante de Pago - Periodo: ' . $nomina['periodo_inicio'];
        $mail->Body = $body;

        try {
            $mail->send();
            $success_count++;
        } catch (Exception $e) {
            $error_count++;
            // Guardamos el error para mostrarlo al final
            echo "Error al enviar a {$empleado['email_personal']}: " . $mail->ErrorInfo . "<br/>";
        }
    }

} catch (Exception $e) {
    // Si algo falla ANTES del bucle (ej. conexión a BD)
    ob_end_clean(); // Limpiar el búfer
    header('Location: show.php?id=' . $id_nomina . '&status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}

// 4. Mostrar el resultado final de la depuración
echo "<h1>Resultados de la Depuración de Envío</h1>";
echo "<h3>Conversación con el servidor SMTP:</h3>";
echo "<pre>";
echo htmlspecialchars(ob_get_clean()); // Muestra todo lo que PHPMailer "habló"
echo "</pre>";

$message = "Proceso finalizado. Enviados: {$success_count}. Fallidos: {$error_count}. Sin email: {$no_email_count}.";
echo "<h3>Resumen</h3>";
echo "<p><strong>{$message}</strong></p>";
echo "<a href='show.php?id={$id_nomina}'>Volver a la nómina</a>";
exit();
