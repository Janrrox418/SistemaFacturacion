<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    header('Location: login.php');
    exit();
}

header('Location: ../index.php');
exit();
