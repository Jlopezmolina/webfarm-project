<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dato1 = isset($_POST['dato1']) ? htmlspecialchars($_POST['dato1']) : '';
    $dato2 = isset($_POST['dato2']) ? htmlspecialchars($_POST['dato2']) : '';

    echo "<h2>Datos Recibidos:</h2>";
    echo "<p>Dato 1: " . $dato1 . "</p>";
    echo "<p>Dato 2: " . $dato2 . "</p>";
    echo "<hr>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario</title>
</head>
<body>
    <h1>Formulario de prueba para el test de carga</h1>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div>
            <label for="dato1">Data 1:</label>
            <input type="text" id="dato1" name="dato1" value="Hola">
        </div>
        <br>
        <div>
            <label for="dato2">Data 2:</label>
            <input type="text" id="dato2" name="dato2" value="Mundo">
        </div>
        <br>
        <div>
            <input type="submit" value="Submit">
        </div>
    </form>
</body>
</html>