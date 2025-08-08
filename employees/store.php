<?php
// employees/store.php
// v1.3 - Implementa la lógica de validación correcta y definitiva para Cédula y NSS.

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Recoger datos del formulario
$cedula = trim($_POST['cedula']);
$nombres = trim($_POST['nombres']);
$primer_apellido = trim($_POST['primer_apellido']);
$nss = trim($_POST['nss'] ?? '');
// ... resto de los campos ...
$email_personal = trim($_POST['email_personal'] ?? '');
$segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
$fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
$sexo = $_POST['sexo'] ?? 'Masculino';
$direccion_completa = trim($_POST['direccion_completa'] ?? '');
$telefono_principal = trim($_POST['telefono_principal'] ?? '');
$id_banco = !empty($_POST['id_banco']) ? $_POST['id_banco'] : null;
$tipo_cuenta_bancaria = !empty($_POST['tipo_cuenta_bancaria']) ? $_POST['tipo_cuenta_bancaria'] : null;
$numero_cuenta_bancaria = trim($_POST['numero_cuenta_bancaria'] ?? '');


if (empty($cedula) || empty($nombres) || empty($primer_apellido)) {
    header("Location: create.php?status=error&message=" . urlencode("Los campos Cédula, Nombres y Primer Apellido son obligatorios."));
    exit();
}

try {
    // --- LÓGICA DE VALIDACIÓN CORREGIDA ---
    
    // 1. Validar que la CÉDULA sea única (siempre)
    $stmt_check_cedula = $pdo->prepare("SELECT id FROM empleados WHERE cedula = ?");
    $stmt_check_cedula->execute([$cedula]);
    if ($stmt_check_cedula->fetch()) {
        header("Location: create.php?status=error&message=" . urlencode("La Cédula introducida ya está registrada."));
        exit();
    }

    // 2. Si se proporcionó un NSS, validar que sea único
    if (!empty($nss)) {
        $stmt_check_nss = $pdo->prepare("SELECT id FROM empleados WHERE nss = ?");
        $stmt_check_nss->execute([$nss]);
        if ($stmt_check_nss->fetch()) {
            header("Location: create.php?status=error&message=" . urlencode("El NSS introducido ya está registrado para otro empleado."));
            exit();
        }
    }
    // --- FIN DE LA CORRECCIÓN ---

    $sql = "INSERT INTO empleados 
                (cedula, nss, nombres, primer_apellido, segundo_apellido, fecha_nacimiento, sexo, direccion_completa, telefono_principal, email_personal, id_banco, tipo_cuenta_bancaria, numero_cuenta_bancaria, estado_empleado) 
            VALUES 
                (:cedula, :nss, :nombres, :primer_apellido, :segundo_apellido, :fecha_nacimiento, :sexo, :direccion_completa, :telefono_principal, :email_personal, :id_banco, :tipo_cuenta_bancaria, :numero_cuenta_bancaria, 'Activo')";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':cedula' => $cedula,
        ':nss' => !empty($nss) ? $nss : null,
        ':nombres' => $nombres,
        ':primer_apellido' => $primer_apellido,
        ':segundo_apellido' => $segundo_apellido,
        ':fecha_nacimiento' => $fecha_nacimiento,
        ':sexo' => $sexo,
        ':direccion_completa' => $direccion_completa,
        ':telefono_principal' => $telefono_principal,
        ':email_personal' => $email_personal,
        ':id_banco' => $id_banco,
        ':tipo_cuenta_bancaria' => $tipo_cuenta_bancaria,
        ':numero_cuenta_bancaria' => $numero_cuenta_bancaria
    ]);

    header("Location: index.php?status=success&message=Empleado creado exitosamente.");
    exit();

} catch (PDOException $e) {
    error_log("Error al crear empleado: " . $e->getMessage());
    header("Location: create.php?status=error&message=" . urlencode("Ocurrió un error de base de datos."));
    exit();
}
