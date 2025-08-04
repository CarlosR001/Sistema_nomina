<?php
// conceptos/store.php - v4.1 (Versión Definitiva Sincronizada)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    // Leer los 6 campos que se envían desde el formulario
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $origen_calculo = $_POST['origen_calculo'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];

    if (empty($codigo_concepto) || empty($descripcion_publica)) {
        header("Location: index.php?status=error&message=El código y la descripción son obligatorios.");
        exit();
    }

    // Consulta SQL que coincide con las 6 columnas de la tabla
    $sql = "INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar con los 6 valores en el orden correcto
    $stmt->execute([
        $codigo_concepto,
        $descripcion_publica,
        $tipo_concepto,
        $origen_calculo,
        $afecta_tss,
        $afecta_isr
    ]);

    header("Location: index.php?status=success&message=Concepto guardado exitosamente.");
    exit();

} catch (PDOException $e) {
    // Si el código ya existe, la base de datos dará un error de integridad (código 23000)
    if ($e->getCode() == '23000') {
        $error_message = "Error: El código de concepto '" . htmlspecialchars($codigo_concepto) . "' ya existe.";
    } else {
        $error_message = "Error al guardar en la base de datos: " . $e->getMessage();
    }
    header("Location: index.php?status=error&message=" . urlencode($error_message));
    exit();
}
