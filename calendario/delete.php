<?php
// calendario/delete.php
// Procesa la eliminación de un día feriado.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$fecha = $_POST['fecha'] ?? null;

if (empty($fecha)) {
    header('Location: index.php?status=error&message=Fecha no proporcionada.');
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM CalendarioLaboralRD WHERE fecha = ?");
    $stmt->execute([$fecha]);

    header('Location: index.php?status=success&message=Día feriado eliminado correctamente.');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos.'));
    exit();
}
?>
