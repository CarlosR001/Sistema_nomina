<?php
// configuracion/store.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configs'])) {
    
    try {
        $pdo->beginTransaction();

        $sql = "UPDATE ConfiguracionGlobal SET valor = :valor WHERE clave = :clave";
        $stmt = $pdo->prepare($sql);

        foreach ($_POST['configs'] as $clave => $valor) {
            $stmt->execute([':valor' => trim($valor), ':clave' => $clave]);
        }

        $pdo->commit();
        header("Location: index.php?status=success&message=Configuraci%C3%B3n%20actualizada%20exitosamente.");

    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: index.php?status=error&message=" . urlencode("Error al actualizar la configuración: " . $e->getMessage()));
    }
    exit();
}

header("Location: index.php");
exit();
