<?php
// temp_migrate_roles.php
// Script de un solo uso para migrar del sistema de roles antiguo al nuevo.

require_once 'auth.php';
require_login();
require_role(['Admin']); // Solo un admin puede ejecutar esta migración

echo "<!DOCTYPE html><html lang='es'><head><title>Migración de Roles</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head><body class='bg-light'><div class='container mt-5'><div class='card'><div class='card-header'><h2>Proceso de Migración de Roles</h2></div><div class='card-body'>";

try {
    $pdo->beginTransaction();

    // --- PASO 1: Definir y crear los nuevos roles ---
    echo "<h4>Paso 1: Creando Roles</h4><ul class='list-group mb-3'>";
    $roles_a_crear = [
        'Admin' => 'Acceso total al sistema.',
        'Supervisor' => 'Gestiona órdenes y aprueba horas.',
        'Inspector' => 'Registra horas de trabajo.',
        'Contabilidad' => 'Procesa nóminas y gestiona catálogos.',
        'ReporteHorasExtras' => 'Puede ver reportes específicos de horas extra.'
    ];

    $stmt_insert_rol = $pdo->prepare("INSERT INTO roles (nombre_rol, descripcion) VALUES (?, ?)");
    foreach ($roles_a_crear as $nombre => $descripcion) {
        try {
            $stmt_insert_rol->execute([$nombre, $descripcion]);
            echo "<li class='list-group-item list-group-item-success'>Rol '{$nombre}' creado.</li>";
        } catch (PDOException $e) {
            // Ignorar error si el rol ya existe (código 23000)
            if ($e->getCode() == 23000) {
                echo "<li class='list-group-item list-group-item-warning'>Rol '{$nombre}' ya existía. Omitiendo.</li>";
            } else {
                throw $e; // Lanzar otros errores
            }
        }
    }
    echo "</ul>";

    // --- PASO 2: Obtener los nuevos IDs de los roles ---
    $roles_en_db = $pdo->query("SELECT id, nombre_rol FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);

    // --- PASO 3: Migrar usuarios existentes ---
    echo "<h4>Paso 2: Migrando Usuarios</h4><ul class='list-group mb-3'>";
    $stmt_usuarios = $pdo->query("SELECT id, nombre_usuario, rol FROM usuarios WHERE rol IS NOT NULL AND rol != ''");
    $usuarios_a_migrar = $stmt_usuarios->fetchAll();

    if (empty($usuarios_a_migrar)) {
        echo "<li class='list-group-item'>No se encontraron usuarios con roles antiguos para migrar.</li>";
    } else {
        $stmt_asignar_rol = $pdo->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)");
        $pdo->exec("DELETE FROM usuario_rol"); // Limpiar asignaciones previas para evitar duplicados

        foreach ($usuarios_a_migrar as $usuario) {
            $rol_antiguo = $usuario['rol'];
            if (isset($roles_en_db[$rol_antiguo])) {
                $nuevo_rol_id = $roles_en_db[$rol_antiguo];
                $stmt_asignar_rol->execute([$usuario['id'], $nuevo_rol_id]);
                echo "<li class='list-group-item list-group-item-success'>Usuario '{$usuario['nombre_usuario']}' migrado al rol '{$rol_antiguo}'.</li>";
            } else {
                echo "<li class='list-group-item list-group-item-danger'>Usuario '{$usuario['nombre_usuario']}' tiene un rol antiguo ('{$rol_antiguo}') que no existe en la nueva tabla. Omitiendo.</li>";
            }
        }
    }
    echo "</ul>";

    $pdo->commit();

    echo "<div class='alert alert-success mt-4'>";
    echo "<h4>¡Migración completada exitosamente!</h4>";
    echo "<p>Tus usuarios ahora están asignados a los nuevos roles.</p>";
    echo "<hr><p class='mb-0'><strong>Siguiente Paso:</strong> Una vez que confirmes que todo funciona, debes ejecutar el siguiente comando SQL en tu base de datos para eliminar la columna obsoleta:</p>";
    echo "<pre class='mt-2'><code>ALTER TABLE `usuarios` DROP COLUMN `rol`;</code></pre>";
    echo "</div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div class='alert alert-danger'><h4>Ocurrió un error</h4><p>No se pudo completar la migración. Mensaje: " . $e->getMessage() . "</p></div>";
}

echo "</div></div><a href='index.php' class='btn btn-primary mt-3'>Volver al Inicio</a></div></body></html>";
