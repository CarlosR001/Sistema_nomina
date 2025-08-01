<?php
// novedades/update.php
// Procesa la actualización de una novedad existente.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

// Validar datos
$id = $_POST['id'] ?? null;
$id_contrato = $_POST['id_contrato'] ?? null;
$id_concepto = $_POST['id_concepto'] ?? null;
$monto_valor = $_POST['monto_valor'] ?? null;
$periodo_aplicacion = $_POST['periodo_aplicacion'] ?? null;
$descripcion_adicional = $_POST['descripcion_adicional'] ?? '';

if (empty($id) || empty($id_contrato) || empty($id_concepto) || empty($monto_valor) || empty($periodo_aplicacion)) {
    header('Location: index.php?status=error&message=Todos los campos obligatorios deben ser completados.');
    exit();
}

try {
    // Asegurarse de que la novedad que se intenta editar todavía está 'Pendiente'
    $stmt_check = $pdo->prepare("SELECT estado_novedad FROM NovedadesPeriodo WHERE id = ?");
    $stmt_check->execute([$id]);
    $estado_actual = $stmt_check->fetchColumn();

    if ($estado_actual !== 'Pendiente') {
        throw new Exception("No se puede actualizar una novedad que ya ha sido aplicada en una nómina.");
    }

    // Actualizar el registro
    $stmt_update = $pdo->prepare(
        "UPDATE NovedadesPeriodo SET 
            id_contrato = :id_contrato,
            id_concepto = :id_concepto,
            monto_valor = :monto_valor,
            periodo_aplicacion = :periodo_aplicacion,
            descripcion_adicional = :descripcion_adicional
         WHERE id = :id"
    );

    $stmt_update->execute([
        ':id_contrato' => $id_contrato,
        ':id_concepto' => $id_concepto,
        ':monto_valor' => $monto_valor,
        ':periodo_aplicacion' => $periodo_aplicacion,
        ':descripcion_adicional' => $descripcion_adicional,
        ':id' => $id
    ]);

    header('Location: index.php?status=success&message=Novedad actualizada correctamente.');
    exit();

} catch (Exception $e) {
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
}
?>
