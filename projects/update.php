<?php
// projects/update.php
// Procesa la actualización de un proyecto.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$id = $_POST['id'] ?? null;
$nombre_proyecto = trim($_POST['nombre_proyecto'] ?? '');
$codigo_proyecto = trim($_POST['codigo_proyecto'] ?? '');
$estado_proyecto = $_POST['estado_proyecto'] ?? 'Activo';

if (empty($id) || empty($nombre_proyecto)) {
    header('Location: index.php?status=error&message=Faltan datos requeridos.');
    exit();
}

try {
    // Podríamos añadir validación de código de proyecto único si fuera necesario

    $stmt = $pdo->prepare("UPDATE Proyectos SET nombre_proyecto = ?, codigo_proyecto = ?, estado_proyecto = ? WHERE id = ?");
    $stmt->execute([$nombre_proyecto, $codigo_proyecto, $estado_proyecto, $id]);

    header('Location: index.php?status=success&message=Proyecto actualizado correctamente.');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos.'));
    exit();
}
?>
