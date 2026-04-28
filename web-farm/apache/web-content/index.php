<Doctype html>
<html>
    <head>
        <title>SWAP - P1</title>
    </head>
    <body>
        <h1>Práctica SWAP - Jorge López Molina</h1>
        <p>Dirección IP del servidor Apache: <?php echo $_SERVER['SERVER_ADDR']; ?></p>
        <p>Software del servidor: <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
        <p>El nombre del contenedor que lo está sirviendo es <?php echo gethostname(); ?></p>
        <!-- Esto es GPT made -->
        <p>Número del contenedor: Web
        <?php 
            $ip_parts = explode('.', $_SERVER['SERVER_ADDR']);
            $container_number = end($ip_parts) - 1;
            echo $container_number; 
        ?>

        <a href="paginaAux1.php">Auxiliar 1</a>
        <a href="paginaAux2.php">Auxiliar 2</a>
        <a href="formulario.php">formulario</a>        
        </p>
    </body>
</html>

<!-- Se ha añadido información respecto al index.php usado en la práctica 1
 para hacer más reconocible el reparto de peticiones a los contenedores. -->
