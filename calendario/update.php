<?php
// calendario/update.php
// Procesa la actualización de un día feriado.

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$fecha = $_POST['fecha'] ?? null;
$descripcion = trim($_POST['descripcion'] ?? '');

if (empty($fecha) || empty($descripcion)) {
    header('Location: index.php?status=error&message=Faltan datos requeridos.');
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE calendariolaboralrd SET descripcion = ? WHERE fecha = ?");
    $stmt->execute([$descripcion, $fecha]);

    header('Location: index.php?status=success&message=Día feriado actualizado correctamente.');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos.'));
    exit();
}
?>
