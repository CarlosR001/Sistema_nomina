<?php
// employees/update.php
// v1.1 - Corrige el nombre de la tabla de 'Empleados' a 'empleados'.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

// Recoger datos del formulario
$id = $_POST['id'];
$cedula = $_POST['cedula'];
$nss = $_POST['nss'];
$nombres = $_POST['nombres'];
$primer_apellido = $_POST['primer_apellido'];
$segundo_apellido = $_POST['segundo_apellido'];
$fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
$sexo = $_POST['sexo'];
$direccion_completa = $_POST['direccion_completa'];
$telefono_principal = $_POST['telefono_principal'];
$email_personal = $_POST['email_personal'];
$id_banco = !empty($_POST['id_banco']) ? $_POST['id_banco'] : null;
$tipo_cuenta_bancaria = !empty($_POST['tipo_cuenta_bancaria']) ? $_POST['tipo_cuenta_bancaria'] : null;
$numero_cuenta_bancaria = $_POST['numero_cuenta_bancaria'];
$estado_empleado = $_POST['estado_empleado'];


if (empty($id) || empty($cedula) || empty($nombres) || empty($primer_apellido) || empty($estado_empleado)) {
    header('Location: edit.php?id=' . $id . '&status=error&message=Los campos Cédula, Nombres, Primer Apellido y Estado son obligatorios.');
    exit();
}


try {
    // CORRECCIÓN: El nombre de la tabla es 'empleados', no 'Empleados'.
    $stmt_check = $pdo->prepare("SELECT id FROM empleados WHERE (cedula = ? OR nss = ?) AND id != ?");
    $stmt_check->execute([$cedula, $nss, $id]);
    if ($stmt_check->fetch()) {
        header('Location: edit.php?id=' . $id . '&status=error&message=La cédula o el NSS ya están registrados para otro empleado.');
        exit();
    }

    $sql = "UPDATE empleados SET 
                cedula = :cedula, nss = :nss, nombres = :nombres, primer_apellido = :primer_apellido,
                segundo_apellido = :segundo_apellido, fecha_nacimiento = :fecha_nacimiento, sexo = :sexo,
                direccion_completa = :direccion_completa, telefono_principal = :telefono_principal,
                email_personal = :email_personal, id_banco = :id_banco, tipo_cuenta_bancaria = :tipo_cuenta_bancaria,
                numero_cuenta_bancaria = :numero_cuenta_bancaria, estado_empleado = :estado_empleado
            WHERE id = :id";
            
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':cedula' => $cedula, ':nss' => $nss, ':nombres' => $nombres, ':primer_apellido' => $primer_apellido,
        ':segundo_apellido' => $segundo_apellido, ':fecha_nacimiento' => $fecha_nacimiento, ':sexo' => $sexo,
        ':direccion_completa' => $direccion_completa, ':telefono_principal' => $telefono_principal,
        ':email_personal' => $email_personal, ':id_banco' => $id_banco, ':tipo_cuenta_bancaria' => $tipo_cuenta_bancaria,
        ':numero_cuenta_bancaria' => $numero_cuenta_bancaria, ':estado_empleado' => $estado_empleado,
        ':id' => $id
    ]);

    header('Location: index.php?status=success&message=Empleado actualizado correctamente.');
    exit();

} catch (PDOException $e) {
    header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit();
}
?>
