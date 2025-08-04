<?php
// conceptos/store.php - v4.0 (Sintaxis Robusta)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
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

    // Usando una sintaxis más robusta y moderna para la inserción/actualización
    $sql = "INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            descripcion_publica = VALUES(descripcion_publica), 
            tipo_concepto = VALUES(tipo_concepto),
            origen_calculo = VALUES(origen_calculo),
            afecta_tss = VALUES(afecta_tss),
            afecta_isr = VALUES(afecta_isr)";
    
    $stmt = $pdo->prepare($sql);
    
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
    header("Location: index.php?status=error&message=" . urlencode("Error al guardar: " . $e->getMessage()));
    exit();
}
