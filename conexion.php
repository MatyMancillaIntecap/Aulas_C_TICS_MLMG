<?php
$host = "localhost";
$dbname = "aulas_c_tics_mlmg";
$username = "root";
$password = "";

try {
    // PDO (PHP Data Objects) es nuestro traductor oficial y seguro para hablar con MySQL
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configuramos los errores para que PHP nos avise inmediatamente si algo falla
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Al quitarle las barras, le decimos que imprima este mensaje en pantalla
    //echo "¡Conexión exitosa a la base de datos!";
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
?>