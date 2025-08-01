<?php
// contracts/update.php - v2.1 MODO DEPURACIÓN
// Este script fuerza la visualización de errores para diagnosticar el problema de guardado.

// --- PASO 1: FORZAR LA VISUALIZACIÓN DE ERRORES ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre style='font-family: monospace; border: 2px solid #c00; padding: 15px; background-color: #f9f9f9;'>";
echo "<strong>--- INICIO DE DEPURACIÓN: contracts/update.php ---</strong>

";

echo "[Paso 1/7] Script iniciado. Cargando dependencias...
";
require_once '../auth.php';
echo "[Paso 2/7] auth.php cargado. Verificando sesión y rol...
";
require_login();
require_role('Admin');
echo "[Paso 3/7] Sesión y rol verificados. El método de solicitud es: " . $_SERVER['REQUEST_METHOD'] . "
";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<strong>ERROR:</strong> El método no es POST. Script detenido.");
}

echo "[Paso 4/7] Recogiendo datos del formulario...
";
// --- Recoger datos ---
$id = $_POST['id'] ?? null;
$employee_id = $_POST['employee_id'] ?? null;
$id_posicion = $_POST['id_posicion'] ?? null;
$tipo_contrato = $_POST['tipo_contrato'] ?? null;
$tipo_nomina = $_POST['tipo_nomina'] ?? null;
$frecuencia_pago = $_POST['frecuencia_pago'] ?? null;
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$estado_contrato = $_POST['estado_contrato'] ?? null;
$fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
$salario_mensual_bruto = !empty($_POST['salario_mensual_bruto']) ? (float)$_POST['salario_mensual_bruto'] : null;
$tarifa_por_hora = !empty($_POST['tarifa_por_hora']) ? (float)$_POST['tarifa_por_hora'] : null;
echo "Datos recibidos:
";
print_r($_POST);
echo "
";

echo "[Paso 5/7] Aplicando reglas de negocio para salarios...
";
// --- Regla de negocio ---
if ($tipo_nomina === 'Inspectores') {
    $salario_mensual_bruto = null;
    echo "  -> Nómina de Inspectores detectada. Salario mensual puesto a NULL.
";
} else {
    $tarifa_por_hora = null;
    echo "  -> Nómina Administrativa/Directiva detectada. Tarifa por hora puesta a NULL.
";
}

echo "[Paso 6/7] Preparando para ejecutar la consulta en la base de datos...
";
try {
    // Verificar que no haya otro contrato vigente
    if ($estado_contrato === 'Vigente') {
        $stmt_check = $pdo->prepare("SELECT id FROM Contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente' AND id != ?");
        $stmt_check->execute([$employee_id, $id]);
        if ($stmt_check->fetch()) {
            die("<strong>ERROR LÓGICO:</strong> Ya existe otro contrato vigente para este empleado.");
        }
        echo "  -> Verificación de contrato vigente superada.
";
    }

    $sql = "UPDATE Contratos SET
                id_posicion = :id_posicion, tipo_contrato = :tipo_contrato, tipo_nomina = :tipo_nomina,
                fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, salario_mensual_bruto = :salario_mensual_bruto,
                tarifa_por_hora = :tarifa_por_hora, frecuencia_pago = :frecuencia_pago, estado_contrato = :estado_contrato
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    echo "  -> Consulta SQL preparada. Ejecutando ahora...
";
    
    $stmt->execute([
        ':id_posicion' => $id_posicion, ':tipo_contrato' => $tipo_contrato, ':tipo_nomina' => $tipo_nomina,
        ':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin, ':salario_mensual_bruto' => $salario_mensual_bruto,
        ':tarifa_por_hora' => $tarifa_por_hora, ':frecuencia_pago' => $frecuencia_pago, ':estado_contrato' => $estado_contrato,
        ':id' => $id
    ]);
    
    echo "[Paso 7/7] ¡Éxito! La consulta se ejecutó sin errores.
";
    echo "
<strong>--- FIN DE DEPURACIÓN ---</strong>
";
    echo "Redirigiendo en 3 segundos...";
    
    // Redirección con mensaje de éxito
    $success_url = BASE_URL . 'contracts/index.php?employee_id=' . urlencode($employee_id) . '&status=success&message=Contrato actualizado correctamente.';
    header("Refresh:3; url={$success_url}");
    exit();

} catch (PDOException $e) {
    echo "
<strong>--- ERROR FATAL (PDOException) ---</strong>
";
    die("Mensaje de Error: " . $e->getMessage());
} catch (Exception $e) {
    echo "
<strong>--- ERROR FATAL (Exception) ---</strong>
";
    die("Mensaje de Error: " . $e->getMessage());
}
echo "</pre>";
?>
