<?php
// conceptos/store.php - v4.2 (con Código TSS)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    // Leer los 7 campos que se envían desde el formulario
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $origen_calculo = $_POST['origen_calculo'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];
    // El nuevo campo puede estar vacío
    $codigo_tss = trim($_POST['codigo_tss']) ?: null;

    if (empty($codigo_concepto) || empty($descripcion_publica)) {
        header("Location: index.php?status=error&message=El código y la descripción son obligatorios.");
        exit();
    }

    // Consulta SQL con la nueva columna
    $sql = "INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr, codigo_tss) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar con los 7 valores
    $stmt->execute([
        $codigo_concepto,
        $descripcion_publica,
        $tipo_concepto,
        $origen_calculo,
        $afecta_tss,
        $afecta_isr,
        $codigo_tss
    ]);

    header("Location: index.php?status=success&message=Concepto guardado exitosamente.");
    exit();

} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        $error_message = "Error: El código de concepto '" . htmlspecialchars($codigo_concepto) . "' ya existe.";
    } else {
        $error_message = "Error al guardar en la base de datos: " . $e->getMessage();
    }
    header("Location: index.php?status=error&message=" . urlencode($error_message));
    exit();
}
