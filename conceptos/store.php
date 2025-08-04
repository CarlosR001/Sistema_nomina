<?php
// conceptos/store.php - v3.1 (Versión Definitiva)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    // Leer los 6 campos del formulario que coinciden con la tabla
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $origen_calculo = $_POST['origen_calculo'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];

    // Validación
    if (empty($codigo_concepto) || empty($descripcion_publica)) {
        header("Location: index.php?status=error&message=El código y la descripción son obligatorios.");
        exit();
    }

    // Consulta SQL que coincide EXACTAMENTE con la tabla y los datos leídos
    $sql = "INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr) 
            VALUES (:codigo, :descripcion, :tipo, :origen, :tss, :isr)
            ON DUPLICATE KEY UPDATE 
            descripcion_publica = :descripcion, 
            tipo_concepto = :tipo,
            origen_calculo = :origen,
            afecta_tss = :tss,
            afecta_isr = :isr";
    
    $stmt = $pdo->prepare($sql);
    
    // Array de ejecución que coincide EXACTAMENTE con la consulta (6 valores)
    $stmt->execute([
        ':codigo' => $codigo_concepto,
        ':descripcion' => $descripcion_publica,
        ':tipo' => $tipo_concepto,
        ':origen' => $origen_calculo,
        ':tss' => $afecta_tss,
        ':isr' => $afecta_isr
    ]);

    header("Location: index.php?status=success&message=Concepto guardado exitosamente.");
    exit();

} catch (PDOException $e) {
    // Si todavía hay un error, lo mostraremos en pantalla para diagnosticarlo.
    $error_message = "Error al guardar en la base de datos: " . $e->getMessage();
    header("Location: index.php?status=error&message=" . urlencode($error_message));
    exit();
}
