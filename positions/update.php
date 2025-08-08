<?php
// positions/update.php
// Procesa la actualización de una posición.

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$id = $_POST['id'] ?? null;
$nombre_posicion = trim($_POST['nombre_posicion'] ?? '');
$id_departamento = $_POST['id_departamento'] ?? null;

if (empty($id) || empty($nombre_posicion) || empty($id_departamento)) {
    header('Location: index.php?status=error&message=Faltan datos requeridos.');
    exit();
}

try {
    // Aquí podríamos añadir una validación para evitar nombres de posición duplicados si fuera necesario.

    $stmt = $pdo->prepare("UPDATE Posiciones SET nombre_posicion = ?, id_departamento = ? WHERE id = ?");
    $stmt->execute([$nombre_posicion, $id_departamento, $id]);

    header('Location: index.php?status=success&message=Posición actualizada correctamente.');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos.'));
    exit();
}
?>
