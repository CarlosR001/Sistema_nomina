<?php
// conceptos/store.php - v2.0 Corregido

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Leer los 5 campos que envía el formulario
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];

    // 2. Validación básica
    if (empty($codigo_concepto) || empty($descripcion_publica)) {
        header("Location: index.php?status=error&message=El código y la descripción son obligatorios.");
        exit();
    }

    try {
        // 3. Esta es la consulta SQL corregida. Solo usa 5 columnas y 5 marcadores.
        // La columna 'origen_calculo' ha sido eliminada.
        $sql = "INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, afecta_tss, afecta_isr) 
                VALUES (:codigo, :descripcion, :tipo, :tss, :isr)
                ON DUPLICATE KEY UPDATE 
                descripcion_publica = :descripcion, 
                tipo_concepto = :tipo,
                afecta_tss = :tss,
                afecta_isr = :isr";
        
        $stmt = $pdo->prepare($sql);
        
        // 4. Este array de ejecución ahora coincide perfectamente con la consulta SQL (5 valores).
        $stmt->execute([
            ':codigo' => $codigo_concepto,
            ':descripcion' => $descripcion_publica,
            ':tipo' => $tipo_concepto,
            ':tss' => $afecta_tss,
            ':isr' => $afecta_isr
        ]);

        header("Location: index.php?status=success&message=Concepto guardado exitosamente.");

    } catch (PDOException $e) {
        header("Location: index.php?status=error&message=" . urlencode("Error al guardar el concepto: " . $e->getMessage()));
    }
    exit();
}

header("Location: index.php");
exit();
