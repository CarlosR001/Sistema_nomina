<?php
// payroll/process.php - v8.7 (MODO DEPURACIÓN)
// Este script fuerza la visualización de errores para diagnosticar por qué no se genera la nómina.

// --- PASO 1: FORZAR LA VISUALIZACIÓN DE ERRORES ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre style='font-family: monospace; border: 2px solid #c00; padding: 15px; background-color: #f9f9f9;'>";
echo "<strong>--- INICIO DE DEPURACIÓN: payroll/process.php ---</strong>\n\n";

echo "[Paso 1/8] Script iniciado. Cargando dependencias...\n";
require_once '../auth.php';
echo "[Paso 2/8] auth.php cargado. Verificando sesión y rol...\n";
require_login();
require_role('Admin');
echo "[Paso 3/8] Sesión y rol verificados.\n";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'])) {
    die("<strong>ERROR:</strong> Solicitud no válida.");
}
$periodo_id = $_POST['periodo_id'];
echo "[Paso 4/8] Período ID recibido: " . htmlspecialchars($periodo_id) . "\n";

try {
    echo "[Paso 5/8] Iniciando transacción de base de datos...\n";
    $pdo->beginTransaction();

    $stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
    $stmt_periodo->execute([$periodo_id]);
    $periodo = $stmt_periodo->fetch();
    if (!$periodo) { throw new Exception("Período no encontrado."); }

    $fecha_inicio = $periodo['fecha_inicio_periodo'];
    echo "  -> Período encontrado. Fecha de inicio: " . htmlspecialchars($fecha_inicio) . "\n";
    
    // ... (El resto de la inicialización) ...
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = " . date('Y') . " ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo "[Paso 6/8] Buscando contratos con novedades para este período...\n";
    $sql_contratos = "SELECT DISTINCT c.id as id_contrato FROM Contratos c JOIN NovedadesPeriodo np ON c.id = np.id_contrato WHERE np.periodo_aplicacion = ?";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([$fecha_inicio]);
    $contratos = $stmt_contratos->fetchAll();

    if (empty($contratos)) {
        throw new Exception("No se encontraron novedades para ningún contrato en este período. No se puede procesar una nómina vacía.");
    }
    echo "  -> Se encontraron " . count($contratos) . " contratos para procesar.\n";

    // Crear la cabecera de la nómina
    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$periodo['tipo_nomina'], $fecha_inicio, $periodo['fecha_fin_periodo'], $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();
    echo "[Paso 7/8] Cabecera de nómina creada (ID: $id_nomina_procesada). Empezando a procesar empleados...\n\n";

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id_contrato'];
        echo "-------------------------------------------------\n";
        echo "Procesando Contrato ID: $id_contrato\n";
        // ... (Aquí iría la lógica de cálculo detallada, que por ahora asumimos correcta) ...
    }

    echo "\n-------------------------------------------------\n";
    echo "[Paso 8/8] Todos los empleados procesados. Actualizando estado del período...\n";
    $stmt_cerrar = $pdo->prepare("UPDATE PeriodosDeReporte SET estado_periodo = 'Procesado y Finalizado' WHERE id = ?");
    $stmt_cerrar->execute([$periodo_id]);
    
    echo "  -> Estado del período actualizado a 'Procesado y Finalizado'.\n";
    echo "Confirmando transacción (commit)...\n";
    $pdo->commit();
    
    echo "\n<strong>--- FIN DE DEPURACIÓN ---</strong>\n";
    echo "Redirigiendo a la página de resultados en 3 segundos...";
    
    header("Refresh:3; url=" . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed');
    exit();

} catch (Exception $e) {
    if($pdo->inTransaction()) {
        echo "\n<strong>ERROR:</strong> Se ha producido una excepción. Realizando rollback...\n";
        $pdo->rollBack();
    }
    echo "\n<strong>--- ERROR FATAL (Exception) ---</strong>\n";
    die("Mensaje de Error: " . $e->getMessage());
}
echo "</pre>";
?>
