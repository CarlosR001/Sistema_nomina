<?php
// zones/update.php
// Procesa la actualización de una zona de transporte.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$id = $_POST['id'] ?? null;
$nombre_zona = trim($_POST['nombre_zona'] ?? '');
$monto = $_POST['monto'] ?? null;

if (empty($id) || empty($nombre_zona) || !is_numeric($monto)) {
    header('Location: index.php?status=error&message=Faltan datos requeridos o son incorrectos.');
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE ZonasTransporte SET nombre_zona_o_muelle = ?, monto_transporte_completo = ? WHERE id = ?");
    $stmt->execute([$nombre_zona, $monto, $id]);

    header('Location: index.php?status=success&message=Zona actualizada correctamente.');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos.'));
    exit();
}
?>
