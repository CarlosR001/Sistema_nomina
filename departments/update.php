<?php
// departments/update.php
// Procesa la actualización de un departamento.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$id = $_POST['id'] ?? null;
$nombre_departamento = trim($_POST['nombre_departamento'] ?? '');
$estado = $_POST['estado'] ?? 'Activo';

if (empty($id) || empty($nombre_departamento)) {
    header('Location: index.php?status=error&message=Faltan datos requeridos.');
    exit();
}

try {
    // Verificar si ya existe otro departamento con el mismo nombre
    $stmt_check = $pdo->prepare("SELECT id FROM Departamentos WHERE nombre_departamento = ? AND id != ?");
    $stmt_check->execute([$nombre_departamento, $id]);
    if ($stmt_check->fetch()) {
        header('Location: index.php?status=error&message=duplicate');
        exit();
    }

    $stmt = $pdo->prepare("UPDATE Departamentos SET nombre_departamento = ?, estado = ? WHERE id = ?");
    $stmt->execute([$nombre_departamento, $estado, $id]);

    header('Location: index.php?status=success');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error');
    exit();
}
?>
