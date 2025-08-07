<?php
// conceptos/store.php - v4.3 (con Visibilidad en Volante)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    // Leer todos los campos del formulario, incluyendo el nuevo
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];
    $codigo_tss = trim($_POST['codigo_tss']) ?: null;
    $incluir_en_volante = $_POST['incluir_en_volante']; // Nuevo campo

    if (empty($codigo_concepto) || empty($descripcion_publica)) {
        header("Location: index.php?status=error&message=El código y la descripción son obligatorios.");
        exit();
    }

    // Consulta SQL con la nueva columna
    $sql = "INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr, codigo_tss, incluir_en_volante) 
            VALUES (?, ?, ?, 'Novedad', ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar con todos los valores
    $stmt->execute([
        $codigo_concepto,
        $descripcion_publica,
        $tipo_concepto,
        $afecta_tss,
        $afecta_isr,
        $codigo_tss,
        $incluir_en_volante
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
