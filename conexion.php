<?php
// Datos de acceso a la base de datos local en XAMPP
$servidor = "localhost";
$usuario = "root";
$password = "";
$base_datos = "aulas_c_tics_mlmg"; // El nombre exacto de tu base de datos en phpMyAdmin

try {
    // Creamos la conexión usando PDO (PHP Data Objects)
    // PDO es el estándar moderno y seguro en PHP para conectar bases de datos
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8", $usuario, $password);
    
    // Configuramos los errores para que PHP nos avise si algo falla en SQL
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    //echo "¡Conexión exitosa a la base de datos <strong>$base_datos</strong>!";

} catch (PDOException $e) {
    // Si hay un error, lo mostramos claramente
    die("Error de conexión: " . $e->getMessage());
}
?>