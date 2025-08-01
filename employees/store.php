<?php
// employees/store.php
// v1.1 - Corrige el nombre de la tabla y mejora las validaciones.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Recoger todos los datos del formulario (create.php envía más campos)
$cedula = trim($_POST['cedula']);
$nombres = trim($_POST['nombres']);
$primer_apellido = trim($_POST['primer_apellido']);
$email_personal = trim($_POST['email_personal']);

// Datos opcionales
$segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
$nss = trim($_POST['nss'] ?? '');

if (empty($cedula) || empty($nombres) || empty($primer_apellido)) {
    header("Location: create.php?status=error&message=" . urlencode("Los campos Cédula, Nombres y Primer Apellido son obligatorios."));
    exit();
}

try {
    // Verificar duplicados
    $stmt_check = $pdo->prepare("SELECT id FROM empleados WHERE cedula = ? OR nss = ?");
    $stmt_check->execute([$cedula, $nss]);
    if ($stmt_check->fetch()) {
        header("Location: create.php?status=error&message=" . urlencode("La Cédula o el NSS ya están registrados."));
        exit();
    }

    // CORRECCIÓN: El nombre de la tabla es 'empleados', no 'Empleados'.
    $sql = "INSERT INTO empleados (cedula, nss, nombres, primer_apellido, segundo_apellido, email_personal, estado_empleado) 
            VALUES (:cedula, :nss, :nombres, :primer_apellido, :segundo_apellido, :email_personal, 'Activo')";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':cedula' => $cedula,
        ':nss' => $nss,
        ':nombres' => $nombres,
        ':primer_apellido' => $primer_apellido,
        ':segundo_apellido' => $segundo_apellido,
        ':email_personal' => $email_personal
    ]);

    header("Location: index.php?status=success&message=Empleado creado exitosamente.");
    exit();

} catch (PDOException $e) {
    header("Location: create.php?status=error&message=" . urlencode("Error de base de datos al crear el empleado."));
    exit();
}
