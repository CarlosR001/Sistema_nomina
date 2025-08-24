<?php
// ordenes/store.php - v2.0 (con nuevos campos)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Recoger todos los campos del formulario
$codigo_orden = $_POST['codigo_orden'] ?? null;
$numero_orden_compra = $_POST['numero_orden_compra'] ?? null; // <-- NUEVO
$id_cliente = $_POST['id_cliente'] ?? null;
$id_lugar = $_POST['id_lugar'] ?? null;
$id_producto = $_POST['id_producto'] ?? null;
$id_operacion = $_POST['id_operacion'] ?? null;
$id_division = !empty($_POST['id_division']) ? $_POST['id_division'] : null;
$id_supervisor = !empty($_POST['id_supervisor']) ? $_POST['id_supervisor'] : null;
$observaciones = $_POST['observaciones'] ?? null; // <-- NUEVO
$id_usuario_creador = $_SESSION['user_id'];
$fecha_creacion = date('Y-m-d');
$estado_orden = 'En Proceso';

// Validaciones básicas
if (empty($codigo_orden) || empty($id_cliente) || empty($id_lugar) || empty($id_producto) || empty($id_operacion)) {
    redirect_with_error('create.php', 'Los campos obligatorios no pueden estar vacíos.');
    exit;
}

try {
    // Consulta SQL actualizada para incluir los nuevos campos
    $sql = "INSERT INTO ordenes (
                codigo_orden, numero_orden_compra, id_cliente, id_lugar, id_producto, 
                id_operacion, id_division, id_supervisor, fecha_creacion, 
                id_usuario_creador, estado_orden, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $codigo_orden,
        $numero_orden_compra, // <-- NUEVO
        $id_cliente,
        $id_lugar,
        $id_producto,
        $id_operacion,
        $id_division,
        $id_supervisor,
        $fecha_creacion,
        $id_usuario_creador,
        $estado_orden,
        $observaciones // <-- NUEVO
    ]);

    redirect_with_success('index.php', 'Orden creada exitosamente.');
    exit;

} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) {
        $message = urlencode('Error: El código de orden ya existe.');
    } else {
        $message = urlencode('Error de base de datos: ' . $e->getMessage());
    }
    header('Location: create.php?status=error&message=' . $message);
    exit;
}
