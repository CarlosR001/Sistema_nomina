<?php
// calendario/delete.php
// Procesa la eliminación de un día feriado.

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$fecha = $_POST['fecha'] ?? null;

if (empty($fecha)) {
    
    redirect_with_error('index.php', 'Fecha no proporcionada.');
   exit();
}

try {
   $stmt = $pdo->prepare("DELETE FROM calendariolaboralrd WHERE fecha = ?");
   $stmt->execute([$fecha]);

   redirect_with_error('index.php', 'Día feriado eliminado correctamente.');
   exit();

} catch (PDOException $e) {
  redirect_with_error('index.php', 'Error de base de datos.');
   exit();
}
?>
