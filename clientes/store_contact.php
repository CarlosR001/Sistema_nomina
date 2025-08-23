<?php
// clientes/store_contact.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = $_POST['ID_Custumer'] ?? null;
    $nombre = $_POST['Contact_Name'] ?? '';
    $posicion = $_POST['Position'] ?? null;
    $email = $_POST['Email'] ?? null;
    $telefono = $_POST['Phone_Number'] ?? '';

    if (empty($id_cliente) || empty($nombre) || empty($telefono)) {
        header('Location: edit.php?id=' . $id_cliente . '&status=error&message=' . urlencode('Faltan datos obligatorios.'));
        exit;
    }

    // Generar un código de contacto único (ej: CUST1-CONTACT123)
    $cod_contact = 'CUST' . $id_cliente . '-CONTACT' . time();
    $fecha_actual = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO TB_Contact (cod_Contact, Contact_Name, Position, Email, Phone_Number, ID_Custumer, Contact_Date_Release, Contact_Delete) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([$cod_contact, $nombre, $posicion, $email, $telefono, $id_cliente, $fecha_actual]);

        header('Location: edit.php?id=' . $id_cliente . '&status=success&message=' . urlencode('Contacto añadido correctamente.'));
        exit;
    } catch (PDOException $e) {
        header('Location: edit.php?id=' . $id_cliente . '&status=error&message=' . urlencode('Error al guardar el contacto: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
