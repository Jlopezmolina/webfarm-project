<?php
// paginaAux1.php

// Simula una página auxiliar para probar un test de carga con Locust
// Esta página está diseñada para ser enlazada desde un index.php existente

// Configuración básica
$pageTitle = "Página Auxiliar 1";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
</head>
<body>
    <h1><?php echo $pageTitle; ?></h1>
    <p>Esta es una página auxiliar diseñada para pruebas de carga.</p>
    <p><a href="index.php">Volver al inicio</a></p>
</body>
</html>